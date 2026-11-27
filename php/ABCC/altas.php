<?php
require_once __DIR__ . '/../auth/autenticacion.php';
require_once __DIR__ . '/../baseDatos/BaseDatosFactory.php';
require_once __DIR__ . '/../validacionesCita.php';
require_once __DIR__ . '/../validaciones/validacionesPersona.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$u   = requerirSesionApi(['paciente', 'personal']);
$db  = BaseDatosFactory::conexion();
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
        if (!isset($datos[$campo]) || trim((string) $datos[$campo]) === '') {
            $faltan[] = $campo;
        }
    }
    return $faltan;
}

/** Responde 422 si falta algún campo obligatorio. */
function exigir(ConexionInterface $db, array $datos, array $requeridos): void
{
    $faltan = camposFaltantes($datos, $requeridos);
    if ($faltan) {
        $db->responder(422, [
            'ok' => false,
            'error' => 'Faltan campos obligatorios.',
            'faltan' => $faltan
        ]);
    }
}

// ---------------------------------------------------------------------------
// Enrutador
// ---------------------------------------------------------------------------
$tipo = $datos['tipo'] ?? '';

$soloPersonal = [
    'persona',
    'paciente',
    'paciente_existente',
    'paciente_transaccion',
    'empleado',
    'empleado_existente',
    'empleado_transaccion'
];
$tiposDeEmpleado = ['empleado', 'empleado_existente', 'empleado_transaccion'];
if (
    $u['tipo'] === 'personal'
    && in_array($tipo, $tiposDeEmpleado, true)
    && mb_strtolower($u['cargo'] ?? '') !== 'administrador'
) {
    $db->responder(403, ['ok' => false, 'error' => 'Solo administración puede dar de alta empleados.']);
}

if ($u['tipo'] === 'paciente') {
    if (in_array($tipo, $soloPersonal, true)) {
        $db->responder(403, ['ok' => false, 'error' => 'No tienes permisos para esta acción.']);
    }
    if ($tipo === 'cita') {
        $datos['paciente_id'] = (int) $u['paciente_id']; // ignora lo que mande el cliente
        $datos['estado'] = 'Pendiente';
    }
}

try {
    switch ($tipo) {
        case 'persona':
            ValidadorPersonas::persona($datos);
            break;
        case 'paciente':
            ValidadorPersonas::paciente($datos);
            break;
        case 'paciente_existente':
            ValidadorPersonas::paciente($datos);
            ValidadorPersonas::credenciales($datos);
            break;
        case 'paciente_transaccion':
            ValidadorPersonas::persona($datos);
            ValidadorPersonas::paciente($datos);
            ValidadorPersonas::credenciales($datos);
            break;
        case 'empleado':
            ValidadorPersonas::empleado($datos);
            break;
        case 'empleado_existente':
            ValidadorPersonas::empleado($datos);
            ValidadorPersonas::credenciales($datos);
            break;
        case 'empleado_transaccion':
            ValidadorPersonas::persona($datos);
            ValidadorPersonas::empleado($datos);
            ValidadorPersonas::credenciales($datos);
            break;
    }
} catch (InvalidArgumentException $e) {
    $db->responder(422, ['ok' => false, 'error' => $e->getMessage()]);
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

            $idPersona = BaseDatosFactory::persona($pdo)->insertarRetornandoId($persona);
            $db->responder(201, ['ok' => true, 'mensaje' => 'Persona registrada.', 'idPersona' => $idPersona]);
            break;

        // ---- Paciente (persona existente, sin usuario) ---------------------
        case 'paciente':
            exigir($db, $datos, ['id_persona', 'num_seguro']);

            $paciente = new paciente(
                (int) $datos['id_persona'],
                $datos['num_seguro'],
                $datos['grupo_sanguineo'] ?? null,
                $datos['alergias'] ?? null
            );

            BaseDatosFactory::paciente($pdo)->insertar($paciente);

            $db->responder(201, ['ok' => true, 'mensaje' => 'Paciente registrado.']);
            break;

        // ---- Paciente con procedimiento almacenado (persona + usuario) -----
        case 'paciente_existente':
            exigir($db, $datos, ['id_persona', 'num_seguro', 'user', 'password']);

            $paciente = new paciente(
                (int) $datos['id_persona'],
                $datos['num_seguro'],
                $datos['grupo_sanguineo'] ?? null,
                $datos['alergias'] ?? null
            );

            BaseDatosFactory::paciente($pdo)->crearConPersonaExistente(
                $paciente,
                $datos['user'],
                $datos['password']
            );

            $db->responder(201, [
                'ok' => true,
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

            BaseDatosFactory::paciente($pdo)->crearConTransaccion(
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
                'ok' => true,
                'mensaje' => 'Paciente y usuario creados mediante transacción.'
            ]);
            break;

        // ---- Empleado (persona existente, sin usuario) ---------------------
        case 'empleado':
            exigir($db, $datos, ['id_persona', 'cargo', 'salario']);

            $empleado = new empleado(
                (int) $datos['id_persona'],
                $datos['cargo'],
                $datos['especializacion'] ?? null,
                $datos['salario']
            );

            BaseDatosFactory::empleado($pdo)->insertar($empleado);

            $db->responder(201, ['ok' => true, 'mensaje' => 'Empleado registrado.']);
            break;

        // ---- Empleado con procedimiento almacenado (persona + usuario) -----
        case 'empleado_existente':
            exigir($db, $datos, ['id_persona', 'cargo', 'salario', 'user', 'password']);

            $empleado = new empleado(
                (int) $datos['id_persona'],
                $datos['cargo'],
                $datos['especializacion'] ?? null,
                $datos['salario']
            );

            BaseDatosFactory::empleado($pdo)->crearConPersonaExistente(
                $empleado,
                $datos['user'],
                $datos['password']
            );

            $db->responder(201, [
                'ok' => true,
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

            BaseDatosFactory::empleado($pdo)->crearConTransaccion(
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
                'ok' => true,
                'mensaje' => 'Empleado y usuario creados mediante transacción.'
            ]);
            break;

        // ---- Cita ----------------------------------------------------------
        case 'cita':
            exigir($db, $datos, ['fecha_hora', 'motivo', 'paciente_id', 'doctor_id']);
            $datos['fecha_hora'] = ValidadorCitas::validarFechaHora($datos['fecha_hora']);
            $datos['motivo'] = ValidadorCitas::validarMotivo($datos['motivo']);
            $datos['estado'] = ValidadorCitas::validarEstado($datos['estado'] ?? 'Pendiente');
            $datos['doctor_id'] = ValidadorCitas::validarEnteroPositivo($datos['doctor_id'], 'doctor_id');
            $cita = new Cita(
                $datos['fecha_hora'],            // formato: YYYY-MM-DD HH:MM:SS
                $datos['motivo'],
                $datos['estado'] ?? 'Pendiente',
                (int) $datos['paciente_id'],
                (int) $datos['doctor_id']
            );

            $idCita = BaseDatosFactory::cita($pdo)->guardarSinChoque($cita);
            if ($idCita === null) {
                $db->responder(409, ['ok' => false, 'error' => 'Ese médico ya tiene una cita en ese horario.']);
            }

            $db->responder(201, [
                'ok' => true,
                'mensaje' => 'Cita registrada.',
                'idCita' => $idCita
            ]);
            break;

        // ---- Tipo desconocido ----------------------------------------------
        default:
            $db->responder(400, [
                'ok' => false,
                'error' => 'Campo "tipo" inválido o ausente.',
                'validos' => [
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
    $t = BaseDatosFactory::tipoError($e);
    if ($t === 'regla') {
        $db->responder(404, ['ok' => false, 'error' => BaseDatosFactory::mensajeError($e)]);
    } elseif ($t === 'duplicado') {
        $db->responder(409, ['ok' => false, 'error' => 'Registro duplicado (num_seguro, usuario o la persona ya tiene ese rol).']);
    } elseif ($t === 'fk') {
        $db->responder(422, ['ok' => false, 'error' => 'Llave foránea inválida: el paciente, doctor o persona indicado no existe.']);
    } elseif ($t === 'formato') {
        $db->responder(422, ['ok' => false, 'error' => 'Formato de dato inválido.']);
    }
    $db->responder(500, ['ok' => false, 'error' => 'Error de base de datos.', 'detalle' => $e->getMessage()]);
} catch (Throwable $e) {
    $db->responder(500, ['ok' => false, 'error' => 'Error interno.', 'detalle' => $e->getMessage()]);
}
?>