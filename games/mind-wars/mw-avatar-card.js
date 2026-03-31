/**
 * Mind Wars battle-style avatar card DOM builder (matches mind-wars-arena renderKndCard).
 * Exposes window.createMwAvatarCard(avatar, options).
 */
(function (global) {
  'use strict';

  var KND_RARITY = {
    common: { color: '#4a7a8a', label: 'COMMON', glow: 'rgba(74,122,138,0.5)' },
    special: { color: '#1aaa6a', label: 'SPECIAL', glow: 'rgba(26,170,106,0.5)' },
    rare: { color: '#1a6aee', label: 'RARE', glow: 'rgba(26,106,238,0.5)' },
    epic: { color: '#c040ff', label: 'EPIC', glow: 'rgba(192,64,255,0.5)' },
    legendary: { color: '#ffc030', label: 'LEGENDARY', glow: 'rgba(255,192,48,0.5)' }
  };

  var KND_STAT_COLORS = { mnd: '#c040ff', fcs: '#00e5ff', spd: '#20e080', lck: '#ffc030' };
  var KND_STAT_LABELS = { mnd: 'MIND', fcs: 'FOCUS', spd: 'SPEED', lck: 'LUCK' };

  function escHtml(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function setupSlotSilhouette() {
    return '<div class="thumb-silhouette"><div class="thumb-hex">⬡</div><div class="thumb-sil-head"></div><div class="thumb-sil-body"></div></div>';
  }

  /** Prefer level; fall back to avatar_level (API / lobby payload). */
  function resolveMwAvatarLevel(avatar) {
    if (!avatar || typeof avatar !== 'object') return 1;
    var raw = avatar.level;
    if (raw == null || raw === '') raw = avatar.avatar_level;
    var n = Number(raw);
    if (!Number.isFinite(n) || n < 1) n = 1;
    return Math.min(99, Math.floor(n));
  }

  function mwLevelToneClass(level) {
    var b = Math.min(10, Math.max(1, level));
    return 'level-' + b;
  }

  /**
   * @param {object} avatar — item_id|id, name, rarity, class, level|avatar_level, image (url string), stats {mnd,fcs,spd,lck}
   * @param {object} [options] — placeholder, slotIndex, buttonLabel, compact, hideButton, tagline, characterClass, embedHologram
   */
  function createMwAvatarCard(avatar, options) {
    var opts = options || {};
    var isPlaceholder = !!opts.placeholder;
    var rarityKey = avatar && avatar.rarity ? String(avatar.rarity).toLowerCase() : 'common';
    var rarity = KND_RARITY[rarityKey] || KND_RARITY.common;
    var level = isPlaceholder ? 1 : resolveMwAvatarLevel(avatar);
    var stats = avatar && avatar.stats ? avatar.stats : { mnd: 0, fcs: 0, spd: 0, lck: 0 };
    var name = isPlaceholder ? 'SELECT AVATAR' : (avatar && avatar.name ? String(avatar.name) : 'UNKNOWN AVATAR');
    var tagline;
    if (isPlaceholder) {
      tagline = 'SLOT ' + (Number(opts.slotIndex || 0) + 1);
    } else if (opts.tagline != null && String(opts.tagline).trim() !== '') {
      tagline = String(opts.tagline);
    } else {
      tagline = 'LV ' + level;
    }
    var idNum = avatar && (avatar.id != null) ? avatar.id : (avatar && avatar.item_id != null ? avatar.item_id : 0);
    var imgUrl = !isPlaceholder && avatar && avatar.image ? String(avatar.image) : '';
    var holoModelUrl = !isPlaceholder && avatar && (avatar.model || avatar.hologramModelUrl)
      ? String(avatar.model || avatar.hologramModelUrl).trim()
      : '';
    var useHolo = !!opts.embedHologram && holoModelUrl !== '';
    var thumbHTML = useHolo
      ? '<div class="thumb-holo-host"></div>'
      : (imgUrl
        ? '<img src="' + escHtml(imgUrl) + '" alt="' + escHtml(name) + '">'
        : setupSlotSilhouette());

    var statsHtml = Object.keys(KND_STAT_LABELS).map(function (key) {
      var val = Math.max(0, Math.min(100, Number(stats[key] || 0)));
      return '<div class="stat-item">'
        + '<div class="stat-header"><span class="stat-label">' + KND_STAT_LABELS[key] + '</span><span class="stat-value">' + val + '</span></div>'
        + '<div class="stat-bar"><div class="stat-fill" style="--stat-color:' + KND_STAT_COLORS[key] + ';width:' + val + '%"></div></div>'
        + '</div>';
    }).join('');

    var btnLabel = opts.hideButton ? '' : (opts.buttonLabel != null ? String(opts.buttonLabel) : 'SELECT');

    var characterClassText = '';
    if (!isPlaceholder) {
      if (opts.characterClass != null && String(opts.characterClass).trim() !== '') {
        characterClassText = String(opts.characterClass).trim();
      } else if (avatar && avatar.class != null && String(avatar.class).trim() !== '') {
        characterClassText = String(avatar.class).trim();
      }
    }

    var card = document.createElement('article');
    card.className = 'avatar-card' + (isPlaceholder ? ' is-placeholder' : '') + (opts.compact ? ' avatar-card--compact' : '');
    card.style.setProperty('--rarity-color', rarity.color);
    card.style.setProperty('--rarity-glow', rarity.glow);
    card.dataset.rarity = rarityKey;

    var footerHtml = '';
    if (!opts.hideButton) {
      var classSpanHtml = characterClassText !== ''
        ? '<span class="card-class">' + escHtml(characterClassText) + '</span>'
        : '';
      footerHtml = '<div class="card-footer">' + classSpanHtml
        + '<button type="button" class="inspect-btn" tabindex="-1">' + escHtml(btnLabel) + '</button></div>';
    }

    var levelBadgeHtml = isPlaceholder
      ? ''
      : '<div class="card-level-badge level-badge ' + mwLevelToneClass(level) + '" aria-label="Level ' + level + '">LV ' + level + '</div>';

    card.innerHTML = ''
      + '<div class="card-thumb">'
      + '  <div class="card-id">#' + String(idNum).padStart(3, '0') + '</div>'
      + '  <div class="card-rarity-badge">' + (isPlaceholder ? 'EMPTY' : rarity.label) + '</div>'
      + '  <div class="thumb-model' + (useHolo ? ' thumb-model--holo' : '') + '">' + thumbHTML + '</div>'
      + '  <div class="thumb-ring"></div>'
      + levelBadgeHtml
      + '</div>'
      + '<div class="card-body">'
      + '  <div><div class="card-name">' + escHtml(name) + '</div><div class="card-tagline">' + escHtml(tagline) + '</div></div>'
      + '  <div class="card-stats">' + statsHtml + '</div>'
      + footerHtml
      + '</div>';

    if (isPlaceholder) {
      var btn = card.querySelector('.inspect-btn');
      if (btn) btn.textContent = 'EMPTY';
    }

    return card;
  }

  global.createMwAvatarCard = createMwAvatarCard;
})(typeof window !== 'undefined' ? window : this);
