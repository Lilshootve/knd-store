/**
 * KND Cursor FX — vanilla JS, requestAnimationFrame smoothing
 * - Follows pointer with exponential easing
 * - Optional click ripples (spawned under fixed overlay, pointer-events: none)
 * - Skips touch / coarse pointer / reduced motion / opt-out data attribute
 *
 * No dependencies. Does not call preventDefault or stopPropagation.
 */
(function () {
  'use strict';

  var EASE = 0.14; /* higher = snappier (0.1–0.22 typical) */
  var RIPPLE_ENABLED = true;

  /**
   * Opt out on any page: <html data-knd-cursor-fx="off">
   */
  function isOptOut() {
    var v = document.documentElement.getAttribute('data-knd-cursor-fx');
    return v === 'off' || v === 'false' || v === '0';
  }

  function prefersReducedMotion() {
    try {
      return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) {
      return false;
    }
  }

  function isCoarseOrNoHover() {
    try {
      if (window.matchMedia('(pointer: coarse)').matches) return true;
      if (window.matchMedia('(hover: none)').matches) return true;
    } catch (e) {}
    return false;
  }

  function disable(reason) {
    document.documentElement.classList.add('knd-cursor-fx--disabled');
    if (typeof console !== 'undefined' && console.debug) {
      console.debug('[knd-cursor-fx] disabled:', reason);
    }
  }

  function buildDOM() {
    var root = document.createElement('div');
    root.id = 'knd-cursor-fx';
    root.className = 'knd-cursor-fx';
    root.setAttribute('aria-hidden', 'true');

    var track = document.createElement('div');
    track.className = 'knd-cursor-fx__track';

    var glow = document.createElement('div');
    glow.className = 'knd-cursor-fx__glow';

    var lens = document.createElement('div');
    lens.className = 'knd-cursor-fx__lens';

    var core = document.createElement('div');
    core.className = 'knd-cursor-fx__core';

    track.appendChild(glow);
    track.appendChild(lens);
    track.appendChild(core);
    root.appendChild(track);

    document.body.appendChild(root);
    return { root: root, track: track };
  }

  function spawnRipple(rootEl, clientX, clientY) {
    if (!RIPPLE_ENABLED || !rootEl) return;
    /* #knd-cursor-fx is fixed inset:0 → clientX/Y are already local coordinates. */
    var el = document.createElement('div');
    el.className = 'knd-cursor-fx__ripple';
    el.style.left = clientX + 'px';
    el.style.top = clientY + 'px';
    rootEl.appendChild(el);
    window.setTimeout(function () {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, 700);
  }

  function init() {
    if (isOptOut()) {
      disable('data-knd-cursor-fx');
      return;
    }
    if (prefersReducedMotion()) {
      disable('prefers-reduced-motion');
      return;
    }
    if (isCoarseOrNoHover()) {
      disable('coarse-pointer-or-no-hover');
      return;
    }
    if (!document.body) return;

    var dom = buildDOM();
    var track = dom.track;

    var targetX = window.innerWidth / 2;
    var targetY = window.innerHeight / 2;
    var currentX = targetX;
    var currentY = targetY;
    var rafId = null;
    var visible = false;

    function applyPosition() {
      track.style.left = currentX + 'px';
      track.style.top = currentY + 'px';
      dom.root.style.opacity = visible ? '1' : '0';
    }

    function tick() {
      var dx = targetX - currentX;
      var dy = targetY - currentY;
      if (Math.abs(dx) < 0.08 && Math.abs(dy) < 0.08) {
        currentX = targetX;
        currentY = targetY;
        applyPosition();
        rafId = null;
        return;
      }
      currentX += dx * EASE;
      currentY += dy * EASE;
      applyPosition();
      rafId = window.requestAnimationFrame(tick);
    }

    function scheduleTick() {
      if (rafId == null) {
        rafId = window.requestAnimationFrame(tick);
      }
    }

    function onMove(e) {
      if (e.pointerType && e.pointerType !== 'mouse') return;
      targetX = e.clientX;
      targetY = e.clientY;
      visible = true;
      scheduleTick();
    }

    function onLeave() {
      visible = false;
      dom.root.style.opacity = '0';
    }

    function onPointerDown(e) {
      if (e.pointerType && e.pointerType !== 'mouse') return;
      if (e.button !== 0) return;
      spawnRipple(dom.root, e.clientX, e.clientY);
    }

    window.addEventListener('pointermove', onMove, { passive: true });
    document.documentElement.addEventListener('mouseleave', onLeave, { passive: true });
    window.addEventListener('blur', onLeave, { passive: true });
    window.addEventListener('pointerdown', onPointerDown, { passive: true });

    applyPosition();

    /* Re-show when re-entering window */
    window.addEventListener(
      'pointerenter',
      function (e) {
        if (e.pointerType === 'mouse') visible = true;
      },
      { passive: true }
    );
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
