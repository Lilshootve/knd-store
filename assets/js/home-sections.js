/**
 * KND Store - Home fullscreen sections
 * Scroll-snap activation (intro only), reveal animations via IntersectionObserver
 * Sin librerías pesadas; degradación correcta en móvil.
 */
(function () {
    'use strict';

    var isHome = document.body.querySelector('#home-fullpage') || document.body.querySelector('.home-section-full');
    if (!isHome) return;
    var homeRest = document.getElementById('home-rest');
    var sections = Array.prototype.slice.call(document.querySelectorAll('.home-section-full'));
    if (!sections.length) return;

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                // Opcional: dejar de observar para no re-ejecutar animaciones
                // observer.unobserve(entry.target);
            }
        });
    }, {
        root: null,
        rootMargin: '0px 0px -15% 0px',
        threshold: 0.1
    });

    sections.forEach(function (section) {
        observer.observe(section);
        // Si ya está en viewport al cargar, marcar visible
        var rect = section.getBoundingClientRect();
        if (rect.top < window.innerHeight * 0.85) {
            section.classList.add('is-visible');
        }
    });

    // Scroll por secciones en desktop (similar al home fullpage nuevo)
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

        var scrollTop = window.scrollY || window.pageYOffset;
        var firstTop = sections[0].offsetTop;
        var last = sections[sections.length - 1];
        var lastBottom = last.offsetTop + last.offsetHeight;

        // Solo interceptar mientras el viewport esté dentro del bloque de secciones
        if (scrollTop < firstTop - 80 || scrollTop > lastBottom - window.innerHeight * 0.6) {
            return;
        }

        var direction = deltaY > 0 ? 1 : -1;
        var currentIndex = getCurrentSectionIndex();
        var targetIndex = currentIndex + direction;

        // Permitir scroll natural antes de la primera y después de la última sección
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
