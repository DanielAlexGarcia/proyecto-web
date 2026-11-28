<?php
require_once __DIR__ . '/../php/baseDatos/BaseDatosFactory.php';

function pinValido(string $p): bool  { return (bool) preg_match('/^\d{4,6}$/', $p); }
function passValida(string $p): bool { return strlen($p) >= 8 && strlen($p) <= 100; }

// ---------------------------------------------------------------------------
// POST JSON (fetch desde esta misma página). No requiere sesión.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $db  = BaseDatosFactory::conexion();
        $dao = BaseDatosFactory::confUser($db->getConexion());
    } catch (Throwable $e) {
        error_log('[restablecerClaves] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
        exit;
    }

    $d = $db->leerJSON();
    if (!$d) {
        $db->responder(400, ['error' => 'Datos JSON inválidos o vacíos.']);
    }

    $accion = (string) ($d['accion'] ?? '');
    $user   = trim((string) ($d['user'] ?? ''));
    if ($user === '') {
        $db->responder(422, ['error' => 'Escribe tu usuario o correo.']);
    }

    // Mensaje genérico: no revela si el usuario existe ni si tiene NIP
    $generico = 'Los datos no coinciden. Si nunca creaste un NIP, inicia sesión y créalo en Configuración.';

    try {
        if ($accion === 'recuperar_password') {          // usuario + NIP -> contraseña nueva
            $pin   = (string) ($d['pin'] ?? '');
            $nueva = (string) ($d['password_nueva'] ?? '');
            if (!pinValido($pin)) {
                $db->responder(422, ['error' => 'El NIP debe tener de 4 a 6 dígitos.']);
            }
            if (!passValida($nueva)) {
                $db->responder(422, ['error' => 'La contraseña nueva debe tener entre 8 y 100 caracteres.']);
            }
            if (!$dao->recuperarPassword($user, $pin, $nueva)) {
                $db->responder(422, ['error' => $generico]);
            }
            $db->responder(200, ['ok' => true, 'mensaje' => 'Contraseña restablecida. Ya puedes iniciar sesión.']);
        }

        if ($accion === 'recuperar_pin') {               // usuario + contraseña -> NIP nuevo
            $pass  = (string) ($d['password'] ?? '');
            $nuevo = (string) ($d['pin_nuevo'] ?? '');
            if ($pass === '') {
                $db->responder(422, ['error' => 'Escribe tu contraseña.']);
            }
            if (!pinValido($nuevo)) {
                $db->responder(422, ['error' => 'El NIP nuevo debe tener de 4 a 6 dígitos.']);
            }
            if (!$dao->recuperarPin($user, $pass, $nuevo)) {
                $db->responder(422, ['error' => 'Usuario o contraseña incorrectos.']);
            }
            $db->responder(200, ['ok' => true, 'mensaje' => 'NIP restablecido correctamente.']);
        }

        $db->responder(400, ['error' => 'Acción no válida.']);
    } catch (ConfUserBloqueadoException $e) {
        $db->responder(429, ['error' => "Demasiados intentos fallidos. Intenta de nuevo en {$e->minutos} minuto(s)."]);
    } catch (Throwable $e) {
        error_log('[restablecerClaves] ' . $e->getMessage());
        $db->responder(500, ['error' => 'No se pudo completar la operación.']);
    }
}

$seccionInicial = (($_GET['seccion'] ?? '') === 'pin') ? 'pin' : 'password';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Restablecer acceso | Clínica Bienestar</title>
    <script src="JS/tema.js"></script>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/tema.css">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="JS/popperValidacion.js"></script>
    <style>
        .msg { font-size: .92rem; margin-top: 1rem; display: none; }
        .msg.ok { color: #15803d; display: block; }
        .msg.err { color: #d93025; display: block; }
        html[data-tema="oscuro"] .msg.ok { color: #86efac; }
        html[data-tema="oscuro"] .msg.err { color: #fca5a5; }
        .sec { display: none; }
        .sec.on { display: block; }
    </style>
</head>

<body>
    <main class="auth">
        <div class="card">
            <a class="logo" href="index.html">Clínica <span>Bienestar</span></a>
            <h1>Restablecer acceso</h1>
            <p class="s">Elige qué quieres recuperar y confirma tu identidad.</p>

            <div class="tabs" role="group" aria-label="Qué restablecer">
                <button type="button" data-s="password" aria-pressed="<?= $seccionInicial === 'password' ? 'true' : 'false' ?>">Contraseña</button>
                <button type="button" data-s="pin" aria-pressed="<?= $seccionInicial === 'pin' ? 'true' : 'false' ?>">NIP</button>
            </div>

            <!-- Contraseña: usuario/correo + NIP -->
            <form id="f-password" class="sec <?= $seccionInicial === 'password' ? 'on' : '' ?>" novalidate>
                <label for="rp-user">Usuario / correo electrónico</label>
                <input id="rp-user" autocomplete="username" required>

                <label for="rp-pin">NIP</label>
                <input id="rp-pin" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" autocomplete="off"
                    title="El NIP debe tener de 4 a 6 dígitos." required>

                <label for="rp-nueva">Contraseña nueva</label>
                <input id="rp-nueva" type="password" autocomplete="new-password" minlength="8" maxlength="100" required>
                <p class="hint">Mínimo 8 caracteres.</p>

                <label for="rp-nueva2">Confirmar contraseña nueva</label>
                <input id="rp-nueva2" type="password" autocomplete="new-password" required>

                <button class="btn" type="submit">Restablecer contraseña</button>
            </form>

            <!-- NIP: usuario/correo + contraseña -->
            <form id="f-pin" class="sec <?= $seccionInicial === 'pin' ? 'on' : '' ?>" novalidate>
                <label for="rn-user">Usuario / correo electrónico</label>
                <input id="rn-user" autocomplete="username" required>

                <label for="rn-pass">Contraseña</label>
                <input id="rn-pass" type="password" autocomplete="current-password" required>

                <label for="rn-nuevo">NIP nuevo</label>
                <input id="rn-nuevo" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" autocomplete="off"
                    title="El NIP debe tener de 4 a 6 dígitos." required>
                <p class="hint">De 4 a 6 dígitos.</p>

                <label for="rn-nuevo2">Confirmar NIP nuevo</label>
                <input id="rn-nuevo2" type="password" inputmode="numeric" maxlength="6" autocomplete="off" required>

                <button class="btn" type="submit">Restablecer NIP</button>
            </form>

            <p id="msg" class="msg" role="status"></p>

            <p class="foot">
                <a href="login">← Volver a iniciar sesión</a>
            </p>
        </div>
    </main>

    <script>
        const $ = id => document.getElementById(id);

        function mostrar(texto, ok) {
            const m = $('msg');
            m.textContent = texto;
            m.className = 'msg ' + (ok ? 'ok' : 'err');
        }

        function seccion(s) {
            document.querySelectorAll('.tabs button').forEach(b => b.setAttribute('aria-pressed', b.dataset.s === s));
            $('f-password').classList.toggle('on', s === 'password');
            $('f-pin').classList.toggle('on', s === 'pin');
            Validacion.limpiarTodos();
            $('msg').className = 'msg';
        }
        document.querySelectorAll('.tabs button').forEach(b => b.onclick = () => seccion(b.dataset.s));

        async function enviar(payload) {
            const res = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            let data;
            try { data = await res.json(); } catch (_) { data = { error: 'Respuesta no válida del servidor.' }; }
            return { ok: res.ok && !data.error, data };
        }

        Validacion.activar($('f-password'), {
            reglas: { 'rp-nueva2': v => v !== $('rp-nueva').value ? 'Las contraseñas no coinciden.' : null }
        });
        Validacion.activar($('f-pin'), {
            reglas: { 'rn-nuevo2': v => v !== $('rn-nuevo').value ? 'Los NIP no coinciden.' : null }
        });

        $('f-password').addEventListener('submit', async e => {
            e.preventDefault();
            const { ok, data } = await enviar({
                accion: 'recuperar_password',
                user: $('rp-user').value.trim(),
                pin: $('rp-pin').value,
                password_nueva: $('rp-nueva').value
            });
            mostrar(ok ? data.mensaje : (data.error || 'No se pudo restablecer la contraseña.'), ok);
            if (ok) { e.target.reset(); setTimeout(() => window.location.href = 'login', 1800); }
        });

        $('f-pin').addEventListener('submit', async e => {
            e.preventDefault();
            const { ok, data } = await enviar({
                accion: 'recuperar_pin',
                user: $('rn-user').value.trim(),
                password: $('rn-pass').value,
                pin_nuevo: $('rn-nuevo').value
            });
            mostrar(ok ? data.mensaje : (data.error || 'No se pudo restablecer el NIP.'), ok);
            if (ok) e.target.reset();
        });
    </script>
</body>

</html>
