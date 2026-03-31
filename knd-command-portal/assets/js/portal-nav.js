(function () {
    // Integración UX: oscurecer fondo cuando el dropdown de Command Portal está abierto.
    var dropdownRoot = document.querySelector('.knd-portal.dropdown');
    if (!dropdownRoot) return;

    dropdownRoot.addEventListener('shown.bs.dropdown', function () {
        document.body.classList.add('portal-dropdown-open');
    });

    dropdownRoot.addEventListener('hidden.bs.dropdown', function () {
        document.body.classList.remove('portal-dropdown-open');
    });
})();

