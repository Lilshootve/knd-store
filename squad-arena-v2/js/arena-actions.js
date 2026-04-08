/**
 * Mind Wars Arena — Action Handlers v2
 * Hitstop, hit reactions, SFX, improved VFX, cinematic camera
 */
var animating = false;
function endAnimation() {
  if (window.MWArenaThree && MWArenaThree.restoreHolos) MWArenaThree.restoreHolos();
  animating = false;
}
function beginAnimation() {
  animating = true;
  if (window.MWArenaThree && MWArenaThree.stabilizeHolos) MWArenaThree.stabilizeHolos();
}
var SFX = null;
function sfx() { return SFX || (SFX = window.MWArenaSFX) || null; }

function hitstop(ms) { return new Promise(function (r) { setTimeout(r, ms || 60); }); }

function hitReaction(target, intensity) {
  if (!target) return;
  var ox = target.position.x;
  var d = (intensity || 1) * 0.15;
  var t0 = performance.now();
  function shake() {
    var p = (performance.now() - t0) / 180;
    if (p >= 1) { target.position.x = ox; return; }
    target.position.x = ox + (Math.random() - 0.5) * d * 2 * (1 - p);
    requestAnimationFrame(shake);
  }
  shake();
  if (target.userData && target.userData.holoUniforms && target.userData.holoUniforms.length) {
    target.userData.holoUniforms.forEach(function (uni) {
      var orig = uni.uOpacity.value;
      uni.uOpacity.value = Math.min(2.0, orig * 2.5);
      uni.uColor.value.set(2, 2, 3);
      setTimeout(function () {
        uni.uOpacity.value = orig;
        var isE = target.userData && target.userData.isEnemy;
        uni.uColor.value.set(isE ? 0.55 : 0, isE ? 0.27 : 0.61, isE ? 3.35 : 3);
      }, 100);
    });
  }
}

function knockback(target, fx, fz, dist, dur) {
  if (!target) return Promise.resolve();
  var dx = target.position.x - fx, dz = target.position.z - fz;
  var len = Math.sqrt(dx * dx + dz * dz) || 1;
  dx /= len; dz /= len;
  var bx = target.userData.basePos ? target.userData.basePos.x : target.position.x;
  var bz = target.userData.basePos ? target.userData.basePos.z : target.position.z;
  return new Promise(function (resolve) {
    var t0 = performance.now();
    function step() {
      var p = Math.min((performance.now() - t0) / (dur || 200), 1);
      var e = p < 0.3 ? p / 0.3 : 1 - (p - 0.3) / 0.7;
      target.position.x = bx + dx * dist * e;
      target.position.z = bz + dz * dist * e;
      if (p < 1) requestAnimationFrame(step);
      else { target.position.x = bx; target.position.z = bz; resolve(); }
    }
    step();
  });
}

function executeAttack(actorSlot, targetSlot, damage, side, context, roll, opts) {
  opts = opts || {};
  var skipHp = !!opts.skipHpApply;
  return new Promise(function (resolve) {
    if (animating) { resolve(); return; }
    beginAnimation();
    var au = side === 'player' ? context.player : context.enemy;
    var tu = side === 'enemy' ? context.player : context.enemy;
    var a = au[actorSlot], t = tu[targetSlot];
    if (!a || !t || a.userData.isDead || t.userData.isDead) { endAnimation(); resolve(); return; }
    var an = a.userData.name || 'Unit', tsl = side === 'player' ? 'enemy' : 'player';
    context.addLog(an + ' strikes ' + (t.userData.name || 'Unit'), side + '-log');
    context.setCinematicBars(true);
    var mx = (a.position.x + t.position.x) * 0.5, mz = (a.position.z + t.position.z) * 0.5;
    context.lerpCam(mx * 0.6, 2.5, side === 'player' ? mz + 4.5 : mz - 4.5, mx, 1.0, mz, 400, function () {
      context.showAbilityCaption('STRIKE', 'ATTACK // ' + an);
      var bx = a.userData.basePos.x, bz = a.userData.basePos.z, tp = t.position.clone();
      var t0 = performance.now();
      function rush() {
        var p = Math.min((performance.now() - t0) / 180, 1);
        var e = p < 0.5 ? 4 * p * p * p : 1;
        a.position.x = bx + (tp.x - bx) * e * 0.6;
        a.position.z = bz + (tp.z - bz) * e * 0.6;
        if (p < 1) { requestAnimationFrame(rush); return; }
        if (roll && roll.crit && sfx()) sfx().playCrit();
        else if (sfx()) sfx().playAttack ? sfx().playAttack() : sfx().playHit(0.8);
        if (window.MWArenaVFX) MWArenaVFX.playVFX('attack', 'strike', a.position, t.position);
        context.spawnParticles(tp.x, 1.2, tp.z, 0xff4400, 50);
        context.spawnParticles(tp.x, 1.5, tp.z, 0xffaa00, 25);
        context.flashScreen('rgba(255,100,20,0.5)', 0.12);
        context.shakeCanvas(6, 300);
        hitReaction(t, 1.0);
        /* Delay damage number — impact registers visually first */
        setTimeout(function () {
          context.showDmgNumber('-' + damage, '#ff4444');
          if (!skipHp) context.updateHP(tsl, targetSlot, damage);
        }, 25);
        hitstop(65).then(function () {
          knockback(t, a.position.x, a.position.z, 0.25, 200);
          setTimeout(function () {
            var t1 = performance.now();
            function ret() {
              var p2 = Math.min((performance.now() - t1) / 250, 1);
              var e2 = 1 - Math.pow(1 - p2, 3);
              a.position.x = bx + (tp.x - bx) * 0.6 * (1 - e2);
              a.position.z = bz + (tp.z - bz) * 0.6 * (1 - e2);
              if (p2 < 1) { requestAnimationFrame(ret); return; }
              a.position.x = bx; a.position.z = bz;
              context.setCinematicBars(false);
              context.resetCamera(400);
              setTimeout(function () { endAnimation(); resolve(); }, 350);
            }
            ret();
          }, 250);
        });
      }
      setTimeout(rush, 150);
    });
  });
}

function executeAbility(actorSlot, targetSlot, skillCode, damage, effects, side, context, opts) {
  opts = opts || {};
  var skipHp = !!opts.skipHpApply;
  return new Promise(function (resolve) {
    if (animating) { resolve(); return; }
    beginAnimation();
    var au = side === 'player' ? context.player : context.enemy;
    var tu = side === 'enemy' ? context.player : context.enemy;
    var a = au[actorSlot], t = tu[targetSlot];
    if (!a || !t || a.userData.isDead || t.userData.isDead) { endAnimation(); resolve(); return; }
    var an = a.userData.name || 'Unit', tsl = side === 'player' ? 'enemy' : 'player';
    var sn = skillCode ? skillCode.replace(/_/g, ' ').toUpperCase() : 'ABILITY';
    context.addLog(an + ' — ' + sn, side + '-log');
    context.setCinematicBars(true);
    if (sfx()) sfx().playAbility();
    context.lerpCam(a.position.x * 0.5, 3.0, side === 'player' ? 6 : -6, 0, 1.2, 0, 400, function () {
      context.showAbilityCaption(sn, 'ABILITY // ' + an);
      if (a.userData.holoUniforms) a.userData.holoUniforms.forEach(function (u) {
        var o = u.uOpacity.value; u.uOpacity.value = Math.min(1.8, o * 2);
        setTimeout(function () { u.uOpacity.value = o; }, 300);
      });
      setTimeout(function () {
        var tp = t.position;
        if (window.MWArenaVFX) MWArenaVFX.playVFX('ability', skillCode, a.position, t.position);
        context.spawnParticles(tp.x, 1.8, tp.z, 0xaa44ff, 60);
        context.spawnParticles(tp.x, 1.0, tp.z, 0x7700cc, 35);
        context.flashScreen('rgba(150,0,255,0.35)', 0.18);
        context.shakeCanvas(4, 300);
        if (sfx()) sfx().playHit(0.6);
        hitReaction(t, 0.8);
        setTimeout(function () {
          if (damage > 0) {
            context.showDmgNumber('-' + damage, '#aa44ff');
            if (!skipHp) context.updateHP(tsl, targetSlot, damage);
          }
        }, 25);
        hitstop(50).then(function () {
          knockback(t, a.position.x, a.position.z, 0.18, 180);
          setTimeout(function () {
            context.setCinematicBars(false); context.resetCamera(400);
            setTimeout(function () { endAnimation(); resolve(); }, 400);
          }, 350);
        });
      }, 350);
    });
  });
}

function executeSpecial(actorSlot, targetSlot, skillCode, damage, isAoE, aoeTargets, side, context, opts) {
  opts = opts || {};
  var skipHp = !!opts.skipHpApply;
  return new Promise(function (resolve) {
    if (animating) { resolve(); return; }
    beginAnimation();
    var au = side === 'player' ? context.player : context.enemy;
    var tu = side === 'enemy' ? context.player : context.enemy;
    var a = au[actorSlot];
    if (!a || a.userData.isDead) { endAnimation(); resolve(); return; }
    var an = a.userData.name || 'Unit';
    var sn = skillCode ? skillCode.replace(/_/g, ' ').toUpperCase() : 'SPECIAL';
    context.addLog(an + ' — ' + sn + (isAoE ? ' [AOE]' : ''), side + '-log');
    context.setCinematicBars(true);
    if (sfx()) sfx().playSpecial();
    context.lerpCam(a.position.x * 0.3, 3.5, side === 'player' ? 6.5 : -6.5, a.position.x, 1.5, a.position.z, 500, function () {
      context.showAbilityCaption(sn, 'SPECIAL // ' + an);
      var t0 = performance.now();
      function rise() {
        var p = Math.min((performance.now() - t0) / 500, 1);
        var e = 1 - Math.pow(1 - p, 3);
        a.position.y = e * 1.8;
        if (a.userData.holoUniforms) a.userData.holoUniforms.forEach(function (u) { u.uOpacity.value = 0.7 + e * 0.8; });
        if (p < 1) { requestAnimationFrame(rise); return; }
        context.lerpCam(0, 5.5, side === 'player' ? 8 : -8, 0, 0.5, 0, 300, function () {
          context.flashScreen('rgba(255,230,0,0.7)', 0.25);
          context.shakeCanvas(12, 500);
          var tsl = side === 'player' ? 'enemy' : 'player';
          /* VFX: build target positions for AOE */
          var vfxTargets = [];
          if (isAoE && aoeTargets) {
            aoeTargets.forEach(function (at) {
              var eu = tu[at.slot];
              if (eu && !eu.userData.isDead) vfxTargets.push({ pos: eu.position });
            });
          }
          var vfxCenter = (isAoE && tu[0]) ? tu[0].position : (tu[targetSlot] ? tu[targetSlot].position : a.position);
          if (window.MWArenaVFX) MWArenaVFX.playVFX('special', sn.toLowerCase().replace(/ /g,'_'), a.position, vfxCenter, { isAoE: isAoE, targets: vfxTargets });
          if (isAoE && aoeTargets) {
            aoeTargets.forEach(function (at) {
              var eu = tu[at.slot];
              if (eu && !eu.userData.isDead) {
                context.spawnParticles(eu.position.x, 1.5, eu.position.z, 0xffcc00, 55);
                context.spawnParticles(eu.position.x, 0.8, eu.position.z, 0xff6600, 30);
                hitReaction(eu, 1.2);
              }
            });
          } else {
            var tg = tu[targetSlot];
            if (tg && !tg.userData.isDead) {
              context.spawnParticles(tg.position.x, 1.5, tg.position.z, 0xffcc00, 65);
              hitReaction(tg, 1.2);
            }
          }
          if (sfx()) sfx().playHit(1.0);
          /* Delay damage numbers — explosion registers first */
          setTimeout(function () {
            context.showDmgNumber('-' + damage, '#ffcc00');
            if (!skipHp) {
              if (isAoE && aoeTargets) {
                aoeTargets.forEach(function (at) { if (at.damage > 0) context.updateHP(tsl, at.slot, at.damage); });
              } else {
                context.updateHP(tsl, targetSlot, damage);
              }
            }
          }, 30);
          hitstop(80).then(function () {
            if (a.userData.holoUniforms) a.userData.holoUniforms.forEach(function (u) { u.uOpacity.value = 0.7; });
            var t1 = performance.now();
            function fall() {
              var p2 = Math.min((performance.now() - t1) / 350, 1);
              a.position.y = (1 - p2) * 1.8;
              if (p2 < 1) { requestAnimationFrame(fall); return; }
              a.position.y = 0;
              context.setCinematicBars(false); context.resetCamera(500);
              setTimeout(function () { endAnimation(); resolve(); }, 400);
            }
            fall();
          });
        });
      }
      rise();
    });
  });
}

function executeDefend(actorSlot, side, context) {
  return new Promise(function (resolve) {
    if (animating) { resolve(); return; }
    beginAnimation();
    var au = side === 'player' ? context.player : context.enemy;
    var u = au[actorSlot];
    if (!u || u.userData.isDead) { endAnimation(); resolve(); return; }
    if (sfx()) sfx().playDefend();
    if (window.MWArenaVFX) MWArenaVFX.playVFX('defense', 'defend', u.position, u.position);
    var ring = u.userData.ring;
    if (ring) {
      var oc = ring.material.color.clone();
      ring.material.color.setHex(0x00ddff); ring.material.opacity = 0.9;
      if (u.userData.holoUniforms) u.userData.holoUniforms.forEach(function (uni) {
        var o = uni.uOpacity.value;
        uni.uOpacity.value = Math.min(1.5, o * 1.8);
        uni.uColor.value.set(0.2, 1.5, 3);
        setTimeout(function () { uni.uOpacity.value = o; var isE = u.userData.isEnemy; uni.uColor.value.set(isE ? 0.55 : 0, isE ? 0.27 : 0.61, isE ? 3.35 : 3); }, 600);
      });
      setTimeout(function () { ring.material.color = oc; ring.material.opacity = 0.4; endAnimation(); resolve(); }, 700);
    } else { endAnimation(); resolve(); }
  });
}

function executeUnitDeath(slot, side, context) {
  return new Promise(function (resolve) {
    var slotN = slot == null ? -1 : Number(slot);
    if (slotN < 0 || slotN > 2) { resolve(); return; }
    var waitStart = Date.now();
    function enter() {
      /* No devolver sin efecto si aún corre un ataque: antes se perdía toda la muerte en playback servidor. */
      if (animating && Date.now() - waitStart < 5000) {
        setTimeout(enter, 24);
        return;
      }
      var units = side === 'player' ? context.player : context.enemy;
      var t = units[slotN];
      if (!t || t.userData.isDead) { resolve(); return; }
      beginAnimation();
      if (sfx()) sfx().playDeath();
      context.setCinematicBars(true);
      context.lerpCam(t.position.x * 0.4, 2.2, side === 'player' ? t.position.z + 3 : t.position.z - 3, t.position.x, 0.8, t.position.z, 350, function () {
      context.spawnParticles(t.position.x, 1.5, t.position.z, 0x4488ff, 90);
      context.spawnParticles(t.position.x, 0.8, t.position.z, 0xffffff, 50);
      context.flashScreen('rgba(255,255,255,0.6)', 0.3);
      context.shakeCanvas(5, 350);
      context.showDmgNumber('DEFEATED', '#ffffff');
      var t0 = performance.now();
      function dissolve() {
        var p = Math.min((performance.now() - t0) / 700, 1);
        var s = 1 - p * p;
        t.scale.set(s, s, s); t.position.y = p * p * (-0.3);
        if (t.userData.holoUniforms) t.userData.holoUniforms.forEach(function (u) {
          u.uOpacity.value = Math.max(0, 0.7 * (1 - p));
          u.uFlickerIntensity.value = p * 0.5;
        });
        if (p < 1) { requestAnimationFrame(dissolve); return; }
        t.userData.isDead = true; t.scale.set(0.001, 0.001, 0.001);
        context.setCinematicBars(false); context.resetCamera(500);
        setTimeout(function () { endAnimation(); resolve(); }, 400);
      }
      dissolve();
    });
    }
    enter();
  });
}


function executeHealBetween(healerSlot, targetSlot, healAmount, side, context) {
  return new Promise(function (resolve) {
    if (animating) { resolve(); return; }
    beginAnimation();
    var au = side === 'player' ? context.player : context.enemy;
    var healer = au[healerSlot];
    var target = au[targetSlot];
    if (!target || target.userData.isDead) { endAnimation(); resolve(); return; }
    if (sfx()) sfx().playHeal();
    var tx = target.position.x, tz = target.position.z;
    context.lerpCam(tx * 0.45, 2.85, side === 'player' ? tz + 5.2 : tz - 5.2, tx, 1.15, tz, 380, function () {});
    if (window.MWArenaVFX) MWArenaVFX.playVFX('heal', 'heal', healer ? healer.position : target.position, target.position);
    context.spawnParticles(tx, 0.5, tz, 0x00ff88, 55);
    context.spawnParticles(tx, 1.5, tz, 0x88ffaa, 30);
    context.flashScreen('rgba(0,255,136,0.15)', 0.25);
    context.showDmgNumber('+' + healAmount, '#00ff88');
    if (target.userData.holoUniforms) target.userData.holoUniforms.forEach(function (uni) {
      var o = uni.uOpacity.value;
      uni.uColor.value.set(0, 2, 1); uni.uOpacity.value = Math.min(1.5, o * 1.6);
      setTimeout(function () { uni.uOpacity.value = o; var isE = target.userData.isEnemy; uni.uColor.value.set(isE ? 0.55 : 0, isE ? 0.27 : 0.61, isE ? 3.35 : 3); }, 500);
    });
    setTimeout(function () {
      context.resetCamera(420);
      setTimeout(function () { endAnimation(); resolve(); }, 220);
    }, 720);
  });
}

function executeHeal(actorSlot, healAmount, side, context) {
  return executeHealBetween(actorSlot, actorSlot, healAmount, side, context);
}
