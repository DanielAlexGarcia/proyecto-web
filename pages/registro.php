<?php


require_once __DIR__ . '/../php/baseDatos/conexionBaseDatos.php';
require_once __DIR__ . '/../php/modelos/persona.php';
require_once __DIR__ . '/../php/modelosDAO/personaDAO.php';

function newPaciente($names, $APS, $fechaNac, $tel, $direction, $correo, $User, $pass)
{
  $db = new conexionBaseDatos();
  $conexion = $db->getConexion();

  // Separar apellidos en primer y segundo apellido para el modelo persona
  $partesApellidos = explode(' ', trim($APS), 2);
  $primerApellido = $partesApellidos[0] ?? '';
  $segundoApellido = $partesApellidos[1] ?? '';

  try {
    $conexion->beginTransaction();

    // 1. Dar de alta a la Persona usando PersonaDAO
    $nuevaPersona = new persona(
      trim($names),
      $primerApellido,
      $segundoApellido,
      $fechaNac,
      "M",
      trim($direction)
    );

    $personaDAO = new PersonaDAO($conexion);
    if (!$personaDAO->insertar($nuevaPersona)) {
      throw new Exception('No se pudo registrar la persona.');
    }

    $idPersona = (int) $conexion->lastInsertId();

    // 2. Dar de alta en la tabla users con user y password encriptados en SHA1
    $sqlUser = "INSERT INTO users (user, password, name) VALUES (SHA1(:user), SHA1(:password), :name)";
    $stmtUser = $conexion->prepare($sqlUser);

    $nombreCompleto = trim($names . ' ' . $APS);

    $exitoUser = $stmtUser->execute([
      ':user' => sha1(trim($User)),
      ':password' => sha1($pass),
      ':name' => $nombreCompleto
    ]);

    if (!$exitoUser) {
      throw new Exception('No se pudo crear el usuario.');
    }

    $conexion->commit();

    return [
      'ok' => true,
      'idPersona' => $idPersona,
      'mensaje' => 'Paciente y usuario registrados correctamente.'
    ];

  } catch (Exception $e) {
    if ($conexion->inTransaction()) {
      $conexion->rollBack();
    }
    return [
      'ok' => false,
      'error' => $e->getMessage()
    ];
  }
}

// Procesar la petición POST enviada desde el formulario vía fetch()
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  $db = new conexionBaseDatos();
  $datos = $db->leerJSON();

  if (!$datos) {
    $db->responder(400, ['error' => 'Datos JSON inválidos o vacíos.']);
  }

  $resultado = newPaciente(
    $datos['names'] ?? '',
    $datos['APS'] ?? '',
    $datos['fechaNac'] ?? '',
    $datos['tel'] ?? '',
    $datos['direction'] ?? '',
    $datos['correo'] ?? '',
    $datos['User'] ?? ($datos['correo'] ?? ''),
    $datos['pass'] ?? ''
  );

  if ($resultado['ok']) {
    $db->responder(201, $resultado);
  } else {
    $db->responder(500, ['error' => $resultado['error']]);
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
      <a class="logo" href="../index.html">Clínica <span>Bienestar</span></a>
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
    const c = document.getElementById('c'),
      b = document.getElementById('cob'),
      l = document.getElementById('lnp');

    c.onchange = () => {
      const v = c.value;
      b.style.display = (v === 'seguro' || v === 'estado') ? 'block' : 'none';
      l.textContent = v === 'seguro' ? 'Número de póliza y aseguradora' : 'Número de tarjeta médica';
    };

    document.getElementById('f').onsubmit = async e => {
      e.preventDefault();

      const names = document.getElementById('n').value.trim();
      const APS = document.getElementById('a').value.trim();
      const fechaNac = document.getElementById('fn').value;
      const tel = document.getElementById('t').value.trim();
      const direction = document.getElementById('d').value.trim();
      const correo = document.getElementById('e').value.trim();
      const p1 = document.getElementById('p1').value;
      const p2 = document.getElementById('p2').value;
      const priv = document.getElementById('priv').checked;

      const f = document.getElementById('f');
      Validacion.activar(f, {
        reglas: {
          p2: (v) => v !== document.getElementById('p1').value ? 'Las contraseñas no coinciden.' : null,
          fn: (v) => v > new Date().toISOString().slice(0, 10) ? 'La fecha no puede ser futura.' : null
        }
      });

      f.onsubmit = async e => {
        e.preventDefault();
        // ...lee los campos y hace el fetch igual que antes...
        if (res.ok) {
          window.location.href = 'login';
        } else {
          Validacion.mostrarError(document.getElementById('e'), data.error || 'Ocurrió un error al registrar.');
        }
      };
      try {
        const res = await fetch(window.location.href, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            names,
            APS,
            fechaNac,
            tel,
            direction,
            correo,
            User: correo, // Se usa el correo como identificador de usuario
            pass: p1
          })
        });

        const data = await res.json();

        if (res.ok) {
          alert(data.mensaje || 'Registro exitoso.');
          window.location.href = 'login';
        } else {
          alert(data.error || 'Ocurrió un error al registrar.');
        }
      } catch (err) {
        alert('Error de conexión con el servidor.');
      }
    };
  </script>
</body>

</html>