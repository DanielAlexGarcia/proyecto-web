<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth/autenticacion.php';
require_once __DIR__ . '/../baseDatos/conexionBaseDatos.php';
require_once __DIR__ . '/altas/AutorizadorAltas.php';
require_once __DIR__ . '/altas/ValidadorEntradaAltas.php';
require_once __DIR__ . '/altas/ManejadorErroresApi.php';
require_once __DIR__ . '/altas/servicios/ServicioAltaPersona.php';
require_once __DIR__ . '/altas/servicios/ServicioAltaPaciente.php';
require_once __DIR__ . '/altas/servicios/ServicioAltaEmpleado.php';
require_once __DIR__ . '/altas/servicios/ServicioAltaCita.php';

$usuario = requerirSesionApi(['paciente', 'personal']);
$db = conexionBaseDatos::getInstancia();
$pdo = $db->getConexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $db->responder(405, ['ok' => false, 'error' => 'Método no permitido. Usa POST.']);
}

$datos = $db->leerJSON();
if ($datos === null) {
    $db->responder(400, ['ok' => false, 'error' => 'El cuerpo debe ser un JSON válido (raw > JSON en Postman).']);
}

$tipo = $datos['tipo'] ?? '';

try {
    // 1. Autorización y ajuste de contexto según el rol
    $datos = AutorizadorAltas::verificarYSanitizar($usuario, $tipo, $datos);

    // 2. Validación de estructura y reglas de dominio
    ValidadorEntradaAltas::validar($tipo, $datos);

    // 3. Ejecución del caso de uso correspondiente
    $respuesta = match ($tipo) {
        'persona'              => (new ServicioAltaPersona($pdo))->crear($datos),
        'paciente'             => (new ServicioAltaPaciente($pdo))->crearSimple($datos),
        'paciente_existente'   => (new ServicioAltaPaciente($pdo))->crearConProcedimiento($datos),
        'paciente_transaccion' => (new ServicioAltaPaciente($pdo))->crearConTransaccion($datos),
        'empleado'             => (new ServicioAltaEmpleado($pdo))->crearSimple($datos),
        'empleado_existente'   => (new ServicioAltaEmpleado($pdo))->crearConProcedimiento($datos),
        'empleado_transaccion' => (new ServicioAltaEmpleado($pdo))->crearConTransaccion($datos),
        'cita'                 => (new ServicioAltaCita($pdo))->crear($datos),
        default                => throw new InvalidArgumentException('TIPO_INVALIDO')
    };

    $db->responder(201, $respuesta);

} catch (Throwable $e) {
    ManejadorErroresApi::manejar($db, $e);
}