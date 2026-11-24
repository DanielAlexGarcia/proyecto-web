<?php
session_start(); // solo para mensajes flash
require_once __DIR__ . '/../php/auth/autenticacion.php';
$u = requerirSesionPagina('personal');

require_once __DIR__ . '/../php/baseDatos/conexionBaseDatos.php';

$pdo = (new conexionBaseDatos())->getConexion();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$empleadoActual = [
    'id_persona'      => (int)$u['id_persona'],
    'nombre_completo' => $u['nombre_completo'] ?? 'Personal',
    'cargo'           => $u['cargo'] ?? 'Personal',
    'especializacion' => $u['especializacion'] ?? '',
];

// Mensaje "flash" (tras redirigir después de un POST exitoso)
$mensaje     = $_SESSION['flash_msg']  ?? '';
$tipoMensaje = $_SESSION['flash_tipo'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

$resultadoFuncionCitas     = '';
$resultadoFuncionEmpleados = '';
$gruposSangre = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

// Helpers para leer el POST sin avisos de índices inexistentes
function post(string $k): string { return trim((string)($_POST[$k] ?? '')); }
function postNull(string $k) { $x = post($k); return $x !== '' ? $x : null; }
function fechaSQL(string $f): string {
    $f = str_replace('T', ' ', trim($f));
    return strlen($f) === 16 ? $f . ':00' : $f;
}

// ============================================================================
// PROCESAMIENTO DE FORMULARIOS (POST)
// ============================================================================
$accion = $_POST['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Paciente desde persona existente (procedimiento almacenado)
        if ($accion === 'crear_paciente_existente') {
            $stmt = $pdo->prepare("CALL sp_crear_paciente_existente(:id_persona, :num_seguro, :grupo_sanguineo, :alergias, :user, :password)");
            $stmt->execute([
                ':id_persona'      => (int)post('id_persona'),
                ':num_seguro'      => post('num_seguro'),
                ':grupo_sanguineo' => postNull('grupo_sanguineo'),
                ':alergias'        => postNull('alergias'),
                ':user'            => post('user'),
                ':password'        => post('password'),
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
                    ':nombres'   => post('nombres'),
                    ':ap1'       => post('primer_apellido'),
                    ':ap2'       => postNull('segundo_apellido'),
                    ':fnac'      => post('fecha_nacimiento'),
                    ':genero'    => postNull('genero'),
                    ':direccion' => postNull('direccion'),
                ]);
            $nuevoIdPersona = (int)$pdo->lastInsertId();

            $pdo->prepare("INSERT INTO pacientes (id_persona, num_seguro, grupo_sanguineo, alergias)
                           VALUES (:id, :seg, :gs, :al)")
                ->execute([
                    ':id'  => $nuevoIdPersona,
                    ':seg' => post('num_seguro'),
                    ':gs'  => postNull('grupo_sanguineo'),
                    ':al'  => postNull('alergias'),
                ]);

            // El trigger trg_usuarios_before_insert cifra user y password en SHA1
            $pdo->prepare("INSERT INTO usuarios (user, password, idDocPac) VALUES (:user, :password, :id)")
                ->execute([
                    ':user'     => post('user'),
                    ':password' => post('password'),
                    ':id'       => $nuevoIdPersona,
                ]);

            $pdo->commit();
            $mensaje = "Paciente nuevo creado con ID #$nuevoIdPersona y cuenta de usuario asignada.";
        }

        // 3. Modificar paciente (personas + pacientes)
        elseif ($accion === 'editar_paciente') {
            $pdo->beginTransaction();
            $idPaciente = (int)post('id_paciente');

            $pdo->prepare("UPDATE personas SET nombres = :nombres, primer_apellido = :ap1, segundo_apellido = :ap2,
                                  fecha_nacimiento = :fnac, genero = :genero, direccion = :direccion
                           WHERE id = :id")
                ->execute([
                    ':nombres'   => post('nombres'),
                    ':ap1'       => post('primer_apellido'),
                    ':ap2'       => postNull('segundo_apellido'),
                    ':fnac'      => post('fecha_nacimiento'),
                    ':genero'    => postNull('genero'),
                    ':direccion' => postNull('direccion'),
                    ':id'        => $idPaciente,
                ]);

            $pdo->prepare("UPDATE pacientes SET num_seguro = :seg, grupo_sanguineo = :gs, alergias = :al
                           WHERE id_persona = :id")
                ->execute([
                    ':seg' => post('num_seguro'),
                    ':gs'  => postNull('grupo_sanguineo'),
                    ':al'  => postNull('alergias'),
                    ':id'  => $idPaciente,
                ]);

            $pdo->commit();
            $mensaje = "Datos del paciente #$idPaciente actualizados correctamente (auditado por triggers).";
        }

        // 4. Crear cita
        elseif ($accion === 'crear_cita') {
            $pdo->prepare("INSERT INTO citas (fecha_hora, motivo, estado, paciente_id, doctor_id)
                           VALUES (:fh, :motivo, :estado, :pac, :doc)")
                ->execute([
                    ':fh'     => fechaSQL(post('fecha_hora')),
                    ':motivo' => post('motivo'),
                    ':estado' => post('estado') ?: 'Pendiente',
                    ':pac'    => (int)post('paciente_id'),
                    ':doc'    => (int)post('doctor_id'),
                ]);
            $mensaje = "Cita médica agendada correctamente.";
        }

        // 5. Modificar cita
        elseif ($accion === 'editar_cita') {
            $idCita = (int)post('id_cita');
            $pdo->prepare("UPDATE citas SET fecha_hora = :fh, motivo = :motivo, estado = :estado, doctor_id = :doc
                           WHERE id = :id")
                ->execute([
                    ':fh'     => fechaSQL(post('fecha_hora')),
                    ':motivo' => post('motivo'),
                    ':estado' => post('estado'),
                    ':doc'    => (int)post('doctor_id'),
                    ':id'     => $idCita,
                ]);
            $mensaje = "Cita #$idCita actualizada correctamente.";
        }

        // 6. Función almacenada fn_citas_proximas
        elseif ($accion === 'consultar_fn_citas') {
            $stmt = $pdo->prepare("SELECT fn_citas_proximas(:pac, :fecha) AS resultado");
            $stmt->execute([
                ':pac'   => (int)post('paciente_id'),
                ':fecha' => fechaSQL(post('fecha_consulta')),
            ]);
            $resultadoFuncionCitas = (string)$stmt->fetchColumn();
        }

        // 7. Función almacenada fn_consultar_empleados
        elseif ($accion === 'consultar_fn_empleados') {
            $stmt = $pdo->prepare("SELECT fn_consultar_empleados(:rol, :esp) AS resultado");
            $stmt->execute([':rol' => post('rol'), ':esp' => post('especialidad')]);
            $resultadoFuncionEmpleados = (string)$stmt->fetchColumn();
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[panel personal] ' . $e->getMessage());
        $tipoMensaje = 'error';
        $mensaje = "Error de Base de Datos: " . $e->getMessage();
    }

    // Post/Redirect/Get: evita duplicar registros al recargar (F5) tras una operación exitosa
    $mutaciones = ['crear_paciente_existente', 'crear_paciente_nuevo', 'editar_paciente', 'crear_cita', 'editar_cita'];
    if ($tipoMensaje === 'success' && $mensaje !== '' && in_array($accion, $mutaciones, true)) {
        $_SESSION['flash_msg']  = $mensaje;
        $_SESSION['flash_tipo'] = 'success';
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }
}

// ============================================================================
// CONSULTAS DE LECTURA (JOINs equivalentes a las vistas; no dependen de que existan)
// ============================================================================
$erroresLectura = [];
function leer(PDO $pdo, string $sql, array &$errores): array {
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        error_log('[panel personal] ' . $e->getMessage());
        $errores[] = $e->getMessage();
        return [];
    }
}

$pacientes = leer($pdo, "
    SELECT pa.id_persona AS id_paciente,
           TRIM(CONCAT_WS(' ', per.nombres, per.primer_apellido, per.segundo_apellido)) AS nombre_completo,
           per.nombres, per.primer_apellido, per.segundo_apellido,
           per.fecha_nacimiento, per.genero, per.direccion,
           pa.num_seguro, pa.grupo_sanguineo, pa.alergias
    FROM pacientes pa
    INNER JOIN personas per ON pa.id_persona = per.id
    ORDER BY pa.id_persona ASC", $erroresLectura);

$personasSinPaciente = leer($pdo, "
    SELECT per.id,
           TRIM(CONCAT_WS(' ', per.nombres, per.primer_apellido, per.segundo_apellido)) AS nombre_completo,
           per.fecha_nacimiento
    FROM personas per
    LEFT JOIN pacientes pac ON per.id = pac.id_persona
    WHERE pac.id_persona IS NULL
    ORDER BY per.id ASC", $erroresLectura);

$citas = leer($pdo, "
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
    ORDER BY c.fecha_hora DESC", $erroresLectura);

$empleados = leer($pdo, "
    SELECT e.id_persona, e.cargo, e.especializacion,
           TRIM(CONCAT_WS(' ', p.nombres, p.primer_apellido, p.segundo_apellido)) AS nombre_completo
    FROM empleados e
    INNER JOIN personas p ON e.id_persona = p.id
    ORDER BY p.primer_apellido ASC", $erroresLectura);

$registrosAuditoria = leer($pdo, "SELECT * FROM registros ORDER BY id DESC LIMIT 15", $erroresLectura);

$jsonAttr = JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Médico | Clínica Bienestar</title>
    <style>
        :root { --primary: #0284c7; --primary-dark: #0369a1; --bg: #f1f5f9; --card: #ffffff; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); margin: 0; }
        header { background: #0f172a; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        header h1 { margin: 0; font-size: 1.25rem; }
        .user-badge { font-size: 0.9rem; color: #cbd5e1; }
        .btn-logout { background: #ef4444; color: #fff; text-decoration: none; padding: 0.45rem 0.9rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; margin-left: 1rem; }
        .container { max-width: 1280px; margin: 1.5rem auto; padding: 0 1rem; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-weight: 500; }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem; flex-wrap: wrap; }
        .tab-btn { background: transparent; border: none; padding: 0.65rem 1.2rem; font-size: 0.95rem; font-weight: 600; color: var(--muted); cursor: pointer; border-radius: 8px; }
        .tab-btn.active { background: var(--primary); color: #fff; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(420px, 100%), 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
        .card { background: var(--card); border-radius: 10px; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid var(--border); margin-bottom: 1.25rem; }
        .card h3 { margin-top: 0; font-size: 1.05rem; color: #1e293b; border-bottom: 1px solid var(--border); padding-bottom: 0.6rem; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 0.75rem; }
        label { display: block; font-size: 0.82rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem; }
        input, select, textarea { width: 100%; padding: 0.55rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; }
        button.btn { background: var(--primary); color: #fff; border: none; padding: 0.6rem 1.1rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem; }
        button.btn:hover { background: var(--primary-dark); }
        button.btn-sm { padding: 0.35rem 0.7rem; font-size: 0.8rem; background: #475569; }
        button.btn-sm:hover { background: #334155; }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        th, td { text-align: left; padding: 0.7rem; border-bottom: 1px solid var(--border); }
        th { background: #f8fafc; font-weight: 700; color: #334155; }
        tr:hover { background: #f8fafc; }
        .badge { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: #e0f2fe; color: #0369a1; }
        .badge.alta { background: #dcfce7; color: #166534; }
        .badge.cambio { background: #fef3c7; color: #92400e; }
        .badge.baja { background: #fee2e2; color: #991b1b; }
        pre.fn-output { background: #0f172a; color: #38bdf8; padding: 1rem; border-radius: 8px; white-space: pre-wrap; font-size: 0.85rem; margin-top: 0.75rem; }
        .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); justify-content: center; align-items: center; z-index: 50; padding: 1rem; }
        .modal-backdrop.open { display: flex; }
        .modal { background: #fff; border-radius: 12px; max-width: 550px; width: 100%; max-height: 92vh; overflow-y: auto; padding: 1.5rem; box-shadow: 0 20px 35px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .close-btn { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b; }
    </style>
</head>
<body>

<header>
    <h1>Clínica Bienestar — Panel del Personal</h1>
    <div class="user-badge">
        <strong><?= htmlspecialchars($empleadoActual['nombre_completo']) ?></strong> |
        Cargo: <?= htmlspecialchars($empleadoActual['cargo']) ?>
        (<?= htmlspecialchars($empleadoActual['especializacion'] ?: 'General') ?>)
        <a href="../close.php" class="btn-logout">Cerrar sesión</a>
    </div>
</header>

<div class="container">
    <?php if ($mensaje): ?>
        <div class="alert <?= htmlspecialchars($tipoMensaje) ?>"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php foreach ($erroresLectura as $err): ?>
        <div class="alert error">No se pudo leer parte de los datos: <?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <div class="tabs">
        <button class="tab-btn active" onclick="abrirTab(event, 'tab-pacientes')">1. Pacientes (Alta y Edición)</button>
        <button class="tab-btn" onclick="abrirTab(event, 'tab-citas')">2. Citas Médicas</button>
        <button class="tab-btn" onclick="abrirTab(event, 'tab-funciones')">3. Funciones SQL y Auditoría</button>
    </div>

    <!-- ===================== PESTAÑA 1: PACIENTES ===================== -->
    <div id="tab-pacientes" class="tab-content active">
        <div class="grid-2">
            <div class="card">
                <h3>Alta desde Persona Existente (<code>sp_crear_paciente_existente</code>)</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="crear_paciente_existente">
                    <div class="form-row">
                        <div>
                            <label>Persona registrada sin expediente de paciente</label>
                            <select name="id_persona" required>
                                <option value="">-- Selecciona persona --</option>
                                <?php foreach ($personasSinPaciente as $per): ?>
                                    <option value="<?= (int)$per['id'] ?>">
                                        ID #<?= (int)$per['id'] ?> - <?= htmlspecialchars($per['nombre_completo']) ?> (<?= htmlspecialchars($per['fecha_nacimiento']) ?>)
                                    </option>
                                <?php endforeach; ?>
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
                                <?php foreach ($gruposSangre as $g): ?><option value="<?= $g ?>"><?= $g ?></option><?php endforeach; ?>
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
                            <label>Usuario de acceso (se cifrará en SHA1)</label>
                            <input type="text" name="user" placeholder="Ej. daniel@mail.com" required>
                        </div>
                        <div>
                            <label>Contraseña (se cifrará en SHA1)</label>
                            <input type="password" name="password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn">Ejecutar Procedimiento Almacenado</button>
                </form>
            </div>

            <div class="card">
                <h3>Registrar Paciente Nuevo (Persona + Paciente + Usuario)</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="crear_paciente_nuevo">
                    <div class="form-row">
                        <div><label>Nombres</label><input type="text" name="nombres" required></div>
                        <div><label>Primer Apellido</label><input type="text" name="primer_apellido" required></div>
                        <div><label>Segundo Apellido</label><input type="text" name="segundo_apellido"></div>
                    </div>
                    <div class="form-row">
                        <div><label>Fecha de Nacimiento</label><input type="date" name="fecha_nacimiento" required></div>
                        <div>
                            <label>Género</label>
                            <select name="genero"><option value="M">M</option><option value="F">F</option><option value="Otro">Otro</option></select>
                        </div>
                        <div><label>Dirección</label><input type="text" name="direccion"></div>
                    </div>
                    <div class="form-row">
                        <div><label>Núm. Seguro (Único)</label><input type="text" name="num_seguro" required></div>
                        <div>
                            <label>Grupo Sanguíneo</label>
                            <select name="grupo_sanguineo">
                                <?php foreach ($gruposSangre as $g): ?><option value="<?= $g ?>"><?= $g ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div><label>Alergias</label><input type="text" name="alergias"></div>
                    </div>
                    <div class="form-row">
                        <div><label>Usuario de acceso</label><input type="text" name="user" required></div>
                        <div><label>Contraseña</label><input type="password" name="password" required></div>
                    </div>
                    <button type="submit" class="btn">Registrar Paciente Completo</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h3>Listado de Pacientes</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Nombre Completo</th><th>F. Nacimiento</th><th>Género</th>
                            <th>Núm. Seguro</th><th>Tipo Sangre</th><th>Alergias</th><th>Dirección</th><th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pacientes as $pac): ?>
                            <tr>
                                <td>#<?= (int)$pac['id_paciente'] ?></td>
                                <td><strong><?= htmlspecialchars($pac['nombre_completo']) ?></strong></td>
                                <td><?= htmlspecialchars($pac['fecha_nacimiento']) ?></td>
                                <td><?= htmlspecialchars($pac['genero'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($pac['num_seguro']) ?></td>
                                <td><span class="badge"><?= htmlspecialchars($pac['grupo_sanguineo'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($pac['alergias'] ?? 'Ninguna') ?></td>
                                <td><?= htmlspecialchars($pac['direccion'] ?? '-') ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm"
                                        onclick='abrirModalPaciente(<?= json_encode($pac, $jsonAttr) ?>)'>Modificar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$pacientes): ?><tr><td colspan="9">No hay pacientes registrados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== PESTAÑA 2: CITAS ===================== -->
    <div id="tab-citas" class="tab-content">
        <div class="card">
            <h3>Agendar Nueva Cita Médica</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="crear_cita">
                <div class="form-row">
                    <div>
                        <label>Paciente</label>
                        <select name="paciente_id" required>
                            <option value="">-- Selecciona paciente --</option>
                            <?php foreach ($pacientes as $pac): ?>
                                <option value="<?= (int)$pac['id_paciente'] ?>">
                                    #<?= (int)$pac['id_paciente'] ?> - <?= htmlspecialchars($pac['nombre_completo']) ?> (Seguro: <?= htmlspecialchars($pac['num_seguro']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Doctor / Especialista Asignado</label>
                        <select name="doctor_id" required>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?= (int)$emp['id_persona'] ?>" <?= ((int)$emp['id_persona'] === $empleadoActual['id_persona']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['nombre_completo']) ?> — <?= htmlspecialchars($emp['cargo']) ?> (<?= htmlspecialchars($emp['especializacion'] ?? 'Sin especialidad') ?>)
                                </option>
                            <?php endforeach; ?>
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
                            <th>ID Cita</th><th>Fecha y Hora</th><th>Paciente</th><th>Doctor / Especialista</th>
                            <th>Especialidad</th><th>Motivo</th><th>Estado</th><th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas as $cita): ?>
                            <tr>
                                <td>#<?= (int)$cita['id_cita'] ?></td>
                                <td><?= htmlspecialchars($cita['fecha_hora']) ?></td>
                                <td><strong><?= htmlspecialchars($cita['paciente_nombre_completo']) ?></strong></td>
                                <td><?= htmlspecialchars($cita['doctor_nombre_completo']) ?> (<?= htmlspecialchars($cita['doctor_cargo']) ?>)</td>
                                <td><?= htmlspecialchars($cita['doctor_especialidad']) ?></td>
                                <td><?= htmlspecialchars($cita['motivo']) ?></td>
                                <td><span class="badge"><?= htmlspecialchars($cita['estado']) ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-sm"
                                        onclick='abrirModalCita(<?= json_encode($cita, $jsonAttr) ?>)'>Modificar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$citas): ?><tr><td colspan="8">No hay citas registradas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== PESTAÑA 3: FUNCIONES Y AUDITORÍA ===================== -->
    <div id="tab-funciones" class="tab-content">
        <div class="grid-2">
            <div class="card">
                <h3>Consultar Próximas Citas (<code>fn_citas_proximas</code>)</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="consultar_fn_citas">
                    <div class="form-row">
                        <div>
                            <label>Paciente</label>
                            <select name="paciente_id" required>
                                <?php foreach ($pacientes as $pac): ?>
                                    <option value="<?= (int)$pac['id_paciente'] ?>">#<?= (int)$pac['id_paciente'] ?> - <?= htmlspecialchars($pac['nombre_completo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>A partir de fecha y hora</label>
                            <input type="datetime-local" name="fecha_consulta" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn">Ejecutar fn_citas_proximas()</button>
                </form>
                <?php if ($resultadoFuncionCitas !== ''): ?>
                    <pre class="fn-output"><?= htmlspecialchars($resultadoFuncionCitas) ?></pre>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Directorio Médico (<code>fn_consultar_empleados</code>)</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="consultar_fn_empleados">
                    <div class="form-row">
                        <div><label>Filtrar por Cargo / Rol (opcional)</label><input type="text" name="rol" placeholder="Ej. doctor, enfermero"></div>
                        <div><label>Filtrar por Especialidad (opcional)</label><input type="text" name="especialidad" placeholder="Ej. cardiologia"></div>
                    </div>
                    <button type="submit" class="btn">Ejecutar fn_consultar_empleados()</button>
                </form>
                <?php if ($resultadoFuncionEmpleados !== ''): ?>
                    <pre class="fn-output"><?= htmlspecialchars($resultadoFuncionEmpleados) ?></pre>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3>Bitácora Automática de Triggers (<code>registros</code>)</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Tabla Origen</th><th>Acción</th><th>Fecha / Hora</th><th>Valores Actuales (JSON)</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrosAuditoria as $reg): ?>
                            <tr>
                                <td>#<?= (int)$reg['id'] ?></td>
                                <td><code><?= htmlspecialchars($reg['tabla_origen']) ?></code></td>
                                <td><span class="badge <?= htmlspecialchars($reg['accion']) ?>"><?= htmlspecialchars($reg['accion']) ?></span></td>
                                <td><?= htmlspecialchars($reg['fecha_hora']) ?></td>
                                <td><small><code><?= htmlspecialchars($reg['campos_actuales'] ?? 'N/A (Baja)') ?></code></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ===================== MODAL: EDITAR PACIENTE ===================== -->
<div id="modal-paciente" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h3 style="margin:0;">Modificar Datos del Paciente</h3>
            <button type="button" class="close-btn" onclick="cerrarModal('modal-paciente')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="accion" value="editar_paciente">
            <input type="hidden" name="id_paciente" id="edit_id_paciente">
            <div class="form-row">
                <div><label>Nombres</label><input type="text" name="nombres" id="edit_nombres" required></div>
                <div><label>Primer Apellido</label><input type="text" name="primer_apellido" id="edit_primer_apellido" required></div>
            </div>
            <div class="form-row">
                <div><label>Segundo Apellido</label><input type="text" name="segundo_apellido" id="edit_segundo_apellido"></div>
                <div><label>Fecha Nacimiento</label><input type="date" name="fecha_nacimiento" id="edit_fecha_nacimiento" required></div>
            </div>
            <div class="form-row">
                <div>
                    <label>Género</label>
                    <select name="genero" id="edit_genero"><option value="M">M</option><option value="F">F</option><option value="Otro">Otro</option></select>
                </div>
                <div><label>Dirección</label><input type="text" name="direccion" id="edit_direccion"></div>
            </div>
            <div class="form-row">
                <div><label>Núm. Seguro</label><input type="text" name="num_seguro" id="edit_num_seguro" required></div>
                <div>
                    <label>Grupo Sanguíneo</label>
                    <select name="grupo_sanguineo" id="edit_grupo_sanguineo">
                        <option value="">No especificado</option>
                        <?php foreach ($gruposSangre as $g): ?><option value="<?= $g ?>"><?= $g ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div><label>Alergias</label><input type="text" name="alergias" id="edit_alergias"></div>
            </div>
            <button type="submit" class="btn">Guardar Cambios</button>
        </form>
    </div>
</div>

<!-- ===================== MODAL: EDITAR CITA ===================== -->
<div id="modal-cita" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h3 style="margin:0;">Modificar Cita Médica</h3>
            <button type="button" class="close-btn" onclick="cerrarModal('modal-cita')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="accion" value="editar_cita">
            <input type="hidden" name="id_cita" id="edit_id_cita">
            <div class="form-row">
                <div><label>Fecha y Hora</label><input type="datetime-local" name="fecha_hora" id="edit_cita_fecha" required></div>
                <div>
                    <label>Estado</label>
                    <select name="estado" id="edit_cita_estado">
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
                    <select name="doctor_id" id="edit_cita_doctor" required>
                        <?php foreach ($empleados as $emp): ?>
                            <option value="<?= (int)$emp['id_persona'] ?>">
                                <?= htmlspecialchars($emp['nombre_completo']) ?> (<?= htmlspecialchars($emp['especializacion'] ?? $emp['cargo']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div><label>Motivo / Diagnóstico</label><input type="text" name="motivo" id="edit_cita_motivo" required></div>
            </div>
            <button type="submit" class="btn">Actualizar Cita</button>
        </form>
    </div>
</div>

<script>
function abrirTab(evt, tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    evt.currentTarget.classList.add('active');
}

function abrirModalPaciente(pac) {
    document.getElementById('edit_id_paciente').value = pac.id_paciente;
    document.getElementById('edit_nombres').value = pac.nombres || '';
    document.getElementById('edit_primer_apellido').value = pac.primer_apellido || '';
    document.getElementById('edit_segundo_apellido').value = pac.segundo_apellido || '';
    document.getElementById('edit_fecha_nacimiento').value = pac.fecha_nacimiento || '';
    document.getElementById('edit_genero').value = pac.genero || 'M';
    document.getElementById('edit_direccion').value = pac.direccion || '';
    document.getElementById('edit_num_seguro').value = pac.num_seguro || '';
    document.getElementById('edit_grupo_sanguineo').value = pac.grupo_sanguineo || '';
    document.getElementById('edit_alergias').value = pac.alergias || '';
    document.getElementById('modal-paciente').classList.add('open');
}

function abrirModalCita(cita) {
    document.getElementById('edit_id_cita').value = cita.id_cita;
    document.getElementById('edit_cita_fecha').value = cita.fecha_hora.replace(' ', 'T').slice(0, 16);
    document.getElementById('edit_cita_estado').value = cita.estado;
    document.getElementById('edit_cita_doctor').value = cita.doctor_id;
    document.getElementById('edit_cita_motivo').value = cita.motivo;
    document.getElementById('modal-cita').classList.add('open');
}

function cerrarModal(id) {
    document.getElementById(id).classList.remove('open');
}
</script>
</body>
</html>