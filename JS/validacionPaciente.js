// JS/validacionPaciente.js

function esEnteroPositivo(valor) {
    if (typeof valor === 'boolean' || valor === null || valor === undefined) return false;
    const texto = String(valor).trim();
    if (!/^\d+$/.test(texto)) return false;
    const num = Number(texto);
    return Number.isSafeInteger(num) && num > 0;
}

function esFechaValidaYActual(fechaStr) {
    if (typeof fechaStr !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(fechaStr)) {
        return false;
    }
    const [anio, mes, dia] = fechaStr.split('-').map(Number);
    const fechaObj = new Date(anio, mes - 1, dia);

    const esReal =
        fechaObj.getFullYear() === anio &&
        fechaObj.getMonth() === mes - 1 &&
        fechaObj.getDate() === dia;

    if (!esReal) return false;

    return fechaStr >= localISODate(0);
}

function esHorarioPermitido(horaStr, fechaStr) {
    if (typeof horaStr !== 'string') return false;
    const horasValidas = new Set(HORARIOS.map(([valor]) => valor));
    if (!horasValidas.has(horaStr)) return false;

    // Si es para hoy, validar que la hora aún no haya pasado
    if (fechaStr === localISODate(0)) {
        const ahora = new Date();
        const [h, m] = horaStr.split(':').map(Number);
        return (h * 60 + m) > (ahora.getHours() * 60 + ahora.getMinutes());
    }
    return true;
}

function esMotivoTextoValido(motivo) {
    if (typeof motivo !== 'string') return false;
    const limpio = motivo.trim();
    if (limpio.length < 5 || limpio.length > 255) return false;
    return /^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s.,;:()\-¿?¡!]+$/.test(limpio);
}

function marcarErrorCampo(idCampo, tieneError) {
    const el = document.getElementById(idCampo);
    if (!el) return;

    el.classList.toggle('border-rose-500', tieneError);
    el.classList.toggle('ring-2', tieneError);
    el.classList.toggle('ring-rose-200', tieneError);

    if (tieneError && !el.dataset.listenerError) {
        el.dataset.listenerError = '1';
        const limpiar = () => {
            el.classList.remove('border-rose-500', 'ring-2', 'ring-rose-200');
        };
        el.addEventListener('input', limpiar, { once: true });
        el.addEventListener('change', limpiar, { once: true });
    }
}

function validarAgendarCita({ doctorId, fecha, hora, motivo }) {
    const doctorOk = esEnteroPositivo(doctorId) && doctorsList.some(d => d.doctor_id === Number(doctorId));
    marcarErrorCampo('sched-doctor', !doctorOk);
    if (!doctorOk) return { valido: false, mensaje: 'Selecciona un médico válido de la lista.' };

    const fechaOk = esFechaValidaYActual(fecha);
    marcarErrorCampo('sched-date', !fechaOk);
    if (!fechaOk) return { valido: false, mensaje: 'Ingresa una fecha válida igual o posterior a hoy.' };

    const horaOk = esHorarioPermitido(hora, fecha);
    marcarErrorCampo('sched-time', !horaOk);
    if (!horaOk) return { valido: false, mensaje: 'Selecciona un horario disponible válido.' };

    const motivoOk = esMotivoTextoValido(motivo);
    marcarErrorCampo('sched-reason', !motivoOk);
    if (!motivoOk) return { valido: false, mensaje: 'El motivo debe tener entre 5 y 255 caracteres sin símbolos especiales.' };

    return { valido: true, mensaje: '' };
}

function validarModificarCita({ citaId, doctorId, fecha, hora, motivo }) {
    const citaOk = esEnteroPositivo(citaId) && appointments.some(c => c.id === Number(citaId));
    marcarErrorCampo('mod-select-cita', !citaOk);
    if (!citaOk) return { valido: false, mensaje: 'La cita seleccionada no es válida.' };

    const doctorOk = esEnteroPositivo(doctorId) && doctorsList.some(d => d.doctor_id === Number(doctorId));
    marcarErrorCampo('mod-doctor', !doctorOk);
    if (!doctorOk) return { valido: false, mensaje: 'Selecciona un médico válido de la lista.' };

    const fechaOk = esFechaValidaYActual(fecha);
    marcarErrorCampo('mod-date', !fechaOk);
    if (!fechaOk) return { valido: false, mensaje: 'La nueva fecha debe ser válida y no anterior a hoy.' };

    const horaOk = esHorarioPermitido(hora, fecha);
    marcarErrorCampo('mod-time', !horaOk);
    if (!horaOk) return { valido: false, mensaje: 'Selecciona un nuevo horario válido.' };

    const motivoOk = esMotivoTextoValido(motivo);
    marcarErrorCampo('mod-reason', !motivoOk);
    if (!motivoOk) return { valido: false, mensaje: 'El motivo debe tener entre 5 y 255 caracteres sin símbolos especiales.' };

    return { valido: true, mensaje: '' };
}

function validarCancelarCita({ citaId }) {
    const citaOk = esEnteroPositivo(citaId) && appointments.some(c => c.id === Number(citaId));
    marcarErrorCampo('cancel-select-cita', !citaOk);
    if (!citaOk) return { valido: false, mensaje: 'Selecciona una cita activa válida para cancelar.' };

    return { valido: true, mensaje: '' };
}