<?php
require_once __DIR__ . '/../auth/autenticacion.php';
require_once __DIR__ . '/../validacionesCita.php';
require_once __DIR__ . '/../validaciones/validacionesPersona.php';
$u = requerirSesionApi(['paciente', 'personal']); // o solo ['personal'] según el endpoint
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
    0 => ['id', 'nombres', 'primerAP', 'segundoAP', 'fechaNaci', 'genero', 'Direccion'], // Persona    
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
    $claveId = [0 => 'id', 1 => 'personaID', 2 => 'personaID', 3 => 'id'][$tipoEntidad];
ValidadorCitas::validarEnteroPositivo($datos[$claveId], $claveId);

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
    // ---- Restricciones para personal ----
    if (
        $u['tipo'] === 'personal'
        && $tipoEntidad === 1
        && mb_strtolower(trim($u['cargo'] ?? ''), 'UTF-8') !== 'administrador'
    ) {
        $cons->responder(403, ['error' => 'Solo administración puede modificar empleados.']);
    }
    // ---- Restricciones para pacientes ----
    if ($u['tipo'] === 'paciente') {
        if ($tipoEntidad !== 3) {
            $cons->responder(403, ['error' => 'No tienes permisos para esta acción.']);
        }

        $citaActual = (new CitaDAO($conexion))->obtenerPorId((int) $datos['id']);

        // 404 (no 403) para no revelar si la cita existe
        if (!$citaActual || (int) $citaActual->getPacienteID() !== (int) $u['paciente_id']) {
            $cons->responder(404, ['error' => 'Cita no encontrada.']);
        }

        // El cliente no decide a quién pertenece la cita
        $datos['pacienteID'] = (int) $u['paciente_id'];

        // Solo puede dejar el estado igual o cancelar
        if ($datos['estado'] !== $citaActual->getEstado() && $datos['estado'] !== 'Cancelada') {
            $cons->responder(403, ['error' => 'Estado no permitido.']);
        }

        // No se puede editar una cita ya cancelada
        if (strtolower($citaActual->getEstado()) === 'cancelada') {
            $cons->responder(409, ['error' => 'La cita ya está cancelada.']);
        }
        if ($datos['estado'] === 'Cancelada') {
            // Al cancelar no se puede alterar nada más de la cita
            $datos['fechaHora'] = $citaActual->getFechaHora();
            $datos['motivo'] = $citaActual->getMotivo();
            $datos['doctorID'] = (int) $citaActual->getDoctorID();
        } else {
            $datos['fechaHora'] = ValidadorCitas::validarFechaHora($datos['fechaHora']);
            $datos['motivo'] = ValidadorCitas::validarMotivo($datos['motivo']);
            $datos['doctorID'] = ValidadorCitas::validarEnteroPositivo($datos['doctorID'], 'doctorID');
        }
    }

if ($u['tipo'] === 'personal') {
    if ($tipoEntidad === 3) {
        $datos['fechaHora'] = ValidadorCitas::validarFechaHora($datos['fechaHora'], true, false);
        $datos['motivo']    = ValidadorCitas::validarMotivo($datos['motivo']);
        $datos['estado']    = ValidadorCitas::validarEstado($datos['estado']);
        $datos['doctorID']  = ValidadorCitas::validarEnteroPositivo($datos['doctorID'], 'doctorID');
    } else {

        try {
            switch ($tipoEntidad) {
                case 0: ValidadorPersonas::persona([
                    'nombres' => $datos['nombres'], 'primer_apellido' => $datos['primerAP'],
                    'segundo_apellido' => $datos['segundoAP'], 'fecha_nacimiento' => $datos['fechaNaci'],
                    'genero' => $datos['genero'], 'direccion' => $datos['Direccion']]); break;
                case 1: ValidadorPersonas::empleado($datos); break;
                case 2: ValidadorPersonas::paciente([
                    'num_seguro' => $datos['numSeguro'], 'grupo_sanguineo' => $datos['grupSanginio'],
                    'alergias' => $datos['alergias']]); break;
            }
        } catch (InvalidArgumentException $e) {
            $cons->responder(422, ['error' => $e->getMessage()]);
        }
    }
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
                if (strtolower($datos['estado']) !== 'cancelada'
    && (new CitaDAO($conexion))->existeChoque((int) $datos['doctorID'], $datos['fechaHora'], $idActualizado)) {
    $cons->responder(409, ['error' => 'Ese médico ya tiene una cita en ese horario.']);
}
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