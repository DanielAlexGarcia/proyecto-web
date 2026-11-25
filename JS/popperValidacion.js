// JS/popperValidacion.js
// Requiere Popper.js (@popperjs/core) cargado antes.
(function (global) {
  'use strict';

  const estilo = document.createElement('style');
  estilo.textContent = `
    .pp-error{background:#be123c;color:#fff;font:600 12px/1.3 system-ui,sans-serif;
      padding:7px 11px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.18);
      z-index:9999;max-width:260px;pointer-events:none}
    .pp-error [data-popper-arrow],.pp-error [data-popper-arrow]::before{
      position:absolute;width:10px;height:10px;background:inherit}
    .pp-error [data-popper-arrow]{visibility:hidden}
    .pp-error [data-popper-arrow]::before{content:'';visibility:visible;transform:rotate(45deg)}
    .pp-error[data-popper-placement^='bottom']>[data-popper-arrow]{top:-5px}
    .pp-error[data-popper-placement^='top']>[data-popper-arrow]{bottom:-5px}
    .pp-invalido{border-color:#e11d48!important;box-shadow:0 0 0 3px rgba(225,29,72,.15)!important}
  `;
  document.head.appendChild(estilo);

  const activos = new Map(); // campo -> { tip, popper }

  function limpiarError(campo) {
    const a = activos.get(campo);
    if (!a) return;
    a.popper.destroy();
    a.tip.remove();
    campo.classList.remove('pp-invalido');
    campo.removeAttribute('aria-invalid');
    activos.delete(campo);
  }

  function limpiarTodos(raiz) {
    [...activos.keys()].forEach(c => { if (!raiz || raiz.contains(c)) limpiarError(c); });
  }

  function mostrarError(campo, mensaje) {
    if (!campo) return;
    limpiarError(campo);

    const tip = document.createElement('div');
    tip.className = 'pp-error';
    tip.setAttribute('role', 'alert');
    const texto = document.createElement('span');
    texto.textContent = mensaje;               // textContent: sin riesgo de XSS
    const flecha = document.createElement('div');
    flecha.setAttribute('data-popper-arrow', '');
    tip.append(texto, flecha);
    document.body.appendChild(tip);

    const popper = Popper.createPopper(campo, tip, {
      placement: 'bottom-start',
      strategy: 'fixed',                       // funciona dentro de modales con scroll
      modifiers: [
        { name: 'offset', options: { offset: [0, 8] } },
        { name: 'arrow', options: { element: flecha, padding: 8 } },
        { name: 'preventOverflow', options: { padding: 8 } },
        { name: 'flip' }
      ]
    });

    campo.classList.add('pp-invalido');
    campo.setAttribute('aria-invalid', 'true');
    activos.set(campo, { tip, popper });

    const quitar = () => limpiarError(campo);
    campo.addEventListener('input', quitar, { once: true });
    campo.addEventListener('change', quitar, { once: true });
  }

  function mensajeDe(c) {
    if (c.dataset.mensaje) return c.dataset.mensaje;       // mensaje personalizado
    const v = c.validity;
    if (v.valueMissing) {
      if (c.type === 'checkbox') return 'Debes aceptar para continuar.';
      if (c.tagName === 'SELECT') return 'Selecciona una opción.';
      return 'Este campo es obligatorio.';
    }
    if (v.typeMismatch) return c.type === 'email' ? 'Ingresa un correo electrónico válido.' : 'El formato no es válido.';
    if (v.tooShort) return `Debe tener al menos ${c.minLength} caracteres.`;
    if (v.tooLong) return `No puede exceder ${c.maxLength} caracteres.`;
    if (v.patternMismatch) return c.title || 'El formato no es válido.';
    if (v.rangeUnderflow) return (c.type === 'date' || c.type === 'datetime-local') ? 'La fecha es anterior a la permitida.' : `El valor mínimo es ${c.min}.`;
if (v.rangeOverflow)  return (c.type === 'date' || c.type === 'datetime-local') ? 'La fecha es posterior a la permitida.' : `El valor máximo es ${c.max}.`;
return c.validationMessage;
  }

  /**
   * Activa la validación con Popper en un formulario.
   * opciones.reglas = { idCampo: (valor, campo) => 'mensaje' | null }
   */
  function activar(form, opciones = {}) {
    const reglas = opciones.reglas || {};
    form.noValidate = true; // desactiva las burbujas nativas del navegador

    // Fase de captura + stopImmediatePropagation: si hay error, no se ejecuta
    // ni el onsubmit inline ni el form.onsubmit = ... de cada página.
    form.addEventListener('submit', e => {
      limpiarTodos(form);
      for (const c of form.elements) {
        let msg = null;
        if (!c.checkValidity()) msg = mensajeDe(c);
        else if (c.id && reglas[c.id]) msg = reglas[c.id](c.value, c);
        if (msg) {
          e.preventDefault();
          e.stopImmediatePropagation();
          mostrarError(c, msg);
          c.focus();
          return;
        }
      }
    }, true);
  }

  global.Validacion = { activar, mostrarError, limpiarError, limpiarTodos };
})(window);