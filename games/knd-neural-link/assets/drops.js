/**
 * KND Neural Link — drops.js (vanilla)
 */

'use strict';

const RARITY_COLORS = {
  common:    '#4a8a9a',
  rare:      '#1a6aee',
  special:   '#18aa6a',
  epic:      '#d6cfc7',
  legendary: '#ffcc00',
};
const RARITY_LABELS = {
  common: 'COMMON', rare: 'RARE', special: 'SPECIAL', epic: 'EPIC', legendary: 'LEGENDARY',
};
/** Sci-fi reveal labels (API still uses rarity keys) */
const RARITY_UX_LABELS = {
  common: 'BASELINE SIGNAL',
  rare: 'STRONG SIGNAL',
  special: 'LOCKED PATTERN',
  epic: 'HIGH COHERENCE',
  legendary: 'PERFECT SYNC',
};
const RARITY_GLOWS = {
  common:    'rgba(74,138,154,.35)',
  rare:      'rgba(26,106,238,.45)',
  special:   'rgba(24,170,106,.4)',
  epic:      'rgba(155,48,255,.45)',
  legendary: 'rgba(255,204,0,.55)',
};
const STAT_COLORS = { mind:'#c040ff', focus:'#00e8ff', speed:'#00ff99', luck:'#ffcc00' };
const STAT_LABELS = { mind:'MND', focus:'FCS', speed:'SPD', luck:'LCK' };

const State = {
  packs:           [],
  selectedPack:    null,
  balance:         { knd_points: 0 },
  pity:            { legendary: 0, epic: 0 },
  lastResult:      null,
  sessionHistory:  [],
  isOpening:       false,
};

const DOM = {};
function resolveDOM() {
  const ids = [
    'packs-grid','bal-kp',
    'stage-idle','stage-opening','stage-result',
    'open-btn','open-btn-cost','open-btn-hint',
    'pid-name','pid-desc','pid-rates',
    'pf-legendary','pf-epic','pv-legendary','pv-epic',
    'open-burst','open-shatter','open-label',
    'result-card','rc-topbar','rc-portrait','rc-glow-ring',
    'rc-image','rc-beam','rc-rarity-badge','rc-name','rc-class',
    'rc-stats','rc-duplicate','dup-ke','ra-equip','ra-again',
    'history-list','equip-modal','em-av-name','em-av-class','em-confirm',
    'capsule-wrap',
  ];
  ids.forEach(id => { DOM[id] = document.getElementById(id); });
}

function initBgCanvas() {
  const c   = document.getElementById('bg-canvas');
  if (!c) return;
  const ctx = c.getContext('2d');
  const resize = () => { c.width = innerWidth; c.height = innerHeight; };
  resize();
  window.addEventListener('resize', resize);

  const stars = Array.from({length: 100}, () => ({
    x: Math.random() * innerWidth,
    y: Math.random() * innerHeight,
    r: Math.random() * 1.4,
    speed: .002 + Math.random() * .007,
    col: Math.random() > .65 ? 'rgba(155,48,255,' : 'rgba(0,232,255,',
  }));

  (function draw() {
    ctx.clearRect(0, 0, c.width, c.height);
    stars.forEach(s => {
      const a = Math.sin(Date.now() * s.speed) * .5 + .5;
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.fillStyle = s.col + a + ')';
      ctx.fill();
    });
    requestAnimationFrame(draw);
  })();
}

function toast(msg, type = 'info') {
  const icons = {info:'ℹ️', ok:'✅', warn:'⚠️', error:'❌'};
  const stack = document.getElementById('toast-stack');
  const el    = document.createElement('div');
  el.className = `toast-item ${type}`;
  el.innerHTML = `<span>${icons[type]||'ℹ️'}</span><span>${msg}</span>`;
  stack.appendChild(el);
  setTimeout(() => {
    el.classList.add('out');
    setTimeout(() => el.remove(), 320);
  }, 3600);
}

function screenFlash(color = 'rgba(0,232,255,.1)') {
  const el = document.getElementById('screen-flash');
  el.style.background = color;
  el.classList.add('pop');
  setTimeout(() => el.classList.remove('pop'), 90);
}

let _audioCtx;
function playClick(freq = 880) {
  try {
    if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const o = _audioCtx.createOscillator();
    const g = _audioCtx.createGain();
    o.connect(g); g.connect(_audioCtx.destination);
    o.type = 'sine';
    o.frequency.setValueAtTime(freq, _audioCtx.currentTime);
    o.frequency.exponentialRampToValueAtTime(freq * .5, _audioCtx.currentTime + .08);
    g.gain.setValueAtTime(.05, _audioCtx.currentTime);
    g.gain.exponentialRampToValueAtTime(.001, _audioCtx.currentTime + .1);
    o.start(); o.stop(_audioCtx.currentTime + .1);
  } catch(e) {}
}
function playReveal(rarity) {
  const freqMap = {common:440, rare:550, special:660, epic:880, legendary:1100};
  const f = freqMap[rarity] || 440;
  try {
    if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    [f, f*1.25, f*1.5].forEach((freq, i) => {
      const o = _audioCtx.createOscillator();
      const g = _audioCtx.createGain();
      o.connect(g); g.connect(_audioCtx.destination);
      o.type = 'sine'; o.frequency.value = freq;
      g.gain.setValueAtTime(.06, _audioCtx.currentTime + i*.08);
      g.gain.exponentialRampToValueAtTime(.001, _audioCtx.currentTime + i*.08 + .25);
      o.start(_audioCtx.currentTime + i*.08);
      o.stop(_audioCtx.currentTime + i*.08 + .3);
    });
  } catch(e) {}
}

const API = {
  base: (window.KND_CONFIG?.apiBase) || '/games/knd-neural-link/api',

  async get(path) {
    const r = await fetch(this.base + path, { credentials: 'include' });
    let data = {};
    try { data = await r.json(); } catch (e) { /* ignore */ }
    if (!r.ok) {
      const err = new Error(data.error || `HTTP ${r.status}`);
      err.aguacate = data.aguacate;
      err.payload = data;
      throw err;
    }
    return data;
  },

  async post(path, body) {
    const r = await fetch(this.base + path, {
      method:      'POST',
      credentials: 'include',
      headers:     { 'Content-Type': 'application/json' },
      body:        JSON.stringify(body),
    });
    let data = {};
    try { data = await r.json(); } catch (e) { /* ignore */ }
    if (!r.ok) {
      const err = new Error(data.error || `HTTP ${r.status}`);
      err.aguacate = data.aguacate;
      err.payload = data;
      throw err;
    }
    return data;
  },
};

function updateBalance(balance) {
  const kp = balance.knd_points ?? balance.coins ?? 0;
  State.balance = { knd_points: kp };
  if (DOM['bal-kp']) DOM['bal-kp'].textContent = Number(kp).toLocaleString();
}

function updatePity(pity, selectedPack) {
  State.pity = pity;
  if (!selectedPack) return;

  const legPct  = Math.min(100, (pity.legendary / selectedPack.pity_legendary) * 100);
  const epicPct = Math.min(100, (pity.epic       / selectedPack.pity_epic)       * 100);

  DOM['pf-legendary'].style.width = legPct + '%';
  DOM['pf-epic'].style.width      = epicPct + '%';
  DOM['pv-legendary'].textContent = pity.legendary;
  DOM['pv-epic'].textContent      = pity.epic;
}

function renderPacks(packs) {
  State.packs = packs;
  const grid  = DOM['packs-grid'];
  grid.innerHTML = '';

  packs.forEach(pack => {
    const card = document.createElement('div');
    card.className = 'pack-card';
    card.dataset.packId = pack.id;
    card.style.setProperty('--pack-col', pack.color || 'var(--c)');

    const kp = pack.cost_kp ?? pack.cost_coins ?? 0;
    const costLabel = `◇ ${Number(kp).toLocaleString()} KP`;

    const ratesHTML = (pack.rates || [])
      .filter(r => r.pct > 0)
      .map(r => `<span class="pcr-pip" data-r="${r.rarity}">${RARITY_UX_LABELS[r.rarity] || r.label} ${r.pct}%</span>`)
      .join('');

    card.innerHTML = `
      <div class="pc-header">
        <span class="pc-name">${pack.label}</span>
        <span class="pc-cost">${costLabel}</span>
      </div>
      <div class="pc-desc">${pack.description}</div>
      <div class="pc-rates-mini">${ratesHTML}</div>
    `;
    card.addEventListener('click', () => selectPack(pack.id));
    grid.appendChild(card);
  });
}

function selectPack(packId) {
  if (State.isOpening) return;
  playClick();

  State.selectedPack = State.packs.find(p => p.id === packId) || null;
  if (!State.selectedPack) return;

  const pack = State.selectedPack;
  const col  = pack.color || 'var(--c)';

  document.querySelectorAll('.pack-card').forEach(c => {
    c.classList.toggle('selected', c.dataset.packId === packId);
  });

  const capsuleCore = document.querySelector('.capsule-core');
  if (capsuleCore) capsuleCore.style.background =
    `radial-gradient(circle at 40% 35%,color-mix(in srgb,${col} 35%,transparent),rgba(0,20,40,.9))`;
  const capsuleRings = document.querySelectorAll('.capsule-ring');
  capsuleRings.forEach((r,i) => {
    const alphas = ['.55','.5','.3'];
    r.style.borderTopColor    = i === 0 ? col.replace(')',`,${alphas[i]})`) : 'transparent';
    r.style.borderBottomColor = i === 1 ? col.replace(')',`,${alphas[i]})`) : 'transparent';
    r.style.borderTopColor    = i === 2 ? col.replace(')',`,${alphas[i]})`) : (i===0?col:'transparent');
  });

  DOM['pid-name'].textContent = pack.label;
  DOM['pid-desc'].textContent = pack.description;

  const ratesEl = DOM['pid-rates'];
  ratesEl.innerHTML = '';
  (pack.rates || []).filter(r => r.pct > 0).forEach(r => {
    const row = document.createElement('div');
    row.className = 'pid-rate-row';
    row.dataset.rarity = r.rarity;
    row.style.background = `color-mix(in srgb,${RARITY_COLORS[r.rarity]} 8%,transparent)`;
    row.style.border = `1px solid color-mix(in srgb,${RARITY_COLORS[r.rarity]} 25%,transparent)`;
    row.style.color  = RARITY_COLORS[r.rarity];
    const ux = RARITY_UX_LABELS[r.rarity] || r.label;
    row.innerHTML = `<span>${ux}</span><span>${r.pct}%</span>
      <span style="font-size:9.2px;color:var(--t3)">${r.pool_count} entities</span>`;
    ratesEl.appendChild(row);
  });

  updatePity(State.pity, pack);

  const kp = pack.cost_kp ?? pack.cost_coins ?? 0;
  DOM['open-btn-cost'].textContent = `◇ ${Number(kp).toLocaleString()} KP`;
  DOM['open-btn-hint'].textContent = 'Initiate neural link when ready';
  DOM['open-btn'].style.borderColor = col;
  DOM['open-btn'].style.color       = col;
  DOM['open-btn'].querySelector('.ob-icon').textContent = '⬡';
  DOM['open-btn'].disabled = false;
}

function showState(which) {
  ['stage-idle','stage-opening','stage-result'].forEach(id => {
    DOM[id].classList.toggle('hidden', id !== which);
  });
}

async function runOpeningAnimation(rarityColor) {
  showState('stage-opening');

  DOM['open-label'].textContent = 'Initializing Neural Link…';
  await sleep(420);

  DOM['open-label'].textContent = 'Scanning consciousness layer…';
  await sleep(480);

  const stability = 62 + Math.floor(Math.random() * 35);
  DOM['open-label'].textContent = `Signal stability: ${stability}%`;
  await sleep(520);

  DOM['open-burst'].classList.add('fire');
  screenFlash(rarityColor.replace(')',', .08)'));
  await sleep(280);

  DOM['open-shatter'].classList.add('explode');
  DOM['open-label'].textContent = 'Opening capsule…';
  playClick(660);
  await sleep(420);

  DOM['open-label'].textContent = 'Entity detected';
  await sleep(380);

  DOM['open-label'].textContent = 'Link established';
  await sleep(320);
}

async function runRevealAnimation(result) {
  const { avatar, is_duplicate, ke_gained } = result;
  const rarity    = avatar.rarity;
  const rarColor  = RARITY_COLORS[rarity];
  const rarGlow   = RARITY_GLOWS[rarity];

  showState('stage-result');

  const card = DOM['result-card'];
  card.style.setProperty('--rc-color', rarColor);

  DOM['rc-topbar'].style.background = rarColor;
  DOM['rc-topbar'].style.boxShadow  = `0 0 16px ${rarColor}`;

  DOM['rc-glow-ring'].style.borderColor = `color-mix(in srgb,${rarColor} 40%,transparent)`;
  DOM['rc-glow-ring'].style.boxShadow   = `0 0 50px ${rarGlow}`;
  DOM['rc-beam'].style.background       = `linear-gradient(0deg,color-mix(in srgb,${rarColor} 35%,transparent),transparent)`;

  const img = DOM['rc-image'];
  if (avatar.image) {
    img.src   = avatar.image;
    img.alt   = avatar.name;
    img.style.filter = `drop-shadow(0 0 22px ${rarGlow})`;
    img.onerror = () => { img.style.display='none'; renderPortraitFallback(avatar, rarColor); };
    img.style.display = 'block';
  } else {
    img.style.display = 'none';
    renderPortraitFallback(avatar, rarColor);
  }

  DOM['rc-rarity-badge'].textContent = RARITY_UX_LABELS[rarity] || RARITY_LABELS[rarity];
  DOM['rc-rarity-badge'].style.borderColor  = rarColor;
  DOM['rc-rarity-badge'].style.color        = rarColor;
  DOM['rc-rarity-badge'].style.background   = `color-mix(in srgb,${rarColor} 8%,transparent)`;
  DOM['rc-rarity-badge'].style.textShadow   = `0 0 8px ${rarColor}`;

  DOM['rc-name'].textContent  = avatar.name;
  DOM['rc-class'].textContent = (avatar.class || '').toUpperCase();

  renderStats(avatar.stats || {});

  if (is_duplicate) {
    DOM['rc-duplicate'].classList.remove('hidden');
    DOM['dup-ke'].textContent = `+${ke_gained} KE`;
  } else {
    DOM['rc-duplicate'].classList.add('hidden');
  }

  DOM['ra-equip'].classList.toggle('hidden', is_duplicate);

  await sleep(100);
  screenFlash(rarGlow);
  playReveal(rarity);

  if (rarity === 'legendary') {
    await sleep(200); screenFlash('rgba(255,204,0,.12)');
    await sleep(200); screenFlash('rgba(255,204,0,.08)');
  } else if (rarity === 'epic') {
    await sleep(200); screenFlash('rgba(155,48,255,.1)');
  }

  document.getElementById('result-rays').style.background =
    `conic-gradient(from 0deg at 50% 50%,transparent 0deg,${rarGlow.replace(')',', .06)')} 10deg,transparent 20deg,transparent 40deg,${rarGlow.replace(')',', .04)')} 50deg,transparent 60deg)`;
}

function renderPortraitFallback(avatar, rarColor) {
  const portrait = DOM['rc-portrait'];
  let existing = portrait.querySelector('.av-fallback');
  if (existing) existing.remove();
  const fb = document.createElement('div');
  fb.className = 'av-fallback';
  fb.style.cssText = `
    position:relative;z-index:3;display:flex;flex-direction:column;align-items:center;
    gap:3px;margin-bottom:12px;animation:imgFloat 4s ease-in-out infinite;
  `;
  fb.innerHTML = `
    <div style="width:46px;height:46px;border-radius:50%;
      background:linear-gradient(145deg,color-mix(in srgb,${rarColor} 28%,transparent),color-mix(in srgb,${rarColor} 4%,transparent));
      border:2px solid color-mix(in srgb,${rarColor} 45%,transparent);
      box-shadow:0 0 22px color-mix(in srgb,${rarColor} 22%,transparent);
      display:flex;align-items:center;justify-content:center;font-size:20.7px">⬡</div>
    <div style="width:18.4px;height:12px;background:color-mix(in srgb,${rarColor} 14%,transparent);border:1px solid color-mix(in srgb,${rarColor} 22%,transparent)"></div>
    <div style="width:72px;height:90px;clip-path:polygon(14% 0%,86% 0%,100% 100%,0% 100%);
      background:linear-gradient(180deg,color-mix(in srgb,${rarColor} 22%,transparent),color-mix(in srgb,${rarColor} 5%,transparent));
      border:1px solid color-mix(in srgb,${rarColor} 25%,transparent);
      display:flex;align-items:center;justify-content:center;font-size:27.6px;color:color-mix(in srgb,${rarColor} 25%,transparent)">⬡</div>
  `;
  portrait.insertBefore(fb, portrait.querySelector('.rc-platform'));
}

function renderStats(stats) {
  const el = DOM['rc-stats'];
  el.innerHTML = '';
  Object.entries(stats).forEach(([key, val]) => {
    const label = STAT_LABELS[key] || key.substring(0,3).toUpperCase();
    const col   = STAT_COLORS[key] || 'var(--c)';
    const pct   = Math.min(100, val);
    const div = document.createElement('div');
    div.className = 'rcs';
    div.innerHTML = `
      <div class="rcs-row">
        <span class="rcs-key">${label}</span>
        <span class="rcs-val">${val}</span>
      </div>
      <div class="rcs-bar">
        <div class="rcs-fill" style="--sc:${col};width:${pct}%"></div>
      </div>
    `;
    el.appendChild(div);
  });
}

function addHistoryEntry(avatar, isDuplicate) {
  State.sessionHistory.unshift({ avatar, isDuplicate });
  if (State.sessionHistory.length > 20) State.sessionHistory.pop();
  renderHistory();
}

function renderHistory() {
  const list = DOM['history-list'];
  list.innerHTML = '';

  if (State.sessionHistory.length === 0) {
    list.innerHTML = '<div class="history-empty">No links this session</div>';
    return;
  }

  State.sessionHistory.forEach(({ avatar, isDuplicate }) => {
    const rarColor = RARITY_COLORS[avatar.rarity] || 'var(--c)';
    const entry = document.createElement('div');
    entry.className = 'history-entry';
    entry.style.setProperty('--rc-color', rarColor);

    const imgHTML = avatar.image
      ? `<img class="he-img" src="${avatar.image}" alt="${avatar.name}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
      : '';
    const placeholderHTML = `<div class="he-img-placeholder" style="color:${rarColor}">⬡</div>`;
    const ux = RARITY_UX_LABELS[avatar.rarity] || RARITY_LABELS[avatar.rarity];

    entry.innerHTML = `
      ${imgHTML}
      ${placeholderHTML}
      <div class="he-info">
        <div class="he-name">${avatar.name}</div>
        <div class="he-meta">${ux} · ${avatar.class || ''}</div>
      </div>
      ${isDuplicate ? `<span class="he-dup">+KE</span>` : ''}
    `;
    list.appendChild(entry);
  });
}

function openEquipModal(avatar) {
  DOM['em-av-name'].textContent  = avatar.name;
  DOM['em-av-class'].textContent = (avatar.class || '').toUpperCase();
  DOM['equip-modal'].classList.remove('hidden');
  DOM['em-confirm'].onclick = () => confirmEquip(avatar.id);
}

async function confirmEquip(itemId) {
  DOM['equip-modal'].classList.add('hidden');
  const url = window.KND_CONFIG?.equipUrl || '/api/avatar/set_favorite.php';
  const token = window.KND_CONFIG?.csrfToken || '';
  try {
    const fd = new FormData();
    fd.append('csrf_token', token);
    fd.append('item_id', String(itemId));
    const r = await fetch(url, { method: 'POST', body: fd, credentials: 'include' });
    const data = await r.json().catch(() => ({}));
    if (!r.ok || !data.ok) {
      throw new Error(data.error || data.message || `HTTP ${r.status}`);
    }
    toast('Active avatar updated', 'ok');
    playClick(1100);
  } catch (e) {
    toast(e.message || 'Equip failed', 'error');
  }
}

function resetStageAnimState() {
  DOM['open-burst'].classList.remove('fire');
  DOM['open-shatter'].classList.remove('explode');
  DOM['result-card'].style.removeProperty('--rc-color');
  DOM['rc-image'].src = '';
  const fb = DOM['rc-portrait'].querySelector('.av-fallback');
  if (fb) fb.remove();
}

/**
 * @param {object} opts
 * @param {boolean} [opts.playSound]
 * @param {boolean} [opts.clearLastResult]
 */
function resetToIdleState(opts = {}) {
  const playSound = opts.playSound === true;
  const clearLast = opts.clearLastResult === true;
  if (playSound) playClick();
  if (clearLast) State.lastResult = null;
  State.isOpening = false;
  resetStageAnimState();
  showState('stage-idle');
  DOM['open-btn'].disabled = !State.selectedPack;
  DOM['open-btn'].querySelector('.ob-label').textContent = 'INITIATE NEURAL LINK';
}

async function openDrop() {
  if (State.isOpening || !State.selectedPack) return;
  State.isOpening = true;

  DOM['open-btn'].disabled = true;
  DOM['open-btn'].querySelector('.ob-label').textContent = 'OPENING CAPSULE…';

  const packColor = State.selectedPack.color || '#00e8ff';

  try {
    const animPromise = runOpeningAnimation(packColor);
    const apiPromise  = API.post('/open_drop.php', {
      drop_type: State.selectedPack.id,
    });

    const [_, result] = await Promise.all([animPromise, apiPromise]);

    if (result.dry_run) {
      toast('Dry run (SANDBOX_MODE off) — no charges', 'warn');
    }

    if (!result.success) {
      const msgMap = {
        insufficient_kp: 'Not enough KND Points',
        server_error:    'Server error — try again',
      };
      const base = msgMap[result.error] || result.error || 'Request failed';
      const ag = result.aguacate ? ` [AGUACATE:${result.aguacate}]` : '';
      throw new Error(base + ag);
    }

    State.lastResult = result;

    updateBalance(result.new_balance);
    updatePity({
      legendary: result.pity_counter.legendary,
      epic:      result.pity_counter.epic,
    }, State.selectedPack);

    await runRevealAnimation(result);

    addHistoryEntry(result.avatar, result.is_duplicate);

    if (result.avatar.rarity === 'legendary') {
      toast(`Perfect sync — ${result.avatar.name}`, 'ok');
    } else if (result.avatar.rarity === 'epic') {
      toast(`High coherence — ${result.avatar.name}`, 'ok');
    } else if (result.is_duplicate) {
      toast(`Duplicate entity — +${result.ke_gained} KE`, 'warn');
    } else {
      toast('Entity acquired — synchronization complete', 'ok');
    }

  } catch (err) {
    const ag = err.aguacate ? ` AGUACATE:${err.aguacate}` : '';
    toast((err.message || 'Something went wrong') + ag, 'error');
    resetToIdleState({ playSound: false, clearLastResult: false });
  } finally {
    State.isOpening = false;
    DOM['open-btn'].querySelector('.ob-label').textContent = 'INITIATE NEURAL LINK';
  }
}

const KNDDrops = {
  openDrop,

  openAgain() {
    if (!State.selectedPack) return;
    playClick();
    resetStageAnimState();
    showState('stage-idle');
    DOM['open-btn'].disabled = false;
    DOM['open-btn'].querySelector('.ob-label').textContent = 'INITIATE NEURAL LINK';
    setTimeout(() => openDrop(), 120);
  },

  resetToIdle() {
    resetToIdleState({ playSound: true, clearLastResult: true });
  },

  equipAvatar() {
    if (!State.lastResult) return;
    playClick();
    openEquipModal(State.lastResult.avatar);
  },

  closeEquipModal() {
    playClick();
    DOM['equip-modal'].classList.add('hidden');
  },
};
window.KNDDrops = KNDDrops;

function sleep(ms) {
  return new Promise(res => setTimeout(res, ms));
}

async function init() {
  resolveDOM();
  initBgCanvas();

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') KNDDrops.resetToIdle();
    if ((e.key === 'Enter' || e.key === ' ') && State.selectedPack && !State.isOpening) {
      e.preventDefault(); openDrop();
    }
  });

  document.addEventListener('click', e => {
    if (e.target.closest('button, .pack-card, .bal-chip')) playClick();
  }, true);

  try {
    const data = await API.get('/get_drop_rates.php');
    if (!data.success) throw new Error(data.error || 'Failed to load');

    renderPacks(data.packs);
    updateBalance(data.balance);
    updatePity(data.pity, null);

  } catch (err) {
    const ag = err.aguacate ? ` AGUACATE:${err.aguacate}` : '';
    toast('Could not load link data — check session or DB' + ag, 'error');
    console.error('[KNL init]', err);
    DOM['packs-grid'].innerHTML = '<div style="color:var(--t3);font-family:var(--FM);font-size:11.5px;padding:16px">Link catalog unavailable</div>';
  }
}

document.addEventListener('DOMContentLoaded', init);
