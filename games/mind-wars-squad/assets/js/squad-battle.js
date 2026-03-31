/**
 * squad-battle.js — Mind Wars Squad 3v3 Frontend
 *
 * Vanilla JS only. POST SquadConfig.apiBase + /start_battle_3v3.php | /perform_action_3v3.php
 */

'use strict';

(function () {

  /* ══════════════════════════════════════════════════════════════
     CONFIG & STATE
  ══════════════════════════════════════════════════════════════ */
  const CFG = window.SquadConfig || {};
  const API = CFG.apiBase || '/api/mind-wars';
  const CSRF = CFG.csrfToken || '';

  // Live battle state
  const S = {
    battleToken:   null,
    state:         null,       // full state_json from backend
    selectedActor: null,       // { slot: 0|1|2 } — player unit the user clicked
    selectedTarget:null,       // { slot: 0|1|2 } — enemy unit the user clicked
    pendingAction: null,       // 'attack'|'ability'|'special'|'defend'
    difficulty:    'normal',
    busy:          false,      // prevents double-click sends
    slots: {
      front: null,
      mid:   null,
      back:  null,
    }
  };

  /* ══════════════════════════════════════════════════════════════
     DOM REFS
  ══════════════════════════════════════════════════════════════ */
  const el = {
    screenSelect: document.getElementById('screen-select'),
    screenBattle: document.getElementById('screen-battle'),
    screenResult: document.getElementById('screen-result'),

    // Select screen
    slotFront:    document.getElementById('slot-select-front'),
    slotMid:      document.getElementById('slot-select-mid'),
    slotBack:     document.getElementById('slot-select-back'),
    btnStart:     document.getElementById('btn-start'),
    selectError:  document.getElementById('select-error'),

    // Battle screen
    playerUnits:  document.getElementById('player-units'),
    enemyUnits:   document.getElementById('enemy-units'),
    bTurn:        document.getElementById('b-turn'),
    bDiff:        document.getElementById('b-diff'),
    bActor:       document.getElementById('b-actor'),
    actionContext:document.getElementById('action-context'),
    actionStatus: document.getElementById('action-status'),
    combatLog:    document.getElementById('combat-log'),
    btnAttack:    document.getElementById('btn-attack'),
    btnAbility:   document.getElementById('btn-ability'),
    btnSpecial:   document.getElementById('btn-special'),
    btnDefend:    document.getElementById('btn-defend'),
    initiativeStrip: document.getElementById('initiative-strip'),

    // Result screen
    resultGlyph:   document.getElementById('result-glyph'),
    resultTitle:   document.getElementById('result-title'),
    resultSub:     document.getElementById('result-sub'),
    resultRewards: document.getElementById('result-rewards'),
    btnPlayAgain:  document.getElementById('btn-play-again'),
  };

  /* ══════════════════════════════════════════════════════════════
     SCREEN MANAGEMENT
  ══════════════════════════════════════════════════════════════ */
  function showScreen(name) {
    ['select', 'battle', 'result'].forEach(n => {
      document.getElementById('screen-' + n).classList.toggle('active', n === name);
    });
  }

  /* ══════════════════════════════════════════════════════════════
     TEAM SELECT SCREEN
  ══════════════════════════════════════════════════════════════ */

  // Difficulty buttons
  document.querySelectorAll('.diff-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.diff-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      S.difficulty = btn.dataset.diff;
    });
  });

  // Slot select changes
  ['front', 'mid', 'back'].forEach(pos => {
    const sel = document.getElementById('slot-select-' + pos);
    if (!sel) return;
    sel.addEventListener('change', () => {
      S.slots[pos] = sel.value ? { item_id: parseInt(sel.value), position: pos } : null;
      updateSlotPreview(pos, sel);
      updateStartButton();
    });
  });

  function updateSlotPreview(pos, sel) {
    const frame = document.getElementById('slot-frame-' + pos);
    const nameEl = document.getElementById('slot-name-' + pos);
    const opt = sel.options[sel.selectedIndex];
    if (!sel.value || !opt) {
      frame.innerHTML = '<span class="slot-empty">＋</span>';
      nameEl.textContent = 'Empty';
      return;
    }
    const img  = opt.dataset.img;
    const name = opt.dataset.name || 'Avatar';
    const rar  = opt.dataset.rarity || 'common';
    frame.innerHTML = img
      ? `<img src="${escHtml(img)}" alt="${escHtml(name)}" class="slot-img rarity-${escHtml(rar)}">`
      : `<span class="slot-icon">⬡</span>`;
    nameEl.textContent = name;
  }

  function updateStartButton() {
    if (!hasMinimumConnectedAvatars()) {
      el.btnStart.disabled = true;
      showSelectError('Select 3 connected avatars to start.');
      return;
    }

    const all_filled = S.slots.front && S.slots.mid && S.slots.back;
    const unique = new Set([
      S.slots.front?.item_id,
      S.slots.mid?.item_id,
      S.slots.back?.item_id,
    ].filter(Boolean)).size === 3;
    el.btnStart.disabled = !(all_filled && unique);
    if (all_filled && !unique) {
      showSelectError('Each slot must use a different avatar.');
    } else {
      hideSelectError();
    }
  }

  function hasMinimumConnectedAvatars() {
    const sourceSelect = el.slotFront;
    if (!sourceSelect) return false;
    let available = 0;
    Array.prototype.forEach.call(sourceSelect.options, opt => {
      if (!opt || !opt.value) return;
      if (opt.disabled) return;
      available++;
    });
    return available >= 3;
  }

  function showSelectError(msg) {
    el.selectError.textContent = msg;
    el.selectError.classList.remove('hidden');
  }
  function hideSelectError() {
    el.selectError.classList.add('hidden');
  }

  el.btnStart.addEventListener('click', startBattle);

  /* ══════════════════════════════════════════════════════════════
     START BATTLE
  ══════════════════════════════════════════════════════════════ */
  async function startBattle() {
    if (S.busy) return;
    S.busy = true;
    el.btnStart.textContent = 'Initializing...';
    el.btnStart.disabled    = true;
    hideSelectError();

    const player_slots = [
      { slot: 0, position: 'front', item_id: S.slots.front.item_id },
      { slot: 1, position: 'mid',   item_id: S.slots.mid.item_id   },
      { slot: 2, position: 'back',  item_id: S.slots.back.item_id  },
    ];

    try {
      const data = await postJSON(API + '/start_battle_3v3.php', {
        csrf_token:   CSRF,
        difficulty:   S.difficulty,
        player_slots: player_slots,
      });

      if (!data.ok) {
        showSelectError(data.error?.message || 'Could not start battle.');
        return;
      }

      S.battleToken = data.data.battle_token;
      S.state       = data.data.state;
      S.selectedActor  = null;
      S.selectedTarget = null;
      S.pendingAction  = null;

      showScreen('battle');
      renderBattle();
      appendLog({ type: 'info', msg: 'Battle started! Squad link established.', turn: 1 });

    } catch (err) {
      showSelectError('Network error: ' + err.message);
    } finally {
      S.busy = false;
      el.btnStart.textContent = '⬡ ENGAGE SQUAD LINK';
      el.btnStart.disabled    = false;
    }
  }

  /* ══════════════════════════════════════════════════════════════
     RENDER BATTLE STATE
  ══════════════════════════════════════════════════════════════ */
  function renderBattle() {
    const state = S.state;
    if (!state) return;

    // Header — "turn" in state = combat round (all units act per round)
    el.bTurn.textContent = 'Round ' + state.turn + ' / ' + state.max_turns;
    el.bDiff.textContent = (state.meta?.difficulty || '').toUpperCase();

    const isPlayerTurn = state.next_actor === 'player';
    const actorSlot    = state.meta?.next_actor_slot ?? 0;

    if (isPlayerTurn) {
      const unit = state.squads?.player?.units?.[actorSlot];
      el.bActor.textContent = unit ? unit.name + ' acts' : 'Your turn';
      el.bActor.className   = 'meta-pill meta-pill--player';
    } else {
      el.bActor.textContent = 'Enemy acting...';
      el.bActor.className   = 'meta-pill meta-pill--enemy';
    }

    renderInitiativeStrip(state, isPlayerTurn ? 'player' : 'enemy', actorSlot);
    renderTeam('player', state.squads?.player?.units ?? {}, actorSlot, isPlayerTurn);
    renderTeam('enemy',  state.squads?.enemy?.units  ?? {}, null,      false);
    updateActionBar();
  }

  function renderInitiativeStrip(state, currentSide, currentSlot) {
    if (!el.initiativeStrip) return;
    const order = state.turn_order;
    if (!Array.isArray(order) || !order.length) {
      el.initiativeStrip.innerHTML = '';
      return;
    }
    const idx = state.turn_order_index ?? 0;
    const parts = ['<span class="initiative-strip__label">Initiative this round</span>'];
    order.forEach((entry, i) => {
      const side = entry.side || '';
      const slot = entry.slot ?? 0;
      const u = state.squads?.[side]?.units?.[slot];
      const name = u && !u.is_dead ? (u.name || side) : '—';
      const isCur = i === idx && side === currentSide && slot === currentSlot;
      const cls = [
        'init-pip',
        side === 'player' ? 'init-pip--player' : 'init-pip--enemy',
        isCur ? 'init-pip--current' : '',
      ].filter(Boolean).join(' ');
      const tag = side === 'player' ? 'YOU' : 'CPU';
      parts.push(`<span class="${cls}" title="${escHtml(name)}">${tag} ${slot + 1}</span>`);
    });
    el.initiativeStrip.innerHTML = parts.join('');
  }

  function renderTeam(side, units, activeSlot, isPlayerTurn) {
    const container = side === 'player' ? el.playerUnits : el.enemyUnits;
    container.innerHTML = '';

    const positions = ['front', 'mid', 'back'];
    [0, 1, 2].forEach(slot => {
      const unit = units[slot];
      if (!unit) return;

      const isActive    = (slot === activeSlot) && isPlayerTurn && !unit.is_dead;
      const isSelected  = side === 'player' && S.selectedActor?.slot === slot;
      const isTargeted  = side === 'enemy'  && S.selectedTarget?.slot === slot;

      const div = document.createElement('div');
      div.className = [
        'unit-card',
        `unit-card--${side}`,
        unit.is_dead ? 'unit-dead' : '',
        isActive     ? 'unit-active' : '',
        isSelected   ? 'unit-selected' : '',
        isTargeted   ? 'unit-targeted' : '',
        `rarity-border-${unit.rarity ?? 'common'}`,
      ].filter(Boolean).join(' ');
      div.dataset.slot = slot;
      div.dataset.side = side;

      const hpPct = unit.hp_max > 0 ? Math.max(0, Math.round((unit.hp / unit.hp_max) * 100)) : 0;
      const hpClass = hpPct <= 25 ? 'hp-fill--critical' : (hpPct <= 50 ? 'hp-fill--warn' : '');
      const energyPips = buildEnergyPips(unit.energy ?? 0);
      const posLabel  = positions[slot] ? positions[slot].toUpperCase() : '';
      const posIcon   = slot === 0 ? '▲' : (slot === 1 ? '◆' : '▼');

      div.innerHTML = `
        <div class="unit-position">${posIcon} ${posLabel}</div>
        ${unit.is_dead ? '<div class="unit-dead-overlay">✕ DEFEATED</div>' : ''}
        <div class="unit-avatar">
          ${unit.asset_path
            ? `<img src="${escHtml(unit.asset_path)}" alt="${escHtml(unit.name)}" class="unit-img">`
            : `<div class="unit-img-fallback">⬡</div>`}
        </div>
        <div class="unit-name">${escHtml(unit.name)}</div>
        <div class="unit-class">${escHtml((unit.combat_class || '').toUpperCase())}</div>
        <div class="unit-hp-wrap">
          <div class="unit-hp-label">
            <span>HP</span>
            <span>${unit.hp} / ${unit.hp_max}</span>
          </div>
          <div class="unit-hp-track">
            <div class="unit-hp-fill ${hpClass}" style="width:${hpPct}%"></div>
          </div>
        </div>
        <div class="unit-energy">${energyPips}</div>
        ${unit.ability_cooldown > 0 ? `<div class="unit-cooldown">CD: ${unit.ability_cooldown}</div>` : ''}
        ${unit.defending ? '<div class="unit-defending">🛡 DEFENDING</div>' : ''}
      `;

      // Click handlers
      if (side === 'player' && !unit.is_dead && S.state?.next_actor === 'player' && !S.busy) {
        div.addEventListener('click', () => selectActor(slot));
        div.style.cursor = 'pointer';
      }
      if (side === 'enemy' && !unit.is_dead && S.selectedActor !== null && !S.busy) {
        div.addEventListener('click', () => selectTarget(slot));
        div.style.cursor = 'crosshair';
      }

      container.appendChild(div);
    });
  }

  function buildEnergyPips(energy) {
    let html = '<div class="energy-pips">';
    for (let i = 0; i < 5; i++) {
      html += `<span class="energy-pip ${i < energy ? 'pip-on' : 'pip-off'}"></span>`;
    }
    html += '</div>';
    return html;
  }

  /* ══════════════════════════════════════════════════════════════
     ACTION SELECTION FLOW
  ══════════════════════════════════════════════════════════════ */
  function selectActor(slot) {
    if (S.state?.next_actor !== 'player') return;
    const expected = S.state?.meta?.next_actor_slot ?? -1;
    if (slot !== expected) {
      setActionStatus(`It's ${S.state.squads.player.units[expected]?.name}'s turn, not this unit.`);
      return;
    }

    S.selectedActor  = { slot };
    S.selectedTarget = null;
    S.pendingAction  = null;

    const unit = S.state.squads.player.units[slot];
    setActionContext(`${unit.name} selected — choose an action.`);
    setActionStatus('');
    renderBattle();
    enableActionBtns(unit);
  }

  function selectTarget(slot) {
    if (S.selectedActor === null || !S.pendingAction) return;
    S.selectedTarget = { slot };
    renderBattle();
    setActionStatus(`Target: ${S.state.squads.enemy.units[slot]?.name} — confirm?`);
    // Auto-submit on target click when action is already chosen
    sendAction();
  }

  function setActionContext(msg) { el.actionContext.textContent = msg }
  function setActionStatus(msg)  { el.actionStatus.textContent  = msg }

  function enableActionBtns(unit) {
    if (!unit) return;
    const energy   = unit.energy ?? 0;
    const cooldown = unit.ability_cooldown ?? 0;

    el.btnAttack.disabled  = false;
    el.btnDefend.disabled  = false;
    el.btnAbility.disabled = (energy < 2) || (cooldown > 0);
    el.btnSpecial.disabled = (energy < 5) || !unit.special_code;

    el.btnAttack.classList.toggle('btn-unavailable', false);
    el.btnAbility.classList.toggle('btn-unavailable', el.btnAbility.disabled);
    el.btnSpecial.classList.toggle('btn-unavailable', el.btnSpecial.disabled);
  }

  function updateActionBar() {
    const isPlayer = S.state?.next_actor === 'player';
    const busy     = S.busy;

    if (!isPlayer || busy) {
      [el.btnAttack, el.btnAbility, el.btnSpecial, el.btnDefend].forEach(b => b.disabled = true);
      if (!isPlayer) {
        setActionContext('Enemy is acting...');
        setActionStatus('');
      }
    } else if (!S.selectedActor) {
      [el.btnAttack, el.btnAbility, el.btnSpecial, el.btnDefend].forEach(b => b.disabled = true);
      const expectedSlot = S.state?.meta?.next_actor_slot ?? 0;
      const unit = S.state?.squads?.player?.units?.[expectedSlot];
      setActionContext(`Click ${unit?.name ?? 'your unit'} to act.`);
    }
  }

  // Wire action buttons — they set S.pendingAction, then prompt for target
  [el.btnAttack, el.btnAbility, el.btnSpecial, el.btnDefend].forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.disabled || S.selectedActor === null) return;
      const action = btn.dataset.action;
      S.pendingAction = action;

      if (action === 'defend') {
        // Defend needs no target
        S.selectedTarget = null;
        sendAction();
      } else {
        setActionContext(`${action.toUpperCase()} — click an enemy unit to target.`);
        setActionStatus('');
        // Re-render to make enemy units clickable
        renderBattle();
      }
    });
  });

  /* ══════════════════════════════════════════════════════════════
     SEND ACTION TO BACKEND
  ══════════════════════════════════════════════════════════════ */
  async function sendAction() {
    if (S.busy) return;
    if (!S.battleToken) return;
    if (S.selectedActor === null) return;
    if (S.pendingAction !== 'defend' && S.selectedTarget === null) return;

    S.busy = true;
    updateActionBar();
    setActionStatus('Processing...');

    const payload = {
      csrf_token:   CSRF,
      battle_token: S.battleToken,
      actor_slot:   S.selectedActor.slot,
      action:       S.pendingAction,
      target_slot:  S.selectedTarget?.slot ?? 0,
    };

    try {
      const data = await postJSON(API + '/perform_action_3v3.php', payload);

      if (!data.ok) {
        setActionStatus('Error: ' + (data.error?.message || 'Unknown error'));
        S.busy = false;
        updateActionBar();
        return;
      }

      const d = data.data;
      S.state = d.state;

      // Log player action
      if (d.action_result) {
        logActionResult(d.action_result);
      }
      // Log AI actions
      if (Array.isArray(d.ai_actions)) {
        d.ai_actions.forEach(logActionResult);
      }
      // Append any new log entries from backend
      const log = d.state?.log ?? [];
      if (log.length) {
        appendLog(log[log.length - 1]); // only latest to avoid dupes
      }

      S.selectedActor  = null;
      S.selectedTarget = null;
      S.pendingAction  = null;

      if (d.battle_over) {
        showResult(d.winner, d.rewards);
        return;
      }

      renderBattle();
      setActionStatus('');
      setActionContext(
        d.next_actor === 'player'
          ? `Click ${S.state?.squads?.player?.units?.[d.next_actor_slot]?.name ?? 'your unit'} to act.`
          : 'Enemy acting...'
      );

    } catch (err) {
      setActionStatus('Network error: ' + err.message);
    } finally {
      S.busy = false;
      updateActionBar();
    }
  }

  /* ══════════════════════════════════════════════════════════════
     COMBAT LOG
  ══════════════════════════════════════════════════════════════ */
  function logActionResult(ar) {
    if (!ar) return;
    const actor  = ar.actor?.name  || '?';
    const target = ar.target?.name || '?';
    const action = (ar.action || '').toUpperCase();
    let msg = '';

    if (ar.action === 'defend') {
      msg = `${actor} takes a defensive stance.`;
    } else if (ar.evaded) {
      msg = `${actor} [${action}] → ${target}: EVADED!`;
    } else {
      msg = `${actor} [${action}] → ${target}: ${ar.damage} dmg`;
      if (ar.crit)        msg += ' ★CRIT';
      if (ar.target_died) msg += ` — ${target} DEFEATED!`;
    }
    appendLog({
      type:  ar.damage > 0 ? 'damage' : 'info',
      msg:   msg,
      actor: ar.actor?.side,
      turn:  S.state?.turn ?? 0,
    });
  }

  let _lastLogMsg = '';
  function appendLog(entry) {
    if (!entry) return;
    // Deduplicate consecutive identical messages
    if (entry.msg === _lastLogMsg) return;
    _lastLogMsg = entry.msg;

    const div = document.createElement('div');
    const typeClass = {
      damage: 'log-damage',
      info:   'log-info',
      result: 'log-result',
    }[entry.type] || 'log-info';
    const sideClass = entry.actor === 'player' ? 'log-player' : (entry.actor === 'enemy' ? 'log-enemy' : '');
    div.className = `log-entry ${typeClass} ${sideClass}`;
    div.innerHTML = `<span class="log-turn">T${entry.turn ?? '?'}</span> ${escHtml(entry.msg)}`;
    el.combatLog.appendChild(div);
    el.combatLog.scrollTop = el.combatLog.scrollHeight;
  }

  /* ══════════════════════════════════════════════════════════════
     RESULT SCREEN
  ══════════════════════════════════════════════════════════════ */
  function showResult(winner, rewards) {
    const isWin  = winner === 'player';
    const isDraw = winner === 'draw' || !winner;

    el.resultGlyph.textContent = isWin ? '🏆' : (isDraw ? '◈' : '✕');
    el.resultTitle.textContent = isWin ? 'SQUAD VICTORY' : (isDraw ? 'DRAW' : 'SQUAD DEFEATED');
    el.resultSub.textContent   = isWin
      ? 'Your neural squad prevailed. Rewards are applied to your account and lead avatar.'
      : (isDraw
        ? 'Stalemate. Any draw rewards are applied to your account and lead avatar.'
        : 'Your squad was overwhelmed.');

    if (rewards) {
      el.resultRewards.innerHTML = `
        <div class="reward-row"><span>XP</span><span>+ ${rewards.xp}</span></div>
        <div class="reward-row"><span>Knowledge Energy (lead)</span><span>+ ${rewards.knowledge_energy}</span></div>
        <div class="reward-row"><span>Rank</span><span>+ ${rewards.rank}</span></div>
      `;
    }

    el.resultGlyph.className = isWin ? 'result-glyph result-win' : (isDraw ? 'result-glyph result-draw' : 'result-glyph result-lose');
    showScreen('result');
  }

  el.btnPlayAgain.addEventListener('click', () => {
    S.battleToken    = null;
    S.state          = null;
    S.selectedActor  = null;
    S.selectedTarget = null;
    S.pendingAction  = null;
    S.busy           = false;
    el.combatLog.innerHTML = '';
    _lastLogMsg = '';
    showScreen('select');
  });

  /* ══════════════════════════════════════════════════════════════
     UTILITIES
  ══════════════════════════════════════════════════════════════ */
  async function postJSON(url, body) {
    const r = await fetch(url, {
      method:      'POST',
      credentials: 'include',
      headers:     { 'Content-Type': 'application/json' },
      body:        JSON.stringify(body),
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  }

  function escHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c =>
      ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]
    );
  }

  /* ══════════════════════════════════════════════════════════════
     INIT
  ══════════════════════════════════════════════════════════════ */
  showScreen('select');

})();
