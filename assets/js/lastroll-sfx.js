/**
 * KND LastRoll — SFX Engine (Web Audio API)
 * Synthesized sounds: dice roll, impact, danger, victory, defeat, tick.
 */
(function () {
  'use strict';
  var ctx = null, sfxGain = null;

  function ensure() {
    if (ctx) return true;
    try {
      ctx = new (window.AudioContext || window.webkitAudioContext)();
      sfxGain = ctx.createGain();
      sfxGain.gain.value = 0.5;
      sfxGain.connect(ctx.destination);
      return true;
    } catch (e) { return false; }
  }

  function unlock() { if (ensure() && ctx.state === 'suspended') ctx.resume(); }

  /* ── Dice Roll (rattling clicks) ── */
  function playRollTick() {
    if (!ensure()) return;
    var t = ctx.currentTime;
    var osc = ctx.createOscillator();
    osc.type = 'square';
    osc.frequency.setValueAtTime(800 + Math.random() * 400, t);
    osc.frequency.exponentialRampToValueAtTime(200, t + 0.03);
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.08, t);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.04);
    osc.connect(g); g.connect(sfxGain);
    osc.start(t); osc.stop(t + 0.05);
  }

  /* ── Dice Land (impact thud) ── */
  function playLand(critical) {
    if (!ensure()) return;
    var t = ctx.currentTime;
    var vol = critical ? 0.6 : 0.35;
    // Thud
    var osc = ctx.createOscillator();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(critical ? 80 : 150, t);
    osc.frequency.exponentialRampToValueAtTime(30, t + 0.12);
    var g = ctx.createGain();
    g.gain.setValueAtTime(vol, t);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.15);
    osc.connect(g); g.connect(sfxGain);
    osc.start(t); osc.stop(t + 0.18);
    // Click
    var osc2 = ctx.createOscillator();
    osc2.type = 'triangle';
    osc2.frequency.setValueAtTime(critical ? 200 : 500, t);
    osc2.frequency.exponentialRampToValueAtTime(critical ? 50 : 200, t + 0.06);
    var g2 = ctx.createGain();
    g2.gain.setValueAtTime(0.15, t);
    g2.gain.exponentialRampToValueAtTime(0.001, t + 0.08);
    osc2.connect(g2); g2.connect(sfxGain);
    osc2.start(t); osc2.stop(t + 0.1);
  }

  /* ── Danger Warning (low alarm) ── */
  function playDanger() {
    if (!ensure()) return;
    var t = ctx.currentTime;
    var osc = ctx.createOscillator();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(120, t);
    osc.frequency.linearRampToValueAtTime(180, t + 0.15);
    osc.frequency.linearRampToValueAtTime(120, t + 0.3);
    var filt = ctx.createBiquadFilter();
    filt.type = 'lowpass'; filt.frequency.value = 400;
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.12, t);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.35);
    osc.connect(filt); filt.connect(g); g.connect(sfxGain);
    osc.start(t); osc.stop(t + 0.4);
  }

  /* ── Timer Tick ── */
  function playTimerTick(urgent) {
    if (!ensure()) return;
    var t = ctx.currentTime;
    var osc = ctx.createOscillator();
    osc.type = 'sine';
    osc.frequency.value = urgent ? 900 : 600;
    var g = ctx.createGain();
    g.gain.setValueAtTime(urgent ? 0.1 : 0.05, t);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.05);
    osc.connect(g); g.connect(sfxGain);
    osc.start(t); osc.stop(t + 0.06);
  }

  /* ── Victory ── */
  function playVictory() {
    if (!ensure()) return;
    var t = ctx.currentTime;
    [523, 659, 784, 1047, 1319].forEach(function (f, i) {
      var osc = ctx.createOscillator();
      osc.type = 'triangle';
      osc.frequency.value = f;
      var g = ctx.createGain();
      var s = t + i * 0.1;
      g.gain.setValueAtTime(0.0, s);
      g.gain.linearRampToValueAtTime(0.15, s + 0.04);
      g.gain.exponentialRampToValueAtTime(0.001, s + 0.4);
      osc.connect(g); g.connect(sfxGain);
      osc.start(s); osc.stop(s + 0.45);
    });
  }

  /* ── Defeat ── */
  function playDefeat() {
    if (!ensure()) return;
    var t = ctx.currentTime;
    [400, 320, 250, 180].forEach(function (f, i) {
      var osc = ctx.createOscillator();
      osc.type = 'sawtooth';
      osc.frequency.value = f;
      var filt = ctx.createBiquadFilter();
      filt.type = 'lowpass'; filt.frequency.value = 500;
      var g = ctx.createGain();
      var s = t + i * 0.18;
      g.gain.setValueAtTime(0.0, s);
      g.gain.linearRampToValueAtTime(0.1, s + 0.04);
      g.gain.exponentialRampToValueAtTime(0.001, s + 0.4);
      osc.connect(filt); filt.connect(g); g.connect(sfxGain);
      osc.start(s); osc.stop(s + 0.45);
    });
  }

  /* ── UI Click ── */
  function playClick() {
    if (!ensure()) return;
    var t = ctx.currentTime;
    var osc = ctx.createOscillator();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(700, t);
    osc.frequency.exponentialRampToValueAtTime(1000, t + 0.04);
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.06, t);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.06);
    osc.connect(g); g.connect(sfxGain);
    osc.start(t); osc.stop(t + 0.08);
  }

  /* Auto-unlock */
  ['click','touchstart'].forEach(function(e){
    document.addEventListener(e, unlock, { once: true });
  });

  window.LastRollSFX = {
    unlock: unlock,
    playRollTick: playRollTick,
    playLand: playLand,
    playDanger: playDanger,
    playTimerTick: playTimerTick,
    playVictory: playVictory,
    playDefeat: playDefeat,
    playClick: playClick
  };
})();
