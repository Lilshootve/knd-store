/**
 * Mind Wars Audio Manager
 * Preload, play, mute toggle. Respects autoplay policies (requires user interaction).
 */
(function () {
  'use strict';

  const BASE = '/assets/audio/mind-wars/';
  const STORAGE_KEY = 'mindWarsAudioMuted';

  const SOUNDS = {
    attack: BASE + 'sfx-attack.mp3',
    defend: BASE + 'sfx-defend.mp3',
    ability: BASE + 'sfx-ability.mp3',
    special: BASE + 'sfx-special.mp3',
    hit: BASE + 'sfx-hit.mp3',
    crit: BASE + 'sfx-crit.mp3',
    heal: BASE + 'sfx-heal.mp3',
    evade: BASE + 'sfx-evade.mp3',
    victory: BASE + 'sfx-victory.mp3',
    defeat: BASE + 'sfx-defeat.mp3',
    turn: BASE + 'sfx-turn.mp3',
    battleStart: BASE + 'sfx-turn.mp3',
  };

  const pool = {};
  let muted = false;
  let unlocked = false;
  /** @type {HTMLAudioElement|null} */
  let bgmAudio = null;

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

  function stopBgmInternal() {
    if (bgmAudio) {
      try {
        bgmAudio.pause();
        bgmAudio.currentTime = 0;
      } catch (e) {}
    }
  }

  function unlock() {
    if (unlocked) return;
    unlocked = true;
    const dummy = new Audio();
    dummy.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=';
    dummy.play().catch(function () {});
  }

  muted = loadMuted();

  window.MindWarsAudio = {
    play: function (key) {
      playFromPool(key);
    },
    playAction: function (actionType) {
      const map = {
        attack: 'attack',
        defend: 'defend',
        ability: 'ability',
        special: 'special',
        heal: 'heal',
      };
      playFromPool(map[actionType] || 'hit');
    },
    playHit: function (isCrit) {
      playFromPool(isCrit ? 'crit' : 'hit');
    },
    playHeal: function () {
      playFromPool('heal');
    },
    playEvade: function () {
      playFromPool('evade');
    },
    playResult: function (result) {
      if (result === 'win') playFromPool('victory');
      else if (result === 'lose') playFromPool('defeat');
    },
    playTurn: function () {
      playFromPool('turn');
    },
    playBattleStart: function () {
      playFromPool('battleStart');
    },
    startBgm: function () {
      if (muted || !unlocked) return;
      if (!bgmAudio) {
        bgmAudio = new Audio();
        bgmAudio.preload = 'auto';
        bgmAudio.loop = true;
        bgmAudio.volume = 0.22;
        bgmAudio.src = BASE + 'bgm-arena.mp3';
        bgmAudio.load();
      }
      try {
        bgmAudio.play().catch(function () {});
      } catch (e) {}
    },
    stopBgm: function () {
      stopBgmInternal();
    },
    setMuted: function (val) {
      muted = !!val;
      saveMuted(muted);
      if (muted) stopBgmInternal();
    },
    isMuted: function () {
      return muted;
    },
    toggleMuted: function () {
      muted = !muted;
      saveMuted(muted);
      if (muted) stopBgmInternal();
      return muted;
    },
    unlock: unlock,
    preload: function () {
      Object.keys(SOUNDS).forEach(function (k) {
        getPool(k);
      });
    },
  };

  document.addEventListener('click', unlock, { once: true });
  document.addEventListener('keydown', unlock, { once: true });
  document.addEventListener('touchstart', unlock, { once: true });
})();
