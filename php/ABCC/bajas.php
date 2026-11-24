<?php
header('Content-Type: application/json; charset=utf-8');

include_once('../baseDatos/conexionBaseDatos.php');

// Cargar automáticamente todos los modelos y DAOs
foreach (glob('../modelos/*.php') as $modelo) {
    require_once $modelo;
}
foreach (glob('../modelosDAO/*.php') as $dao) {
    require_once $dao;
}

$cons = new conexionBaseDatos();
$conexion = $cons->getConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

// Estructura esperada en la petición (ej. {"entidad": "persona", "id": 1})
$estructuraDatos = [
    ['entidad', 'id']
];

if ($metodo === 'DELETE') {
    // Lee el cuerpo JSON; si viene vacío, intenta leer parámetros por URL (?entidad=...&id=...)
    $datos = $cons->leerJSON();
    if (!$datos && !empty($_GET)) {
        $datos = $_GET;
    }

    if (!$datos) {
        $cons->responder(400, ['error' => 'JSON inválido, vacío o sin parámetros de identificación']);
    }

    // Validar que se hayan recibido exactamente las claves esperadas
    $clavesRecibidas = array_keys($datos);
    $camposEsperados = $estructuraDatos[0];

    if (
        count($camposEsperados) !== count($clavesRecibidas) ||
        !empty(array_diff($camposEsperados, $clavesRecibidas))
    ) {
        $cons->responder(400, [
            'error' => 'La estructura no coincide. Se requiere "entidad" e "id".',
            'claves_recibidas' => $clavesRecibidas
        ]);
    }

    if (!is_numeric($datos['id']) || (int) $datos['id'] <= 0) {
        $cons->responder(400, ['error' => 'El parámetro "id" debe ser un número entero positivo']);
    }

    $id = (int) $datos['id'];
    $entidad = strtolower(trim($datos['entidad']));

    try {
        $exito = false;
        $entidadNombre = '';

        switch ($entidad) {
            case 'persona':
                $entidadNombre = 'Persona';
                $dao = new PersonaDAO($conexion);
                $exito = $dao->eliminar($id);
                break;

            case 'empleado':
                $entidadNombre = 'Empleado';
                $dao = new EmpleadoDAO($conexion);
                $exito = $dao->eliminar($id);
                break;

            case 'paciente':
                $entidadNombre = 'Paciente';
                $dao = new PacienteDAO($conexion);
                $exito = $dao->eliminar($id);
                break;

            case 'cita':
                $entidadNombre = 'Cita';
                $dao = new CitaDAO($conexion);
                $exito = $dao->eliminar($id);
                break;

            default:
                $cons->responder(400, [
                    'error' => "Entidad '$entidad' no reconocida. Valores válidos: persona, empleado, paciente, cita."
                ]);
                break;
        }

        if ($exito) {
            $cons->responder(200, [
                'mensaje' => "Baja de $entidadNombre procesada correctamente",
                'id' => $id
            ]);
        } else {
            $cons->responder(500, ['error' => "No se pudo eliminar el registro de $entidadNombre"]);
        }

    } catch (PDOException $e) {
        // Captura restricciones de llave foránea (ej. intentar borrar un doctor que tiene citas asignadas)
        $cons->responder(409, [
            'error' => 'No se puede eliminar el registro porque está asociado a otros datos en el sistema',
            'detalle' => $e->getMessage()
        ]);
    }

} else {
    $cons->responder(405, ['error' => 'Método no permitido. Usa DELETE.']);
}
?>