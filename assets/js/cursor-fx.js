/**
 * KND Cursor FX — black-hole style backdrop warp (SVG displacement on backdrop),
 * smooth follow via requestAnimationFrame. Touch / reduced-motion safe.
 *
 * Opt out: <html data-knd-cursor-fx="off">
 * No preventDefault; pointer-events stay on underlying UI.
 */
(function () {
  'use strict';

  var EASE = 0.13;
  var FILTER_ID = 'knd-cfx-backdrop-warp';

  function isOptOut() {
    var v = document.documentElement.getAttribute('data-knd-cursor-fx');
    return v === 'off' || v === 'false' || v === '0';
  }

  function prefersReducedMotion() {
    try {
      return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
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
      console.debug('[knd-cursor-fx]', reason);
    }
  }

  /**
   * SVG filter used by CSS backdrop-filter: url(#id).
   * feDisplacementMap warps the backdrop using fractal noise (subtle, premium).
   */
  function injectSvgFilter() {
    if (document.getElementById('knd-cfx-svg-defs')) return;

    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('aria-hidden', 'true');
    svg.id = 'knd-cfx-svg-defs';
    svg.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden;pointer-events:none';

    var defs = document.createElementNS(ns, 'defs');
    var filter = document.createElementNS(ns, 'filter');
    filter.setAttribute('id', FILTER_ID);
    filter.setAttribute('x', '-50%');
    filter.setAttribute('y', '-50%');
    filter.setAttribute('width', '200%');
    filter.setAttribute('height', '200%');
    filter.setAttribute('color-interpolation-filters', 'sRGB');

    var turb = document.createElementNS(ns, 'feTurbulence');
    turb.setAttribute('type', 'fractalNoise');
    turb.setAttribute('baseFrequency', '0.013');
    turb.setAttribute('numOctaves', '2');
    turb.setAttribute('seed', '17');
    turb.setAttribute('result', 'turb');

    var disp = document.createElementNS(ns, 'feDisplacementMap');
    disp.setAttribute('in', 'SourceGraphic');
    disp.setAttribute('in2', 'turb');
    disp.setAttribute('scale', '14');
    disp.setAttribute('xChannelSelector', 'R');
    disp.setAttribute('yChannelSelector', 'G');

    filter.appendChild(turb);
    filter.appendChild(disp);
    defs.appendChild(filter);
    svg.appendChild(defs);

    document.documentElement.insertBefore(svg, document.documentElement.firstChild);
  }

  function buildDOM() {
    var mount = document.getElementById('knd-cursor-fx-root');
    var root = document.createElement('div');
    root.id = 'knd-cursor-fx';
    root.className = 'knd-cursor-fx ' + (mount ? 'knd-cursor-fx--in-root' : 'knd-cursor-fx--floating');
    root.setAttribute('aria-hidden', 'true');

    var track = document.createElement('div');
    track.className = 'knd-cursor-fx__track';

    var warp = document.createElement('div');
    warp.className = 'knd-cursor-fx__warp';

    var voidEl = document.createElement('div');
    voidEl.className = 'knd-cursor-fx__void';

    var rim = document.createElement('div');
    rim.className = 'knd-cursor-fx__rim';

    var sing = document.createElement('div');
    sing.className = 'knd-cursor-fx__singularity';

    track.appendChild(warp);
    track.appendChild(voidEl);
    track.appendChild(rim);
    track.appendChild(sing);
    root.appendChild(track);
    (mount || document.body).appendChild(root);

    return { root: root, track: track };
  }

  function init() {
    if (isOptOut()) {
      disable('opt-out');
      return;
    }
    if (prefersReducedMotion()) {
      disable('prefers-reduced-motion');
      return;
    }
    if (isCoarseOrNoHover()) {
      disable('touch/coarse');
      return;
    }
    if (!document.body) return;

    injectSvgFilter();
    var dom = buildDOM();

    var targetX = window.innerWidth / 2;
    var targetY = window.innerHeight / 2;
    var currentX = targetX;
    var currentY = targetY;
    var rafId = null;
    var visible = false;

    function applyPosition() {
      dom.track.style.left = currentX + 'px';
      dom.track.style.top = currentY + 'px';
      dom.root.style.opacity = visible ? '1' : '0';
    }

    function tick() {
      var dx = targetX - currentX;
      var dy = targetY - currentY;
      if (Math.abs(dx) < 0.06 && Math.abs(dy) < 0.06) {
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
      if (rafId == null) rafId = window.requestAnimationFrame(tick);
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

    window.addEventListener('pointermove', onMove, { passive: true });
    document.documentElement.addEventListener('mouseleave', onLeave, { passive: true });
    window.addEventListener('blur', onLeave, { passive: true });
    window.addEventListener(
      'pointerenter',
      function (e) {
        if (e.pointerType === 'mouse') visible = true;
      },
      { passive: true }
    );

    applyPosition();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
