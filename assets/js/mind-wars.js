(function () {
  'use strict';

  const cfg = window.MindWarsConfig || {};
  const CSRF = cfg.csrfToken || '';
  const MW_SESSION_BATTLE_TOKEN_KEY = 'mindWarsActiveBattleToken';
  const MW_SELECTED_AVATAR_KEY = 'mindWarsLastSelectedAvatarItemId';

  const SKILL_DISPLAY_NAMES = {
    generic_strike: 'Power Strike',
    generic_focus: 'Focus Break',
    generic_burst: 'Burst Protocol',
    generic_finisher: 'Final Blow',
    generic_legendary_strike: 'Legend Pulse',
    generic_legendary_finisher: 'Mythic End',
    relativity_collapse: 'Relativity Collapse',
    mental_singularity: 'Mental Singularity',
    lightning_conductor: 'Lightning Conductor',
    storm_protocol: 'Storm Protocol',
    predictive_strike: 'Predictive Strike',
    final_deduction: 'Final Deduction',
    clone_assault: 'Clone Assault',
    celestial_rampage: 'Celestial Rampage',
    thunder_judgment: 'Thunder Judgment',
    wrath_of_olympus: 'Wrath of Olympus',
    petrifying_gaze: 'Petrifying Gaze',
    stone_eternity: 'Stone Eternity',
    abyssal_grip: 'Abyssal Grip',
    leviathan_crush: 'Leviathan Crush',
    frostbite_pulse: 'Frostbite Pulse',
    absolute_zero: 'Absolute Zero',
    chaos_tea: 'Chaos Tea',
    mirror_madness: 'Mirror Madness',
    mind_expansion: 'Mind Expansion',
    spark_of_genius: 'Spark of Genius',
    deductive_precision: 'Deductive Precision',
    trickster_instinct: 'Trickster Instinct',
    divine_pressure: 'Divine Pressure',
    cursed_presence: 'Cursed Presence',
    deep_armor: 'Deep Armor',
    frozen_calm: 'Frozen Calm',
    wonderland_shift: 'Wonderland Shift',
  };
  const ROLE_FALLBACK = 'Fighter';
  const CLASS_TOOLTIPS = {
    striker: 'High damage output.',
    tank: 'High survivability and damage resistance.',
    controller: 'Applies status effects like stun or freeze.',
    strategist: 'Higher critical chance and tactical advantage.',
    trickster: 'Unpredictable abilities and higher dodge potential.',
  };
  const STAT_TOOLTIPS = {
    mind: 'Increases attack damage. Higher Mind = more damage per hit.',
    focus: 'Reduces incoming damage. Higher Focus = better defense.',
    speed: 'Chance to act first and sometimes attack twice. Affects turn order.',
    luck: 'Increases critical hit chance. Critical hits deal extra damage.',
  };
  const RARITY_TOOLTIPS = {
    common: 'Base rarity. Solid stats for beginners.',
    rare: 'Enhanced abilities. Stronger combat potential.',
    epic: 'Powerful fighters with unique skills.',
    legendary: 'Elite avatars with exceptional abilities.',
    special: 'Special edition. Enhanced combat kit.',
  };
  const ACTION_SKILL_NAMES = {
    attack: 'Attack',
    defend: 'Shield Up',
  };
  const SKILL_SHORT_DESCRIPTIONS = {
    generic_strike: 'Deal heavy damage to one target.',
    generic_focus: 'Strike while disrupting enemy defense.',
    generic_burst: 'Deal burst damage and pressure the enemy.',
    generic_finisher: 'Deliver a high-damage finishing hit.',
    generic_legendary_strike: 'Legendary strike with boosted output.',
    generic_legendary_finisher: 'Mythic finishing move with huge impact.',
    relativity_collapse: 'Distort space to overwhelm the enemy.',
    mental_singularity: 'Collapse the enemy mind with concentrated force.',
    lightning_conductor: 'Chain lightning damage through your strike.',
    storm_protocol: 'Call a storm surge for amplified damage.',
    predictive_strike: 'Read the enemy and strike with precision.',
    final_deduction: 'Exploit a weakness for decisive damage.',
    clone_assault: 'Swarm the target with rapid clone attacks.',
    celestial_rampage: 'Unleash divine fury in one massive hit.',
    thunder_judgment: 'Strike with thunder and punishing power.',
    wrath_of_olympus: 'Channel Olympus power for a devastating finisher.',
    petrifying_gaze: 'Paralyze momentum and hit with force.',
    stone_eternity: 'Seal the target under crushing pressure.',
    abyssal_grip: 'Drag the target into abyssal pressure.',
    leviathan_crush: 'Overwhelm the enemy with leviathan force.',
    frostbite_pulse: 'Inflict chilling damage with control pressure.',
    absolute_zero: 'Freeze the battlefield with lethal cold.',
    chaos_tea: 'Chaos-infused strike with unpredictable pressure.',
    mirror_madness: 'Confuse and punish the target with mirror tricks.',
    mind_expansion: 'Expand combat cognition for stronger attacks.',
    spark_of_genius: 'Precision strike powered by insight.',
    deductive_precision: 'Exploit tiny openings with exact force.',
    trickster_instinct: 'Outplay timing and punish with style.',
    divine_pressure: 'Apply holy pressure that breaks resistance.',
    cursed_presence: 'Oppressive cursed aura weakens enemy resolve.',
    deep_armor: 'Dense guard profile that reduces damage taken.',
    frozen_calm: 'Calm, controlled defense under pressure.',
    wonderland_shift: 'Reality shift that creates tactical advantage.',
  };

  function getSkillDisplayName(code) {
    return SKILL_DISPLAY_NAMES[code] || (code ? String(code).replace(/_/g, ' ') : '—');
  }

  function getSkillDescription(code, actionType) {
    if (SKILL_SHORT_DESCRIPTIONS[code]) return SKILL_SHORT_DESCRIPTIONS[code];
    if (actionType === 'special') return 'Powerful finishing move.';
    if (actionType === 'ability') return 'Deal damage and apply combat pressure.';
    return 'Core combat action.';
  }

  function buildTooltip(name, desc, requirement) {
    return [name || 'Skill', desc || '', requirement || ''].filter(Boolean).join('\n');
  }

  function titleCase(value) {
    return String(value || '')
      .split(/[\s_-]+/)
      .filter(Boolean)
      .map(function (part) { return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase(); })
      .join(' ');
  }

  function resolveRole(fighter) {
    const roleRaw = fighter?.combat_class_label || fighter?.combat_class || fighter?.role || fighter?.combat_role || fighter?.combat_role_label || fighter?.archetype || fighter?.class_type || '';
    const role = titleCase(roleRaw);
    return role || ROLE_FALLBACK;
  }

  function getClassTooltip(fighter) {
    if (fighter?.combat_class_tooltip) return String(fighter.combat_class_tooltip);
    const key = String(fighter?.combat_class || resolveRole(fighter)).toLowerCase();
    return CLASS_TOOLTIPS[key] || 'Balanced fighter.';
  }

  function getRarityLabel(rarity) {
    const raw = String(rarity || 'common');
    return raw.charAt(0).toUpperCase() + raw.slice(1);
  }

  function getActionSkillName(actionType, skillCode, fighter) {
    if (actionType === 'ability') return getSkillDisplayName((fighter && fighter.ability_code) || skillCode);
    if (actionType === 'special') return getSkillDisplayName((fighter && fighter.special_code) || skillCode);
    return ACTION_SKILL_NAMES[actionType] || 'Action';
  }

  const state = {
    user: null,
    season: null,
    ranking: null,
    avatars: [],
    selectedAvatar: null,
    battleToken: null,
    battleState: null,
    entryStep: 'landing',
    loading: false,
    lastLogLength: 0,
    lastPlayerHp: null,
    lastEnemyHp: null,
    bannerTimeout: null,
    resumeAttempted: false,
    selectedMode: 'pve',
    selectedDifficulty: 'normal',
    queuePollTimer: null,
    queueLastStatus: 'idle',
    queueExitSent: false,
    queueJoinArmed: false,
    battlePollTimer: null,
    battlePollInFlight: false,
    battlePollFailCount: 0,
    lastActionSentAt: 0,
    lastActionTurn: 0,
    resolvingEnemyTurn: false,
    viewerUser: null,
    opponentUser: null,
    pvpViewerSide: 'player',
    pvpJoinInFlight: false,
    specialReadyLast: false,
    seasonRemainingSeconds: 0,
    seasonCountdownTimer: null,
    onlinePlayersTimer: null,
    recentBattlesTimer: null,
    challengePollTimer: null,
    pendingIncomingChallenge: null,
    pendingOutgoingChallengeToken: '',
    handledAcceptedBattleToken: '',
    challengeCreateInFlight: false,
    challengeAcceptInFlight: false,
    lastNextActor: '',
  };

  function normalizeMode(mode) {
    const v = String(mode || '').toLowerCase();
    if (v === 'training') return 'pve';
    if (v === 'pvp_ranked') return 'pvp_ranked';
    return 'pve';
  }

  function normalizeDifficulty(difficulty) {
    const v = String(difficulty || '').toLowerCase();
    return (v === 'easy' || v === 'hard' || v === 'normal') ? v : 'normal';
  }

  function createActionId(action) {
    const rand = Math.random().toString(36).slice(2, 10);
    return 'mw_' + Date.now() + '_' + String(action || 'act') + '_' + rand;
  }

  function saveActiveBattleToken(token) {
    try {
      if (!token) return;
      window.sessionStorage.setItem(MW_SESSION_BATTLE_TOKEN_KEY, String(token));
    } catch (e) {}
  }

  function clearActiveBattleToken() {
    try {
      window.sessionStorage.removeItem(MW_SESSION_BATTLE_TOKEN_KEY);
    } catch (e) {}
  }

  function loadActiveBattleToken() {
    try {
      return window.sessionStorage.getItem(MW_SESSION_BATTLE_TOKEN_KEY) || '';
    } catch (e) {
      return '';
    }
  }

  function saveLastSelectedAvatarId(itemId) {
    try {
      if (!itemId) return;
      window.localStorage.setItem(MW_SELECTED_AVATAR_KEY, String(itemId));
    } catch (e) {}
  }

  function loadLastSelectedAvatarId() {
    try {
      const raw = window.localStorage.getItem(MW_SELECTED_AVATAR_KEY);
      const n = Number(raw || 0);
      return Number.isFinite(n) && n > 0 ? n : 0;
    } catch (e) {
      return 0;
    }
  }

  const el = {
    flowLanding: document.getElementById('mw-flow-landing'),
    initialPlayWrap: document.getElementById('mw-initial-play-wrap'),
    entryContent: document.getElementById('mw-entry-content'),
    flowMode: document.getElementById('mw-flow-mode'),
    playNowBtn: document.getElementById('mw-play-now-btn'),
    backToLandingBtn: document.getElementById('mw-back-to-landing'),
    modeCardPve: document.getElementById('mw-mode-card-pve'),
    modeCardPvp: document.getElementById('mw-mode-card-pvp'),
    backToModeBtn: document.getElementById('mw-back-to-mode'),
    setupModeBadge: document.getElementById('mw-setup-mode-badge'),
    lobby: document.getElementById('mw-lobby'),
    battle: document.getElementById('mw-battle'),
    result: document.getElementById('mw-result'),
    currentDuelistThumb: document.getElementById('mw-current-duelist-thumb'),
    currentDuelistFrame: document.getElementById('mw-current-duelist-frame'),
    currentDuelistName: document.getElementById('mw-current-duelist-name'),
    currentDuelistSub: document.getElementById('mw-current-duelist-sub'),
    statsPreview: document.getElementById('mw-stats-preview'),
    combatKitLabel: document.getElementById('mw-combat-kit-label'),
    avatarDropdownBtn: document.getElementById('mw-avatar-dropdown-btn'),
    avatarDropdownMenu: document.getElementById('mw-avatar-dropdown-menu'),
    avatarSearch: document.getElementById('mw-avatar-search'),
    modeSelect: document.getElementById('mw-mode-select'),
    difficultyWrap: document.getElementById('mw-difficulty-wrap'),
    difficultySelect: document.getElementById('mw-difficulty-select'),
    pvpQueuePanel: document.getElementById('mw-pvp-queue-panel'),
    pvpQueueStatus: document.getElementById('mw-pvp-queue-status'),
    queueRank: document.getElementById('mw-queue-rank'),
    queueCount: document.getElementById('mw-queue-count'),
    queueEta: document.getElementById('mw-queue-eta'),
    searchingIndicator: document.getElementById('mw-searching-indicator'),
    playerLabel: document.getElementById('mw-player-label'),
    enemyLabel: document.getElementById('mw-enemy-label'),
    leaveMatchBtn: document.getElementById('mw-leave-match-btn'),
    startBattleBtn: document.getElementById('mw-start-battle-btn'),
    turnChip: document.getElementById('mw-turn-chip'),
    playerAvatar: document.getElementById('mw-player-avatar'),
    playerAvatarFrame: document.getElementById('mw-player-avatar-frame'),
    playerName: document.getElementById('mw-player-name'),
    playerHpText: document.getElementById('mw-player-hp-text'),
    playerHpMax: document.getElementById('mw-player-hp-max'),
    playerHpFill: document.getElementById('mw-player-hp-fill'),
    playerEnergy: document.getElementById('mw-player-energy'),
    enemyAvatar: document.getElementById('mw-enemy-avatar'),
    enemyAvatarFrame: document.getElementById('mw-enemy-avatar-frame'),
    enemyName: document.getElementById('mw-enemy-name'),
    enemyHpText: document.getElementById('mw-enemy-hp-text'),
    enemyHpMax: document.getElementById('mw-enemy-hp-max'),
    enemyHpFill: document.getElementById('mw-enemy-hp-fill'),
    enemyEnergy: document.getElementById('mw-enemy-energy'),
    actionAttack: document.getElementById('mw-action-attack'),
    actionDefend: document.getElementById('mw-action-defend'),
    actionAbility: document.getElementById('mw-action-ability'),
    actionSpecial: document.getElementById('mw-action-special'),
    actionBanner: document.getElementById('mw-action-banner'),
    playerRole: document.getElementById('mw-player-role'),
    enemyRole: document.getElementById('mw-enemy-role'),
    playerEnergyLabel: document.getElementById('mw-player-energy-label'),
    enemyEnergyLabel: document.getElementById('mw-enemy-energy-label'),
    playerPassive: document.getElementById('mw-player-passive'),
    playerAbility: document.getElementById('mw-player-ability'),
    playerSpecial: document.getElementById('mw-player-special'),
    playerHpBar: document.getElementById('mw-player-hp-bar'),
    enemyHpBar: document.getElementById('mw-enemy-hp-bar'),
    battleLog: document.getElementById('mw-battle-log'),
    resultTitle: document.getElementById('mw-result-title'),
    resultRewards: document.getElementById('mw-result-rewards'),
    playAgainBtn: document.getElementById('mw-play-again'),
    statXp: document.getElementById('mw-stat-xp'),
    statLevel: document.getElementById('mw-stat-level'),
    statAvatar: document.getElementById('mw-stat-avatar'),
    statKe: document.getElementById('mw-stat-ke'),
    statAvatarLevel: document.getElementById('mw-stat-avatar-level'),
    statRank: document.getElementById('mw-stat-rank'),
    statWins: document.getElementById('mw-stat-wins'),
    statLosses: document.getElementById('mw-stat-losses'),
    statWinRate: document.getElementById('mw-stat-win-rate'),
    statPos: document.getElementById('mw-stat-position'),
    statXpDetail: document.getElementById('mw-stat-xp-detail'),
    statKeDetail: document.getElementById('mw-stat-ke-detail'),
    userPanelProgress: document.getElementById('mw-user-panel-progress'),
    avatarPanelProgress: document.getElementById('mw-avatar-panel-progress'),
    levelPill: document.getElementById('mw-level-pill'),
    rankPill: document.getElementById('mw-rank-pill'),
    seasonName: document.getElementById('mw-season-name'),
    seasonCountdown: document.getElementById('mw-season-countdown'),
    leaderboard: document.getElementById('mw-leaderboard'),
    onlinePlayers: document.getElementById('mw-online-players'),
    recentBattles: document.getElementById('mw-recent-battles'),
    resultModal: document.getElementById('mw-result-modal'),
    resultModalCard: document.querySelector('#mw-result-modal .mw-result-modal-card'),
    resultModalTitle: document.getElementById('mw-result-modal-title'),
    resultModalSub: document.getElementById('mw-result-modal-sub'),
    resultModalPlayerAvatar: document.getElementById('mw-result-player-avatar'),
    resultModalEnemyAvatar: document.getElementById('mw-result-enemy-avatar'),
    resultModalPlayerName: document.getElementById('mw-result-player-name'),
    resultModalEnemyName: document.getElementById('mw-result-enemy-name'),
    resultModalRewards: document.getElementById('mw-result-rewards-modal'),
    resultSummary: document.getElementById('mw-result-summary'),
    resultModalPlayAgain: document.getElementById('mw-result-play-again-modal'),
    resultModalClose: document.getElementById('mw-result-modal-close'),
    resultModalX: document.getElementById('mw-result-modal-x'),
    challengeModal: document.getElementById('mw-challenge-modal'),
    challengeModalClose: document.getElementById('mw-challenge-modal-close'),
    challengeModalX: document.getElementById('mw-challenge-modal-x'),
    challengeAvatar: document.getElementById('mw-challenge-avatar'),
    challengeUsername: document.getElementById('mw-challenge-username'),
    challengeSelfAvatar: document.getElementById('mw-challenge-self-avatar'),
    challengeSelfName: document.getElementById('mw-challenge-self-name'),
    challengeSummary: document.getElementById('mw-challenge-summary'),
    challengeAccept: document.getElementById('mw-challenge-accept'),
    challengeDecline: document.getElementById('mw-challenge-decline'),
    leaveMatchModal: document.getElementById('mw-leave-match-modal'),
    leaveMatchModalClose: document.getElementById('mw-leave-match-modal-close'),
    leaveMatchConfirm: document.getElementById('mw-leave-match-confirm'),
    leaveMatchCancel: document.getElementById('mw-leave-match-cancel'),
  };

  function escapeHtml(v) {
    return String(v || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function getInitials(name) {
    return String(name || '')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map(function (p) { return p.charAt(0).toUpperCase(); })
      .join('') || '?';
  }

  function formatSeasonDuration(seconds) {
    const total = Math.max(0, Number(seconds || 0));
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const mins = Math.floor((total % 3600) / 60);
    const secs = Math.floor(total % 60);
    return days + 'd ' + hours + 'h ' + mins + 'm ' + secs + 's';
  }

  function formatRelativeTime(isoLike) {
    const ts = Date.parse(String(isoLike || ''));
    if (!Number.isFinite(ts)) return '';
    const diffSec = Math.max(0, Math.floor((Date.now() - ts) / 1000));
    if (diffSec < 60) return diffSec + 's ago';
    if (diffSec < 3600) return Math.floor(diffSec / 60) + 'm ago';
    if (diffSec < 86400) return Math.floor(diffSec / 3600) + 'h ago';
    return Math.floor(diffSec / 86400) + 'd ago';
  }

  function formatAgeSeconds(ageSeconds, isoLike) {
    const raw = Number(ageSeconds);
    if (Number.isFinite(raw) && raw >= 0) {
      const s = Math.floor(raw);
      if (s < 60) return s + 's ago';
      if (s < 3600) return Math.floor(s / 60) + 'm ago';
      if (s < 86400) return Math.floor(s / 3600) + 'h ago';
      return Math.floor(s / 86400) + 'd ago';
    }
    return formatRelativeTime(isoLike);
  }

  function closeResultModal() {
    if (!el.resultModal) return;
    el.resultModal.style.display = 'none';
    el.resultModal.setAttribute('aria-hidden', 'true');
  }

  function dismissResultModalToLobby() {
    closeResultModal();
    showLobby();
    loadState();
    loadLeaderboardPanel();
    loadSeasonInfo();
    loadOnlinePlayers();
    loadRecentBattles();
  }

  function openResultModal(result, rewards) {
    if (!el.resultModal || !el.resultModalCard) return;
    const titles = { win: 'Victory', lose: 'Defeat', draw: 'Draw' };
    const labels = { win: 'You dominated this duel.', lose: 'You were outplayed this time.', draw: 'A balanced clash ended in draw.' };
    const safeResult = (result === 'win' || result === 'lose' || result === 'draw') ? result : 'draw';

    if (safeResult === 'win' && typeof window.kndConfetti === 'function') {
      window.kndConfetti({ duration: 2800, count: 32 });
    }
    if (safeResult === 'lose') el.resultModalCard.classList.add('mw-result-modal-defeat');

    if (el.resultModalTitle) el.resultModalTitle.textContent = titles[safeResult] || 'Battle Result';
    if (el.resultModalSub) el.resultModalSub.textContent = labels[safeResult] || 'Mind Wars combat summary';

    el.resultModalCard.classList.remove('is-win', 'is-lose');
    if (safeResult === 'win') el.resultModalCard.classList.add('is-win');
    if (safeResult === 'lose') el.resultModalCard.classList.add('is-lose');

    const viewer = (state.viewerUser && state.viewerUser.username) ? state.viewerUser.username : 'You';
    const opponent = (state.opponentUser && state.opponentUser.username) ? state.opponentUser.username : 'Enemy';
    const playerF = fighterForVisual(state.battleState || {}, 'player');
    const enemyF = fighterForVisual(state.battleState || {}, 'enemy');

    if (el.resultModalPlayerAvatar) el.resultModalPlayerAvatar.src = assetUrl(playerF.asset_path || (state.selectedAvatar && state.selectedAvatar.asset_path));
    if (el.resultModalEnemyAvatar) el.resultModalEnemyAvatar.src = assetUrl(enemyF.asset_path);
    if (el.resultModalPlayerName) el.resultModalPlayerName.textContent = isPvpState(state.battleState) ? viewer : (playerF.name || 'Player');
    if (el.resultModalEnemyName) el.resultModalEnemyName.textContent = isPvpState(state.battleState) ? opponent : (enemyF.name || 'Enemy');

    const xp = Number((rewards && rewards.xp) || 0);
    const rank = Number((rewards && rewards.rank) || 0);
    const avatarXp = Number((rewards && rewards.knowledge_energy) || 0);
    if (el.resultModalRewards) {
      el.resultModalRewards.innerHTML =
        '<div class="mw-result-reward"><span>XP Gained</span><strong>+' + xp.toLocaleString() + '</strong></div>' +
        '<div class="mw-result-reward"><span>Rank Score</span><strong>' + (rank >= 0 ? '+' : '') + rank.toLocaleString() + '</strong></div>' +
        '<div class="mw-result-reward"><span>Avatar XP</span><strong>+' + avatarXp.toLocaleString() + '</strong></div>';
    }

    const turnNo = Number((state.battleState && state.battleState.turn) || 0);
    const modeText = normalizeMode((state.battleState && state.battleState.meta && state.battleState.meta.mode) || state.selectedMode).toUpperCase();
    if (el.resultSummary) {
      el.resultSummary.textContent = 'Mode: ' + modeText + ' · Turn reached: ' + (turnNo > 0 ? turnNo : 1) + '. Keep improving your strategy and timing.';
    }

    el.resultModal.style.display = 'block';
    el.resultModal.setAttribute('aria-hidden', 'false');
  }

  function renderSeasonCountdown() {
    if (!el.seasonCountdown) return;
    el.seasonCountdown.textContent = 'Time remaining: ' + formatSeasonDuration(state.seasonRemainingSeconds);
  }

  function ensureSeasonCountdownTicker() {
    if (state.seasonCountdownTimer) return;
    state.seasonCountdownTimer = setInterval(function () {
      if (state.seasonRemainingSeconds > 0) {
        state.seasonRemainingSeconds -= 1;
      }
      renderSeasonCountdown();
    }, 1000);
  }

  function setSeasonRemaining(seconds) {
    state.seasonRemainingSeconds = Math.max(0, Number(seconds || 0));
    renderSeasonCountdown();
    ensureSeasonCountdownTicker();
  }

  function assetUrl(path) {
    if (!path) return '/assets/avatars/_placeholder.svg';
    return path.startsWith('/') ? path : '/assets/avatars/' + path;
  }

  function modeDisplayName(mode) {
    return normalizeMode(mode) === 'pvp_ranked' ? 'PvP' : 'PvE';
  }

  function setEntryStep(step) {
    const next = String(step || 'landing');
    state.entryStep = (next === 'mode' || next === 'setup' || next === 'battle') ? next : 'landing';
    if (el.flowLanding) el.flowLanding.style.display = '';
    if (el.initialPlayWrap) el.initialPlayWrap.style.display = state.entryStep === 'landing' ? '' : 'none';
    if (el.entryContent) el.entryContent.style.display = (state.entryStep === 'mode' || state.entryStep === 'setup') ? '' : 'none';
    if (el.flowMode) el.flowMode.style.display = (state.entryStep === 'mode' || state.entryStep === 'setup') ? '' : 'none';
    if (el.lobby) el.lobby.style.display = (state.entryStep === 'mode' || state.entryStep === 'setup') ? '' : 'none';
  }

  function syncModeControls() {
    const mode = normalizeMode(state.selectedMode);
    state.selectedMode = mode;
    state.selectedDifficulty = normalizeDifficulty(state.selectedDifficulty);
    if (el.modeSelect && el.modeSelect.value !== mode) {
      el.modeSelect.value = mode;
    }
    if (el.difficultySelect && el.difficultySelect.value !== state.selectedDifficulty) {
      el.difficultySelect.value = state.selectedDifficulty;
    }
    if (el.difficultyWrap) {
      el.difficultyWrap.style.display = mode === 'pve' ? '' : 'none';
    }
    if (el.pvpQueuePanel) {
      el.pvpQueuePanel.style.display = mode === 'pvp_ranked' ? '' : 'none';
    }
    if (el.setupModeBadge) {
      el.setupModeBadge.textContent = modeDisplayName(mode);
    }
    if (el.startBattleBtn) {
      el.startBattleBtn.disabled = !state.selectedAvatar;
      if (mode === 'pvp_ranked') {
        el.startBattleBtn.innerHTML = '<i class="fas fa-crosshairs me-1"></i>Find Match';
        el.startBattleBtn.classList.remove('btn-outline-neon');
        el.startBattleBtn.classList.add('btn-neon-primary');
      } else {
        el.startBattleBtn.innerHTML = '<i class="fas fa-bolt me-1"></i>Start Battle';
        el.startBattleBtn.classList.remove('btn-outline-neon');
        el.startBattleBtn.classList.add('btn-neon-primary');
      }
    }
    if (mode !== 'pvp_ranked') {
      queueStatusPollStop();
    }
  }

  function applyStateMetaMode(meta) {
    if (!meta || typeof meta !== 'object') return;
    state.selectedMode = normalizeMode(meta.mode || state.selectedMode);
    state.selectedDifficulty = normalizeDifficulty(meta.difficulty || state.selectedDifficulty);
    syncModeControls();
  }

  function isPvpState(s) {
    return normalizeMode(s?.meta?.mode || state.selectedMode) === 'pvp_ranked';
  }

  function viewerSideForState(s) {
    return isPvpState(s) ? (state.pvpViewerSide === 'enemy' ? 'enemy' : 'player') : 'player';
  }

  function applyPvpUsersMeta(payload) {
    if (!payload || typeof payload !== 'object') return;
    const vu = payload.viewer_user;
    const ou = payload.opponent_user;
    if (vu && typeof vu === 'object') {
      state.viewerUser = { id: Number(vu.id || 0), username: String(vu.username || '') };
    }
    if (ou && typeof ou === 'object') {
      state.opponentUser = { id: Number(ou.id || 0), username: String(ou.username || '') };
    }
  }

  function canonicalSideForVisual(visualSide, s) {
    if (!isPvpState(s)) return visualSide === 'enemy' ? 'enemy' : 'player';
    const viewer = viewerSideForState(s);
    if (visualSide === 'player') return viewer;
    return viewer === 'player' ? 'enemy' : 'player';
  }

  function fighterForVisual(s, visualSide) {
    const canonical = canonicalSideForVisual(visualSide, s);
    return canonical === 'enemy' ? (s?.enemy || {}) : (s?.player || {});
  }

  function actorDisplayName(actor, s) {
    const a = String(actor || '').toLowerCase();
    if (a !== 'player' && a !== 'enemy') return '';
    if (isPvpState(s)) {
      const viewer = viewerSideForState(s);
      const isViewer = a === viewer;
      const user = isViewer ? state.viewerUser : state.opponentUser;
      const uname = String(user?.username || '').trim();
      if (uname) return uname;
    }
    const fighter = a === 'enemy' ? (s?.enemy || null) : (s?.player || null);
    return String(fighter?.name || (a === 'enemy' ? 'Enemy' : 'Player'));
  }

  function refreshBattleLabels(s) {
    if (!el.playerLabel || !el.enemyLabel) return;
    if (isPvpState(s)) {
      const selfName = String(state.viewerUser?.username || '').trim() || 'Player';
      const oppName = String(state.opponentUser?.username || '').trim() || 'Opponent';
      el.playerLabel.textContent = selfName;
      el.enemyLabel.textContent = oppName;
      return;
    }
    el.playerLabel.textContent = 'You';
    el.enemyLabel.textContent = 'Enemy';
  }

  const BANNER_TYPE_MAP = {
    ability: ['ABILITY USED', 'mw-banner-ability'],
    special: ['SPECIAL USED', 'mw-banner-special'],
    attack: ['BASIC ATTACK', 'mw-banner-attack'],
    defend: ['DEFEND', 'mw-banner-defend'],
  };

  function showActionBanner(actionType, skillName) {
    const banner = el.actionBanner;
    if (!banner) return;
    const pair = BANNER_TYPE_MAP[actionType] || ['ACTION', ''];
    banner.querySelector('.mw-action-banner-type').textContent = pair[0];
    banner.querySelector('.mw-action-banner-skill').textContent = skillName || '';
    banner.className = 'mw-action-banner ' + pair[1];
    banner.style.display = 'block';
    banner.classList.remove('mw-banner-out');
    clearTimeout(state.bannerTimeout);
    state.bannerTimeout = setTimeout(function () {
      banner.classList.add('mw-banner-out');
      setTimeout(function () {
        banner.style.display = 'none';
        banner.classList.remove('mw-banner-out');
      }, 300);
    }, 2000);
  }

  function rarityClass(r) {
    const k = String(r || 'common').toLowerCase();
    if (k === 'legendary') return 'mw-rarity-legendary';
    if (k === 'epic') return 'mw-rarity-epic';
    if (k === 'rare' || k === 'special') return 'mw-rarity-rare';
    return 'mw-rarity-common';
  }

  function clampAvatarLevel(level) {
    return Math.min(10, Math.max(1, Number(level || 1)));
  }

  async function fetchJson(url, options) {
    const r = await fetch(url, options || {});
    const text = await r.text();
    if (!text || text.trim() === '') {
      throw new Error('Empty response from server. Please try again.');
    }
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error('Invalid response from server. Please try again.');
    }
    if (!data.ok) {
      throw new Error(data?.error?.message || 'Request failed');
    }
    return data.data || {};
  }

  function kitLabelClass(label) {
    if (!label) return '';
    var k = String(label).toLowerCase();
    if (k.indexOf('legendary unique') >= 0) return ' mw-kit-legendary-unique';
    if (k.indexOf('legendary fallback') >= 0) return ' mw-kit-legendary-fallback';
    if (k.indexOf('epic') >= 0) return ' mw-kit-epic';
    if (k.indexOf('rare') >= 0) return ' mw-kit-rare';
    if (k.indexOf('common') >= 0) return ' mw-kit-common';
    return '';
  }

  function renderStatsPreview(avatar) {
    if (!avatar || !el.statsPreview) return;
    const cp = avatar.combat_profile || {};
    const parts = [
      { short: 'M', key: 'mind', value: cp.mind ?? '—' },
      { short: 'F', key: 'focus', value: cp.focus ?? '—' },
      { short: 'S', key: 'speed', value: cp.speed ?? '—' },
      { short: 'L', key: 'luck', value: cp.luck ?? '—' },
    ];
    const TOOLTIP_NAMES = { mind: 'MIND', focus: 'FOCUS', speed: 'SPEED', luck: 'LUCKY' };
    el.statsPreview.innerHTML = parts.map(function (part) {
      const tooltipName = TOOLTIP_NAMES[part.key] || titleCase(part.key);
      return '<span class="mw-stat-hint mw-stat-tooltip" data-mw-tooltip="' + escapeHtml(tooltipName) + '" title="' + escapeHtml(tooltipName + ': ' + STAT_TOOLTIPS[part.key]) + '">' + part.short + ':' + escapeHtml(part.value) + '</span>';
    }).join(' <span class="mw-stat-sep">|</span> ');
    if (el.combatKitLabel) {
      const label = cp.combat_kit_label || '';
      el.combatKitLabel.textContent = label;
      el.combatKitLabel.className = 'badge mw-kit-badge mt-1' + kitLabelClass(label);
      el.combatKitLabel.style.display = label ? 'inline-block' : 'none';
    }
  }

  function renderAvatarDropdown() {
    if (!el.avatarDropdownMenu) return;
    el.avatarDropdownMenu.innerHTML = '';
    const query = String((el.avatarSearch && el.avatarSearch.value) || '').trim().toLowerCase();
    const rarityWeight = function (rarity) {
      const r = String(rarity || '').toLowerCase();
      if (r === 'legendary') return 5;
      if (r === 'epic') return 4;
      if (r === 'rare' || r === 'special') return 3;
      if (r === 'common') return 2;
      return 1;
    };
    const filtered = (state.avatars || [])
      .filter(function (a) {
        if (!query) return true;
        const name = String(a && a.name || '').toLowerCase();
        return name.indexOf(query) >= 0;
      })
      .sort(function (a, b) {
        const rw = rarityWeight(b && b.rarity) - rarityWeight(a && a.rarity);
        if (rw !== 0) return rw;
        const an = String(a && a.name || '');
        const bn = String(b && b.name || '');
        return an.localeCompare(bn);
      });

    if (!filtered.length) {
      el.avatarDropdownMenu.innerHTML = '<div class="mw-avatar-option mw-avatar-option--empty"><div><div class="fw-bold text-white">No avatars found</div><div class="small text-white-50">Try another search term.</div></div></div>';
      return;
    }

    filtered.forEach(function (a) {
      const div = document.createElement('div');
      div.className = 'mw-avatar-option';
      div.dataset.itemId = String(a.item_id);
      const kitLabel = (a.combat_profile || {}).combat_kit_label || '';
      const rarityLabel = getRarityLabel(a.rarity);
      const level = clampAvatarLevel(a.avatar_level);
      div.innerHTML =
        '<div class="mw-avatar-option-media">' +
          '<div class="kd-avatar-frame" data-level="' + level + '">' +
            '<img src="' + escapeHtml(assetUrl(a.asset_path)) + '" alt="">' +
          '</div>' +
        '</div>' +
        '<div class="mw-avatar-option-content">' +
          '<div class="mw-avatar-option-name-row">' +
            '<span class="fw-bold text-white">' + escapeHtml(a.name) + '</span>' +
            '<span class="level-badge level-' + level + ' text-level-' + level + '">Lv ' + level + '</span>' +
          '</div>' +
          (kitLabel ? '<div class="small text-white-50">' + escapeHtml(kitLabel) + '</div>' : '') +
          '<div class="mw-avatar-option-badges">' +
            '<span class="mw-rarity-badge ' + rarityClass(a.rarity) + '">' + escapeHtml(rarityLabel) + '</span>' +
          '</div>' +
        '</div>';
      div.addEventListener('click', function () {
        selectAvatar(a);
        el.avatarDropdownMenu.style.display = 'none';
        el.avatarDropdownBtn.setAttribute('aria-expanded', 'false');
      });
      el.avatarDropdownMenu.appendChild(div);
    });
  }

  function selectAvatar(avatar) {
    state.selectedAvatar = avatar;
    saveLastSelectedAvatarId(avatar && avatar.item_id);
    if (el.currentDuelistFrame) el.currentDuelistFrame.setAttribute('data-level', String(clampAvatarLevel(avatar.avatar_level)));
    if (el.currentDuelistThumb) el.currentDuelistThumb.src = assetUrl(avatar.asset_path);
    if (el.currentDuelistName) el.currentDuelistName.textContent = avatar.name;
    if (el.currentDuelistSub) el.currentDuelistSub.textContent = 'Lv.' + (avatar.avatar_level || 1) + ' — Ready';
    renderStatsPreview(avatar);
    renderProgressPanel();
    syncModeControls();
    var setMainBtn = document.getElementById('mw-set-main-btn');
    if (setMainBtn) {
      setMainBtn.style.display = avatar ? '' : 'none';
      setMainBtn.innerHTML = (avatar && avatar.is_favorite) ? '<i class="fas fa-star text-warning me-1"></i>Main' : '<i class="fas fa-star me-1"></i>Set as Main';
    }
  }

  async function setMainAvatar() {
    var a = state.selectedAvatar;
    if (!a || !a.item_id) return;
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('item_id', String(a.item_id));
    try {
      var r = await fetch('/api/avatar/set_favorite.php', { method: 'POST', body: fd });
      var json = await r.json();
      if (json && json.ok) {
        a.is_favorite = true;
        selectAvatar(a);
        loadState();
      }
    } catch (e) {}
  }

  function renderProgressPanel() {
    const u = state.user || {};
    const r = state.ranking || {};
    const a = state.selectedAvatar || {};

    if (el.statXp) el.statXp.textContent = Number(u.xp || 0).toLocaleString();
    if (el.statLevel) el.statLevel.textContent = Number(u.level || 1);
    if (el.statAvatar) el.statAvatar.textContent = a.name || '-';
    if (el.statKe) el.statKe.textContent = Number(a.knowledge_energy || 0).toLocaleString();
    if (el.statAvatarLevel) el.statAvatarLevel.textContent = Number(a.avatar_level || 1);
    if (el.statRank) el.statRank.textContent = Number(r.rank_score || 0).toLocaleString();
    if (el.statWins) el.statWins.textContent = Number(r.wins || 0).toLocaleString();
    if (el.statLosses) el.statLosses.textContent = Number(r.losses || 0).toLocaleString();
    if (el.statWinRate) el.statWinRate.textContent = Number(r.win_rate || 0).toFixed(2) + '%';
    if (el.statPos) {
      const pos = Number(r.estimated_position || 0);
      el.statPos.textContent = pos > 0 ? ('#' + pos) : 'Pending';
      el.statPos.classList.remove('mw-stat-pos--top');
      if (pos > 0 && pos <= 3) el.statPos.classList.add('mw-stat-pos--top');
    }
    if (el.levelPill) {
      el.levelPill.textContent = 'Lv ' + Number(u.level || 1);
    }
    if (el.rankPill) {
      el.rankPill.textContent = 'Rank ' + Number(r.rank_score || 0).toLocaleString();
    }
    if (el.queueRank) {
      el.queueRank.textContent = Number(r.rank_score || 0).toLocaleString();
    }

    const userInto = Number(u.xp_into_level || 0);
    const userReq = Number(u.xp_required_current || 0);
    const userPct = userReq > 0 ? Math.round((userInto / userReq) * 100) : 0;
    if (el.userPanelProgress) el.userPanelProgress.style.width = Math.max(0, Math.min(100, userPct)) + '%';
    if (el.statXpDetail) el.statXpDetail.textContent = userInto.toLocaleString() + ' / ' + userReq.toLocaleString();

    const keTotal = Number(a.knowledge_energy || 0);
    const avatarLvl = Math.max(1, Number(a.avatar_level || 1));
    const fallbackReq = Math.ceil(80 * Math.pow(avatarLvl, 1.3));
    const keReq = Number(a.knowledge_energy_required_current || fallbackReq || 0);
    const keInto = Number(
      (a.knowledge_energy_into_level != null)
        ? a.knowledge_energy_into_level
        : (keReq > 0 ? (keTotal % keReq) : 0)
    );
    const kePct = keReq > 0 ? Math.round((keInto / keReq) * 100) : 0;
    if (el.avatarPanelProgress) el.avatarPanelProgress.style.width = Math.max(0, Math.min(100, kePct)) + '%';
    if (el.statKeDetail) el.statKeDetail.textContent = keInto.toLocaleString() + ' / ' + keReq.toLocaleString();
  }

  async function loadLeaderboardPanel() {
    if (!el.leaderboard || !el.seasonName) return;
    try {
      const data = await fetchJson('/api/mind-wars/leaderboard.php?limit=10');
      const season = data.season || {};
      const top = data.top || [];
      el.seasonName.textContent = season.name || 'Season';
      if (typeof season.seconds_remaining !== 'undefined') {
        setSeasonRemaining(Number(season.seconds_remaining || 0));
      }
      if (!top.length) {
        el.leaderboard.innerHTML = '<div class="text-white-50">No rankings yet.</div>';
        return;
      }
      el.leaderboard.innerHTML = top.map(function (entry) {
        const mine = entry.is_current_user ? ' mw-lb-row--me' : '';
        const pos = Number(entry.position || 0);
        const medal = pos === 1 ? '🥇' : (pos === 2 ? '🥈' : (pos === 3 ? '🥉' : ''));
        const posLabel = medal ? (medal + ' #' + pos) : ('#' + pos);
        return '<div class="mw-lb-row' + mine + '">' +
          '<div class="mw-lb-left">' +
            '<span class="mw-lb-pos">' + posLabel + '</span>' +
            '<span class="mw-lb-avatar">' + escapeHtml(getInitials(entry.username)) + '</span>' +
            '<span class="mw-lb-user">' + escapeHtml(entry.username) + '</span>' +
          '</div>' +
          '<strong class="mw-lb-score">' + Number(entry.rank_score || 0).toLocaleString() + '</strong>' +
        '</div>';
      }).join('');
    } catch (e) {
      el.seasonName.textContent = 'Season';
      if (el.seasonCountdown) el.seasonCountdown.textContent = 'Time remaining: --';
      el.leaderboard.innerHTML = '<div class="text-white-50">Unable to load leaderboard.</div>';
    }
  }

  async function loadSeasonInfo() {
    try {
      const data = await fetchJson('/api/mind-wars/season_info.php');
      if (data.season_name && el.seasonName) {
        el.seasonName.textContent = data.season_name;
      }
      setSeasonRemaining(Number(data.seconds_remaining || 0));
      if (!state.season || typeof state.season !== 'object') state.season = {};
      state.season.name = data.season_name || state.season.name || 'Season';
      state.season.starts_at = data.season_start || state.season.starts_at || '';
      state.season.ends_at = data.season_end || state.season.ends_at || '';
    } catch (e) {
      if (el.seasonCountdown && !el.seasonCountdown.textContent) {
        el.seasonCountdown.textContent = 'Time remaining: --';
      }
    }
  }

  async function loadOnlinePlayers() {
    if (!el.onlinePlayers) return;
    try {
      const data = await fetchJson('/api/mind-wars/online_players.php?limit=16');
      const players = Array.isArray(data.players) ? data.players : [];
      const source = String(data.source || 'queue');
      if (!players.length) {
        const emptyMsg = source === 'queue'
          ? 'No players currently in queue. Waiting for activity...'
          : 'No active players found yet.';
        el.onlinePlayers.innerHTML = '<div class="mw-online-empty">' + escapeHtml(emptyMsg) + '</div>';
        return;
      }
      el.onlinePlayers.innerHTML = players.map(function (p) {
        const userId = Number(p.user_id || 0);
        const me = Number(state.user && state.user.id || 0);
        const isSelf = userId > 0 && me > 0 && userId === me;
        const hasPendingOutgoing = !!(state.pendingOutgoingChallengeToken && state.pendingOutgoingChallengeToken.length > 0);
        const challengeDisabled = isSelf || !userId;
        const challengeMode = hasPendingOutgoing ? 'cancel' : 'create';
        const challengeLabel = isSelf ? 'You' : (hasPendingOutgoing ? 'Cancel' : 'Challenge');
        return '<div class="mw-online-row">' +
          '<span class="mw-online-dot" title="Online"></span>' +
          '<span class="mw-online-name">' + escapeHtml(p.username || 'Player') + '</span>' +
          '<span class="mw-online-info">Lv ' + Number(p.user_level || 1) + ' · Rank ' + Number(p.rank_score || 0).toLocaleString() + '</span>' +
          '<button type="button" class="mw-challenge-btn ' + (hasPendingOutgoing ? 'mw-challenge-btn-cancel' : '') + '" data-mode="' + challengeMode + '" data-user-id="' + userId + '" data-avatar-id="' + Number(p.avatar_item_id || 0) + '" ' + (challengeDisabled ? 'disabled' : '') + '>' + challengeLabel + '</button>' +
        '</div>';
      }).join('');
      if (el.onlinePlayers) {
        el.onlinePlayers.querySelectorAll('.mw-challenge-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
            const mode = btn.getAttribute('data-mode') || 'create';
            if (mode === 'cancel') {
              cancelChallenge(state.pendingOutgoingChallengeToken || '');
              return;
            }
            const targetUserId = Number(btn.getAttribute('data-user-id') || 0);
            createChallenge(targetUserId);
          });
        });
      }
    } catch (e) {
      const msg = (e && e.message) ? String(e.message) : 'Unable to load list';
      el.onlinePlayers.innerHTML = '<div class="mw-online-empty">Online list unavailable right now (' + escapeHtml(msg) + '). Retrying automatically...</div>';
    }
  }

  async function loadRecentBattles() {
    if (!el.recentBattles) return;
    try {
      const data = await fetchJson('/api/mind-wars/recent_battles.php?limit=8');
      const rows = Array.isArray(data.battles) ? data.battles : [];
      if (!rows.length) {
        el.recentBattles.innerHTML = '<div class="small text-white-50">No recent battles available.</div>';
        return;
      }
      el.recentBattles.innerHTML = rows.map(function (row) {
        const result = String(row.result || 'draw');
        const icon = result === 'win' ? '🏆' : (result === 'lose' ? '💀' : '⚖');
        const mode = normalizeMode(row.mode) === 'pvp_ranked' ? 'PvP' : 'PvE';
        return '<div class="mw-recent-item">' +
          '<div class="mw-recent-title">⚔ ' + escapeHtml(row.player_username || 'Player') + ' vs ' + escapeHtml(row.opponent_username || 'Opponent') + ' · ' + icon + ' ' + result.toUpperCase() + '</div>' +
          '<div class="mw-recent-meta">' + mode + ' · XP +' + Number(row.xp_gained || 0) + ' · Rank ' + (Number(row.rank_gained || 0) >= 0 ? '+' : '') + Number(row.rank_gained || 0) + ' · Avatar XP +' + Number(row.avatar_xp_gained || 0) + ' · ' + escapeHtml(formatAgeSeconds(row.age_seconds, row.updated_at)) + '</div>' +
        '</div>';
      }).join('');
    } catch (e) {
      el.recentBattles.innerHTML = '<div class="small text-white-50">Unable to load recent battles.</div>';
    }
  }

  function closeChallengeModal() {
    if (!el.challengeModal) return;
    el.challengeModal.style.display = 'none';
    el.challengeModal.setAttribute('aria-hidden', 'true');
    state.pendingIncomingChallenge = null;
  }

  function openChallengeModal(challenge) {
    if (!el.challengeModal || !challenge) return;
    state.pendingIncomingChallenge = challenge;
    if (el.challengeAvatar) el.challengeAvatar.src = assetUrl(challenge.challenger_avatar_asset || '/assets/avatars/_placeholder.svg');
    if (el.challengeUsername) el.challengeUsername.textContent = challenge.challenger_username || 'Player';
    if (el.challengeSelfAvatar) {
      const ownAsset = state.selectedAvatar ? assetUrl(state.selectedAvatar.asset_path || '') : '/assets/avatars/_placeholder.svg';
      el.challengeSelfAvatar.src = ownAsset || '/assets/avatars/_placeholder.svg';
    }
    if (el.challengeSelfName) el.challengeSelfName.textContent = 'You';
    if (el.challengeSummary) {
      el.challengeSummary.textContent = (challenge.challenger_username || 'A player') + ' challenges you. Accept to start a ranked PvP battle.';
    }
    el.challengeModal.style.display = '';
    el.challengeModal.setAttribute('aria-hidden', 'false');
  }

  function showBattleIntro(s) {
    const intro = document.getElementById('mw-battle-intro');
    if (!intro) return;
    const player = fighterForVisual(s || {}, 'player');
    const enemy = fighterForVisual(s || {}, 'enemy');
    const introPlayerImg = document.getElementById('mw-intro-player-avatar');
    const introPlayerName = document.getElementById('mw-intro-player-name');
    const introEnemyImg = document.getElementById('mw-intro-enemy-avatar');
    const introEnemyName = document.getElementById('mw-intro-enemy-name');
    if (introPlayerImg) introPlayerImg.src = assetUrl(player.asset_path);
    if (introPlayerName) introPlayerName.textContent = isPvpState(s) ? (state.viewerUser?.username || 'You') : (player.name || 'You');
    if (introEnemyImg) introEnemyImg.src = assetUrl(enemy.asset_path);
    if (introEnemyName) introEnemyName.textContent = isPvpState(s) ? (state.opponentUser?.username || 'Opponent') : (enemy.name || 'Enemy');
    intro.style.display = '';
    intro.classList.remove('mw-intro-out');
    setTimeout(function () {
      intro.classList.add('mw-intro-out');
      setTimeout(function () {
        intro.style.display = 'none';
        intro.classList.remove('mw-intro-out');
      }, 450);
    }, 2500);
  }

  function enterBattleFromPayload(data) {
    state.pvpViewerSide = (data.viewer_side === 'enemy') ? 'enemy' : 'player';
    applyPvpUsersMeta(data || {});
    state.battleToken = data.battle_token || null;
    if (state.battleToken) saveActiveBattleToken(state.battleToken);
    state.battleState = data.state || null;
    if (!state.battleState) return;
    applyStateMetaMode(state.battleState.meta || {});
    state.queueJoinArmed = false;
    state.pendingOutgoingChallengeToken = '';
    queueStatusPollStop();
    battleStatePollStop();
    closeChallengeModal();
    setEntryStep('battle');
    el.battle.style.display = 'block';
    el.result.style.display = 'none';
    state.lastLogLength = (state.battleState.log || []).filter(function (entry) {
      return !isHiddenMetaLogEntry(entry);
    }).length;
    state.lastPlayerHp = state.battleState.player ? state.battleState.player.hp : null;
    state.lastEnemyHp = state.battleState.enemy ? state.battleState.enemy.hp : null;
    state.specialReadyLast = false;
    if (window.MindWarsAudio) window.MindWarsAudio.unlock();
    if (window.MindWarsAudio) window.MindWarsAudio.playBattleStart();
    showBattleIntro(state.battleState);
    updateBattleUI(state.battleState);
    battleStatePollStart();
  }

  async function hydrateAcceptedChallengeBattle(battleToken) {
    if (!battleToken) return;
    if (state.handledAcceptedBattleToken && state.handledAcceptedBattleToken === String(battleToken)) return;
    try {
      const data = await fetchJson('/api/mind-wars/get_battle_state.php?battle_token=' + encodeURIComponent(battleToken));
      if (!data || !data.state) return;
      if (data && data.result) {
        state.handledAcceptedBattleToken = String(battleToken);
        return;
      }
      enterBattleFromPayload({
        battle_token: battleToken,
        state: data.state,
        viewer_side: data.viewer_side || 'player',
        viewer_user: data.viewer_user || 'You',
        opponent_user: data.opponent_user || 'Opponent',
      });
      state.handledAcceptedBattleToken = String(battleToken);
    } catch (e) {
      // no-op, polling will retry
    }
  }

  async function createChallenge(challengedUserId) {
    if (!challengedUserId || challengedUserId <= 0) return;
    if (state.challengeCreateInFlight) return;
    if (!state.selectedAvatar || !state.selectedAvatar.item_id) {
      alert('Select your avatar before challenging.');
      return;
    }
    state.challengeCreateInFlight = true;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('challenged_user_id', String(challengedUserId));
    fd.append('avatar_item_id', String(state.selectedAvatar.item_id));
    try {
      const data = await fetchJson('/api/mind-wars/challenge_create.php', { method: 'POST', body: fd });
      state.pendingOutgoingChallengeToken = String(data.challenge_token || '');
      if (el.pvpQueueStatus) el.pvpQueueStatus.textContent = 'Challenge sent. Waiting for response...';
      loadOnlinePlayers();
    } catch (e) {
      // Recovery path: sometimes server creates the challenge but client gets a transient error.
      try {
        const inbox = await fetchJson('/api/mind-wars/challenge_inbox.php');
        const outgoing = inbox && inbox.outgoing ? inbox.outgoing : null;
        if (outgoing && Number(outgoing.challenged_user_id || 0) === Number(challengedUserId)) {
          state.pendingOutgoingChallengeToken = String(outgoing.challenge_token || '');
          if (el.pvpQueueStatus) el.pvpQueueStatus.textContent = 'Challenge sent. Waiting for response...';
          loadOnlinePlayers();
          return;
        }
      } catch (_) {}
      alert(e.message || 'Unable to send challenge.');
    } finally {
      state.challengeCreateInFlight = false;
    }
  }

  async function cancelChallenge(challengeToken) {
    if (!challengeToken) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('challenge_token', String(challengeToken));
    try {
      await fetchJson('/api/mind-wars/challenge_cancel.php', { method: 'POST', body: fd });
      state.pendingOutgoingChallengeToken = '';
      loadOnlinePlayers();
    } catch (e) {
      alert(e.message || 'Unable to cancel challenge.');
    }
  }

  async function acceptIncomingChallenge() {
    const token = state.pendingIncomingChallenge && state.pendingIncomingChallenge.challenge_token;
    if (!token) return;
    if (state.challengeAcceptInFlight) return;
    state.challengeAcceptInFlight = true;
    if (el.challengeAccept) el.challengeAccept.disabled = true;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('challenge_token', String(token));
    try {
      const data = await fetchJson('/api/mind-wars/challenge_accept.php', { method: 'POST', body: fd });
      enterBattleFromPayload(data || {});
      loadOnlinePlayers();
    } catch (e) {
      // Recovery path: accept may have succeeded but response failed/transient.
      try {
        const inbox = await fetchJson('/api/mind-wars/challenge_inbox.php');
        const accepted = inbox && inbox.accepted ? inbox.accepted : null;
        if (accepted && accepted.battle_token) {
          await hydrateAcceptedChallengeBattle(String(accepted.battle_token || ''));
          return;
        }
      } catch (_) {}
      alert(e.message || 'Unable to accept challenge.');
    } finally {
      state.challengeAcceptInFlight = false;
      if (el.challengeAccept) el.challengeAccept.disabled = false;
    }
  }

  async function declineIncomingChallenge() {
    const token = state.pendingIncomingChallenge && state.pendingIncomingChallenge.challenge_token;
    if (!token) {
      closeChallengeModal();
      return;
    }
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('challenge_token', String(token));
    try {
      await fetchJson('/api/mind-wars/challenge_decline.php', { method: 'POST', body: fd });
    } catch (e) {
      // keep UX flowing even if decline fails
    }
    closeChallengeModal();
  }

  async function pollChallenges() {
    if (state.battleToken) return;
    try {
      const data = await fetchJson('/api/mind-wars/challenge_inbox.php');
      const outgoing = data && data.outgoing ? data.outgoing : null;
      state.pendingOutgoingChallengeToken = outgoing ? String(outgoing.challenge_token || '') : '';
      if (outgoing && el.pvpQueueStatus && state.queueLastStatus === 'idle') {
        el.pvpQueueStatus.textContent = 'Challenge pending with ' + (outgoing.challenged_username || 'player') + '.';
      }
      if (data && data.accepted && data.accepted.battle_token) {
        hydrateAcceptedChallengeBattle(String(data.accepted.battle_token || ''));
      }
      if (data && data.incoming && data.incoming.challenge_token) {
        const current = state.pendingIncomingChallenge && state.pendingIncomingChallenge.challenge_token;
        if (!current || current !== data.incoming.challenge_token) {
          openChallengeModal(data.incoming);
        }
      } else if (state.pendingIncomingChallenge) {
        closeChallengeModal();
      }
    } catch (e) {
      // silent by design, poll continues
    }
  }

  function queueStatusRender(payload) {
    if (!el.pvpQueueStatus) return;
    const status = String(payload?.status || 'idle');
    state.queueLastStatus = status;
    if (el.queueRank) el.queueRank.textContent = Number((state.ranking && state.ranking.rank_score) || 0).toLocaleString();
    if (el.queueCount) el.queueCount.textContent = Number(payload?.queue_count || 0).toLocaleString();
    if (el.searchingIndicator) el.searchingIndicator.style.display = (status === 'queued') ? '' : 'none';
    if (el.startBattleBtn && normalizeMode(state.selectedMode) === 'pvp_ranked') {
      el.startBattleBtn.classList.remove('btn-neon-primary', 'btn-outline-neon');
    }
    if (status === 'queued') {
      const sec = Number(payload?.queued_for_seconds || 0);
      const win = Number(payload?.level_window ?? payload?.rank_window ?? 0);
      const etaSec = Math.max(8, 45 - Math.min(35, sec));
      if (el.queueEta) el.queueEta.textContent = etaSec + 's';
      el.pvpQueueStatus.textContent = 'Queued for ' + sec + 's (avatar level window ±' + win + ').';
      if (el.startBattleBtn && normalizeMode(state.selectedMode) === 'pvp_ranked') {
        el.startBattleBtn.disabled = false;
        el.startBattleBtn.classList.add('btn-outline-neon');
        el.startBattleBtn.innerHTML = '<i class="fas fa-xmark me-1"></i>Cancel Search';
      }
      return;
    }
    if (status === 'matched') {
      const opp = Number(payload?.match?.opponent_user_id || 0);
      if (el.queueEta) el.queueEta.textContent = '0s';
      el.pvpQueueStatus.textContent = 'Match found with player #' + opp + '. Joining battle...';
      if (el.startBattleBtn && normalizeMode(state.selectedMode) === 'pvp_ranked') {
        el.startBattleBtn.disabled = true;
        el.startBattleBtn.classList.add('btn-neon-primary');
        el.startBattleBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Joining...';
      }
      return;
    }
    if (status === 'cancelled') {
      el.pvpQueueStatus.textContent = 'Queue cancelled.';
      if (el.startBattleBtn && normalizeMode(state.selectedMode) === 'pvp_ranked') {
        el.startBattleBtn.disabled = !state.selectedAvatar;
        el.startBattleBtn.classList.add('btn-neon-primary');
        el.startBattleBtn.innerHTML = '<i class="fas fa-crosshairs me-1"></i>Find Match';
      }
      return;
    }
    if (status === 'expired') {
      if (el.queueEta) el.queueEta.textContent = '--';
      el.pvpQueueStatus.textContent = 'Queue expired. You can join again.';
      if (el.startBattleBtn && normalizeMode(state.selectedMode) === 'pvp_ranked') {
        el.startBattleBtn.disabled = !state.selectedAvatar;
        el.startBattleBtn.classList.add('btn-neon-primary');
        el.startBattleBtn.innerHTML = '<i class="fas fa-crosshairs me-1"></i>Find Match';
      }
      return;
    }
    if (el.queueEta) el.queueEta.textContent = '--';
    el.pvpQueueStatus.textContent = 'Queue idle.';
    if (el.startBattleBtn && normalizeMode(state.selectedMode) === 'pvp_ranked') {
      el.startBattleBtn.disabled = !state.selectedAvatar;
      el.startBattleBtn.classList.add('btn-neon-primary');
      el.startBattleBtn.innerHTML = '<i class="fas fa-crosshairs me-1"></i>Find Match';
    }
  }

  function queueLooksActiveForUnload() {
    if (state.battleToken) return false;
    if (state.queueJoinArmed) return true;
    return state.queueLastStatus === 'queued' || state.queueLastStatus === 'matched';
  }

  function queueSendUnloadDequeue() {
    if (state.queueExitSent) return;
    if (!queueLooksActiveForUnload()) return;
    if (!CSRF) return;
    state.queueExitSent = true;
    state.queueJoinArmed = false;
    queueStatusPollStop();

    const params = new URLSearchParams();
    params.set('csrf_token', CSRF);
    params.set('reason', 'unload');

    let sent = false;
    try {
      if (navigator.sendBeacon) {
        sent = navigator.sendBeacon('/api/mind-wars/queue_dequeue.php', params);
      }
    } catch (e) {}

    if (!sent) {
      try {
        fetch('/api/mind-wars/queue_dequeue.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
          body: params.toString(),
          keepalive: true,
        });
      } catch (e) {}
    }
  }

  async function queueStatusFetch() {
    try {
      const data = await fetchJson('/api/mind-wars/queue_status.php');
      queueStatusRender(data || {});
      if ((data?.status || '') === 'matched' && state.queueJoinArmed && !state.pvpJoinInFlight && !state.battleToken) {
        state.pvpJoinInFlight = true;
        try {
          await pvpJoinMatched();
        } catch (joinErr) {
          // Keep user armed and polling; backend can self-heal orphan matches.
          console.warn('Mind Wars pvpJoinMatched retry:', joinErr);
          queueStatusRender({ status: 'queued' });
        }
        state.pvpJoinInFlight = false;
      }
      return data || {};
    } catch (e) {
      queueStatusRender({ status: 'idle' });
      state.pvpJoinInFlight = false;
      return { status: 'idle' };
    }
  }

  function queueStatusPollStop() {
    if (state.queuePollTimer) {
      clearInterval(state.queuePollTimer);
      state.queuePollTimer = null;
    }
  }

  function queueStatusPollStart() {
    if (state.queuePollTimer) return;
    queueStatusFetch();
    state.queuePollTimer = setInterval(function () {
      queueStatusFetch();
    }, 4000);
  }

  function battleStatePollStop() {
    if (state.battlePollTimer) {
      clearInterval(state.battlePollTimer);
      state.battlePollTimer = null;
    }
    state.battlePollInFlight = false;
  }

  async function battleStatePollFetch() {
    if (!state.battleToken || state.battlePollInFlight || state.loading) return;
    if (!state.battleState || !isPvpState(state.battleState)) return;
    state.battlePollInFlight = true;
    try {
      const data = await fetchJson('/api/mind-wars/get_battle_state.php?battle_token=' + encodeURIComponent(state.battleToken));
      state.battlePollFailCount = 0;
      state.pvpViewerSide = (data.viewer_side === 'enemy') ? 'enemy' : 'player';
      applyPvpUsersMeta(data || {});
      if (data.result) {
        battleStatePollStop();
        showResult(data.result, {
          xp: data.xp_gained || 0,
          knowledge_energy: data.knowledge_energy_gained || 0,
          rank: data.rank_gained || 0,
        });
        return;
      }
      if (data.state) {
        state.battleState = data.state;
        updateBattleUI(state.battleState);
      }
    } catch (e) {
      console.warn('Mind Wars battleStatePollFetch:', e);
      state.battlePollFailCount = Number(state.battlePollFailCount || 0) + 1;
      if (state.battlePollFailCount >= 3) {
        // If battle no longer exists (or consistently fails), leave PvP loop safely.
        battleStatePollStop();
        queueStatusPollStop();
        state.queueJoinArmed = false;
        state.battleToken = null;
        state.battleState = null;
        clearActiveBattleToken();
        if (el.battle) el.battle.style.display = 'none';
        if (el.result) el.result.style.display = 'none';
        setEntryStep('setup');
        if (el.pvpQueueStatus) el.pvpQueueStatus.textContent = 'Battle session expired or closed. Join queue again.';
      }
    } finally {
      state.battlePollInFlight = false;
    }
  }

  function battleStatePollStart() {
    if (state.battlePollTimer) return;
    if (!state.battleToken || !state.battleState || !isPvpState(state.battleState)) return;
    battleStatePollFetch();
    state.battlePollTimer = setInterval(function () {
      battleStatePollFetch();
    }, 2000);
  }

  async function queueEnqueue() {
    if (!state.selectedAvatar) {
      alert('Select an avatar first.');
      return;
    }
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('avatar_item_id', String(state.selectedAvatar.item_id));
    try {
      const data = await fetchJson('/api/mind-wars/queue_enqueue.php', { method: 'POST', body: fd });
      state.queueJoinArmed = true;
      state.queueExitSent = false;
      queueStatusRender(data || {});
      queueStatusPollStart();
    } catch (e) {
      alert(e.message || 'Failed to join queue');
    }
  }

  async function queueDequeue() {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    try {
      const data = await fetchJson('/api/mind-wars/queue_dequeue.php', { method: 'POST', body: fd });
      state.queueJoinArmed = false;
      state.queueExitSent = true;
      queueStatusRender(data || {});
      queueStatusPollStop();
    } catch (e) {
      alert(e.message || 'Failed to leave queue');
    }
  }

  async function pvpJoinMatched() {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    const data = await fetchJson('/api/mind-wars/pvp_join_matched.php', { method: 'POST', body: fd });
    enterBattleFromPayload(data || {});
  }

  function updateFighterUI(side, fighter) {
    const prefix = side === 'player' ? 'mw-player' : 'mw-enemy';
    const nameEl = document.getElementById(prefix + '-name');
    const hpText = document.getElementById(prefix + '-hp-text');
    const hpMax = document.getElementById(prefix + '-hp-max');
    const hpFill = document.getElementById(prefix + '-hp-fill');
    const energyEl = document.getElementById(prefix + '-energy');
    const avatarEl = document.getElementById(prefix === 'mw-player' ? 'mw-player-avatar' : 'mw-enemy-avatar');
    const avatarFrameEl = document.getElementById(prefix === 'mw-player' ? 'mw-player-avatar-frame' : 'mw-enemy-avatar-frame');
    const rarityEl = document.getElementById(prefix + '-rarity-badge');
    const kitEl = document.getElementById(prefix + '-kit-label');
    const roleEl = document.getElementById(prefix + '-role');

    if (nameEl) nameEl.textContent = fighter.name || 'Fighter';
    if (hpText) hpText.textContent = fighter.hp ?? 0;
    if (hpMax) hpMax.textContent = fighter.hp_max ?? 100;
    if (hpFill) {
      const pct = fighter.hp_max > 0 ? Math.max(0, (fighter.hp / fighter.hp_max) * 100) : 0;
      hpFill.style.width = pct + '%';
    }
    if (energyEl) {
      energyEl.innerHTML = '';
      energyEl.title = 'Energy is used to activate Special abilities.\nMaximum Energy: 5.';
      for (let i = 0; i < 5; i++) {
        const dot = document.createElement('span');
        dot.className = 'mw-energy-dot' + (i < (fighter.energy || 0) ? ' filled' : '');
        energyEl.appendChild(dot);
      }
    }
    if (avatarEl && fighter.asset_path) avatarEl.src = assetUrl(fighter.asset_path);
    if (avatarFrameEl) avatarFrameEl.setAttribute('data-level', String(clampAvatarLevel(fighter.avatar_level)));
    if (rarityEl) {
      const rarityLabel = getRarityLabel(fighter.rarity);
      const rarityKey = String(fighter.rarity || 'common').toLowerCase();
      rarityEl.textContent = rarityLabel;
      rarityEl.className = 'mw-rarity-badge mt-1 ' + rarityClass(fighter.rarity);
      rarityEl.style.display = 'inline-flex';
      rarityEl.title = RARITY_TOOLTIPS[rarityKey] || RARITY_TOOLTIPS.common;
    }
    if (roleEl) {
      roleEl.textContent = getRarityLabel(fighter.rarity) + ' · ' + resolveRole(fighter);
      roleEl.title = getClassTooltip(fighter);
    }
    if (kitEl) {
      kitEl.textContent = fighter.combat_kit_label || '';
      kitEl.style.display = fighter.combat_kit_label ? 'block' : 'none';
    }
    var card = (avatarFrameEl && avatarFrameEl.closest('.mw-fighter-card')) || null;
    if (card) {
      var hpPct = (fighter.hp_max > 0) ? (fighter.hp / fighter.hp_max) * 100 : 100;
      card.classList.toggle('mw-hp-low', hpPct > 0 && hpPct <= 25);
    }
    if (side === 'player') {
      if (el.playerPassive) el.playerPassive.textContent = getSkillDisplayName(fighter.passive_code);
      if (el.playerAbility) el.playerAbility.textContent = getSkillDisplayName(fighter.ability_code);
      if (el.playerSpecial) el.playerSpecial.textContent = getSkillDisplayName(fighter.special_code);
    }
    setFighterCardStats(side, fighter || {});
  }

  function getFighterStatValue(fighter, key) {
    const cp = (fighter && typeof fighter.combat_profile === 'object') ? fighter.combat_profile : {};
    const value = (cp[key] != null) ? cp[key] : fighter[key];
    const n = Number(value);
    if (!Number.isFinite(n) || n <= 0) return '-';
    return String(Math.round(n));
  }

  function setFighterCardStats(side, fighter) {
    const frame = document.getElementById(side === 'player' ? 'mw-player-avatar-frame' : 'mw-enemy-avatar-frame');
    if (!frame) return;
    const card = frame.closest('.mw-fighter-card');
    if (!card) return;
    const map = [
      ['mind', '.mw-stat-value--mind'],
      ['focus', '.mw-stat-value--focus'],
      ['speed', '.mw-stat-value--speed'],
      ['luck', '.mw-stat-value--luck'],
    ];
    map.forEach(function (entry) {
      const key = entry[0];
      const selector = entry[1];
      const node = card.querySelector(selector);
      if (node) node.textContent = getFighterStatValue(fighter, key);
    });
  }

  function nameToSlug(name) {
    return String(name || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'avatar';
  }

  async function openLoreModal(side) {
    const s = state.battleState;
    if (!s) return;
    const fighter = fighterForVisual(s, side);
    const name = fighter.name || 'Unknown';
    const slug = nameToSlug(name);
    const modal = document.getElementById('mw-lore-modal');
    const titleEl = document.getElementById('mw-lore-modal-title');
    const subEl = document.getElementById('mw-lore-modal-sub');
    const bodyEl = document.getElementById('mw-lore-modal-body');
    if (!modal || !titleEl || !bodyEl) return;
    titleEl.textContent = name;
    subEl.textContent = 'Loading...';
    bodyEl.innerHTML = '';
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');
    try {
      const r = await fetch('/api/mind-wars/lore.php?slug=' + encodeURIComponent(slug));
      const json = await r.json();
      const lore = (json && json.data && json.data.lore) || null;
      if (lore) {
        subEl.textContent = (lore.class_label || '') + (lore.culture ? ' · ' + lore.culture : '');
        bodyEl.innerHTML =
          '<p class="mb-2">' + (lore.description || lore.short_lore || 'No lore available.') + '</p>' +
          (lore.role ? '<p class="mb-0"><strong>Role:</strong> ' + lore.role + '</p>' : '');
      } else {
        subEl.textContent = '';
        bodyEl.innerHTML = '<p class="mb-0">No lore available for this fighter.</p>';
      }
    } catch (e) {
      subEl.textContent = '';
      bodyEl.innerHTML = '<p class="mb-0 text-danger">Could not load lore.</p>';
    }
  }

  function closeLoreModal() {
    const modal = document.getElementById('mw-lore-modal');
    if (modal) {
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden', 'true');
    }
  }

  function getCardForSide(side) {
    const frame = document.getElementById(side === 'enemy' ? 'mw-enemy-avatar-frame' : 'mw-player-avatar-frame');
    return frame ? frame.closest('.mw-fighter-card') : null;
  }

  function showDamagePopup(side, amount, mode) {
    const card = getCardForSide(side);
    const n = Number(amount || 0);
    if (!card || !Number.isFinite(n) || n <= 0) return;
    const popup = document.createElement('span');
    const isHeal = mode === 'heal';
    popup.className = 'mw-damage-popup' + (isHeal ? ' is-heal' : '');
    popup.textContent = (isHeal ? '+' : '-') + Math.round(n);
    card.appendChild(popup);
    setTimeout(function () {
      if (popup && popup.parentNode) popup.parentNode.removeChild(popup);
    }, 900);
  }

  function spawnParticles(fromSide, toSide, type) {
    const layer = document.getElementById('mw-particle-layer');
    const fromCard = getCardForSide(fromSide);
    const toCard = getCardForSide(toSide);
    if (!layer || !fromCard || !toCard) return;
    const fromRect = fromCard.getBoundingClientRect();
    const toRect = toCard.getBoundingClientRect();
    const stageRect = layer.getBoundingClientRect();
    const fromX = fromRect.left + fromRect.width / 2 - stageRect.left;
    const fromY = fromRect.top + fromRect.height / 2 - stageRect.top;
    const toX = toRect.left + toRect.width / 2 - stageRect.left;
    const toY = toRect.top + toRect.height / 2 - stageRect.top;
    const count = type === 'special' ? 16 : (type === 'crit' ? 12 : 6);
    const colors = type === 'special' ? ['#ffd77a', '#b48cff', '#6fdfff'] : (type === 'crit' ? ['#ffd77a', '#ff6d92'] : ['#6fdfff', '#ff6d92']);
    for (let i = 0; i < count; i++) {
      const p = document.createElement('span');
      p.className = 'mw-particle';
      const size = type === 'special' ? 8 + Math.random() * 6 : 4 + Math.random() * 4;
      var x = fromX;
      var y = fromY;
      if (type === 'special') {
        x = fromX + (toX - fromX) * 0.5 + (Math.random() - 0.5) * 60;
        y = fromY + (toY - fromY) * 0.5 + (Math.random() - 0.5) * 60;
      } else {
        var t = Math.random();
        x = fromX + (toX - fromX) * t + (Math.random() - 0.5) * 30;
        y = fromY + (toY - fromY) * t + (Math.random() - 0.5) * 30;
      }
      p.style.cssText = 'left:' + x + 'px;top:' + y + 'px;width:' + size + 'px;height:' + size + 'px;background:' + colors[Math.floor(Math.random() * colors.length)] + ';box-shadow:0 0 ' + (size * 2) + 'px currentColor;animation-duration:' + (0.5 + Math.random() * 0.3) + 's;';
      layer.appendChild(p);
      setTimeout(function () {
        if (p.parentNode) p.parentNode.removeChild(p);
      }, 800);
    }
  }

  function animateFighterHit(side, isCrit) {
    const card = getCardForSide(side);
    if (!card) return;
    card.classList.remove('mw-fighter-card-hit', 'mw-hit-crit');
    void card.offsetWidth;
    card.classList.add('mw-fighter-card-hit');
    if (isCrit) card.classList.add('mw-hit-crit');
    setTimeout(function () {
      if (card) card.classList.remove('mw-fighter-card-hit', 'mw-hit-crit');
    }, isCrit ? 360 : 260);
  }

  function formatLogEntry(e) {
    const turn = e.turn != null ? e.turn : '';
    const body = describeLogEntry(e);
    if (turn === '') return body;
    return 'Turn ' + turn + ' · ' + body;
  }

  function isStatusLogEntry(e) {
    if (String(e?.type || '').toLowerCase() === 'status') return true;
    if (String(e?.type || '').toLowerCase() === 'shield_block') return true;
    if (String(e?.type || '').toLowerCase() === 'ability_no_damage') return true;
    const msg = String(e?.msg || '');
    return /(status|stun|freeze|burn|poison|bleed|shield|buff|debuff|cooldown|energy|reduced|increase|decrease)/i.test(msg);
  }

  function isHiddenMetaLogEntry(e) {
    const msg = String(e?.msg || '').toLowerCase();
    return /next[_ ]attack[_ ]crit|next attack will crit/.test(msg);
  }

  function describeLogEntry(e) {
    const actor = String(e?.actor || '').toLowerCase();
    const actorName = actorDisplayName(actor, state.battleState);
    const actionType = String(e?.action_type || '').toLowerCase();
    const msg = String(e?.msg || '').trim();

    if (actionType) {
      if (msg) {
        const icon = actionType === 'special' ? '🔥' : (actionType === 'ability' ? '⚡' : (actionType === 'defend' ? '🛡' : '⚔'));
        return icon + ' ' + msg;
      }
      const actionFighter = actor === 'enemy' ? (state.battleState?.enemy || null) : (state.battleState?.player || null);
      const actionName = getActionSkillName(actionType, e.skill_code, actionFighter);
      const fighterName = (actionFighter && actionFighter.name) ? actionFighter.name : actorName;
      const icon = actionType === 'special' ? '🔥' : (actionType === 'ability' ? '⚡' : (actionType === 'defend' ? '🛡' : '⚔'));
      var verb = 'used';
      if (actionType === 'special') verb = 'unleashed';
      else if (actionType === 'ability') verb = 'unleashed';
      else if (actionType === 'attack') verb = 'struck with';
      else if (actionType === 'defend') verb = 'deployed';
      return icon + ' ' + (fighterName ? fighterName + ' ' : '') + verb + ' ' + actionName + '!';
    }
    if (e?.type === 'crit') {
      return '💥 Critical Hit! ' + (msg || 'A devastating blow landed!');
    }
    if (e?.type === 'heal') {
      const fn = actor === 'enemy' ? (state.battleState?.enemy?.name) : (state.battleState?.player?.name);
      return '✨ ' + (fn || actorName || 'A fighter') + ' recovered health!';
    }
    if (e?.type === 'evade') {
      const fn = actor === 'enemy' ? (state.battleState?.enemy?.name) : (state.battleState?.player?.name);
      return '🌀 ' + (fn || actorName || 'A fighter') + ' evaded the attack!';
    }
    if (e?.type === 'shield_block' && msg) {
      return '🛡 ' + msg;
    }
    if (e?.type === 'ability_no_damage' && msg) {
      return '📘 ' + msg;
    }
    if (e?.reason === 'defending' && msg) {
      return '🛡 ' + msg;
    }
    if (e?.reason === 'shield_full' && msg) {
      return '🛡 ' + msg;
    }
    if (isStatusLogEntry(e) && msg) {
      return '📘 ' + msg;
    }
    if (msg) {
      return msg;
    }
    return (actorName || 'A fighter') + ' acted.';
  }

  function updateBattleUI(s) {
    if (!s) return;
    const player = fighterForVisual(s, 'player');
    const enemy = fighterForVisual(s, 'enemy');
    const rawLog = s.log || [];
    const log = rawLog.filter(function (entry) { return !isHiddenMetaLogEntry(entry); });
    var prevNextActor = state.lastNextActor;
    state.lastNextActor = String(s.next_actor || '');
    if (prevNextActor && prevNextActor !== state.lastNextActor && state.lastNextActor && window.MindWarsAudio) {
      window.MindWarsAudio.playTurn();
    }
    if (el.turnChip) {
      const viewerSide = viewerSideForState(s);
      const actor = String(s.next_actor || '');
      const who = actor === viewerSide ? 'YOUR TURN' : 'ENEMY TURN';
      el.turnChip.textContent = 'Turn ' + (s.turn || 1) + ' • ' + who;
    }
    refreshBattleLabels(s);

    try {
      var actorVal = String(s.next_actor || '');
      var viewerSideVal = viewerSideForState(s);
      var activeVisualSide = (actorVal === viewerSideVal) ? 'player' : 'enemy';
      var playerCard = getCardForSide('player');
      var enemyCard = getCardForSide('enemy');
      if (playerCard) playerCard.classList.toggle('mw-fighter-card--active-turn', activeVisualSide === 'player');
      if (enemyCard) enemyCard.classList.toggle('mw-fighter-card--active-turn', activeVisualSide === 'enemy');
    } catch (err) {
      console.warn('Mind Wars updateBattleUI card turn:', err);
    }

    var prevPlayerHp = state.lastPlayerHp;
    var prevEnemyHp = state.lastEnemyHp;
    state.lastPlayerHp = player.hp;
    state.lastEnemyHp = enemy.hp;

    updateFighterUI('player', player);
    updateFighterUI('enemy', enemy);

    var lastLogHasCrit = log.length > 0 && log.slice(-3).some(function (e) { return e.type === 'crit'; });
    var lastLogHasEvade = log.length > 0 && log.slice(-3).some(function (e) { return e.type === 'evade'; });
    if (el.playerHpBar && prevPlayerHp != null && player.hp < prevPlayerHp) {
      el.playerHpBar.classList.add('mw-hp-hit');
      setTimeout(function () { if (el.playerHpBar) el.playerHpBar.classList.remove('mw-hp-hit'); }, 300);
      spawnParticles('enemy', 'player', lastLogHasCrit ? 'crit' : 'attack');
      animateFighterHit('player', lastLogHasCrit);
      showDamagePopup('player', prevPlayerHp - player.hp, 'damage', lastLogHasCrit);
      if (window.MindWarsAudio) window.MindWarsAudio.playHit(lastLogHasCrit);
    }
    if (prevEnemyHp != null && enemy.hp < prevEnemyHp) {
      spawnParticles('player', 'enemy', lastLogHasCrit ? 'crit' : 'attack');
      animateFighterHit('enemy', lastLogHasCrit);
      showDamagePopup('enemy', prevEnemyHp - enemy.hp, 'damage', lastLogHasCrit);
      if (window.MindWarsAudio) window.MindWarsAudio.playHit(lastLogHasCrit);
    }
    if (prevPlayerHp != null && player.hp > prevPlayerHp) {
      showDamagePopup('player', player.hp - prevPlayerHp, 'heal');
      if (window.MindWarsAudio) window.MindWarsAudio.playHeal();
    }
    if (prevEnemyHp != null && enemy.hp > prevEnemyHp) {
      showDamagePopup('enemy', enemy.hp - prevEnemyHp, 'heal');
      if (window.MindWarsAudio) window.MindWarsAudio.playHeal();
    }
    if (lastLogHasEvade && log.length > state.lastLogLength) {
      if (window.MindWarsAudio) window.MindWarsAudio.playEvade();
    }

    const viewerSide = viewerSideForState(s);
    const own = player;
    const canAct = (s.next_actor || '') === viewerSide && (player.hp || 0) > 0 && (enemy.hp || 0) > 0;
    const abilityCd = own.ability_cooldown || 0;
    const energy = own.energy || 0;

    const hasAbility = !!(own.ability_code);
    const hasSpecial = !!(own.special_code);
    const canUseAbility = canAct && hasAbility && abilityCd <= 0;
    const canUseSpecial = canAct && hasSpecial && energy >= 5;

    if (el.actionAttack) el.actionAttack.disabled = !canAct;
    if (el.actionDefend) el.actionDefend.disabled = !canAct;
    if (el.actionAbility) {
      el.actionAbility.disabled = !canUseAbility;
      const abilityName = getActionSkillName('ability', null, own);
      const abilityRequirement = abilityCd > 0 ? ('Cooldown: ' + abilityCd + ' turns.') : 'Cooldown: 3 turns.';
      const abilityDesc = getSkillDescription(own.ability_code, 'ability');
      el.actionAbility.dataset.skillName = abilityName;
      el.actionAbility.dataset.skillDesc = abilityDesc;
      el.actionAbility.dataset.requirement = abilityRequirement;
      el.actionAbility.title = buildTooltip(abilityName, abilityDesc, abilityRequirement);
    }
    if (el.actionSpecial) {
      el.actionSpecial.disabled = !canUseSpecial;
      const specialName = getActionSkillName('special', null, own);
      const specialDesc = getSkillDescription(own.special_code, 'special');
      const specialRequirement = 'Requires 5 Energy.';
      el.actionSpecial.dataset.skillName = specialName;
      el.actionSpecial.dataset.skillDesc = specialDesc;
      el.actionSpecial.dataset.requirement = specialRequirement;
      el.actionSpecial.title = buildTooltip(specialName, specialDesc, specialRequirement);
      el.actionSpecial.classList.toggle('mw-special-ready', canUseSpecial);
      el.actionSpecial.classList.toggle('mw-action-locked', hasSpecial && !canUseSpecial);
      if (canUseSpecial && !state.specialReadyLast) {
        const card = getCardForSide('player');
        if (card) {
          card.classList.add('mw-energy-ready-burst');
          setTimeout(function () {
            if (card) card.classList.remove('mw-energy-ready-burst');
          }, 900);
        }
      }
      state.specialReadyLast = !!canUseSpecial;
    }
    if (el.leaveMatchBtn) {
      el.leaveMatchBtn.style.display = isPvpState(s) ? '' : 'none';
      el.leaveMatchBtn.disabled = !isPvpState(s) || state.loading;
    }

    if (el.battleLog) {
      el.battleLog.innerHTML = log.map(function (e) {
        var cls = 'mw-log-entry';
        if (e.type === 'crit') cls += ' crit';
        if (e.type === 'evade') cls += ' evade';
        if (e.type === 'heal') cls += ' heal';
        if (e.type === 'shield_block') cls += ' mw-log-shield';
        if (e.type === 'ability_no_damage') cls += ' mw-log-ability-no-damage';
        if (e.actor === 'player') cls += ' mw-log-player';
        if (e.actor === 'enemy') cls += ' mw-log-enemy';
        if (e.action_type === 'special') cls += ' mw-log-special';
        if (e.action_type === 'ability') cls += ' mw-log-ability';
        if (isStatusLogEntry(e)) cls += ' mw-log-status';
        return '<div class="' + cls + '">' + escapeHtml(formatLogEntry(e)) + '</div>';
      }).reverse().join('');
    }

    if (log.length > state.lastLogLength && log.length > 0) {
      const last = log[log.length - 1];
      const at = last.action_type;
      if (at && BANNER_TYPE_MAP[at]) {
        const bannerActor = String(last.actor || '').toLowerCase();
        const bannerFighter = bannerActor === 'enemy' ? fighterForVisual(s, 'enemy') : fighterForVisual(s, 'player');
        const baseName = getActionSkillName(at, last.skill_code, bannerFighter);
        const actorName = actorDisplayName(bannerActor, s);
        const skillName = actorName ? (actorName + ': ' + baseName) : baseName;
        showActionBanner(at, skillName);
        if (at === 'special') {
          const fromSide = bannerActor === 'enemy' ? 'enemy' : 'player';
          const toSide = bannerActor === 'enemy' ? 'player' : 'enemy';
          spawnParticles(fromSide, toSide, 'special');
        }
      }
    }
    state.lastLogLength = log.length;

  }

  function showResumeEnemyTurnHint() {
    if (el.turnChip) {
      const current = String(el.turnChip.textContent || '');
      if (current.indexOf('Opponent turn') === -1) {
        el.turnChip.textContent = current ? (current + ' • Opponent turn') : 'Opponent turn';
      }
    }
    if (el.actionBanner) {
      const typeEl = el.actionBanner.querySelector('.mw-action-banner-type');
      const skillEl = el.actionBanner.querySelector('.mw-action-banner-skill');
      if (typeEl) typeEl.textContent = 'BATTLE RESUMED';
      if (skillEl) skillEl.textContent = 'Opponent turn pending. Confirm to resolve action.';
      el.actionBanner.className = 'mw-action-banner';
      el.actionBanner.style.display = 'block';
      el.actionBanner.classList.remove('mw-banner-out');
    }
  }

  function showResult(result, rewards) {
    if (window.MindWarsAudio) window.MindWarsAudio.playResult(result);
    state.resolvingEnemyTurn = false;
    battleStatePollStop();
    state.queueJoinArmed = false;
    state.specialReadyLast = false;
    queueStatusPollStop();
    el.battle.style.display = 'none';
    el.result.style.display = 'none';
    const titles = { win: 'Victory!', lose: 'Defeat', draw: 'Draw' };
    if (el.resultTitle) {
      el.resultTitle.textContent = titles[result] || result;
      el.resultTitle.className = 'mb-3 ' + (result === 'win' ? 'mw-result-win' : result === 'lose' ? 'mw-result-lose' : 'mw-result-draw');
    }
    if (el.resultRewards && rewards) {
      el.resultRewards.innerHTML =
        '<div class="mb-2">+' + (rewards.xp || 0) + ' XP</div>' +
        '<div class="mb-2">+' + (rewards.knowledge_energy || 0) + ' Knowledge Energy</div>' +
        '<div>+' + (rewards.rank || 0) + ' Rank</div>';
    }
    openResultModal(result, rewards || null);
    if (state.battleToken) {
      state.handledAcceptedBattleToken = String(state.battleToken);
    }
    clearActiveBattleToken();
    state.queueLastStatus = 'idle';
    setTimeout(function () {
      loadState();
      loadLeaderboardPanel();
      loadSeasonInfo();
      loadOnlinePlayers();
      loadRecentBattles();
    }, 250);
  }

  function showLobby(targetStep) {
    state.resolvingEnemyTurn = false;
    battleStatePollStop();
    state.queueJoinArmed = false;
    state.specialReadyLast = false;
    queueStatusPollStop();
    el.battle.style.display = 'none';
    el.result.style.display = 'none';
    state.battleToken = null;
    state.battleState = null;
    state.lastPlayerHp = null;
    state.lastEnemyHp = null;
    state.queueLastStatus = 'idle';
    clearActiveBattleToken();
    closeResultModal();
    closeChallengeModal();
    setEntryStep(targetStep || 'setup');
  }

  async function loadState() {
    try {
      const data = await fetchJson('/api/mind-wars/get_state.php');
      state.user = data.user || {};
      state.season = data.season || {};
      state.ranking = data.ranking || {};
      state.avatars = data.avatars || [];
      state.selectedAvatar = data.selected_avatar || null;
      const preferredAvatarId = loadLastSelectedAvatarId();
      const preferredAvatar = preferredAvatarId > 0
        ? state.avatars.find(function (a) { return Number(a?.item_id || 0) === preferredAvatarId; }) || null
        : null;

      if (preferredAvatar) {
        selectAvatar(preferredAvatar);
      } else if (state.selectedAvatar) {
        selectAvatar(state.selectedAvatar);
      } else if (state.avatars.length > 0) {
        selectAvatar(state.avatars[0]);
      } else {
        if (el.startBattleBtn) el.startBattleBtn.disabled = true;
      }
      renderAvatarDropdown();
      renderProgressPanel();
      if (state.season && state.season.name && el.seasonName) {
        el.seasonName.textContent = state.season.name;
      }
    } catch (e) {
      console.error('Mind Wars loadState:', e);
      if (el.startBattleBtn) el.startBattleBtn.disabled = true;
    }
  }

  async function startBattle() {
    if (!state.selectedAvatar || state.loading) return;
    if (normalizeMode(state.selectedMode) === 'pvp_ranked') {
      const queueStatus = String(state.queueLastStatus || 'idle');
      if (queueStatus === 'queued') {
        await queueDequeue();
      } else if (queueStatus !== 'matched') {
        await queueEnqueue();
      }
      return;
    }
    state.loading = true;
    if (el.startBattleBtn) el.startBattleBtn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('avatar_item_id', String(state.selectedAvatar.item_id));
      fd.append('mode', normalizeMode(state.selectedMode));
      fd.append('difficulty', normalizeDifficulty(state.selectedDifficulty));
      const data = await fetchJson('/api/mind-wars/start_battle.php', {
        method: 'POST',
        body: fd,
      });
      state.battleToken = data.battle_token;
      state.pvpViewerSide = 'player';
      state.viewerUser = null;
      state.opponentUser = null;
      battleStatePollStop();
      saveActiveBattleToken(state.battleToken);
      state.battleState = data.state || {};
      applyStateMetaMode(state.battleState.meta || {});
      state.lastLogLength = 0;
      state.lastPlayerHp = (data.state && data.state.player) ? data.state.player.hp : null;
      state.lastEnemyHp = (data.state && data.state.enemy) ? data.state.enemy.hp : null;
      state.specialReadyLast = false;
      setEntryStep('battle');
      el.battle.style.display = 'block';
      el.result.style.display = 'none';
      if (window.MindWarsAudio) window.MindWarsAudio.unlock();
      if (window.MindWarsAudio) window.MindWarsAudio.playBattleStart();
      showBattleIntro(state.battleState);
      updateBattleUI(state.battleState);
      if (isPvpState(state.battleState)) {
        battleStatePollStart();
      }
      if ((state.battleState.next_actor || '') === 'enemy') {
        state.loading = false;
        await performAction('advance');
        return;
      }
    } catch (e) {
      console.error('Mind Wars startBattle:', e);
      alert(e.message || 'Failed to start battle');
      if (el.startBattleBtn) el.startBattleBtn.disabled = false;
    } finally {
      state.loading = false;
    }
  }

  async function performAction(action) {
    if (!state.battleToken || state.loading) return;
    if (!['attack', 'defend', 'ability', 'special', 'advance'].includes(String(action || ''))) return;
    const s = state.battleState;
    if (!s) return;
    const now = Date.now();
    const isAdvance = (action === 'advance');
    if (!isAdvance && (now - Number(state.lastActionSentAt || 0)) < 450) return;
    const isPvp = isPvpState(s);
    const viewerSide = viewerSideForState(s);
    if (isPvp && isAdvance) return;
    if (!isAdvance && (s.next_actor || '') !== viewerSide) return;
    if (!isPvp && isAdvance && (s.next_actor || '') !== 'enemy') return;
    if ((s.player?.hp || 0) <= 0 || (s.enemy?.hp || 0) <= 0) return;

    state.loading = true;
    [el.actionAttack, el.actionDefend, el.actionAbility, el.actionSpecial].forEach(function (b) {
      if (b) b.disabled = true;
    });
    var resp;
    try {
      if (!isAdvance && window.MindWarsAudio) window.MindWarsAudio.playAction(action);
      state.lastActionSentAt = now;
      const fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('battle_token', state.battleToken);
      fd.append('action', action);
      fd.append('action_id', createActionId(action));
      resp = await fetchJson('/api/mind-wars/perform_action.php', {
        method: 'POST',
        body: fd,
      });
      state.battleState = resp.state || {};
      if (state.battleToken) saveActiveBattleToken(state.battleToken);
      updateBattleUI(state.battleState);
      if (isPvpState(state.battleState)) {
        battleStatePollStart();
      }

      if (resp.battle_over) {
        showResult(resp.result, resp.rewards);
      }
    } catch (e) {
      console.error('Mind Wars performAction:', e);
      if (!(state._performActionSuppressAlert)) {
        alert(e.message || 'Action failed');
      }
      updateBattleUI(state.battleState);
      throw e;
    } finally {
      state.loading = false;
      if (resp && !resp.battle_over) {
        updateBattleUI(state.battleState);
        loadRecentBattles();
        if (!isPvpState(state.battleState)) {
          await resolvePveEnemyTurnIfNeeded();
        }
      }
    }
  }

  async function resolvePveEnemyTurnIfNeeded() {
    if (state.resolvingEnemyTurn) return;
    if (!state.battleToken || !state.battleState || isPvpState(state.battleState)) return;

    const shouldResolve = function () {
      const s = state.battleState;
      if (!s) return false;
      if ((s.next_actor || '') !== 'enemy') return false;
      if ((s.player?.hp || 0) <= 0 || (s.enemy?.hp || 0) <= 0) return false;
      return true;
    };
    if (!shouldResolve()) return;

    state.resolvingEnemyTurn = true;
    try {
      let safety = 0;
      while (shouldResolve() && safety < 12) {
        safety += 1;
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('battle_token', state.battleToken);
        fd.append('action', 'advance');
        fd.append('action_id', createActionId('advance'));
        const resp = await fetchJson('/api/mind-wars/perform_action.php', {
          method: 'POST',
          body: fd,
        });
        state.battleState = resp.state || state.battleState || {};
        updateBattleUI(state.battleState);
        if (resp.battle_over) {
          showResult(resp.result, resp.rewards);
          return;
        }
      }
    } catch (e) {
      console.warn('Mind Wars resolvePveEnemyTurnIfNeeded:', e);
      alert(e.message || 'Failed to resolve enemy turn');
    } finally {
      state.resolvingEnemyTurn = false;
    }
  }

  function showLeaveMatchModal() {
    if (el.leaveMatchModal) {
      el.leaveMatchModal.style.display = '';
      el.leaveMatchModal.setAttribute('aria-hidden', 'false');
    }
  }

  function hideLeaveMatchModal() {
    if (el.leaveMatchModal) {
      el.leaveMatchModal.style.display = 'none';
      el.leaveMatchModal.setAttribute('aria-hidden', 'true');
    }
  }

  async function executeForfeit() {
    if (!state.battleToken || state.loading) return;
    state.loading = true;
    try {
      const fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('battle_token', state.battleToken);
      const resp = await fetchJson('/api/mind-wars/forfeit.php', { method: 'POST', body: fd });
      state.battleState = resp.state || state.battleState;
      updateBattleUI(state.battleState);
      showResult(resp.result || 'lose', resp.rewards || null);
    } catch (e) {
      alert(e.message || 'Failed to leave match');
    } finally {
      state.loading = false;
    }
  }

  function leaveMatch() {
    if (!state.battleToken || state.loading) return;
    if (!state.battleState || !isPvpState(state.battleState)) return;
    showLeaveMatchModal();
  }

  async function resumeBattleIfAny() {
    if (state.resumeAttempted) return false;
    state.resumeAttempted = true;
    const token = loadActiveBattleToken();
    if (!token) return false;
    try {
      const data = await fetchJson('/api/mind-wars/get_battle_state.php?battle_token=' + encodeURIComponent(token));
      state.battleToken = data.battle_token || token;
      saveActiveBattleToken(state.battleToken);
      state.battleState = data.state || null;
      state.pvpViewerSide = (data.viewer_side === 'enemy') ? 'enemy' : 'player';
      applyPvpUsersMeta(data || {});
      applyStateMetaMode((state.battleState && state.battleState.meta) || {});
      if (!state.battleState) {
        clearActiveBattleToken();
        return false;
      }
      if (data.result) {
        showResult(data.result, {
          xp: data.xp_gained || 0,
          knowledge_energy: data.knowledge_energy_gained || 0,
          rank: data.rank_gained || 0,
        });
        return true;
      }
      setEntryStep('battle');
      el.battle.style.display = 'block';
      el.result.style.display = 'none';
      state.lastLogLength = (state.battleState.log || []).filter(function (entry) {
        return !isHiddenMetaLogEntry(entry);
      }).length;
      state.lastPlayerHp = state.battleState.player ? state.battleState.player.hp : null;
      state.lastEnemyHp = state.battleState.enemy ? state.battleState.enemy.hp : null;
      state.specialReadyLast = false;
      updateBattleUI(state.battleState);
      if (isPvpState(state.battleState)) {
        battleStatePollStart();
      }
      if (!isPvpState(state.battleState) && (state.battleState.next_actor || '') === 'enemy') {
        showResumeEnemyTurnHint();
        const shouldAdvance = window.confirm('Opponent turn is pending from the resumed battle. Resolve it now?');
        if (shouldAdvance) {
          try {
            state._performActionSuppressAlert = true;
            await performAction('advance');
          } catch (advanceErr) {
            clearActiveBattleToken();
            alert('Could not resume battle. The saved battle has been cleared. You can start a new one.');
            return false;
          } finally {
            state._performActionSuppressAlert = false;
          }
        }
      }
      return true;
    } catch (e) {
      clearActiveBattleToken();
      return false;
    }
  }

  function init() {
    if (el.playerEnergyLabel) {
      el.playerEnergyLabel.title = 'Energy is used to activate Special abilities.\nMaximum Energy: 5.';
    }
    if (el.enemyEnergyLabel) {
      el.enemyEnergyLabel.title = 'Energy is used to activate Special abilities.\nMaximum Energy: 5.';
    }
    setEntryStep('landing');
    loadState().then(function () {
      return resumeBattleIfAny();
    }).then(function (resumed) {
      if (resumed) {
        setEntryStep('battle');
      } else {
        setEntryStep('landing');
      }
    });
    loadLeaderboardPanel();
    loadSeasonInfo();
    setInterval(loadSeasonInfo, 30000);
    loadOnlinePlayers();
    loadRecentBattles();
    pollChallenges();
    state.onlinePlayersTimer = setInterval(loadOnlinePlayers, 15000);
    state.recentBattlesTimer = setInterval(loadRecentBattles, 4000);
    state.challengePollTimer = setInterval(pollChallenges, 3000);

    syncModeControls();

    if (el.playNowBtn) {
      el.playNowBtn.addEventListener('click', function () {
        try {
          if (window.MindWarsAudio && window.MindWarsAudio.unlock) window.MindWarsAudio.unlock();
          setEntryStep('mode');
        } catch (e) {
          console.warn('Mind Wars Play Now:', e);
          setEntryStep('mode');
        }
      });
    }
    if (el.backToLandingBtn) {
      el.backToLandingBtn.addEventListener('click', function () {
        setEntryStep('landing');
      });
    }
    if (el.backToModeBtn) {
      el.backToModeBtn.addEventListener('click', function () {
        setEntryStep('mode');
      });
    }
    if (el.modeCardPve) {
      el.modeCardPve.addEventListener('click', function () {
        state.selectedMode = 'pve';
        syncModeControls();
        setEntryStep('setup');
        if (window.MindWarsTutorial && !window.MindWarsTutorial._started) {
          window.MindWarsTutorial._started = true;
          setTimeout(function () { window.MindWarsTutorial.start(); }, 400);
        }
      });
    }
    if (el.modeCardPvp) {
      el.modeCardPvp.addEventListener('click', function () {
        state.selectedMode = 'pvp_ranked';
        syncModeControls();
        setEntryStep('setup');
        if (window.MindWarsTutorial && !window.MindWarsTutorial._started) {
          window.MindWarsTutorial._started = true;
          setTimeout(function () { window.MindWarsTutorial.start(); }, 400);
        }
      });
    }

    if (el.avatarDropdownBtn && el.avatarDropdownMenu) {
      el.avatarDropdownBtn.addEventListener('click', function () {
        const open = el.avatarDropdownMenu.style.display === 'block';
        el.avatarDropdownMenu.style.display = open ? 'none' : 'block';
        el.avatarDropdownBtn.setAttribute('aria-expanded', !open);
        if (!open && el.avatarSearch) {
          el.avatarSearch.focus();
        }
      });
    }
    if (el.avatarSearch) {
      el.avatarSearch.addEventListener('input', function () {
        renderAvatarDropdown();
        if (el.avatarDropdownMenu && el.avatarDropdownMenu.style.display !== 'block') {
          el.avatarDropdownMenu.style.display = 'block';
          if (el.avatarDropdownBtn) el.avatarDropdownBtn.setAttribute('aria-expanded', 'true');
        }
      });
    }

    if (el.startBattleBtn) {
      el.startBattleBtn.addEventListener('click', startBattle);
    }
    var setMainBtn = document.getElementById('mw-set-main-btn');
    if (setMainBtn) setMainBtn.addEventListener('click', setMainAvatar);

    if (el.modeSelect) {
      el.modeSelect.addEventListener('change', function () {
        state.selectedMode = normalizeMode(el.modeSelect.value);
        syncModeControls();
      });
    }
    if (el.difficultySelect) {
      el.difficultySelect.addEventListener('change', function () {
        state.selectedDifficulty = normalizeDifficulty(el.difficultySelect.value);
        syncModeControls();
      });
    }
    if (el.leaveMatchBtn) {
      el.leaveMatchBtn.addEventListener('click', function () {
        leaveMatch();
      });
    }
    var playerLoreBtn = document.getElementById('mw-player-lore-btn');
    var enemyLoreBtn = document.getElementById('mw-enemy-lore-btn');
    if (playerLoreBtn) playerLoreBtn.addEventListener('click', function () { openLoreModal('player'); });
    if (enemyLoreBtn) enemyLoreBtn.addEventListener('click', function () { openLoreModal('enemy'); });
    var loreModalClose = document.getElementById('mw-lore-modal-close');
    var loreModalX = document.getElementById('mw-lore-modal-x');
    if (loreModalClose) loreModalClose.addEventListener('click', closeLoreModal);
    if (loreModalX) loreModalX.addEventListener('click', closeLoreModal);

    [el.actionAttack, el.actionDefend, el.actionAbility, el.actionSpecial].forEach(function (btn) {
      if (btn) {
        btn.addEventListener('click', function () {
          performAction(btn.dataset.action || 'attack');
        });
      }
    });

    if (el.playAgainBtn) {
      el.playAgainBtn.addEventListener('click', function () {
        showLobby('setup');
        loadState();
        loadLeaderboardPanel();
        loadSeasonInfo();
        loadOnlinePlayers();
        loadRecentBattles();
      });
    }
    if (el.resultModalPlayAgain) {
      el.resultModalPlayAgain.addEventListener('click', function () {
        dismissResultModalToLobby();
      });
    }
    if (el.resultModalClose) {
      el.resultModalClose.addEventListener('click', dismissResultModalToLobby);
    }
    if (el.resultModalX) {
      el.resultModalX.addEventListener('click', dismissResultModalToLobby);
    }
    if (el.challengeModalClose) {
      el.challengeModalClose.addEventListener('click', declineIncomingChallenge);
    }
    if (el.challengeModalX) {
      el.challengeModalX.addEventListener('click', declineIncomingChallenge);
    }
    if (el.challengeAccept) {
      el.challengeAccept.addEventListener('click', acceptIncomingChallenge);
    }
    if (el.challengeDecline) {
      el.challengeDecline.addEventListener('click', declineIncomingChallenge);
    }
    if (el.leaveMatchModalClose) {
      el.leaveMatchModalClose.addEventListener('click', hideLeaveMatchModal);
    }
    if (el.leaveMatchCancel) {
      el.leaveMatchCancel.addEventListener('click', hideLeaveMatchModal);
    }
    if (el.leaveMatchConfirm) {
      el.leaveMatchConfirm.addEventListener('click', function () {
        hideLeaveMatchModal();
        executeForfeit();
      });
    }

    document.addEventListener('click', function (e) {
      if (el.avatarDropdownMenu && el.avatarDropdownBtn && !el.avatarDropdownMenu.contains(e.target) && !el.avatarDropdownBtn.contains(e.target)) {
        el.avatarDropdownMenu.style.display = 'none';
        el.avatarDropdownBtn.setAttribute('aria-expanded', 'false');
      }
    });

    window.addEventListener('pagehide', function () {
      queueSendUnloadDequeue();
    });
    window.addEventListener('beforeunload', function () {
      queueSendUnloadDequeue();
    });
    window.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && el.resultModal && el.resultModal.style.display !== 'none') {
        dismissResultModalToLobby();
      } else if (e.key === 'Escape' && el.challengeModal && el.challengeModal.style.display !== 'none') {
        declineIncomingChallenge();
      } else if (e.key === 'Escape' && el.leaveMatchModal && el.leaveMatchModal.style.display !== 'none') {
        hideLeaveMatchModal();
      } else if (e.key === 'Escape') {
        var loreModal = document.getElementById('mw-lore-modal');
        if (loreModal && loreModal.style.display !== 'none') closeLoreModal();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
