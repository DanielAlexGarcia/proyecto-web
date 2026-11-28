<?php
require_once __DIR__ . '/../php/auth/autenticacion.php';
require_once __DIR__ . '/../php/baseDatos/BaseDatosFactory.php';

$esPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($esPost) {
    $u = requerirSesionApi(['paciente', 'personal']);      // 401/403 en JSON
} else {
    $u = usuarioActual();
    if (!$u || !in_array($u['tipo'] ?? '', ['paciente', 'personal'], true)) {
        header('Location: login');
        exit;
    }
}
$idPersona = (int) ($u['id_persona'] ?? 0);

function pinValido(string $p): bool  { return (bool) preg_match('/^\d{4,6}$/', $p); }
function passValida(string $p): bool { return strlen($p) >= 8 && strlen($p) <= 100; }

$db = null;
$dao = null;
try {
    $db  = BaseDatosFactory::conexion();
    $dao = BaseDatosFactory::confUser($db->getConexion());
} catch (Throwable $e) {
    error_log('[cambiarClaves] ' . $e->getMessage());
}

// ---------------------------------------------------------------------------
// POST JSON (fetch desde esta misma página)
// ---------------------------------------------------------------------------
if ($esPost) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$dao) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
        exit;
    }
    $d = $db->leerJSON();
    if (!$d) {
        $db->responder(400, ['error' => 'Datos JSON inválidos o vacíos.']);
    }
    $accion = (string) ($d['accion'] ?? '');

    try {
        if ($accion === 'cambiar_pin') {
            $pass  = (string) ($d['password'] ?? '');
            $ant   = (string) ($d['pin_actual'] ?? '');
            $nuevo = (string) ($d['pin_nuevo'] ?? '');
            $tiene = $dao->tienePin($idPersona);

            if (!pinValido($nuevo)) {
                $db->responder(422, ['error' => 'El NIP nuevo debe tener de 4 a 6 dígitos.']);
            }
            if ($tiene && !pinValido($ant)) {
                $db->responder(422, ['error' => 'El NIP actual no es válido.']);
            }
            if ($tiene && $ant === $nuevo) {
                $db->responder(422, ['error' => 'El NIP nuevo debe ser distinto al actual.']);
            }
            if ($pass === '' || !$dao->actualizarPin($idPersona, $pass, $ant, $nuevo)) {
                $db->responder(422, ['error' => $tiene ? 'Contraseña o NIP actual incorrectos.' : 'La contraseña es incorrecta.']);
            }
            $db->responder(200, ['ok' => true, 'mensaje' => $tiene ? 'NIP actualizado correctamente.' : 'NIP creado correctamente.']);
        }

        if ($accion === 'cambiar_password') {
            $pin   = (string) ($d['pin'] ?? '');
            $ant   = (string) ($d['password_actual'] ?? '');
            $nueva = (string) ($d['password_nueva'] ?? '');

            if (!$dao->tienePin($idPersona)) {
                $db->responder(409, ['error' => 'Aún no tienes un NIP. Créalo primero en la sección "Cambiar NIP".', 'sin_pin' => true]);
            }
            if (!pinValido($pin)) {
                $db->responder(422, ['error' => 'El NIP no es válido.']);
            }
            if (!passValida($nueva)) {
                $db->responder(422, ['error' => 'La contraseña nueva debe tener entre 8 y 100 caracteres.']);
            }
            if ($nueva === $ant) {
                $db->responder(422, ['error' => 'La contraseña nueva debe ser distinta a la actual.']);
            }
            if ($ant === '' || !$dao->actualizarPassword($idPersona, $pin, $ant, $nueva)) {
                $db->responder(422, ['error' => 'NIP o contraseña actual incorrectos.']);
            }
            $db->responder(200, ['ok' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
        }

        $db->responder(400, ['error' => 'Acción no válida.']);
    } catch (ConfUserBloqueadoException $e) {
        $db->responder(429, ['error' => "Demasiados intentos fallidos. Intenta de nuevo en {$e->minutos} minuto(s)."]);
    } catch (Throwable $e) {
        error_log('[cambiarClaves] ' . $e->getMessage());
        $db->responder(500, ['error' => 'No se pudo completar la operación.']);
    }
}

// ---------------------------------------------------------------------------
// GET: página
// ---------------------------------------------------------------------------
$tienePin = false;
try {
    $tienePin = $dao ? $dao->tienePin($idPersona) : false;
} catch (Throwable $e) {
    error_log('[cambiarClaves] ' . $e->getMessage());
}
$seccionInicial = (($_GET['seccion'] ?? '') === 'pin') ? 'pin' : 'password';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cambiar contraseña o NIP | Clínica Bienestar</title>
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
        .aviso { font-size: .88rem; color: var(--m); background: var(--bg); border: 1px solid var(--l);
                 border-radius: 8px; padding: .6rem .8rem; margin-top: 1rem; }
        .sec { display: none; }
        .sec.on { display: block; }
    </style>
</head>

<body>
    <main class="auth">
        <div class="card">
            <a class="logo" href="dashboard">Clínica <span>Bienestar</span></a>
            <h1>Seguridad de la cuenta</h1>
            <p class="s"><?= htmlspecialchars($u['nombre_completo'] ?? '') ?></p>

            <div class="tabs" role="group" aria-label="Sección">
                <button type="button" data-s="password" aria-pressed="<?= $seccionInicial === 'password' ? 'true' : 'false' ?>">Contraseña</button>
                <button type="button" data-s="pin" aria-pressed="<?= $seccionInicial === 'pin' ? 'true' : 'false' ?>">NIP</button>
            </div>

            <!-- ======== Cambiar contraseña: NIP + contraseña actual + contraseña nueva ======== -->
            <form id="f-password" class="sec <?= $seccionInicial === 'password' ? 'on' : '' ?>" novalidate>
                <div id="aviso-sin-pin" class="aviso" style="display:none">
                    Aún no tienes NIP. Para cambiar tu contraseña primero
                    <a href="cambiarClaves?seccion=pin">crea tu NIP</a>.
                </div>
                <label for="pw-pin">NIP</label>
                <input id="pw-pin" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" autocomplete="off"
                    title="El NIP debe tener de 4 a 6 dígitos." required>

                <label for="pw-actual">Contraseña actual</label>
                <input id="pw-actual" type="password" autocomplete="current-password" required>

                <label for="pw-nueva">Contraseña nueva</label>
                <input id="pw-nueva" type="password" autocomplete="new-password" minlength="8" maxlength="100" required>
                <p class="hint">Mínimo 8 caracteres.</p>

                <label for="pw-nueva2">Confirmar contraseña nueva</label>
                <input id="pw-nueva2" type="password" autocomplete="new-password" required>

                <button class="btn" type="submit">Cambiar contraseña</button>
            </form>

            <!-- ======== Cambiar NIP: contraseña + NIP actual + NIP nuevo ======== -->
            <form id="f-pin" class="sec <?= $seccionInicial === 'pin' ? 'on' : '' ?>" novalidate>
                <div id="aviso-primer-pin" class="aviso" style="display:none">
                    Aún no tienes NIP. Créalo confirmando tu contraseña.
                </div>
                <label for="pin-pw">Contraseña</label>
                <input id="pin-pw" type="password" autocomplete="current-password" required>

                <div id="bloque-pin-actual">
                    <label for="pin-actual">NIP actual</label>
                    <input id="pin-actual" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" autocomplete="off"
                        title="El NIP debe tener de 4 a 6 dígitos.">
                </div>

                <label for="pin-nuevo">NIP nuevo</label>
                <input id="pin-nuevo" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" autocomplete="off"
                    title="El NIP debe tener de 4 a 6 dígitos." required>
                <p class="hint">De 4 a 6 dígitos.</p>

                <label for="pin-nuevo2">Confirmar NIP nuevo</label>
                <input id="pin-nuevo2" type="password" inputmode="numeric" maxlength="6" autocomplete="off" required>

                <button class="btn" type="submit">Guardar NIP</button>
            </form>

            <p id="msg" class="msg" role="status"></p>

            <p class="foot">
                <a href="dashboard">← Volver al panel</a>
            </p>
        </div>
    </main>

    <script>
        const $ = id => document.getElementById(id);
        let tienePin = <?= json_encode($tienePin) ?>;

        function mostrar(texto, ok) {
            const m = $('msg');
            m.textContent = texto;
            m.className = 'msg ' + (ok ? 'ok' : 'err');
        }

        function vistaPin() {
            $('bloque-pin-actual').style.display = tienePin ? 'block' : 'none';
            $('pin-actual').required = tienePin;
            $('aviso-primer-pin').style.display = tienePin ? 'none' : 'block';
            $('aviso-sin-pin').style.display = tienePin ? 'none' : 'block';
        }
        vistaPin();

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
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if (res.status === 401) { window.location.href = 'login'; return { ok: false, data: { error: 'Sesión expirada.' } }; }
            let data;
            try { data = await res.json(); } catch (_) { data = { error: 'Respuesta no válida del servidor.' }; }
            return { ok: res.ok && !data.error, data };
        }

        Validacion.activar($('f-password'), {
            reglas: {
                'pw-nueva': (v, c) => v === $('pw-actual').value ? 'Debe ser distinta a la contraseña actual.' : null,
                'pw-nueva2': v => v !== $('pw-nueva').value ? 'Las contraseñas no coinciden.' : null
            }
        });
        Validacion.activar($('f-pin'), {
            reglas: {
                'pin-nuevo': v => (tienePin && v === $('pin-actual').value) ? 'Debe ser distinto al NIP actual.' : null,
                'pin-nuevo2': v => v !== $('pin-nuevo').value ? 'Los NIP no coinciden.' : null
            }
        });

        $('f-password').addEventListener('submit', async e => {
            e.preventDefault();
            const { ok, data } = await enviar({
                accion: 'cambiar_password',
                pin: $('pw-pin').value,
                password_actual: $('pw-actual').value,
                password_nueva: $('pw-nueva').value
            });
            mostrar(ok ? data.mensaje : (data.error || 'No se pudo cambiar la contraseña.'), ok);
            if (ok) e.target.reset();
        });

        $('f-pin').addEventListener('submit', async e => {
            e.preventDefault();
            const { ok, data } = await enviar({
                accion: 'cambiar_pin',
                password: $('pin-pw').value,
                pin_actual: $('pin-actual').value,
                pin_nuevo: $('pin-nuevo').value
            });
            mostrar(ok ? data.mensaje : (data.error || 'No se pudo cambiar el NIP.'), ok);
            if (ok) { e.target.reset(); tienePin = true; vistaPin(); }
        });
    </script>
</body>

</html>
