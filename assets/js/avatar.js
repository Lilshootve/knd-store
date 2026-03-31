(function () {
  'use strict';

  const BODY_SVG = '/assets/avatars/base/body.svg';
  const BODY_FALLBACK_SVG = '/assets/avatars/_placeholder.svg';
  const LAYER_ORDER = ['bg', 'body', 'bottom', 'shoes', 'top', 'accessory', 'hair', 'frame'];
  const SLOT_ORDER = ['bg', 'top', 'bottom', 'shoes', 'hair', 'accessory', 'frame'];
  const SLOT_TO_COL = { hair: 'hair_item_id', top: 'top_item_id', bottom: 'bottom_item_id', shoes: 'shoes_item_id', accessory1: 'accessory1_item_id', accessory: 'accessory1_item_id', bg: 'bg_item_id', frame: 'frame_item_id' };

  let state = { loadout: {}, inventory: [], inventoryIds: [], kpBalance: 0 };
  let shopItems = [];
  let svgCache = {};

  function isSvgAsset(path) {
    return /\.svg(?:\?|#|$)/i.test(path || '');
  }

  async function fetchSvg(path) {
    if (svgCache[path]) return svgCache[path];
    try {
      const r = await fetch('/api/avatar/svg.php?path=' + encodeURIComponent(path));
      if (!r.ok) return '';
      const text = await r.text();
      svgCache[path] = text;
      return text;
    } catch (_) { return ''; }
  }

  function buildItemMap() {
    const byId = {};
    state.inventory.forEach(i => { byId[i.id] = i; });
    shopItems.forEach(i => { byId[i.id] = i; });
    return byId;
  }

  async function renderAvatar(container, loadout, itemMap) {
    if (!container) return;
    loadout = loadout || state.loadout;
    itemMap = itemMap || buildItemMap();

    const colToSlot = {};
    Object.keys(SLOT_TO_COL).forEach(slot => { colToSlot[SLOT_TO_COL[slot]] = slot === 'accessory1' ? 'accessory' : slot; });

    const inner = container.querySelector('.avatar-stage-inner') || (() => {
      const d = document.createElement('div');
      d.className = 'avatar-stage-inner';
      container.appendChild(d);
      return d;
    })();

    inner.innerHTML = '';

    for (const layerName of LAYER_ORDER) {
      let svg = '';
      if (layerName === 'body') {
        svg = await fetchSvg(BODY_SVG) || await fetchSvg(BODY_FALLBACK_SVG);
      } else {
        const col = layerName === 'accessory' ? 'accessory1_item_id' : layerName + '_item_id';
        const itemId = loadout[col];
        if (!itemId) continue;
        const item = itemMap[itemId];
        if (!item || !item.asset_path) continue;
        if (isSvgAsset(item.asset_path)) {
          svg = await fetchSvg(item.asset_path);
          if (!svg) continue;
        } else {
          const layer = document.createElement('div');
          layer.className = 'avatar-layer';
          layer.innerHTML = `<img src="${item.asset_path}" alt="" loading="lazy" decoding="async">`;
          inner.appendChild(layer);
          continue;
        }
      }
      const layer = document.createElement('div');
      layer.className = 'avatar-layer';
      layer.innerHTML = svg;
      inner.appendChild(layer);
    }
  }

  function updateKpDisplay(val) {
    const n = typeof val === 'number' ? val : (state.kpBalance || 0);
    document.querySelectorAll('#avatar-kp-balance, #avatar-kp-balance-modal').forEach(function (el) {
      el.textContent = n.toLocaleString();
    });
  }

  async function loadState() {
    try {
      const r = await fetch('/api/avatar/state.php');
      const j = await r.json();
      if (j.ok && j.data) {
        state.loadout = j.data.loadout || {};
        state.inventory = j.data.inventory || [];
        state.inventoryIds = j.data.inventory_ids || [];
        state.kpBalance = j.data.kp_balance || 0;
        return true;
      }
    } catch (_) {}
    return false;
  }

  async function loadShop() {
    try {
      const r = await fetch('/api/avatar/shop.php');
      const j = await r.json();
      if (j.ok && j.data && j.data.items) {
        shopItems = j.data.items;
        return shopItems;
      }
    } catch (_) {}
    return [];
  }

  window.KNDAvatar = {
    async init() {
      const container = document.getElementById('avatar-preview');
      if (!container) return;
      await loadState();
      await renderAvatar(container, state.loadout);
      updateKpDisplay(state.kpBalance);
    },
    async openCustomize() {
      const modal = document.getElementById('avatar-customize-modal');
      if (!modal) return;
      await loadState();
      await loadShop();
      renderCustomizeModal();
      modal.classList.add('avatar-modal-open');
      document.body.classList.add('avatar-modal-open');
    },
    closeCustomize() {
      const modal = document.getElementById('avatar-customize-modal');
      if (modal) {
        modal.classList.remove('avatar-modal-open');
        document.body.classList.remove('avatar-modal-open');
      }
      const container = document.getElementById('avatar-preview');
      if (container) renderAvatar(container, state.loadout);
    },
    getState: () => ({ ...state }),
    getShopItems: () => [...shopItems],
    async buy(itemId) {
      const fd = new FormData();
      fd.append('csrf_token', typeof CSRF !== 'undefined' ? CSRF : (document.querySelector('[name="csrf_token"]')?.value || ''));
      fd.append('item_id', itemId);
      const r = await fetch('/api/avatar/buy.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        await loadState();
        updateKpDisplay(j.data.available_after);
        return { ok: true, balance: j.data.available_after };
      }
      return { ok: false, error: j.error?.message || 'Buy failed' };
    },
    async equip(slot, itemId) {
      const fd = new FormData();
      fd.append('csrf_token', typeof CSRF !== 'undefined' ? CSRF : (document.querySelector('[name="csrf_token"]')?.value || ''));
      fd.append('slot', slot);
      fd.append('item_id', itemId === null || itemId === undefined ? '' : itemId);
      const r = await fetch('/api/avatar/equip.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        await loadState();
        return true;
      }
      return false;
    },
    renderAvatar,
  };

  function renderCustomizeModal() {
    const modal = document.getElementById('avatar-customize-modal');
    if (!modal) return;

    const scrollEl = modal.querySelector('.avatar-modal-scroll');
    const scrollTop = scrollEl ? scrollEl.scrollTop : 0;

    const preview = modal.querySelector('#avatar-customize-preview');
    const tabs = modal.querySelector('#avatar-slot-tabs');
    const ownedPane = modal.querySelector('#avatar-owned-pane');
    const shopPane = modal.querySelector('#avatar-shop-pane');

    const activeSlot = tabs?.dataset?.active || 'hair';

    const itemMap = buildItemMap();
    renderAvatar(preview, state.loadout, itemMap);
    updateKpDisplay(state.kpBalance);

    const slotLabels = { hair: 'Hair', top: 'Top', bottom: 'Bottom', shoes: 'Shoes', accessory1: 'Accessory', bg: 'Background', frame: 'Frame' };
    const t = (key, fallback) => (typeof window.t === 'function' ? window.t(key, fallback) : fallback);

    if (tabs) {
      tabs.innerHTML = SLOT_ORDER.map(slot => {
        const label = t('avatar.slot_' + slot, slotLabels[slot] || slot);
        const active = activeSlot === slot ? ' avatar-slot-active' : '';
        return `<button type="button" class="avatar-slot-btn${active}" data-slot="${slot}">${label}</button>`;
      }).join('');
    }

    const invBySlot = {};
    state.inventory.forEach(i => {
      const s = i.slot;
      if (!invBySlot[s]) invBySlot[s] = [];
      invBySlot[s].push(i);
    });

    const shopBySlot = {};
    shopItems.forEach(i => {
      const s = i.slot;
      if (!shopBySlot[s]) shopBySlot[s] = [];
      shopBySlot[s].push(i);
    });

    const col = activeSlot === 'accessory' ? 'accessory1_item_id' : activeSlot + '_item_id';
    const equippedId = state.loadout[col] || null;

    function cardHtml(item, isOwned) {
      const eq = equippedId === item.id;
      const rarity = item.rarity || 'common';
      const r = t('avatar.rarity_' + rarity, rarity);
      return `
        <div class="avatar-shop-card rarity-${rarity} ${eq ? 'frame-equipped' : ''}">
          <span class="avatar-rarity-chip avatar-rarity-${rarity}">${r}</span>
          <div class="avatar-card-name">${item.name}</div>
          ${!isOwned ? `<div class="avatar-card-price">${item.price_kp} KP</div><button type="button" class="avatar-card-btn avatar-btn-buy" data-id="${item.id}">${t('avatar.buy', 'Buy')}</button>` : ''}
          ${isOwned ? `<button type="button" class="avatar-card-btn avatar-btn-equip ${eq ? 'avatar-btn-equipped' : ''}" data-slot="${activeSlot}" data-id="${item.id}">${eq ? t('avatar.equipped', 'Equipped') : t('avatar.equip', 'Equip')}</button>` : ''}
        </div>
      `;
    }

    if (ownedPane) {
      const items = invBySlot[activeSlot] || [];
      const isDefaultEquipped = !equippedId;
      const defaultBtn = `<div class="avatar-shop-card"><span class="avatar-rarity-chip" style="opacity:.6">—</span><div class="avatar-card-name">${t('avatar.default', 'Default')}</div><button type="button" class="avatar-card-btn avatar-btn-equip ${isDefaultEquipped ? 'avatar-btn-equipped' : ''}" data-slot="${activeSlot}" data-id="">${isDefaultEquipped ? t('avatar.equipped', 'Equipped') : t('avatar.equip', 'Equip')}</button></div>`;
      ownedPane.innerHTML = defaultBtn + (items.length ? items.map(i => cardHtml(i, true)).join('') : `<p class="text-white-50 small">${t('avatar.no_owned', 'No items in this slot')}</p>`);
    }

    if (shopPane) {
      const items = (shopBySlot[activeSlot] || []).filter(i => !state.inventoryIds.includes(i.id));
      shopPane.innerHTML = items.length ? items.map(i => cardHtml(i, false)).join('') : `<p class="text-white-50 small">${t('avatar.no_shop', 'Nothing to buy')}</p>`;
    }

    modal.querySelectorAll('.avatar-btn-buy').forEach(btn => {
      btn.addEventListener('click', async () => {
        btn.disabled = true;
        const res = await window.KNDAvatar.buy(parseInt(btn.dataset.id, 10));
        if (res.ok) renderCustomizeModal();
        else if (window.kndToast) window.kndToast(res.error || 'Error', 'error');
        btn.disabled = false;
      });
    });

    modal.querySelectorAll('.avatar-btn-equip').forEach(btn => {
      btn.addEventListener('click', async () => {
        const slot = btn.dataset.slot === 'accessory' ? 'accessory1' : btn.dataset.slot;
        const idVal = btn.dataset.id;
        const itemId = (idVal === '' || idVal === undefined) ? null : parseInt(idVal, 10);
        const ok = await window.KNDAvatar.equip(slot, itemId);
        if (ok) renderCustomizeModal();
      });
    });

    tabs?.querySelectorAll('[data-slot]').forEach(btn => {
      btn.addEventListener('click', () => {
        tabs.dataset.active = btn.dataset.slot;
        renderCustomizeModal();
      });
    });

    if (scrollEl) scrollEl.scrollTop = scrollTop;
  }

  document.addEventListener('DOMContentLoaded', () => {
    window.KNDAvatar.init();

    document.getElementById('avatar-btn-customize')?.addEventListener('click', () => window.KNDAvatar.openCustomize());
    document.getElementById('avatar-customize-close')?.addEventListener('click', () => window.KNDAvatar.closeCustomize());
    document.getElementById('avatar-customize-modal')?.addEventListener('click', (e) => {
      if (e.target.id === 'avatar-customize-modal') window.KNDAvatar.closeCustomize();
    });
  });
})();
