<?php
require_once __DIR__ . '/../auth/autenticacion.php';
require_once __DIR__ . '/../baseDatos/BaseDatosFactory.php';
require_once __DIR__ . '/../validacionesCita.php';
require_once __DIR__ . '/../validaciones/validacionesPersona.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$u = requerirSesionApi(['paciente', 'personal']);
$cons = BaseDatosFactory::conexion();
$conexion = $cons->getConexion();

$metodo = $_SERVER['REQUEST_METHOD'];
$esAdmin = mb_strtolower(trim($u['cargo'] ?? ''), 'UTF-8') === 'administrador';

function sinSalario(array $datos): array
{
    if (array_key_exists('salario', $datos)) {
        unset($datos['salario']);
        return $datos;
    }
    foreach ($datos as $k => $v) {
        if (is_array($v)) {
            $datos[$k] = sinSalario($v);
        }
    }
    return $datos;
}

// Estructuras JSON válidas para consultas:
// 1) Todos:     {"entidad": "..."}
// 2) Uno:       {"entidad": "...", "id": 1}  (o "personaID" / "id_paciente" / "id_cita")
// 3) Filtro:    {"entidad": "...", "filtro": "...", "valor": "..."}
// 4) Funciones: {"entidad": "citas_proximas", "paciente_id": 2, "fecha_hora": "2026-01-01 00:00:00"}
//               {"entidad": "consultar_empleados", "rol": "doctor", "especialidad": "cardiologia"}
$estructuraDatos = [
    0 => [
        'nombres_entidad' => ['persona', 'personas'],
        'claves_id' => ['id'],
        'filtros' => [
            'id' => 'p.id',
            'nombres' => 'p.nombres',
            'primerAP' => 'p.primer_apellido',
            'primer_apellido' => 'p.primer_apellido',
            'segundoAP' => 'p.segundo_apellido',
            'segundo_apellido' => 'p.segundo_apellido',
            'fechaNaci' => 'p.fecha_nacimiento',
            'genero' => 'p.genero',
            'Direccion' => 'p.direccion',
            'direccion' => 'p.direccion'
        ]
    ],
    1 => [
        'nombres_entidad' => ['empleado', 'empleados'],
        'claves_id' => ['id', 'personaID', 'id_persona'],
        'filtros' => [
            'personaID' => 'e.id_persona',
            'nombres' => 'p.nombres',
            'primerAP' => 'p.primer_apellido',
            'cargo' => 'e.cargo',
            'especializacion' => 'e.especializacion',
            'salario' => 'e.salario'
        ]
    ],
    2 => [
        'nombres_entidad' => ['paciente', 'pacientes'],
        'claves_id' => ['id', 'personaID', 'id_persona'],
        'filtros' => [
            'personaID' => 'pa.id_persona',
            'nombres' => 'p.nombres',
            'primerAP' => 'p.primer_apellido',
            'numSeguro' => 'pa.num_seguro',
            'grupSanginio' => 'pa.grupo_sanguineo',
            'grupSangineo' => 'pa.grupo_sanguineo',
            'alergias' => 'pa.alergias'
        ]
    ],
    3 => [
        'nombres_entidad' => ['cita', 'citas'],
        'claves_id' => ['id'],
        'filtros' => [
            'id' => 'c.id',
            'fechaHora' => 'c.fecha_hora',
            'motivo' => 'c.motivo',
            'estado' => 'c.estado',
            'pacienteID' => 'c.paciente_id',
            'doctorID' => 'c.doctor_id'
        ]
    ],
    4 => [
        'nombres_entidad' => ['vista_pacientes', 'vista_paciente', 'vistapacientes', 'vistapaciente'],
        'claves_id' => ['id', 'id_paciente', 'personaID', 'id_persona'],
        'filtros' => [
            'id' => 'id_paciente',
            'id_paciente' => 'id_paciente',
            'personaID' => 'id_paciente',
            'nombre_completo' => 'nombre_completo',
            'nombreCompleto' => 'nombre_completo',
            'nombre' => 'nombre_completo',
            'fecha_nacimiento' => 'fecha_nacimiento',
            'fechaNaci' => 'fecha_nacimiento',
            'genero' => 'genero',
            'direccion' => 'direccion',
            'Direccion' => 'direccion',
            'num_seguro' => 'num_seguro',
            'numSeguro' => 'num_seguro',
            'grupo_sanguineo' => 'grupo_sanguineo',
            'grupSangineo' => 'grupo_sanguineo',
            'grupSanginio' => 'grupo_sanguineo',
            'alergias' => 'alergias'
        ]
    ],
    5 => [
        'nombres_entidad' => ['vista_citas', 'vista_cita', 'vistacitas', 'vistacita'],
        'claves_id' => ['id', 'id_cita'],
        'filtros' => [
            'id' => 'id_cita',
            'id_cita' => 'id_cita',
            'fecha_hora' => 'fecha_hora',
            'fechaHora' => 'fecha_hora',
            'motivo' => 'motivo',
            'estado' => 'estado',
            'paciente_id' => 'paciente_id',
            'pacienteID' => 'paciente_id',
            'paciente_nombre_completo' => 'paciente_nombre_completo',
            'pacienteNombre' => 'paciente_nombre_completo',
            'doctor_id' => 'doctor_id',
            'doctorID' => 'doctor_id',
            'doctor_nombre_completo' => 'doctor_nombre_completo',
            'doctorNombre' => 'doctor_nombre_completo',
            'doctor_cargo' => 'doctor_cargo',
            'doctorCargo' => 'doctor_cargo',
            'cargo' => 'doctor_cargo',
            'doctor_especialidad' => 'doctor_especialidad',
            'doctorEspecialidad' => 'doctor_especialidad',
            'especialidad' => 'doctor_especialidad'
        ]
    ],
    6 => [
        'nombres_entidad' => ['citas_proximas', 'cita_proxima', 'citasproximas', 'fn_citas_proximas'],
        'claves_id' => ['id', 'paciente_id', 'pacienteID', 'id_paciente'],
        'claves_funcion' => ['fecha_hora', 'fechaHora', 'fecha_hora_consulta'],
        'filtros' => []
    ],
    7 => [
        'nombres_entidad' => ['consultar_empleados', 'consulta_empleados', 'fn_consultar_empleados'],
        'claves_id' => [],
        'claves_funcion' => ['rol', 'cargo', 'p_rol', 'especialidad', 'especializacion', 'p_especialidad'],
        'filtros' => []
    ]
];

if ($metodo === 'GET') {

    $datos = $cons->leerJSON();

    if (!$datos) {
        $cons->responder(400, ['error' => 'JSON inválido o vacío']);
    }

    if (empty($datos['entidad'])) {
        $cons->responder(400, [
            'error' => 'Debes especificar la clave "entidad" en el JSON (persona, empleado, paciente, cita, vista_pacientes, vista_citas, citas_proximas o consultar_empleados)'
        ]);
    }

    // Identificar la entidad solicitada
    $entidadSolicitada = strtolower(trim($datos['entidad']));
    $tipoEntidad = null;

    foreach ($estructuraDatos as $indice => $config) {
        if (in_array($entidadSolicitada, $config['nombres_entidad'], true)) {
            $tipoEntidad = $indice;
            break;
        }
    }

    if ($tipoEntidad === null) {
        $cons->responder(400, [
            'error' => "Entidad '$entidadSolicitada' no válida. Opciones: persona, empleado, paciente, cita, vista_pacientes, vista_citas, citas_proximas, consultar_empleados."
        ]);
    }

    $configEntidad = $estructuraDatos[$tipoEntidad];
    $esFuncion = in_array($tipoEntidad, [6, 7], true);
    if (!$esAdmin) {
        unset($configEntidad['filtros']['salario']);
    }

    // Validar que no se envíen claves desconocidas en el JSON
    $clavesBase = $esFuncion ? ['entidad'] : ['entidad', 'filtro', 'valor'];
    $clavesFuncion = $configEntidad['claves_funcion'] ?? [];
    $clavesPermitidas = array_merge($clavesBase, $configEntidad['claves_id'], $clavesFuncion);
    $clavesExtra = array_diff(array_keys($datos), $clavesPermitidas);

    if (!empty($clavesExtra)) {
        $cons->responder(400, [
            'error' => 'El JSON contiene claves no permitidas para esta consulta',
            'claves_no_validas' => array_values($clavesExtra)
        ]);
    }

    // Detectar si viene identificador individual
    $idBuscado = null;
    foreach ($configEntidad['claves_id'] as $claveId) {
        if (isset($datos[$claveId]) && $datos[$claveId] !== '') {
            $idBuscado = $datos[$claveId];
            break;
        }
    }

    // Detectar si viene búsqueda con filtro
    $tieneFiltro = isset($datos['filtro']) || isset($datos['valor']);

    if ($tieneFiltro && (empty($datos['filtro']) || !isset($datos['valor']) || $datos['valor'] === '')) {
        $cons->responder(400, [
            'error' => 'Para consultar con filtro debes enviar ambas claves en el JSON: "filtro" y "valor".'
        ]);
    }

    try {
        // MODO FUNCIONES SQL (fn_citas_proximas y fn_consultar_empleados)
        if ($tipoEntidad === 6) {
            if ($idBuscado === null) {
                $cons->responder(400, [
                    'error' => 'Debes indicar el ID del paciente ("paciente_id", "pacienteID" o "id") para consultar sus citas próximas.'
                ]);
            }

            if (!is_numeric($idBuscado) || (int) $idBuscado <= 0) {
                $cons->responder(400, ['error' => 'El ID del paciente debe ser un número entero positivo']);
            }

            $pacienteId = (int) $idBuscado;
            $fechaHora = $datos['fecha_hora']
                ?? $datos['fechaHora']
                ?? $datos['fecha_hora_consulta']
                ?? date('Y-m-d H:i:s');

            $stmt = $conexion->prepare(
                "SELECT fn_citas_proximas(" . BaseDatosFactory::tipado(':paciente_id', 'int') . ", "
                . BaseDatosFactory::tipado(':fecha_hora', 'fecha') . ") AS resultado"
            );
            $stmt->execute([
                ':paciente_id' => $pacienteId,
                ':fecha_hora' => trim((string) $fechaHora)
            ]);

            $texto = (string) $stmt->fetchColumn();
            $sinResultados = ($texto === 'No se encontraron citas próximas para el paciente indicado.');

            $cons->responder(200, [
                'paciente_id' => $pacienteId,
                'fecha_hora_consulta' => trim((string) $fechaHora),
                'resultado' => $texto,
                'citas' => $sinResultados ? [] : explode("\n", $texto)
            ]);
        }

        if ($tipoEntidad === 7) {
            $rol = $datos['rol'] ?? $datos['cargo'] ?? $datos['p_rol'] ?? null;
            $especialidad = $datos['especialidad'] ?? $datos['especializacion'] ?? $datos['p_especialidad'] ?? null;

            $rol = ($rol !== null && trim((string) $rol) !== '') ? trim((string) $rol) : null;
            $especialidad = ($especialidad !== null && trim((string) $especialidad) !== '') ? trim((string) $especialidad) : null;

            $stmt = $conexion->prepare(
                "SELECT fn_consultar_empleados(" . BaseDatosFactory::tipado(':rol', 'texto') . ", "
                . BaseDatosFactory::tipado(':especialidad', 'texto') . ") AS resultado"
            );
            $stmt->execute([
                ':rol' => $rol,
                ':especialidad' => $especialidad
            ]);

            $texto = (string) $stmt->fetchColumn();
            $sinResultados = ($texto === 'No se encontraron empleados con los filtros proporcionados.');

            $cons->responder(200, [
                'rol' => $rol,
                'especialidad' => $especialidad,
                'resultado' => $texto,
                'empleados' => $sinResultados ? [] : explode("\n", $texto)
            ]);
        }

        // MODO 1: CONSULTA INDIVIDUAL POR IDENTIFICADOR EN JSON
        if ($idBuscado !== null) {
            if (!is_numeric($idBuscado) || (int) $idBuscado <= 0) {
                $cons->responder(400, ['error' => 'El identificador debe ser un número entero positivo']);
            }

            $id = (int) $idBuscado;
            $resultado = null;

            switch ($tipoEntidad) {
                case 0: // Persona
                    $dao = BaseDatosFactory::persona($conexion);
                    $obj = $dao->obtenerPorId($id);
                    if ($obj) {
                        $resultado = [
                            'id' => $id,
                            'nombres' => $obj->getNombres(),
                            'primerAP' => $obj->getPrimerApellido(),
                            'segundoAP' => $obj->getSegundoApellido(),
                            'fechaNaci' => $obj->getFechaNacimiento(),
                            'genero' => $obj->getGenero(),
                            'Direccion' => $obj->getCURP()
                        ];
                    }
                    break;

                case 1: // Empleado
                    $dao =BaseDatosFactory::empleado($conexion);
                    $obj = $dao->obtenerPorId($id);
                    if ($obj) {
                        $resultado = [
                            'personaID' => $obj->getIDPersona(),
                            'cargo' => $obj->getCargo(),
                            'especializacion' => $obj->getEspecializacion(),
                            'salario' => $obj->getSalario()
                        ];
                    }
                    break;

                case 2: // Paciente
                    $dao = BaseDatosFactory::paciente($conexion);
                    $obj = $dao->obtenerPorId($id);
                    if ($obj) {
                        $resultado = [
                            'personaID' => $obj->getIDPersona(),
                            'numSeguro' => $obj->getNumSeg(),
                            'grupSangineo' => $obj->getGrupSangineo(),
                            'alergias' => $obj->getAlergias()
                        ];
                    }
                    break;

                case 3: // Cita
                    $dao =BaseDatosFactory::cita($conexion);
                    $obj = $dao->obtenerPorId($id);
                    if ($obj) {
                        $resultado = [
                            'id' => $id,
                            'fechaHora' => $obj->getFechaHora(),
                            'motivo' => $obj->getMotivo(),
                            'estado' => $obj->getEstado(),
                            'pacienteID' => $obj->getPacienteID(),
                            'doctorID' => $obj->getDoctorID()
                        ];
                    }
                    break;

                case 4: // Vista Pacientes
                    $stmt = $conexion->prepare("SELECT * FROM vista_pacientes WHERE id_paciente = :id");
                    $stmt->execute([':id' => $id]);
                    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($fila) {
                        $resultado = $fila;
                    }
                    break;

                case 5: // Vista Citas
                    $stmt = $conexion->prepare("SELECT * FROM vista_citas WHERE paciente_id = :id");
                    $stmt->execute([':id' => $id]);
                    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($fila) {
                        $resultado = $fila;
                    }
                    break;
            }

            if (!$resultado) {
                $cons->responder(404, ['error' => "No se encontró registro en '$entidadSolicitada' con ID $id"]);
            }

            $cons->responder(200, $esAdmin ? $resultado : sinSalario($resultado));
        }

        // MODO 2: CONSULTA CON FILTRO EN JSON
        if ($tieneFiltro) {
            $filtro = trim($datos['filtro']);
            $valor = trim((string) $datos['valor']);

            if (!array_key_exists($filtro, $configEntidad['filtros'])) {
                $cons->responder(400, [
                    'error' => "El filtro '$filtro' no es válido para '$entidadSolicitada'.",
                    'filtros_permitidos' => array_keys($configEntidad['filtros'])
                ]);
            }

            $columnaSQL = $configEntidad['filtros'][$filtro];

            $consultasBase = [
                0 => "SELECT p.* FROM personas p",
                1 => "SELECT p.id, p.nombres, p.primer_apellido, p.segundo_apellido, 
                             p.fecha_nacimiento, p.genero, p.direccion,
                             e.cargo, e.especializacion, e.salario
                      FROM empleados e
                      INNER JOIN personas p ON e.id_persona = p.id",
                2 => "SELECT p.id, p.nombres, p.primer_apellido, p.segundo_apellido, 
                             p.fecha_nacimiento, p.genero, p.direccion,
                             pa.num_seguro, pa.grupo_sanguineo, pa.alergias
                      FROM pacientes pa
                      INNER JOIN personas p ON pa.id_persona = p.id",
                3 => "SELECT c.id, c.fecha_hora, c.motivo, c.estado,
                             c.paciente_id,
                             CONCAT(pp.nombres, ' ', pp.primer_apellido, ' ', COALESCE(pp.segundo_apellido, '')) AS nombre_paciente,
                             c.doctor_id,
                             CONCAT(pd.nombres, ' ', pd.primer_apellido, ' ', COALESCE(pd.segundo_apellido, '')) AS nombre_doctor
                      FROM citas c
                      INNER JOIN personas pp ON c.paciente_id = pp.id
                      INNER JOIN personas pd ON c.doctor_id = pd.id",
                4 => "SELECT * FROM vista_pacientes",
                5 => "SELECT * FROM vista_citas"
            ];

            $camposNumericos = [
                'id',
                'personaID',
                'id_paciente',
                'id_cita',
                'pacienteID',
                'paciente_id',
                'doctorID',
                'doctor_id',
                'salario'
            ];

            $esNumerico = is_numeric($valor) && in_array($filtro, $camposNumericos, true);
            $operador = $esNumerico ? '=' : BaseDatosFactory::like();
            $parametroValor = $esNumerico ? $valor : "%$valor%";
            $columnaWhere = $esNumerico ? $columnaSQL : BaseDatosFactory::texto($columnaSQL);

            $sql = $consultasBase[$tipoEntidad] . " WHERE $columnaWhere $operador :valor";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':valor' => $parametroValor]);

            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $cons->responder(200, $esAdmin ? $filas : sinSalario($filas));
        }

        // MODO 3: CONSULTA GENERAL (Todos los registros)
        $lista = [];
        switch ($tipoEntidad) {
            case 0:
                $dao = BaseDatosFactory::persona($conexion);
                $lista = $dao->obtenerTodos();
                break;
            case 1:
                $dao = BaseDatosFactory::empleado($conexion);
                $lista = $dao->obtenerTodos();
                break;
            case 2:
                $dao = BaseDatosFactory::paciente($conexion);
                $lista = $dao->obtenerTodos();
                break;
            case 3:
                $dao = BaseDatosFactory::cita($conexion);
                $lista = $dao->obtenerTodos();
                break;
            case 4:
                $stmt = $conexion->query("SELECT * FROM vista_pacientes");
                $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
            case 5:
                $stmt = $conexion->query("SELECT * FROM vista_citas");
                $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }

        $cons->responder(200, $esAdmin ? $lista : sinSalario($lista));

    } catch (PDOException $e) {
        $cons->responder(500, [
            'error' => 'Error en la base de datos al realizar la consulta',
            'detalle' => $e->getMessage()
        ]);
    }

} else {
    $cons->responder(405, ['error' => 'Método no permitido. Usa GET.']);
}
?>