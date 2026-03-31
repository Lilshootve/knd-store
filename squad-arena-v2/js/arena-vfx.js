/**
 * Mind Wars Arena — Procedural VFX System
 * Unique visual effects per ability type using Three.js geometry.
 * Requires THREE global + scene reference from MWArenaThree.
 */
(function () {
  'use strict';

  var _active = [];

  function getScene() {
    if (window.MWArenaThree && MWArenaThree.getActionContext) {
      var ctx = MWArenaThree.getActionContext();
      return ctx && ctx.scene ? ctx.scene : null;
    }
    return null;
  }

  /** Remove a VFX group from scene and dispose */
  function cleanup(vfx) {
    var scene = getScene();
    if (!vfx) return;
    if (scene) scene.remove(vfx.group);
    vfx.group.traverse(function (o) {
      if (o.isMesh || o.isLine) {
        if (o.geometry) o.geometry.dispose();
        if (o.material) {
          if (Array.isArray(o.material)) o.material.forEach(function (m) { m.dispose(); });
          else o.material.dispose();
        }
      }
    });
    vfx.alive = false;
  }

  /** Schedule auto-cleanup */
  function autoClean(vfx, dur) {
    setTimeout(function () { cleanup(vfx); }, dur);
  }

  /* ══════════════════════════════════════════════════════════════════
     1. LIGHTNING BOLT — jagged line from A to B
     ══════════════════════════════════════════════════════════════════ */
  function spawnLightning(from, to, opts) {
    var scene = getScene();
    if (!scene || !from || !to) return null;
    var o = opts || {};
    var color = o.color || 0x00ccff;
    var thickness = o.thickness || 3;
    var segments = o.segments || 12;
    var duration = o.duration || 350;
    var branches = o.branches !== false;

    var group = new THREE.Group();

    function buildBolt(start, end, segs, spread, lineW) {
      var pts = [];
      for (var i = 0; i <= segs; i++) {
        var t = i / segs;
        var x = start.x + (end.x - start.x) * t;
        var y = start.y + (end.y - start.y) * t;
        var z = start.z + (end.z - start.z) * t;
        if (i > 0 && i < segs) {
          x += (Math.random() - 0.5) * spread;
          y += (Math.random() - 0.5) * spread * 0.5;
          z += (Math.random() - 0.5) * spread;
        }
        pts.push(new THREE.Vector3(x, y, z));
      }
      var geo = new THREE.BufferGeometry().setFromPoints(pts);
      var mat = new THREE.LineBasicMaterial({
        color: color,
        transparent: true,
        opacity: 1,
        linewidth: lineW || thickness,
        blending: THREE.AdditiveBlending,
        depthWrite: false
      });
      return new THREE.Line(geo, mat);
    }

    // Main bolt
    var mainBolt = buildBolt(from, to, segments, 0.6, thickness);
    group.add(mainBolt);

    // Glow bolt (wider, dimmer)
    var glowBolt = buildBolt(from, to, segments, 0.7, thickness + 2);
    glowBolt.material.opacity = 0.3;
    glowBolt.material.color.set(0xffffff);
    group.add(glowBolt);

    // Branches
    if (branches) {
      for (var b = 0; b < 3; b++) {
        var branchStart = new THREE.Vector3().lerpVectors(from, to, 0.2 + Math.random() * 0.6);
        var branchEnd = branchStart.clone().add(new THREE.Vector3(
          (Math.random() - 0.5) * 1.5,
          (Math.random() - 0.5) * 0.8,
          (Math.random() - 0.5) * 1.5
        ));
        var branchLine = buildBolt(branchStart, branchEnd, 5, 0.3, 1);
        branchLine.material.opacity = 0.5;
        group.add(branchLine);
      }
    }

    // Core glow sphere at impact
    var glowGeo = new THREE.SphereGeometry(0.2, 8, 8);
    var glowMat = new THREE.MeshBasicMaterial({
      color: color, transparent: true, opacity: 0.6,
      blending: THREE.AdditiveBlending, depthWrite: false
    });
    var glowSphere = new THREE.Mesh(glowGeo, glowMat);
    glowSphere.position.copy(to);
    group.add(glowSphere);

    scene.add(group);

    var vfx = { group: group, alive: true };

    // Animate: flicker bolts, then fade
    var t0 = performance.now();
    function tick() {
      if (!vfx.alive) return;
      var p = (performance.now() - t0) / duration;
      if (p >= 1) { cleanup(vfx); return; }
      // Flicker
      var flick = Math.random() > 0.3 ? 1 : 0.2;
      mainBolt.material.opacity = flick * (1 - p);
      glowBolt.material.opacity = flick * 0.3 * (1 - p);
      glowSphere.material.opacity = 0.6 * (1 - p);
      glowSphere.scale.setScalar(1 + p * 2);
      requestAnimationFrame(tick);
    }
    tick();
    _active.push(vfx);
    return vfx;
  }

  /* ══════════════════════════════════════════════════════════════════
     2. ENERGY PROJECTILE — glowing sphere traveling A to B
     ══════════════════════════════════════════════════════════════════ */
  function spawnProjectile(from, to, opts) {
    var scene = getScene();
    if (!scene || !from || !to) return null;
    var o = opts || {};
    var color = o.color || 0xaa44ff;
    var speed = o.speed || 600;
    var size = o.size || 0.18;
    var onHit = o.onHit || null;

    var group = new THREE.Group();

    // Core
    var coreGeo = new THREE.SphereGeometry(size, 12, 12);
    var coreMat = new THREE.MeshBasicMaterial({
      color: 0xffffff, transparent: true, opacity: 0.9,
      blending: THREE.AdditiveBlending, depthWrite: false
    });
    var core = new THREE.Mesh(coreGeo, coreMat);
    group.add(core);

    // Outer glow
    var glowGeo = new THREE.SphereGeometry(size * 2.5, 12, 12);
    var glowMat = new THREE.MeshBasicMaterial({
      color: color, transparent: true, opacity: 0.35,
      blending: THREE.AdditiveBlending, depthWrite: false
    });
    var glow = new THREE.Mesh(glowGeo, glowMat);
    group.add(glow);

    group.position.copy(from);
    scene.add(group);

    var dist = from.distanceTo(to);
    var duration = (dist / 8) * speed;
    var vfx = { group: group, alive: true };

    var t0 = performance.now();
    function tick() {
      if (!vfx.alive) return;
      var p = Math.min((performance.now() - t0) / duration, 1);
      group.position.lerpVectors(from, to, p);
      glow.scale.setScalar(1 + Math.sin(p * Math.PI * 6) * 0.3);
      core.material.opacity = 0.9 - p * 0.3;
      if (p >= 1) {
        if (onHit) onHit();
        cleanup(vfx);
        return;
      }
      requestAnimationFrame(tick);
    }
    tick();
    _active.push(vfx);
    return vfx;
  }

  /* ══════════════════════════════════════════════════════════════════
     3. SHOCKWAVE — expanding ring on ground
     ══════════════════════════════════════════════════════════════════ */
  function spawnShockwave(center, opts) {
    var scene = getScene();
    if (!scene || !center) return null;
    var o = opts || {};
    var color = o.color || 0xffcc00;
    var maxRadius = o.maxRadius || 4;
    var duration = o.duration || 600;
    var y = o.y != null ? o.y : 0.05;

    var group = new THREE.Group();

    var ringGeo = new THREE.RingGeometry(0.1, 0.2, 48);
    var ringMat = new THREE.MeshBasicMaterial({
      color: color, transparent: true, opacity: 0.8,
      side: THREE.DoubleSide, blending: THREE.AdditiveBlending, depthWrite: false
    });
    var ring = new THREE.Mesh(ringGeo, ringMat);
    ring.rotation.x = -Math.PI / 2;
    ring.position.set(center.x, y, center.z);
    group.add(ring);

    // Secondary ring
    var ring2Geo = new THREE.RingGeometry(0.05, 0.12, 48);
    var ring2Mat = new THREE.MeshBasicMaterial({
      color: 0xffffff, transparent: true, opacity: 0.4,
      side: THREE.DoubleSide, blending: THREE.AdditiveBlending, depthWrite: false
    });
    var ring2 = new THREE.Mesh(ring2Geo, ring2Mat);
    ring2.rotation.x = -Math.PI / 2;
    ring2.position.set(center.x, y + 0.01, center.z);
    group.add(ring2);

    scene.add(group);

    var vfx = { group: group, alive: true };
    var t0 = performance.now();
    function tick() {
      if (!vfx.alive) return;
      var p = Math.min((performance.now() - t0) / duration, 1);
      var ease = 1 - Math.pow(1 - p, 2);
      var r = ease * maxRadius;
      ring.scale.set(r / 0.2, r / 0.2, 1);
      ring2.scale.set(r * 0.7 / 0.12, r * 0.7 / 0.12, 1);
      ring.material.opacity = 0.8 * (1 - p);
      ring2.material.opacity = 0.4 * (1 - p);
      if (p >= 1) { cleanup(vfx); return; }
      requestAnimationFrame(tick);
    }
    tick();
    _active.push(vfx);
    return vfx;
  }

  /* ══════════════════════════════════════════════════════════════════
     4. SLASH ARC — curved trail for melee
     ══════════════════════════════════════════════════════════════════ */
  function spawnSlash(center, opts) {
    var scene = getScene();
    if (!scene || !center) return null;
    var o = opts || {};
    var color = o.color || 0xff4444;
    var duration = o.duration || 300;
    var radius = o.radius || 0.8;

    var group = new THREE.Group();
    group.position.copy(center);
    group.position.y += 1.0;

    // Arc geometry
    var pts = [];
    var arcStart = -Math.PI * 0.4;
    var arcEnd = Math.PI * 0.4;
    for (var i = 0; i <= 20; i++) {
      var t = i / 20;
      var angle = arcStart + (arcEnd - arcStart) * t;
      pts.push(new THREE.Vector3(
        Math.cos(angle) * radius,
        Math.sin(angle) * radius * 0.5,
        0
      ));
    }
    var geo = new THREE.BufferGeometry().setFromPoints(pts);
    var mat = new THREE.LineBasicMaterial({
      color: color, transparent: true, opacity: 1,
      linewidth: 3, blending: THREE.AdditiveBlending, depthWrite: false
    });
    var arc = new THREE.Line(geo, mat);
    group.add(arc);

    // Glow trail
    var glowMat = new THREE.LineBasicMaterial({
      color: 0xffffff, transparent: true, opacity: 0.4,
      linewidth: 5, blending: THREE.AdditiveBlending, depthWrite: false
    });
    var glowArc = new THREE.Line(geo.clone(), glowMat);
    glowArc.scale.setScalar(1.05);
    group.add(glowArc);

    scene.add(group);

    var vfx = { group: group, alive: true };
    var t0 = performance.now();
    function tick() {
      if (!vfx.alive) return;
      var p = Math.min((performance.now() - t0) / duration, 1);
      var rotSpeed = Math.PI * 1.5;
      group.rotation.z = p * rotSpeed;
      arc.material.opacity = 1 - p;
      glowMat.opacity = 0.4 * (1 - p);
      group.scale.setScalar(1 + p * 0.5);
      if (p >= 1) { cleanup(vfx); return; }
      requestAnimationFrame(tick);
    }
    tick();
    _active.push(vfx);
    return vfx;
  }

  /* ══════════════════════════════════════════════════════════════════
     5. RADIATION SPHERE — pulsing toxic cloud
     ══════════════════════════════════════════════════════════════════ */
  function spawnRadiation(center, opts) {
    var scene = getScene();
    if (!scene || !center) return null;
    var o = opts || {};
    var color = o.color || 0x44ff00;
    var duration = o.duration || 800;

    var group = new THREE.Group();
    group.position.copy(center);
    group.position.y += 1.0;

    // Inner toxic core
    var coreGeo = new THREE.SphereGeometry(0.4, 16, 16);
    var coreMat = new THREE.MeshBasicMaterial({
      color: color, transparent: true, opacity: 0.5,
      blending: THREE.AdditiveBlending, depthWrite: false
    });
    var core = new THREE.Mesh(coreGeo, coreMat);
    group.add(core);

    // Outer cloud rings
    for (var r = 0; r < 3; r++) {
      var ringGeo = new THREE.TorusGeometry(0.5 + r * 0.25, 0.04, 8, 24);
      var ringMat = new THREE.MeshBasicMaterial({
        color: color, transparent: true, opacity: 0.25,
        blending: THREE.AdditiveBlending, depthWrite: false
      });
      var ring = new THREE.Mesh(ringGeo, ringMat);
      ring.rotation.x = Math.random() * Math.PI;
      ring.rotation.y = Math.random() * Math.PI;
      ring.userData._ringSpeed = 1 + Math.random() * 2;
      group.add(ring);
    }

    scene.add(group);

    var vfx = { group: group, alive: true };
    var t0 = performance.now();
    function tick() {
      if (!vfx.alive) return;
      var elapsed = performance.now() - t0;
      var p = Math.min(elapsed / duration, 1);
      // Pulse core
      var pulse = 1 + Math.sin(elapsed * 0.01) * 0.2;
      core.scale.setScalar(pulse * (1 + p * 0.5));
      core.material.opacity = 0.5 * (1 - p * 0.7);
      // Rotate rings
      group.children.forEach(function (child) {
        if (child !== core && child.userData._ringSpeed) {
          child.rotation.z += 0.02 * child.userData._ringSpeed;
          child.material.opacity = 0.25 * (1 - p);
        }
      });
      // Expand
      group.scale.setScalar(1 + p * 0.8);
      if (p >= 1) { cleanup(vfx); return; }
      requestAnimationFrame(tick);
    }
    tick();
    _active.push(vfx);
    return vfx;
  }

  /* ══════════════════════════════════════════════════════════════════
     6. HEAL SPIRAL — rising green rings
     ══════════════════════════════════════════════════════════════════ */
  function spawnHealSpiral(center, opts) {
    var scene = getScene();
    if (!scene || !center) return null;
    var o = opts || {};
    var color = o.color || 0x00ff88;
    var duration = o.duration || 900;

    var group = new THREE.Group();
    group.position.set(center.x, 0.1, center.z);

    // Create spiral rings at different heights
    var rings = [];
    for (var i = 0; i < 5; i++) {
      var rGeo = new THREE.TorusGeometry(0.35 + i * 0.08, 0.015, 8, 32);
      var rMat = new THREE.MeshBasicMaterial({
        color: color, transparent: true, opacity: 0,
        blending: THREE.AdditiveBlending, depthWrite: false
      });
      var rMesh = new THREE.Mesh(rGeo, rMat);
      rMesh.rotation.x = Math.PI / 2;
      rMesh.position.y = 0;
      rMesh.userData._delay = i * 0.12;
      rMesh.userData._targetY = 0.3 + i * 0.35;
      group.add(rMesh);
      rings.push(rMesh);
    }

    // Center column glow
    var colGeo = new THREE.CylinderGeometry(0.08, 0.08, 2.2, 8);
    var colMat = new THREE.MeshBasicMaterial({
      color: color, transparent: true, opacity: 0,
      blending: THREE.AdditiveBlending, depthWrite: false
    });
    var col = new THREE.Mesh(colGeo, colMat);
    col.position.y = 1.1;
    group.add(col);

    scene.add(group);

    var vfx = { group: group, alive: true };
    var t0 = performance.now();
    function tick() {
      if (!vfx.alive) return;
      var elapsed = (performance.now() - t0) / 1000;
      var p = Math.min(elapsed / (duration / 1000), 1);

      rings.forEach(function (r) {
        var rp = Math.max(0, Math.min(1, (p - r.userData._delay) / 0.5));
        r.position.y = rp * r.userData._targetY;
        r.material.opacity = rp < 0.5 ? rp * 2 * 0.5 : (1 - rp) * 0.5;
        r.rotation.z = elapsed * 3 + r.userData._delay * 10;
      });

      col.material.opacity = p < 0.3 ? p / 0.3 * 0.2 : (1 - p) * 0.2;

      if (p >= 1) { cleanup(vfx); return; }
      requestAnimationFrame(tick);
    }
    tick();
    _active.push(vfx);
    return vfx;
  }

  /* ══════════════════════════════════════════════════════════════════
     7. SHIELD DOME — translucent hemisphere
     ══════════════════════════════════════════════════════════════════ */
  function spawnShieldDome(center, opts) {
    var scene = getScene();
    if (!scene || !center) return null;
    var o = opts || {};
    var color = o.color || 0x00ccff;
    var duration = o.duration || 800;

    var group = new THREE.Group();
    group.position.set(center.x, 0, center.z);

    var domeGeo = new THREE.SphereGeometry(0.7, 24, 16, 0, Math.PI * 2, 0, Math.PI / 2);
    var domeMat = new THREE.MeshBasicMaterial({
      color: color, transparent: true, opacity: 0,
      side: THREE.DoubleSide, blending: THREE.AdditiveBlending, depthWrite: false
    });
    var dome = new THREE.Mesh(domeGeo, domeMat);
    group.add(dome);

    // Hex wireframe overlay
    var wireGeo = new THREE.SphereGeometry(0.72, 12, 8, 0, Math.PI * 2, 0, Math.PI / 2);
    var wireMat = new THREE.MeshBasicMaterial({
      color: 0xffffff, transparent: true, opacity: 0, wireframe: true,
      blending: THREE.AdditiveBlending, depthWrite: false
    });
    var wire = new THREE.Mesh(wireGeo, wireMat);
    group.add(wire);

    scene.add(group);

    var vfx = { group: group, alive: true };
    var t0 = performance.now();
    function tick() {
      if (!vfx.alive) return;
      var p = Math.min((performance.now() - t0) / duration, 1);
      var fadeIn = p < 0.2 ? p / 0.2 : 1;
      var fadeOut = p > 0.7 ? (1 - p) / 0.3 : 1;
      var alpha = fadeIn * fadeOut;
      dome.material.opacity = alpha * 0.2;
      wire.material.opacity = alpha * 0.15;
      wire.rotation.y += 0.02;
      dome.scale.setScalar(0.8 + alpha * 0.2);
      if (p >= 1) { cleanup(vfx); return; }
      requestAnimationFrame(tick);
    }
    tick();
    _active.push(vfx);
    return vfx;
  }

  /* ══════════════════════════════════════════════════════════════════
     HIGH-LEVEL API — map ability names to VFX combinations
     ══════════════════════════════════════════════════════════════════ */

  /**
   * Play VFX for an attack/ability. Call from arena-actions.js.
   * @param {string} type — 'attack' | 'ability' | 'special' | 'heal' | 'defense'
   * @param {string} skillCode — lowercase_underscore name (e.g. 'lightning_conductor')
   * @param {THREE.Vector3} fromPos — caster world position
   * @param {THREE.Vector3} toPos — target world position (or center for AOE)
   * @param {object} opts — { isAoE, targets: [{pos}] }
   */
  function playVFX(type, skillCode, fromPos, toPos, opts) {
    var o = opts || {};
    var code = (skillCode || '').toLowerCase();

    // Derive from position vectors (clone to avoid mutation)
    var from = fromPos ? new THREE.Vector3(fromPos.x, fromPos.y + 1.1, fromPos.z) : null;
    var to = toPos ? new THREE.Vector3(toPos.x, toPos.y + 1.1, toPos.z) : null;

    // ── ATTACK (basic strike) ──
    if (type === 'attack') {
      if (to) spawnSlash(toPos, { color: 0xff4444, duration: 280 });
      return;
    }

    // ── DEFENSE ──
    if (type === 'defense') {
      if (fromPos) spawnShieldDome(fromPos, { color: 0x00ccff, duration: 900 });
      return;
    }

    // ── HEAL ──
    if (type === 'heal') {
      var healTarget = to || from;
      if (healTarget) spawnHealSpiral(toPos || fromPos, { color: 0x00ff88, duration: 1000 });
      return;
    }

    // ── ABILITY — mapped by skill code ──
    if (type === 'ability') {
      // Electric abilities
      if (code.indexOf('lightning') >= 0 || code.indexOf('conductor') >= 0 ||
          code.indexOf('spark') >= 0 || code.indexOf('storm') >= 0 ||
          code.indexOf('field_charge') >= 0) {
        if (from && to) spawnLightning(from, to, { color: 0x00ccff, duration: 400 });
        if (to) spawnShockwave(toPos, { color: 0x00aaff, maxRadius: 1.5, duration: 350 });
        return;
      }
      // Radiation / Nuclear
      if (code.indexOf('isotope') >= 0 || code.indexOf('radiation') >= 0 ||
          code.indexOf('nuclear') >= 0 || code.indexOf('radium') >= 0) {
        if (from && to) spawnProjectile(from, to, { color: 0x44ff00, speed: 500 });
        if (to) spawnRadiation(toPos, { color: 0x44ff00, duration: 700 });
        return;
      }
      // Blood / Vampire
      if (code.indexOf('crimson') >= 0 || code.indexOf('blood') >= 0 ||
          code.indexOf('bite') >= 0 || code.indexOf('dark') >= 0) {
        if (to) spawnSlash(toPos, { color: 0xcc0022, radius: 1.0, duration: 250 });
        if (from && to) spawnProjectile(from, to, { color: 0xff0033, size: 0.12, speed: 400 });
        return;
      }
      // Stone / Petrify
      if (code.indexOf('petrif') >= 0 || code.indexOf('stone') >= 0 ||
          code.indexOf('gaze') >= 0) {
        if (from && to) spawnProjectile(from, to, { color: 0x8866aa, speed: 550 });
        if (to) spawnShieldDome(toPos, { color: 0x664488, duration: 600 });
        return;
      }
      // Water / Abyssal
      if (code.indexOf('abyssal') >= 0 || code.indexOf('leviathan') >= 0 ||
          code.indexOf('nile') >= 0 || code.indexOf('deep') >= 0 ||
          code.indexOf('kraken') >= 0) {
        if (from && to) spawnProjectile(from, to, { color: 0x0066cc, size: 0.22, speed: 500 });
        if (to) spawnShockwave(toPos, { color: 0x0088ff, maxRadius: 2, duration: 500 });
        return;
      }
      // Pharaoh / Golden
      if (code.indexOf('pharaoh') >= 0 || code.indexOf('golden') >= 0 ||
          code.indexOf('royal') >= 0 || code.indexOf('decree') >= 0) {
        if (from && to) spawnProjectile(from, to, { color: 0xffcc00, speed: 450 });
        return;
      }
      // Default ability: purple projectile
      if (from && to) spawnProjectile(from, to, { color: 0xaa44ff, speed: 500 });
      return;
    }

    // ── SPECIAL — big dramatic effects ──
    if (type === 'special') {
      // Electric specials
      if (code.indexOf('storm') >= 0 || code.indexOf('lightning') >= 0) {
        if (o.isAoE && o.targets) {
          o.targets.forEach(function (tgt) {
            if (from) spawnLightning(from, new THREE.Vector3(tgt.pos.x, tgt.pos.y + 1.1, tgt.pos.z), { color: 0x00ccff, duration: 500 });
          });
        } else if (from && to) {
          spawnLightning(from, to, { color: 0x00ccff, segments: 16, duration: 500 });
        }
        if (toPos) spawnShockwave(toPos, { color: 0x00ccff, maxRadius: 5, duration: 700 });
        return;
      }
      // Nuclear
      if (code.indexOf('nuclear') >= 0 || code.indexOf('meltdown') >= 0) {
        if (to) spawnRadiation(toPos, { color: 0x88ff00, duration: 1000 });
        if (toPos) spawnShockwave(toPos, { color: 0x44ff00, maxRadius: 5, duration: 800 });
        return;
      }
      // Night / Blood AOE
      if (code.indexOf('night') >= 0 || code.indexOf('domination') >= 0) {
        if (toPos) spawnShockwave(toPos, { color: 0xcc0033, maxRadius: 5, duration: 700 });
        if (o.isAoE && o.targets) {
          o.targets.forEach(function (tgt) {
            spawnSlash(tgt.pos, { color: 0xff0033, duration: 300 });
          });
        }
        return;
      }
      // Leviathan / Water AOE
      if (code.indexOf('leviathan') >= 0 || code.indexOf('crush') >= 0 ||
          code.indexOf('flood') >= 0) {
        if (toPos) spawnShockwave(toPos, { color: 0x0066ff, maxRadius: 6, duration: 800 });
        return;
      }
      // Default special: golden shockwave
      if (toPos) spawnShockwave(toPos, { color: 0xffcc00, maxRadius: 5, duration: 700 });
      return;
    }
  }

  /** Cleanup all active VFX */
  function clearAll() {
    _active.forEach(function (v) { cleanup(v); });
    _active = [];
  }

  window.MWArenaVFX = {
    playVFX: playVFX,
    spawnLightning: spawnLightning,
    spawnProjectile: spawnProjectile,
    spawnShockwave: spawnShockwave,
    spawnSlash: spawnSlash,
    spawnRadiation: spawnRadiation,
    spawnHealSpiral: spawnHealSpiral,
    spawnShieldDome: spawnShieldDome,
    clearAll: clearAll
  };
})();
