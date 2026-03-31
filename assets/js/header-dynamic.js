/**
 * KND Store - Header dinámico (scroll-aware)
 * Detecta dirección del scroll: oculta header al bajar, muestra al subir.
 * Añade .header-scrolled cuando scroll > umbral para fondo sólido.
 * Sin librerías externas. Compatible con Bootstrap y móvil.
 */
(function () {
  'use strict';

  var header = document.getElementById('site-header') || document.querySelector('.site-header');
  if (!header) return;

  var scrollThreshold = 80;   // px a partir del cual se considera "scrolled" (fondo sólido)
  var lastScrollY = window.scrollY || window.pageYOffset;
  var minDelta = 8;            // px mínimos de scroll para considerar cambio de dirección (evita jitter)
  var ticking = false;

  function updateHeader() {
    var currentScrollY = window.scrollY || window.pageYOffset;

    // En el top: quitar estados scrolled y hidden
    if (currentScrollY <= scrollThreshold) {
      header.classList.remove('header-scrolled', 'header-hidden');
      lastScrollY = currentScrollY;
      ticking = false;
      return;
    }

    // Por debajo del umbral: marcar como scrolled
    header.classList.add('header-scrolled');

    // Dirección: ocultar al bajar, mostrar al subir
    var delta = currentScrollY - lastScrollY;
    if (delta > minDelta) {
      header.classList.add('header-hidden');
    } else if (delta < -minDelta) {
      header.classList.remove('header-hidden');
    }

    lastScrollY = currentScrollY;
    ticking = false;
  }

  function onScroll() {
    if (!ticking) {
      window.requestAnimationFrame(updateHeader);
      ticking = true;
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });

  // Estado inicial por si la página se carga ya scrolleada
  updateHeader();
})();
