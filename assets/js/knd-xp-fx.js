/**
 * KND Arena - XP gain floating pop (+N XP) + Level badge update
 */
(function () {
    'use strict';

    window.showXpGain = function (xpDelta) {
        xpDelta = Number(xpDelta || 0);
        if (xpDelta <= 0) return;

        var old = document.querySelector('.knd-xp-pop');
        if (old && old.parentNode) {
            old.parentNode.removeChild(old);
        }

        var el = document.createElement('div');
        el.className = 'knd-xp-pop';
        el.textContent = '+' + xpDelta + ' XP';
        el.setAttribute('aria-live', 'polite');

        document.body.appendChild(el);

        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 1600);
    };

    window.updateNavLevelBadge = function (level) {
        var lvl = Number(level);
        if (!lvl || lvl < 1) return;
        var badge = document.querySelector('.lvl-badge');
        if (!badge) return;
        var current = parseInt(badge.getAttribute('data-level'), 10);
        if (current === lvl) return;
        badge.setAttribute('data-level', lvl);
        badge.textContent = 'Lvl ' + lvl;
        badge.classList.remove('lvl-badge-flash');
        badge.offsetHeight;
        badge.classList.add('lvl-badge-flash');
        setTimeout(function () { badge.classList.remove('lvl-badge-flash'); }, 600);
    };
})();
