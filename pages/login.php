<?php
require_once __DIR__ . '/../php/auth/autenticacion.php';
require_once('../php/baseDatos/conexionBaseDatos.php');

$error = '';
$cone = null;

try {
    $con = new conexionBaseDatos();
    $cone = $con->getConexion();
} catch (Throwable $e) {
    $error = 'No se pudo conectar a la base de datos.';
    error_log('[login] ' . $e->getMessage());
}

/** Detecta si la tabla de credenciales se llama `usuarios` o `users`. */
function tablaCredenciales(PDO $conexion): ?string
{
    foreach (['usuarios', 'users'] as $tabla) {
        $stmt = $conexion->query("SHOW TABLES LIKE " . $conexion->quote($tabla));
        if ($stmt && $stmt->fetchColumn()) {
            return $tabla;
        }
    }
    return null;
}

function verificarUsuario(PDO $conexion, string $user, string $password)
{
    $tabla = tablaCredenciales($conexion);
    if ($tabla === null) {
        throw new RuntimeException('No existe la tabla de usuarios (usuarios/users).');
    }

    $sql = "SELECT * FROM `$tabla` WHERE user = SHA1(:u) AND password = SHA1(:p) LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':u' => $user, ':p' => $password]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fila) {
        $fila['_tabla'] = $tabla;
    }
    return $fila;
}

/** Obtiene el id de la persona ligada al usuario. */
function obtenerIdPersona(PDO $conexion, array $usuario): int
{
    // Columnas posibles que enlazan con personas.id
    foreach (['idDocPac', 'id_persona', 'persona_id'] as $col) {
        if (!empty($usuario[$col])) {
            return (int) $usuario[$col];
        }
    }

    // Respaldo: la tabla `users` solo guarda el nombre completo (como lo arma registro.php)
    if (!empty($usuario['name'])) {
        $stmt = $conexion->prepare(
            "SELECT id FROM personas
             WHERE TRIM(CONCAT_WS(' ', nombres, primer_apellido, segundo_apellido)) = :n
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':n' => trim($usuario['name'])]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
    }
    return 0;
}

function obtenerDatosPaciente(PDO $conexion, int $idPersona)
{
    $sql = "SELECT p.id, p.nombres, p.primer_apellido, p.segundo_apellido,
                   p.fecha_nacimiento, p.genero, p.direccion,
                   pa.num_seguro, pa.grupo_sanguineo, pa.alergias
            FROM pacientes pa
            INNER JOIN personas p ON pa.id_persona = p.id
            WHERE pa.id_persona = :id
            LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $idPersona]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtenerDatosEmpleado(PDO $conexion, int $idPersona)
{
    $sql = "SELECT p.id, p.nombres, p.primer_apellido, p.segundo_apellido,
                   e.cargo, e.especializacion
            FROM empleados e
            INNER JOIN personas p ON e.id_persona = p.id
            WHERE e.id_persona = :id
            LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $idPersona]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($cone && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $password = $_POST['password'] ?? '';
    $tipoAcceso = $_POST['tipo_acceso'] ?? 'paciente';

    try {
        $usuario = verificarUsuario($cone, $user, $password);

        if (!$usuario) {
            $error = 'Correo electrónico o contraseña incorrectos.';
        } else {
            $idPersona = obtenerIdPersona($cone, $usuario);

            if ($tipoAcceso === 'paciente') {
                $datos = $idPersona ? obtenerDatosPaciente($cone, $idPersona) : false;

                if (!$datos) {
                    $error = 'Esta cuenta no está registrada como paciente. Si eres trabajador, selecciona la pestaña "Personal".';
                } else {
                    $nombreCompleto = trim($datos['nombres'] . ' ' . $datos['primer_apellido'] . ' ' . ($datos['segundo_apellido'] ?? ''));

                    emitirToken([
                        'tipo' => 'paciente',
                        'id_persona' => (int) $datos['id'],
                        'paciente_id' => (int) $datos['id'],
                        'nombre_completo' => $nombreCompleto,
                        'num_seguro' => $datos['num_seguro'],
                    ]);

                    header('Location: homePaciente.php');
                    exit;
                }
            } else {
                $datos = $idPersona ? obtenerDatosEmpleado($cone, $idPersona) : false;

                if (!$datos) {
                    $error = 'Esta cuenta no está registrada como personal de la clínica.';
                } else {
                    $nombreCompleto = trim($datos['nombres'] . ' ' . $datos['primer_apellido'] . ' ' . ($datos['segundo_apellido'] ?? ''));

                    emitirToken([
                        'tipo' => 'personal',
                        'id_persona' => (int) $datos['id'],
                        'nombre_completo' => $nombreCompleto,
                        'cargo' => $datos['cargo'],
                        'especializacion' => $datos['especializacion'] ?? '',
                    ]);

                    header('Location: homePersonal.php');
                    exit;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[login] ' . $e->getMessage());
        // Temporal para depurar; quita el detalle cuando ya funcione:
        $error = 'Error interno: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Iniciar sesión | Clínica Bienestar</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>

<body>
    <main class="auth">
        <div class="card">
            <a class="logo" href="../index.html">Clínica <span>Bienestar</span></a>
            <h1>Iniciar sesión</h1>
            <p class="s">Accede a tu cuenta para continuar.</p>

            <?php if (!empty($error)): ?>
                <p style="color: #d93025; font-size: 0.9rem; margin-bottom: 1rem;">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <div class="tabs" role="group" aria-label="Tipo de acceso">
                <button type="button" aria-pressed="true" data-t="paciente">Paciente</button>
                <button type="button" aria-pressed="false" data-t="personal">Personal</button>
            </div>

            <form id="f" method="POST" action="">
                <input type="hidden" id="tipo_acceso" name="tipo_acceso" value="paciente">

                <div id="rol" style="display: none;">
                    <label for="r">Rol</label>
                    <select id="r" name="rol">
                        <option>Administrador</option>
                        <option>Recepcionista</option>
                        <option>Médico</option>
                        <option>Enfermera practicante</option>
                        <option>Enfermera registrada</option>
                        <option>Partera</option>
                        <option>Farmacéutico</option>
                        <option>Técnico médico</option>
                        <option>Contador</option>
                        <option>Doctor</option>
                    </select>
                </div>

                <label for="u">Correo electrónico</label>
                <input id="u" name="user" autocomplete="username" required placeholder="correo@ejemplo.com">

                <label for="p">Contraseña</label>
                <input id="p" name="password" type="password" autocomplete="current-password" required>

                <div class="opt">
                    <a href="#">¿Olvidaste tu contraseña?</a>
                </div>

                <button class="btn" type="submit">Entrar</button>
            </form>

            <p class="foot">
                ¿Eres paciente nuevo? <a href="registro.php">Crea tu cuenta</a><br>
                <a href="../index.html">← Volver al inicio</a>
            </p>
        </div>
    </main>

    <script>
        document.querySelectorAll('.tabs button').forEach(b => b.onclick = () => {
            document.querySelectorAll('.tabs button').forEach(x => x.setAttribute('aria-pressed', x === b));
            const esPersonal = b.dataset.t === 'personal';
            document.getElementById('rol').style.display = esPersonal ? 'block' : 'none';
            document.getElementById('tipo_acceso').value = b.dataset.t;
        });
    </script>
</body>

</html>