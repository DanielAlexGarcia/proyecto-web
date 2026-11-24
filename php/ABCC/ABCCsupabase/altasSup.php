<?php
// php/ABCC/altas.php
// Endpoint de ALTAS. Recibe JSON por POST y responde JSON.

//ini_set('display_errors', 1);
//ini_set('log_errors', 1);
//error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Asegúrate de apuntar al archivo de conexión configurado para Supabase
require_once __DIR__ . '/../../baseDatos/BDsupabase.php';
require_once __DIR__ . '/../../modelos/persona.php';
require_once __DIR__ . '/../../modelos/paciente.php';
require_once __DIR__ . '/../../modelos/empleado.php';
require_once __DIR__ . '/../../modelos/cita.php';
require_once __DIR__ . '/../../modelosDAO/modelosDAOSupabase/personaDAO.php';
require_once __DIR__ . '/../../modelosDAO/modelosDAOSupabase/pacienteDAO.php';
require_once __DIR__ . '/../../modelosDAO/modelosDAOSupabase/empleadoDAO.php';
require_once __DIR__ . '/../../modelosDAO/modelosDAOSupabase/citaDAO.php';

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

            // Usa insertarRetornandoId() (con RETURNING id) en lugar de lastInsertId()
            $idPersona = (new PersonaDAO($pdo))->insertarRetornandoId($persona);

            $db->responder(201, [
                'ok'        => true,
                'mensaje'   => 'Persona registrada.',
                'idPersona' => $idPersona
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

            // Usa insertarRetornandoId() en CitaDAO en lugar de lastInsertId()
            $idCita = (new CitaDAO($pdo))->insertar($cita);

            $db->responder(201, [
                'ok'      => true,
                'mensaje' => 'Cita registrada.',
                'idCita'  => $idCita
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
    // Códigos SQLSTATE estándar de PostgreSQL / Supabase
    $sqlState = $e->errorInfo[0] ?? $e->getCode();

    if ($sqlState === 'P0001' || $sqlState === '45000') {
        // Error lanzado con RAISE EXCEPTION dentro de un procedimiento almacenado
        $mensajeLimpio = preg_replace('/^ERROR:\s*/i', '', strtok($e->errorInfo[2] ?? $e->getMessage(), "\n"));
        $db->responder(404, ['ok' => false, 'error' => trim($mensajeLimpio)]);
    } elseif ($sqlState === '23505') {
        // unique_violation (equivalente a 1062 en MySQL)
        $db->responder(409, [
            'ok'      => false,
            'error'   => 'Registro duplicado (num_seguro, usuario o la persona ya tiene ese rol).',
            'detalle' => $e->getMessage()
        ]);
    } elseif ($sqlState === '23503') {
        // foreign_key_violation (equivalente a 1452 en MySQL)
        $db->responder(422, [
            'ok'      => false,
            'error'   => 'Llave foránea inválida: el paciente, doctor o persona indicado no existe.',
            'detalle' => $e->getMessage()
        ]);
    } elseif ($sqlState === '22P02' || $sqlState === '23514') {
        // Formato de fecha/tipo inválido o violación de restricción CHECK
        $db->responder(422, [
            'ok'      => false,
            'error'   => 'Formato de datos inválido o valor fuera de rango.',
            'detalle' => $e->getMessage()
        ]);
    } else {
        $db->responder(500, [
            'ok'      => false,
            'error'   => 'Error de base de datos.',
            'detalle' => $e->getMessage()
        ]);
    }

} catch (Throwable $e) {
    $db->responder(500, ['ok' => false, 'error' => 'Error interno.', 'detalle' => $e->getMessage()]);
}
?>