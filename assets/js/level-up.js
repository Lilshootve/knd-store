/**
 * KND Arena - Level Up overlay animation
 * Custom sci-fi HUD style, no alert/SweetAlert
 */
(function () {
    'use strict';

    var DURATION_MS = 2500;

    function getTierForLevel(level) {
        if (level >= 30) return 30;
        if (level >= 20) return 20;
        if (level >= 10) return 10;
        if (level >= 5) return 5;
        return 0;
    }

    window.showLevelUp = function (oldLevel, newLevel) {
        var oldL = Number(oldLevel);
        var newL = Number(newLevel);
        if (isNaN(oldL) || isNaN(newL) || newL <= oldL) return;

        var tier = getTierForLevel(newL);
        var tierClass = tier ? ' knd-level-up-tier-' + tier : '';

        var overlay = document.createElement('div');
        overlay.className = 'knd-level-up-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-label', 'Level Up!');

        var html = '<div class="knd-level-up-backdrop"></div>';
        html += '<div class="knd-level-up-content' + tierClass + '">';
        html += '  <div class="knd-level-up-title">LEVEL UP</div>';
        html += '  <div class="knd-level-up-levels">Level ' + oldL + ' <span class="knd-level-up-arrow">&rarr;</span> Level ' + newL + '</div>';
        html += '</div>';

        overlay.innerHTML = html;
        document.body.appendChild(overlay);

        requestAnimationFrame(function () {
            overlay.classList.add('visible');
        });

        function close() {
            overlay.classList.remove('visible');
            setTimeout(function () {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            }, 400);
        }

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.classList.contains('knd-level-up-backdrop')) close();
        });

        setTimeout(close, DURATION_MS);

        try {
            localStorage.setItem('knd_last_seen_level', String(newL));
        } catch (e) {}
    };

    // Fallback: detect level-up on page load (e.g. leveled via admin, different tab)
    function checkLevelUpOnLoad() {
        var badge = document.querySelector('.lvl-badge[data-level]');
        if (!badge) return;
        var current = parseInt(badge.getAttribute('data-level'), 10);
        if (isNaN(current) || current < 1) return;
        var lastStr;
        try {
            lastStr = localStorage.getItem('knd_last_seen_level');
        } catch (e) { return; }
        var last = (lastStr != null && lastStr !== '') ? parseInt(lastStr, 10) : NaN;
        if (!isNaN(last) && current > last) {
            if (typeof updateNavLevelBadge === 'function') updateNavLevelBadge(current);
            showLevelUp(last, current);
        }
        try {
            localStorage.setItem('knd_last_seen_level', String(current));
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkLevelUpOnLoad);
    } else {
        checkLevelUpOnLoad();
    }

    // Ensure styles exist
    var styleId = 'knd-level-up-styles';
    if (!document.getElementById(styleId)) {
        var style = document.createElement('style');
        style.id = styleId;
        style.textContent = [
            '.knd-level-up-overlay { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity .3s ease; pointer-events:none; }',
            '.knd-level-up-overlay.visible { opacity:1; pointer-events:auto; }',
            '.knd-level-up-backdrop { position:absolute; inset:0; background:rgba(0,0,0,.7); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); cursor:pointer; }',
            '.knd-level-up-content { position:relative; padding:2rem 3rem; text-align:center; background:linear-gradient(135deg,rgba(0,212,255,.12),rgba(37,156,174,.08)); border:2px solid rgba(0,212,255,.5); border-radius:16px; box-shadow:0 0 40px rgba(0,212,255,.3), inset 0 0 30px rgba(0,212,255,.05); animation:knd-level-up-pop .5s ease forwards; max-width:90vw; }',
            '.knd-level-up-title { font-family:"Orbitron",sans-serif; font-size:2rem; font-weight:700; color:#00d4ff; text-transform:uppercase; letter-spacing:.2em; margin-bottom:.5rem; text-shadow:0 0 20px rgba(0,212,255,.8); animation:knd-level-up-glow 1.5s ease-in-out infinite; }',
            '.knd-level-up-levels { font-size:1.25rem; color:rgba(255,255,255,.95); font-family:"Orbitron",monospace; }',
            '.knd-level-up-arrow { color:#00d4ff; margin:0 .5rem; }',
            '.knd-level-up-content.knd-level-up-tier-5 { background:linear-gradient(135deg,rgba(74,222,128,.15),rgba(34,197,94,.08)); border-color:rgba(74,222,128,.6); box-shadow:0 0 50px rgba(74,222,128,.35), inset 0 0 30px rgba(74,222,128,.08); }',
            '.knd-level-up-content.knd-level-up-tier-5 .knd-level-up-title { color:#4ade80; text-shadow:0 0 24px rgba(74,222,128,.9); animation:knd-level-up-glow-5 1.5s ease-in-out infinite; }',
            '.knd-level-up-content.knd-level-up-tier-5 .knd-level-up-arrow { color:#4ade80; }',
            '.knd-level-up-content.knd-level-up-tier-10 { background:linear-gradient(135deg,rgba(167,139,250,.15),rgba(139,92,246,.08)); border-color:rgba(167,139,250,.6); box-shadow:0 0 50px rgba(167,139,250,.35), inset 0 0 30px rgba(167,139,250,.08); }',
            '.knd-level-up-content.knd-level-up-tier-10 .knd-level-up-title { color:#a78bfa; text-shadow:0 0 24px rgba(167,139,250,.9); animation:knd-level-up-glow-10 1.5s ease-in-out infinite; }',
            '.knd-level-up-content.knd-level-up-tier-10 .knd-level-up-arrow { color:#a78bfa; }',
            '.knd-level-up-content.knd-level-up-tier-20 { background:linear-gradient(135deg,rgba(251,191,36,.15),rgba(245,158,11,.08)); border-color:rgba(251,191,36,.6); box-shadow:0 0 50px rgba(251,191,36,.35), inset 0 0 30px rgba(251,191,36,.08); }',
            '.knd-level-up-content.knd-level-up-tier-20 .knd-level-up-title { color:#fbbf24; text-shadow:0 0 24px rgba(251,191,36,.9); animation:knd-level-up-glow-20 1.5s ease-in-out infinite; }',
            '.knd-level-up-content.knd-level-up-tier-20 .knd-level-up-arrow { color:#fbbf24; }',
            '.knd-level-up-content.knd-level-up-tier-30 { background:linear-gradient(135deg,rgba(244,114,182,.15),rgba(219,39,119,.1)); border-color:rgba(244,114,182,.7); box-shadow:0 0 60px rgba(244,114,182,.4), inset 0 0 40px rgba(244,114,182,.1); }',
            '.knd-level-up-content.knd-level-up-tier-30 .knd-level-up-title { color:#f472b6; text-shadow:0 0 28px rgba(244,114,182,.95); animation:knd-level-up-glow-30 1.5s ease-in-out infinite; }',
            '.knd-level-up-content.knd-level-up-tier-30 .knd-level-up-arrow { color:#f472b6; }',
            '@keyframes knd-level-up-pop { 0%{transform:scale(.7); opacity:0} 70%{transform:scale(1.05)} 100%{transform:scale(1); opacity:1} }',
            '@keyframes knd-level-up-glow { 0%,100%{text-shadow:0 0 20px rgba(0,212,255,.8)} 50%{text-shadow:0 0 30px rgba(0,212,255,1)} }',
            '@keyframes knd-level-up-glow-5 { 0%,100%{text-shadow:0 0 24px rgba(74,222,128,.9)} 50%{text-shadow:0 0 32px rgba(74,222,128,1)} }',
            '@keyframes knd-level-up-glow-10 { 0%,100%{text-shadow:0 0 24px rgba(167,139,250,.9)} 50%{text-shadow:0 0 32px rgba(167,139,250,1)} }',
            '@keyframes knd-level-up-glow-20 { 0%,100%{text-shadow:0 0 24px rgba(251,191,36,.9)} 50%{text-shadow:0 0 32px rgba(251,191,36,1)} }',
            '@keyframes knd-level-up-glow-30 { 0%,100%{text-shadow:0 0 28px rgba(244,114,182,.95)} 50%{text-shadow:0 0 38px rgba(244,114,182,1)} }',
            '@media (max-width:576px){ .knd-level-up-content{padding:1.5rem 1.5rem} .knd-level-up-title{font-size:1.4rem; letter-spacing:.1em} .knd-level-up-levels{font-size:1rem} }'
        ].join('\n');
        document.head.appendChild(style);
    }
})();
