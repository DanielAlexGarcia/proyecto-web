<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // solo para mensajes flash
}
require_once __DIR__ . '/../php/auth/autenticacion.php';
$u = requerirSesionPagina('personal', 'login');

require_once __DIR__ . '/../php/baseDatos/conexionBaseDatos.php';

$pdo = (new conexionBaseDatos())->getConexion();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$empleadoActual = [
    'id_persona' => (int) $u['id_persona'],
    'nombre_completo' => $u['nombre_completo'] ?? 'Personal',
    'cargo' => $u['cargo'] ?? 'Personal',
    'especializacion' => $u['especializacion'] ?? '',
];

// Mensaje "flash" (tras redirigir después de un POST exitoso)
$mensaje = $_SESSION['flash_msg'] ?? '';
$tipoMensaje = $_SESSION['flash_tipo'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

$resultadoFuncionCitas = '';
$resultadoFuncionEmpleados = '';
$gruposSangre = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

// Helpers para leer el POST sin avisos de índices inexistentes
function post(string $k): string
{
    return trim((string) ($_POST[$k] ?? ''));
}
function postNull(string $k)
{
    $x = post($k);
    return $x !== '' ? $x : null;
}
function fechaSQL(string $f): string
{
    $f = str_replace('T', ' ', trim($f));
    return strlen($f) === 16 ? $f . ':00' : $f;
}

// ============================================================================
// CONSULTAS DE LECTURA (JOINs equivalentes a las vistas; no dependen de que existan)
// ============================================================================
function leer(PDO $pdo, string $sql, array &$errores): array
{
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        error_log('[panel personal] ' . $e->getMessage());
        $errores[] = $e->getMessage();
        return [];
    }
}

$obtenerDatosPanel = function (PDO $pdo, array &$erroresLectura): array {
    return [
        'pacientes' => leer($pdo, "
            SELECT pa.id_persona AS id_paciente,
                   TRIM(CONCAT_WS(' ', per.nombres, per.primer_apellido, per.segundo_apellido)) AS nombre_completo,
                   per.nombres, per.primer_apellido, per.segundo_apellido,
                   per.fecha_nacimiento, per.genero, per.direccion,
                   pa.num_seguro, pa.grupo_sanguineo, pa.alergias
            FROM pacientes pa
            INNER JOIN personas per ON pa.id_persona = per.id
            ORDER BY pa.id_persona ASC", $erroresLectura),

        'personasSinPaciente' => leer($pdo, "
            SELECT per.id,
                   TRIM(CONCAT_WS(' ', per.nombres, per.primer_apellido, per.segundo_apellido)) AS nombre_completo,
                   per.fecha_nacimiento
            FROM personas per
            LEFT JOIN pacientes pac ON per.id = pac.id_persona
            WHERE pac.id_persona IS NULL
            ORDER BY per.id ASC", $erroresLectura),

        'citas' => leer($pdo, "
            SELECT c.id AS id_cita, c.fecha_hora, c.motivo, c.estado, c.paciente_id,
                   TRIM(CONCAT_WS(' ', pp.nombres, pp.primer_apellido, pp.segundo_apellido)) AS paciente_nombre_completo,
                   c.doctor_id,
                   TRIM(CONCAT_WS(' ', pd.nombres, pd.primer_apellido, pd.segundo_apellido)) AS doctor_nombre_completo,
                   e.cargo AS doctor_cargo,
                   IFNULL(e.especializacion, 'Sin especialidad') AS doctor_especialidad
            FROM citas c
            INNER JOIN personas pp ON pp.id = c.paciente_id
            INNER JOIN empleados e ON e.id_persona = c.doctor_id
            INNER JOIN personas pd ON pd.id = e.id_persona
            ORDER BY c.fecha_hora DESC", $erroresLectura),

        'empleados' => leer($pdo, "
            SELECT e.id_persona, e.cargo, e.especializacion,
                   TRIM(CONCAT_WS(' ', p.nombres, p.primer_apellido, p.segundo_apellido)) AS nombre_completo
            FROM empleados e
            INNER JOIN personas p ON e.id_persona = p.id
            ORDER BY p.primer_apellido ASC", $erroresLectura),

        'registrosAuditoria' => leer($pdo, "SELECT * FROM registros ORDER BY id DESC LIMIT 15", $erroresLectura),
    ];
};

// ============================================================================
// PROCESAMIENTO DE FORMULARIOS (POST)
// ============================================================================
$accion = $_POST['accion'] ?? '';
$esFetch = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Paciente desde persona existente (procedimiento almacenado)
        if ($accion === 'crear_paciente_existente') {
            $stmt = $pdo->prepare("CALL sp_crear_paciente_existente(:id_persona, :num_seguro, :grupo_sanguineo, :alergias, :user, :password)");
            $stmt->execute([
                ':id_persona' => (int) post('id_persona'),
                ':num_seguro' => post('num_seguro'),
                ':grupo_sanguineo' => postNull('grupo_sanguineo'),
                ':alergias' => postNull('alergias'),
                ':user' => post('user'),
                ':password' => post('password'),
            ]);
            $stmt->closeCursor();
            $mensaje = "Paciente registrado correctamente usando sp_crear_paciente_existente.";
        }

        // 2. Paciente completamente nuevo (persona + paciente + usuario)
        elseif ($accion === 'crear_paciente_nuevo') {
            $pdo->beginTransaction();

            $pdo->prepare("INSERT INTO personas (nombres, primer_apellido, segundo_apellido, fecha_nacimiento, genero, direccion)
                           VALUES (:nombres, :ap1, :ap2, :fnac, :genero, :direccion)")
                ->execute([
                    ':nombres' => post('nombres'),
                    ':ap1' => post('primer_apellido'),
                    ':ap2' => postNull('segundo_apellido'),
                    ':fnac' => post('fecha_nacimiento'),
                    ':genero' => postNull('genero'),
                    ':direccion' => postNull('direccion'),
                ]);
            $nuevoIdPersona = (int) $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO pacientes (id_persona, num_seguro, grupo_sanguineo, alergias)
                           VALUES (:id, :seg, :gs, :al)")
                ->execute([
                    ':id' => $nuevoIdPersona,
                    ':seg' => post('num_seguro'),
                    ':gs' => postNull('grupo_sanguineo'),
                    ':al' => postNull('alergias'),
                ]);

            // El trigger trg_usuarios_before_insert cifra user y password en SHA1
            $pdo->prepare("INSERT INTO usuarios (user, password, idDocPac) VALUES (:user, :password, :id)")
                ->execute([
                    ':user' => post('user'),
                    ':password' => post('password'),
                    ':id' => $nuevoIdPersona,
                ]);

            $pdo->commit();
            $mensaje = "Paciente nuevo creado con ID #$nuevoIdPersona y cuenta de usuario asignada.";
        }

        // 3. Modificar paciente (personas + pacientes)
        elseif ($accion === 'editar_paciente') {
            $pdo->beginTransaction();
            $idPaciente = (int) post('id_paciente');

            $pdo->prepare("UPDATE personas SET nombres = :nombres, primer_apellido = :ap1, segundo_apellido = :ap2,
                                  fecha_nacimiento = :fnac, genero = :genero, direccion = :direccion
                           WHERE id = :id")
                ->execute([
                    ':nombres' => post('nombres'),
                    ':ap1' => post('primer_apellido'),
                    ':ap2' => postNull('segundo_apellido'),
                    ':fnac' => post('fecha_nacimiento'),
                    ':genero' => postNull('genero'),
                    ':direccion' => postNull('direccion'),
                    ':id' => $idPaciente,
                ]);

            $pdo->prepare("UPDATE pacientes SET num_seguro = :seg, grupo_sanguineo = :gs, alergias = :al
                           WHERE id_persona = :id")
                ->execute([
                    ':seg' => post('num_seguro'),
                    ':gs' => postNull('grupo_sanguineo'),
                    ':al' => postNull('alergias'),
                    ':id' => $idPaciente,
                ]);

            $pdo->commit();
            $mensaje = "Datos del paciente #$idPaciente actualizados correctamente (auditado por triggers).";
        }

        // 4. Crear cita
        elseif ($accion === 'crear_cita') {
            $pdo->prepare("INSERT INTO citas (fecha_hora, motivo, estado, paciente_id, doctor_id)
                           VALUES (:fh, :motivo, :estado, :pac, :doc)")
                ->execute([
                    ':fh' => fechaSQL(post('fecha_hora')),
                    ':motivo' => post('motivo'),
                    ':estado' => post('estado') ?: 'Pendiente',
                    ':pac' => (int) post('paciente_id'),
                    ':doc' => (int) post('doctor_id'),
                ]);
            $mensaje = "Cita médica agendada correctamente.";
        }

        // 5. Modificar cita
        elseif ($accion === 'editar_cita') {
            $idCita = (int) post('id_cita');
            $pdo->prepare("UPDATE citas SET fecha_hora = :fh, motivo = :motivo, estado = :estado, doctor_id = :doc
                           WHERE id = :id")
                ->execute([
                    ':fh' => fechaSQL(post('fecha_hora')),
                    ':motivo' => post('motivo'),
                    ':estado' => post('estado'),
                    ':doc' => (int) post('doctor_id'),
                    ':id' => $idCita,
                ]);
            $mensaje = "Cita #$idCita actualizada correctamente.";
        }

        // 6. Función almacenada fn_citas_proximas
        elseif ($accion === 'consultar_fn_citas') {
            $stmt = $pdo->prepare("SELECT fn_citas_proximas(:pac, :fecha) AS resultado");
            $stmt->execute([
                ':pac' => (int) post('paciente_id'),
                ':fecha' => fechaSQL(post('fecha_consulta')),
            ]);
            $resultadoFuncionCitas = (string) $stmt->fetchColumn();
        }

        // 7. Función almacenada fn_consultar_empleados
        elseif ($accion === 'consultar_fn_empleados') {
            $stmt = $pdo->prepare("SELECT fn_consultar_empleados(:rol, :esp) AS resultado");
            $stmt->execute([':rol' => post('rol'), ':esp' => post('especialidad')]);
            $resultadoFuncionEmpleados = (string) $stmt->fetchColumn();
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[panel personal] ' . $e->getMessage());
        $tipoMensaje = 'error';
        $mensaje = "Error de Base de Datos: " . $e->getMessage();
    }

    // Respuesta parcial JSON para Alpine.js (sin recargar la página completa)
    if ($esFetch) {
        $erroresFetch = [];
        $datosActualizados = $obtenerDatosPanel($pdo, $erroresFetch);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $tipoMensaje === 'success',
            'mensaje' => $mensaje,
            'tipoMensaje' => $tipoMensaje,
            'resultadoFuncionCitas' => $resultadoFuncionCitas,
            'resultadoFuncionEmpleados' => $resultadoFuncionEmpleados,
            'datos' => $datosActualizados,
            'erroresLectura' => $erroresFetch,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Post/Redirect/Get: evita duplicar registros al recargar (F5) tras una operación exitosa
    $mutaciones = ['crear_paciente_existente', 'crear_paciente_nuevo', 'editar_paciente', 'crear_cita', 'editar_cita'];
    if ($tipoMensaje === 'success' && $mensaje !== '' && in_array($accion, $mutaciones, true)) {
        $_SESSION['flash_msg'] = $mensaje;
        $_SESSION['flash_tipo'] = 'success';
        header('Location: dashboard');
        exit;
    }
}

$erroresLectura = [];
$datosIniciales = $obtenerDatosPanel($pdo, $erroresLectura);
$pacientes = $datosIniciales['pacientes'];
$personasSinPaciente = $datosIniciales['personasSinPaciente'];
$citas = $datosIniciales['citas'];
$empleados = $datosIniciales['empleados'];
$registrosAuditoria = $datosIniciales['registrosAuditoria'];

$jsonAttr = JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Médico | Clínica Bienestar</title>
    <style>
        [x-cloak] {
            display: none !important;
        }

        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
        }

        header {
            background: #0f172a;
            color: #fff;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        header h1 {
            margin: 0;
            font-size: 1.25rem;
        }

        .user-badge {
            font-size: 0.9rem;
            color: #cbd5e1;
        }

        .btn-logout {
            background: #ef4444;
            color: #fff;
            text-decoration: none;
            padding: 0.45rem 0.9rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 1rem;
        }

        .container {
            max-width: 1280px;
            margin: 1.5rem auto;
            padding: 0 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-weight: 500;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 0.65rem 1.2rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            border-radius: 8px;
        }

        .tab-btn.active {
            background: var(--primary);
            color: #fff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(420px, 100%), 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .card {
            background: var(--card);
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border);
            margin-bottom: 1.25rem;
        }

        .card h3 {
            margin-top: 0;
            font-size: 1.05rem;
            color: #1e293b;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.6rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.25rem;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 0.55rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        button.btn {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 0.6rem 1.1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
        }

        button.btn:hover {
            background: var(--primary-dark);
        }

        button.btn-sm {
            padding: 0.35rem 0.7rem;
            font-size: 0.8rem;
            background: #475569;
        }

        button.btn-sm:hover {
            background: #334155;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }

        th,
        td {
            text-align: left;
            padding: 0.7rem;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: #f8fafc;
            font-weight: 700;
            color: #334155;
        }

        tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #e0f2fe;
            color: #0369a1;
        }

        .badge.alta {
            background: #dcfce7;
            color: #166534;
        }

        .badge.cambio {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.baja {
            background: #fee2e2;
            color: #991b1b;
        }

        pre.fn-output {
            background: #0f172a;
            color: #38bdf8;
            padding: 1rem;
            border-radius: 8px;
            white-space: pre-wrap;
            font-size: 0.85rem;
            margin-top: 0.75rem;
        }

        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            justify-content: center;
            align-items: center;
            z-index: 50;
            padding: 1rem;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal {
            background: #fff;
            border-radius: 12px;
            max-width: 550px;
            width: 100%;
            max-height: 92vh;
            overflow-y: auto;
            padding: 1.5rem;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: #64748b;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="JS/popperValidacion.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body x-data="panelPersonal()">

    <header>
        <h1>Clínica Bienestar — Panel del Personal</h1>
        <div class="user-badge">
            <strong><?= htmlspecialchars($empleadoActual['nombre_completo']) ?></strong> |
            Cargo: <?= htmlspecialchars($empleadoActual['cargo']) ?>
            (<?= htmlspecialchars($empleadoActual['especializacion'] ?: 'General') ?>)
            <a href="close.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <div class="container">
        <template x-if="mensaje">
            <div class="alert" :class="tipoMensaje" x-text="mensaje"></div>
        </template>
        <template x-for="(err, idx) in erroresLectura" :key="idx">
            <div class="alert error" x-text="`No se pudo leer parte de los datos: ${err}`"></div>
        </template>

        <div class="tabs">
            <button type="button" class="tab-btn" :class="{ active: activeTab === 'tab-pacientes' }"
                @click="activeTab = 'tab-pacientes'">1. Pacientes (Alta y Edición)</button>
            <button type="button" class="tab-btn" :class="{ active: activeTab === 'tab-citas' }"
                @click="activeTab = 'tab-citas'">2. Citas Médicas</button>
            <button type="button" class="tab-btn" :class="{ active: activeTab === 'tab-funciones' }"
                @click="activeTab = 'tab-funciones'">3. Funciones SQL y Auditoría</button>
        </div>

        <!-- ===================== PESTAÑA 1: PACIENTES ===================== -->
        <div id="tab-pacientes" class="tab-content" :class="{ active: activeTab === 'tab-pacientes' }">
            <div class="grid-2">
                <div class="card">
                    <h3>Registrar paciente con datos en el sistema</h3>
                    <form method="POST" @submit.prevent="enviarFormulario($event, true)">
                        <input type="hidden" name="accion" value="crear_paciente_existente">
                        <div class="form-row">
                            <div>
                                <label>Selecciona persona registrada sin expediente de paciente</label>
                                <select name="id_persona" required>
                                    <option value="">-- Selecciona persona --</option>
                                    <template x-for="per in personasSinPaciente" :key="per.id">
                                        <option :value="per.id"
                                            x-text="`ID #${per.id} - ${per.nombre_completo} (${per.fecha_nacimiento})`">
                                        </option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Número de Seguro (Único)</label>
                                <input type="text" name="num_seguro" placeholder="Ej. 554433221" required>
                            </div>
                            <div>
                                <label>Grupo Sanguíneo</label>
                                <select name="grupo_sanguineo">
                                    <option value="">No especificado</option>
                                    <?php foreach ($gruposSangre as $g): ?>
                                        <option value="<?= $g ?>"><?= $g ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Alergias</label>
                                <input type="text" name="alergias" placeholder="Ej. Penicilina, Ninguna">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Usuario de acceso</label>
                                <input type="text" name="user" placeholder="Ej. daniel@mail.com" required>
                            </div>
                            <div>
                                <label>Contraseña</label>
                                <input type="password" name="password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn">Guardar nuevo paciente</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Registrar Paciente sin registros en el sistema</h3>
                    <form method="POST" @submit.prevent="enviarFormulario($event, true)">
                        <input type="hidden" name="accion" value="crear_paciente_nuevo">
                        <div class="form-row">
                            <div><label>Nombres</label><input type="text" name="nombres" required></div>
                            <div><label>Primer Apellido</label><input type="text" name="primer_apellido" required></div>
                            <div><label>Segundo Apellido</label><input type="text" name="segundo_apellido"></div>
                        </div>
                        <div class="form-row">
                            <div><label>Fecha de Nacimiento</label><input type="date" name="fecha_nacimiento" required>
                            </div>
                            <div>
                                <label>Género</label>
                                <select name="genero">
                                    <option value="M">M</option>
                                    <option value="F">F</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div><label>Dirección</label><input type="text" name="direccion"></div>
                        </div>
                        <div class="form-row">
                            <div><label>Núm. Seguro (Único)</label><input type="text" name="num_seguro" required></div>
                            <div>
                                <label>Grupo Sanguíneo</label>
                                <select name="grupo_sanguineo">
                                    <?php foreach ($gruposSangre as $g): ?>
                                        <option value="<?= $g ?>"><?= $g ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div><label>Alergias</label><input type="text" name="alergias"></div>
                        </div>
                        <div class="form-row">
                            <div><label>Usuario de acceso</label><input type="text" name="user" required></div>
                            <div><label>Contraseña</label><input type="password" name="password" required></div>
                        </div>
                        <button type="submit" class="btn">Registrar Paciente y datos</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h3>Listado de Pacientes</h3>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre Completo</th>
                                <th>F. Nacimiento</th>
                                <th>Género</th>
                                <th>Núm. Seguro</th>
                                <th>Tipo Sangre</th>
                                <th>Alergias</th>
                                <th>Dirección</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="pac in pacientes" :key="pac.id_paciente">
                                <tr>
                                    <td x-text="`#${pac.id_paciente}`"></td>
                                    <td><strong x-text="pac.nombre_completo"></strong></td>
                                    <td x-text="pac.fecha_nacimiento"></td>
                                    <td x-text="pac.genero || '-'"></td>
                                    <td x-text="pac.num_seguro"></td>
                                    <td><span class="badge" x-text="pac.grupo_sanguineo || 'N/A'"></span></td>
                                    <td x-text="pac.alergias || 'Ninguna'"></td>
                                    <td x-text="pac.direccion || '-'"></td>
                                    <td>
                                        <button type="button" class="btn btn-sm"
                                            @click="abrirModalPaciente(pac)">Modificar</button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="pacientes.length === 0">
                                <tr>
                                    <td colspan="9">No hay pacientes registrados.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===================== PESTAÑA 2: CITAS ===================== -->
        <div id="tab-citas" class="tab-content" :class="{ active: activeTab === 'tab-citas' }">
            <div class="card">
                <h3>Agendar Nueva Cita Médica</h3>
                <form method="POST" @submit.prevent="enviarFormulario($event, true)">
                    <input type="hidden" name="accion" value="crear_cita">
                    <div class="form-row">
                        <div>
                            <label>Paciente</label>
                            <select name="paciente_id" required>
                                <option value="">-- Selecciona paciente --</option>
                                <template x-for="pac in pacientes" :key="pac.id_paciente">
                                    <option :value="pac.id_paciente"
                                        x-text="`#${pac.id_paciente} - ${pac.nombre_completo} (Seguro: ${pac.num_seguro})`">
                                    </option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label>Doctor / Especialista Asignado</label>
                            <select name="doctor_id" required>
                                <template x-for="emp in empleados" :key="emp.id_persona">
                                    <option :value="emp.id_persona"
                                        :selected="Number(emp.id_persona) === empleadoActualId"
                                        x-text="`${emp.nombre_completo} — ${emp.cargo} (${emp.especializacion || 'Sin especialidad'})`">
                                    </option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label>Fecha y Hora</label>
                            <input type="datetime-local" name="fecha_hora" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Motivo de la consulta</label>
                            <input type="text" name="motivo" placeholder="Ej. Revisión cardiológica general" required>
                        </div>
                        <div>
                            <label>Estado inicial</label>
                            <select name="estado">
                                <option value="Pendiente">Pendiente</option>
                                <option value="registrada">registrada</option>
                                <option value="Confirmada">Confirmada</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn">Agendar Cita</button>
                </form>
            </div>

            <div class="card">
                <h3>Agenda de Citas</h3>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Cita</th>
                                <th>Fecha y Hora</th>
                                <th>Paciente</th>
                                <th>Doctor / Especialista</th>
                                <th>Especialidad</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="cita in citas" :key="cita.id_cita">
                                <tr>
                                    <td x-text="`#${cita.id_cita}`"></td>
                                    <td x-text="cita.fecha_hora"></td>
                                    <td><strong x-text="cita.paciente_nombre_completo"></strong></td>
                                    <td x-text="`${cita.doctor_nombre_completo} (${cita.doctor_cargo})`"></td>
                                    <td x-text="cita.doctor_especialidad"></td>
                                    <td x-text="cita.motivo"></td>
                                    <td><span class="badge" x-text="cita.estado"></span></td>
                                    <td>
                                        <button type="button" class="btn btn-sm"
                                            @click="abrirModalCita(cita)">Modificar</button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="citas.length === 0">
                                <tr>
                                    <td colspan="8">No hay citas registradas.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===================== PESTAÑA 3: FUNCIONES Y AUDITORÍA ===================== -->
        <div id="tab-funciones" class="tab-content" :class="{ active: activeTab === 'tab-funciones' }">
            <div class="grid-2">
                <div class="card">
                    <h3>Consultar Próximas Citas</h3>
                    <form method="POST" @submit.prevent="enviarFormulario($event, false)">
                        <input type="hidden" name="accion" value="consultar_fn_citas">
                        <div class="form-row">
                            <div>
                                <label>Paciente</label>
                                <select name="paciente_id" required>
                                    <template x-for="pac in pacientes" :key="pac.id_paciente">
                                        <option :value="pac.id_paciente"
                                            x-text="`#${pac.id_paciente} - ${pac.nombre_completo}`"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label>A partir de fecha y hora</label>
                                <input type="datetime-local" name="fecha_consulta" value="<?= date('Y-m-d\TH:i') ?>"
                                    required>
                            </div>
                        </div>
                        <button type="submit" class="btn">Buscar</button>
                    </form>
                    <template x-if="resultadoFuncionCitas !== ''">
                        <pre class="fn-output" x-text="resultadoFuncionCitas"></pre>
                    </template>
                </div>

                <div class="card">
                    <h3>Buscar Médico</h3>
                    <form method="POST" @submit.prevent="enviarFormulario($event, false)">
                        <input type="hidden" name="accion" value="consultar_fn_empleados">
                        <div class="form-row">
                            <div><label>Filtrar por Cargo / Rol (opcional)</label><input type="text" name="rol"
                                    placeholder="Ej. doctor, enfermero"></div>
                            <div><label>Filtrar por Especialidad (opcional)</label><input type="text"
                                    name="especialidad" placeholder="Ej. cardiologia"></div>
                        </div>
                        <button type="submit" class="btn">Buscar</button>
                    </form>
                    <template x-if="resultadoFuncionEmpleados !== ''">
                        <pre class="fn-output" x-text="resultadoFuncionEmpleados"></pre>
                    </template>
                </div>
            </div>

            <div class="card">
                <h3>Bitácora Automática de Triggers (<code>registros</code>)</h3>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tabla Origen</th>
                                <th>Acción</th>
                                <th>Fecha / Hora</th>
                                <th>Valores Actuales (JSON)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="reg in registrosAuditoria" :key="reg.id">
                                <tr>
                                    <td x-text="`#${reg.id}`"></td>
                                    <td><code x-text="reg.tabla_origen"></code></td>
                                    <td><span class="badge" :class="reg.accion" x-text="reg.accion"></span></td>
                                    <td x-text="reg.fecha_hora"></td>
                                    <td><small><code x-text="reg.campos_actuales || 'N/A (Baja)'"></code></small></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== MODAL: EDITAR PACIENTE ===================== -->
    <div id="modal-paciente" class="modal-backdrop" :class="{ open: modalPacienteOpen }">
        <div class="modal">
            <div class="modal-header">
                <h3 style="margin:0;">Modificar Datos del Paciente</h3>
                <button type="button" class="close-btn" @click="modalPacienteOpen = false">&times;</button>
            </div>
            <form method="POST" @submit.prevent="enviarFormulario($event, false, 'paciente')">
                <input type="hidden" name="accion" value="editar_paciente">
                <input type="hidden" name="id_paciente" id="edit_id_paciente" x-model="editPac.id_paciente">
                <div class="form-row">
                    <div><label>Nombres</label><input type="text" name="nombres" id="edit_nombres"
                            x-model="editPac.nombres" required></div>
                    <div><label>Primer Apellido</label><input type="text" name="primer_apellido"
                            id="edit_primer_apellido" x-model="editPac.primer_apellido" required></div>
                </div>
                <div class="form-row">
                    <div><label>Segundo Apellido</label><input type="text" name="segundo_apellido"
                            id="edit_segundo_apellido" x-model="editPac.segundo_apellido"></div>
                    <div><label>Fecha Nacimiento</label><input type="date" name="fecha_nacimiento"
                            id="edit_fecha_nacimiento" x-model="editPac.fecha_nacimiento" required></div>
                </div>
                <div class="form-row">
                    <div>
                        <label>Género</label>
                        <select name="genero" id="edit_genero" x-model="editPac.genero">
                            <option value="M">M</option>
                            <option value="F">F</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div><label>Dirección</label><input type="text" name="direccion" id="edit_direccion"
                            x-model="editPac.direccion"></div>
                </div>
                <div class="form-row">
                    <div><label>Núm. Seguro</label><input type="text" name="num_seguro" id="edit_num_seguro"
                            x-model="editPac.num_seguro" required>
                    </div>
                    <div>
                        <label>Grupo Sanguíneo</label>
                        <select name="grupo_sanguineo" id="edit_grupo_sanguineo" x-model="editPac.grupo_sanguineo">
                            <option value="">No especificado</option>
                            <?php foreach ($gruposSangre as $g): ?>
                                <option value="<?= $g ?>"><?= $g ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div><label>Alergias</label><input type="text" name="alergias" id="edit_alergias"
                            x-model="editPac.alergias"></div>
                </div>
                <button type="submit" class="btn">Guardar Cambios</button>
            </form>
        </div>
    </div>

    <!-- ===================== MODAL: EDITAR CITA ===================== -->
    <div id="modal-cita" class="modal-backdrop" :class="{ open: modalCitaOpen }">
        <div class="modal">
            <div class="modal-header">
                <h3 style="margin:0;">Modificar Cita Médica</h3>
                <button type="button" class="close-btn" @click="modalCitaOpen = false">&times;</button>
            </div>
            <form method="POST" @submit.prevent="enviarFormulario($event, false, 'cita')">
                <input type="hidden" name="accion" value="editar_cita">
                <input type="hidden" name="id_cita" id="edit_id_cita" x-model="editCita.id_cita">
                <div class="form-row">
                    <div><label>Fecha y Hora</label><input type="datetime-local" name="fecha_hora" id="edit_cita_fecha"
                            x-model="editCita.fecha_hora" required></div>
                    <div>
                        <label>Estado</label>
                        <select name="estado" id="edit_cita_estado" x-model="editCita.estado">
                            <option value="Pendiente">Pendiente</option>
                            <option value="registrada">registrada</option>
                            <option value="Confirmada">Confirmada</option>
                            <option value="Completada">Completada</option>
                            <option value="Cancelada">Cancelada</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label>Doctor / Especialista</label>
                        <select name="doctor_id" id="edit_cita_doctor" x-model="editCita.doctor_id" required>
                            <template x-for="emp in empleados" :key="emp.id_persona">
                                <option :value="emp.id_persona"
                                    x-text="`${emp.nombre_completo} (${emp.especializacion || emp.cargo})`"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div><label>Motivo / Diagnóstico</label><input type="text" name="motivo" id="edit_cita_motivo"
                            x-model="editCita.motivo" required></div>
                </div>
                <button type="submit" class="btn">Actualizar Cita</button>
            </form>
        </div>
    </div>

    <script>
        function panelPersonal() {
            init() {
                this.$nextTick(() => document.querySelectorAll('form').forEach(f => Validacion.activar(f)));
            },
            return {
                activeTab: 'tab-pacientes',
                empleadoActualId: <?= (int) $empleadoActual['id_persona'] ?>,

                mensaje: <?= json_encode($mensaje, $jsonAttr) ?>,
                tipoMensaje: <?= json_encode($tipoMensaje, $jsonAttr) ?>,
                erroresLectura: <?= json_encode($erroresLectura, $jsonAttr) ?>,

                pacientes: <?= json_encode($pacientes, $jsonAttr) ?>,
                personasSinPaciente: <?= json_encode($personasSinPaciente, $jsonAttr) ?>,
                citas: <?= json_encode($citas, $jsonAttr) ?>,
                empleados: <?= json_encode($empleados, $jsonAttr) ?>,
                registrosAuditoria: <?= json_encode($registrosAuditoria, $jsonAttr) ?>,

                resultadoFuncionCitas: <?= json_encode($resultadoFuncionCitas, $jsonAttr) ?>,
                resultadoFuncionEmpleados: <?= json_encode($resultadoFuncionEmpleados, $jsonAttr) ?>,

                modalPacienteOpen: false,
                modalCitaOpen: false,
                editPac: {
                    id_paciente: '', nombres: '', primer_apellido: '', segundo_apellido: '',
                    fecha_nacimiento: '', genero: 'M', direccion: '', num_seguro: '',
                    grupo_sanguineo: '', alergias: ''
                },
                editCita: {
                    id_cita: '', fecha_hora: '', estado: 'Pendiente', doctor_id: '', motivo: ''
                },

                abrirModalPaciente(pac) {
                    this.editPac = {
                        id_paciente: pac.id_paciente,
                        nombres: pac.nombres || '',
                        primer_apellido: pac.primer_apellido || '',
                        segundo_apellido: pac.segundo_apellido || '',
                        fecha_nacimiento: pac.fecha_nacimiento || '',
                        genero: pac.genero || 'M',
                        direccion: pac.direccion || '',
                        num_seguro: pac.num_seguro || '',
                        grupo_sanguineo: pac.grupo_sanguineo || '',
                        alergias: pac.alergias || ''
                    };
                    this.modalPacienteOpen = true;
                },

                abrirModalCita(cita) {
                    this.editCita = {
                        id_cita: cita.id_cita,
                        fecha_hora: String(cita.fecha_hora || '').replace(' ', 'T').slice(0, 16),
                        estado: cita.estado,
                        doctor_id: String(cita.doctor_id),
                        motivo: cita.motivo
                    };
                    this.modalCitaOpen = true;
                },

                async enviarFormulario(evt, resetForm = false, cerrarModal = null) {
                    const form = evt.target;
                    const formData = new FormData(form);

                    try {
                        const res = await fetch(window.location.href, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'X-Requested-With': 'fetch' },
                            body: formData
                        });

                        const data = await res.json();
                        this.mensaje = data.mensaje || '';
                        this.tipoMensaje = data.tipoMensaje || 'success';
                        this.erroresLectura = data.erroresLectura || [];

                        if (data.datos) {
                            this.pacientes = data.datos.pacientes;
                            this.personasSinPaciente = data.datos.personasSinPaciente;
                            this.citas = data.datos.citas;
                            this.empleados = data.datos.empleados;
                            this.registrosAuditoria = data.datos.registrosAuditoria;
                        }

                        if (formData.get('accion') === 'consultar_fn_citas') {
                            this.resultadoFuncionCitas = data.resultadoFuncionCitas || '';
                        }
                        if (formData.get('accion') === 'consultar_fn_empleados') {
                            this.resultadoFuncionEmpleados = data.resultadoFuncionEmpleados || '';
                        }

                        if (data.ok) {
                            if (resetForm) form.reset();
                            if (cerrarModal === 'paciente') this.modalPacienteOpen = false;
                            if (cerrarModal === 'cita') this.modalCitaOpen = false;
                        }
                    } catch (err) {
                        this.tipoMensaje = 'error';
                        this.mensaje = 'Error de conexión al procesar la solicitud.';
                    }
                }
            };
        }
    </script>
</body>

</html>