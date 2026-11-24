<?php
require_once __DIR__ . '/../auth/autenticacion.php';
$u = requerirSesionApi(['paciente', 'personal']); // o solo ['personal'] según el endpoint
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../baseDatos/conexionBaseDatos.php';
require_once __DIR__ . '/../modelosDAO/newUserPacDAO.php';

$cons     = new conexionBaseDatos();
$conexion = $cons->getConexion();
$dao      = new NewUsersPacDAO($conexion);
$metodo   = $_SERVER['REQUEST_METHOD'];

try {
    switch ($metodo) {

        // =====================================================================
        // 1. ALTA (POST): Registra Persona + Paciente en una sola transacción
        // =====================================================================
        case 'POST':
            $datos = $cons->leerJSON();
            if (!$datos) {
                $cons->responder(400, ['error' => 'JSON inválido o vacío']);
            }

            $requeridos = ['nombres', 'primerAP', 'fechaNaci', 'Direccion', 'numSeguro'];
            foreach ($requeridos as $campo) {
                if (!isset($datos[$campo]) || trim((string) $datos[$campo]) === '') {
                    $cons->responder(400, ['error' => "El campo '$campo' es obligatorio"]);
                }
            }

            $grupSanguineo = $datos['grupSanginio'] ?? ($datos['grupSangineo'] ?? null);

            $persona = new persona(
                trim($datos['nombres']),
                trim($datos['primerAP']),
                trim($datos['segundoAP'] ?? ''),
                $datos['fechaNaci'],
                $datos['genero'] ?? null,
                trim($datos['Direccion'])
            );

            $paciente = new paciente(
                null,
                trim($datos['numSeguro']),
                $grupSanguineo,
                trim($datos['alergias'] ?? '')
            );

            $nuevoRegistro = new NewUsersPac($persona, $paciente);
            $idGenerado    = $dao->insertarRetornandoId($nuevoRegistro);

            if ($idGenerado !== null) {
                $cons->responder(201, [
                    'mensaje' => 'Paciente registrado correctamente',
                    'id'      => $idGenerado
                ]);
            } else {
                $cons->responder(500, ['error' => 'No se pudo registrar el paciente']);
            }
            break;

        // =====================================================================
        // 2. CONSULTA (GET): Todos, individual por ID o búsqueda con filtro
        // =====================================================================
        case 'GET':
            $datos = $cons->leerJSON() ?? [];

            $idBuscado   = $datos['id'] ?? ($datos['personaID'] ?? null);
            $tieneFiltro = isset($datos['filtro']) || isset($datos['valor']);

            // MODO A: Consulta individual por ID
            if ($idBuscado !== null && $idBuscado !== '') {
                if (!is_numeric($idBuscado) || (int) $idBuscado <= 0) {
                    $cons->responder(400, ['error' => 'El identificador debe ser un número entero positivo']);
                }

                $id       = (int) $idBuscado;
                $registro = $dao->obtenerPorId($id);

                if (!$registro) {
                    $cons->responder(404, ['error' => "No se encontró el paciente con ID $id"]);
                }

                $p  = $registro->getPersona();
                $pa = $registro->getPaciente();

                $cons->responder(200, [
                    'id'           => $pa->getIDPersona(),
                    'nombres'      => $p->getNombres(),
                    'primerAP'     => $p->getPrimerApellido(),
                    'segundoAP'    => $p->getSegundoApellido(),
                    'fechaNaci'    => $p->getFechaNacimiento(),
                    'genero'       => $p->getGenero(),
                    'Direccion'    => $p->getCURP(),
                    'numSeguro'    => $pa->getNumSeg(),
                    'grupSangineo' => $pa->getGrupSangineo(),
                    'alergias'     => $pa->getAlergias()
                ]);
            }

            // MODO B: Consulta con filtro y valor
            if ($tieneFiltro) {
                if (empty($datos['filtro']) || !isset($datos['valor']) || trim((string) $datos['valor']) === '') {
                    $cons->responder(400, ['error' => 'Debes enviar ambos parámetros en el JSON: "filtro" y "valor"']);
                }

                $filtrosPermitidos = [
                    'id'               => 'p.id',
                    'personaID'        => 'pa.id_persona',
                    'nombres'          => 'p.nombres',
                    'primerAP'         => 'p.primer_apellido',
                    'primer_apellido'  => 'p.primer_apellido',
                    'segundoAP'        => 'p.segundo_apellido',
                    'segundo_apellido' => 'p.segundo_apellido',
                    'fechaNaci'        => 'p.fecha_nacimiento',
                    'genero'           => 'p.genero',
                    'Direccion'        => 'p.direccion',
                    'direccion'        => 'p.direccion',
                    'numSeguro'        => 'pa.num_seguro',
                    'grupSanginio'     => 'pa.grupo_sanguineo',
                    'grupSangineo'     => 'pa.grupo_sanguineo',
                    'alergias'         => 'pa.alergias'
                ];

                $filtro = trim($datos['filtro']);
                $valor  = trim((string) $datos['valor']);

                if (!array_key_exists($filtro, $filtrosPermitidos)) {
                    $cons->responder(400, [
                        'error'              => "El filtro '$filtro' no es válido",
                        'filtros_permitidos' => array_keys($filtrosPermitidos)
                    ]);
                }

                $columnaSQL = $filtrosPermitidos[$filtro];
                $esNumerico = is_numeric($valor) && in_array($filtro, ['id', 'personaID'], true);
                $operador   = $esNumerico ? '=' : 'LIKE';
                $paramValor = $esNumerico ? $valor : "%$valor%";

                $sql = "SELECT p.id, p.nombres, p.primer_apellido, p.segundo_apellido, 
                               p.fecha_nacimiento, p.genero, p.direccion,
                               pa.num_seguro, pa.grupo_sanguineo, pa.alergias
                        FROM pacientes pa
                        INNER JOIN personas p ON pa.id_persona = p.id
                        WHERE $columnaSQL $operador :valor";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([':valor' => $paramValor]);

                $cons->responder(200, $stmt->fetchAll(PDO::FETCH_ASSOC));
            }

            // MODO C: Consulta general (todos los pacientes)
            $cons->responder(200, $dao->obtenerTodos());
            break;

        // =====================================================================
        // 3. CAMBIO COMPLETO (PUT): Actualiza todos los datos personales y clínicos
        // =====================================================================
        case 'PUT':
            $datos = $cons->leerJSON();
            if (!$datos) {
                $cons->responder(400, ['error' => 'JSON inválido o vacío']);
            }

            $idRaw = $datos['id'] ?? ($datos['personaID'] ?? null);
            if (!is_numeric($idRaw) || (int) $idRaw <= 0) {
                $cons->responder(400, ['error' => 'Debes enviar un "id" o "personaID" válido']);
            }

            $id = (int) $idRaw;

            $requeridos = ['nombres', 'primerAP', 'fechaNaci', 'Direccion', 'numSeguro'];
            foreach ($requeridos as $campo) {
                if (!isset($datos[$campo]) || trim((string) $datos[$campo]) === '') {
                    $cons->responder(400, ['error' => "El campo '$campo' es obligatorio para actualización completa"]);
                }
            }

            if (!$dao->obtenerPorId($id)) {
                $cons->responder(404, ['error' => "No se encontró el paciente con ID $id"]);
            }

            $grupSanguineo = $datos['grupSanginio'] ?? ($datos['grupSangineo'] ?? null);

            $persona = new persona(
                trim($datos['nombres']),
                trim($datos['primerAP']),
                trim($datos['segundoAP'] ?? ''),
                $datos['fechaNaci'],
                $datos['genero'] ?? null,
                trim($datos['Direccion'])
            );

            $paciente = new paciente(
                $id,
                trim($datos['numSeguro']),
                $grupSanguineo,
                trim($datos['alergias'] ?? '')
            );

            $registroActualizado = new NewUsersPac($persona, $paciente);

            if ($dao->actualizar($registroActualizado)) {
                $cons->responder(200, [
                    'mensaje' => 'Datos del paciente actualizados correctamente',
                    'id'      => $id
                ]);
            } else {
                $cons->responder(500, ['error' => 'No se pudo actualizar el paciente']);
            }
            break;

        // =====================================================================
        // 4. CAMBIO PARCIAL (PATCH): Solo Direccion, numSeguro y/o alergias
        // =====================================================================
        case 'PATCH':
            $datos = $cons->leerJSON();
            if (!$datos) {
                $cons->responder(400, ['error' => 'JSON inválido o vacío']);
            }

            $idRaw = $datos['id'] ?? ($datos['personaID'] ?? null);
            if (!is_numeric($idRaw) || (int) $idRaw <= 0) {
                $cons->responder(400, ['error' => 'Debes enviar un "id" o "personaID" válido']);
            }

            $id = (int) $idRaw;

            // Validar que solo vengan claves de identificación y campos permitidos
            $clavesPermitidas = ['id', 'personaID', 'Direccion', 'numSeguro', 'alergias'];
            $clavesRecibidas  = array_keys($datos);
            $clavesNoValidas  = array_diff($clavesRecibidas, $clavesPermitidas);

            if (!empty($clavesNoValidas)) {
                $cons->responder(400, [
                    'error'             => 'Campos no permitidos en cambio parcial. Solo se permite: Direccion, numSeguro, alergias.',
                    'campos_invalidos'  => array_values($clavesNoValidas)
                ]);
            }

            $camposEditables = array_intersect($clavesRecibidas, ['Direccion', 'numSeguro', 'alergias']);
            if (empty($camposEditables)) {
                $cons->responder(400, ['error' => 'Debes enviar al menos un campo a modificar (Direccion, numSeguro o alergias)']);
            }

            $registro = $dao->obtenerPorId($id);
            if (!$registro) {
                $cons->responder(404, ['error' => "No se encontró el paciente con ID $id"]);
            }

            $persona  = $registro->getPersona();
            $paciente = $registro->getPaciente();

            if (array_key_exists('Direccion', $datos)) {
                $persona->setCURP(trim($datos['Direccion']));
            }
            if (array_key_exists('numSeguro', $datos)) {
                $paciente->setNumSeg(trim($datos['numSeguro']));
            }
            if (array_key_exists('alergias', $datos)) {
                $paciente->setAlergias(trim($datos['alergias']));
            }

            if ($dao->actualizar($registro)) {
                $cons->responder(200, [
                    'mensaje' => 'Cambio parcial aplicado correctamente',
                    'id'      => $id
                ]);
            } else {
                $cons->responder(500, ['error' => 'No se pudo aplicar el cambio parcial']);
            }
            break;

        // =====================================================================
        // 5. BAJA (DELETE): Elimina registro en pacientes y en personas
        // =====================================================================
        case 'DELETE':
            $datos = $cons->leerJSON();
            if (!$datos) {
                $cons->responder(400, ['error' => 'JSON inválido o vacío']);
            }

            $idRaw = $datos['id'] ?? ($datos['personaID'] ?? null);
            if (!is_numeric($idRaw) || (int) $idRaw <= 0) {
                $cons->responder(400, ['error' => 'Debes enviar un "id" o "personaID" válido']);
            }

            $id = (int) $idRaw;

            if (!$dao->obtenerPorId($id)) {
                $cons->responder(404, ['error' => "No se encontró el paciente con ID $id"]);
            }

            if ($dao->eliminar($id)) {
                $cons->responder(200, [
                    'mensaje' => 'Paciente eliminado correctamente',
                    'id'      => $id
                ]);
            } else {
                $cons->responder(500, ['error' => 'No se pudo eliminar el paciente']);
            }
            break;

        default:
            $cons->responder(405, ['error' => 'Método no permitido. Usa GET, POST, PUT, PATCH o DELETE.']);
            break;
    }

} catch (PDOException $e) {
    // Código 23000 suele corresponder a duplicados (ej. num_seguro UNIQUE) o llaves foráneas (ej. citas asociadas)
    $codigoHttp = ($e->getCode() === '23000') ? 409 : 500;
    $cons->responder($codigoHttp, [
        'error'   => 'Error de integridad o base de datos',
        'detalle' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    $cons->responder(500, [
        'error'   => 'Error interno en el servidor',
        'detalle' => $e->getMessage()
    ]);
}
?>