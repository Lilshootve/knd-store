/* global MW_LOBBY_CSRF, MW_LOBBY_INITIAL */
(function () {
  'use strict';

  const CSRF = typeof MW_LOBBY_CSRF !== 'undefined' ? MW_LOBBY_CSRF : '';
  let lobbyData = typeof MW_LOBBY_INITIAL !== 'undefined' && MW_LOBBY_INITIAL ? MW_LOBBY_INITIAL : null;
  let selectedMode = 'pvp';
  /** 'mode' | 'pve-format' — second step after choosing PvE */
  let mmUiStep = 'mode';
  let selectedPveFormat = '1v1';
  const MM_TITLE_MODE = '⚔ SELECT BATTLE MODE';
  const MM_TITLE_PVE = '⚔ PVE FORMAT';
  const MM_TITLE_PVE_3V3 = '⚔ PVE 3V3 — SQUAD';
  const MM_BTN_ENTER = '⚔ ENTER BATTLE';
  const MM_BTN_START = '⚔ START';
  /** @type {(object|null)[]} */
  let mmPve3v3Slots = [null, null, null];
  let mmPve3v3PickIndex = null;
  let queuePollTimer = null;
  let searchInterval = null;
  let searchSecs = 0;
  let heroHologramMessageHandler = null;
  /** @type {AudioContext|null} */
  let audioCtx = null;

  const HOLOGRAM_VIEWER_PATH = '/tools/hologram-viewer-epic/';

  const HERO_SIL_HTML =
    '<div class="hero-sil" id="hero-avatar-fallback">' +
    '<div class="hs-head"></div><div class="hs-neck"></div><div class="hs-torso"></div>' +
    '<div class="hs-legs"><div class="hs-leg"></div><div class="hs-leg"></div></div></div>';

  const RARITY_BAR = {
    common: '#a1a1a1',
    special: '#22c55e',
    rare: '#22d3ee',
    epic: '#d6cfc7',
    legendary: '#eab308'
  };

  const BATTLE_SUBTEXTS = [
    'READY FOR COMBAT',
    'NEURAL LINK ACTIVE',
    'MIND SHARPENED',
    'AWAITING OPPONENT',
    'POWER AT PEAK'
  ];

  async function fetchJson(url, options) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, options || {}));
    const text = await r.text();
    let j;
    try {
      j = JSON.parse(text);
    } catch (e) {
      throw new Error('Invalid JSON');
    }
    if (!j.ok) {
      const msg = (j.error && j.error.message) || j.error || r.statusText || 'Request failed';
      throw new Error(msg);
    }
    return j.data !== undefined ? j.data : j;
  }

  function showToast(msg, type) {
    type = type || 'info';
    const icons = { info: 'ℹ', success: '✅', warn: '⚠️', error: '❌' };
    const container = document.getElementById('toast-container');
    if (!container) return;
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = '<span class="toast-icon">' + (icons[type] || 'ℹ') + '</span><span class="toast-msg"></span>';
    t.querySelector('.toast-msg').textContent = msg;
    container.appendChild(t);
    setTimeout(function () {
      t.classList.add('out');
      setTimeout(function () { t.remove(); }, 320);
    }, 3500);
  }

  function sfxOn() {
    var el = document.getElementById('sfx-toggle');
    return !!(el && el.classList.contains('on'));
  }

  function playUiClick() {
    if (!sfxOn()) return;
    try {
      if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      }
      if (audioCtx.state === 'suspended') {
        audioCtx.resume();
      }
      var o = audioCtx.createOscillator();
      var g = audioCtx.createGain();
      o.connect(g);
      g.connect(audioCtx.destination);
      o.type = 'sine';
      o.frequency.setValueAtTime(880, audioCtx.currentTime);
      o.frequency.exponentialRampToValueAtTime(440, audioCtx.currentTime + 0.08);
      g.gain.setValueAtTime(0.06, audioCtx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.1);
      o.start();
      o.stop(audioCtx.currentTime + 0.1);
    } catch (e) {}
  }

  function initUiClickSounds() {
    document.addEventListener(
      'click',
      function (e) {
        if (!sfxOn()) return;
        if (e.target.closest('#sfx-toggle, [data-sd-toggle]')) return;
        if (
          e.target.closest(
            'button,.mission-card,.mode-card,.currency-chip,.tb-icon-btn,.event-banner,.ms-option,.lb-row,.tb-avatar,.tb-logo,.lavs-knd-card,.inspect-btn,[data-open-mm]'
          )
        ) {
          playUiClick();
        }
      },
      false
    );
    document.querySelectorAll('#sfx-toggle, [data-sd-toggle]').forEach(function (t) {
      t.addEventListener('click', function () {
        t.classList.toggle('on');
        if (t.id === 'sfx-toggle' && sfxOn()) playUiClick();
      });
    });
  }

  function openOverlay(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (id === 'lb-overlay') renderLbFull();
    if (id === 'av-overlay') renderAvatarGrid();
    if (id === 'notif-overlay') renderNotifications();
    el.classList.add('open');
  }

  function closeOverlay(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  }

  function rarityColor(r) {
    const k = String(r || 'common').toLowerCase();
    return RARITY_BAR[k] || RARITY_BAR.common;
  }

  function disposeHeroHologram(wrap) {
    if (heroHologramMessageHandler) {
      window.removeEventListener('message', heroHologramMessageHandler);
      heroHologramMessageHandler = null;
    }
    if (!wrap) return;
    wrap.removeAttribute('data-hologram-token');
    const iframe = wrap.querySelector('#hero-hologram-iframe');
    if (iframe) {
      iframe.removeAttribute('src');
    }
  }

  /** Decode path once so URLSearchParams encodes spaces correctly (avoids %2520). */
  function modelPathForHologramQuery(modelUrl) {
    if (!modelUrl) return '';
    try {
      return decodeURIComponent(modelUrl);
    } catch (e) {
      return modelUrl;
    }
  }

  function buildHologramViewerIframeSrc(modelUrl) {
    const path = modelPathForHologramQuery(modelUrl);
    const u = new URL(HOLOGRAM_VIEWER_PATH, window.location.origin);
    u.searchParams.set('model', path);
    u.searchParams.set('autoload', '1');
    u.searchParams.set('embed', '1');
    const token = Math.random().toString(36).slice(2) + Date.now().toString(36);
    u.searchParams.set('reply_token', token);
    return { src: u.pathname + u.search, token: token };
  }

  function renderHero2d(wrap, imgUrl) {
    disposeHeroHologram(wrap);
    wrap.innerHTML = '';
    if (imgUrl) {
      const im = document.createElement('img');
      im.id = 'hero-avatar-img';
      im.className = 'hero-img';
      im.alt = '';
      im.src = imgUrl.indexOf('%') !== -1 || /^https?:/i.test(imgUrl) ? imgUrl : encodeURI(imgUrl).replace(/'/g, '%27');
      wrap.appendChild(im);
    } else {
      wrap.innerHTML = HERO_SIL_HTML;
    }
  }

  /** Same holographic Three.js viewer as tools/cards (iframe). */
  function mountHeroHologramIframe(wrap, modelUrl, onError) {
    disposeHeroHologram(wrap);
    wrap.innerHTML = '';
    const { src, token } = buildHologramViewerIframeSrc(modelUrl);
    wrap.setAttribute('data-hologram-token', token);

    const projector = document.createElement('div');
    projector.className = 'hero-holo-projector';
    const inner = document.createElement('div');
    inner.className = 'hero-holo-projector-inner';

    const iframe = document.createElement('iframe');
    iframe.id = 'hero-hologram-iframe';
    iframe.setAttribute('title', 'Holographic avatar');
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('allow', 'accelerometer; autoplay');
    iframe.referrerPolicy = 'same-origin';
    iframe.className = 'hero-hologram-iframe';

    heroHologramMessageHandler = function (ev) {
      if (ev.origin !== window.location.origin) return;
      const data = ev.data;
      if (!data || data.type !== 'mw-hologram') return;
      if (data.reply_token !== wrap.getAttribute('data-hologram-token')) return;
      if (data.status === 'error') {
        onError();
      }
    };
    window.addEventListener('message', heroHologramMessageHandler);

    inner.appendChild(iframe);
    projector.appendChild(inner);
    wrap.appendChild(projector);

    iframe.src = src;
  }

  function renderHeroStage(d) {
    const wrap = document.getElementById('hero-avatar-wrap');
    if (!wrap) return;
    const sel = d && d.selected_avatar;
    const imgUrl = sel ? String(sel.display_image_url || d.hero_image_url || '').trim() : '';
    const modelUrl = sel ? String(sel.display_model_url || d.hero_model_url || '').trim() : '';
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const want3d = Boolean(modelUrl && !reduceMotion);

    wrap.setAttribute('data-hero-image-url', imgUrl);
    wrap.setAttribute('data-hero-model-url', modelUrl);

    if (!sel) {
      disposeHeroHologram(wrap);
      wrap.innerHTML = HERO_SIL_HTML;
      return;
    }

    if (!want3d) {
      renderHero2d(wrap, imgUrl);
      return;
    }

    const fallback = function () {
      renderHero2d(wrap, imgUrl);
    };
    mountHeroHologramIframe(wrap, modelUrl, fallback);
  }

  function applyLobbyData(d) {
    if (!d) return;
    lobbyData = d;
    const u = d.user || {};
    const cur = d.currencies || {};
    const rank = d.ranking || {};
    const sel = d.selected_avatar;

    const un = document.getElementById('tb-username');
    if (un) un.textContent = u.username || '—';
    const tl = document.getElementById('tb-level');
    if (tl) {
      const pos = rank.estimated_position != null ? '#' + rank.estimated_position : '—';
      tl.textContent = 'LVL ' + (u.level || 1) + ' · ' + pos;
    }
    const xpFill = document.getElementById('tb-xpfill');
    if (xpFill) xpFill.style.width = Math.min(100, Math.max(0, u.xp_fill_pct || 0)) + '%';

    const cc = document.getElementById('cc-coins');
    if (cc) cc.textContent = Number(cur.knd_points_available || 0).toLocaleString();
    const cg = document.getElementById('cc-gems');
    if (cg) cg.textContent = Number(cur.fragments_total || 0).toLocaleString();

    const rwRank = document.getElementById('rw-rank');
    if (rwRank) rwRank.textContent = rank.estimated_position != null ? '#' + rank.estimated_position : '—';
    const rwVal = document.getElementById('rw-bar-val');
    if (rwVal) rwVal.textContent = Number(rank.rank_score || 0).toLocaleString() + ' pts';
    const rwBar = document.getElementById('rw-barfill');
    if (rwBar) {
      const rs = Number(rank.rank_score || 0);
      rwBar.style.width = (rs > 0 ? Math.min(100, Math.log10(rs + 1) * 25) : 0) + '%';
    }

    const heroName = document.getElementById('hero-name');
    const heroClass = document.getElementById('hero-class');
    const heroTag = document.getElementById('hero-rarity-tag');
    if (sel && heroName) {
      heroName.textContent = sel.name || 'Avatar';
      const rar = String(sel.rarity || 'common').toUpperCase();
      if (heroTag) heroTag.textContent = rar;
      const sk = d.equipped_mw_skills || {};
      const avLvlRaw = sel.avatar_level != null ? sel.avatar_level : sel.level;
      const avLvl = Math.max(1, parseInt(avLvlRaw, 10) || 1);
      const sub = 'LV ' + avLvl + ' · ' + (sk.ability_code ? String(sk.ability_code).toUpperCase() + ' · ' : '') + rar;
      if (heroClass) heroClass.textContent = sub;
    } else if (heroName) {
      heroName.textContent = '—';
      if (heroTag) heroTag.textContent = '—';
      if (heroClass) heroClass.textContent = '—';
    }
    renderHeroStage(d);

    const tbThumb = document.getElementById('tb-avatar-thumb');
    const tbRing = document.getElementById('tb-avatar-ring');
    if (tbThumb && sel) {
      const url = (sel.display_image_url || d.hero_image_url || '');
      if (url) {
        tbThumb.innerHTML = '<img src="' + encodeURI(url).replace(/'/g, '%27') + '" alt="">';
        if (tbRing) tbRing.style.display = '';
      } else {
        tbThumb.textContent = '⬡';
        if (tbRing) tbRing.style.display = 'none';
      }
    }

    renderMissions(d.missions || []);
    renderKnowledgeOrbs(sel);
    renderAvpSlots(d.avatars || [], d.selected_avatar || null);
    updateNotifBadge(d.notifications || {});

    const onlineEl = document.getElementById('mc-pvp-online');
    if (onlineEl && d.online_hint != null) {
      onlineEl.textContent = Number(d.online_hint).toLocaleString() + ' in queue / matched';
    }

    const ebTitle = document.getElementById('eb-title');
    const ebTimer = document.getElementById('eb-timer');
    const se = d.season || {};
    if (ebTitle) ebTitle.textContent = se.name || 'Mind Wars';
    if (ebTimer && se.seconds_remaining != null) {
      const s = se.seconds_remaining;
      const dd = Math.floor(s / 86400);
      const hh = Math.floor((s % 86400) / 3600);
      const mm = Math.floor((s % 3600) / 60);
      ebTimer.textContent = s > 0 ? 'Ends in ' + dd + 'd ' + hh + 'h ' + mm + 'm' : 'Season ended';
    }
  }

  function renderKnowledgeOrbs(sel) {
    const wrap = document.getElementById('energy-orbs-display');
    const lbl = document.getElementById('knowledge-next-label');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!sel) {
      if (lbl) lbl.textContent = '—';
      return;
    }
    const n = function (v) {
      const x = Number(v);
      return Number.isFinite(x) ? Math.floor(x) : NaN;
    };
    let ke = n(sel.knowledge_energy_into_level);
    if (!Number.isFinite(ke) || ke < 0) ke = 0;
    let req = n(sel.knowledge_energy_required_current);
    if (!Number.isFinite(req) || req < 1) req = 1;
    let toNext = n(sel.knowledge_energy_to_next_level);
    if (!Number.isFinite(toNext) || toNext < 0) {
      toNext = Math.max(0, req - ke);
    }
    const filled = Math.min(10, Math.round((ke / req) * 10));
    for (let i = 0; i < 10; i++) {
      const o = document.createElement('div');
      o.className = 'orb' + (i < filled ? '' : ' empty');
      wrap.appendChild(o);
    }
    if (lbl) {
      lbl.textContent = toNext > 0 ? String(toNext) + ' KE to next level' : 'MAX';
    }
  }

  function renderMissions(missions) {
    const body = document.getElementById('missions-body');
    if (!body) return;
    body.innerHTML = '';
    if (!missions.length) {
      body.innerHTML = '<div class="mc-desc" style="padding:8px">No missions today.</div>';
      return;
    }
    const colors = { daily: 'var(--accent-action)', weekly: 'var(--accent-primary)', event: 'var(--color-warning)' };
    missions.forEach(function (m) {
      const pct = m.target > 0 ? Math.round((m.progress / m.target) * 100) : 0;
      const typ = 'daily';
      const col = colors[typ] || 'var(--accent-action)';
      const el = document.createElement('div');
      el.className = 'mission-card fade-in';
      el.style.setProperty('--mc', col);
      const claimBtn = m.can_claim
        ? '<button type="button" class="mc-claim" data-mission-code="' + String(m.code || '').replace(/"/g, '') + '">⬡ CLAIM</button>'
        : '';
      el.innerHTML =
        '<div class="mc-top"><span class="mc-name"></span><span class="mc-tag daily">DAILY</span></div>' +
        '<div class="mc-desc"></div>' +
        '<div class="mc-progress"><div class="mc-prog-bar"><div class="mc-prog-fill" style="width:' + pct + '%;--mc:' + col + '"></div></div>' +
        '<div class="mc-prog-meta"><span></span><span>' + pct + '%</span></div></div>' +
        '<div class="mc-reward">💰 ' + Number(m.reward_kp || 0).toLocaleString() + ' KP</div>' + claimBtn;
      el.querySelector('.mc-name').textContent = m.title || m.code || 'Mission';
      el.querySelector('.mc-desc').textContent = m.description || '';
      el.querySelector('.mc-prog-meta span').textContent = m.progress + '/' + m.target;
      body.appendChild(el);
    });
    body.querySelectorAll('.mc-claim').forEach(function (btn) {
      btn.addEventListener('click', function () {
        claimMission(btn.getAttribute('data-mission-code'));
      });
    });
  }

  async function claimMission(code) {
    if (!code) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('code', code);
    try {
      await fetchJson('/api/mind-wars/mission_claim.php', { method: 'POST', body: fd });
      showToast('Reward claimed.', 'success');
      await refreshLobby();
    } catch (e) {
      showToast(e.message || 'Claim failed', 'error');
    }
  }

  async function renderLbMini() {
    const body = document.getElementById('lb-mini-body');
    if (!body) return;
    body.innerHTML = '<div class="mc-desc" style="padding:8px">Loading…</div>';
    try {
      const data = await fetchJson('/api/mind-wars/get_leaderboard_preview.php');
      const top = data.top || [];
      body.innerHTML = '';
      top.forEach(function (p, i) {
        if (i > 0) {
          const sep = document.createElement('div');
          sep.className = 'lb-sep';
          body.appendChild(sep);
        }
        const cls = ['r1', 'r2', 'r3', 'rn', 'rn'][i] || 'rn';
        const div = document.createElement('div');
        div.className = 'lb-row fade-in';
        const avHtml = p.avatar_url
          ? '<img src="' + p.avatar_url.replace(/"/g, '') + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">'
          : '⬡';
        div.innerHTML =
          '<span class="lb-rank ' + cls + '">' + p.position + '</span>' +
          '<div class="lb-av">' + avHtml + '</div>' +
          '<div class="lb-info"><div class="lb-name"></div><div class="lb-score"></div></div>';
        div.querySelector('.lb-name').textContent = (p.username || '') + (p.is_current_user ? ' (YOU)' : '');
        div.querySelector('.lb-score').textContent = Number(p.rank_score || 0).toLocaleString() + ' pts';
        body.appendChild(div);
      });
      if (!top.length) body.innerHTML = '<div class="mc-desc">No rankings yet.</div>';
    } catch (e) {
      body.innerHTML = '<div class="mc-desc" style="color:var(--red)">' + (e.message || 'Failed') + '</div>';
    }
  }

  async function renderLbFull() {
    const body = document.getElementById('lb-full-body');
    if (!body) return;
    body.innerHTML = 'Loading…';
    try {
      const data = await fetchJson('/api/mind-wars/leaderboard.php?limit=50');
      const top = data.top || [];
      body.innerHTML = '';
      const uid = lobbyData && lobbyData.user ? lobbyData.user.id : 0;
      top.forEach(function (p) {
        const div = document.createElement('div');
        div.className = 'lb-full-row' + (p.is_current_user ? ' me' : '');
        const pos = p.position;
        const rankCol = pos === 1 ? 'var(--gold)' : pos === 2 ? '#aaa' : pos === 3 ? '#cd7f32' : 'var(--t3)';
        const medal = pos === 1 ? '🥇' : pos === 2 ? '🥈' : pos === 3 ? '🥉' : '#' + pos;
        div.innerHTML =
          '<span class="lbf-rank" style="color:' + rankCol + '">' + medal + '</span>' +
          '<div class="lbf-av">⬡</div>' +
          '<div class="lbf-info"><div class="lbf-name"></div><div class="lbf-detail"></div></div>' +
          '<div class="lbf-score"></div>';
        div.querySelector('.lbf-name').textContent = p.username || '';
        const tot = (p.wins || 0) + (p.losses || 0);
        div.querySelector('.lbf-detail').textContent =
          'W/L ' + (p.wins || 0) + '/' + (p.losses || 0) + (tot ? ' · ' + (p.win_rate || 0) + '% WR' : '');
        div.querySelector('.lbf-score').textContent = Number(p.rank_score || 0).toLocaleString();
        body.appendChild(div);
      });
      if (!top.length) body.textContent = 'No data.';
    } catch (e) {
      body.textContent = e.message || 'Failed';
    }
  }

  function renderAvatarGrid() {
    const grid = document.getElementById('av-grid');
    if (!grid || !lobbyData) return;
    const mk = typeof createMwAvatarCard === 'function' ? createMwAvatarCard : null;
    if (!mk) {
      grid.innerHTML = '<div class="mc-desc" style="padding:16px">Avatar cards failed to load.</div>';
      return;
    }
    const list = lobbyData.avatars || [];
    grid.innerHTML = '';
    if (!list.length) {
      grid.innerHTML = '<div class="mc-desc" style="padding:16px;text-align:center">No avatars in collection.</div>';
      return;
    }
    list.forEach(function (av) {
      const st = av.mw_stats || { mnd: 0, fcs: 0, spd: 0, lck: 0 };
      const card = mk({
        item_id: av.item_id,
        name: av.name || 'Avatar',
        rarity: av.rarity || 'common',
        level: av.avatar_level || 1,
        image: av.display_image_url || '',
        stats: st
      }, { buttonLabel: av.is_favorite ? 'EQUIPPED' : 'EQUIP' });
      card.classList.add('lavs-knd-card');
      if (av.is_favorite) card.classList.add('mw-card-equipped');
      const doEquip = function () {
        if (av.is_favorite) return;
        setFavoriteAvatar(av.item_id);
      };
      card.addEventListener('click', function (e) {
        if (e.target.closest('.inspect-btn')) return;
        doEquip();
      });
      const btn = card.querySelector('.inspect-btn');
      if (btn) btn.addEventListener('click', function (e) {
        e.stopPropagation();
        doEquip();
      });
      grid.appendChild(card);
    });
  }

  async function setFavoriteAvatar(itemId) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('item_id', String(itemId));
    try {
      const r = await fetch('/api/avatar/set_favorite.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || 'Failed');
      showToast('Avatar equipped.', 'success');
      await refreshLobby();
      closeOverlay('av-overlay');
    } catch (e) {
      showToast(e.message || 'Could not change avatar', 'error');
    }
  }

  function renderAvpSlots(avatars, selected) {
    const row = document.getElementById('avp-slots-row');
    if (!row) return;
    row.innerHTML = '';
    const mk = typeof createMwAvatarCard === 'function' ? createMwAvatarCard : null;
    const scroll = document.createElement('div');
    scroll.className = 'avp-slots-scroll avp-slots-scroll--single';

    var av = null;
    if (selected && selected.item_id != null) {
      var sid = Number(selected.item_id);
      av = avatars.find(function (a) { return a && Number(a.item_id) === sid; }) || selected;
    } else if (selected) {
      av = selected;
    } else {
      av = avatars.find(function (a) { return a && a.is_favorite; }) || avatars[0] || null;
    }

    if (mk && av) {
      const st = av.mw_stats || { mnd: 0, fcs: 0, spd: 0, lck: 0 };
      const wrap = document.createElement('div');
      wrap.className = 'avp-slot-card-wrap';
      const equipped = !!av.is_favorite;
      const card = mk({
        item_id: av.item_id,
        name: av.name || 'Avatar',
        rarity: av.rarity || 'common',
        level: av.avatar_level || 1,
        image: av.display_image_url || '',
        stats: st
      }, {
        compact: true,
        buttonLabel: equipped ? 'EQUIPPED' : 'EQUIP'
      });
      if (equipped) card.classList.add('mw-card-equipped');
      const equip = function () {
        if (equipped) return;
        setFavoriteAvatar(av.item_id);
      };
      card.addEventListener('click', function (e) {
        if (e.target.closest('.inspect-btn')) return;
        equip();
      });
      const btn = card.querySelector('.inspect-btn');
      if (btn) btn.addEventListener('click', function (e) {
        e.stopPropagation();
        equip();
      });
      wrap.appendChild(card);
      scroll.appendChild(wrap);
    } else {
      const fallback = document.createElement('div');
      fallback.className = 'avp-slot' + (av && av.is_favorite ? ' active' : '');
      fallback.innerHTML = '⬡';
      scroll.appendChild(fallback);
    }
    row.appendChild(scroll);
  }

  function renderNotifications() {
    const body = document.getElementById('notif-body');
    if (!body || !lobbyData) return;
    const n = lobbyData.notifications || {};
    const items = n.items || [];
    body.innerHTML = '';
    if (!items.length) {
      body.innerHTML = '<div class="mc-desc" style="padding:12px">No notifications.</div>';
      return;
    }
    items.forEach(function (it) {
      const div = document.createElement('div');
      div.style.cssText =
        'display:flex;align-items:flex-start;gap:12px;padding:12px;border-radius:3px;margin-bottom:8px;background:' +
        (it.unread ? 'rgba(0,232,255,.04)' : 'rgba(255,255,255,.02)') +
        ';border:1px solid ' + (it.unread ? 'rgba(0,232,255,.12)' : 'rgba(255,255,255,.05)');
      div.innerHTML =
        '<span style="font-size:23px;flex-shrink:0"></span><div style="flex:1;min-width:0">' +
        '<div style="font-family:var(--FD);font-size:11.5px;font-weight:700;color:var(--t1);margin-bottom:2px"></div>' +
        '<div style="font-family:var(--FB);font-size:13.8px;color:var(--t2);line-height:1.4"></div></div>';
      div.querySelector('span').textContent = it.icon || '•';
      div.querySelector('div div').textContent = it.title || '';
      div.querySelector('div div + div').textContent = it.message || '';
      body.appendChild(div);
    });
  }

  function updateNotifBadge(n) {
    const badge = document.getElementById('notif-badge');
    if (!badge) return;
    const c = Number(n.unread_count || 0);
    if (c > 0) {
      badge.textContent = c > 9 ? '9+' : String(c);
      badge.classList.remove('hidden');
    } else {
      badge.classList.add('hidden');
    }
  }

  async function refreshLobby() {
    const d = await fetchJson('/api/mind-wars/get_lobby_data.php');
    applyLobbyData(d);
    renderLbMini();
  }

  function getMmSquadStepEl() {
    return document.getElementById('mm-pve-3v3-setup-step');
  }

  function revealMmSquadStep(el) {
    if (!el) return;
    el.classList.remove('hidden');
    el.removeAttribute('hidden');
    el.hidden = false;
  }

  function concealMmSquadStep(el) {
    if (!el) return;
    el.classList.add('hidden');
    el.hidden = true;
  }

  function closeMmPveTeamPicker() {
    const sel = document.getElementById('mm-pve-team-selector');
    if (sel) {
      sel.classList.remove('show');
      sel.setAttribute('aria-hidden', 'true');
    }
    mmPve3v3PickIndex = null;
  }

  function resetMmPve3v3SlotsState() {
    mmPve3v3Slots = [null, null, null];
    mmPve3v3PickIndex = null;
  }

  function findLobbyAvatarByItemId(itemId) {
    const n = Number(itemId);
    const list = (lobbyData && lobbyData.avatars) ? lobbyData.avatars : [];
    for (let i = 0; i < list.length; i++) {
      const a = list[i];
      if (a && Number(a.item_id) === n) return a;
    }
    return null;
  }

  function snapshotLobbyAv(row) {
    if (!row) return null;
    return {
      item_id: row.item_id,
      name: row.name,
      rarity: row.rarity,
      avatar_level: row.avatar_level,
      display_image_url: row.display_image_url,
      mw_stats: row.mw_stats || { mnd: 0, fcs: 0, spd: 0, lck: 0 }
    };
  }

  function slotRowToCardPayload(row) {
    return {
      item_id: row.item_id,
      name: row.name || 'Avatar',
      rarity: row.rarity || 'common',
      level: row.avatar_level || 1,
      image: row.display_image_url || '',
      stats: row.mw_stats || { mnd: 0, fcs: 0, spd: 0, lck: 0 }
    };
  }

  function mmPve3v3SquadComplete() {
    return !!(mmPve3v3Slots[0] && mmPve3v3Slots[1] && mmPve3v3Slots[2]);
  }

  function updateMmEnterButtonDisabled() {
    const btn = document.getElementById('mm-enter-btn');
    if (!btn) return;
    if (mmUiStep === 'pve-3v3-setup') {
      btn.disabled = !mmPve3v3SquadComplete();
    } else {
      btn.disabled = false;
    }
  }

  function renderMmPve3v3Slots() {
    const wrap = document.getElementById('mm-pve-3v3-slots');
    if (!wrap) return;
    const mk = typeof createMwAvatarCard === 'function' ? createMwAvatarCard : null;
    wrap.innerHTML = '';
    if (!mk) {
      wrap.innerHTML = '<div class="mc-desc" style="padding:12px;text-align:center">Cards unavailable.</div>';
      updateMmEnterButtonDisabled();
      return;
    }
    for (let i = 0; i < 3; i++) {
      const row = mmPve3v3Slots[i];
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'mm-pve-slot-btn';
      b.setAttribute('data-slot-index', String(i));
      const card = row
        ? mk(slotRowToCardPayload(row), { compact: true, buttonLabel: 'CHANGE' })
        : mk({}, { placeholder: true, slotIndex: i, compact: true, buttonLabel: 'PICK' });
      b.appendChild(card);
      b.addEventListener('click', function () {
        openMmPveTeamPicker(i);
      });
      wrap.appendChild(b);
    }
    updateMmEnterButtonDisabled();
  }

  function openMmPveTeamPicker(slotIndex) {
    const overlay = document.getElementById('mm-pve-team-selector');
    const grid = document.getElementById('mm-pve-team-grid');
    const titleEl = document.getElementById('mm-pve-team-title');
    if (!overlay || !grid) return;
    mmPve3v3PickIndex = slotIndex;
    if (titleEl) titleEl.textContent = 'SELECT — SLOT ' + (slotIndex + 1);
    grid.innerHTML = '';
    const mk = typeof createMwAvatarCard === 'function' ? createMwAvatarCard : null;
    const list = (lobbyData && lobbyData.avatars) ? lobbyData.avatars : [];
    const blocked = {};
    for (let j = 0; j < 3; j++) {
      if (j === slotIndex) continue;
      const s = mmPve3v3Slots[j];
      if (s && s.item_id) blocked[Number(s.item_id)] = true;
    }
    if (!mk || !list.length) {
      const empty = document.createElement('div');
      empty.className = 'lavs-empty';
      empty.textContent = list.length ? 'No cards.' : 'No avatars in collection.';
      grid.appendChild(empty);
    } else {
      var added = 0;
      list.forEach(function (av) {
        const id = Number(av.item_id);
        if (!id || blocked[id]) return;
        const card = mk(slotRowToCardPayload(av), { buttonLabel: 'SELECT' });
        card.classList.add('lavs-knd-card');
        card.setAttribute('role', 'button');
        card.setAttribute('tabindex', '0');
        const pick = function () {
          mmPve3v3Slots[slotIndex] = snapshotLobbyAv(av);
          closeMmPveTeamPicker();
          renderMmPve3v3Slots();
        };
        card.addEventListener('click', function (e) {
          if (e.target.closest('.inspect-btn')) return;
          pick();
        });
        const btn = card.querySelector('.inspect-btn');
        if (btn) btn.addEventListener('click', function (e) {
          e.stopPropagation();
          pick();
        });
        card.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            pick();
          }
        });
        grid.appendChild(card);
        added++;
      });
      if (added === 0) {
        const empty = document.createElement('div');
        empty.className = 'lavs-empty';
        empty.textContent = 'No avatars left for this slot (others already in your squad).';
        grid.appendChild(empty);
      }
    }
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
  }

  function showPve3v3SetupStep() {
    closeMmPveTeamPicker();
    const squadStep = getMmSquadStepEl();
    if (!squadStep) {
      console.error('Mind Wars lobby: #mm-pve-3v3-setup-step missing from DOM');
      showToast('Squad UI unavailable. Refresh the page.', 'error');
      return;
    }
    mmUiStep = 'pve-3v3-setup';
    const modeStep = document.getElementById('mm-mode-step');
    const pveStep = document.getElementById('mm-pve-format-step');
    if (modeStep) modeStep.classList.add('hidden');
    if (pveStep) pveStep.classList.add('hidden');
    revealMmSquadStep(squadStep);
    resetMmPve3v3SlotsState();
    const fav = lobbyData && lobbyData.selected_avatar;
    if (fav && fav.item_id) {
      const row = findLobbyAvatarByItemId(fav.item_id);
      if (row) mmPve3v3Slots[0] = snapshotLobbyAv(row);
    }
    const btn = document.getElementById('mm-enter-btn');
    if (btn) btn.textContent = MM_BTN_START;
    const title = document.getElementById('mm-title');
    if (title) title.textContent = MM_TITLE_PVE_3V3;
    renderMmPve3v3Slots();
  }

  function resetMmUi() {
    mmUiStep = 'mode';
    selectedPveFormat = '1v1';
    closeMmPveTeamPicker();
    resetMmPve3v3SlotsState();
    const modeStep = document.getElementById('mm-mode-step');
    const pveStep = document.getElementById('mm-pve-format-step');
    const squadStep = getMmSquadStepEl();
    if (modeStep) modeStep.classList.remove('hidden');
    if (pveStep) pveStep.classList.add('hidden');
    concealMmSquadStep(squadStep);
    const btn = document.getElementById('mm-enter-btn');
    if (btn) {
      btn.textContent = MM_BTN_ENTER;
      btn.disabled = false;
    }
    const title = document.getElementById('mm-title');
    if (title) title.textContent = MM_TITLE_MODE;
  }

  function showPveFormatStep() {
    mmUiStep = 'pve-format';
    closeMmPveTeamPicker();
    concealMmSquadStep(getMmSquadStepEl());
    const modeStep = document.getElementById('mm-mode-step');
    const pveStep = document.getElementById('mm-pve-format-step');
    if (modeStep) modeStep.classList.add('hidden');
    if (pveStep) pveStep.classList.remove('hidden');
    document.querySelectorAll('#mm-pve-format-step .ms-option').forEach(function (o) { o.classList.remove('selected'); });
    const one = document.querySelector('#mm-pve-format-step .ms-option[data-pve-format="1v1"]');
    if (one) one.classList.add('selected');
    selectedPveFormat = '1v1';
    const btn = document.getElementById('mm-enter-btn');
    if (btn) {
      btn.textContent = MM_BTN_START;
      btn.disabled = false;
    }
    const title = document.getElementById('mm-title');
    if (title) title.textContent = MM_TITLE_PVE;
  }

  function showMmModeStep() {
    mmUiStep = 'mode';
    closeMmPveTeamPicker();
    resetMmPve3v3SlotsState();
    concealMmSquadStep(getMmSquadStepEl());
    const modeStep = document.getElementById('mm-mode-step');
    const pveStep = document.getElementById('mm-pve-format-step');
    if (pveStep) pveStep.classList.add('hidden');
    if (modeStep) modeStep.classList.remove('hidden');
    const btn = document.getElementById('mm-enter-btn');
    if (btn) {
      btn.textContent = MM_BTN_ENTER;
      btn.disabled = false;
    }
    const title = document.getElementById('mm-title');
    if (title) title.textContent = MM_TITLE_MODE;
  }

  function openMM() {
    document.getElementById('mm-modal').classList.add('open');
    document.getElementById('mm-idle-state').classList.remove('hidden');
    document.getElementById('mm-searching-state').classList.add('hidden');
    resetMmUi();
  }

  function closeMM() {
    cancelSearch();
    resetMmUi();
    document.getElementById('mm-modal').classList.remove('open');
  }

  function selectModeEl(el) {
    document.querySelectorAll('#mm-mode-step .ms-option').forEach(function (o) { o.classList.remove('selected'); });
    el.classList.add('selected');
    selectedMode = el.getAttribute('data-mode') || 'pvp';
  }

  function selectPveFormatEl(el) {
    document.querySelectorAll('#mm-pve-format-step .ms-option').forEach(function (o) { o.classList.remove('selected'); });
    el.classList.add('selected');
    selectedPveFormat = el.getAttribute('data-pve-format') || '1v1';
  }

  function cancelSearch() {
    if (searchInterval) clearInterval(searchInterval);
    searchInterval = null;
    searchSecs = 0;
    if (queuePollTimer) clearInterval(queuePollTimer);
    queuePollTimer = null;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fetch('/api/mind-wars/queue_dequeue.php', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
    const idle = document.getElementById('mm-idle-state');
    const search = document.getElementById('mm-searching-state');
    if (idle) idle.classList.remove('hidden');
    if (search) search.classList.add('hidden');
  }

  async function onMmPrimaryClick() {
    const sel = lobbyData && lobbyData.selected_avatar;
    if (!sel || !sel.item_id) {
      showToast('Select an avatar first.', 'warn');
      return;
    }
    if (mmUiStep === 'pve-3v3-setup') {
      if (!mmPve3v3SquadComplete()) {
        showToast('Choose three different avatars for your squad.', 'warn');
        return;
      }
      const ids = mmPve3v3Slots.map(function (s) { return Number(s.item_id); });
      await startPveBattleWithFormat('3v3', ids);
      return;
    }
    if (mmUiStep === 'pve-format') {
      if (selectedPveFormat === '3v3') {
        const list = (lobbyData && lobbyData.avatars) ? lobbyData.avatars : [];
        if (list.length < 3) {
          showToast('You need at least 3 avatars in your collection for 3v3.', 'warn');
          return;
        }
        showPve3v3SetupStep();
        return;
      }
      await startPveBattleWithFormat('1v1');
      return;
    }
    if (selectedMode === 'pve') {
      showPveFormatStep();
      return;
    }
    await startPvpOrRankedSearch();
  }

  async function startPveBattleWithFormat(format, teamItemIds) {
    const sel = lobbyData && lobbyData.selected_avatar;
    if (!sel || !sel.item_id) {
      showToast('Select an avatar first.', 'warn');
      return;
    }
    if (format === '3v3') {
      if (!Array.isArray(teamItemIds) || teamItemIds.length < 3) {
        showToast('Invalid squad.', 'warn');
        return;
      }
    }
    document.getElementById('mm-idle-state').classList.add('hidden');
    document.getElementById('mm-searching-state').classList.remove('hidden');
    searchSecs = 0;
    searchInterval = setInterval(function () {
      searchSecs++;
      var mm = Math.floor(searchSecs / 60);
      var ss = searchSecs % 60;
      document.getElementById('search-timer').textContent =
        String(mm).padStart(2, '0') + ':' + String(ss).padStart(2, '0');
    }, 1000);
    try {
      const fdPve = new FormData();
      fdPve.append('csrf_token', CSRF);
      fdPve.append('mode', 'pve');
      fdPve.append('difficulty', 'normal');
      fdPve.append('format', format);
      if (format === '3v3' && Array.isArray(teamItemIds)) {
        teamItemIds.slice(0, 3).forEach(function (id) {
          fdPve.append('avatar_item_ids[]', String(id));
        });
        fdPve.append('avatar_item_id', String(teamItemIds[0]));
      } else {
        fdPve.append('avatar_item_id', String(sel.item_id));
      }
      const res = await fetch('/api/mind-wars/start_matchmaking.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fdPve
      });
      const text = await res.text();
      const j = JSON.parse(text);
      if (!j.ok) throw new Error((j.error && j.error.message) || 'Failed');
      const tok = j.data && j.data.battle_token;
      if (tok) {
        clearInterval(searchInterval);
        searchInterval = null;
        window.location.href = '/games/mind-wars/mind-wars-arena.php?battle_token=' + encodeURIComponent(tok);
        return;
      }
      throw new Error('No battle token');
    } catch (e) {
      clearInterval(searchInterval);
      searchInterval = null;
      showToast(e.message || 'PvE start failed', 'error');
      cancelSearch();
      openMM();
    }
  }

  async function startPvpOrRankedSearch() {
    const sel = lobbyData && lobbyData.selected_avatar;
    if (!sel || !sel.item_id) {
      showToast('Select an avatar first.', 'warn');
      return;
    }
    document.getElementById('mm-idle-state').classList.add('hidden');
    document.getElementById('mm-searching-state').classList.remove('hidden');
    searchSecs = 0;

    searchInterval = setInterval(function () {
      searchSecs++;
      var mm = Math.floor(searchSecs / 60);
      var ss = searchSecs % 60;
      document.getElementById('search-timer').textContent =
        String(mm).padStart(2, '0') + ':' + String(ss).padStart(2, '0');
    }, 1000);

    try {
      const fdQ = new FormData();
      fdQ.append('csrf_token', CSRF);
      fdQ.append('mode', selectedMode === 'ranked' ? 'ranked' : 'pvp');
      fdQ.append('avatar_item_id', String(sel.item_id));
      const res = await fetch('/api/mind-wars/start_matchmaking.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fdQ
      });
      const text = await res.text();
      const j = JSON.parse(text);
      if (!j.ok) throw new Error((j.error && j.error.message) || 'Queue failed');
      const st = j.data && j.data.status;
      if (st === 'matched' && j.data.match) {
        await joinMatchedPvp();
        return;
      }
      queuePollTimer = setInterval(pollQueueStatus, 2000);
    } catch (e) {
      showToast(e.message || 'Matchmaking failed', 'error');
      cancelSearch();
      openMM();
    }
  }

  async function pollQueueStatus() {
    try {
      const d = await fetchJson('/api/mind-wars/queue_status.php');
      if (d.status === 'matched') {
        if (queuePollTimer) clearInterval(queuePollTimer);
        queuePollTimer = null;
        await joinMatchedPvp();
      }
    } catch (e) {
      /* keep polling */
    }
  }

  async function joinMatchedPvp() {
    try {
      const fd = new FormData();
      fd.append('csrf_token', CSRF);
      const r = await fetch('/api/mind-wars/pvp_join_matched.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      const text = await r.text();
      const j = JSON.parse(text);
      if (!j.ok) throw new Error((j.error && j.error.message) || 'Join failed');
      const tok = j.data && j.data.battle_token;
      if (tok) {
        clearInterval(searchInterval);
        searchInterval = null;
        window.location.href = '/games/mind-wars/mind-wars-arena.php?battle_token=' + encodeURIComponent(tok);
        return;
      }
      throw new Error('No battle');
    } catch (e) {
      showToast(e.message || 'Could not join PvP', 'error');
      cancelSearch();
      openMM();
    }
  }

  /** MW3000: perspective grid, rising particles, hero float + parallax (no overlap with game logic). */
  var MW3000 = {
    _heroRaf: null,
    _mouse: { x: 0, y: 0 },
    particles: [],
    initBackground: function () {
      var grid = document.getElementById('grid-canvas');
      var pCan = document.getElementById('particle-canvas');
      if (grid && grid.getContext) {
        this._initGrid(grid);
      }
      if (pCan && pCan.getContext) {
        this._initParticles(pCan);
      }
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (!reduce) {
        this._initHeroMotion();
      }
    },
    _initGrid: function (canvas) {
      var ctx = canvas.getContext('2d');
      var self = this;
      function draw() {
        var w = canvas.width;
        var h = canvas.height;
        ctx.clearRect(0, 0, w, h);
        var gridSize = 50;
        var vanishY = h * 0.55;
        var cx = w / 2;
        ctx.strokeStyle = 'rgba(0, 232, 255, 0.12)';
        ctx.lineWidth = 0.5;
        var i;
        for (i = 0; i <= 18; i++) {
          var t = i / 18;
          var y = vanishY + (h - vanishY) * (t * t);
          var spread = 0.3 + t * 0.7;
          ctx.beginPath();
          ctx.moveTo(cx - w * spread, y);
          ctx.lineTo(cx + w * spread, y);
          ctx.globalAlpha = t * 0.6;
          ctx.stroke();
        }
        ctx.globalAlpha = 1;
        for (i = -12; i <= 12; i++) {
          var xBase = cx + i * gridSize;
          ctx.beginPath();
          ctx.moveTo(xBase, h);
          ctx.lineTo(cx + i * (gridSize * 0.05), vanishY);
          ctx.globalAlpha = (1 - Math.abs(i) / 13) * 0.25;
          ctx.stroke();
        }
        ctx.globalAlpha = 0.06;
        ctx.strokeStyle = 'rgba(155, 48, 255, 0.5)';
        var topGrid = 40;
        for (var x = 0; x < w; x += topGrid) {
          ctx.beginPath();
          ctx.moveTo(x, 0);
          ctx.lineTo(x, vanishY);
          ctx.stroke();
        }
        for (var yy = 0; yy < vanishY; yy += topGrid) {
          ctx.beginPath();
          ctx.moveTo(0, yy);
          ctx.lineTo(w, yy);
          ctx.stroke();
        }
        ctx.globalAlpha = 1;
      }
      function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        draw();
      }
      window.addEventListener('resize', resize);
      resize();
    },
    _spawnParticle: function (canvas) {
      var colors = ['rgba(34,211,238', 'rgba(214,207,199', 'rgba(234,179,8'];
      var c = colors[Math.floor(Math.random() * colors.length)];
      return {
        x: Math.random() * canvas.width,
        y: canvas.height * 0.5 + Math.random() * canvas.height * 0.5,
        vx: (Math.random() - 0.5) * 0.4,
        vy: -Math.random() * 0.8 - 0.2,
        r: Math.random() * 2.5 + 1,
        alpha: Math.random() * 0.5 + 0.2,
        color: c,
        life: 0,
        maxLife: 200 + Math.random() * 300
      };
    },
    _initParticles: function (canvas) {
      var ctx = canvas.getContext('2d');
      var self = this;
      var parts = [];
      var i;
      for (i = 0; i < 30; i++) {
        parts.push(self._spawnParticle(canvas));
      }
      self.particles = parts;
      function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
      }
      function frame() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        parts = parts.filter(function (p) { return p.life < p.maxLife; });
        while (parts.length < 30) {
          parts.push(self._spawnParticle(canvas));
        }
        self.particles = parts;
        parts.forEach(function (p) {
          p.x += p.vx;
          p.y += p.vy;
          p.life++;
          var t = p.life / p.maxLife;
          var a = p.alpha * (t < 0.1 ? t / 0.1 : t > 0.8 ? (1 - t) / 0.2 : 1);
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
          ctx.fillStyle = p.color + ',' + a + ')';
          ctx.fill();
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r * 2, 0, Math.PI * 2);
          ctx.fillStyle = p.color + ',' + a * 0.15 + ')';
          ctx.fill();
        });
        requestAnimationFrame(frame);
      }
      window.addEventListener('resize', resize);
      resize();
      frame();
    },
    _initHeroMotion: function () {
      var wrap = document.getElementById('hero-avatar-wrap');
      if (!wrap) return;
      document.addEventListener('mousemove', function (e) {
        MW3000._mouse.x = e.clientX;
        MW3000._mouse.y = e.clientY;
      });
      function tick() {
        var w = window.innerWidth;
        var h = window.innerHeight;
        var cx = w / 2;
        var cy = h / 2;
        var dx = (MW3000._mouse.x - cx) / Math.max(cx, 1);
        var dy = (MW3000._mouse.y - cy) / Math.max(cy, 1);
        var t = Date.now() / 1000;
        var floatY = Math.sin(t) * -10;
        wrap.style.transform =
          'translate(' + (dx * 6) + 'px,' + (floatY + dy * 4) + 'px)';
        MW3000._heroRaf = requestAnimationFrame(tick);
      }
      MW3000._heroRaf = requestAnimationFrame(tick);
    }
  };

  function initStars() {
    const c = document.getElementById('star-canvas');
    if (!c || !c.getContext) return;
    const ctx = c.getContext('2d');
    function resize() {
      c.width = window.innerWidth;
      c.height = window.innerHeight;
    }
    resize();
    const stars = Array.from({ length: 120 }, function () {
      return {
        x: Math.random() * c.width,
        y: Math.random() * c.height,
        r: Math.random() * 1.5,
        speed: 0.002 + Math.random() * 0.008,
        col: Math.random() > 0.7 ? 'rgba(214,207,199,' : 'rgba(34,211,238,'
      };
    });
    function draw() {
      ctx.clearRect(0, 0, c.width, c.height);
      const t = Date.now();
      stars.forEach(function (s) {
        var a = Math.sin(t * s.speed) * 0.5 + 0.5;
        ctx.beginPath();
        ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
        ctx.fillStyle = s.col + a + ')';
        ctx.fill();
      });
      requestAnimationFrame(draw);
    }
    draw();
    window.addEventListener('resize', resize);
  }

  function initLivePreview() {
    const container = document.getElementById('live-preview');
    if (!container) return;
    var colors = ['rgba(34,211,238,', 'rgba(214,207,199,', 'rgba(234,179,8,'];
    setInterval(function () {
      if (document.hidden) return;
      var p = document.createElement('div');
      p.className = 'lp-particle';
      var size = 2 + Math.random() * 4;
      var col = colors[Math.floor(Math.random() * colors.length)];
      var opacity = 0.3 + Math.random() * 0.5;
      p.style.cssText =
        'width:' + size + 'px;height:' + size + 'px;left:' + (20 + Math.random() * 60) + '%;bottom:' + (10 + Math.random() * 20) +
        '%;background:' + col + opacity + ');box-shadow:0 0 ' + (size * 3) + 'px ' + col + '0.6);--drift:' +
        ((Math.random() - 0.5) * 60) + 'px;animation-duration:' + (2 + Math.random() * 3) + 's;animation-delay:' + Math.random() * 0.5 + 's';
      container.appendChild(p);
      setTimeout(function () { p.remove(); }, 5000);
    }, 300);
  }

  function finishLoading() {
    var ls = document.getElementById('loading-screen');
    var fill = document.getElementById('ls-fill');
    var msg = document.getElementById('ls-msg');
    if (fill) fill.style.width = '100%';
    if (msg) msg.textContent = 'READY.';
    setTimeout(function () {
      if (ls) ls.classList.add('hide');
      setTimeout(function () { if (ls) ls.remove(); }, 600);
      if (document.getElementById('grid-canvas') && document.getElementById('particle-canvas')) {
        MW3000.initBackground();
      } else {
        initStars();
      }
      initLivePreview();
      var st = 0;
      setInterval(function () {
        var el = document.getElementById('battle-subtext');
        if (!el) return;
        el.style.opacity = '0';
        setTimeout(function () {
          el.textContent = BATTLE_SUBTEXTS[st++ % BATTLE_SUBTEXTS.length];
          el.style.opacity = '1';
          el.style.transition = 'opacity .4s';
        }, 300);
      }, 3500);
    }, 400);
  }

  document.addEventListener('DOMContentLoaded', function () {
    applyLobbyData(lobbyData);
    Promise.all([
      fetchJson('/api/mind-wars/get_lobby_data.php').then(function (d) {
        applyLobbyData(d);
      }).catch(function () {}),
      renderLbMini()
    ]).finally(finishLoading);

    var battleOpenMm = document.getElementById('battle-open-mm');
    if (battleOpenMm) battleOpenMm.addEventListener('click', openMM);
    document.getElementById('mm-close').addEventListener('click', closeMM);
    document.getElementById('mm-enter-btn').addEventListener('click', onMmPrimaryClick);
    document.getElementById('mm-cancel-btn').addEventListener('click', cancelSearch);
    var mmPveBack = document.getElementById('mm-pve-format-back');
    if (mmPveBack) mmPveBack.addEventListener('click', showMmModeStep);
    var mmPve3Back = document.getElementById('mm-pve-3v3-setup-back');
    if (mmPve3Back) mmPve3Back.addEventListener('click', showPveFormatStep);
    var mmPveTeamClose = document.getElementById('mm-pve-team-close');
    if (mmPveTeamClose) mmPveTeamClose.addEventListener('click', closeMmPveTeamPicker);
    var mmPveTeamSel = document.getElementById('mm-pve-team-selector');
    if (mmPveTeamSel) {
      mmPveTeamSel.addEventListener('click', function (e) {
        if (e.target === mmPveTeamSel) closeMmPveTeamPicker();
      });
    }
    document.querySelectorAll('#mm-mode-step .ms-option').forEach(function (o) {
      o.addEventListener('click', function () { selectModeEl(o); });
    });
    document.querySelectorAll('#mm-pve-format-step .ms-option').forEach(function (o) {
      o.addEventListener('click', function () { selectPveFormatEl(o); });
    });
    document.getElementById('mm-modal').addEventListener('click', function (e) {
      if (e.target.id === 'mm-modal') closeMM();
    });

    document.querySelectorAll('[data-open-mm]').forEach(function (card) {
      card.addEventListener('click', function () {
        var m = card.getAttribute('data-open-mm');
        document.querySelectorAll('#mm-mode-step .ms-option').forEach(function (o) {
          if (o.getAttribute('data-mode') === m) selectModeEl(o);
        });
        openMM();
      });
    });

    document.getElementById('tb-avatar-btn').addEventListener('click', function () { openOverlay('av-overlay'); });
    var qaChange = document.getElementById('qa-change-avatar');
    if (qaChange) qaChange.addEventListener('click', function () {
      window.location.assign('/tools/cards/index.html');
    });
    document.getElementById('av-panel-change').addEventListener('click', function () { openOverlay('av-overlay'); });
    document.getElementById('avp-inspect').addEventListener('click', function () { openOverlay('av-overlay'); });
    document.getElementById('notif-btn').addEventListener('click', function () { openOverlay('notif-overlay'); });
    document.getElementById('settings-btn').addEventListener('click', function () {
      document.getElementById('settings-drawer').classList.add('open');
    });
    document.getElementById('settings-close').addEventListener('click', function () {
      document.getElementById('settings-drawer').classList.remove('open');
    });
    var qaInv = document.getElementById('qa-inventory');
    if (qaInv) {
      qaInv.addEventListener('click', function () {
        showToast('Inventory coming soon', 'info');
      });
    }
    var qaNl = document.getElementById('qa-neural-link');
    if (qaNl) {
      qaNl.addEventListener('click', function () {
        window.location.assign('/games/knd-neural-link/drops.php');
      });
    }
    document.getElementById('lb-mini-viewall').addEventListener('click', function () { openOverlay('lb-overlay'); });
    document.getElementById('missions-all-btn').addEventListener('click', function () {
      showToast('Full mission list coming soon', 'info');
    });
    document.getElementById('events-all-btn').addEventListener('click', function () {
      showToast('Events hub coming soon', 'info');
    });

    document.querySelectorAll('[data-close-overlay]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        closeOverlay(btn.getAttribute('data-close-overlay'));
      });
    });
    document.querySelectorAll('.overlay-panel').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (e.target === el) closeOverlay(el.id);
      });
    });

    initUiClickSounds();

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        var pts = document.getElementById('mm-pve-team-selector');
        if (pts && pts.classList.contains('show')) {
          e.preventDefault();
          closeMmPveTeamPicker();
          return;
        }
        document.querySelectorAll('.overlay-panel.open').forEach(function (el) { el.classList.remove('open'); });
        closeMM();
        var sd = document.getElementById('settings-drawer');
        if (sd) sd.classList.remove('open');
      }
    });
  });
})();
