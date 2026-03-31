(function () {
    var dropdown = document.querySelector('[data-knd-portal-dropdown]');
    var toggle = document.querySelector('[data-knd-portal-toggle]');
    var menu = document.querySelector('[data-knd-portal-menu]');

    if (!dropdown || !toggle || !menu) return;

    var body = document.body;
    var isOpen = false;

    function setOpen(nextOpen) {
        isOpen = !!nextOpen;
        dropdown.classList.toggle('is-open', isOpen);
        body.classList.toggle('knd-portal-dropdown-active', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
    }

    toggle.addEventListener('click', function (event) {
        event.stopPropagation();
        setOpen(!isOpen);
    });

    document.addEventListener('click', function (event) {
        if (!dropdown.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
            toggle.focus();
        }
    });

    menu.addEventListener('click', function (event) {
        var link = event.target.closest('[data-knd-portal-link]');
        if (!link) return;

        // Hook para una futura transición tipo portal (sin implementarla todavía)
        body.classList.add('knd-portal-transition-pending');
        window.dispatchEvent(new CustomEvent('knd:portal:navigate', {
            detail: {
                target: link.getAttribute('data-knd-portal-link'),
                href: link.getAttribute('href')
            }
        }));

        setOpen(false);
    });
})();
