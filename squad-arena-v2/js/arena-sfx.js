/**
 * Mind Wars Arena — SFX Engine (Web Audio API, zero external files)
 * Synthesized sound effects for battle impacts, abilities, UI, and music.
 */
(function () {
  'use strict';

  var ctx = null;
  var masterGain = null;
  var musicGain = null;
  var sfxGain = null;
  var _ready = false;
  var _musicPlaying = false;
  var _musicNodes = [];

  function ensureCtx() {
    if (ctx) return true;
    try {
      ctx = new (window.AudioContext || window.webkitAudioContext)();
      masterGain = ctx.createGain();
      masterGain.gain.value = 0.6;
      masterGain.connect(ctx.destination);
      sfxGain = ctx.createGain();
      sfxGain.gain.value = 0.7;
      sfxGain.connect(masterGain);
      musicGain = ctx.createGain();
      musicGain.gain.value = 0.18;
      musicGain.connect(masterGain);
      _ready = true;
      return true;
    } catch (e) {
      return false;
    }
  }

  /** Resume audio context on first user interaction */
  function unlock() {
    if (!ensureCtx()) return;
    if (ctx.state === 'suspended') ctx.resume();
  }

  /* ─── NOISE BUFFER ─── */
  var _noiseBuf = null;
  function getNoiseBuffer(dur) {
    if (!ctx) return null;
    var len = Math.floor((dur || 0.5) * ctx.sampleRate);
    if (_noiseBuf && _noiseBuf.length >= len) return _noiseBuf;
    _noiseBuf = ctx.createBuffer(1, len, ctx.sampleRate);
    var data = _noiseBuf.getChannelData(0);
    for (var i = 0; i < len; i++) data[i] = Math.random() * 2 - 1;
    return _noiseBuf;
  }

  /* ─── SFX: Basic attack (categoría; luego por skill ID) ─── */
  function playAttack() {
    playHit(0.78);
  }

  /* ─── SFX: Hit / Impact ─── */
  function playHit(intensity) {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;
    var vol = Math.min(1, (intensity || 0.7));

    // Noise burst (thud)
    var noise = ctx.createBufferSource();
    noise.buffer = getNoiseBuffer(0.15);
    var nFilt = ctx.createBiquadFilter();
    nFilt.type = 'lowpass';
    nFilt.frequency.setValueAtTime(800, t);
    nFilt.frequency.exponentialRampToValueAtTime(100, t + 0.1);
    var nGain = ctx.createGain();
    nGain.gain.setValueAtTime(vol * 0.5, t);
    nGain.gain.exponentialRampToValueAtTime(0.001, t + 0.12);
    noise.connect(nFilt);
    nFilt.connect(nGain);
    nGain.connect(sfxGain);
    noise.start(t);
    noise.stop(t + 0.15);

    // Low sine punch
    var osc = ctx.createOscillator();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(120, t);
    osc.frequency.exponentialRampToValueAtTime(40, t + 0.08);
    var oGain = ctx.createGain();
    oGain.gain.setValueAtTime(vol * 0.6, t);
    oGain.gain.exponentialRampToValueAtTime(0.001, t + 0.1);
    osc.connect(oGain);
    oGain.connect(sfxGain);
    osc.start(t);
    osc.stop(t + 0.12);
  }

  /* ─── SFX: Critical Hit ─── */
  function playCrit() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;

    playHit(1.0);

    // Bright metallic ping
    var osc = ctx.createOscillator();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(1800, t + 0.02);
    osc.frequency.exponentialRampToValueAtTime(600, t + 0.2);
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.25, t + 0.02);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.25);
    osc.connect(g);
    g.connect(sfxGain);
    osc.start(t + 0.02);
    osc.stop(t + 0.3);
  }

  /* ─── SFX: Ability (energy) ─── */
  function playAbility() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;

    // Rising sweep
    var osc = ctx.createOscillator();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(200, t);
    osc.frequency.exponentialRampToValueAtTime(1200, t + 0.15);
    osc.frequency.exponentialRampToValueAtTime(400, t + 0.35);
    var filt = ctx.createBiquadFilter();
    filt.type = 'bandpass';
    filt.frequency.value = 600;
    filt.Q.value = 2;
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.2, t);
    g.gain.linearRampToValueAtTime(0.35, t + 0.12);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.4);
    osc.connect(filt);
    filt.connect(g);
    g.connect(sfxGain);
    osc.start(t);
    osc.stop(t + 0.45);

    playHit(0.5);
  }

  /* ─── SFX: Special / Ultimate ─── */
  function playSpecial() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;

    // Deep rumble buildup
    var osc1 = ctx.createOscillator();
    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(50, t);
    osc1.frequency.linearRampToValueAtTime(180, t + 0.4);
    var g1 = ctx.createGain();
    g1.gain.setValueAtTime(0.0, t);
    g1.gain.linearRampToValueAtTime(0.45, t + 0.3);
    g1.gain.exponentialRampToValueAtTime(0.001, t + 0.6);
    osc1.connect(g1);
    g1.connect(sfxGain);
    osc1.start(t);
    osc1.stop(t + 0.65);

    // High sweep
    var osc2 = ctx.createOscillator();
    osc2.type = 'sawtooth';
    osc2.frequency.setValueAtTime(300, t + 0.15);
    osc2.frequency.exponentialRampToValueAtTime(2000, t + 0.4);
    var filt = ctx.createBiquadFilter();
    filt.type = 'bandpass';
    filt.frequency.value = 900;
    filt.Q.value = 3;
    var g2 = ctx.createGain();
    g2.gain.setValueAtTime(0.0, t + 0.15);
    g2.gain.linearRampToValueAtTime(0.25, t + 0.35);
    g2.gain.exponentialRampToValueAtTime(0.001, t + 0.6);
    osc2.connect(filt);
    filt.connect(g2);
    g2.connect(sfxGain);
    osc2.start(t + 0.15);
    osc2.stop(t + 0.65);

    // Noise explosion at impact
    setTimeout(function () { playHit(1.0); }, 350);
  }

  /* ─── SFX: Heal ─── */
  function playHeal() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;

    // Gentle ascending arpeggio
    var notes = [440, 554, 659, 880];
    notes.forEach(function (freq, i) {
      var osc = ctx.createOscillator();
      osc.type = 'sine';
      osc.frequency.value = freq;
      var g = ctx.createGain();
      var start = t + i * 0.08;
      g.gain.setValueAtTime(0.0, start);
      g.gain.linearRampToValueAtTime(0.15, start + 0.04);
      g.gain.exponentialRampToValueAtTime(0.001, start + 0.3);
      osc.connect(g);
      g.connect(sfxGain);
      osc.start(start);
      osc.stop(start + 0.35);
    });
  }

  /* ─── SFX: Defend ─── */
  function playDefend() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;

    var osc = ctx.createOscillator();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(300, t);
    osc.frequency.linearRampToValueAtTime(500, t + 0.1);
    osc.frequency.linearRampToValueAtTime(350, t + 0.3);
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.25, t);
    g.gain.linearRampToValueAtTime(0.15, t + 0.15);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.35);
    osc.connect(g);
    g.connect(sfxGain);
    osc.start(t);
    osc.stop(t + 0.4);
  }

  /* ─── SFX: Death ─── */
  function playDeath() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;

    // Descending sweep
    var osc = ctx.createOscillator();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(600, t);
    osc.frequency.exponentialRampToValueAtTime(40, t + 0.6);
    var filt = ctx.createBiquadFilter();
    filt.type = 'lowpass';
    filt.frequency.setValueAtTime(2000, t);
    filt.frequency.exponentialRampToValueAtTime(80, t + 0.5);
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.3, t);
    g.gain.linearRampToValueAtTime(0.35, t + 0.1);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.7);
    osc.connect(filt);
    filt.connect(g);
    g.connect(sfxGain);
    osc.start(t);
    osc.stop(t + 0.75);

    // Noise decay
    var noise = ctx.createBufferSource();
    noise.buffer = getNoiseBuffer(0.5);
    var nf = ctx.createBiquadFilter();
    nf.type = 'lowpass';
    nf.frequency.setValueAtTime(400, t);
    nf.frequency.exponentialRampToValueAtTime(60, t + 0.5);
    var ng = ctx.createGain();
    ng.gain.setValueAtTime(0.2, t);
    ng.gain.exponentialRampToValueAtTime(0.001, t + 0.5);
    noise.connect(nf);
    nf.connect(ng);
    ng.connect(sfxGain);
    noise.start(t);
    noise.stop(t + 0.55);
  }

  /* ─── SFX: Evade / Miss ─── */
  function playEvade() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;

    var osc = ctx.createOscillator();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(800, t);
    osc.frequency.exponentialRampToValueAtTime(200, t + 0.15);
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.12, t);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.15);
    osc.connect(g);
    g.connect(sfxGain);
    osc.start(t);
    osc.stop(t + 0.2);
  }

  /* ─── SFX: UI Select ─── */
  function playSelect() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;

    var osc = ctx.createOscillator();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(600, t);
    osc.frequency.exponentialRampToValueAtTime(900, t + 0.06);
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.1, t);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.08);
    osc.connect(g);
    g.connect(sfxGain);
    osc.start(t);
    osc.stop(t + 0.1);
  }

  /* ─── SFX: Confirm ─── */
  function playConfirm() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;

    [700, 900, 1100].forEach(function (f, i) {
      var osc = ctx.createOscillator();
      osc.type = 'sine';
      osc.frequency.value = f;
      var g = ctx.createGain();
      var s = t + i * 0.06;
      g.gain.setValueAtTime(0.12, s);
      g.gain.exponentialRampToValueAtTime(0.001, s + 0.1);
      osc.connect(g);
      g.connect(sfxGain);
      osc.start(s);
      osc.stop(s + 0.12);
    });
  }

  /* ─── SFX: Victory ─── */
  function playVictory() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;
    var notes = [523, 659, 784, 1047, 1319]; // C5 E5 G5 C6 E6
    notes.forEach(function (f, i) {
      var osc = ctx.createOscillator();
      osc.type = 'triangle';
      osc.frequency.value = f;
      var g = ctx.createGain();
      var s = t + i * 0.12;
      g.gain.setValueAtTime(0.0, s);
      g.gain.linearRampToValueAtTime(0.18, s + 0.05);
      g.gain.exponentialRampToValueAtTime(0.001, s + 0.5);
      osc.connect(g);
      g.connect(sfxGain);
      osc.start(s);
      osc.stop(s + 0.55);
    });
  }

  /* ─── SFX: Defeat ─── */
  function playDefeat() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;
    var notes = [400, 350, 280, 200];
    notes.forEach(function (f, i) {
      var osc = ctx.createOscillator();
      osc.type = 'sawtooth';
      osc.frequency.value = f;
      var filt = ctx.createBiquadFilter();
      filt.type = 'lowpass';
      filt.frequency.value = 600;
      var g = ctx.createGain();
      var s = t + i * 0.2;
      g.gain.setValueAtTime(0.0, s);
      g.gain.linearRampToValueAtTime(0.12, s + 0.05);
      g.gain.exponentialRampToValueAtTime(0.001, s + 0.5);
      osc.connect(filt);
      filt.connect(g);
      g.connect(sfxGain);
      osc.start(s);
      osc.stop(s + 0.55);
    });
  }

  /* ─── SFX: Round Start whoosh ─── */
  function playRoundStart() {
    if (!ensureCtx()) return;
    var t = ctx.currentTime;

    var noise = ctx.createBufferSource();
    noise.buffer = getNoiseBuffer(0.4);
    var filt = ctx.createBiquadFilter();
    filt.type = 'bandpass';
    filt.frequency.setValueAtTime(200, t);
    filt.frequency.exponentialRampToValueAtTime(2000, t + 0.15);
    filt.frequency.exponentialRampToValueAtTime(400, t + 0.35);
    filt.Q.value = 1.5;
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.0, t);
    g.gain.linearRampToValueAtTime(0.2, t + 0.1);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.4);
    noise.connect(filt);
    filt.connect(g);
    g.connect(sfxGain);
    noise.start(t);
    noise.stop(t + 0.45);

    // Tone
    var osc = ctx.createOscillator();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(400, t);
    osc.frequency.linearRampToValueAtTime(700, t + 0.2);
    var og = ctx.createGain();
    og.gain.setValueAtTime(0.08, t);
    og.gain.exponentialRampToValueAtTime(0.001, t + 0.3);
    osc.connect(og);
    og.connect(sfxGain);
    osc.start(t);
    osc.stop(t + 0.35);
  }

  /* ─── AMBIENT BATTLE MUSIC (simple drone loop) ─── */
  function startMusic() {
    if (!ensureCtx() || _musicPlaying) return;
    _musicPlaying = true;

    // Bass drone
    var bass = ctx.createOscillator();
    bass.type = 'sine';
    bass.frequency.value = 55; // A1
    var bassG = ctx.createGain();
    bassG.gain.value = 0.3;
    bass.connect(bassG);
    bassG.connect(musicGain);
    bass.start();

    // Pad
    var pad = ctx.createOscillator();
    pad.type = 'triangle';
    pad.frequency.value = 110;
    var padFilt = ctx.createBiquadFilter();
    padFilt.type = 'lowpass';
    padFilt.frequency.value = 300;
    var padG = ctx.createGain();
    padG.gain.value = 0.15;
    pad.connect(padFilt);
    padFilt.connect(padG);
    padG.connect(musicGain);
    pad.start();

    // Sub pulse LFO
    var lfo = ctx.createOscillator();
    lfo.type = 'sine';
    lfo.frequency.value = 0.3;
    var lfoG = ctx.createGain();
    lfoG.gain.value = 15;
    lfo.connect(lfoG);
    lfoG.connect(bass.frequency);
    lfo.start();

    _musicNodes = [bass, pad, lfo, bassG, padG, lfoG, padFilt];
  }

  function stopMusic() {
    _musicPlaying = false;
    _musicNodes.forEach(function (n) {
      try { if (n.stop) n.stop(); else if (n.disconnect) n.disconnect(); } catch (e) {}
    });
    _musicNodes = [];
  }

  /* ─── INIT: unlock on first click ─── */
  function init() {
    var events = ['click', 'touchstart', 'keydown'];
    function handler() {
      unlock();
      events.forEach(function (e) { document.removeEventListener(e, handler); });
    }
    events.forEach(function (e) { document.addEventListener(e, handler, { once: false }); });
  }

  init();

  window.MWArenaSFX = {
    unlock: unlock,
    playHit: playHit,
    playAttack: playAttack,
    playCrit: playCrit,
    playAbility: playAbility,
    playSpecial: playSpecial,
    playHeal: playHeal,
    playDefend: playDefend,
    playDeath: playDeath,
    playEvade: playEvade,
    playSelect: playSelect,
    playConfirm: playConfirm,
    playVictory: playVictory,
    playDefeat: playDefeat,
    playRoundStart: playRoundStart,
    startMusic: startMusic,
    stopMusic: stopMusic
  };
})();
