<?php
require_once __DIR__ . '/../auth/autenticacion.php';
$u = requerirSesionApi(['personal']);
header('Content-Type: application/json; charset=utf-8');

include_once('/../baseDatos/conexionBaseDatos.php');

// Cargar automáticamente todos los modelos y DAOs
foreach (glob('/../modelos/*.php') as $modelo) {
    require_once $modelo;
}
foreach (glob('/../modelosDAO/*.php') as $dao) {
    require_once $dao;
}

$cons = new conexionBaseDatos();
$conexion = $cons->getConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

// Primer elemento = identificador requerido; el resto = campos permitidos para cambio parcial
$estructuraDatos = [
    0 => ['id', 'Direccion'],                              // Persona
    1 => ['personaID', 'especializacion', 'salario'],       // Empleado
    2 => ['personaID', 'numSeguro', 'alergias'],            // Paciente
    3 => ['id', 'fechaHora', 'estado', 'doctorID']          // Cita
];

if ($metodo === 'PATCH') {
    $datos = $cons->leerJSON();

    if (!$datos) {
        $cons->responder(400, ['error' => 'JSON inválido o vacío']);
    }

    // Identificar qué entidad corresponde según el ID y los campos permitidos enviados
    $tipoEntidad = null;
    $clavesRecibidas = array_keys($datos);

    foreach ($estructuraDatos as $indice => $camposPermitidos) {
        $claveId = $camposPermitidos[0];

        if (
            in_array($claveId, $clavesRecibidas, true) &&
            count($clavesRecibidas) >= 2 &&
            empty(array_diff($clavesRecibidas, $camposPermitidos))
        ) {
            $tipoEntidad = $indice;
            break;
        }
    }

    if ($tipoEntidad === null) {
        $cons->responder(400, [
            'error' => 'La estructura del JSON no coincide con los campos permitidos para cambio parcial',
            'claves_recibidas' => $clavesRecibidas
        ]);
    }

    try {
        $exito = false;
        $entidadNombre = '';
        $idActualizado = null;

        switch ($tipoEntidad) {
            case 0: // Cambio parcial de Persona (solo Direccion)
                $entidadNombre = 'Persona';
                $idActualizado = (int) $datos['id'];
                $dao = new PersonaDAO($conexion);

                $persona = $dao->obtenerPorId($idActualizado);
                if (!$persona) {
                    $cons->responder(404, ['error' => "No se encontró la persona con ID $idActualizado"]);
                }

                if (array_key_exists('Direccion', $datos)) {
                    $persona->setCURP($datos['Direccion']); // Mapeado a $Direccion en tu modelo
                }

                $exito = $dao->actualizar($persona, $idActualizado);
                break;

            case 1: // Cambio parcial de Empleado (especializacion y/o salario)
                $entidadNombre = 'Empleado';
                $idActualizado = (int) $datos['personaID'];
                $dao = new EmpleadoDAO($conexion);

                $empleado = $dao->obtenerPorId($idActualizado);
                if (!$empleado) {
                    $cons->responder(404, ['error' => "No se encontró el empleado con ID $idActualizado"]);
                }

                if (array_key_exists('especializacion', $datos)) {
                    $empleado->setEspecializacion($datos['especializacion']);
                }
                if (array_key_exists('salario', $datos)) {
                    $empleado->setSalario((float) $datos['salario']);
                }

                $exito = $dao->actualizar($empleado);
                break;

            case 2: // Cambio parcial de Paciente (numSeguro y/o alergias)
                $entidadNombre = 'Paciente';
                $idActualizado = (int) $datos['personaID'];
                $dao = new PacienteDAO($conexion);

                $paciente = $dao->obtenerPorId($idActualizado);
                if (!$paciente) {
                    $cons->responder(404, ['error' => "No se encontró el paciente con ID $idActualizado"]);
                }

                if (array_key_exists('numSeguro', $datos)) {
                    $paciente->setNumSeg($datos['numSeguro']);
                }
                if (array_key_exists('alergias', $datos)) {
                    $paciente->setAlergias($datos['alergias']);
                }

                $exito = $dao->actualizar($paciente);
                break;

            case 3: // Cambio parcial de Cita (fechaHora, estado y/o doctorID)
                $entidadNombre = 'Cita';
                $idActualizado = (int) $datos['id'];
                $dao = new CitaDAO($conexion);

                $cita = $dao->obtenerPorId($idActualizado);
                if (!$cita) {
                    $cons->responder(404, ['error' => "No se encontró la cita con ID $idActualizado"]);
                }

                if (array_key_exists('fechaHora', $datos)) {
                    $cita->setFechaHora($datos['fechaHora']);
                }
                if (array_key_exists('estado', $datos)) {
                    $cita->setEstado($datos['estado']);
                }
                if (array_key_exists('doctorID', $datos)) {
                    $cita->setDoctorID((int) $datos['doctorID']);
                }

                $exito = $dao->actualizar($cita, $idActualizado);
                break;
        }

        if ($exito) {
            $cons->responder(200, [
                'mensaje' => "Cambio parcial en $entidadNombre aplicado correctamente",
                'id' => $idActualizado
            ]);
        } else {
            $cons->responder(500, ['error' => "No se pudo aplicar el cambio parcial en $entidadNombre"]);
        }

    } catch (PDOException $e) {
        $cons->responder(500, [
            'error' => 'Error en la base de datos al realizar el cambio parcial',
            'detalle' => $e->getMessage()
        ]);
    }

} else {
    $cons->responder(405, ['error' => 'Método no permitido. Usa PATCH.']);
}
?>