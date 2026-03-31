/* global MW_LOBBY_CSRF, MW_LOBBY_INITIAL — Knowledge Duel page: lobby chrome without lobby.js */
(function () {
  'use strict';

  var CSRF = typeof MW_LOBBY_CSRF !== 'undefined' ? MW_LOBBY_CSRF : '';
  var lobbyData = typeof MW_LOBBY_INITIAL !== 'undefined' && MW_LOBBY_INITIAL ? MW_LOBBY_INITIAL : null;

  function fetchJson(url, options) {
    return fetch(url, Object.assign({ credentials: 'same-origin' }, options || {})).then(function (r) {
      return r.text();
    }).then(function (text) {
      var j = JSON.parse(text);
      if (!j.ok) {
        throw new Error((j.error && j.error.message) || j.error || 'Request failed');
      }
      return j.data !== undefined ? j.data : j;
    });
  }

  function showToast(msg, type) {
    type = type || 'info';
    var icons = { info: 'ℹ', success: '✅', warn: '⚠️', error: '❌' };
    var container = document.getElementById('toast-container');
    if (!container) return;
    var t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = '<span class="toast-icon">' + (icons[type] || 'ℹ') + '</span><span class="toast-msg"></span>';
    t.querySelector('.toast-msg').textContent = msg;
    container.appendChild(t);
    setTimeout(function () {
      t.classList.add('out');
      setTimeout(function () { t.remove(); }, 320);
    }, 3500);
  }

  function applyLobbyData(d) {
    if (!d) return;
    lobbyData = d;
    var u = d.user || {};
    var cur = d.currencies || {};
    var rank = d.ranking || {};
    var sel = d.selected_avatar;

    var un = document.getElementById('tb-username');
    if (un) un.textContent = u.username || '—';
    var tl = document.getElementById('tb-level');
    if (tl) {
      var pos = rank.estimated_position != null ? '#' + rank.estimated_position : '—';
      tl.textContent = 'LVL ' + (u.level || 1) + ' · ' + pos;
    }
    var xpFill = document.getElementById('tb-xpfill');
    if (xpFill) xpFill.style.width = Math.max(0, Math.min(100, parseInt(u.xp_fill_pct, 10) || 0)) + '%';

    var cc = document.getElementById('cc-coins');
    if (cc) cc.textContent = Number(cur.knd_points_available || 0).toLocaleString();
    var cg = document.getElementById('cc-gems');
    if (cg) cg.textContent = Number(cur.fragments_total || 0).toLocaleString();

    var rwRank = document.getElementById('rw-rank');
    if (rwRank) rwRank.textContent = rank.estimated_position != null ? '#' + rank.estimated_position : '—';
    var rwVal = document.getElementById('rw-bar-val');
    if (rwVal) rwVal.textContent = Number(rank.rank_score || 0).toLocaleString() + ' pts';
    var rwBar = document.getElementById('rw-barfill');
    if (rwBar) {
      var rs = Number(rank.rank_score || 0);
      rwBar.style.width = (rs > 0 ? Math.min(100, Math.log10(rs + 1) * 25) : 0) + '%';
    }

    var tbThumb = document.getElementById('tb-avatar-thumb');
    var tbRing = document.getElementById('tb-avatar-ring');
    if (tbThumb && sel) {
      var url = sel.display_image_url || d.hero_image_url || '';
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

    var onlineEl = document.getElementById('mc-pvp-online');
    if (onlineEl && d.online_hint != null) {
      onlineEl.textContent = Number(d.online_hint).toLocaleString() + ' in queue / matched';
    }

    var ebTitle = document.getElementById('eb-title');
    var ebTimer = document.getElementById('eb-timer');
    var se = d.season || {};
    if (ebTitle) ebTitle.textContent = se.name || 'Mind Wars';
    if (ebTimer && se.seconds_remaining != null) {
      var s = se.seconds_remaining;
      var dd = Math.floor(s / 86400);
      var hh = Math.floor((s % 86400) / 3600);
      var mm = Math.floor((s % 3600) / 60);
      ebTimer.textContent = s > 0 ? 'Ends in ' + dd + 'd ' + hh + 'h ' + mm + 'm' : 'Season ended';
    }
  }

  function renderKnowledgeOrbs(sel) {
    var wrap = document.getElementById('energy-orbs-display');
    var lbl = document.getElementById('knowledge-next-label');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!sel) {
      if (lbl) lbl.textContent = '—';
      return;
    }
    var ke = Math.max(0, parseInt(sel.knowledge_energy_into_level, 10) || 0);
    var req = Math.max(1, parseInt(sel.knowledge_energy_required_current, 10) || 1);
    var toNext = Math.max(0, parseInt(sel.knowledge_energy_to_next_level, 10) || 0);
    var filled = Math.min(10, Math.round((ke / req) * 10));
    for (var i = 0; i < 10; i++) {
      var o = document.createElement('div');
      o.style.cssText = 'width:14px;height:14px;border-radius:50%;' + (i < filled
        ? 'background:radial-gradient(circle at 35% 35%,rgba(0,232,255,.85),rgba(0,120,180,.75));border:1px solid var(--c);box-shadow:0 0 6px rgba(0,232,255,.4)'
        : 'background:rgba(0,232,255,.06);border:1px solid rgba(0,232,255,.15)');
      wrap.appendChild(o);
    }
    if (lbl) lbl.textContent = toNext > 0 ? toNext + ' KE to next level' : 'MAX';
  }

  function renderMissions(missions) {
    var body = document.getElementById('missions-body');
    if (!body) return;
    body.innerHTML = '';
    if (!missions.length) {
      body.innerHTML = '<div class="mc-desc" style="padding:8px">No missions today.</div>';
      return;
    }
    var colors = { daily: '#00e8ff', weekly: '#9b30ff', event: '#ffcc00' };
    missions.forEach(function (m) {
      var pct = m.target > 0 ? Math.round((m.progress / m.target) * 100) : 0;
      var typ = 'daily';
      var col = colors[typ] || '#00e8ff';
      var el = document.createElement('div');
      el.className = 'mission-card fade-in';
      el.style.setProperty('--mc', col);
      var claimBtn = m.can_claim
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

  function claimMission(code) {
    if (!code) return;
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('code', code);
    fetch('/api/mind-wars/mission_claim.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error((j.error && j.error.message) || 'Claim failed');
        showToast('Reward claimed.', 'success');
        return refreshLobby();
      })
      .catch(function (e) {
        showToast(e.message || 'Claim failed', 'error');
      });
  }

  function updateNotifBadge(n) {
    var badge = document.getElementById('notif-badge');
    if (!badge) return;
    var c = Number(n.unread_count || 0);
    if (c > 0) {
      badge.textContent = c > 9 ? '9+' : String(c);
      badge.classList.remove('hidden');
    } else {
      badge.classList.add('hidden');
    }
  }

  function refreshLobby() {
    return fetchJson('/api/mind-wars/get_lobby_data.php').then(function (d) {
      applyLobbyData(d);
      return renderLbMini();
    });
  }

  function fillLbMiniFromKdData(data) {
    var body = document.getElementById('lb-mini-body');
    if (!body) return;
    var top = (data && data.top) || [];
    body.innerHTML = '';
    top.forEach(function (p, i) {
      if (i > 0) {
        var sep = document.createElement('div');
        sep.className = 'lb-sep';
        body.appendChild(sep);
      }
      var cls = ['r1', 'r2', 'r3', 'rn', 'rn'][i] || 'rn';
      var div = document.createElement('div');
      div.className = 'lb-row fade-in';
      var avHtml = '⬡';
      div.innerHTML =
        '<span class="lb-rank ' + cls + '">' + p.position + '</span>' +
        '<div class="lb-av">' + avHtml + '</div>' +
        '<div class="lb-info"><div class="lb-name"></div><div class="lb-score"></div></div>';
      div.querySelector('.lb-name').textContent = (p.username || '') + (p.is_current_user ? ' (YOU)' : '');
      div.querySelector('.lb-score').textContent = Number(p.rank_score || 0).toLocaleString() + ' pts';
      body.appendChild(div);
    });
    if (!top.length) body.innerHTML = '<div class="mc-desc">No rankings yet.</div>';
  }

  window.applyKdMiniLeaderboard = fillLbMiniFromKdData;

  function renderLbMini() {
    var body = document.getElementById('lb-mini-body');
    if (!body) return Promise.resolve();
    body.innerHTML = '<div class="mc-desc" style="padding:8px">Loading…</div>';
    var kdShell = typeof window.MW_SHELL_GAME === 'string' && window.MW_SHELL_GAME === 'knowledge-duel';
    var url = kdShell ? '/api/knowledge-duel/leaderboard.php?limit=10' : '/api/mind-wars/get_leaderboard_preview.php';
    return fetchJson(url).then(function (data) {
      if (kdShell) {
        fillLbMiniFromKdData(data);
        return;
      }
      var top = data.top || [];
      body.innerHTML = '';
      top.forEach(function (p, i) {
        if (i > 0) {
          var sep = document.createElement('div');
          sep.className = 'lb-sep';
          body.appendChild(sep);
        }
        var cls = ['r1', 'r2', 'r3', 'rn', 'rn'][i] || 'rn';
        var div = document.createElement('div');
        div.className = 'lb-row fade-in';
        var avHtml = p.avatar_url
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
    }).catch(function (e) {
      body.innerHTML = '<div class="mc-desc" style="color:var(--red)">' + (e.message || 'Failed') + '</div>';
    });
  }

  function renderAvatarGrid() {
    var grid = document.getElementById('av-grid');
    if (!grid || !lobbyData) return;
    var mk = typeof createMwAvatarCard === 'function' ? createMwAvatarCard : null;
    if (!mk) {
      grid.innerHTML = '<div class="mc-desc" style="padding:16px">Avatar cards failed to load.</div>';
      return;
    }
    var list = lobbyData.avatars || [];
    grid.innerHTML = '';
    if (!list.length) {
      grid.innerHTML = '<div class="mc-desc" style="padding:16px;text-align:center">No avatars in collection.</div>';
      return;
    }
    list.forEach(function (av) {
      var st = av.mw_stats || { mnd: 0, fcs: 0, spd: 0, lck: 0 };
      var card = mk({
        item_id: av.item_id,
        name: av.name || 'Avatar',
        rarity: av.rarity || 'common',
        level: av.avatar_level || 1,
        image: av.display_image_url || '',
        stats: st
      }, { buttonLabel: av.is_favorite ? 'EQUIPPED' : 'EQUIP' });
      card.classList.add('lavs-knd-card');
      if (av.is_favorite) card.classList.add('mw-card-equipped');
      var doEquip = function () {
        if (av.is_favorite) return;
        setFavoriteAvatar(av.item_id);
      };
      card.addEventListener('click', function (e) {
        if (e.target.closest('.inspect-btn')) return;
        doEquip();
      });
      var btn = card.querySelector('.inspect-btn');
      if (btn) btn.addEventListener('click', function (e) {
        e.stopPropagation();
        doEquip();
      });
      grid.appendChild(card);
    });
  }

  function setFavoriteAvatar(itemId) {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('item_id', String(itemId));
    fetch('/api/avatar/set_favorite.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Failed');
        showToast('Avatar equipped.', 'success');
        return refreshLobby();
      })
      .catch(function (e) {
        showToast(e.message || 'Could not change avatar', 'error');
      });
  }

  function renderAvpSlots(avatars, selected) {
    var row = document.getElementById('avp-slots-row');
    if (!row) return;
    row.innerHTML = '';
    var mk = typeof createMwAvatarCard === 'function' ? createMwAvatarCard : null;
    var scroll = document.createElement('div');
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
      var st = av.mw_stats || { mnd: 0, fcs: 0, spd: 0, lck: 0 };
      var wrap = document.createElement('div');
      wrap.className = 'avp-slot-card-wrap';
      var equipped = !!av.is_favorite;
      var card = mk({
        item_id: av.item_id,
        name: av.name || 'Avatar',
        rarity: av.rarity || 'common',
        level: av.avatar_level || 1,
        image: av.display_image_url || '',
        stats: st
      }, { compact: true, buttonLabel: equipped ? 'EQUIPPED' : 'EQUIP' });
      if (equipped) card.classList.add('mw-card-equipped');
      var equip = function () {
        if (equipped) return;
        setFavoriteAvatar(av.item_id);
      };
      card.addEventListener('click', function (e) {
        if (e.target.closest('.inspect-btn')) return;
        equip();
      });
      var btn = card.querySelector('.inspect-btn');
      if (btn) btn.addEventListener('click', function (e) {
        e.stopPropagation();
        equip();
      });
      wrap.appendChild(card);
      scroll.appendChild(wrap);
    } else {
      var fallback = document.createElement('div');
      fallback.className = 'avp-slot' + (av && av.is_favorite ? ' active' : '');
      fallback.innerHTML = '⬡';
      scroll.appendChild(fallback);
    }
    row.appendChild(scroll);
  }

  function initStars() {
    var c = document.getElementById('star-canvas');
    if (!c || !c.getContext) return;
    var ctx = c.getContext('2d');
    function resize() {
      c.width = window.innerWidth;
      c.height = window.innerHeight;
    }
    resize();
    var stars = Array.from({ length: 120 }, function () {
      return {
        x: Math.random() * c.width,
        y: Math.random() * c.height,
        r: Math.random() * 1.5,
        speed: 0.002 + Math.random() * 0.008,
        col: Math.random() > 0.7 ? 'rgba(155,48,255,' : 'rgba(0,232,255,'
      };
    });
    function draw() {
      ctx.clearRect(0, 0, c.width, c.height);
      var t = Date.now();
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

  function wireChromeNav() {
    var lobbyUrl = '/games/mind-wars/lobby.php';
    var tb = document.getElementById('tb-avatar-btn');
    if (tb) {
      tb.addEventListener('click', function () { window.location.href = lobbyUrl; });
      tb.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); window.location.href = lobbyUrl; }
      });
    }
    ['av-panel-change', 'avp-inspect'].forEach(function (id) {
      var n = document.getElementById(id);
      if (n) n.addEventListener('click', function () { window.location.href = lobbyUrl; });
    });
    document.querySelectorAll('[data-open-mm]').forEach(function (card) {
      card.addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = lobbyUrl;
      });
    });
    var evBtn = document.getElementById('events-all-btn');
    if (evBtn) evBtn.addEventListener('click', function () { showToast('Events hub — open Mind Wars lobby', 'info'); });
    var mBtn = document.getElementById('missions-all-btn');
    if (mBtn) mBtn.addEventListener('click', function () { showToast('Full mission list — open Mind Wars lobby', 'info'); });
    var lbBtn = document.getElementById('lb-mini-viewall');
    if (lbBtn) {
      lbBtn.addEventListener('click', function () {
        if (window.MW_SHELL_GAME === 'knowledge-duel' || window.MW_SHELL_GAME === 'insight') {
          window.location.href = '/leaderboard.php';
        } else {
          window.location.href = lobbyUrl;
        }
      });
    }
    var notif = document.getElementById('notif-btn');
    if (notif) notif.addEventListener('click', function () { showToast('Notifications — open Mind Wars lobby', 'info'); });
    var settings = document.getElementById('settings-btn');
    if (settings) settings.addEventListener('click', function () { showToast('Settings — open Mind Wars lobby', 'info'); });
    var profile = document.getElementById('profile-btn');
    if (profile) profile.addEventListener('click', function () { showToast('Profile — open Mind Wars lobby', 'info'); });
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
      initStars();
    }, 400);
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('star-canvas')) return;

    applyLobbyData(lobbyData);
    wireChromeNav();

    Promise.all([
      fetchJson('/api/mind-wars/get_lobby_data.php').then(function (d) {
        applyLobbyData(d);
      }).catch(function () {}),
      renderLbMini()
    ]).finally(finishLoading);
  });
})();
