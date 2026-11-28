// JS/tema.js  — cargar en <head> (síncrono) para evitar el parpadeo al cambiar de página.
(function (g) {
  'use strict';
  var K = 'tema';

  function leer() {
    try { return localStorage.getItem(K) === 'oscuro' ? 'oscuro' : 'claro'; }
    catch (e) { return 'claro'; }
  }
  function aplicar(t) {
    document.documentElement.setAttribute('data-tema', t);
    document.documentElement.style.colorScheme = t === 'oscuro' ? 'dark' : 'light';
  }
  function set(t) {
    t = t === 'oscuro' ? 'oscuro' : 'claro';
    try { localStorage.setItem(K, t); } catch (e) { /* modo privado: solo aplica a esta página */ }
    aplicar(t);
    return t;
  }

  aplicar(leer());
  // Sincroniza otras pestañas abiertas
  window.addEventListener('storage', function (e) { if (e.key === K) aplicar(leer()); });

  g.Tema = {
    get: leer,
    set: set,
    alternar: function () { return set(leer() === 'oscuro' ? 'claro' : 'oscuro'); }
  };
})(window);
