<?php
require_once __DIR__ . '/../auth/autenticacion.php';
$u = requerirSesionApi(['paciente', 'personal']); // o solo ['personal'] según el endpoint
// php/ABCC/altas.php
// Endpoint de ALTAS. Recibe JSON por POST y responde JSON.
// El campo "tipo" indica qué alta se realiza.

// Evita que warnings/notices de PHP ensucien la respuesta JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../baseDatos/conexionBaseDatos.php';
require_once __DIR__ . '/../modelos/persona.php';
require_once __DIR__ . '/../modelos/paciente.php';
require_once __DIR__ . '/../modelos/empleado.php';
require_once __DIR__ . '/../modelos/cita.php';
require_once __DIR__ . '/../modelosDAO/personaDAO.php';
require_once __DIR__ . '/../modelosDAO/pacienteDAO.php';
require_once __DIR__ . '/../modelosDAO/empleadoDAO.php';
require_once __DIR__ . '/../modelosDAO/citaDAO.php';

$u = requerirSesionApi(['paciente', 'personal']);

$db = new conexionBaseDatos();
$pdo = $db->getConexion();

// ---------------------------------------------------------------------------
// Validaciones básicas
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $db->responder(405, ['ok' => false, 'error' => 'Método no permitido. Usa POST.']);
}

$datos = $db->leerJSON();
if ($datos === null) {
    $db->responder(400, ['ok' => false, 'error' => 'El cuerpo debe ser un JSON válido (raw > JSON en Postman).']);
}

/** Devuelve los campos obligatorios que no vienen (o vienen vacíos) en $datos. */
function camposFaltantes(array $datos, array $requeridos): array
{
    $faltan = [];
    foreach ($requeridos as $campo) {
        if (!isset($datos[$campo]) || trim((string)$datos[$campo]) === '') {
            $faltan[] = $campo;
        }
    }
    return $faltan;
}

/** Responde 422 si falta algún campo obligatorio. */
function exigir(conexionBaseDatos $db, array $datos, array $requeridos): void
{
    $faltan = camposFaltantes($datos, $requeridos);
    if ($faltan) {
        $db->responder(422, [
            'ok'      => false,
            'error'   => 'Faltan campos obligatorios.',
            'faltan'  => $faltan
        ]);
    }
}

// ---------------------------------------------------------------------------
// Enrutador
// ---------------------------------------------------------------------------
$tipo = $datos['tipo'] ?? '';

$soloPersonal = ['persona', 'paciente', 'paciente_existente', 'paciente_transaccion',
                 'empleado', 'empleado_existente', 'empleado_transaccion'];

if ($u['tipo'] === 'paciente') {
    if (in_array($tipo, $soloPersonal, true)) {
        $db->responder(403, ['ok' => false, 'error' => 'No tienes permisos para esta acción.']);
    }
    if ($tipo === 'cita') {
        $datos['paciente_id'] = $u['paciente_id']; // ignora lo que mande el cliente
    }
}

try {
    switch ($tipo) {

        // ---- Persona -------------------------------------------------------
        case 'persona':
            exigir($db, $datos, ['nombres', 'primer_apellido', 'fecha_nacimiento']);

            $persona = new persona(
                trim($datos['nombres']),
                trim($datos['primer_apellido']),
                $datos['segundo_apellido'] ?? null,
                $datos['fecha_nacimiento'],
                $datos['genero'] ?? null,
                $datos['direccion'] ?? null
            );

            (new PersonaDAO($pdo))->insertar($persona);

            $db->responder(201, [
                'ok'        => true,
                'mensaje'   => 'Persona registrada.',
                'idPersona' => (int)$pdo->lastInsertId()
            ]);
            break;

        // ---- Paciente (persona existente, sin usuario) ---------------------
        case 'paciente':
            exigir($db, $datos, ['id_persona', 'num_seguro']);

            $paciente = new paciente(
                (int)$datos['id_persona'],
                $datos['num_seguro'],
                $datos['grupo_sanguineo'] ?? null,
                $datos['alergias'] ?? null
            );

            (new PacienteDAO($pdo))->insertar($paciente);

            $db->responder(201, ['ok' => true, 'mensaje' => 'Paciente registrado.']);
            break;

        // ---- Paciente con procedimiento almacenado (persona + usuario) -----
        case 'paciente_existente':
            exigir($db, $datos, ['id_persona', 'num_seguro', 'user', 'password']);

            $paciente = new paciente(
                (int)$datos['id_persona'],
                $datos['num_seguro'],
                $datos['grupo_sanguineo'] ?? null,
                $datos['alergias'] ?? null
            );

            (new PacienteDAO($pdo))->crearConPersonaExistente(
                $paciente,
                $datos['user'],
                $datos['password']
            );

            $db->responder(201, [
                'ok'      => true,
                'mensaje' => 'Paciente y usuario creados con el procedimiento almacenado.'
            ]);
            break;

        // ---- Paciente con transacción (persona nueva/existente + usuario) --
        case 'paciente_transaccion':
            exigir($db, $datos, [
                'nombres',
                'primer_apellido',
                'fecha_nacimiento',
                'num_seguro',
                'user',
                'password'
            ]);

            $paciente = new paciente(
                0,
                $datos['num_seguro'],
                $datos['grupo_sanguineo'] ?? null,
                $datos['alergias'] ?? null
            );

            (new PacienteDAO($pdo))->crearConTransaccion(
                $datos['nombres'],
                $datos['primer_apellido'],
                $datos['segundo_apellido'] ?? null,
                $datos['fecha_nacimiento'],
                $datos['genero'] ?? null,
                $datos['direccion'] ?? null,
                $paciente,
                $datos['user'],
                $datos['password']
            );

            $db->responder(201, [
                'ok'      => true,
                'mensaje' => 'Paciente y usuario creados mediante transacción.'
            ]);
            break;

        // ---- Empleado (persona existente, sin usuario) ---------------------
        case 'empleado':
            exigir($db, $datos, ['id_persona', 'cargo', 'salario']);

            $empleado = new empleado(
                (int)$datos['id_persona'],
                $datos['cargo'],
                $datos['especializacion'] ?? null,
                $datos['salario']
            );

            (new EmpleadoDAO($pdo))->insertar($empleado);

            $db->responder(201, ['ok' => true, 'mensaje' => 'Empleado registrado.']);
            break;

        // ---- Empleado con procedimiento almacenado (persona + usuario) -----
        case 'empleado_existente':
            exigir($db, $datos, ['id_persona', 'cargo', 'salario', 'user', 'password']);

            $empleado = new empleado(
                (int)$datos['id_persona'],
                $datos['cargo'],
                $datos['especializacion'] ?? null,
                $datos['salario']
            );

            (new EmpleadoDAO($pdo))->crearConPersonaExistente(
                $empleado,
                $datos['user'],
                $datos['password']
            );

            $db->responder(201, [
                'ok'      => true,
                'mensaje' => 'Empleado y usuario creados con el procedimiento almacenado.'
            ]);
            break;

        // ---- Empleado con transacción (persona nueva/existente + usuario) --
        case 'empleado_transaccion':
            exigir($db, $datos, [
                'nombres',
                'primer_apellido',
                'fecha_nacimiento',
                'cargo',
                'salario',
                'user',
                'password'
            ]);

            $empleado = new empleado(
                0,
                $datos['cargo'],
                $datos['especializacion'] ?? null,
                $datos['salario']
            );

            (new EmpleadoDAO($pdo))->crearConTransaccion(
                $datos['nombres'],
                $datos['primer_apellido'],
                $datos['segundo_apellido'] ?? null,
                $datos['fecha_nacimiento'],
                $datos['genero'] ?? null,
                $datos['direccion'] ?? null,
                $empleado,
                $datos['user'],
                $datos['password']
            );

            $db->responder(201, [
                'ok'      => true,
                'mensaje' => 'Empleado y usuario creados mediante transacción.'
            ]);
            break;

        // ---- Cita ----------------------------------------------------------
        case 'cita':
            exigir($db, $datos, ['fecha_hora', 'motivo', 'paciente_id', 'doctor_id']);

            $cita = new Cita(
                $datos['fecha_hora'],            // formato: YYYY-MM-DD HH:MM:SS
                $datos['motivo'],
                $datos['estado'] ?? 'Pendiente',
                (int)$datos['paciente_id'],
                (int)$datos['doctor_id']
            );

            (new CitaDAO($pdo))->insertar($cita);

            $db->responder(201, [
                'ok'      => true,
                'mensaje' => 'Cita registrada.',
                'idCita'  => (int)$pdo->lastInsertId()
            ]);
            break;

        // ---- Tipo desconocido ----------------------------------------------
        default:
            $db->responder(400, [
                'ok'       => false,
                'error'    => 'Campo "tipo" inválido o ausente.',
                'validos'  => [
                    'persona',
                    'paciente',
                    'paciente_existente',
                    'paciente_transaccion',
                    'empleado',
                    'empleado_existente',
                    'empleado_transaccion',
                    'cita'
                ]
            ]);
    }

} catch (PDOException $e) {
    // Código de error de MySQL (1062 = duplicado, 1452 = llave foránea, etc.)
    $codigoMySQL = $e->errorInfo[1] ?? 0;
    $sqlState    = $e->errorInfo[0] ?? '';

    if ($sqlState === '45000') {
        // Error lanzado con SIGNAL dentro del procedimiento almacenado
        $db->responder(404, ['ok' => false, 'error' => $e->errorInfo[2] ?? $e->getMessage()]);
    } elseif ($codigoMySQL === 1062) {
        $db->responder(409, ['ok' => false, 'error' => 'Registro duplicado (num_seguro, usuario o la persona ya tiene ese rol).', 'detalle' => $e->getMessage()]);
    } elseif ($codigoMySQL === 1452) {
        $db->responder(422, ['ok' => false, 'error' => 'Llave foránea inválida: el paciente, doctor o persona indicado no existe.', 'detalle' => $e->getMessage()]);
    } else {
        $db->responder(500, ['ok' => false, 'error' => 'Error de base de datos.', 'detalle' => $e->getMessage()]);
    }

} catch (Throwable $e) {
    $db->responder(500, ['ok' => false, 'error' => 'Error interno.', 'detalle' => $e->getMessage()]);
}
?>