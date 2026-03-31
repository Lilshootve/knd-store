// KND Store - Navigation extensions + Orders dropdown
// Single source of truth for the Orders dropdown toggle logic.

(function() {
    'use strict';
    if (window.__kndNavigationExtendInit) {
        return;
    }
    window.__kndNavigationExtendInit = true;

    // ─── Nav item injection (Apparel / Custom Design) ───

    function extendNavigation() {
        var currentPage = window.location.pathname.split('/').pop() || 'index.php';
        var navList = document.querySelector('#navbarNav .navbar-nav');

        if (!navList) {
            return;
        }

        if (navList.querySelector('a[href="/apparel.php"]')) return;

        var aboutItem = navList.querySelector('a[href="/about.php"]');
        var contactItem = navList.querySelector('a[href="/contact.php"]');
        if (!aboutItem || !contactItem) return;

        function tJs(key, fallback) {
            return (window.I18N && window.I18N[key]) || fallback || key;
        }

        var apparelItem = document.createElement('li');
        apparelItem.className = 'nav-item';
        var apparelLink = document.createElement('a');
        apparelLink.className = 'nav-link' + (currentPage === 'apparel.php' ? ' active' : '');
        apparelLink.href = '/apparel.php';
        apparelLink.textContent = tJs('nav.apparel', 'Apparel');
        apparelItem.appendChild(apparelLink);

        var customItem = document.createElement('li');
        customItem.className = 'nav-item';
        var customLink = document.createElement('a');
        customLink.className = 'nav-link' + (currentPage === 'custom-design.php' ? ' active' : '');
        customLink.href = '/custom-design.php';
        customLink.textContent = tJs('nav.custom_design', 'Custom Design');
        customItem.appendChild(customLink);

        aboutItem.parentElement.insertAdjacentElement('afterend', apparelItem);
        apparelItem.insertAdjacentElement('afterend', customItem);
    }

    // ─── Orders dropdown (ID-based, single implementation) ───

    var DESKTOP_BP = 992;
    var lastTouchToggleAt = 0;

    function getToggle() { return document.getElementById('ordersDropdownToggle'); }
    function getMenu()   { return document.getElementById('ordersDropdownMenu'); }

    function isOpen() {
        var menu = getMenu();
        return menu && menu.classList.contains('open');
    }

    function openDropdown() {
        var toggle = getToggle();
        var menu   = getMenu();
        if (!toggle || !menu) return;
        menu.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
    }

    function closeDropdown() {
        var toggle = getToggle();
        var menu   = getMenu();
        if (!toggle || !menu) return;
        menu.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    // 1) Toggle click
    document.addEventListener('click', function(e) {
        var toggle = getToggle();
        var menu   = getMenu();
        if (!toggle || !menu) return;
        var isToggleTarget = (toggle === e.target || toggle.contains(e.target));

        // Mobile browsers often dispatch a synthetic click right after touchend.
        // Ignore that click to prevent immediate open->close flicker.
        if (isToggleTarget && (Date.now() - lastTouchToggleAt) < 700) {
            e.preventDefault();
            e.stopPropagation();
            return;
        }
        if (isToggleTarget) {
            e.preventDefault();
            e.stopPropagation();
            if (isOpen()) closeDropdown();
            else openDropdown();
            return;
        }

        // Clicked on a link inside the menu — navigate, close dropdown
        var item = e.target.closest('.knd-dropdown-item');
        if (item && menu.contains(item)) {
            closeDropdown();
            if (window.innerWidth < DESKTOP_BP) {
                try {
                    var navCollapse = document.getElementById('navbarNav');
                    if (navCollapse && navCollapse.classList.contains('show')) {
                        var bsCollapse = bootstrap.Collapse.getInstance(navCollapse);
                        if (bsCollapse) bsCollapse.hide();
                    }
                } catch (err) { /* bootstrap not loaded yet */ }
            }
            return;
        }

        // Clicked outside toggle + menu — close
        if (!menu.contains(e.target)) {
            closeDropdown();
        }
    }, true);

    // 1b) Touch support for mobile (click can be delayed or not fire on touch)
    document.addEventListener('touchend', function(e) {
        var toggle = getToggle();
        var menu   = getMenu();
        if (!toggle || !menu) return;
        if (toggle !== e.target && !toggle.contains(e.target)) return;
        lastTouchToggleAt = Date.now();
        e.preventDefault();
        e.stopPropagation();
        if (isOpen()) closeDropdown();
        else openDropdown();
    }, { passive: false });

    // 2) Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDropdown();
    });

    // 3) Reset on resize / orientation change (close if going to desktop)
    function onViewportChange() {
        if (window.innerWidth >= DESKTOP_BP) {
            closeDropdown();
        }
    }
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('orientationchange', onViewportChange);

    // 4) Close when Bootstrap hamburger collapse hides
    function bindCollapseReset() {
        var navCollapse = document.getElementById('navbarNav');
        if (navCollapse) {
            navCollapse.addEventListener('hide.bs.collapse', function() {
                closeDropdown();
            });
        }
    }

    // ─── Init ───

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            extendNavigation();
            bindCollapseReset();
        });
    } else {
        extendNavigation();
        bindCollapseReset();
    }

})();
