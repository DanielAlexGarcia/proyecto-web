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

// Para cambios completos, Persona y Cita requieren incluir su 'id'
$estructuraDatos = [
    0 => ['id','nombres', 'primerAP', 'segundoAP', 'fechaNaci', 'genero', 'Direccion'], // Persona    
    1 => ['personaID', 'cargo', 'especializacion', 'salario'],                                    // Empleado
    2 => ['personaID', 'numSeguro', 'grupSanginio', 'alergias'],                                  // Paciente
    3 => ['id', 'fechaHora', 'motivo', 'estado', 'pacienteID', 'doctorID']                        // Cita
];

if ($metodo === 'PUT') {
    $datos = $cons->leerJSON();

    if (!$datos) {
        $cons->responder(400, ['error' => 'JSON inválido o vacío']);
    }

    // Identificar qué estructura coincide con las llaves recibidas en el JSON
    $tipoEntidad = null;
    $clavesRecibidas = array_keys($datos);

    foreach ($estructuraDatos as $indice => $camposEsperados) {
        if (
            count($camposEsperados) === count($clavesRecibidas) &&
            empty(array_diff($camposEsperados, $clavesRecibidas))
        ) {
            $tipoEntidad = $indice;
            break;
        }
    }

    if ($tipoEntidad === null) {
        $cons->responder(400, [
            'error' => 'La estructura del JSON no coincide con ningún modelo registrado',
            'claves_recibidas' => $clavesRecibidas
        ]);
    }

    try {
        $exito = false;
        $entidadNombre = '';
        $idActualizado = null;

        switch ($tipoEntidad) {
            case 0: // Cambio de Persona
                $entidadNombre = 'Persona';
                $idActualizado = (int) $datos['id'];
                $persona = new persona(
                    $datos['nombres'],
                    $datos['primerAP'],
                    $datos['segundoAP'],
                    $datos['fechaNaci'],
                    $datos['genero'],
                    $datos['Direccion']
                );
                $dao = new PersonaDAO($conexion);
                $exito = $dao->actualizar($persona, $idActualizado);
                break;

            case 1: // Cambio de Empleado
                $entidadNombre = 'Empleado';
                $idActualizado = (int) $datos['personaID'];
                $empleado = new empleado(
                    $idActualizado,
                    $datos['cargo'],
                    $datos['especializacion'],
                    (float) $datos['salario']
                );
                $dao = new EmpleadoDAO($conexion);
                $exito = $dao->actualizar($empleado);
                break;

            case 2: // Cambio de Paciente
                $entidadNombre = 'Paciente';
                $idActualizado = (int) $datos['personaID'];
                $paciente = new paciente(
                    $idActualizado,
                    $datos['numSeguro'],
                    $datos['grupSanginio'],
                    $datos['alergias']
                );
                $dao = new PacienteDAO($conexion);
                $exito = $dao->actualizar($paciente);
                break;

            case 3: // Cambio de Cita
                $entidadNombre = 'Cita';
                $idActualizado = (int) $datos['id'];
                $cita = new Cita(
                    $datos['fechaHora'],
                    $datos['motivo'],
                    $datos['estado'],
                    (int) $datos['pacienteID'],
                    (int) $datos['doctorID']
                );
                $dao = new CitaDAO($conexion);
                $exito = $dao->actualizar($cita, $idActualizado);
                break;
        }

        if ($exito) {
            $cons->responder(200, [
                'mensaje' => "$entidadNombre actualizada correctamente",
                'id' => $idActualizado
            ]);
        } else {
            $cons->responder(500, ['error' => "No se pudo actualizar $entidadNombre"]);
        }

    } catch (PDOException $e) {
        $cons->responder(500, [
            'error' => 'Error en la base de datos al realizar el cambio',
            'detalle' => $e->getMessage()
        ]);
    }

} else {
    $cons->responder(405, ['error' => 'Método no permitido. Usa PUT.']);
}
?>