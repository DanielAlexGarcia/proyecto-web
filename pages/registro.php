<?php
require_once __DIR__ . '/../php/baseDatos/BaseDatosFactory.php';
require_once __DIR__ . '/../php/validaciones/validacionesPersona.php';

// Alta de paciente (POST JSON enviado con fetch desde este mismo formulario)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $db = BaseDatosFactory::conexion(); // también carga los modelos
    $d  = $db->leerJSON();

    if (!$d) {
        $db->responder(400, ['error' => 'Datos JSON inválidos o vacíos.']);
    }

    try {
        $nombres  = trim((string) ($d['names'] ?? ''));
        $partes   = explode(' ', trim((string) ($d['APS'] ?? '')), 2);
        $apellido1 = $partes[0] ?? '';
        $apellido2 = (isset($partes[1]) && trim($partes[1]) !== '') ? trim($partes[1]) : null;
        $fechaNac = (string) ($d['fechaNac'] ?? '');
        $direccion = trim((string) ($d['direction'] ?? ''));
        $correo   = trim((string) ($d['correo'] ?? ''));
        $pass     = (string) ($d['pass'] ?? '');

        // num_seguro es NOT NULL + UNIQUE: si no hay cobertura, se genera un folio interno
        $numSeguro = trim((string) ($d['numSeguro'] ?? ''));
        if ($numSeguro === '') {
            $numSeguro = 'PAC-' . strtoupper(bin2hex(random_bytes(5)));
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo electrónico no es válido.');
        }

        ValidadorPersonas::persona([
            'nombres' => $nombres, 'primer_apellido' => $apellido1, 'segundo_apellido' => $apellido2,
            'fecha_nacimiento' => $fechaNac, 'genero' => '', 'direccion' => $direccion,
        ]);
        ValidadorPersonas::paciente(['num_seguro' => $numSeguro, 'grupo_sanguineo' => '', 'alergias' => '']);
        ValidadorPersonas::credenciales(['user' => $correo, 'password' => $pass]);

        $pdo = $db->getConexion();
        BaseDatosFactory::paciente($pdo)->crearConTransaccion(
            $nombres, $apellido1, $apellido2, $fechaNac, null, $direccion !== '' ? $direccion : null,
            new paciente(0, $numSeguro, null, null),
            $correo,
            $pass
        );

        $db->responder(201, ['ok' => true, 'mensaje' => 'Paciente y usuario registrados correctamente.']);

    } catch (InvalidArgumentException $e) {
        $db->responder(422, ['error' => $e->getMessage()]);
    } catch (PDOException $e) {
        error_log('[registro] ' . $e->getMessage());
        if (BaseDatosFactory::tipoError($e) === 'duplicado') {
            $db->responder(409, ['error' => 'Ya existe una cuenta o un paciente con esos datos (correo o número de seguro).']);
        }
        $db->responder(500, ['error' => 'No se pudo completar el registro.']);
    } catch (Throwable $e) {
        error_log('[registro] ' . $e->getMessage());
        $db->responder(500, ['error' => 'Error interno al registrar.']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Registro de paciente | Clínica Bienestar</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
  <script src="JS/popperValidacion.js"></script>
</head>

<body>
  <main class="auth">
    <div class="card w">
      <a class="logo" href="index.html">Clínica <span>Bienestar</span></a>
      <h1>Crear cuenta de paciente</h1>
      <p class="s">Regístrate para agendar citas y consultar tu historial y saldos.</p>

      <form id="f" novalidate>
        <div class="row">
          <div>
            <label for="n">Nombre(s)</label>
            <input id="n" autocomplete="given-name" required>
          </div>
          <div>
            <label for="a">Apellidos</label>
            <input id="a" autocomplete="family-name" required>
          </div>
          <div>
            <label for="fn">Fecha de nacimiento</label>
            <input id="fn" type="date" required>
          </div>
          <div>
            <label for="t">Teléfono</label>
            <input id="t" type="tel" autocomplete="tel" required>
          </div>
        </div>

        <label for="d">Dirección</label>
        <input id="d" autocomplete="street-address" required>

        <label for="e">Correo electrónico</label>
        <input id="e" type="email" autocomplete="email" required>

        <label for="c">Tipo de cobertura</label>
        <select id="c">
          <option value="">Selecciona una opción</option>
          <option value="privado">Particular (pago directo)</option>
          <option value="seguro">Seguro médico</option>
          <option value="estado">Tarjeta médica del Estado</option>
          <option value="ninguna">Sin cobertura</option>
        </select>

        <div id="cob" style="display:none">
          <label for="np" id="lnp">Número</label>
          <input id="np">
          <p class="hint">Lo usaremos para la facturación a terceros.</p>
        </div>

        <div class="row">
          <div>
            <label for="p1">Contraseña</label>
            <input id="p1" type="password" autocomplete="new-password" minlength="8" required>
            <p class="hint">Mínimo 8 caracteres.</p>
          </div>
          <div>
            <label for="p2">Confirmar contraseña</label>
            <input id="p2" type="password" autocomplete="new-password" required>
          </div>
        </div>

        <label style="display:flex;gap:.5rem;font-weight:400;align-items:flex-start;margin-top:1.1rem">
          <input id="priv" type="checkbox" required style="width:auto;margin-top:.3rem"> Acepto el aviso de privacidad y
          el tratamiento de mis datos de salud.
        </label>

        <button class="btn" type="submit">Registrarme</button>
      </form>

      <p class="foot">
        ¿Ya tienes cuenta? <a href="login">Inicia sesión</a><br>
        <a href="index.html">← Volver al inicio</a>
      </p>
    </div>
  </main>

  <script>
    const $ = id => document.getElementById(id);
    const cobertura = $('c'), bloque = $('cob'), etiqueta = $('lnp');

    cobertura.onchange = () => {
      const v = cobertura.value;
      bloque.style.display = (v === 'seguro' || v === 'estado') ? 'block' : 'none';
      etiqueta.textContent = v === 'seguro' ? 'Número de póliza y aseguradora' : 'Número de tarjeta médica';
    };

    const form = $('f');

    // La validación se activa UNA vez (captura el submit y detiene el envío si hay errores)
    Validacion.activar(form, {
      reglas: {
        p2: v => v !== $('p1').value ? 'Las contraseñas no coinciden.' : null,
        fn: v => v > new Date().toISOString().slice(0, 10) ? 'La fecha no puede ser futura.' : null
      }
    });

    form.addEventListener('submit', async e => {
      e.preventDefault();

      const v = cobertura.value;
      const payload = {
        names: $('n').value.trim(),
        APS: $('a').value.trim(),
        fechaNac: $('fn').value,
        tel: $('t').value.trim(),
        direction: $('d').value.trim(),
        correo: $('e').value.trim(),
        numSeguro: (v === 'seguro' || v === 'estado') ? $('np').value.trim() : '',
        pass: $('p1').value
      };

      try {
        const res = await fetch(window.location.href, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (res.ok) {
          alert(data.mensaje || 'Registro exitoso.');
          window.location.href = 'login';
        } else {
          Validacion.mostrarError($('e'), data.error || 'Ocurrió un error al registrar.');
        }
      } catch (err) {
        alert('Error de conexión con el servidor.');
      }
    });
  </script>
</body>

</html>
