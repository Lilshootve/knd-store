/**
 * KND Holo Orbs — vanilla JS spawn / offer / claim (logged-in desktop only).
 * Config: window.__KND_HOLO_ORB__ = { csrf, offerUrl, claimUrl }
 */
(function () {
  "use strict";

  var CFG = window.__KND_HOLO_ORB__;
  if (!CFG || !CFG.csrf || !CFG.offerUrl || !CFG.claimUrl) return;

  /** Desktop / non-touch gate */
  function isOrbEnvironment() {
    if (window.matchMedia && !window.matchMedia("(pointer: fine)").matches) return false;
    if (window.matchMedia && !window.matchMedia("(hover: hover)").matches) return false;
    if (window.innerWidth < 1024) return false;
    if (navigator.maxTouchPoints > 0) return false;
    if ("ontouchstart" in window) return false;
    if (document.body.classList.contains("arena-info-page")) return false;
    if (document.body.classList.contains("admin-page")) return false;
    return true;
  }

  var BAD_SELECTORS = [
    "header",
    ".topbar",
    ".site-header",
    ".navbar",
    ".sc-nav-badge",
    ".lvl-badge",
    "#knd-chat-btn",
    ".knd-chat-panel",
    ".knd-chat-btn",
    ".modal.show",
    ".swal2-container",
    ".bottom-nav",
    ".bnav-item",
    ".settings-drawer",
    ".overlay-panel",
    ".mw-portrait-gate",
    "[data-no-holo-orb]",
  ];

  function overlapsBlockedElements(x, y) {
    var el = document.elementFromPoint(x, y);
    if (!el) return true;
    for (var i = 0; i < BAD_SELECTORS.length; i++) {
      if (el.closest(BAD_SELECTORS[i])) return true;
    }
    return false;
  }

  function pickPosition() {
    var padT =
      Math.max(100, parseInt(getComputedStyle(document.documentElement).getPropertyValue("env(safe-area-inset-top)") || "0", 10) + 88);
    var padB = Math.max(140, parseInt(getComputedStyle(document.documentElement).getPropertyValue("env(safe-area-inset-bottom)") || "0", 10) + 120);
    var padX = 24;
    var w = window.innerWidth;
    var h = window.innerHeight;
    var minY = padT;
    var maxY = h - padB - 56;
    var minX = padX + 28;
    var maxX = w - padX - 28;
    for (var attempt = 0; attempt < 28; attempt++) {
      var x = minX + Math.random() * (maxX - minX);
      var y = minY + Math.random() * (maxY - minY);
      if (!overlapsBlockedElements(x, y)) {
        return { x: x, y: y, maxY: maxY, minY: minY };
      }
    }
    return null;
  }

  function orbClassForType(t) {
    if (t === "ke") return "knd-orb--ke";
    if (t === "knd_points") return "knd-orb--knd_points";
    return "knd-orb--xp";
  }

  function labelForReward(type, amount) {
    if (type === "ke") return "+" + amount + " KE";
    if (type === "knd_points") return "+" + amount + " KP";
    return "+" + amount + " XP";
  }

  function playPickupSound() {
    try {
      if (localStorage.getItem("knd_orb_sound") === "0") return;
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.type = "sine";
      o.frequency.value = 880;
      g.gain.value = 0.04;
      o.connect(g);
      g.connect(ctx.destination);
      o.start();
      o.stop(ctx.currentTime + 0.08);
      setTimeout(function () {
        ctx.close();
      }, 200);
    } catch (e) { /* ignore */ }
  }

  var root = null;
  var busy = false;
  var rafId = 0;

  /** Session totals + HUD (AAA-style yield panel) */
  var hudEl = null;
  var totals = { xp: 0, ke: 0, knd_points: 0 };
  var hudAnimRaf = { xp: 0, ke: 0, knd_points: 0 };
  var hudDisplay = { xp: 0, ke: 0, knd_points: 0 };

  function ensureRoot() {
    if (root) return root;
    root = document.createElement("div");
    root.id = "knd-holo-orb-root";
    root.setAttribute("aria-hidden", "true");
    document.body.appendChild(root);
    return root;
  }

  function hudKeyForRewardType(rt) {
    if (rt === "ke") return "ke";
    if (rt === "knd_points") return "knd_points";
    return "xp";
  }

  function formatHudNum(n) {
    n = Math.round(n);
    if (n >= 1000000) return (n / 1000000).toFixed(1).replace(/\.0$/, "") + "M";
    if (n >= 10000) return (n / 1000).toFixed(1).replace(/\.0$/, "") + "K";
    return String(n);
  }

  function ensureHud() {
    if (hudEl) return hudEl;
    ensureRoot();
    hudEl = document.createElement("div");
    hudEl.className = "knd-holo-hud knd-holo-hud--dormant";
    hudEl.setAttribute("aria-hidden", "true");
    hudEl.innerHTML =
      '<div class="knd-holo-hud__scan" aria-hidden="true"></div>' +
      '<div class="knd-holo-hud__ring" aria-hidden="true"></div>' +
      '<div class="knd-holo-hud__inner">' +
      '<div class="knd-holo-hud__head">' +
      '<span class="knd-holo-hud__glyph" aria-hidden="true"></span>' +
      "<span class=\"knd-holo-hud__title\">HOLO YIELD</span>" +
      '<span class="knd-holo-hud__live" aria-hidden="true">LIVE</span>' +
      "</div>" +
      '<div class="knd-holo-hud__grid">' +
      '<div class="knd-holo-hud__cell knd-holo-hud__cell--xp">' +
      '<span class="knd-holo-hud__label">XP</span>' +
      '<span class="knd-holo-hud__value" data-knd-hud="xp">0</span>' +
      "</div>" +
      '<div class="knd-holo-hud__cell knd-holo-hud__cell--ke">' +
      '<span class="knd-holo-hud__label">KE</span>' +
      '<span class="knd-holo-hud__value" data-knd-hud="ke">0</span>' +
      "</div>" +
      '<div class="knd-holo-hud__cell knd-holo-hud__cell--kp">' +
      '<span class="knd-holo-hud__label">KP</span>' +
      '<span class="knd-holo-hud__value" data-knd-hud="knd_points">0</span>' +
      "</div>" +
      "</div>" +
      '<div class="knd-holo-hud__last" data-knd-hud-last></div>' +
      "</div>";
    root.appendChild(hudEl);
    return hudEl;
  }

  function cancelHudAnim(key) {
    if (hudAnimRaf[key]) {
      cancelAnimationFrame(hudAnimRaf[key]);
      hudAnimRaf[key] = 0;
    }
  }

  function runHudCountUp(key, targetTotal) {
    ensureHud();
    var el = hudEl.querySelector('[data-knd-hud="' + key + '"]');
    if (!el) return;
    var start = hudDisplay[key];
    var end = targetTotal;
    if (start === end) {
      el.textContent = formatHudNum(end);
      return;
    }
    cancelHudAnim(key);
    var t0 = performance.now();
    var dur = 420;
    function step(now) {
      var t = Math.min(1, (now - t0) / dur);
      var e = 1 - Math.pow(1 - t, 3);
      var v = start + (end - start) * e;
      hudDisplay[key] = v;
      el.textContent = formatHudNum(Math.round(v));
      if (t < 1) hudAnimRaf[key] = requestAnimationFrame(step);
      else {
        hudDisplay[key] = end;
        el.textContent = formatHudNum(end);
        hudAnimRaf[key] = 0;
      }
    }
    hudAnimRaf[key] = requestAnimationFrame(step);
  }

  function flashHudLast(rt, amt) {
    ensureHud();
    var last = hudEl.querySelector("[data-knd-hud-last]");
    if (!last) return;
    last.className = "knd-holo-hud__last knd-holo-hud__last--show";
    if (rt === "ke") last.classList.add("knd-holo-hud__last--ke");
    else if (rt === "knd_points") last.classList.add("knd-holo-hud__last--kp");
    else last.classList.add("knd-holo-hud__last--xp");
    last.textContent = labelForReward(rt, amt);
    window.clearTimeout(flashHudLast._t);
    flashHudLast._t = window.setTimeout(function () {
      last.className = "knd-holo-hud__last";
      last.textContent = "";
    }, 1400);
  }

  function pulseHudCell(key) {
    if (!hudEl) return;
    var cell = hudEl.querySelector(".knd-holo-hud__cell--" + (key === "knd_points" ? "kp" : key));
    if (!cell) return;
    cell.classList.remove("knd-holo-hud__cell--bump");
    void cell.offsetWidth;
    cell.classList.add("knd-holo-hud__cell--bump");
  }

  function onOrbClaimed(rt, amt) {
    var key = hudKeyForRewardType(rt);
    totals[key] += amt;
    ensureHud();
    hudEl.classList.remove("knd-holo-hud--dormant");
    hudEl.classList.add("knd-holo-hud--active");
    hudEl.classList.remove("knd-holo-hud--surge");
    void hudEl.offsetWidth;
    hudEl.classList.add("knd-holo-hud--surge");
    window.setTimeout(function () {
      if (hudEl) hudEl.classList.remove("knd-holo-hud--surge");
    }, 600);
    runHudCountUp(key, totals[key]);
    pulseHudCell(key);
    flashHudLast(rt, amt);
  }

  function postJson(url) {
    return fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-CSRF-Token": CFG.csrf,
      },
      body: "",
    }).then(function (r) {
      return r.json().then(function (j) {
        return { ok: r.ok, status: r.status, json: j };
      });
    });
  }

  function destroyWrap(wrap) {
    if (rafId) {
      cancelAnimationFrame(rafId);
      rafId = 0;
    }
    if (wrap && wrap.parentNode) wrap.remove();
  }

  function mountOrb(rewardType, amount) {
    var pos = pickPosition();
    if (!pos) {
      busy = false;
      return;
    }

    var host = ensureRoot();
    var wrap = document.createElement("div");
    wrap.style.position = "absolute";
    wrap.style.left = pos.x + "px";
    wrap.style.top = pos.y + "px";
    wrap.style.transform = "translate(-50%, -50%)";
    wrap.style.zIndex = "1";

    var orb = document.createElement("button");
    orb.type = "button";
    orb.className = "knd-holo-orb " + orbClassForType(rewardType);
    orb.setAttribute("aria-label", "Collect holo reward");

    var t0 = performance.now();
    var phase = Math.random() * Math.PI * 2;

    function tick(now) {
      var t = (now - t0) / 1000;
      var bob = Math.sin(t * 1.6 + phase) * 6;
      var sway = Math.cos(t * 1.1 + phase) * 3;
      orb.style.transform = "translate(" + sway + "px," + bob + "px)";
      rafId = requestAnimationFrame(tick);
    }
    rafId = requestAnimationFrame(tick);

    wrap.appendChild(orb);
    host.appendChild(wrap);

    requestAnimationFrame(function () {
      orb.classList.add("knd-holo-orb--visible");
    });

    var claimed = false;
    orb.addEventListener(
      "click",
      function () {
        if (claimed) return;
        claimed = true;
        orb.classList.add("knd-orb--pop");
        playPickupSound();

        postJson(CFG.claimUrl)
          .then(function (res) {
            var j = res.json || {};
            var data = j.data || j;
            var ok = j.ok === true && data && data.success === true;
            var rt = ok ? data.reward_type : null;
            var amt = ok ? data.amount : 0;
            if (!ok) {
              destroyWrap(wrap);
              busy = false;
              return;
            }
            try {
              onOrbClaimed(rt, amt);
            } catch (e) { /* ignore */ }
            var float = document.createElement("div");
            float.className = "knd-holo-orb-float";
            if (rt === "ke") float.classList.add("knd-float--ke");
            if (rt === "knd_points") float.classList.add("knd-float--knd_points");
            float.textContent = labelForReward(rt, amt);
            float.style.left = "50%";
            float.style.top = "0";
            wrap.appendChild(float);
            setTimeout(function () {
              destroyWrap(wrap);
              busy = false;
            }, 900);
          })
          .catch(function () {
            destroyWrap(wrap);
            busy = false;
          });
      },
      { once: true }
    );
  }

  function trySpawn() {
    if (!isOrbEnvironment() || busy) return;
    if (Math.random() > 0.3) return;
    busy = true;
    postJson(CFG.offerUrl)
      .then(function (res) {
        var j = res.json || {};
        var data = j.data || j;
        if (!j.ok || !data || data.success !== true) {
          busy = false;
          return;
        }
        mountOrb(data.reward_type, data.amount);
      })
      .catch(function () {
        busy = false;
      });
  }

  function scheduleLoop() {
    var delay = 120000 + Math.random() * 180000;
    setTimeout(function () {
      trySpawn();
      scheduleLoop();
    }, delay);
  }

  function boot() {
    if (!isOrbEnvironment()) return;
    ensureRoot();
    ensureHud();
    scheduleLoop();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
