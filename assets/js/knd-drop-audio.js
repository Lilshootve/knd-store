/**
 * KND Drop Chamber Audio Manager
 * SFX for scan, reveal by rarity. Silent fallback if files missing.
 */
(function () {
    'use strict';

    var BASE = '/assets/audio/drop/';
    var STORAGE_KEY = 'kndDropAudioMuted';

    var SOUNDS = {
        scan: BASE + 'sfx-scan.mp3',
        revealCommon: BASE + 'sfx-reveal-common.mp3',
        revealRare: BASE + 'sfx-reveal-rare.mp3',
        revealEpic: BASE + 'sfx-reveal-epic.mp3',
        revealLegendary: BASE + 'sfx-reveal-legendary.mp3'
    };

    var pool = {};
    var muted = false;
    var unlocked = false;

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
        var a = new Audio();
        a.preload = 'auto';
        a.volume = 0.5;
        a.src = src;
        a.load();
        return a;
    }

    function getPool(key) {
        var src = SOUNDS[key];
        if (!src) return null;
        if (!pool[key]) {
            pool[key] = createAudio(src);
        }
        return pool[key];
    }

    function play(key) {
        if (muted || !unlocked) return;
        var a = getPool(key);
        if (!a) return;
        try {
            a.currentTime = 0;
            a.play().catch(function () {});
        } catch (e) {}
    }

    function unlock() {
        if (unlocked) return;
        unlocked = true;
        var dummy = new Audio();
        dummy.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=';
        dummy.play().catch(function () {});
    }

    muted = loadMuted();

    window.DropAudio = {
        playScan: function () { play('scan'); },
        playReveal: function (rarity) {
            var key = 'revealCommon';
            if (rarity === 'legendary') key = 'revealLegendary';
            else if (rarity === 'epic') key = 'revealEpic';
            else if (rarity === 'rare' || rarity === 'special') key = 'revealRare';
            play(key);
        },
        setMuted: function (val) {
            muted = !!val;
            saveMuted(muted);
        },
        isMuted: function () { return muted; },
        toggleMuted: function () {
            muted = !muted;
            saveMuted(muted);
            return muted;
        },
        unlock: unlock
    };

    document.addEventListener('click', unlock, { once: true });
    document.addEventListener('keydown', unlock, { once: true });
    document.addEventListener('touchstart', unlock, { once: true });
})();
