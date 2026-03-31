/**
 * KND Gallery - Lightbox simple y moderno.
 * Al hacer click en una imagen de la galería se abre en grande; overlay o ESC cierran.
 */
(function () {
    'use strict';

    var lightbox = document.getElementById('knd-gallery-lightbox');
    var backdrop = lightbox && lightbox.querySelector('.knd-gallery-lightbox-backdrop');
    var closeBtn = lightbox && lightbox.querySelector('.knd-gallery-lightbox-close');
    var lightboxImg = lightbox && lightbox.querySelector('.knd-gallery-lightbox-img');

    function openLightbox(src) {
        if (!lightbox || !lightboxImg) return;
        lightboxImg.src = src;
        lightboxImg.alt = '';
        lightbox.setAttribute('aria-hidden', 'false');
        lightbox.removeAttribute('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (!lightbox) return;
        lightbox.setAttribute('aria-hidden', 'true');
        lightbox.setAttribute('hidden', '');
        document.body.style.overflow = '';
        if (lightboxImg) lightboxImg.removeAttribute('src');
    }

    function onKeyDown(e) {
        if (e.key === 'Escape' && lightbox && lightbox.getAttribute('aria-hidden') === 'false') {
            closeLightbox();
        }
    }

    if (backdrop) backdrop.addEventListener('click', closeLightbox);
    if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
    document.addEventListener('keydown', onKeyDown);

    // Delegación: cualquier click en una imagen con data-lightbox-src abre el lightbox
    document.addEventListener('click', function (e) {
        var img = e.target.closest('img[data-lightbox-src]');
        if (!img) return;
        e.preventDefault();
        var src = img.getAttribute('data-lightbox-src');
        if (src) openLightbox(src);
    });
})();
