<?php
require_once __DIR__ . '/../php/auth/autenticacion.php';
$u = requerirSesionPagina('paciente', 'login');

require_once __DIR__ . '/../php/baseDatos/conexionBaseDatos.php';
require_once __DIR__ . '/../php/validacionesCita.php';

$pacienteId = (int) $u['paciente_id'];
$nombrePaciente = $u['nombre_completo'] ?? 'Paciente';
$numSeguro = $u['num_seguro'] ?? 'Activo';

$partes = array_values(array_filter(explode(' ', $nombrePaciente)));
$iniciales = strtoupper(mb_substr($partes[0] ?? 'P', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));

// 4. Carga de doctores y citas (PDO directo; no depende de CitaDAO)
$listaDoctores = [];
$citasPaciente = [];
$errorCarga = '';

try {
    $db = new conexionBaseDatos();
    $pdo = $db->getConexion();

    $listaDoctores = $pdo->query(
        "SELECT p.id AS doctor_id,
                TRIM(CONCAT_WS(' ', p.nombres, p.primer_apellido, p.segundo_apellido)) AS nombre_doctor,
                IFNULL(e.especializacion, e.cargo) AS especialidad
         FROM empleados e
         INNER JOIN personas p ON e.id_persona = p.id
         WHERE LOWER(e.cargo) LIKE '%doctor%' OR LOWER(e.cargo) LIKE '%medic%'
         ORDER BY especialidad ASC, nombre_doctor ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($listaDoctores as &$d) {
        $d['doctor_id'] = (int) $d['doctor_id'];
    }
    unset($d);

    $stmt = $pdo->prepare(
        "SELECT c.id, c.fecha_hora, c.motivo, c.estado, c.paciente_id, c.doctor_id,
                TRIM(CONCAT_WS(' ', pd.nombres, pd.primer_apellido, pd.segundo_apellido)) AS nombre_doctor,
                IFNULL(e.especializacion, e.cargo) AS especialidad
         FROM citas c
         INNER JOIN empleados e ON c.doctor_id = e.id_persona
         INNER JOIN personas pd ON pd.id = e.id_persona
         WHERE c.paciente_id = :id AND LOWER(c.estado) <> 'cancelada'
         ORDER BY c.fecha_hora ASC"
    );
    $stmt->execute([':id' => $pacienteId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $f['id'] = (int) $f['id'];
        $f['paciente_id'] = (int) $f['paciente_id'];
        $f['doctor_id'] = (int) $f['doctor_id'];
        $citasPaciente[] = $f;
    }
} catch (Throwable $e) {
    error_log('[inicio] ' . $e->getMessage());
    $errorCarga = 'No se pudieron cargar tus citas. Intenta de nuevo más tarde.';
}

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Paciente | Clínica Bienestar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="JS/popperValidacion.js"></script>
    <style>
        body {
            background: #f8fafc;
            color: #1e293b;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(.96) translateY(8px)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .animate-modal {
            animation: fadeInScale .22s ease-out forwards;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .animate-toast {
            animation: slideInRight .3s cubic-bezier(.16, 1, .3, 1) forwards;
        }
    </style>
</head>

<body class="min-h-screen flex flex-col antialiased">

    <!-- ================= NAVEGACIÓN ================= -->
    <header class="sticky top-0 z-40 w-full bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">
                <a href="#inicio" onclick="scrollToTop(event)" class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#0284c7] to-[#0d9488] flex items-center justify-center text-white font-bold text-xl">
                        +</div>
                    <div class="flex flex-col">
                        <span class="text-base md:text-lg font-bold text-slate-900 leading-tight">Clínica <span
                                class="text-[#0284c7]">Bienestar</span></span>
                        <span class="text-[11px] font-medium text-slate-500 hidden sm:inline">Portal del Paciente</span>
                    </div>
                </a>

                <nav class="hidden lg:flex items-center gap-1.5" aria-label="Navegación principal">
                    <a href="#inicio" onclick="scrollToTop(event)"
                        class="px-4 py-2.5 rounded-xl text-sm font-semibold text-[#0284c7] bg-sky-50 border border-sky-100">Inicio</a>
                    <button type="button" onclick="openScheduleModal()"
                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:text-[#0284c7] hover:bg-slate-50">Agendar
                        nueva cita</button>
                    <button type="button" onclick="openModifyModal()"
                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:text-[#0284c7] hover:bg-slate-50">Modificar
                        cita</button>
                    <button type="button" onclick="openCancelModal()"
                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:text-rose-600 hover:bg-rose-50/70">Cancelar
                        cita</button>
                </nav>

                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center gap-2.5 pl-2 pr-3 py-1.5 rounded-full bg-slate-50 border border-slate-200/90">
                        <div
                            class="w-8 h-8 rounded-full bg-gradient-to-tr from-[#0284c7] to-[#0d9488] text-white font-semibold text-xs flex items-center justify-center">
                            <?= htmlspecialchars($iniciales) ?>
                        </div>
                        <div class="hidden sm:flex flex-col text-left">
                            <span
                                class="text-xs font-semibold text-slate-800 leading-none"><?= htmlspecialchars($nombrePaciente) ?></span>
                            <span class="text-[10px] text-emerald-700 font-medium mt-0.5">Seguro:
                                <?= htmlspecialchars($numSeguro) ?></span>
                        </div>
                    </div>
                    <a href="close.php"
                        class="hidden sm:inline-flex px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 border border-slate-200 hover:bg-slate-50">Cerrar
                        sesión</a>
                    <button type="button" onclick="toggleMobileMenu()" aria-label="Abrir menú"
                        class="lg:hidden p-2.5 rounded-xl text-slate-600 hover:bg-slate-100">☰</button>
                </div>
            </div>
        </div>

        <div id="mobile-menu"
            class="hidden lg:hidden bg-white border-t border-slate-100 px-4 pt-3 pb-5 space-y-1.5 shadow-lg">
            <a href="#inicio" onclick="toggleMobileMenu(); scrollToTop(event);"
                class="block px-4 py-3 rounded-xl text-sm font-semibold text-[#0284c7] bg-sky-50">Inicio</a>
            <button type="button" onclick="toggleMobileMenu(); openScheduleModal();"
                class="w-full px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 text-left">Agendar
                nueva cita</button>
            <button type="button" onclick="toggleMobileMenu(); openModifyModal();"
                class="w-full px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 text-left">Modificar
                cita</button>
            <button type="button" onclick="toggleMobileMenu(); openCancelModal();"
                class="w-full px-4 py-3 rounded-xl text-sm font-medium text-rose-600 hover:bg-rose-50 text-left">Cancelar
                cita</button>
            <a href="close.php"
                class="block px-4 py-3 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-50">Cerrar
                sesión</a>
        </div>
    </header>

    <!-- ================= CONTENIDO ================= -->
    <main id="inicio" class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-10 space-y-8">

        <?php if ($errorCarga): ?>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                <?= htmlspecialchars($errorCarga) ?>
            </div>
        <?php endif; ?>

        <section class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-6 sm:p-8 lg:p-10 bg-gradient-to-br from-white via-sky-50/40 to-teal-50/40">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <div
                            class="inline-flex px-3 py-1 rounded-full bg-sky-50 border border-sky-100 text-[#0284c7] text-xs font-medium">
                            <span id="current-date-display">Cargando fecha...</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900 tracking-tight">
                            Bienvenido de nuevo, <span
                                class="text-[#0284c7]"><?= htmlspecialchars($nombrePaciente) ?></span>
                        </h1>
                        <p class="text-slate-600 text-sm sm:text-base">Gestiona tus consultas médicas y mantén el
                            control de tus próximas citas.</p>
                    </div>
                    <button type="button" onclick="openScheduleModal()"
                        class="inline-flex items-center justify-center px-6 py-3.5 rounded-2xl bg-[#0284c7] hover:bg-[#0369a1] text-white font-semibold text-sm shadow-md">
                        + Agendar nueva cita
                    </button>
                </div>
                <div id="nearest-appointment-spotlight" class="mt-7 pt-6 border-t border-slate-200/70"></div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <section class="lg:col-span-8 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Próximas Citas</h2>
                        <span id="appointments-count-badge"
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-[#0284c7]"><?= count($citasPaciente) ?></span>
                    </div>
                    <div class="inline-flex p-1 rounded-xl bg-white border border-slate-200/90 shadow-sm self-start">
                        <button type="button" onclick="setStatusFilter('Todas')" id="filter-Todas"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#0284c7] text-white">Todas</button>
                        <button type="button" onclick="setStatusFilter('Pendiente')" id="filter-Pendiente"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600">Pendientes</button>
                        <button type="button" onclick="setStatusFilter('Confirmada')" id="filter-Confirmada"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600">Confirmadas</button>
                    </div>
                </div>
                <div id="appointments-list" class="space-y-4"></div>
            </section>

            <aside class="lg:col-span-4 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h3 class="text-base font-bold text-slate-900">Indicaciones para tu visita</h3>
                    <ul class="space-y-3 text-sm text-slate-600 list-decimal pl-5">
                        <li><strong>Llega 15 minutos antes</strong> de tu hora programada.</li>
                        <li>Presenta una <strong>identificación oficial vigente</strong> y tu número de seguro.</li>
                        <li>Trae <strong>estudios clínicos recientes</strong> o recetas previas.</li>
                    </ul>
                </div>
                <div
                    class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-2xl text-white shadow-md p-6 space-y-3">
                    <span
                        class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30">Atención
                        24/7</span>
                    <h3 class="text-lg font-bold">Contacto y Urgencias</h3>
                    <div class="p-3 rounded-xl bg-white/5 border border-white/10 flex items-center justify-between">
                        <div>
                            <p class="text-[11px] text-slate-400">Línea de Urgencias</p>
                            <p class="font-bold text-base tracking-wide">800 555 0199</p>
                        </div>
                        <a href="tel:8005550199"
                            class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-xs font-semibold">Llamar</a>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <!-- ================= MODALES ================= -->
    <!-- AGENDAR: POST ../php/ABCC/altas.php -->
    <div id="modal-schedule"
        class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div
            class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden animate-modal max-h-[92vh] flex flex-col">
            <div
                class="px-6 py-5 bg-gradient-to-r from-[#0284c7] to-[#0d9488] text-white flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold">Agendar Nueva Cita</h3>
                    <p class="text-xs text-sky-100">Selecciona especialista, fecha y motivo</p>
                </div>
                <button type="button" onclick="closeModal('modal-schedule')"
                    class="p-2 rounded-xl hover:bg-white/15">✕</button>
            </div>
            <form id="form-schedule" onsubmit="handleScheduleSubmit(event)" class="p-6 space-y-4 overflow-y-auto">
                <div>
                    <label for="sched-doctor"
                        class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Médico</label>
                    <select id="sched-doctor" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm"></select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="sched-date"
                            class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Fecha</label>
                        <input type="date" id="sched-date" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm">
                    </div>
                    <div>
                        <label for="sched-time"
                            class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Horario</label>
                        <select id="sched-time" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm"></select>
                    </div>
                </div>
                <div>
                    <label for="sched-reason" class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Motivo
                        de consulta</label>
                    <input type="text" id="sched-reason" required placeholder="Ej. Revisión general"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm">
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeModal('modal-schedule')"
                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-100">Cancelar</button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-[#0284c7] hover:bg-[#0369a1] text-white">Confirmar
                        Cita</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODIFICAR: PUT ../php/ABCC/cambios.php -->
    <div id="modal-modify"
        class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div
            class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden animate-modal max-h-[92vh] flex flex-col">
            <div class="px-6 py-5 bg-sky-700 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold">Modificar Cita</h3>
                    <p class="text-xs text-sky-100">Actualiza especialista, fecha, hora o motivo</p>
                </div>
                <button type="button" onclick="closeModal('modal-modify')"
                    class="p-2 rounded-xl hover:bg-white/15">✕</button>
            </div>
            <form onsubmit="handleModifySubmit(event)" class="p-6 space-y-4 overflow-y-auto">
                <div>
                    <label for="mod-select-cita"
                        class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Selecciona la cita</label>
                    <select id="mod-select-cita" onchange="fillModifyFields()" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm"></select>
                </div>
                <div>
                    <label for="mod-doctor"
                        class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Médico</label>
                    <select id="mod-doctor" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm"></select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="mod-date" class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Nueva
                            fecha</label>
                        <input type="date" id="mod-date" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm">
                    </div>
                    <div>
                        <label for="mod-time" class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Nuevo
                            horario</label>
                        <select id="mod-time" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm"></select>
                    </div>
                </div>
                <div>
                    <label for="mod-reason"
                        class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Motivo</label>
                    <input type="text" id="mod-reason" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm">
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeModal('modal-modify')"
                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-100">Cerrar</button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-[#0284c7] hover:bg-[#0369a1] text-white">Guardar
                        Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CANCELAR: PATCH ../php/ABCC/cambiosParciales.php -->
    <div id="modal-cancel"
        class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl overflow-hidden animate-modal">
            <div class="px-6 py-5 bg-rose-600 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold">Cancelar Cita</h3>
                    <p class="text-xs text-rose-100">La cita cambiará a estado Cancelada</p>
                </div>
                <button type="button" onclick="closeModal('modal-cancel')"
                    class="p-2 rounded-xl hover:bg-white/15">✕</button>
            </div>
            <form onsubmit="handleCancelSubmit(event)" class="p-6 space-y-4">
                <div>
                    <label for="cancel-select-cita"
                        class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Cita a cancelar</label>
                    <select id="cancel-select-cita" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm"></select>
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeModal('modal-cancel')"
                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-100">Conservar</button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-rose-600 hover:bg-rose-700 text-white">Confirmar
                        Cancelación</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-5 right-5 z-50 space-y-2 max-w-sm w-full pointer-events-none"></div>

    <!-- ================= JAVASCRIPT ================= -->
    <script src="JS/validacionPaciente.js"></script>
    <script>
        const PACIENTE_ID = <?= (int) $pacienteId ?>;
        const NOMBRE_PACIENTE = <?= json_encode($nombrePaciente, JSON_UNESCAPED_UNICODE) ?>;
        let appointments = <?= json_encode($citasPaciente, $jsonFlags) ?>;
        const doctorsList = <?= json_encode($listaDoctores, $jsonFlags) ?>;

        // Rutas relativas a /pages/
        const API_URLS = {
            altas: 'php/ABCC/altas.php',
            cambios: 'php/ABCC/cambios.php',
            cambiosParciales: 'php/ABCC/cambiosParciales.php',
            bajas: 'php/ABCC/bajas.php'
        };

        const HORARIOS = [
            ['08:30:00', '08:30 AM'], ['09:30:00', '09:30 AM'], ['10:30:00', '10:30 AM'], ['11:30:00', '11:30 AM'],
            ['13:00:00', '01:00 PM'], ['16:00:00', '04:00 PM'], ['17:30:00', '05:30 PM']
        ];

        let currentStatusFilter = 'Todas';

        // ---------- Utilidades ----------
        const escapeHTML = s => String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));

        function splitFechaHora(fh) {
            const [datePart = '', timePart = '00:00:00'] = String(fh).split(' ');
            return { datePart, timePart };
        }
        function formatDateSpanish(fh) {
            const { datePart } = splitFechaHora(fh);
            if (!datePart) return '';
            const [y, m, d] = datePart.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        }
        function formatTime12h(fh) {
            const [h = 0, m = 0] = splitFechaHora(fh).timePart.split(':').map(Number);
            return `${String(h % 12 || 12).padStart(2, '0')}:${String(m).padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`;
        }
        function getDaysRemainingLabel(fh) {
            const { datePart } = splitFechaHora(fh);
            if (!datePart) return 'Programada';
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const [y, m, d] = datePart.split('-').map(Number);
            const diff = Math.round((new Date(y, m - 1, d) - today) / 86400000);
            if (diff < 0) return 'Pasada';
            if (diff === 0) return 'Hoy';
            if (diff === 1) return 'Mañana';
            return `En ${diff} días`;
        }
        function localISODate(offsetDays = 0) {
            const t = new Date(); t.setDate(t.getDate() + offsetDays);
            return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
        }

        // ---------- Llamada genérica a los endpoints ABCC ----------
        async function llamarAPI(url, method, payload) {
            const res = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            // Token expirado o ausente: volver al login
            if (res.status === 401) {
                window.location.href = 'login';
                return { ok: false, data: { error: 'Sesión expirada.' } };
            }

            const texto = await res.text();
            let data;
            try { data = JSON.parse(texto); }
            catch (_) {
                console.error('Respuesta no JSON de', url, texto);
                data = { error: `Respuesta no válida del servidor (HTTP ${res.status}).` };
            }
            return { ok: res.ok && !data.error && data.ok !== false, data };
        }

        // ---------- Render ----------
        function renderAppointments() {
            appointments.sort((a, b) => a.fecha_hora.localeCompare(b.fecha_hora));
            const container = document.getElementById('appointments-list');
            const filtered = currentStatusFilter === 'Todas'
                ? appointments
                : appointments.filter(c => c.estado.toLowerCase() === currentStatusFilter.toLowerCase());

            document.getElementById('appointments-count-badge').textContent = filtered.length;

            if (filtered.length === 0) {
                container.innerHTML = `
                <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center space-y-3">
                    <p class="text-base font-bold text-slate-800">No tienes citas en esta categoría</p>
                    <button type="button" onclick="openScheduleModal()" class="px-5 py-2.5 rounded-xl bg-[#0284c7] text-white text-sm font-semibold hover:bg-[#0369a1]">+ Agendar nueva cita</button>
                </div>`;
                renderNearestSpotlight();
                return;
            }

            container.innerHTML = filtered.map(c => {
                const conf = c.estado.toLowerCase() === 'confirmada';
                const style = conf ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-sky-50 text-[#0284c7] border-sky-200';
                return `
            <article class="bg-white rounded-2xl border border-slate-200/90 shadow-sm hover:shadow-md hover:border-sky-200 transition-all p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                    <div class="space-y-2.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border ${style}">${escapeHTML(c.estado)}</span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200/70">${getDaysRemainingLabel(c.fecha_hora)}</span>
                            <span class="text-xs text-slate-400 font-medium">Cita #${c.id}</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">${escapeHTML(c.especialidad)}</h3>
                            <p class="text-sm font-medium text-slate-700">Dr(a). ${escapeHTML(c.nombre_doctor)}</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-600">
                            <div><span class="font-semibold text-slate-800">Fecha:</span> <span class="capitalize">${formatDateSpanish(c.fecha_hora)}</span></div>
                            <div><span class="font-semibold text-slate-800">Hora:</span> ${formatTime12h(c.fecha_hora)}</div>
                            <div class="sm:col-span-2"><span class="font-semibold text-slate-800">Motivo:</span> ${escapeHTML(c.motivo)}</div>
                        </div>
                    </div>
                    <div class="flex sm:flex-col gap-2 shrink-0">
                        <button type="button" onclick="openModifyModal(${c.id})" class="flex-1 px-3.5 py-2 rounded-xl text-xs font-semibold text-[#0284c7] bg-sky-50 hover:bg-sky-100">Reprogramar</button>
                        <button type="button" onclick="openCancelModal(${c.id})" class="flex-1 px-3.5 py-2 rounded-xl text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100">Cancelar</button>
                    </div>
                </div>
            </article>`;
            }).join('');

            renderNearestSpotlight();
        }

        function renderNearestSpotlight() {
            const el = document.getElementById('nearest-appointment-spotlight');
            if (appointments.length === 0) {
                el.innerHTML = `<p class="text-sm text-slate-500">No tienes consultas próximas registradas.</p>`;
                return;
            }
            const n = appointments[0];
            el.innerHTML = `
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/80 rounded-2xl p-4 border border-sky-100">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl bg-sky-100 text-[#0284c7] flex items-center justify-center font-bold text-xs text-center px-1 shrink-0">${getDaysRemainingLabel(n.fecha_hora)}</div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#0d9488]">Tu consulta más cercana</p>
                        <p class="text-sm font-bold text-slate-900">${escapeHTML(n.especialidad)} — Dr(a). ${escapeHTML(n.nombre_doctor)}</p>
                        <p class="text-xs text-slate-600 capitalize">${formatDateSpanish(n.fecha_hora)} a las ${formatTime12h(n.fecha_hora)}</p>
                    </div>
                </div>
                <button type="button" onclick="openModifyModal(${n.id})" class="text-xs font-semibold text-[#0284c7] hover:underline self-start sm:self-center">Cambiar horario →</button>
            </div>`;
        }

        function populateSelects() {
            const docs = doctorsList.length
                ? doctorsList.map(d => `<option value="${d.doctor_id}">Dr(a). ${escapeHTML(d.nombre_doctor)} — ${escapeHTML(d.especialidad)}</option>`).join('')
                : '<option value="">No hay médicos disponibles</option>';
            const horas = HORARIOS.map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
            ['sched-doctor', 'mod-doctor'].forEach(id => document.getElementById(id).innerHTML = docs);
            ['sched-time', 'mod-time'].forEach(id => document.getElementById(id).innerHTML = horas);
        }

        // ---------- AGENDAR (POST altas.php) ----------
        function openScheduleModal() {
            const f = document.getElementById('sched-date');
            f.min = f.value = localISODate(1);
            document.getElementById('modal-schedule').classList.replace('hidden', 'flex');
        }

        async function handleScheduleSubmit(e) {
            e.preventDefault();
            const doctorId = Number(document.getElementById('sched-doctor').value);
            const reason = document.getElementById('sched-reason').value.trim();
            const fechaHora = `${document.getElementById('sched-date').value} ${document.getElementById('sched-time').value}`;
            if (!doctorId) return showToast('Selecciona un médico.', 'error');

            try {
                const { ok, data } = await llamarAPI(API_URLS.altas, 'POST', {
                    tipo: 'cita', fecha_hora: fechaHora, motivo: reason, estado: 'Pendiente',
                    paciente_id: PACIENTE_ID, doctor_id: doctorId
                });
                if (ok) {
                    const d = doctorsList.find(x => x.doctor_id === doctorId);
                    appointments.push({
                        id: Number(data.idCita), fecha_hora: fechaHora, motivo: reason, estado: 'Pendiente',
                        paciente_id: PACIENTE_ID, doctor_id: doctorId,
                        nombre_doctor: d ? d.nombre_doctor : 'Especialista',
                        especialidad: d ? d.especialidad : 'Consulta Médica'
                    });
                    renderAppointments();
                    closeModal('modal-schedule');
                    e.target.reset();
                    showToast(data.mensaje || 'Cita registrada correctamente.', 'success');
                } else {
                    showToast(data.error || 'No se pudo registrar la cita.', 'error');
                }
            } catch (err) {
                showToast('Error de conexión con altas.php', 'error');
            }
        }

        // ---------- MODIFICAR (PUT cambios.php) ----------
        function openModifyModal(preselectedId = null) {
            if (appointments.length === 0) return showToast('No tienes citas activas para modificar.', 'info');
            document.getElementById('mod-select-cita').innerHTML = appointments.map(c =>
                `<option value="${c.id}" ${c.id === preselectedId ? 'selected' : ''}>#${c.id} — ${escapeHTML(c.especialidad)} (${splitFechaHora(c.fecha_hora).datePart} ${formatTime12h(c.fecha_hora)})</option>`
            ).join('');
            fillModifyFields();
            document.getElementById('modal-modify').classList.replace('hidden', 'flex');
        }

        function fillModifyFields() {
            const cita = appointments.find(c => c.id === Number(document.getElementById('mod-select-cita').value));
            if (!cita) return;
            const { datePart, timePart } = splitFechaHora(cita.fecha_hora);
            document.getElementById('mod-doctor').value = cita.doctor_id;
            document.getElementById('mod-date').value = datePart;
            document.getElementById('mod-time').value = timePart;
            document.getElementById('mod-reason').value = cita.motivo;
        }

        async function handleModifySubmit(e) {
            e.preventDefault();
            const id = Number(document.getElementById('mod-select-cita').value);
            const orig = appointments.find(c => c.id === id);
            if (!orig) return;

            const doctorId = Number(document.getElementById('mod-doctor').value);
            const fechaHora = `${document.getElementById('mod-date').value} ${document.getElementById('mod-time').value}`;
            const motivo = document.getElementById('mod-reason').value.trim();

            try {
                const { ok, data } = await llamarAPI(API_URLS.cambios, 'PUT', {
                    id, fechaHora, motivo, estado: orig.estado, pacienteID: PACIENTE_ID, doctorID: doctorId
                });
                if (ok) {
                    const d = doctorsList.find(x => x.doctor_id === doctorId);
                    Object.assign(orig, {
                        fecha_hora: fechaHora, motivo, doctor_id: doctorId,
                        nombre_doctor: d ? d.nombre_doctor : orig.nombre_doctor,
                        especialidad: d ? d.especialidad : orig.especialidad
                    });
                    renderAppointments();
                    closeModal('modal-modify');
                    showToast(data.mensaje || 'Cita actualizada correctamente.', 'success');
                } else {
                    showToast(data.error || 'No se pudo actualizar la cita.', 'error');
                }
            } catch (err) {
                showToast('Error de conexión con cambios.php', 'error');
            }
        }

        // ---------- CANCELAR (PATCH cambiosParciales.php) ----------
        function openCancelModal(preselectedId = null) {
            if (appointments.length === 0) return showToast('No tienes citas activas para cancelar.', 'info');
            document.getElementById('cancel-select-cita').innerHTML = appointments.map(c =>
                `<option value="${c.id}" ${c.id === preselectedId ? 'selected' : ''}>#${c.id} — ${escapeHTML(c.especialidad)} con Dr(a). ${escapeHTML(c.nombre_doctor)} (${splitFechaHora(c.fecha_hora).datePart})</option>`
            ).join('');
            document.getElementById('modal-cancel').classList.replace('hidden', 'flex');
        }

        async function handleCancelSubmit(e) {
            e.preventDefault();
            const id = Number(document.getElementById('cancel-select-cita').value);
            try {
                const orig = appointments.find(c => c.id === id);
                const { ok, data } = await llamarAPI(API_URLS.cambios, 'PUT', {
                    id,
                    fechaHora: orig.fecha_hora,
                    motivo: orig.motivo,
                    estado: 'Cancelada',
                    pacienteID: PACIENTE_ID,
                    doctorID: orig.doctor_id
                });
                if (ok) {
                    appointments = appointments.filter(c => c.id !== id);
                    renderAppointments();
                    closeModal('modal-cancel');
                    showToast(data.mensaje || 'Cita cancelada correctamente.', 'success');
                } else {
                    showToast(data.error || 'No se pudo cancelar la cita.', 'error');
                }
            } catch (err) {
                showToast('Error de conexión con el servidor.', 'error');
            }
        }

        // ---------- Interfaz ----------
        function closeModal(id) {
            Validacion.limpiarTodos(document.getElementById(id));
            document.getElementById(id).classList.replace('flex', 'hidden');
        }
        function setStatusFilter(status) {
            currentStatusFilter = status;
            ['Todas', 'Pendiente', 'Confirmada'].forEach(tab => {
                document.getElementById(`filter-${tab}`).className = tab === status
                    ? 'px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#0284c7] text-white'
                    : 'px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:text-slate-900';
            });
            renderAppointments();
        }

        function toggleMobileMenu() { document.getElementById('mobile-menu').classList.toggle('hidden'); }
        function scrollToTop(e) { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); }

        function showToast(message, type = 'success') {
            const colors = { success: 'bg-slate-900 border-emerald-500', error: 'bg-rose-950 border-rose-500', info: 'bg-sky-950 border-sky-400' };
            const t = document.createElement('div');
            t.className = `pointer-events-auto p-4 rounded-2xl border-l-4 shadow-xl text-xs font-medium text-white animate-toast ${colors[type] || colors.success}`;
            t.textContent = message;
            document.getElementById('toast-container').appendChild(t);
            setTimeout(() => t.remove(), 3500);
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('current-date-display').textContent =
                new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            populateSelects();
            renderAppointments();
        });
    </script>
</body>

</html>