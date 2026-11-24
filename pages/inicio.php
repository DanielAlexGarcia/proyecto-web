<?php
require_once __DIR__ . '/../php/auth/autenticacion.php';
$u = requerirSesionPagina('paciente');
$nombreUsuario = $u['nombre_completo'] ?? 'Paciente';
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Mi panel | Clínica Bienestar</title>
  <link rel="stylesheet" href="../css/panel.css">
</head>
<body>
  <header>
    <div class="wrap">
      <nav>
        <a class="logo" href="inicio.html">Clínica <span>Bienestar</span></a>
        <div class="links">
          <a class="on" href="inicio.html">Inicio</a>
          <a href="#citas">Mis citas</a>
          <a href="#recetas">Recetas</a>
          <a href="#cuenta">Mi cuenta</a>
        </div>
        <div class="user">
          <span class="av" id="ini">P</span>
          <span id="nom">Paciente</span>
          <a class="btn o" href="../close.php">Cerrar sesión</a>
        </div>
      </nav>
    </div>
  </header>

  <main class="wrap">
    <h1 id="hola">Hola, <?= htmlspecialchars($_SESSION['nameUser']) ?> 👋</h1>
    <p class="sub">Este es el resumen de tu atención en la clínica.</p>

    <div class="grid">
      <section class="c next s7" id="citas">
        <small>Tu próxima cita</small>
        <div class="big">Chequeo general</div>
        <div>Martes 18 de noviembre · 10:20 a. m. · 10 min</div>
        <small>Con Dr(a). Apellido · Sala de exploración 3</small><br>
        <a class="btn" href="#">Reprogramar</a>
        <a class="btn o" href="#">Cancelar</a>
      </section>

      <section class="c s5">
        <h2>Saldo pendiente</h2>
        <div class="saldo">$0.00</div>
        <p style="color:var(--m);font-size:.9rem;margin:.2rem 0 .8rem">Cobertura: <span class="tag">Seguro médico</span></p>
        <a class="btn o" href="#cuenta">Ver estado de cuenta</a>
      </section>

      <a class="c q s4" href="#">
        <span class="i">📅</span>
        <b>Agendar cita</b>
        <span>Chequeos, inmunizaciones y consultas</span>
      </a>

      <a class="c q s4" href="#">
        <span class="i">🚶</span>
        <b>Visita sin cita</b>
        <span>Consulta las horas no agendadas</span>
      </a>

      <a class="c q s4" href="#">
        <span class="i">🧪</span>
        <b>Resultados de laboratorio</b>
        <span>Pruebas y estudios recientes</span>
      </a>

      <section class="c s7">
        <h2>Visitas recientes <a href="#">Ver historial</a></h2>
        <div class="sc">
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Motivo</th>
                <th>Profesional</th>
                <th>Diagnóstico</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>28 oct</td>
                <td>Inmunización</td>
                <td>Enf. practicante</td>
                <td>—</td>
              </tr>
              <tr>
                <td>05 sep</td>
                <td>Consulta general</td>
                <td>Dr(a). Apellido</td>
                <td>Seguimiento</td>
              </tr>
              <tr>
                <td>12 jul</td>
                <td>Chequeo</td>
                <td>Dr(a). Apellido</td>
                <td>Sin hallazgos</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="c s5" id="recetas">
        <h2>Recetas activas <a href="#">Ver todas</a></h2>
        <ul class="l">
          <li>
            <div>Medicamento ejemplo 500 mg<small>Indicaciones: 1 cada 8 horas</small></div>
            <span class="tag">2 recargas</span>
          </li>
          <li>
            <div>Medicamento ejemplo 10 mg<small>Indicaciones: 1 al día</small></div>
            <span class="tag w">Última recarga</span>
          </li>
        </ul>
      </section>

      <section class="c s6" id="cuenta">
        <h2>Mis pagos <a href="#">Estados de cuenta</a></h2>
        <ul class="l">
          <li>
            <div>Octubre 2026<small>Servicios del mes</small></div>
            <span class="tag">Pagado</span>
          </li>
          <li>
            <div>Septiembre 2026<small>Deducible de la visita</small></div>
            <span class="tag">Pagado</span>
          </li>
        </ul>
      </section>

      <section class="c em s6">
        <h2>Horarios y emergencias</h2>
        <p>
          Atendemos de lunes a viernes, sábados por la mañana y dos tardes por semana.<br>
          <strong>Emergencias 24/7:</strong> llama al número de la clínica y tu llamada se desviará al profesional de guardia.
        </p>
      </section>
    </div>
  </main>

  <script>
    try {
      const n = sessionStorage.getItem('nombre');
      if (n) {
        document.getElementById('nom').textContent = n;
        document.getElementById('hola').textContent = 'Hola, ' + n.split(' ')[0] + ' 👋';
        document.getElementById('ini').textContent = n[0].toUpperCase();
      }
    } catch (e) {}
  </script>
</body>
</html>