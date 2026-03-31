(function () {
  'use strict';

  if (!document.querySelector('.fp-main')) return;

  // Mark html/body (para estilos específicos de este home)
  document.body.classList.add('fp-home');
  if (document.documentElement) {
    document.documentElement.classList.add('fp-home');
  }

  var sections = Array.prototype.slice.call(document.querySelectorAll('.fp-section'));
  var dots = Array.prototype.slice.call(document.querySelectorAll('.fp-dot[data-fp-target]'));

  function scrollToTarget(target) {
    var el = document.querySelector(target);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  dots.forEach(function (dot) {
    dot.addEventListener('click', function (e) {
      e.preventDefault();
      var target = dot.getAttribute('data-fp-target');
      if (!target) return;
      scrollToTarget(target);
    });
  });

  // Highlight active dot based on section in view (desktop only).
  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var id = entry.target.getAttribute('id');
        if (!id) return;
        dots.forEach(function (dot) {
          var t = dot.getAttribute('data-fp-target');
          dot.classList.toggle('is-active', t === '#' + id);
        });
      });
    }, {
      root: null,
      threshold: 0.5
    });

    sections.forEach(function (s) { observer.observe(s); });
  }

  // Fullpage scroll en desktop: rueda del ratón mueve sección por sección.
  var isDesktop = window.innerWidth >= 992;
  var isAnimating = false;
  var lastScrollTime = 0;

  function getCurrentSectionIndex() {
    var scrollTop = window.scrollY || window.pageYOffset;
    var viewportCenter = scrollTop + (window.innerHeight / 2);
    var nearest = 0;
    var minDist = Infinity;
    sections.forEach(function (sec, idx) {
      var rect = sec.getBoundingClientRect();
      var secCenter = rect.top + scrollTop + (rect.height / 2);
      var dist = Math.abs(secCenter - viewportCenter);
      if (dist < minDist) {
        minDist = dist;
        nearest = idx;
      }
    });
    return nearest;
  }

  function onWheel(e) {
    if (!isDesktop) return;
    if (e.defaultPrevented) return;

    var now = Date.now();
    if (isAnimating || (now - lastScrollTime) < 600) return;

    var deltaY = e.deltaY;
    if (Math.abs(deltaY) < 10) return;

    var direction = deltaY > 0 ? 1 : -1;
    var currentIndex = getCurrentSectionIndex();
    var targetIndex = currentIndex + direction;

    // Permitir scroll natural por encima de la primera sección y por debajo de la última (hacia el footer).
    if (targetIndex < 0 || targetIndex >= sections.length) return;

    e.preventDefault();
    isAnimating = true;
    lastScrollTime = now;

    sections[targetIndex].scrollIntoView({ behavior: 'smooth', block: 'start' });

    setTimeout(function () {
      isAnimating = false;
    }, 700);
  }

  window.addEventListener('wheel', onWheel, { passive: false });
  window.addEventListener('resize', function () {
    isDesktop = window.innerWidth >= 992;
  });
})();

