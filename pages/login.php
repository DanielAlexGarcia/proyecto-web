<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../php/auth/autenticacion.php';
require_once __DIR__ . '/../php/baseDatos/BaseDatosFactory.php';

function generarCaptcha(): string
{
    // Se omiten caracteres ambiguos (0, O, 1, I, l)
    $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $codigo = '';
    for ($i = 0; $i < 5; $i++) {
        $codigo .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }

    $_SESSION['captcha_codigo'] = $codigo;

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="46" viewBox="0 0 160 46">';
    $svg .= '<rect width="100%" height="100%" rx="6" fill="#f1f5f9"/>';

    $coloresRuido = ['#94a3b8', '#cbd5e1', '#64748b'];
    for ($i = 0; $i < 5; $i++) {
        $x1 = random_int(0, 160);
        $y1 = random_int(0, 46);
        $x2 = random_int(0, 160);
        $y2 = random_int(0, 46);
        $color = $coloresRuido[array_rand($coloresRuido)];
        $svg .= "<line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"{$color}\" stroke-width=\"1.2\"/>";
    }

    $coloresTexto = ['#0f172a', '#1e3a8a', '#0f766e', '#334155'];
    for ($i = 0; $i < strlen($codigo); $i++) {
        $x = 18 + ($i * 27);
        $y = random_int(29, 35);
        $angulo = random_int(-18, 18);
        $color = $coloresTexto[array_rand($coloresTexto)];
        $letra = htmlspecialchars($codigo[$i], ENT_QUOTES, 'UTF-8');
        $svg .= "<text x=\"{$x}\" y=\"{$y}\" fill=\"{$color}\" font-family=\"monospace\" font-size=\"24\" font-weight=\"bold\" transform=\"rotate({$angulo} {$x} {$y})\">{$letra}</text>";
    }

    $svg .= '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// Endpoint ligero para refrescar solo la imagen CAPTCHA sin recargar el formulario
if (isset($_GET['refresh_captcha'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['img' => generarCaptcha()]);
    exit;
}

$error = '';
$cone = null;

try {
    $cone = BaseDatosFactory::pdo();
} catch (Throwable $e) {
    $error = 'No se pudo conectar a la base de datos.' . $e->getMessage();
    error_log('[login] ' . $e->getMessage());
}

function verificarUsuario(PDO $conexion, string $user, string $password)
{
    return BaseDatosFactory::usuario($conexion)->buscarPorCredenciales($user, $password) ?? false;
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
    $captchaInput = trim($_POST['captcha'] ?? '');
    $captchaGuardado = $_SESSION['captcha_codigo'] ?? '';

    // Se invalida de inmediato para evitar reutilización del mismo código
    unset($_SESSION['captcha_codigo']);

    if ($captchaInput === '' || $captchaGuardado === '' || strcasecmp($captchaInput, $captchaGuardado) !== 0) {
        $error = 'El código de verificación (CAPTCHA) es incorrecto.';
    } else {
        try {
            $usuario = verificarUsuario($cone, $user, $password);

            if (!$usuario) {
                $error = 'Correo electrónico o contraseña incorrectos.';
            } else {
                $idPersona = (int) ($usuario['id_persona'] ?? 0);   // antes: idDocPac


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

                        header("Location: dashboard");
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

                        header("Location: dashboard");
                        exit;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[login] ' . $e->getMessage());
            $error = 'DEBUG: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine();
        }
    }
}

$captchaImg = generarCaptcha();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Iniciar sesión | Clínica Bienestar</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="JS/popperValidacion.js"></script>
</head>

<body>
    <main class="auth">
        <div class="card">
            <a class="logo" href="index.html">Clínica <span>Bienestar</span></a>
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
                <input id="u" name="user" autocomplete="username" required placeholder="correo@ejemplo.com"
                    value="<?= htmlspecialchars($_POST['user'] ?? '') ?>">

                <label for="p">Contraseña</label>
                <input id="p" name="password" type="password" autocomplete="current-password" required>

                <label for="captcha">Código de verificación</label>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <img id="captcha-img" src="<?= $captchaImg ?>" alt="Código CAPTCHA"
                        style="border: 1px solid #cbd5e1; border-radius: 6px; height: 46px;">
                    <button type="button" id="btn-refresh-captcha" title="Generar otro código"
                        aria-label="Generar otro código"
                        style="padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; cursor: pointer; font-size: 1.1rem;">↻</button>
                </div>
                <input id="captcha" name="captcha" type="text" autocomplete="off" required maxlength="5"
                    placeholder="Escribe los 5 caracteres" style="text-transform: uppercase;">

                <div class="opt">
                    <a href="restablecerClaves">¿Olvidaste tu contraseña o NIP?</a>
                </div>

                <button class="btn" type="submit">Entrar</button>
            </form>

            <p class="foot">
                ¿Eres paciente nuevo? <a href="registro">Crea tu cuenta</a><br>
                <a href="index.html">← Volver al inicio</a>
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

        document.getElementById('btn-refresh-captcha').addEventListener('click', async () => {
            try {
                const res = await fetch('?refresh_captcha=1');
                const data = await res.json();
                if (data.img) {
                    document.getElementById('captcha-img').src = data.img;
                    document.getElementById('captcha').value = '';
                    document.getElementById('captcha').focus();
                }
            } catch (e) {
                console.error('Error al actualizar CAPTCHA', e);
            }
        });
        Validacion.activar(document.getElementById('f'), {
            reglas: {
                captcha: v => v.trim().length !== 5 ? 'Escribe los 5 caracteres del código.' : null
            }
        });
    </script>
</body>

</html>