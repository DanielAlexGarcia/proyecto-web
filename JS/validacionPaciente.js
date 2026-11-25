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

function esHorarioPermitido(horaStr, fechaStr, HORARIOS = []) {
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

function marcarErrorCampo(idCampo, tieneError, mensaje) {
    const el = document.getElementById(idCampo);
    if (!el) return;
    if (tieneError) Validacion.mostrarError(el, mensaje);
    else Validacion.limpiarError(el);
}

function validarAgendarCita({ doctorId, fecha, hora, motivo }, { doctorsList = [], HORARIOS = [] } = {}) {
    const doctorOk = esEnteroPositivo(doctorId) && doctorsList.some(d => d.doctor_id === Number(doctorId));
    marcarErrorCampo('sched-doctor', !doctorOk, 'Selecciona un médico válido de la lista.');
    if (!doctorOk) return { valido: false, mensaje: 'Selecciona un médico válido de la lista.' };

    const fechaOk = esFechaValidaYActual(fecha);
    marcarErrorCampo('sched-date', !fechaOk, 'Ingresa una fecha válida igual o posterior a hoy.');
    if (!fechaOk) return { valido: false, mensaje: 'Ingresa una fecha válida igual o posterior a hoy.' };

    const horaOk = esHorarioPermitido(hora, fecha, HORARIOS);
    marcarErrorCampo('sched-time', !horaOk, 'Selecciona un horario disponible válido.');
    if (!horaOk) return { valido: false, mensaje: 'Selecciona un horario disponible válido.' };

    const motivoOk = esMotivoTextoValido(motivo);
    marcarErrorCampo('sched-reason', !motivoOk, 'El motivo debe tener entre 5 y 255 caracteres sin símbolos especiales.');
    if (!motivoOk) return { valido: false, mensaje: 'El motivo debe tener entre 5 y 255 caracteres sin símbolos especiales.' };

    return { valido: true, mensaje: '' };
}

function validarModificarCita({ citaId, doctorId, fecha, hora, motivo }, { appointments = [], doctorsList = [], HORARIOS = [] } = {}) {
    const citaOk = esEnteroPositivo(citaId) && appointments.some(c => c.id === Number(citaId));
    marcarErrorCampo('mod-select-cita', !citaOk, 'La cita seleccionada no es válida.');
    if (!citaOk) return { valido: false, mensaje: 'La cita seleccionada no es válida.' };

    const doctorOk = esEnteroPositivo(doctorId) && doctorsList.some(d => d.doctor_id === Number(doctorId));
    marcarErrorCampo('mod-doctor', !doctorOk, 'Selecciona un médico válido de la lista.');
    if (!doctorOk) return { valido: false, mensaje: 'Selecciona un médico válido de la lista.' };

    const fechaOk = esFechaValidaYActual(fecha);
    marcarErrorCampo('mod-date', !fechaOk, 'La nueva fecha debe ser válida y no anterior a hoy.');
    if (!fechaOk) return { valido: false, mensaje: 'La nueva fecha debe ser válida y no anterior a hoy.' };

    const horaOk = esHorarioPermitido(hora, fecha, HORARIOS);
    marcarErrorCampo('mod-time', !horaOk, 'Selecciona un nuevo horario válido.');
    if (!horaOk) return { valido: false, mensaje: 'Selecciona un nuevo horario válido.' };

    const motivoOk = esMotivoTextoValido(motivo);
    marcarErrorCampo('mod-reason', !motivoOk, 'El motivo debe tener entre 5 y 255 caracteres sin símbolos especiales.');
    if (!motivoOk) return { valido: false, mensaje: 'El motivo debe tener entre 5 y 255 caracteres sin símbolos especiales.' };

    return { valido: true, mensaje: '' };
}

function validarCancelarCita({ citaId }, { appointments = [] } = {}) {
    const citaOk = esEnteroPositivo(citaId) && appointments.some(c => c.id === Number(citaId));
    marcarErrorCampo('cancel-select-cita', !citaOk, 'Selecciona una cita activa válida para cancelar.');
    if (!citaOk) return { valido: false, mensaje: 'Selecciona una cita activa válida para cancelar.' };

    return { valido: true, mensaje: '' };
}