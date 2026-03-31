/**
 * Knowledge Duel Audio Manager
 * Preload, play, mute toggle. Respects autoplay policies (requires user interaction).
 */
(function () {
  'use strict';

  const BASE = '/assets/audio/knowledge-duel/';
  const STORAGE_KEY = 'knowledgeDuelAudioMuted';

  const SOUNDS = {
    correct: BASE + 'sfx-correct.mp3',
    wrong: BASE + 'sfx-wrong.mp3',
    hitEnemy: BASE + 'sfx-hit-enemy.mp3',
    hitPlayer: BASE + 'sfx-hit-player.mp3',
    victory: BASE + 'sfx-victory.mp3',
    defeat: BASE + 'sfx-defeat.mp3',
    levelUp: BASE + 'sfx-level-up.mp3',
    battleStart: BASE + 'sfx-hit-enemy.mp3',
  };

  const pool = {};
  let muted = false;
  let unlocked = false;
  /** @type {AudioContext|null} */
  let uiAudioCtx = null;

  function loadMuted() {
    try {
      return localStorage.getItem(STORAGE_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function saveMuted(val) {
    try {
      localStorage.setItem(STORAGE_KEY, val ? '1' : '0');
    } catch (e) {}
  }

  function createAudio(src) {
    const a = new Audio();
    a.preload = 'auto';
    a.volume = 0.6;
    a.src = src;
    a.load();
    return a;
  }

  function getPool(key) {
    const src = SOUNDS[key];
    if (!src) return null;
    if (!pool[key]) {
      pool[key] = [];
      for (let i = 0; i < 2; i++) {
        pool[key].push(createAudio(src));
      }
    }
    return pool[key];
  }

  function playUiClick(optStartFreq) {
    if (muted) return;
    var startHz = typeof optStartFreq === 'number' && optStartFreq > 40 ? optStartFreq : 880;
    var endHz = Math.max(110, Math.round(startHz * 0.5));
    function runTone() {
      if (!uiAudioCtx) return;
      var o = uiAudioCtx.createOscillator();
      var g = uiAudioCtx.createGain();
      o.connect(g);
      g.connect(uiAudioCtx.destination);
      o.type = 'sine';
      o.frequency.setValueAtTime(startHz, uiAudioCtx.currentTime);
      o.frequency.exponentialRampToValueAtTime(endHz, uiAudioCtx.currentTime + 0.08);
      g.gain.setValueAtTime(0.08, uiAudioCtx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, uiAudioCtx.currentTime + 0.1);
      o.start();
      o.stop(uiAudioCtx.currentTime + 0.1);
    }
    try {
      if (!uiAudioCtx) {
        uiAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
      }
      if (uiAudioCtx.state === 'suspended') {
        uiAudioCtx.resume().then(runTone).catch(runTone);
      } else {
        runTone();
      }
    } catch (e) {}
  }

  function initUiClickSounds() {
    document.addEventListener(
      'click',
      function (e) {
        if (muted) return;
        if (e.target.closest('#kd-mute-btn')) return;
        /* Battle answers use dedicated SFX (correct/wrong); skip UI beep */
        if (e.target.closest('.kd-option-btn')) return;
        if (
          e.target.closest(
            'button,a.knd-btn,.kd-duelist-picker-item,.kd-avatar-card,.kd-choice-card,.tb-avatar,.tb-logo,.currency-chip,.tb-icon-btn,.bnav-item,.mission-card,.mode-card,.event-banner,.panel-action,.qa-btn,.lb-row,.lavs-knd-card,.kd-lb-row,[data-open-mm]'
          )
        ) {
          playUiClick();
        }
      },
      false
    );
  }

  function playFromPool(key) {
    if (muted || !unlocked) return;
    const arr = getPool(key);
    if (!arr) return;
    let a = arr.find(function (x) {
      return x.paused || x.ended;
    });
    if (!a) a = arr[0];
    try {
      a.currentTime = 0;
      a.play().catch(function () {});
    } catch (e) {}
  }

  function unlock() {
    if (unlocked) return;
    unlocked = true;
    const dummy = new Audio();
    dummy.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=';
    dummy.play().catch(function () {});
  }

  muted = loadMuted();

  window.KnowledgeDuelAudio = {
    play: function (key) {
      playFromPool(key);
    },
    playCorrect: function () {
      playFromPool('correct');
    },
    playWrong: function () {
      playFromPool('wrong');
    },
    playHitEnemy: function () {
      playFromPool('hitEnemy');
    },
    playHitPlayer: function () {
      playFromPool('hitPlayer');
    },
    playResult: function (result) {
      if (result === 'win') playFromPool('victory');
      else if (result === 'lose') playFromPool('defeat');
    },
    playLevelUp: function () {
      playFromPool('levelUp');
    },
    playBattleStart: function () {
      playFromPool('battleStart');
    },
    setMuted: function (val) {
      muted = !!val;
      saveMuted(muted);
    },
    isMuted: function () {
      return muted;
    },
    toggleMuted: function () {
      muted = !muted;
      saveMuted(muted);
      return muted;
    },
    unlock: unlock,
    preload: function () {
      Object.keys(SOUNDS).forEach(function (k) {
        getPool(k);
      });
    },
    playUiClick: playUiClick,
  };

  /* Capture phase: unlock pool before button handlers so first tap plays SFX too */
  document.addEventListener('click', unlock, { once: true, capture: true });
  document.addEventListener('keydown', unlock, { once: true, capture: true });
  document.addEventListener('touchstart', unlock, { once: true, capture: true });

  initUiClickSounds();
})();
