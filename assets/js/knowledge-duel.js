(function () {
  'use strict';

  // ── Config ─────────────────────────────────────────────────────
  const cfg  = window.KnowledgeDuelConfig || {};
  const CSRF = cfg.csrfToken || '';

  // ── Flow steps (unchanged — backend compatibility) ──────────────
  const FLOW_STEPS = {
    IDLE:       'idle',
    CATEGORY:   'category',
    DIFFICULTY: 'difficulty',
    BATTLE:     'battle',
    RESULT:     'result'
  };

  // ── Step metadata — KND language ───────────────────────────────
  const STEP_META = {
    idle:       { label: 'Link Offline',    icon: 'fa-satellite-dish' },
    category:   { label: 'Domain Select',   icon: 'fa-layer-group' },
    difficulty: { label: 'Interference',    icon: 'fa-sliders-h' },
    battle:     { label: 'Link Active',     icon: 'fa-link' },
    result:     { label: 'Link Terminated', icon: 'fa-flag-checkered' }
  };

  // ── Category metadata — KND language ───────────────────────────
  const CATEGORY_META = {
    Tech:                   { icon: 'fa-microchip',        desc: 'Hardware, software & system protocols.' },
    Gaming:                 { icon: 'fa-gamepad',          desc: 'Consoles, genres & interactive history.' },
    Internet:               { icon: 'fa-globe',            desc: 'Web architecture, protocols & culture.' },
    AI:                     { icon: 'fa-robot',            desc: 'Machine cognition & neural systems.' },
    'General Geek':         { icon: 'fa-bolt',             desc: 'Mixed tactical trivia matrix.' },
    Sports:                 { icon: 'fa-futbol',           desc: 'Athletics, leagues & physical protocols.' },
    Entertainment:          { icon: 'fa-film',             desc: 'Cinema, broadcast & cultural data.' },
    Music:                  { icon: 'fa-music',            desc: 'Sonic patterns, artists & history.' },
    Science:                { icon: 'fa-flask',            desc: 'Physics, chemistry & natural laws.' },
    History:                { icon: 'fa-landmark',         desc: 'Temporal records & civilizational events.' },
    Geography:              { icon: 'fa-earth-americas',   desc: 'Spatial intelligence & territorial data.' },
    'Anime & Manga':        { icon: 'fa-star',             desc: 'Japanese animation & sequential art.' },
    Comics:                 { icon: 'fa-book-open',        desc: 'Superheroes, graphic lore & story arcs.' },
    Esports:                { icon: 'fa-trophy',           desc: 'Competitive gaming & tactical meta.' },
    'Memes & Internet Culture': { icon: 'fa-face-smile',   desc: 'Viral signals & digital culture nodes.' },
    'Startups & Business':  { icon: 'fa-briefcase',        desc: 'Economic systems & innovation vectors.' },
    Cybersecurity:          { icon: 'fa-shield-halved',    desc: 'Defense protocols & digital immunity.' },
    Programming:            { icon: 'fa-code',             desc: 'Logic constructs & development systems.' },
    'Space & Astronomy':    { icon: 'fa-satellite-dish',   desc: 'Cosmic exploration & stellar cartography.' },
    Mythology:              { icon: 'fa-dragon',           desc: 'Ancient narrative systems & lore protocols.' }
  };

  // ── Difficulty metadata — KND language ─────────────────────────
  const DIFFICULTY_META = {
    easy:   'Low-interference scan. Reduced cognitive load. Minimal rewards.',
    medium: 'Standard interference. Balanced extraction. Full progression.',
    hard:   'Maximum interference. Premium rewards. Neural resilience required.'
  };

  // ── Result language map ─────────────────────────────────────────
  const RESULT_MAP = {
    win:  { title: 'LINK VICTORIOUS',   subtitle: 'Neural dominance confirmed. Rewards extracted.', glyph: '⬡' },
    lose: { title: 'LINK SEVERED',      subtitle: 'Cognitive integrity compromised. Recalibrate.', glyph: '✕' },
    draw: { title: 'LINK STABILIZED',   subtitle: 'Inconclusive engagement. Partial rewards granted.', glyph: '◈' }
  };

  // ── Feedback messages ───────────────────────────────────────────
  const FEEDBACK_CORRECT = [
    'NEURAL RESPONSE CONFIRMED',
    'COGNITIVE STRIKE REGISTERED',
    'CORRECT — IMPACT DELIVERED',
    'SEQUENCE VALID — ENTITY DAMAGED',
    'LINK SYNCED — STRIKE SUCCESSFUL'
  ];
  const FEEDBACK_WRONG = [
    'LINK STABILITY REDUCED',
    'INCORRECT — INTEGRITY DEGRADED',
    'COGNITIVE MISFIRE DETECTED',
    'SEQUENCE INVALID — LINK DISRUPTED',
    'NEURAL INTERFERENCE — AGENT HIT'
  ];

  const OPTION_STATE_CLASSES = ['btn-success', 'btn-danger', 'kd-option-correct', 'kd-option-wrong', 'kd-option-selected'];

  // ── State (identical structure to original) ─────────────────────
  const state = {
    user: null,
    season: null,
    ranking: null,
    avatars: [],
    selectedAvatar: null,
    battle: null,
    flowStep: FLOW_STEPS.IDLE,
    selectedCategory: null,
    selectedDifficulty: null,
    availableCategories: [],
    difficultyRewards: {}
  };

  // ── DOM refs (same IDs as original — no HTML ID changes needed) ──
  const el = {
    avatarList:             document.getElementById('kd-avatar-list'),
    avatarEmpty:            document.getElementById('kd-avatar-empty'),
    openCategoryBtn:        document.getElementById('kd-open-category-btn'),
    startBtn:               document.getElementById('kd-start-btn'),
    playAgainBtn:           document.getElementById('kd-play-again'),
    changeCategoryBtn:      document.getElementById('kd-change-category'),
    changeAvatarBtn:        document.getElementById('kd-change-avatar'),
    categoryBackBtn:        document.getElementById('kd-category-back'),
    categoryContinueBtn:    document.getElementById('kd-category-continue'),
    difficultyBackBtn:      document.getElementById('kd-difficulty-back'),
    categoryGrid:           document.getElementById('kd-category-grid'),
    difficultyGrid:         document.getElementById('kd-difficulty-grid'),
    stepChip:               document.getElementById('kd-step-chip'),
    stepIdle:               document.getElementById('kd-step-idle'),
    stepCategory:           document.getElementById('kd-step-category'),
    stepDifficulty:         document.getElementById('kd-step-difficulty'),
    stepBattle:             document.getElementById('kd-step-battle'),
    stepResult:             document.getElementById('kd-step-result'),
    battlePlaceholder:      document.getElementById('kd-battle-placeholder'),
    questionMeta:           document.getElementById('kd-question-meta'),
    questionText:           document.getElementById('kd-question-text'),
    options:                document.getElementById('kd-options'),
    feedback:               document.getElementById('kd-feedback'),
    resultTitle:            document.getElementById('kd-result-title'),
    resultRewards:          document.getElementById('kd-result-rewards'),
    userProgressBar:        document.getElementById('kd-user-progress'),
    avatarProgressBar:      document.getElementById('kd-avatar-progress'),
    playerAvatar:           document.getElementById('kd-player-avatar'),
    playerName:             document.getElementById('kd-player-name'),
    playerAvatarBattle:     document.getElementById('kd-player-avatar-battle'),
    playerNameBattle:       document.getElementById('kd-player-name-battle'),
    enemyName:              document.getElementById('kd-enemy-name'),
    enemyNameBattle:        document.getElementById('kd-enemy-name-battle'),
    battleCategoryPill:     document.getElementById('kd-battle-category-pill'),
    battleDifficultyPill:   document.getElementById('kd-battle-difficulty-pill'),
    playerHp:               document.getElementById('kd-player-hp'),
    enemyHp:                document.getElementById('kd-enemy-hp'),
    playerHpBattle:         document.getElementById('kd-player-hp-battle'),
    enemyHpBattle:          document.getElementById('kd-enemy-hp-battle'),
    statXp:                 document.getElementById('kd-stat-xp'),
    statLevel:              document.getElementById('kd-stat-level'),
    statAvatar:             document.getElementById('kd-stat-avatar'),
    statKe:                 document.getElementById('kd-stat-ke'),
    statAvatarLevel:        document.getElementById('kd-stat-avatar-level'),
    statRank:               document.getElementById('kd-stat-rank'),
    statPos:                document.getElementById('kd-stat-position'),
    statXpDetail:           document.getElementById('kd-stat-xp-detail'),
    statKeDetail:           document.getElementById('kd-stat-ke-detail'),
    userPanelProgress:      document.getElementById('kd-user-panel-progress'),
    avatarPanelProgress:    document.getElementById('kd-avatar-panel-progress'),
    levelPill:              document.getElementById('kd-level-pill'),
    rankPill:               document.getElementById('kd-rank-pill'),
    seasonName:             document.getElementById('kd-season-name'),
    leaderboard:            document.getElementById('kd-leaderboard'),
    currentDuelistCard:     document.getElementById('kd-current-duelist-card'),
    currentDuelistFrame:    document.getElementById('kd-current-duelist-frame'),
    currentDuelistThumb:    document.getElementById('kd-current-duelist-thumb'),
    playerAvatarFrame:      document.getElementById('kd-player-avatar-frame'),
    playerAvatarBattleFrame:document.getElementById('kd-player-avatar-battle-frame'),
    currentDuelistName:     document.getElementById('kd-current-duelist-name'),
    currentDuelistSub:      document.getElementById('kd-current-duelist-sub'),
    avatarDropdownBtn:      document.getElementById('kd-avatar-dropdown-btn'),
    avatarDropdownMenu:     document.getElementById('kd-avatar-dropdown-menu'),
    avatarSearch:           document.getElementById('kd-avatar-search'),
    tutorialModal:          document.getElementById('kd-tutorial-modal'),
    tutorialClose:          document.getElementById('kd-tutorial-close'),
    muteBtn:                document.getElementById('kd-mute-btn'),
    enemyAvatarImg:         document.getElementById('kd-enemy-avatar-img'),
    enemyAvatarBattleImg:   document.getElementById('kd-enemy-avatar-battle-img'),
    enemyAvatarWrap:        document.getElementById('kd-enemy-avatar'),
    enemyAvatarBattleWrap:  document.getElementById('kd-enemy-avatar-battle'),
    // KND-new elements
    resultGlyph:            document.getElementById('knd-result-glyph'),
    resultSubtitle:         document.getElementById('knd-result-subtitle'),
    resultHeader:           document.getElementById('knd-result-header'),
    playerHpPct:            document.getElementById('kd-player-hp-pct'),
    enemyHpPct:             document.getElementById('kd-enemy-hp-pct'),
    userProgressLabel:      document.getElementById('knd-user-progress-label'),
    avatarProgressLabel:    document.getElementById('knd-avatar-progress-label'),
    aguacateState:          document.getElementById('kd-aguacate-state'),
    duelAside:              document.getElementById('kd-duel-aside')
  };

  // ── Helpers ─────────────────────────────────────────────────────
  function escapeHtml(v) {
    return String(v || '').replace(/[&<>"']/g, function (c) {
      return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c];
    });
  }

  /** Mind Wars portrait only (server sends display_image_url from mw_avatars.image). */
  function avatarDisplayUrl(a) {
    if (!a) return '';
    return String(a.display_image_url || '').trim();
  }

  function pick(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
  }

  function toTitleCase(v) {
    return String(v || '').split(' ')
      .map(p => p ? p[0].toUpperCase() + p.slice(1).toLowerCase() : '')
      .join(' ');
  }

  function rarityClass(rarity) {
    const k = String(rarity || '').trim().toLowerCase();
    if (k === 'legendary') return 'rarity-legendary';
    if (k === 'epic')      return 'rarity-epic';
    if (k === 'rare' || k === 'special') return 'rarity-rare';
    return 'rarity-common';
  }

  function rarityWeight(rarity) {
    const r = String(rarity || '').toLowerCase();
    if (r === 'legendary') return 5;
    if (r === 'epic')      return 4;
    if (r === 'rare' || r === 'special') return 3;
    if (r === 'common')    return 2;
    return 1;
  }

  function clampAvatarLevel(level) {
    return Math.min(10, Math.max(1, Number(level || 1)));
  }

  function getInitials(value) {
    const parts = String(value || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'KD';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }

  function isPostIdleFlow() {
    return state.flowStep !== FLOW_STEPS.IDLE;
  }

  function syncAsideLayout() {
    const post = isPostIdleFlow();
    if (el.duelAside) el.duelAside.classList.toggle('knd-aside--flow-active', post);
    closeAvatarDropdown();
  }

  function getSortedFilteredAvatars(query) {
    const q = String(query || '').trim().toLowerCase();
    return (state.avatars || [])
      .filter(a => !!avatarDisplayUrl(a))
      .filter(a => !q || String(a && a.name || '').toLowerCase().includes(q))
      .sort(function (a, b) {
        const rw = rarityWeight(b && b.rarity) - rarityWeight(a && a.rarity);
        if (rw !== 0) return rw;
        return String(a && a.name || '').localeCompare(String(b && b.name || ''));
      });
  }

  // ── Flow / step rendering ────────────────────────────────────────
  function renderFlowStep() {
    const map = {
      idle:       el.stepIdle,
      category:   el.stepCategory,
      difficulty: el.stepDifficulty,
      battle:     el.stepBattle,
      result:     el.stepResult
    };
    Object.keys(map).forEach(step => {
      if (!map[step]) return;
      map[step].style.display = (state.flowStep === step) ? '' : 'none';
    });
    if (el.stepChip) {
      const meta = STEP_META[state.flowStep] || STEP_META.idle;
      el.stepChip.setAttribute('data-step', state.flowStep);
      el.stepChip.innerHTML = '<i class="fas ' + meta.icon + ' me-1"></i>' + meta.label;
      el.stepChip.setAttribute('aria-label', 'Current step: ' + meta.label);
    }
    syncAsideLayout();
  }

  function goToStep(step) {
    state.flowStep = STEP_META[step] ? step : FLOW_STEPS.IDLE;
    renderFlowStep();
  }

  // ── HP / Stability bars ──────────────────────────────────────────
  function updateHpBars(userHp, enemyHp) {
    const u = Math.max(0, Math.min(100, userHp));
    const e = Math.max(0, Math.min(100, enemyHp));

    // Update all player bars
    [el.playerHp, el.playerHpBattle].forEach(node => {
      if (!node) return;
      node.style.width = u + '%';
      node.textContent = u + ' HP';
      // critical threshold
      node.classList.toggle('is-critical', u <= 25);
    });
    // Player % label
    if (el.playerHpPct) el.playerHpPct.textContent = u + '%';

    // Update all enemy bars
    [el.enemyHp, el.enemyHpBattle].forEach(node => {
      if (!node) return;
      node.style.width = e + '%';
      node.textContent = e + ' HP';
    });
    if (el.enemyHpPct) el.enemyHpPct.textContent = e + '%';
  }

  // ── Damage number (KND style) ────────────────────────────────────
  function showDamageNumber(container, text, isEnemy) {
    if (!container) return;
    const span = document.createElement('span');
    span.className = 'kd-damage-number kd-damage-number--' + (isEnemy ? 'enemy' : 'player');
    span.textContent = text;
    span.style.position = 'absolute';
    span.style.left = '50%';
    span.style.top = '30%';
    span.style.transform = 'translate(-50%, -50%)';
    span.style.pointerEvents = 'none';
    span.style.zIndex = '100';
    container.style.position = container.style.position || 'relative';
    container.appendChild(span);
    setTimeout(() => span.remove(), 850);
  }

  // ── Flash / shake ────────────────────────────────────────────────
  function flashElement(node, className) {
    if (!node) return;
    node.classList.add(className);
    setTimeout(() => node.classList.remove(className), 450);
  }

  function shakeElement(node) {
    if (!node) return;
    node.classList.add('kd-shake');
    setTimeout(() => node.classList.remove('kd-shake'), 520);
  }

  // ── Screen flash ─────────────────────────────────────────────────
  function screenFlash(color) {
    let sf = document.getElementById('knd-screen-flash-overlay');
    if (!sf) {
      sf = document.createElement('div');
      sf.id = 'knd-screen-flash-overlay';
      sf.style.cssText = 'position:fixed;inset:0;z-index:9999;pointer-events:none;opacity:0;transition:opacity .06s';
      document.body.appendChild(sf);
    }
    sf.style.background = color;
    sf.style.opacity = '1';
    sf.style.transition = 'none';
    setTimeout(() => { sf.style.opacity = '0'; sf.style.transition = 'opacity .4s'; }, 80);
  }

  // ── Click sound (Web Audio API) ───────────────────────────────────
  let _audioCtx;
  function playUIClick(freq) {
    if (window.KnowledgeDuelAudio && typeof window.KnowledgeDuelAudio.playUiClick === 'function') {
      window.KnowledgeDuelAudio.playUiClick(freq);
      return;
    }
    try {
      if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      const o = _audioCtx.createOscillator(), g = _audioCtx.createGain();
      o.connect(g); g.connect(_audioCtx.destination);
      o.type = 'sine'; o.frequency.value = freq || 660;
      g.gain.setValueAtTime(.04, _audioCtx.currentTime);
      g.gain.exponentialRampToValueAtTime(.001, _audioCtx.currentTime + .08);
      o.start(); o.stop(_audioCtx.currentTime + .1);
    } catch (e) {}
  }

  // ── Fetch helper (unchanged) ─────────────────────────────────────
  async function fetchJson(url, options) {
    const r = await fetch(url, options || {});
    const data = await r.json();
    if (!data.ok) throw new Error(data?.error?.message || 'Request failed');
    return data.data || {};
  }

  // ── Avatar selection ─────────────────────────────────────────────
  function setSelectedAvatar(avatarId) {
    const found = state.avatars.find(a => Number(a.item_id) === Number(avatarId)) || null;
    state.selectedAvatar = found;
    renderAvatarList();
    renderStats();
    renderFighters();
    closeAvatarDropdown();
    if (el.openCategoryBtn) el.openCategoryBtn.disabled = !state.selectedAvatar;
    // Update idle hint
    const hint = document.getElementById('kd-idle-hint');
    if (hint) hint.textContent = found ? 'Unit synchronized — ready to engage' : 'Synchronize an avatar unit to proceed';
  }

  function closeAvatarDropdown() {
    if (el.avatarDropdownMenu) el.avatarDropdownMenu.style.display = 'none';
    if (el.avatarDropdownBtn) el.avatarDropdownBtn.setAttribute('aria-expanded', 'false');
  }

  function setAvatarDropdownButtonsLabel(htmlInner) {
    const h = htmlInner || '<i class="fas fa-chevron-down me-1"></i>CHANGE UNIT';
    if (el.avatarDropdownBtn) el.avatarDropdownBtn.innerHTML = h;
  }

  // ── Render avatar dropdown ────────────────────────────────────────
  function renderAvatarDropdown() {
    if (!el.avatarDropdownMenu || !el.avatarDropdownBtn) return;
    const selectedName = state.selectedAvatar ? String(state.selectedAvatar.name || 'Selected') : 'CHANGE UNIT';
    setAvatarDropdownButtonsLabel('<i class="fas fa-chevron-down me-1"></i>' + escapeHtml(selectedName));
    const query = String((el.avatarSearch && el.avatarSearch.value) || '').trim().toLowerCase();
    const filtered = getSortedFilteredAvatars(query);
    if (!filtered.length) {
      el.avatarDropdownMenu.innerHTML = '<div class="kd-duelist-picker-empty">No units found in registry</div>';
      return;
    }
    el.avatarDropdownMenu.innerHTML = filtered.map(a => {
      const selected    = state.selectedAvatar && Number(state.selectedAvatar.item_id) === Number(a.item_id);
      const rarityCss   = rarityClass(a.rarity);
      const avatarLevel = clampAvatarLevel(a.avatar_level);
      const rarityLabel = String(a.rarity || 'common')[0].toUpperCase() + String(a.rarity || 'common').slice(1).toLowerCase();
      return '<button type="button" class="kd-duelist-picker-item ' + (selected ? 'is-selected ' : '') + rarityCss + '" data-avatar-id="' + Number(a.item_id) + '">' +
        '<span class="kd-duelist-picker-main">' +
          '<span class="kd-duelist-picker-meta">' +
            '<span class="kd-duelist-picker-name-row">' +
              '<strong>' + escapeHtml(a.name) + '</strong>' +
              '<span class="level-badge level-' + avatarLevel + '">Lv ' + avatarLevel + '</span>' +
            '</span>' +
            '<small class="' + rarityCss + '">' + escapeHtml(rarityLabel) + '</small>' +
          '</span>' +
        '</span>' +
        (selected ? '<span class="kd-duelist-picker-tag">ACTIVE</span>' : '') +
      '</button>';
    }).join('');
    el.avatarDropdownMenu.querySelectorAll('[data-avatar-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        setSelectedAvatar(Number(btn.getAttribute('data-avatar-id')));
        closeAvatarDropdown();
      });
    });
  }

  // ── Render avatar list (hidden grid, used by JS) ──────────────────
  function renderAvatarList() {
    if (!el.avatarList) return;
    if (!state.avatars.length) {
      el.avatarList.innerHTML = '';
      if (el.avatarEmpty) el.avatarEmpty.style.display = 'none';
      if (el.aguacateState) el.aguacateState.style.display = 'block';
      if (el.avatarDropdownMenu) el.avatarDropdownMenu.innerHTML = '';
      setAvatarDropdownButtonsLabel('<i class="fas fa-chevron-down me-1"></i>CHANGE UNIT');
      if (el.avatarDropdownBtn) el.avatarDropdownBtn.disabled = true;
      if (el.openCategoryBtn) el.openCategoryBtn.disabled = true;
      state.selectedAvatar = null;
      return;
    }
    if (el.aguacateState) el.aguacateState.style.display = 'none';
    if (el.avatarEmpty) el.avatarEmpty.style.display = 'none';
    if (el.avatarDropdownBtn) el.avatarDropdownBtn.disabled = false;
    const query = String((el.avatarSearch && el.avatarSearch.value) || '').trim().toLowerCase();
    const sorted = getSortedFilteredAvatars(query);
    el.avatarList.innerHTML = sorted.map(a => {
      const selected    = state.selectedAvatar && Number(state.selectedAvatar.item_id) === Number(a.item_id);
      const buttonClass = selected ? 'knd-btn-select' : 'knd-btn-select--idle';
      const rarityCss   = rarityClass(String(a.rarity || 'common'));
      const avatarLevel = clampAvatarLevel(a.avatar_level);
      const thumbUrl = avatarDisplayUrl(a);
      const thumbInner = thumbUrl
        ? '<img class="kd-avatar-thumb" src="' + escapeHtml(thumbUrl) + '" alt="' + escapeHtml(a.name) + '">'
        : '<span class="kd-avatar-thumb kd-thumb-fallback" aria-hidden="true">⬡</span>';
      return (
        '<div class="kd-avatar-col">' +
          '<div class="kd-avatar-card ' + rarityCss + (selected ? ' is-selected' : '') + '">' +
            (selected ? '<span class="kd-selected-chip">ACTIVE</span>' : '') +
            '<div class="kd-avatar-head">' +
              '<span class="kd-avatar-frame kd-avatar-frame--thumb" data-level="' + avatarLevel + '">' +
                thumbInner +
              '</span>' +
              '<div class="kd-avatar-meta">' +
                '<div class="kd-avatar-name" title="' + escapeHtml(a.name) + '">' + escapeHtml(a.name) + '</div>' +
                '<div class="kd-avatar-sub">Lv ' + Number(a.avatar_level || 1) + ' · KE ' + Number(a.knowledge_energy || 0) + '</div>' +
                '<div class="kd-avatar-sub">Class: <span class="kd-rarity-label ' + rarityCss + '">' + escapeHtml(String(a.rarity || 'common')) + '</span></div>' +
                (a.is_favorite ? '<div class="kd-avatar-fav">FAVORITE</div>' : '') +
              '</div>' +
            '</div>' +
            '<button type="button" class="' + buttonClass + ' mt-3 kd-select-avatar" data-avatar-id="' + Number(a.item_id) + '">' + (selected ? 'SYNCHRONIZED' : 'SYNCHRONIZE') + '</button>' +
          '</div>' +
        '</div>'
      );
    }).join('');
    el.avatarList.querySelectorAll('.kd-select-avatar').forEach(btn => {
      btn.addEventListener('click', () => setSelectedAvatar(Number(btn.getAttribute('data-avatar-id'))));
    });
    renderAvatarDropdown();
    if (el.openCategoryBtn) el.openCategoryBtn.disabled = !state.selectedAvatar;
  }

  // ── Render fighters ───────────────────────────────────────────────
  function renderFighters() {
    const level = clampAvatarLevel(state.selectedAvatar ? state.selectedAvatar.avatar_level : 1);
    [el.currentDuelistFrame, el.playerAvatarFrame, el.playerAvatarBattleFrame].forEach(f => {
      if (f) f.setAttribute('data-level', String(level));
    });
    if (state.selectedAvatar) {
      const pUrl = avatarDisplayUrl(state.selectedAvatar);
      if (el.playerAvatar) {
        if (pUrl) el.playerAvatar.src = pUrl;
        else el.playerAvatar.removeAttribute('src');
      }
      if (el.playerName) el.playerName.textContent = state.selectedAvatar.name || 'UNIT';
      if (el.playerAvatarBattle) {
        if (pUrl) el.playerAvatarBattle.src = pUrl;
        else el.playerAvatarBattle.removeAttribute('src');
      }
      if (el.playerNameBattle) el.playerNameBattle.textContent = state.selectedAvatar.name || 'AGENT';
      if (el.currentDuelistThumb) {
        if (pUrl) el.currentDuelistThumb.src = pUrl;
        else el.currentDuelistThumb.removeAttribute('src');
      }
      if (el.currentDuelistName)  el.currentDuelistName.textContent = state.selectedAvatar.name || 'UNIT';
      if (el.currentDuelistSub)   el.currentDuelistSub.textContent =
        'LV ' + Number(state.selectedAvatar.avatar_level || 1) + ' · ' + String(state.selectedAvatar.rarity || 'common').toUpperCase();
    } else {
      [el.playerAvatar, el.playerAvatarBattle, el.currentDuelistThumb].forEach(i => { if (i) i.removeAttribute('src') });
      if (el.playerName)        el.playerName.textContent = 'SELECT UNIT';
      if (el.playerNameBattle)  el.playerNameBattle.textContent = 'AGENT';
      if (el.currentDuelistName) el.currentDuelistName.textContent = 'SELECT UNIT';
      if (el.currentDuelistSub)  el.currentDuelistSub.textContent = 'Awaiting synchronization';
    }
    if (state.battle) {
      if (el.enemyName)      el.enemyName.textContent = state.battle.enemyName || 'ENTITY';
      if (el.enemyNameBattle) el.enemyNameBattle.textContent = state.battle.enemyName || 'ENTITY';
      const path = state.battle.enemyAvatarPath || '';
      [el.enemyAvatarImg, el.enemyAvatarBattleImg].forEach(img => {
        if (!img) return;
        if (path) { img.src = path; img.style.display = '' }
        else { img.removeAttribute('src'); img.style.display = 'none' }
      });
      [el.enemyAvatarWrap, el.enemyAvatarBattleWrap].forEach(wrap => {
        if (wrap) wrap.classList.toggle('has-avatar', !!path);
      });
    } else {
      if (el.enemyName)      el.enemyName.textContent = 'SCANNING...';
      if (el.enemyNameBattle) el.enemyNameBattle.textContent = 'ENTITY';
      [el.enemyAvatarImg, el.enemyAvatarBattleImg].forEach(img => { if (img) { img.removeAttribute('src'); img.style.display = 'none' } });
      [el.enemyAvatarWrap, el.enemyAvatarBattleWrap].forEach(wrap => { if (wrap) wrap.classList.remove('has-avatar') });
    }
  }

  // ── Render stats ──────────────────────────────────────────────────
  function renderStats() {
    const u = state.user || {};
    const r = state.ranking || {};
    const a = state.selectedAvatar || {};
    if (el.statXp)          el.statXp.textContent          = Number(u.xp || 0).toLocaleString();
    if (el.statLevel)       el.statLevel.textContent       = Number(u.level || 1);
    if (el.statAvatar)      el.statAvatar.textContent      = a.name || '—';
    if (el.statKe)          el.statKe.textContent          = Number(a.knowledge_energy || 0).toLocaleString();
    if (el.statAvatarLevel) el.statAvatarLevel.textContent = Number(a.avatar_level || 1);
    if (el.statRank)        el.statRank.textContent        = Number(r.rank_score || 0).toLocaleString();
    if (el.statPos) {
      el.statPos.textContent = r.estimated_position ? ('#' + r.estimated_position) : 'PENDING';
      el.statPos.classList.toggle('knd-stat-pos--top', !!(r.estimated_position && Number(r.estimated_position) <= 3));
    }
    if (el.levelPill) el.levelPill.textContent = 'LVL ' + Number(u.level || 1);
    if (el.rankPill)  el.rankPill.textContent  = 'RANK ' + Number(r.rank_score || 0).toLocaleString();

    const userInto = Number(u.xp_into_level || 0);
    const userReq  = Number(u.xp_required_current || 0);
    const userPct  = userReq > 0 ? Math.round((userInto / userReq) * 100) : 0;
    if (el.userPanelProgress) el.userPanelProgress.style.width = Math.max(0, Math.min(100, userPct)) + '%';
    if (el.statXpDetail)      el.statXpDetail.textContent = userInto.toLocaleString() + ' / ' + userReq.toLocaleString();

    const keInto = Number(a.knowledge_energy_into_level || 0);
    const keReq  = Number(a.knowledge_energy_required_current || 0);
    const kePct  = keReq > 0 ? Math.round((keInto / keReq) * 100) : 0;
    if (el.avatarPanelProgress) el.avatarPanelProgress.style.width = Math.max(0, Math.min(100, kePct)) + '%';
    if (el.statKeDetail)        el.statKeDetail.textContent = keInto.toLocaleString() + ' / ' + keReq.toLocaleString();
  }

  // ── Render category cards ─────────────────────────────────────────
  function renderCategoryCards() {
    if (!el.categoryGrid) return;
    const categories = state.availableCategories.length ? state.availableCategories : Object.keys(CATEGORY_META);
    el.categoryGrid.innerHTML = categories.map(cat => {
      const meta     = CATEGORY_META[cat] || { icon: 'fa-star', desc: 'Neural knowledge domain.' };
      const selected = state.selectedCategory === cat;
      return '<button type="button" class="kd-choice-card ' + (selected ? 'is-selected' : '') + '" data-category="' + escapeHtml(cat) + '">' +
        '<i class="fas ' + meta.icon + '"></i>' +
        '<h5>' + escapeHtml(cat) + '</h5>' +
        '<p>' + escapeHtml(meta.desc) + '</p>' +
      '</button>';
    }).join('');
    el.categoryGrid.querySelectorAll('[data-category]').forEach(node => {
      node.addEventListener('click', () => {
        state.selectedCategory = node.getAttribute('data-category');
        renderCategoryCards();
        if (el.categoryContinueBtn) el.categoryContinueBtn.disabled = !state.selectedCategory;
        playUIClick(660);
      });
    });
    if (el.categoryContinueBtn) el.categoryContinueBtn.disabled = !state.selectedCategory;
  }

  // ── Render difficulty cards ───────────────────────────────────────
  function difficultyRewardPreview(difficulty) {
    const profile = state.difficultyRewards[difficulty] || {};
    const win = profile.win || {};
    return '+' + Number(win.xp || 0) + ' XP · +' + Number(win.knowledge_energy || 0) + ' KE · +' + Number(win.rank || 0) + ' RANK';
  }

  function renderDifficultyCards() {
    if (!el.difficultyGrid) return;
    const diffIcons = { easy: 'fa-circle', medium: 'fa-circle-half-stroke', hard: 'fa-radiation' };
    const diffLabels = { easy: 'LOW INTERFERENCE', medium: 'STANDARD', hard: 'MAX INTERFERENCE' };
    ['easy', 'medium', 'hard'].forEach(d => {
      // noop
    });
    el.difficultyGrid.innerHTML = ['easy', 'medium', 'hard'].map(d => {
      const selected = state.selectedDifficulty === d;
      return '<button type="button" class="kd-choice-card kd-choice-card--difficulty ' + (selected ? 'is-selected' : '') + '" data-difficulty="' + d + '">' +
        '<i class="fas ' + (diffIcons[d] || 'fa-circle') + '"></i>' +
        '<h5>' + (diffLabels[d] || toTitleCase(d)) + '</h5>' +
        '<p>' + escapeHtml(DIFFICULTY_META[d] || '') + '</p>' +
        '<span class="kd-reward-preview">' + escapeHtml(difficultyRewardPreview(d)) + '</span>' +
      '</button>';
    }).join('');
    el.difficultyGrid.querySelectorAll('[data-difficulty]').forEach(node => {
      node.addEventListener('click', () => {
        state.selectedDifficulty = (node.getAttribute('data-difficulty') || '').toLowerCase();
        renderDifficultyCards();
        if (el.startBtn) el.startBtn.disabled = !state.selectedDifficulty;
        playUIClick(880);
      });
    });
    if (el.startBtn) el.startBtn.disabled = !state.selectedDifficulty;
  }

  // ── Render current question ───────────────────────────────────────
  function renderQuestion() {
    if (!state.battle) return;
    const q = state.battle.questions[state.battle.index];
    if (!q) return;

    // "Query X / Y" KND language
    if (el.questionMeta) el.questionMeta.textContent = 'Query ' + (state.battle.index + 1) + ' / ' + state.battle.questions.length;
    if (el.questionText) el.questionText.textContent = q.question || '';
    if (el.feedback) { el.feedback.style.display = 'none'; el.feedback.textContent = ''; el.feedback.className = 'knd-feedback' }

    if (!el.options) return;
    el.options.innerHTML = ['A', 'B', 'C', 'D'].map(key => (
      '<button type="button" class="kd-option-btn" data-answer="' + key + '">' +
        '<span class="fw-bold me-2" style="font-family:var(--knd-FM);opacity:.6">' + key + '.</span>' +
        escapeHtml((q.options || {})[key] || '') +
      '</button>'
    )).join('');
    el.options.querySelectorAll('.kd-option-btn').forEach(btn => {
      btn.classList.remove(...OPTION_STATE_CLASSES);
      btn.disabled = false;
      btn.addEventListener('click', () => handleAnswer(btn.getAttribute('data-answer') || ''));
    });
  }

  // ── Handle answer ─────────────────────────────────────────────────
  function handleAnswer(answer) {
    if (!state.battle) return;
    const q = state.battle.questions[state.battle.index];
    if (!q) return;
    if (!['A', 'B', 'C', 'D'].includes(answer)) return;

    state.battle.answers[String(q.id)] = answer;
    const answerKey  = String(answer).toUpperCase();
    const correctKey = String(q.correct_answer || '').toUpperCase();
    const isCorrect  = answerKey === correctKey;
    const damage     = isCorrect ? Number(state.battle.damageCorrect || 20) : Number(state.battle.damageWrong || 15);

    if (isCorrect) {
      state.battle.enemyHp = Math.max(0, state.battle.enemyHp - damage);
      if (window.KnowledgeDuelAudio) { window.KnowledgeDuelAudio.playCorrect(); window.KnowledgeDuelAudio.playHitEnemy() }
      showDamageNumber(el.enemyAvatarBattleWrap || el.enemyAvatarWrap, '−' + damage, true);
      flashElement(el.playerAvatarBattleFrame || el.playerAvatarFrame, 'kd-flash-correct');
      shakeElement(el.enemyHpBattle || el.enemyHp);
      screenFlash('rgba(0,255,153,.06)');
    } else {
      state.battle.playerHp = Math.max(0, state.battle.playerHp - damage);
      if (window.KnowledgeDuelAudio) { window.KnowledgeDuelAudio.playWrong(); window.KnowledgeDuelAudio.playHitPlayer() }
      showDamageNumber(el.playerAvatarBattleFrame || el.playerAvatarFrame, '−' + damage, false);
      flashElement(el.playerAvatarBattleFrame || el.playerAvatarFrame, 'kd-flash-wrong');
      shakeElement(el.playerHpBattle || el.playerHp);
      screenFlash('rgba(255,34,85,.06)');
    }

    updateHpBars(state.battle.playerHp, state.battle.enemyHp);

    // Style options
    el.options.querySelectorAll('.kd-option-btn').forEach(btn => {
      const optKey = (btn.getAttribute('data-answer') || '').toUpperCase();
      btn.disabled = true;
      btn.classList.remove(...OPTION_STATE_CLASSES);
      if (optKey === correctKey) btn.classList.add('kd-option-correct');
      if (optKey === answerKey)  { btn.classList.add('kd-option-selected'); if (!isCorrect) btn.classList.add('kd-option-wrong') }
    });

    // KND feedback text
    if (el.feedback) {
      const feedText = isCorrect ? pick(FEEDBACK_CORRECT) : pick(FEEDBACK_WRONG);
      el.feedback.style.display = 'block';
      el.feedback.textContent   = feedText;
      el.feedback.className     = 'knd-feedback ' + (isCorrect ? 'is-correct' : 'is-wrong');
    }

    setTimeout(moveNextQuestionOrFinish, 750);
  }

  // ── Move to next question or submit ──────────────────────────────
  function moveNextQuestionOrFinish() {
    if (!state.battle) return;
    const doneByHp    = state.battle.playerHp <= 0 || state.battle.enemyHp <= 0;
    const doneByCount = state.battle.index >= (state.battle.questions.length - 1);
    if (doneByHp || doneByCount) { submitBattle(); return }
    state.battle.index += 1;
    renderQuestion();
  }

  // ── Start battle ──────────────────────────────────────────────────
  async function startBattle() {
    if (!state.selectedAvatar || !state.selectedCategory || !state.selectedDifficulty) return;
    el.startBtn.disabled = true;
    el.startBtn.classList.add('kd-loading');
    const origHtml = el.startBtn.innerHTML;
    el.startBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>INITIALIZING...';
    try {
      const fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('avatar_item_id', String(state.selectedAvatar.item_id));
      fd.append('category', String(state.selectedCategory));
      fd.append('difficulty', String(state.selectedDifficulty));
      const data = await fetchJson('/api/knowledge-duel/start_battle.php', { method: 'POST', body: fd });
      state.battle = {
        token:             data.battle_token,
        enemyName:         data.enemy_name,
        enemyAvatarPath:   data.enemy_avatar_path || '',
        enemyQuote:        data.enemy_quote || '',
        enemyTheme:        data.enemy_theme || '#d6cfc7',
        questions:         data.questions || [],
        index:             0,
        answers:           {},
        playerHp:          Number((data.battle_config || {}).user_hp_start  || 100),
        enemyHp:           Number((data.battle_config || {}).enemy_hp_start || 100),
        damageCorrect:     Number((data.battle_config || {}).damage_correct  || 20),
        damageWrong:       Number((data.battle_config || {}).damage_wrong    || 15),
        selectedCategory:  data.selected_category  || state.selectedCategory,
        selectedDifficulty:data.selected_difficulty || state.selectedDifficulty
      };
      updateHpBars(state.battle.playerHp, state.battle.enemyHp);
      renderFighters();
      if (el.battleCategoryPill)   el.battleCategoryPill.textContent   = state.battle.selectedCategory || '—';
      if (el.battleDifficultyPill) el.battleDifficultyPill.textContent = toTitleCase(state.battle.selectedDifficulty || '—');
      if (el.feedback) el.feedback.style.display = 'none';
      renderQuestion();
      goToStep(FLOW_STEPS.BATTLE);
      if (window.KnowledgeDuelAudio) window.KnowledgeDuelAudio.playBattleStart();
      screenFlash('rgba(0,232,255,.06)');
    } catch (err) {
      alert(err.message || 'Neural link could not be established.');
    } finally {
      el.startBtn.disabled = false;
      el.startBtn.classList.remove('kd-loading');
      el.startBtn.innerHTML = origHtml;
    }
  }

  // ── Submit battle ─────────────────────────────────────────────────
  async function submitBattle() {
    if (!state.battle) return;
    const avatarLevelBefore = state.selectedAvatar ? Number(state.selectedAvatar.avatar_level || 1) : 0;
    try {
      const fd = new FormData();
      fd.append('csrf_token',   CSRF);
      fd.append('battle_token', state.battle.token);
      fd.append('answers',      JSON.stringify(state.battle.answers || {}));
      const data = await fetchJson('/api/knowledge-duel/submit_battle.php', { method: 'POST', body: fd });

      state.user = {
        xp:                    Number((data.user_progress || {}).xp_after || 0),
        level:                 Number((data.user_progress || {}).level_after || 1),
        xp_into_level:         Number((data.user_progress || {}).xp_into_level || 0),
        xp_to_next_level:      Number((data.user_progress || {}).xp_to_next_level || 0),
        xp_required_current:   Number((data.user_progress || {}).xp_required_current || 0)
      };
      if (state.selectedAvatar) {
        state.selectedAvatar.knowledge_energy                = Number((data.avatar_progress || {}).knowledge_energy_after || state.selectedAvatar.knowledge_energy || 0);
        state.selectedAvatar.avatar_level                    = Number((data.avatar_progress || {}).level_after || state.selectedAvatar.avatar_level || 1);
        state.selectedAvatar.knowledge_energy_into_level     = Number((data.avatar_progress || {}).knowledge_energy_into_level || 0);
        state.selectedAvatar.knowledge_energy_to_next_level  = Number((data.avatar_progress || {}).knowledge_energy_to_next_level || 0);
        state.selectedAvatar.knowledge_energy_required_current = Number((data.avatar_progress || {}).knowledge_energy_required_current || 0);
      }
      state.ranking = data.ranking || state.ranking;
      renderFighters();
      renderStats();
      renderResult(data);

      const levelAfter = Number((data.avatar_progress || {}).level_after || 0);
      if (levelAfter > avatarLevelBefore && avatarLevelBefore >= 1) {
        if (window.KnowledgeDuelAudio) window.KnowledgeDuelAudio.playLevelUp();
        [el.currentDuelistFrame, el.playerAvatarFrame, el.playerAvatarBattleFrame].forEach(f => {
          if (f) f.classList.add('kd-level-up');
        });
        setTimeout(() => {
          [el.currentDuelistFrame, el.playerAvatarFrame, el.playerAvatarBattleFrame].forEach(f => {
            if (f) f.classList.remove('kd-level-up');
          });
        }, 1400);
      }
      await loadLeaderboard();
    } catch (err) {
      alert(err.message || 'Neural link submission failed.');
      state.battle = null;
      updateHpBars(100, 100);
      goToStep(FLOW_STEPS.IDLE);
    }
  }

  // ── Render result ─────────────────────────────────────────────────
  function renderResult(data) {
    const battle  = data.battle  || {};
    const rewards = data.rewards || {};
    const up = data.user_progress   || {};
    const ap = data.avatar_progress || {};
    const result = battle.result || 'draw';

    const rMap = RESULT_MAP[result] || RESULT_MAP.draw;
    if (el.resultTitle)    el.resultTitle.textContent    = rMap.title;
    if (el.resultGlyph)    el.resultGlyph.textContent    = rMap.glyph;
    if (el.resultSubtitle) el.resultSubtitle.textContent = rMap.subtitle;

    if (window.KnowledgeDuelAudio) window.KnowledgeDuelAudio.playResult(result);

    // Screen flash on result
    if (result === 'win')  screenFlash('rgba(0,232,255,.1)');
    if (result === 'lose') screenFlash('rgba(255,34,85,.08)');

    // Apply result class to step panel
    if (el.stepResult) {
      el.stepResult.classList.remove('knd-result-victory', 'knd-result-defeat');
      // legacy compat
      el.stepResult.classList.remove('kd-result-victory', 'kd-result-defeat');
      if (result === 'win')  { el.stepResult.classList.add('knd-result-victory'); el.stepResult.classList.add('kd-result-victory') }
      if (result === 'lose') { el.stepResult.classList.add('knd-result-defeat');  el.stepResult.classList.add('kd-result-defeat') }
    }

    // Rewards display
    if (el.resultRewards) {
      el.resultRewards.innerHTML =
        '<div>+ ' + Number(rewards.xp               || 0) + ' XP</div>' +
        '<div>+ ' + Number(rewards.knowledge_energy  || 0) + ' KE</div>' +
        '<div>+ ' + Number(rewards.rank              || 0) + ' RANK</div>';
    }

    // Progress bars
    const upPct = up.xp_required_current > 0 ? Math.round((Number(up.xp_into_level || 0) / Number(up.xp_required_current || 1)) * 100) : 0;
    if (el.userProgressBar) {
      el.userProgressBar.style.width = Math.max(0, Math.min(100, upPct)) + '%';
    }
    if (el.userProgressLabel) {
      el.userProgressLabel.textContent = 'LVL ' + Number(up.level_after || 1) + ' — ' + Number(up.xp_into_level || 0).toLocaleString() + ' / ' + Number(up.xp_required_current || 0).toLocaleString();
    }

    const apPct = ap.knowledge_energy_required_current > 0 ? Math.round((Number(ap.knowledge_energy_into_level || 0) / Number(ap.knowledge_energy_required_current || 1)) * 100) : 0;
    if (el.avatarProgressBar) {
      el.avatarProgressBar.style.width = Math.max(0, Math.min(100, apPct)) + '%';
    }
    if (el.avatarProgressLabel) {
      el.avatarProgressLabel.textContent = 'UNIT LVL ' + Number(ap.level_after || 1) + ' — ' + Number(ap.knowledge_energy_into_level || 0).toLocaleString() + ' / ' + Number(ap.knowledge_energy_required_current || 0).toLocaleString();
    }

    goToStep(FLOW_STEPS.RESULT);
  }

  // ── Load state ────────────────────────────────────────────────────
  async function loadState() {
    const data = await fetchJson('/api/knowledge-duel/get_state.php');
    state.user                = data.user    || null;
    state.season              = data.season  || null;
    state.ranking             = data.ranking || null;
    state.avatars             = data.avatars || [];
    let sel = data.selected_avatar || null;
    if (sel && !avatarDisplayUrl(sel)) sel = null;
    if (!sel && state.avatars.length) sel = state.avatars[0];
    state.selectedAvatar      = sel;
    state.availableCategories = data.categories || Object.keys(CATEGORY_META);
    state.difficultyRewards   = data.difficulty_rewards || {};
    renderAvatarList();
    renderStats();
    renderFighters();
    renderCategoryCards();
    renderDifficultyCards();
  }

  // ── Load leaderboard ──────────────────────────────────────────────
  async function loadLeaderboard() {
    const data   = await fetchJson('/api/knowledge-duel/leaderboard.php?limit=10');
    const season = data.season || {};
    const top    = data.top   || [];
    if (el.seasonName) el.seasonName.textContent = season.name || 'SEASON';
    if (typeof window.applyKdMiniLeaderboard === 'function') {
      window.applyKdMiniLeaderboard(data);
      return;
    }
    if (!el.leaderboard) return;
    if (!top.length) { el.leaderboard.innerHTML = '<div style="font-family:var(--knd-FM);font-size:10px;color:var(--knd-t3);letter-spacing:2px">NO RANKINGS YET</div>'; return }
    el.leaderboard.innerHTML = top.map(entry => {
      const mine  = entry.is_current_user ? ' kd-lb-row--me' : '';
      const pos   = Number(entry.position || 0);
      const medal = pos === 1 ? '🥇' : (pos === 2 ? '🥈' : (pos === 3 ? '🥉' : ''));
      return '<div class="kd-lb-row' + mine + '">' +
        '<div class="kd-lb-left">' +
          '<span class="kd-lb-pos">' + (medal ? medal + ' #' + pos : '#' + pos) + '</span>' +
          '<span class="kd-lb-avatar">' + escapeHtml(getInitials(entry.username)) + '</span>' +
          '<span class="kd-lb-user">' + escapeHtml(entry.username) + '</span>' +
        '</div>' +
        '<strong class="kd-lb-score">' + Number(entry.rank_score || 0).toLocaleString() + '</strong>' +
      '</div>';
    }).join('');
  }

  // ── Reset ─────────────────────────────────────────────────────────
  function resetAfterBattle() {
    state.battle = null;
    updateHpBars(100, 100);
    [el.currentDuelistFrame, el.playerAvatarFrame, el.playerAvatarBattleFrame].forEach(f => {
      if (f) f.classList.remove('kd-level-up');
    });
    renderFighters();
    if (el.feedback) { el.feedback.style.display = 'none'; el.feedback.textContent = ''; el.feedback.className = 'knd-feedback' }
  }

  // ── Event binding ─────────────────────────────────────────────────
  function bindEvents() {
    el.startBtn.addEventListener('click', startBattle);

    if (el.openCategoryBtn) {
      el.openCategoryBtn.addEventListener('click', () => {
        if (!state.selectedAvatar) return;
        playUIClick(660);
        goToStep(FLOW_STEPS.CATEGORY);
      });
    }
    if (el.categoryBackBtn) {
      el.categoryBackBtn.addEventListener('click', () => goToStep(FLOW_STEPS.IDLE));
    }
    if (el.categoryContinueBtn) {
      el.categoryContinueBtn.addEventListener('click', () => {
        if (!state.selectedCategory) return;
        playUIClick(770);
        renderDifficultyCards();
        goToStep(FLOW_STEPS.DIFFICULTY);
      });
    }
    if (el.difficultyBackBtn) {
      el.difficultyBackBtn.addEventListener('click', () => goToStep(FLOW_STEPS.CATEGORY));
    }
    if (el.avatarDropdownBtn) {
      el.avatarDropdownBtn.addEventListener('click', () => {
        if (!el.avatarDropdownMenu) return;
        const isOpen = el.avatarDropdownMenu.style.display !== 'none';
        if (isOpen) { closeAvatarDropdown(); return; }
        renderAvatarDropdown();
        el.avatarDropdownMenu.style.display = 'block';
        el.avatarDropdownBtn.setAttribute('aria-expanded', 'true');
        if (el.avatarSearch) el.avatarSearch.focus();
      });
    }
    if (el.avatarSearch) {
      el.avatarSearch.addEventListener('input', () => {
        renderAvatarDropdown();
        renderAvatarList();
        if (el.avatarDropdownMenu && el.avatarDropdownMenu.style.display !== 'block') {
          el.avatarDropdownMenu.style.display = 'block';
          if (el.avatarDropdownBtn) el.avatarDropdownBtn.setAttribute('aria-expanded', 'true');
        }
      });
    }

    document.addEventListener('click', ev => {
      if (!el.currentDuelistCard) return;
      if (!el.currentDuelistCard.contains(ev.target)) closeAvatarDropdown();
    });
    el.playAgainBtn.addEventListener('click', () => { resetAfterBattle(); goToStep(FLOW_STEPS.DIFFICULTY) });
    if (el.changeCategoryBtn) {
      el.changeCategoryBtn.addEventListener('click', () => { resetAfterBattle(); goToStep(FLOW_STEPS.CATEGORY) });
    }
    if (el.changeAvatarBtn) {
      el.changeAvatarBtn.addEventListener('click', () => { resetAfterBattle(); goToStep(FLOW_STEPS.IDLE); window.scrollTo({ top: 0, behavior: 'smooth' }) });
    }
  }

  // ── Tutorial ──────────────────────────────────────────────────────
  function showTutorialIfNeeded() {
    try {
      if (localStorage.getItem('kd_tutorial_seen') === '1') return;
      if (el.tutorialModal) el.tutorialModal.style.display = 'flex';
    } catch (e) {}
  }
  function closeTutorial() {
    if (el.tutorialModal) el.tutorialModal.style.display = 'none';
    try { localStorage.setItem('kd_tutorial_seen', '1') } catch (e) {}
  }

  // ── Mute button ───────────────────────────────────────────────────
  function updateMuteButton() {
    if (!el.muteBtn) return;
    const muted = window.KnowledgeDuelAudio && window.KnowledgeDuelAudio.isMuted();
    el.muteBtn.classList.toggle('muted', muted);
  }

  // ── Init ──────────────────────────────────────────────────────────
  async function init() {
    bindEvents();
    if (el.tutorialClose) el.tutorialClose.addEventListener('click', closeTutorial);
    if (el.tutorialModal && el.tutorialModal.querySelector('.kd-tutorial-backdrop')) {
      el.tutorialModal.querySelector('.kd-tutorial-backdrop').addEventListener('click', closeTutorial);
    }
    if (el.muteBtn) {
      el.muteBtn.addEventListener('click', () => {
        if (!window.KnowledgeDuelAudio) return;
        const wasMuted = window.KnowledgeDuelAudio.isMuted();
        window.KnowledgeDuelAudio.toggleMuted();
        updateMuteButton();
        if (wasMuted && !window.KnowledgeDuelAudio.isMuted()) {
          window.KnowledgeDuelAudio.playUiClick();
        }
      });
    }
    if (window.KnowledgeDuelAudio) { window.KnowledgeDuelAudio.unlock(); window.KnowledgeDuelAudio.preload() }
    updateMuteButton();
    try {
      await loadState();
      await loadLeaderboard();
      updateHpBars(100, 100);
      goToStep(FLOW_STEPS.IDLE);
      showTutorialIfNeeded();
    } catch (err) {
      alert(err.message || 'Neural link initialization failed.');
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();
