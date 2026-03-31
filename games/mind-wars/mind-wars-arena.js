/**
 * Mind Wars Arena v2 - Battle Arena
 * Specific to games/mind-wars/mind-wars-arena.php
 *
 * Flow (all server-side via SQL):
 * - Lobby: /api/avatars/get.php (list user avatars from DB)
 * - Start: /api/mind-wars/start_battle.php (creates battle, returns state)
 * - Actions: /api/mind-wars/perform_action.php (attack, defend, ability, special, heal, advance)
 * - Surrender: /api/mind-wars/forfeit.php
 */
(function () {
  'use strict';

  const G = {
    round: 1,
    turn: 'player',
    busy: false,
    wins: 0,
    player: null,
    enemy: null,
    selectedAvatarItemId: 0,
    lastResult: null,
    battleToken: null,
    lastLogLength: 0,
    battleMode: '1v1',
    enemyQueue: [1],
    enemyIndex: 0,
    pendingWaveTransition: false,
    availableAvatars: [],
    lobbyState: 'INITIAL_PLAY',
    selectedAvatarSlots: [],
    activeSlotIndex: null,
    playerQueue: [],
    playerIndex: 0,
    /** Last PVE 3v3 team (knd item ids) for REMATCH after lobby reset */
    pveRematchItemIds: null,
    /** PvP ranked: server DB side for this user ('player' | 'enemy'); null for PvE */
    pvpViewerSide: null,
    isPvpRanked: false,
    /** setTimeout id for resolveEnemyTurn — must clear on rematch / new battle */
    enemyTurnTimer: null,
    /** Incremented on each new server battle so stale async callbacks are ignored */
    battleEpoch: 0,
    /** Last turns_played from server (PvP poll) to detect opponent actions */
    pvpLastTurnsPlayedSeen: -1
  };
  let pvpPollTimer = null;
  const PVP_POLL_MS = 2000;
  /** Last turn label for arena SFX; null until first setTurn after reset avoids double play with battle-start */
  let prevTurnAudio = null;

  function mwAudio(fn) {
    if (typeof window.MindWarsAudio === 'undefined' || typeof fn !== 'function') return;
    try {
      fn(window.MindWarsAudio);
    } catch (e) {}
  }

  function resetTurnAudio() {
    prevTurnAudio = null;
  }

  function syncArenaSfxToggleUi() {
    const btn = document.getElementById('arena-sfx-toggle');
    if (!btn) return;
    const muted = typeof window.MindWarsAudio !== 'undefined' && window.MindWarsAudio.isMuted();
    btn.classList.toggle('muted', !!muted);
    btn.setAttribute('aria-pressed', muted ? 'true' : 'false');
    btn.textContent = muted ? '🔇 SFX' : '🔊 SFX';
  }
  const RC = { common: '#4a8a9a', special: '#18aa6a', rare: '#1a6aee', epic: '#d400ff', legendary: '#ffc820' };
  const KND_RARITY = {
    common: { color: '#4a7a8a', label: 'COMMON', glow: 'rgba(74,122,138,0.5)' },
    special: { color: '#1aaa6a', label: 'SPECIAL', glow: 'rgba(26,170,106,0.5)' },
    rare: { color: '#1a6aee', label: 'RARE', glow: 'rgba(26,106,238,0.5)' },
    epic: { color: '#c040ff', label: 'EPIC', glow: 'rgba(192,64,255,0.5)' },
    legendary: { color: '#ffc030', label: 'LEGENDARY', glow: 'rgba(255,192,48,0.5)' }
  };
  const KND_STAT_COLORS = { mnd: '#c040ff', fcs: '#00e5ff', spd: '#20e080', lck: '#ffc030' };
  const KND_STAT_LABELS = { mnd: 'MIND', fcs: 'FOCUS', spd: 'SPEED', lck: 'LUCK' };
  const SL = { mnd: 'MIND', fcs: 'FOCUS', spd: 'SPEED', lck: 'LUCK' };
  const SC = { mnd: '#c040ff', fcs: '#00f0ff', spd: '#00ff88', lck: '#ffc820' };
  const COSTS = { attack: 1, defend: 0, ability: 2, special: 5, heal: 2 };
  const INTENTS = ['⚔ PREPARING STRIKE', '🧠 CALCULATING MOVE', '🛡 MATRIX SHIELD', '🌌 VOID CHARGING', '⚡ STORM READY', '👁 SCANNING TARGET'];
  const ENEMY_START_SHIELD_CHANCE = 0.35;
  const TEAM3_ENEMY_COUNT = 3;
  const ENEMY_DEFEAT_TO_NEXT_WAVE_MS = 1650;
  const CSRF = typeof window.MW_ARENA_CSRF !== 'undefined' ? window.MW_ARENA_CSRF : '';

  const SIL_HTML = '<div class="avsil"><div class="sh"></div><div class="sn"></div><div class="st"></div><div class="sl"><div class="slg"></div><div class="slg"></div></div></div>';

  function deepClone(obj) {
    if (typeof structuredClone === 'function') return structuredClone(obj);
    return JSON.parse(JSON.stringify(obj));
  }

  function assetUrl(path) {
    if (!path) return null;
    return (path.startsWith('/') || path.startsWith('http')) ? path : '/assets/avatars/' + path;
  }

  /** Map PHP battle state fighter to arena G.player/G.enemy format */
  function apiFighterToArena(f, side) {
    if (!f) return null;
    const maxE = 5;
    return {
      id: f.id ?? 0,
      item_id: f.item_id != null ? Number(f.item_id) : Number(f.id ?? 0),
      name: f.name ?? 'Unknown',
      class: (f.combat_class_label || f.combat_class || 'Fighter').toUpperCase(),
      stats: {
        mnd: f.mind ?? 50,
        fcs: f.focus ?? 50,
        spd: f.speed ?? 50,
        lck: f.luck ?? 50
      },
      skills: { passive: '', ability: '', special: '', heal: null },
      hp: f.hp ?? 0,
      max: f.hp_max ?? f.hp ?? 1000,
      energy: f.energy ?? 0,
      maxE,
      shield: !!f.defending,
      rarity: f.rarity ?? 'common',
      lore: '',
      image: assetUrl(f.asset_path) || null,
      level: f.level ?? 1,
      ability_code: f.ability_code ?? null,
      special_code: f.special_code ?? null,
      ability_cooldown: f.ability_cooldown ?? 0,
      heal_code: f.heal_code ?? null
    };
  }

  async function fetchApi(url, options) {
    const r = await fetch(url, { credentials: 'same-origin', ...options });
    const text = await r.text();
    if (!text || !text.trim()) throw new Error('Empty response');
    const j = JSON.parse(text);
    if (!j.ok) throw new Error(j?.error?.message || 'Request failed');
    return j.data || j;
  }

  function renderAvatar(side) {
    const fig = document.getElementById(side === 'player' ? 'pfig' : 'efig');
    const avatar = G[side];
    fig.innerHTML = '';
    if (avatar.image) {
      const img = document.createElement('img');
      img.src = avatar.image;
      img.alt = '';
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'contain';
      img.onerror = () => { fig.innerHTML = SIL_HTML; };
      fig.appendChild(img);
    } else {
      fig.innerHTML = SIL_HTML;
    }
  }

  function updateCardMeta(side) {
    const card = document.getElementById(side === 'player' ? 'pc' : 'ec');
    const s = G[side];
    const col = RC[s.rarity] || RC.common;
    card.style.setProperty('--rc', col);
    card.querySelector('.cname').textContent = s.name;
    card.querySelector('.cclass').textContent = s.class;
    card.querySelector('.vid').textContent = '#' + String(s.id || 0).padStart(3, '0');
    card.querySelector('.vrar').textContent = s.rarity.toUpperCase();
    const vlvl = card.querySelector('.vlvl');
    if (vlvl) vlvl.textContent = 'Lv ' + (s.level ?? 1);
    const sg = card.querySelector('.sgrid');
    const st = s.stats;
    sg.innerHTML = `<div class="si"><div class="sir"><span class="sik">MND</span><span class="siv">${st.mnd}</span></div><div class="sitr"><div class="sif" style="--sc:#c040ff;width:${Math.min(100, st.mnd)}%"></div></div></div><div class="si"><div class="sir"><span class="sik">FCS</span><span class="siv">${st.fcs}</span></div><div class="sitr"><div class="sif" style="--sc:var(--c);width:${Math.min(100, st.fcs)}%"></div></div></div><div class="si"><div class="sir"><span class="sik">SPD</span><span class="siv">${st.spd}</span></div><div class="sitr"><div class="sif" style="--sc:var(--green);width:${Math.min(100, st.spd)}%"></div></div></div><div class="si"><div class="sir"><span class="sik">LCK</span><span class="siv">${st.lck}</span></div><div class="sitr"><div class="sif" style="--sc:var(--gold);width:${Math.min(100, st.lck)}%"></div></div></div>`;
  }

  function useFallbackState() {
    const fb = { name: 'Unknown', class: 'FIGHTER', rarity: 'common', lore: '', hp: 1012, max: 1012, energy: 3, maxE: 5, shield: false, stats: { mnd: 50, fcs: 50, spd: 50, lck: 50 }, image: '', id: 0, skills: { passive: '', ability: '', special: '', heal: null } };
    G.player = { ...fb, energy: 3, shield: false };
    G.enemy = { ...fb, energy: 2, maxE: 5, shield: Math.random() < ENEMY_START_SHIELD_CHANCE };
    renderAvatar('player');
    renderAvatar('enemy');
    updateCardMeta('player');
    updateCardMeta('enemy');
    rebuildS('enemy');
  }

  function normalizeLobbyAvatar(a) {
    if (!a) return null;
    const lvl = Math.min(10, Math.max(1, Number(a.avatar_level ?? 1)));
    const img = a.image && (a.image.startsWith('/') || a.image.startsWith('http'))
      ? a.image
      : assetUrl(a.asset_path || a.image_path || a.thumb_path || '');
    const st = a.stats || {};
    return {
      item_id: Number(a.item_id ?? a.id ?? 0),
      id: Number(a.id ?? a.item_id ?? 0),
      name: String(a.name || 'Unknown Avatar'),
      rarity: String(a.rarity || 'common').toLowerCase(),
      level: lvl,
      image: img || null,
      stats: (st.mind != null || st.mnd != null) ? { mnd: st.mnd ?? st.mind, fcs: st.fcs ?? st.focus, spd: st.spd ?? st.speed, lck: st.lck ?? st.luck } : null
    };
  }

  async function loadLobbyAvatars() {
    try {
      const res = await fetch('/api/avatars/get.php', { credentials: 'same-origin' });

      if (!res.ok) throw new Error('HTTP ' + res.status);

      const json = await res.json();

      if (!json.ok || !json.data?.avatars) {
        G.availableAvatars = [];
        return;
      }

      const avatars = json.data.avatars;

      const RARITY_ORDER = { legendary: 0, epic: 1, rare: 2, special: 3, common: 4 };
      const sorted = [...avatars].sort((a, b) => {
        const ra = RARITY_ORDER[a.rarity?.toLowerCase()] ?? 99;
        const rb = RARITY_ORDER[b.rarity?.toLowerCase()] ?? 99;
        if (ra !== rb) return ra - rb;
        return (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' });
      });

      G.availableAvatars = sorted.map(normalizeLobbyAvatar).filter(Boolean);

    } catch (e) {
      console.error(e);
      G.availableAvatars = [];
    }
  }

  function showBattleScreen() {
    document.getElementById('arena-lobby').classList.add('hidden');
    document.getElementById('arena-play').classList.add('visible');
  }

  function showLobbyScreen() {
    mwAudio(function (A) { A.stopBgm(); });
    document.getElementById('arena-play').classList.remove('visible');
    document.getElementById('arena-lobby').classList.remove('hidden');
  }

  function configureBattleMode(mode) {
    const normalized = mode === '3v3' ? '3v3' : '1v1';
    if (normalized === '1v1') {
      G.pveRematchItemIds = null;
    }
    G.battleMode = normalized;
    G.enemyQueue = normalized === '3v3'
      ? Array.from({ length: TEAM3_ENEMY_COUNT }, (_, i) => i + 1)
      : [1];
    G.enemyIndex = 0;
    G.pendingWaveTransition = false;
  }

  /** Align mode/queues with server state (lobby → arena via token does not run configureBattleMode). */
  function syncBattleModeFromState(s) {
    const is3 = s && s.meta && String(s.meta.format || '') === '3v3';
    const normalized = is3 ? '3v3' : '1v1';
    G.battleMode = normalized;
    G.enemyQueue = normalized === '3v3'
      ? Array.from({ length: TEAM3_ENEMY_COUNT }, (_, i) => i + 1)
      : [1];
    const wi = s.meta && s.meta.enemy_wave_index != null ? Number(s.meta.enemy_wave_index) : 0;
    G.enemyIndex = Number.isFinite(wi) ? Math.max(0, wi) : 0;
  }

  function startPveBattle(mode) {
    configureBattleMode(mode || G.battleMode || '1v1');
    showBattleScreen();
    initBattle({ preserveLog: false, activateEnemy: false });
  }

  function lobbyAvatarToFighter(av) {
    const level = Number(av?.level || 1);
    const base = 58 + (level * 4);
    const maxHp = 880 + (level * 40);
    const sk = av?.skills || {};
    return {
      id: Number(av?.id || av?.item_id || 0),
      name: String(av?.name || 'Unknown Avatar'),
      class: (av?.class || 'FIGHTER').toUpperCase(),
      stats: {
        mnd: Math.min(100, Number(av?.stats?.mnd ?? av?.stats?.mind ?? base)),
        fcs: Math.min(100, Number(av?.stats?.fcs ?? av?.stats?.focus ?? base)),
        spd: Math.min(100, Number(av?.stats?.spd ?? av?.stats?.speed ?? base)),
        lck: Math.min(100, Number(av?.stats?.lck ?? av?.stats?.luck ?? base))
      },
      skills: { passive: '', ability: '', special: '', heal: null },
      hp: maxHp,
      max: maxHp,
      energy: 3,
      maxE: 5,
      shield: false,
      rarity: String(av?.rarity || 'common').toLowerCase(),
      lore: '',
      image: av?.image || null,
      level,
      ability_code: av?.ability_code ?? sk?.ability ?? null,
      special_code: av?.special_code ?? sk?.special ?? null,
      ability_cooldown: 0,
      heal_code: av?.heal_code ?? sk?.heal ?? null
    };
  }

  function updatePlayerUI() {
    renderAvatar('player');
    updateCardMeta('player');
    rfhp('player');
    orbs('player');
    rebuildS('player');
    rbtn();
  }

  function updateEnemyUI() {
    renderAvatar('enemy');
    updateCardMeta('enemy');
    rfhp('enemy');
    orbs('enemy');
    rebuildS('enemy');
  }

  function buildPlayerQueueFromSlots() {
    const slots = Array.isArray(G.selectedAvatarSlots) ? G.selectedAvatarSlots.filter(Boolean) : [];
    G.playerQueue = slots.map(lobbyAvatarToFighter).filter(Boolean);
    G.playerIndex = 0;
  }

  /** Rebuild lobby slot objects from three inventory item ids (for REMATCH after resetLobbyFlow). */
  function rematchSlotsFromItemIds(ids) {
    if (!Array.isArray(ids) || ids.length !== 3) return [];
    const list = G.availableAvatars || [];
    return ids.map(function (rawId) {
      const id = Number(rawId) || 0;
      let av = null;
      for (let i = 0; i < list.length; i++) {
        if (Number(list[i].item_id) === id) {
          av = list[i];
          break;
        }
      }
      if (av) return av;
      return {
        item_id: id,
        id: id,
        name: 'Avatar',
        level: 1,
        rarity: 'common',
        image: null,
        stats: { mnd: 58, fcs: 58, spd: 58, lck: 58 },
        skills: {}
      };
    });
  }

  /** Keep 3v3 party ids in sync with server state for REMATCH. */
  function capturePveRematchTeamFromState(s) {
    if (!s || !s.meta || String(s.meta.format || '') !== '3v3') {
      G.pveRematchItemIds = null;
      return;
    }
    const q = s.meta.player_queue;
    if (!Array.isArray(q) || q.length < 3) {
      G.pveRematchItemIds = null;
      return;
    }
    const ids = [];
    for (let i = 0; i < 3; i++) {
      const f = q[i];
      const id = f && (f.id != null ? Number(f.id) : (f.item_id != null ? Number(f.item_id) : 0));
      if (id > 0) ids.push(id);
    }
    G.pveRematchItemIds = ids.length === 3 ? ids : null;
  }

  function runPlayerCardActivation() {
    const pc = document.getElementById('pc');
    if (!pc) return;
    pc.classList.remove('dead', 'hit', 'shaking', 'healing', 'flicker', 'player-activation');
    void pc.offsetWidth;
    pc.classList.add('player-activation');
    setTimeout(() => pc.classList.remove('player-activation'), 520);
  }

  function getLobbySections() {
    return {
      initial: document.getElementById('arena-initial-play-wrap'),
      mode: document.getElementById('arena-mode-content'),
      pveSubmode: document.getElementById('arena-pve-submode-content'),
      setup: document.getElementById('arena-avatar-setup-content')
    };
  }

  function setLobbyState(nextState) {
    G.lobbyState = nextState;
    const sections = getLobbySections();
    Object.values(sections).forEach(function (el) {
      if (!el) return;
      el.classList.remove('active');
    });
    if (nextState === 'INITIAL_PLAY' && sections.initial) sections.initial.classList.add('active');
    if (nextState === 'MODE_PICK' && sections.mode) sections.mode.classList.add('active');
    if (nextState === 'PVE_SUBMODE_PICK' && sections.pveSubmode) sections.pveSubmode.classList.add('active');
    if ((nextState === 'PVE_SETUP_1V1' || nextState === 'PVE_SETUP_3V3') && sections.setup) sections.setup.classList.add('active');
  }

  function setupSlotCount() {
    return G.battleMode === '3v3' ? 3 : 1;
  }

  function isSetupComplete() {
    const needed = setupSlotCount();
    return G.selectedAvatarSlots.length >= needed && G.selectedAvatarSlots.slice(0, needed).every(Boolean);
  }

  function setupSlotSilhouette() {
    return '<div class="thumb-silhouette"><div class="thumb-hex">⬡</div><div class="thumb-sil-head"></div><div class="thumb-sil-body"></div></div>';
  }

  function escHtml(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderKndCard(avatar, options) {
    const opts = options || {};
    const isPlaceholder = !!opts.placeholder;
    const rarityKey = (avatar && avatar.rarity ? String(avatar.rarity).toLowerCase() : 'common');
    const rarity = KND_RARITY[rarityKey] || KND_RARITY.common;
    const level = avatar && avatar.level ? Number(avatar.level) : 1;
    const stats = (avatar && avatar.stats) ? avatar.stats : { mnd: 0, fcs: 0, spd: 0, lck: 0 };
    const name = isPlaceholder ? 'SELECT AVATAR' : (avatar?.name || 'UNKNOWN AVATAR');
    const tagline = isPlaceholder ? ('SLOT ' + (Number(opts.slotIndex || 0) + 1)) : ('LV ' + level);
    const idNum = avatar?.id || avatar?.item_id || 0;
    const thumbHTML = (!isPlaceholder && avatar?.image)
      ? '<img src="' + escHtml(avatar.image) + '" alt="' + escHtml(name) + '">'
      : setupSlotSilhouette();

    const statsHtml = Object.keys(KND_STAT_LABELS).map(function (key) {
      const val = Math.max(0, Math.min(100, Number(stats[key] || 0)));
      return '<div class="stat-item">'
        + '<div class="stat-header"><span class="stat-label">' + KND_STAT_LABELS[key] + '</span><span class="stat-value">' + val + '</span></div>'
        + '<div class="stat-bar"><div class="stat-fill" style="--stat-color:' + KND_STAT_COLORS[key] + ';width:' + val + '%"></div></div>'
        + '</div>';
    }).join('');

    const card = document.createElement('article');
    card.className = 'avatar-card' + (isPlaceholder ? ' is-placeholder' : '');
    card.style.setProperty('--rarity-color', rarity.color);
    card.style.setProperty('--rarity-glow', rarity.glow);
    card.dataset.rarity = rarityKey;
    card.innerHTML = ''
      + '<div class="card-thumb">'
      + '  <div class="card-id">#' + String(idNum).padStart(3, '0') + '</div>'
      + '  <div class="card-rarity-badge">' + (isPlaceholder ? 'EMPTY' : rarity.label) + '</div>'
      + '  <div class="thumb-model">' + thumbHTML + '</div>'
      + '  <div class="thumb-ring"></div>'
      + '</div>'
      + '<div class="card-body">'
      + '  <div><div class="card-name">' + escHtml(name) + '</div><div class="card-tagline">' + escHtml(tagline) + '</div></div>'
      + '  <div class="card-stats">' + statsHtml + '</div>'
      + '  <div class="card-footer"><button type="button" class="inspect-btn" tabindex="-1">SELECT</button></div>'
      + '</div>';

    if (isPlaceholder) {
      const btn = card.querySelector('.inspect-btn');
      if (btn) btn.textContent = 'EMPTY';
    }

    return card;
  }

  function renderSetupSlots() {
    const slotsWrap = document.getElementById('lobby-setup-slots');
    const setupTitle = document.getElementById('lobby-setup-title');
    const setupSub = document.getElementById('lobby-setup-sub');
    const setupPlayBtn = document.getElementById('lobby-setup-play-btn');
    if (!slotsWrap || !setupTitle || !setupSub || !setupPlayBtn) return;

    const count = setupSlotCount();
    slotsWrap.className = 'lobby-setup-slots' + (count === 3 ? ' m3' : '');
    const lobbyBox = document.querySelector('#arena-lobby .lobby-box');
    if (lobbyBox) {
      lobbyBox.classList.toggle('lobby-box--3v3', count === 3);
    }
    setupTitle.textContent = G.battleMode === '3v3' ? 'PVE 3V3' : 'PVE 1V1';
    setupSub.textContent = G.battleMode === '3v3' ? 'Choose three avatars' : 'Choose one avatar';

    if (!Array.isArray(G.selectedAvatarSlots) || G.selectedAvatarSlots.length !== count) {
      G.selectedAvatarSlots = Array.from({ length: count }, function () { return null; });
    }

    slotsWrap.innerHTML = '';
    for (let i = 0; i < count; i++) {
      const av = G.selectedAvatarSlots[i] || null;
      const slot = document.createElement('button');
      slot.type = 'button';
      slot.className = 'lobby-avatar-slot ' + (av ? 'filled' : 'empty');
      slot.setAttribute('data-slot-index', String(i));
      const card = renderKndCard(av, { placeholder: !av, slotIndex: i, context: 'slot' });
      slot.appendChild(card);
      slot.addEventListener('click', function () {
        openAvatarSelector(i);
      });
      slotsWrap.appendChild(slot);
    }

    setupPlayBtn.disabled = !isSetupComplete();
  }

  function openAvatarSelector(slotIndex) {
    const selector = document.getElementById('lobby-avatar-selector');
    const grid = document.getElementById('lobby-avatar-selector-grid');
    if (!selector || !grid) return;
    G.activeSlotIndex = slotIndex;
    grid.innerHTML = '';

    if (!G.availableAvatars.length) {
      const empty = document.createElement('div');
      empty.className = 'lavs-empty';
      empty.textContent = 'No avatars available';
      grid.appendChild(empty);
    } else {
      G.availableAvatars.forEach(function (av) {
        const card = renderKndCard(av, { context: 'selector' });
        card.classList.add('lavs-knd-card');
        card.setAttribute('role', 'button');
        card.setAttribute('tabindex', '0');
        card.addEventListener('click', function () {
          G.selectedAvatarSlots[G.activeSlotIndex] = av;
          closeAvatarSelector();
          renderSetupSlots();
        });
        card.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            G.selectedAvatarSlots[G.activeSlotIndex] = av;
            closeAvatarSelector();
            renderSetupSlots();
          }
        });
        grid.appendChild(card);
      });
    }

    selector.classList.add('show');
    selector.setAttribute('aria-hidden', 'false');
  }

  function closeAvatarSelector() {
    const selector = document.getElementById('lobby-avatar-selector');
    if (!selector) return;
    selector.classList.remove('show');
    selector.setAttribute('aria-hidden', 'true');
    G.activeSlotIndex = null;
  }

  function startSetupBattle() {
    if (!isSetupComplete()) return;
    const firstAvatar = G.selectedAvatarSlots[0];
    if (!firstAvatar || !firstAvatar.item_id) return;
    buildPlayerQueueFromSlots();
    G.selectedAvatarItemId = firstAvatar.item_id;
    startPveBattle(G.battleMode);
  }

  function resetLobbyFlow() {
    G.selectedAvatarSlots = [];
    G.activeSlotIndex = null;
    G.playerQueue = [];
    G.playerIndex = 0;
    const lobbyBox = document.querySelector('#arena-lobby .lobby-box');
    if (lobbyBox) lobbyBox.classList.remove('lobby-box--3v3');
    setLobbyState('INITIAL_PLAY');
    closeAvatarSelector();
  }

  function runEnemyCardActivation() {
    const ec = document.getElementById('ec');
    if (!ec) return;
    ec.classList.remove('dead', 'hit', 'shaking', 'healing', 'flicker', 'card-activation');
    void ec.offsetWidth;
    ec.classList.add('card-activation');
    setTimeout(() => ec.classList.remove('card-activation'), 520);
  }

  async function initBattle(options) {
    const opts = options || {};
    const preserveLog = !!opts.preserveLog;
    const activateEnemy = !!opts.activateEnemy;
    cancelEnemyTurnSchedule();
    stopPvpStatePolling();
    G.battleEpoch = (G.battleEpoch || 0) + 1;
    const initEpoch = G.battleEpoch;
    G.battleToken = null;
    G.pvpViewerSide = null;
    G.isPvpRanked = false;
    G.pvpLastTurnsPlayedSeen = -1;
    G.busy = true;
    rbtn();
    if (!preserveLog) {
      document.getElementById('lb').innerHTML = '<div class="le sys">— Starting battle via server... —</div>';
    }

    try {
      const fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('mode', 'pve');
      fd.append('difficulty', 'normal');
      fd.append('format', G.battleMode === '3v3' ? '3v3' : '1v1');
      if (G.battleMode === '3v3') {
        const slotIds = [];
        if (G.selectedAvatarSlots && G.selectedAvatarSlots.length >= 3) {
          G.selectedAvatarSlots.slice(0, 3).forEach(function (av) {
            if (av && av.item_id) slotIds.push(Number(av.item_id));
          });
        }
        const remIds = (G.pveRematchItemIds && G.pveRematchItemIds.length === 3) ? G.pveRematchItemIds : null;
        const useIds = slotIds.length === 3 ? slotIds : remIds;
        if (useIds && useIds.length === 3) {
          useIds.forEach(function (id) {
            fd.append('avatar_item_ids[]', String(id));
          });
        } else {
          fd.append('avatar_item_id', String(G.selectedAvatarItemId));
        }
      } else {
        fd.append('avatar_item_id', String(G.selectedAvatarItemId));
      }

      const data = await fetchApi('/api/mind-wars/start_battle.php', { method: 'POST', body: fd });
      if (G.battleEpoch !== initEpoch) {
        G.busy = false;
        rbtn();
        return;
      }
      G.battleToken = data.battle_token;
      const s = data.state || {};

      applyApiState(s);
      G.pendingWaveTransition = false;
      if (!preserveLog) {
        document.getElementById('lb').innerHTML = '';
      }
      G.lastLogLength = 0;
      appendLogFromState(s, true);
      if (activateEnemy) {
        runEnemyCardActivation();
      }

      resetTurnAudio();
      mwAudio(function (A) {
        A.playBattleStart();
        A.startBgm();
      });

      G.turn = s.next_actor || 'player';
      G.busy = (G.turn === 'enemy');
      setTurn(G.turn);
      orbs('player');
      orbs('enemy');
      rfhp('player');
      rfhp('enemy');
      rbtn();
      rotI();

      if (G.turn === 'enemy') {
        scheduleEnemyTurn(800);
      }
    } catch (e) {
      console.error('Battle start failed:', e);
      document.getElementById('lb').innerHTML = '<div class="le sys" style="color:var(--red)">— Failed to start battle. ' + (e.message || '') + ' —</div>';
      useFallbackState();
      G.pendingWaveTransition = false;
      G.busy = false;
      G.turn = 'player';
      setTurn('player');
      orbs('player');
      orbs('enemy');
      rfhp('player');
      rfhp('enemy');
      rbtn();
    }
  }

  function applyApiState(s) {
    syncBattleModeFromState(s);
    const p = s.player || {}, e = s.enemy || {};
    G.player = apiFighterToArena(p, 'player');
    G.enemy = apiFighterToArena(e, 'enemy');
    if (!G.player || !G.enemy) {
      useFallbackState();
      return;
    }
    const pid = Number(G.player.item_id || G.player.id || 0);
    if (pid > 0) {
      G.selectedAvatarItemId = pid;
    }
    G.round = s.turn ?? G.round;
    const rbEl = document.getElementById('rb');
    if (rbEl) rbEl.textContent = 'ROUND ' + G.round;
    renderAvatar('player');
    renderAvatar('enemy');
    updateCardMeta('player');
    updateCardMeta('enemy');
    rebuildS('player');
    rebuildS('enemy');
    capturePveRematchTeamFromState(s);
  }

  /** Play damage/heal/crit visual effects when state changes from API */
  function playStateChangeEffects(prevPlayerHp, prevEnemyHp, s, newLogStart) {
    const p = s.player || {}, e = s.enemy || {};
    const newPlayerHp = p.hp ?? 0;
    const newEnemyHp = e.hp ?? 0;
    const entries = s.log || [];
    const newEntries = newLogStart < entries.length ? entries.slice(newLogStart) : [];
    const hasCrit = newEntries.some(function (x) { return x.type === 'crit'; });
    const lastEntry = newEntries.length > 0 ? newEntries[newEntries.length - 1] : null;
    const lastActor = (lastEntry && lastEntry.actor) ? String(lastEntry.actor).toLowerCase() : '';

    const pc = document.getElementById('pc');
    const ec = document.getElementById('ec');

    const shieldBlock = newEntries.some(function (x) { return x.type === 'shield_block' || x.reason === 'defending'; });
    if (shieldBlock && lastActor) {
      const blockedSide = lastActor === 'player' ? 'enemy' : 'player';
      const targetCard = blockedSide === 'enemy' ? ec : pc;
      if (targetCard) {
        mwAudio(function (A) { A.play('defend'); });
        spawnN(targetCard, 'BLOCKED', 'block');
        fx(blockedSide, 'flicker', 350);
      }
    }

    if (prevEnemyHp > newEnemyHp) {
      const dmg = prevEnemyHp - newEnemyHp;
      if (ec) {
        mwAudio(function (A) { A.playHit(hasCrit); });
        spawnN(ec, (hasCrit ? '✦ ' : '-') + dmg, hasCrit ? 'crit' : 'dmg');
        ec.classList.add('hit');
        setTimeout(function () { if (ec) ec.classList.remove('hit'); }, 200);
        fx('enemy', 'shaking', 460);
        fx('enemy', 'flicker', 350);
      }
      if (hasCrit) sflash();
    }
    if (prevPlayerHp > newPlayerHp) {
      const dmg = prevPlayerHp - newPlayerHp;
      if (pc) {
        mwAudio(function (A) { A.playHit(hasCrit); });
        spawnN(pc, (hasCrit ? '✦ ' : '-') + dmg, hasCrit ? 'crit' : 'dmg');
        pc.classList.add('hit');
        setTimeout(function () { if (pc) pc.classList.remove('hit'); }, 200);
        fx('player', 'shaking', 460);
        fx('player', 'flicker', 350);
      }
      if (hasCrit) sflash();
    }
    var playerHeals = newEntries.filter(function (x) { return x.type === 'heal' && (x.actor || '').toLowerCase() === 'player'; });
    var enemyHeals = newEntries.filter(function (x) { return x.type === 'heal' && (x.actor || '').toLowerCase() === 'enemy'; });
    if (prevPlayerHp < newPlayerHp && pc) {
      mwAudio(function (A) { A.playHeal(); });
      spawnN(pc, '+' + (newPlayerHp - prevPlayerHp), 'heal');
      fx('player', 'healing', 700);
      sflash('rgba(0,255,136,.07)');
    } else if (playerHeals.length > 0 && pc) {
      var m = (playerHeals[0].msg || '').match(/healed\s+(\d+)\s+HP/i);
      mwAudio(function (A) { A.playHeal(); });
      spawnN(pc, '+' + (m ? m[1] : '?'), 'heal');
      fx('player', 'healing', 700);
      sflash('rgba(0,255,136,.07)');
    }
    if (prevEnemyHp < newEnemyHp && ec) {
      mwAudio(function (A) { A.playHeal(); });
      spawnN(ec, '+' + (newEnemyHp - prevEnemyHp), 'heal');
      fx('enemy', 'healing', 700);
      sflash('rgba(0,255,136,.07)');
    } else if (enemyHeals.length > 0 && ec) {
      var m2 = (enemyHeals[0].msg || '').match(/healed\s+(\d+)\s+HP/i);
      mwAudio(function (A) { A.playHeal(); });
      spawnN(ec, '+' + (m2 ? m2[1] : '?'), 'heal');
      fx('enemy', 'healing', 700);
      sflash('rgba(0,255,136,.07)');
    }
    if (newPlayerHp <= 0 && pc) setTimeout(function () { fx('player', 'dead', 1400); }, 150);
    if (newEnemyHp <= 0 && ec) setTimeout(function () { fx('enemy', 'dead', 1400); }, 150);
    var nextEnemyMsg = newEntries.some(function (x) { return (x.msg || '').indexOf('Next opponent entering') >= 0; });
    var nextPlayerMsg = newEntries.some(function (x) { return (x.msg || '').indexOf('Next avatar deployed') >= 0; });
    if (nextEnemyMsg && ec) {
      ec.classList.remove('dead', 'hit', 'shaking', 'healing', 'flicker', 'card-activation');
      void ec.offsetWidth;
      ec.classList.add('card-activation');
      setTimeout(function () { if (ec) ec.classList.remove('card-activation'); }, 520);
    }
    if (nextPlayerMsg && pc) {
      pc.classList.remove('dead', 'hit', 'shaking', 'healing', 'flicker', 'player-activation');
      updatePlayerUI();
      void pc.offsetWidth;
      pc.classList.add('player-activation');
      setTimeout(function () { if (pc) pc.classList.remove('player-activation'); }, 520);
    }
  }

  function appendLogFromState(s, reset) {
    const entries = s.log || [];
    const lb = document.getElementById('lb');
    if (!lb) return;
    const start = reset ? 0 : (G.lastLogLength || 0);
    G.lastLogLength = entries.length;
    for (let i = start; i < entries.length; i++) {
      const entry = entries[i];
      const msg = entry.msg || '';
      const actor = (entry.actor || '').toLowerCase();
      const type = actor === 'player' ? 'pa' : actor === 'enemy' ? 'ea' : 'sys';
      const d = document.createElement('div');
      d.className = 'le ' + type;
      d.innerHTML = '<span class="trn">R' + (entry.turn || G.round || 1) + '</span>' + (msg || '—');
      lb.appendChild(d);
    }
    lb.scrollTop = lb.scrollHeight;
  }

  function createActionId(action) {
    return action + '_' + Date.now() + '_' + Math.random().toString(36).slice(2);
  }

  function cancelEnemyTurnSchedule() {
    if (G.enemyTurnTimer != null) {
      clearTimeout(G.enemyTurnTimer);
      G.enemyTurnTimer = null;
    }
  }

  function scheduleEnemyTurn(delayMs) {
    cancelEnemyTurnSchedule();
    const ms = delayMs != null ? delayMs : 800;
    G.enemyTurnTimer = setTimeout(function () {
      G.enemyTurnTimer = null;
      resolveEnemyTurn();
    }, ms);
  }

  async function performAction(action) {
    if (!G.battleToken || G.busy) return;
    const epoch = G.battleEpoch;
    G.busy = true;
    rbtn();
    if (action !== 'heal') {
      mwAudio(function (A) { A.playAction(action); });
    }

    try {
      const fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('battle_token', G.battleToken);
      fd.append('action', action);
      fd.append('action_id', createActionId(action));

      const data = await fetchApi('/api/mind-wars/perform_action.php', { method: 'POST', body: fd });
      if (G.battleEpoch !== epoch) {
        G.busy = false;
        rbtn();
        return;
      }
      const s = normalizeBattleStateForViewer(data.state || {});
      const battleOver = !!data.battle_over;
      const result = data.result || null;
      const rewards = data.rewards || null;

      const prevPlayerHp = G.player?.hp ?? 0;
      const prevEnemyHp = G.enemy?.hp ?? 0;
      const newLogStart = G.lastLogLength || 0;

      applyApiState(s);
      playStateChangeEffects(prevPlayerHp, prevEnemyHp, s, newLogStart);
      G.turn = s.next_actor || 'player';
      G.busy = (G.turn === 'enemy' && !battleOver);

      if (battleOver) {
        cancelEnemyTurnSchedule();
        stopPvpStatePolling();
        G.busy = true;
        rbtn();
        appendLogFromState(s);
        showEndgame(result || 'lose', rewards);
        return;
      }

      appendLogFromState(s);
      orbs('player');
      orbs('enemy');
      rfhp('player');
      rfhp('enemy');
      setTurn(G.turn);
      rbtn();
      rotI();

      if (G.turn === 'enemy' && !G.isPvpRanked) {
        scheduleEnemyTurn(800);
      } else if (G.turn === 'enemy' && G.isPvpRanked) {
        startPvpStatePolling();
      }
    } catch (err) {
      console.error('Mind Wars performAction:', err);
      G.busy = false;
      rbtn();
      log('<span style="color:var(--red)">Action failed: ' + (err.message || '') + '</span>', 'sys');
    }
  }

  async function resolveEnemyTurn() {
    if (G.isPvpRanked) {
      G.busy = (G.turn === 'enemy');
      rbtn();
      return;
    }
    if (!G.battleToken || G.turn !== 'enemy') {
      G.busy = false;
      G.turn = 'player';
      setTurn('player');
      rbtn();
      return;
    }
    const epoch = G.battleEpoch;
    let safety = 0;
    while (G.turn === 'enemy' && G.battleToken && safety < 12) {
      if (G.battleEpoch !== epoch) {
        G.busy = false;
        rbtn();
        return;
      }
      safety++;
      try {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('battle_token', G.battleToken);
        fd.append('action', 'advance');
        fd.append('action_id', createActionId('advance'));

        const data = await fetchApi('/api/mind-wars/perform_action.php', { method: 'POST', body: fd });
        if (G.battleEpoch !== epoch) {
          G.busy = false;
          rbtn();
          return;
        }
        const s = normalizeBattleStateForViewer(data.state || {});
        const battleOver = !!data.battle_over;
        const result = data.result || null;
        const rewards = data.rewards || null;

        const prevPlayerHp = G.player?.hp ?? 0;
        const prevEnemyHp = G.enemy?.hp ?? 0;
        const newLogStart = G.lastLogLength || 0;

        applyApiState(s);
        playStateChangeEffects(prevPlayerHp, prevEnemyHp, s, newLogStart);
        G.turn = s.next_actor || 'player';

        appendLogFromState(s);
        orbs('player');
        orbs('enemy');
        rfhp('player');
        rfhp('enemy');
        setTurn(G.turn);
        rotI();

        if (battleOver) {
          cancelEnemyTurnSchedule();
          stopPvpStatePolling();
          G.busy = true;
          rbtn();
          showEndgame(result || 'lose', rewards);
          return;
        }

        if (G.turn === 'player') break;
      } catch (err) {
        console.error('enemyTurn advance failed:', err);
        G.busy = false;
        G.turn = 'player';
        setTurn('player');
        rbtn();
        log('<span style="color:var(--red)">Enemy turn failed.</span>', 'sys');
        return;
      }
    }
    G.busy = false;
    setTurn('player');
    rbtn();
    orbs('player');
    orbs('enemy');
  }

  function orbs(w) {
    const s = G[w], el = document.getElementById(w === 'player' ? 'po' : 'eo');
    if (!el) return;
    el.innerHTML = '';
    for (let i = 0; i < s.maxE; i++) {
      const o = document.createElement('div');
      o.className = 'orb' + (i < s.energy ? ' on' : '');
      el.appendChild(o);
    }
    const ev = document.getElementById(w === 'player' ? 'pev' : 'eev');
    if (ev) ev.textContent = s.energy + ' / ' + s.maxE;
  }

  function rfhp(w) {
    const s = G[w], p = Math.max(0, s.hp / s.max * 100);
    const b = document.getElementById(w === 'player' ? 'phb' : 'ehb');
    if (b) {
      b.style.width = p + '%';
      b.classList.toggle('warn', p <= 50 && p > 25);
      b.classList.toggle('crit', p <= 25);
    }
    const hv = document.getElementById(w === 'player' ? 'phv' : 'ehv');
    if (hv) hv.textContent = Math.max(0, s.hp) + ' / ' + s.max;
  }

  function rbtn() {
    const ok = G.turn === 'player' && !G.busy, en = G.player?.energy ?? 0;
    const useApi = !!G.battleToken;
    const abilityCd = G.player?.ability_cooldown ?? 0;
    const hasAbility = !!(G.player?.ability_code);
    const hasSpecial = !!(G.player?.special_code);
    const hasHeal = !!(G.player?.heal_code);
    const canAbility = useApi ? (hasAbility && abilityCd <= 0 && en >= 2) : (en >= 2);
    const canSpecial = useApi ? (hasSpecial && en >= 5) : (en >= 4);
    const canHeal = useApi ? (hasHeal && en >= 2) : (en >= 2);

    ['a', 'd', 'b', 's', 'h'].forEach((x, i) => {
      const keys = ['attack', 'defend', 'ability', 'special', 'heal'];
      const b = document.getElementById('b' + x);
      if (!b) return;
      if (keys[i] === 'heal') {
        b.style.display = hasHeal ? '' : 'none';
        b.disabled = !ok || !canHeal;
        return;
      }
      if (keys[i] === 'ability') b.disabled = !ok || !canAbility;
      else if (keys[i] === 'special') b.disabled = !ok || !canSpecial;
      else b.disabled = !ok || en < COSTS[keys[i]];
    });
    const bh = document.getElementById('bh');
    if (bh) bh.style.display = hasHeal ? '' : 'none';
    const bs = document.getElementById('bs');
    if (bs) bs.classList.toggle('sr', ok && canSpecial);
    const pc = document.getElementById('pc');
    if (pc) pc.classList.toggle('sready', ok && canSpecial);
  }

  function spawnN(card, txt, type) {
    if (!card) return;
    const r = card.getBoundingClientRect(), el = document.createElement('div');
    el.className = 'fnum ' + type;
    el.textContent = txt;
    el.style.left = (r.left + r.width * (0.25 + Math.random() * 0.5)) + 'px';
    el.style.top = (r.top + r.height * (0.15 + Math.random() * 0.35)) + 'px';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 1250);
  }

  function sflash(col) {
    const f = document.getElementById('sf');
    if (!f) return;
    f.style.background = col || 'rgba(255,30,50,.14)';
    f.classList.add('pop');
    setTimeout(() => f.classList.remove('pop'), 80);
  }

  function fx(who, cls, dur) {
    const c = document.getElementById(who === 'player' ? 'pc' : 'ec');
    if (!c) return;
    c.classList.add(cls);
    setTimeout(() => c.classList.remove(cls), dur || 500);
  }

  function setTurn(w) {
    if (prevTurnAudio !== null && prevTurnAudio !== w) {
      mwAudio(function (A) { A.playTurn(); });
    }
    prevTurnAudio = w;
    const pc = document.getElementById('pc'), ec = document.getElementById('ec');
    if (pc) pc.classList.toggle('aturn', w === 'player');
    if (ec) ec.classList.toggle('aturn', w === 'enemy');
    const tp = document.getElementById('tp');
    if (tp) {
      tp.textContent = w === 'player' ? 'YOUR TURN' : 'ENEMY TURN';
      tp.className = w === 'player' ? 'tpill pt' : 'tpill et';
    }
  }

  function log(html, type) {
    const b = document.getElementById('lb');
    if (!b) return;
    const d = document.createElement('div');
    d.className = 'le ' + (type === 'player' ? 'pa' : type === 'enemy' ? 'ea' : 'sys');
    d.innerHTML = `<span class="trn">R${G.round}</span>${html}`;
    b.appendChild(d);
    b.scrollTop = b.scrollHeight;
  }

  function clearLog() {
    document.getElementById('lb').innerHTML = '<div class="le sys">— Log cleared. —</div>';
  }

  function dealDmg(target, amount, isCrit) {
    const s = G[target], card = document.getElementById(target === 'player' ? 'pc' : 'ec');
    if (!s || !card) return;
    if (s.shield) {
      s.shield = false;
      rebuildS(target);
      spawnN(card, 'BLOCKED', 'block');
      log(`<span class="actor">${s.name}</span>'s shield absorbs the attack!`, target === 'player' ? 'player' : 'enemy');
      fx(target, 'flicker', 350);
      return;
    }
    s.hp = Math.max(0, s.hp - amount);
    rfhp(target);
    spawnN(card, (isCrit ? '✦ ' : '-') + amount, isCrit ? 'crit' : 'dmg');
    card.classList.add('hit');
    setTimeout(() => card.classList.remove('hit'), 200);
    fx(target, 'shaking', 460);
    fx(target, 'flicker', 350);
    if (isCrit) sflash();
    if (s.hp <= 0) {
      setTimeout(() => fx(target, 'dead', 1400), 150);
      log(`<span class="actor">${s.name}</span> has been <span class="dt">ELIMINATED</span>!`, target === 'player' ? 'player' : 'enemy');
      G.busy = true;
      rbtn();
      if (target === 'enemy') {
        G.wins++;
        const wc = document.getElementById('wc');
        if (wc) wc.textContent = G.wins;
        setTimeout(() => showEndgame('win'), 1600);
      } else {
        setTimeout(() => showEndgame('lose'), 1600);
      }
    }
  }

  function doHeal(target, amount) {
    const s = G[target], card = document.getElementById(target === 'player' ? 'pc' : 'ec');
    s.hp = Math.min(s.max, s.hp + amount);
    rfhp(target);
    spawnN(card, '+' + amount, 'heal');
    fx(target, 'healing', 700);
    sflash('rgba(0,255,136,.07)');
  }

  function rebuildS(w) {
    const s = G[w], el = document.getElementById(w === 'player' ? 'ps' : 'es');
    if (!el) return;
    el.innerHTML = '';
    if (s && s.shield) el.innerHTML = '<span class="sbadge shield">SHIELD ×1</span>';
  }

  function rotI() {
    const ei = document.getElementById('ei');
    if (ei) ei.textContent = INTENTS[Math.floor(Math.random() * INTENTS.length)];
  }

  function getBaseDamage() {
    return 60 + ~~(Math.random() * 45);
  }

  function ability() {
    const player = G.player, enemy = G.enemy;
    const base = getBaseDamage();
    const d = Math.floor(base + (player.stats.mnd * 0.6));
    G.enemy.stats.fcs = Math.max(0, (G.enemy.stats.fcs ?? 50) - 5);
    dealDmg('enemy', d, false);
    log(`<span class="actor">${player.name}</span> uses ability for <span class="dt">${d}</span> damage.`, 'player');
  }

  function special() {
    const player = G.player, enemy = G.enemy;
    let base = getBaseDamage();
    let d = base * 1.8 + (player.stats.mnd * 0.8);
    if (player.class === 'TANK') {
      player.shield = true;
      rebuildS('player');
    }
    if (player.class === 'STRATEGIST') {
      enemy.energy = Math.max(0, (enemy.energy ?? 0) - 1);
      orbs('enemy');
    }
    if (player.class === 'TRICKSTER' && Math.random() < 0.25) {
      d *= 2;
      log('Critical trick!', 'sys');
    }
    d = Math.floor(d);
    dealDmg('enemy', d, false);
    log(`<span class="actor">${player.name}</span> uses special for <span class="dt">${d}</span> damage.`, 'player');
  }

  function healAction() {
    const player = G.player;
    if (!player.skills?.heal) {
      log('<span class="actor">' + player.name + '</span> has no healing ability.', 'sys');
      return;
    }
    let healAmount = player.stats.fcs * 0.8;
    if (player.class === 'SUPPORT') healAmount *= 1.2;
    const amt = Math.floor(healAmount);
    doHeal('player', amt);
    log(`<span class="actor">${player.name}</span> heals for <span class="ht">${amt} HP</span>.`, 'player');
  }

  function act(action) {
    if (G.turn !== 'player' || G.busy) return;
    if (!G.battleToken) return;
    if (!['attack', 'defend', 'ability', 'special', 'heal'].includes(action)) return;
    if (action === 'heal') {
      if (!G.player?.heal_code) return;
      if ((G.player?.energy ?? 0) < 2) return;
    }
    if (action === 'ability' && ((G.player?.ability_cooldown ?? 0) > 0)) return;
    if (action === 'ability' && !G.player?.ability_code) return;
    if (action === 'special' && (G.player?.energy ?? 0) < 5) return;
    if (action === 'special' && !G.player?.special_code) return;
    performAction(action);
  }

  function openA(w) {
    const s = G[w], col = RC[s.rarity] || RC.common;
    document.getElementById('mn').textContent = s.name;
    const mr = document.getElementById('mr');
    mr.textContent = s.rarity.toUpperCase();
    mr.style.setProperty('--rc', col);
    document.getElementById('ml').textContent = s.lore;
    const port = document.getElementById('mp');
    port.style.background = w === 'enemy' ? 'radial-gradient(ellipse 80% 70% at 50% 40%,rgba(120,0,180,.28) 0%,transparent 70%),var(--surface)' : 'radial-gradient(ellipse 80% 70% at 50% 40%,rgba(0,70,130,.28) 0%,transparent 70%),var(--surface)';
    const mf = document.getElementById('mf');
    if (s.image) {
      mf.innerHTML = '';
      const img = document.createElement('img');
      img.src = s.image;
      img.alt = '';
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'contain';
      const ec = w === 'enemy' ? 'rgba(212,0,255,' : 'rgba(0,240,255,';
      const silHtml = `<div class="avsil" style="height:160px;justify-content:flex-end"><div class="sh" style="border-color:${ec}.4);box-shadow:0 0 20px ${ec}.18)"></div><div class="sn" style="border-color:${ec}.2);background:${ec}.12)"></div><div class="st" style="border-color:${ec}.22);background:linear-gradient(180deg,${ec}.2),${ec}.05))"></div><div class="sl" style="width:64px;height:40px"><div class="slg" style="border-color:${ec}.18);background:linear-gradient(180deg,${ec}.16),${ec}.04))"></div><div class="slg" style="border-color:${ec}.18);background:linear-gradient(180deg,${ec}.16),${ec}.04))"></div></div></div>`;
      img.onerror = () => { mf.innerHTML = silHtml; };
      mf.appendChild(img);
    } else {
      const ec = w === 'enemy' ? 'rgba(212,0,255,' : 'rgba(0,240,255,';
      mf.innerHTML = `<div class="avsil" style="height:160px;justify-content:flex-end"><div class="sh" style="border-color:${ec}.4);box-shadow:0 0 20px ${ec}.18)"></div><div class="sn" style="border-color:${ec}.2);background:${ec}.12)"></div><div class="st" style="border-color:${ec}.22);background:linear-gradient(180deg,${ec}.2),${ec}.05))"></div><div class="sl" style="width:64px;height:40px"><div class="slg" style="border-color:${ec}.18);background:linear-gradient(180deg,${ec}.16),${ec}.04))"></div><div class="slg" style="border-color:${ec}.18);background:linear-gradient(180deg,${ec}.16),${ec}.04))"></div></div></div>`;
    }
    document.getElementById('ms').innerHTML = Object.entries(s.stats).map(([k, v]) => `<div class="mst"><div class="mstr"><span class="mstl">${SL[k]}</span><span class="mstv">${v}</span></div><div class="mstbar"><div class="mstfil" style="--sc:${SC[k]};width:${Math.min(100, v)}%"></div></div></div>`).join('');
    document.getElementById('modal').classList.add('open');
  }

  function closeA() {
    document.getElementById('modal').classList.remove('open');
  }

  document.getElementById('modal').addEventListener('click', e => {
    if (e.target === document.getElementById('modal')) closeA();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeA();
  });

  const EG_DATA = {
    win: { result: 'VICTORY', sub: 'YOUR SIGNAL RESONATES ACROSS THE VOID', color: '#00f0ff', bgClass: 'win', flavors: ['Your neural pathways proved superior. The void remembers your name.', "{ENEMY}'s calculations were flawless — yet yours were one step ahead.", 'The storm you commanded was unstoppable. Another mind falls silent.'] },
    lose: { result: 'DEFEAT', sub: 'YOUR SIGNAL HAS BEEN SEVERED', color: '#d400ff', bgClass: 'lose', flavors: ["{ENEMY}'s seventeen dimensions collapsed your every strategy.", '{ENEMY} saw the future before you could act. Train harder. Return stronger.', 'The void consumed you — but a signal can always be rebooted.'] },
    draw: { result: 'DRAW', sub: 'NEITHER SIGNAL DOMINATED', color: '#ffc820', bgClass: 'draw', flavors: ['Two minds met at full strength — and neither broke.', 'The arena records a stalemate. Honor the equal.', 'Power met power; the void holds both names for another day.'] },
    surrender: { result: 'SURRENDER', sub: 'STRATEGIC WITHDRAWAL LOGGED', color: '#ff2244', bgClass: 'surrender', flavors: ['Sometimes wisdom is knowing when to step back from the battlefield.', 'The battle was abandoned. The arena will not forget.', 'You chose survival over glory. The war continues elsewhere.'] }
  };

  function fillEndgameConfetti(typeNorm) {
    const cf = document.getElementById('endgame-confetti');
    if (!cf) return;
    cf.innerHTML = '';
    const w = typeNorm === 'win' || typeNorm === 'draw';
    if (w) {
      const colors = ['#00f0ff', '#d400ff', '#ffc820', '#00ff88', '#ff2244', '#ffffff'];
      for (let i = 0; i < 60; i++) {
        const p = document.createElement('div');
        p.className = 'confetti-p';
        p.style.cssText = `left:${Math.random() * 100}%;top:-10px;width:${4 + Math.random() * 6}px;height:${6 + Math.random() * 10}px;background:${colors[~~(Math.random() * colors.length)]};border-radius:${Math.random() > 0.5 ? '50%' : '1px'};--dur:${2 + Math.random() * 3}s;--delay:${Math.random() * 1.5}s;--rot:${360 + Math.random() * 720}deg`;
        cf.appendChild(p);
      }
    } else {
      for (let i = 0; i < 30; i++) {
        const p = document.createElement('div');
        p.className = 'confetti-p';
        p.style.cssText = `left:${Math.random() * 100}%;top:-10px;width:${2 + Math.random() * 3}px;height:${2 + Math.random() * 3}px;background:rgba(${150 + ~~(Math.random() * 60)},${50 + ~~(Math.random() * 40)},${80 + ~~(Math.random() * 40)},0.6);border-radius:50%;--dur:${4 + Math.random() * 4}s;--delay:${Math.random() * 2}s;--rot:${Math.random() * 360}deg`;
        cf.appendChild(p);
      }
    }
  }

  function clearEndgameConfetti() {
    const cf = document.getElementById('endgame-confetti');
    if (cf) cf.innerHTML = '';
  }

  function showEndgame(type, apiRewards) {
    const typeNorm = normalizeEndgameType(type);
    G.lastResult = typeNorm;
    const submitResult = typeNorm === 'surrender' ? 'lose' : typeNorm;
    const winV = submitResult === 'win' ? { xp: 20, rank: 20, ke: 10 } : { xp: 8, rank: 5, ke: 4 };

    function renderOverlay(rewards) {
      mwAudio(function (A) { A.stopBgm(); });
      if (typeNorm === 'win') {
        mwAudio(function (A) { A.playResult('win'); });
      } else if (typeNorm === 'lose' || typeNorm === 'surrender') {
        mwAudio(function (A) { A.playResult('lose'); });
      } else if (typeNorm === 'draw') {
        mwAudio(function (A) { A.playTurn(); });
      }
      const data = EG_DATA[typeNorm] || EG_DATA.lose;
      const overlay = document.getElementById('endgame');
      if (!overlay) return;
      const xpG = (rewards.xp != null && rewards.xp > 0) ? rewards.xp : winV.xp;
      const rankG = (rewards.rank != null && rewards.rank > 0) ? rewards.rank : winV.rank;
      const keG = (rewards.knowledge_energy != null && rewards.knowledge_energy > 0) ? rewards.knowledge_energy : winV.ke;
      const dmgDealt = G.enemy ? (G.enemy.max - Math.max(0, G.enemy.hp)) : 0;
      const dmgTaken = G.player ? (G.player.max - Math.max(0, G.player.hp)) : 0;
      const en = G.enemy && G.enemy.name ? G.enemy.name : 'Enemy';
      const flav = data.flavors[~~(Math.random() * data.flavors.length)].replace(/\{ENEMY\}/g, en);

      fillEndgameConfetti(typeNorm);

      overlay.style.setProperty('--egc', data.color);
      overlay.className = '';
      void overlay.offsetWidth;
      overlay.classList.add('show', data.bgClass);

      const titleEl = document.getElementById('eg-result');
      const subEl = document.getElementById('eg-sub');
      const d1 = document.getElementById('egd1');
      const d2 = document.getElementById('egd2');
      const xpWrap = document.getElementById('egxp');
      const xpFill = document.getElementById('egxpf');
      const xpVal = document.getElementById('egxpv');
      const statsEl = document.getElementById('eg-stats');
      const rwEl = document.getElementById('eg-rw');
      const quoteEl = document.getElementById('eg-flavor');
      const btnsEl = document.getElementById('eg-btns');
      const blEl = document.getElementById('egbl');
      const tlEl = document.getElementById('egtl');

      [tlEl, titleEl, subEl, d1, d2, xpWrap, quoteEl, btnsEl, blEl].forEach(function (el) { if (el) el.classList.remove('anim'); });
      if (titleEl) {
        titleEl.className = 'eg-res';
        titleEl.textContent = data.result;
      }
      if (subEl) {
        subEl.className = 'eg-sub';
        subEl.textContent = data.sub;
      }
      if (quoteEl) {
        quoteEl.textContent = '';
        quoteEl.classList.remove('anim');
      }
      if (xpFill && xpVal) {
        xpFill.style.width = '0%';
        xpFill.classList.toggle('lose', typeNorm !== 'win');
        xpVal.textContent = '+0 XP';
      }
      if (statsEl) statsEl.innerHTML = '';
      if (rwEl) rwEl.innerHTML = '';

      setTimeout(function () { if (tlEl) tlEl.classList.add('anim'); }, 50);
      setTimeout(function () { if (titleEl) titleEl.classList.add('anim'); }, 400);
      setTimeout(function () { if (subEl) subEl.classList.add('anim'); }, 650);
      setTimeout(function () { if (d1) d1.classList.add('anim'); }, 850);

      const statData = [
        { v: G.round, l: 'ROUNDS' },
        { v: dmgDealt, l: 'DMG DEALT' },
        { v: dmgTaken, l: 'DMG TAKEN' }
      ];
      statData.forEach(function (s, i) {
        const si = document.createElement('div');
        si.className = 'eg-si';
        si.innerHTML = '<div class="eg-sv" id="egsv' + i + '">0</div><div class="eg-sl">' + s.l + '</div>';
        statsEl.appendChild(si);
        setTimeout(function () {
          si.classList.add('anim');
          const target = s.v;
          const svEl = document.getElementById('egsv' + i);
          const countDur = 600;
          const t0 = performance.now();
          function tick() {
            const p = Math.min((performance.now() - t0) / countDur, 1);
            const ease = 1 - Math.pow(1 - p, 3);
            if (svEl) svEl.textContent = Math.round(target * ease).toLocaleString();
            if (p < 1) requestAnimationFrame(tick);
          }
          tick();
        }, 1000 + i * 200);
      });

      setTimeout(function () { if (d2) d2.classList.add('anim'); }, 1650);

      const maxXP = 100;
      setTimeout(function () {
        if (xpWrap) xpWrap.classList.add('anim');
        setTimeout(function () {
          if (xpFill) {
            const pct = Math.min(100, (xpG / maxXP) * 100);
            xpFill.style.width = pct + '%';
          }
          const t0 = performance.now();
          function tickXP() {
            const p = Math.min((performance.now() - t0) / 1200, 1);
            const ease = 1 - Math.pow(1 - p, 3);
            if (xpVal) xpVal.textContent = '+' + Math.round(xpG * ease) + ' XP';
            if (p < 1) requestAnimationFrame(tickXP);
          }
          tickXP();
        }, 150);
      }, 1800);

      const rewardRows = [
        { val: '+' + xpG, cls: 'xp', label: 'XP' },
        { val: (rankG > 0 ? '+' : '') + rankG, cls: 'rk', label: 'RANK' },
        { val: '+' + keG, cls: 'ke', label: 'KE' }
      ];
      rewardRows.forEach(function (r, i) {
        const ri = document.createElement('div');
        ri.className = 'eg-ri';
        ri.innerHTML = '<span class="eg-rv ' + r.cls + '">' + r.val + '</span><span class="eg-ri-lbl">' + r.label + '</span>';
        rwEl.appendChild(ri);
        setTimeout(function () { ri.classList.add('anim'); }, 2400 + i * 180);
      });

      setTimeout(function () {
        if (quoteEl) quoteEl.classList.add('anim');
        let ci = 0;
        function typeChar() {
          if (!quoteEl) return;
          if (ci < flav.length) {
            quoteEl.textContent = '"' + flav.substring(0, ci + 1);
            ci++;
            setTimeout(typeChar, 25 + Math.random() * 15);
          } else {
            quoteEl.textContent = '"' + flav + '"';
          }
        }
        quoteEl.textContent = '"';
        typeChar();
      }, 3100);

      setTimeout(function () { if (btnsEl) btnsEl.classList.add('anim'); }, 3800);
      setTimeout(function () { if (blEl) blEl.classList.add('anim'); }, 3900);
    }

    if (apiRewards && G.battleToken) {
      renderOverlay(apiRewards);
      return;
    }

    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('avatar_item_id', String(G.selectedAvatarItemId || 0));
    fd.append('result', submitResult);
    fetch('/api/mind-wars/pve_submit.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(j => {
        const rw = (j.ok && j.data && j.data.rewards) ? j.data.rewards : { xp: 0, rank: 0, knowledge_energy: 0 };
        renderOverlay(rw);
      })
      .catch(() => {
        renderOverlay({});
      });
  }

  function hideEndgamePanel() {
    mwAudio(function (A) { A.stopBgm(); });
    const eg = document.getElementById('endgame');
    if (eg) eg.classList.remove('show');
    clearEndgameConfetti();
  }

  /** RETURN from victory/defeat → Mind Wars lobby */
  function closeEndgame() {
    hideEndgamePanel();
    window.location.href = '/games/mind-wars/lobby.php';
  }

  async function restartBattle() {
    if (G.isPvpRanked) {
      stopPvpStatePolling();
      window.location.href = '/games/mind-wars/lobby.php';
      return;
    }
    const rematchIds = (G.pveRematchItemIds && G.pveRematchItemIds.length === 3)
      ? G.pveRematchItemIds.slice()
      : null;
    cancelEnemyTurnSchedule();
    stopPvpStatePolling();
    G.battleToken = null;
    G.busy = true;
    rbtn();
    hideEndgamePanel();
    await new Promise(function (r) { setTimeout(r, 120); });
    if (rematchIds) {
      G.selectedAvatarSlots = rematchSlotsFromItemIds(rematchIds);
      G.selectedAvatarItemId = rematchIds[0];
      configureBattleMode('3v3');
      showBattleScreen();
      await initBattle({ preserveLog: false, activateEnemy: false });
      return;
    }
    if (G.selectedAvatarItemId) {
      configureBattleMode(G.battleMode);
      showBattleScreen();
      await initBattle({ preserveLog: false, activateEnemy: false });
      return;
    }
    G.round = 1;
    G.turn = 'player';
    G.busy = false;
    if (G.player) {
      G.player.hp = G.player.max;
      G.player.energy = Math.min(3, G.player.maxE);
      G.player.shield = false;
    }
    if (G.enemy) {
      G.enemy.hp = G.enemy.max;
      G.enemy.energy = Math.min(2, G.enemy.maxE);
      G.enemy.shield = Math.random() < ENEMY_START_SHIELD_CHANCE;
    }
    rfhp('player');
    rfhp('enemy');
    orbs('player');
    orbs('enemy');
    rebuildS('player');
    rebuildS('enemy');
    const rbEl = document.getElementById('rb');
    if (rbEl) rbEl.textContent = 'ROUND 1';
    resetTurnAudio();
    setTurn('player');
    rbtn();
    ['pc', 'ec'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.classList.remove('dead', 'aturn', 'shaking', 'healing', 'flicker', 'sready', 'hit');
    });
    const ec = document.getElementById('ec');
    if (ec) ec.classList.remove('aturn');
    const pc = document.getElementById('pc');
    if (pc) pc.classList.add('aturn');
    document.getElementById('lb').innerHTML = '';
    log('— New battle initiated. Round 1 begins. —', 'sys');
    rotI();
  }

  function showSurrenderConfirm() {
    if (G.busy && (G.player.hp <= 0 || G.enemy.hp <= 0)) return;
    document.getElementById('surrender-confirm').classList.add('show');
  }

  function closeSurrenderConfirm() {
    document.getElementById('surrender-confirm').classList.remove('show');
  }

  async function confirmSurrender() {
    closeSurrenderConfirm();
    G.pendingWaveTransition = false;
    G.busy = true;
    rbtn();
    if (G.battleToken) {
      try {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('battle_token', G.battleToken);
        const data = await fetchApi('/api/mind-wars/forfeit.php', { method: 'POST', body: fd });
        stopPvpStatePolling();
        const s = normalizeBattleStateForViewer(data.state || {});
        applyApiState(s);
        appendLogFromState(s);
        showEndgame('surrender', data.rewards || null);
      } catch (err) {
        console.error('Forfeit failed:', err);
        log('<span style="color:var(--red)">Surrender failed.</span>', 'sys');
        G.busy = false;
        rbtn();
      }
      return;
    }
    const pn = G.player && G.player.name ? G.player.name : 'Player';
    log(`— <span style="color:var(--red)">${pn} has surrendered.</span> Battle over. —`, 'sys');
    fx('player', 'dead', 1400);
    setTimeout(() => showEndgame('surrender'), 900);
  }

  window.showSurrenderConfirm = showSurrenderConfirm;
  window.closeSurrenderConfirm = closeSurrenderConfirm;
  window.confirmSurrender = confirmSurrender;
  window.act = act;
  window.openA = openA;
  window.closeA = closeA;
  window.clearLog = clearLog;
  window.restartBattle = restartBattle;
  window.closeEndgame = closeEndgame;

  /** PvP: canonical DB state is attacker=player; swap for viewer on enemy side (matches perform_action.php mw_pvp_swap_state). */
  function pvpSwapStateForViewer(state, viewerSide) {
    if (!state || viewerSide !== 'enemy') return state;
    const s = deepClone(state);
    const p = s.player;
    s.player = s.enemy;
    s.enemy = p;
    const next = (s.next_actor === 'enemy') ? 'player' : 'enemy';
    s.next_actor = next;
    const enemyCrit = !!(s.meta && s.meta.pvp_enemy_next_attack_crit);
    const playerCrit = !!s.player_next_attack_crit;
    s.player_next_attack_crit = enemyCrit;
    if (!s.meta || typeof s.meta !== 'object') s.meta = {};
    s.meta.pvp_enemy_next_attack_crit = playerCrit;
    if (Array.isArray(s.log)) {
      for (let i = 0; i < s.log.length; i++) {
        const e = s.log[i];
        if (!e || typeof e !== 'object') continue;
        const a = String(e.actor || '').toLowerCase();
        if (a === 'player') e.actor = 'enemy';
        else if (a === 'enemy') e.actor = 'player';
      }
    }
    return s;
  }

  /** Apply viewer-side orientation for PvP defender so UI "player" is always the logged-in user. */
  function normalizeBattleStateForViewer(state) {
    if (!state || !G.isPvpRanked || G.pvpViewerSide !== 'enemy') return state;
    return pvpSwapStateForViewer(state, 'enemy');
  }

  function stopPvpStatePolling() {
    if (pvpPollTimer != null) {
      clearInterval(pvpPollTimer);
      pvpPollTimer = null;
    }
  }

  function startPvpStatePolling() {
    stopPvpStatePolling();
    if (!G.isPvpRanked || !G.battleToken) return;
    if (G.turn !== 'enemy') return;
    const epoch = G.battleEpoch;
    pvpPollTimer = setInterval(function () {
      tickPvpStatePoll(epoch);
    }, PVP_POLL_MS);
  }

  function rewardsFromBattleStatePayload(data) {
    return {
      xp: data.xp_gained != null ? Number(data.xp_gained) : 0,
      rank: data.rank_gained != null ? Number(data.rank_gained) : 0,
      knowledge_energy: data.knowledge_energy_gained != null ? Number(data.knowledge_energy_gained) : 0
    };
  }

  function normalizeEndgameType(r) {
    const s = String(r || '').toLowerCase();
    if (s === 'win' || s === 'lose' || s === 'surrender' || s === 'draw') return s;
    return 'lose';
  }

  async function tickPvpStatePoll(startEpoch) {
    if (G.battleEpoch !== startEpoch || !G.isPvpRanked || !G.battleToken) {
      stopPvpStatePolling();
      return;
    }
    if (G.turn !== 'enemy') {
      stopPvpStatePolling();
      return;
    }
    try {
      const data = await fetchApi('/api/mind-wars/get_battle_state.php?battle_token=' + encodeURIComponent(G.battleToken));
      if (G.battleEpoch !== startEpoch) return;

      const rawResult = data.result;
      const ended = rawResult != null && String(rawResult).length > 0;
      const raw = data.state || {};
      const st = normalizeBattleStateForViewer(raw);

      if (ended) {
        stopPvpStatePolling();
        const prevPlayerHp = G.player?.hp ?? 0;
        const prevEnemyHp = G.enemy?.hp ?? 0;
        const newLogStart = G.lastLogLength || 0;
        applyApiState(st);
        playStateChangeEffects(prevPlayerHp, prevEnemyHp, st, newLogStart);
        appendLogFromState(st);
        G.turn = st.next_actor || 'player';
        G.busy = true;
        setTurn(G.turn);
        rbtn();
        showEndgame(normalizeEndgameType(rawResult), rewardsFromBattleStatePayload(data));
        return;
      }

      const logLen = Array.isArray(st.log) ? st.log.length : 0;
      const turnsPlayed = data.turns_played != null ? Number(data.turns_played) : null;
      const nextActor = st.next_actor || 'player';
      const logGrew = logLen > (G.lastLogLength || 0);
      const turnsAdvanced = turnsPlayed != null && Number.isFinite(turnsPlayed) && G.pvpLastTurnsPlayedSeen >= 0
        && turnsPlayed > G.pvpLastTurnsPlayedSeen;
      const myTurnNow = nextActor === 'player' && G.turn === 'enemy';

      if (!logGrew && !turnsAdvanced && !myTurnNow) return;

      if (turnsPlayed != null && Number.isFinite(turnsPlayed)) {
        G.pvpLastTurnsPlayedSeen = turnsPlayed;
      }

      const prevPlayerHp = G.player?.hp ?? 0;
      const prevEnemyHp = G.enemy?.hp ?? 0;
      const newLogStart = G.lastLogLength || 0;
      applyApiState(st);
      playStateChangeEffects(prevPlayerHp, prevEnemyHp, st, newLogStart);
      appendLogFromState(st);
      G.turn = nextActor;
      G.busy = (G.turn === 'enemy');
      orbs('player');
      orbs('enemy');
      rfhp('player');
      rfhp('enemy');
      setTurn(G.turn);
      rbtn();
      rotI();
      if (G.turn !== 'enemy') {
        stopPvpStatePolling();
      }
    } catch (e) {
      console.error('PvP state poll failed:', e);
    }
  }

  async function tryBootstrapFromBattleTokenUrl() {
    let token = '';
    try {
      const q = new URLSearchParams(window.location.search);
      token = (q.get('battle_token') || '').trim();
    } catch (e) { return false; }
    if (token.length < 32) return false;
    try {
      cancelEnemyTurnSchedule();
      stopPvpStatePolling();
      const data = await fetchApi('/api/mind-wars/get_battle_state.php?battle_token=' + encodeURIComponent(token));
      G.battleEpoch = (G.battleEpoch || 0) + 1;
      const raw = data.state || {};
      G.isPvpRanked = (raw.meta && raw.meta.mode === 'pvp_ranked');
      const vs = data.viewer_side;
      G.pvpViewerSide = G.isPvpRanked && (vs === 'player' || vs === 'enemy') ? vs : null;
      const st = normalizeBattleStateForViewer(raw);
      G.battleToken = data.battle_token || token;
      G.pvpLastTurnsPlayedSeen = data.turns_played != null ? Number(data.turns_played) : -1;

      const rawResultBoot = data.result;
      const battleAlreadyEnded = rawResultBoot != null && String(rawResultBoot).length > 0;
      if (battleAlreadyEnded) {
        applyApiState(st);
        G.lastLogLength = 0;
        const lbEnd = document.getElementById('lb');
        if (lbEnd) lbEnd.innerHTML = '';
        appendLogFromState(st, true);
        G.turn = st.next_actor || 'player';
        G.busy = true;
        showBattleScreen();
        resetTurnAudio();
        setTurn(G.turn);
        orbs('player');
        orbs('enemy');
        rfhp('player');
        rfhp('enemy');
        rbtn();
        rotI();
        showEndgame(normalizeEndgameType(rawResultBoot), rewardsFromBattleStatePayload(data));
        return true;
      }

      applyApiState(st);
      G.lastLogLength = 0;
      const lb = document.getElementById('lb');
      if (lb) lb.innerHTML = '';
      appendLogFromState(st, true);
      G.turn = st.next_actor || 'player';
      G.busy = (G.turn === 'enemy');
      showBattleScreen();
      resetTurnAudio();
      mwAudio(function (A) {
        A.playBattleStart();
        A.startBgm();
      });
      setTurn(G.turn);
      orbs('player');
      orbs('enemy');
      rfhp('player');
      rfhp('enemy');
      rbtn();
      rotI();
      if (G.turn === 'enemy' && !G.isPvpRanked) {
        scheduleEnemyTurn(800);
      } else if (G.isPvpRanked && G.turn === 'enemy') {
        startPvpStatePolling();
      }
      return true;
    } catch (e) {
      console.error('Battle token bootstrap failed:', e);
      const lb = document.getElementById('lb');
      if (lb) {
        lb.innerHTML = '<div class="le sys" style="color:var(--red)">— Could not load battle. ' + (e.message || '') + ' —</div>';
      }
      return false;
    }
  }

  window.addEventListener('pagehide', stopPvpStatePolling);

  window.addEventListener('DOMContentLoaded', async () => {
    (function initArenaSfxToggle() {
      const sfxToggle = document.getElementById('arena-sfx-toggle');
      if (!sfxToggle) return;
      syncArenaSfxToggleUi();
      sfxToggle.addEventListener('click', function () {
        mwAudio(function (A) {
          var m = A.toggleMuted();
          if (!m && G.battleToken) {
            A.startBgm();
          }
        });
        syncArenaSfxToggleUi();
      });
    })();

    if (await tryBootstrapFromBattleTokenUrl()) {
      return;
    }

    await loadLobbyAvatars();

    const playNowBtn = document.getElementById('arena-play-now-btn');
    const pveModeBtn = document.getElementById('lobby-mode-pve');
    const pve1v1Btn = document.getElementById('lobby-pve-1v1');
    const pve3v3Btn = document.getElementById('lobby-pve-3v3');
    const pveCancelBtn = document.getElementById('lobby-pve-submode-cancel');
    const setupBackBtn = document.getElementById('lobby-setup-back-btn');
    const setupPlayBtn = document.getElementById('lobby-setup-play-btn');
    const selector = document.getElementById('lobby-avatar-selector');
    const selectorClose = document.getElementById('lobby-avatar-selector-close');

    resetLobbyFlow();

    if (playNowBtn) {
      playNowBtn.onclick = function () {
        setLobbyState('MODE_PICK');
      };
    }

    if (pveModeBtn) {
      pveModeBtn.onclick = function () {
        setLobbyState('PVE_SUBMODE_PICK');
      };
    }

    if (pveCancelBtn) {
      pveCancelBtn.onclick = function () {
        setLobbyState('MODE_PICK');
      };
    }

    if (pve1v1Btn) {
      pve1v1Btn.onclick = function () {
        configureBattleMode('1v1');
        G.selectedAvatarSlots = [null];
        renderSetupSlots();
        setLobbyState('PVE_SETUP_1V1');
      };
    }

    if (pve3v3Btn) {
      pve3v3Btn.onclick = function () {
        configureBattleMode('3v3');
        G.selectedAvatarSlots = [null, null, null];
        renderSetupSlots();
        setLobbyState('PVE_SETUP_3V3');
      };
    }

    if (setupBackBtn) {
      setupBackBtn.onclick = function () {
        setLobbyState('PVE_SUBMODE_PICK');
      };
    }

    if (setupPlayBtn) {
      setupPlayBtn.onclick = function () {
        startSetupBattle();
      };
    }

    if (selectorClose) {
      selectorClose.onclick = function () {
        closeAvatarSelector();
      };
    }

    if (selector) {
      selector.addEventListener('click', function (e) {
        if (e.target === selector) closeAvatarSelector();
      });
    }
  });
})();
