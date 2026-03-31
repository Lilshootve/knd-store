/**
 * KND Cursor FX — singularity lens: SVG backdrop warp, smooth rAF follow,
 * optional click “lensing” pulse. Touch / reduced-motion safe.
 * Opt out: <html data-knd-cursor-fx="off">
 */
(function () {
  'use strict';

  var EASE = 0.165;
  var FILTER_ID = 'knd-cfx-backdrop-warp';
  var PING_MS = 520;

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

  /** Smoother warp: blurred turbulence before displacement */
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
    turb.setAttribute('baseFrequency', '0.009');
    turb.setAttribute('numOctaves', '2');
    turb.setAttribute('seed', '23');
    turb.setAttribute('result', 'raw');

    var blur = document.createElementNS(ns, 'feGaussianBlur');
    blur.setAttribute('in', 'raw');
    blur.setAttribute('stdDeviation', '1.1');
    blur.setAttribute('result', 'smooth');

    var disp = document.createElementNS(ns, 'feDisplacementMap');
    disp.setAttribute('in', 'SourceGraphic');
    disp.setAttribute('in2', 'smooth');
    disp.setAttribute('scale', '11');
    disp.setAttribute('xChannelSelector', 'R');
    disp.setAttribute('yChannelSelector', 'G');

    filter.appendChild(turb);
    filter.appendChild(blur);
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

    var lens = document.createElement('div');
    lens.className = 'knd-cursor-fx__lens';

    var bloom = document.createElement('div');
    bloom.className = 'knd-cursor-fx__bloom';

    var warp = document.createElement('div');
    warp.className = 'knd-cursor-fx__warp';

    var voidEl = document.createElement('div');
    voidEl.className = 'knd-cursor-fx__void';

    var ion = document.createElement('div');
    ion.className = 'knd-cursor-fx__ion';

    var photon = document.createElement('div');
    photon.className = 'knd-cursor-fx__photon';

    var arc = document.createElement('div');
    arc.className = 'knd-cursor-fx__arc';

    var caustic = document.createElement('div');
    caustic.className = 'knd-cursor-fx__caustic';

    lens.appendChild(bloom);
    lens.appendChild(warp);
    lens.appendChild(voidEl);
    lens.appendChild(ion);
    lens.appendChild(photon);
    lens.appendChild(arc);
    lens.appendChild(caustic);
    track.appendChild(lens);
    root.appendChild(track);
    (mount || document.body).appendChild(root);

    return { root: root, track: track, lens: lens };
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
    var pingTimer = null;

    function applyPosition() {
      dom.track.style.left = currentX + 'px';
      dom.track.style.top = currentY + 'px';
      dom.root.style.opacity = visible ? '1' : '0';
    }

    function tick() {
      var dx = targetX - currentX;
      var dy = targetY - currentY;
      if (Math.abs(dx) < 0.05 && Math.abs(dy) < 0.05) {
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

    function lensPing() {
      if (prefersReducedMotion()) return;
      dom.lens.classList.remove('knd-cursor-fx__lens--ping');
      void dom.lens.offsetWidth;
      dom.lens.classList.add('knd-cursor-fx__lens--ping');
      if (pingTimer) clearTimeout(pingTimer);
      pingTimer = setTimeout(function () {
        dom.lens.classList.remove('knd-cursor-fx__lens--ping');
        pingTimer = null;
      }, PING_MS);
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

    window.addEventListener(
      'pointerdown',
      function (e) {
        if (e.pointerType && e.pointerType !== 'mouse') return;
        if (e.button !== 0) return;
        lensPing();
      },
      true
    );

    applyPosition();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
