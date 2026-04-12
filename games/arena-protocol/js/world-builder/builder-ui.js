/**
 * BuilderUI — injects and manages the full left-panel editor UI.
 * Tabs: Objects | Materials | Lighting | Environment | Scene | Camera
 * AAA additions: Undo/Redo bar, Multi-select indicator, Snap mode,
 *                Marketplace, Hierarchy, Terrain, Collab peers.
 * All UI is generated via JS — zero HTML changes required in nexus-city.html.
 */

import { ENVIRONMENT_PRESETS } from './environment-system.js';

const TABS = ['objects', 'materials', 'lighting', 'environment', 'scene', 'camera'];

export class BuilderUI {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx = builder.ctx;
    this._activeTab = 'objects';
    this._panel = null;
    this._statusEl = null;
    this._statsEl = null;
    this._injectStyles();
    this._buildPanel();
  }

  // ─────────────────────────────────────────────────────────
  // CSS INJECTION
  // ─────────────────────────────────────────────────────────

  _injectStyles() {
    // Always remove + re-inject so updates take effect without hard refresh
    const old = document.getElementById('wb-pro-styles');
    if (old) old.remove();
    const style = document.createElement('style');
    style.id = 'wb-pro-styles';
    style.textContent = `
/* ── WB PRO PANEL — Large readable version ── */
#wb-pro{
  position:fixed;left:0;top:48px;bottom:56px;
  width:min(420px,52vw);max-width:100%;
  z-index:60;
  background:rgba(4,7,22,.98);
  border-right:2px solid rgba(155,48,255,.25);
  backdrop-filter:blur(20px);
  display:flex;flex-direction:column;
  font-family:"Share Tech Mono",monospace;
  transform:translateX(-100%);
  transition:transform .28s cubic-bezier(.2,.8,.2,1);
  overflow:hidden;
  font-size:13px;
}
#wb-pro.open{ transform:translateX(0); }

/* Header */
.wb-pro-hdr{
  padding:14px 16px 12px;
  border-bottom:1px solid rgba(155,48,255,.18);
  display:flex;align-items:center;gap:10px;flex-shrink:0;
}
.wb-pro-badge{
  background:rgba(155,48,255,.18);border:1px solid rgba(155,48,255,.5);
  border-radius:4px;padding:4px 10px;
  font-size:11px;font-weight:700;letter-spacing:.14em;color:#c158ff;
  font-family:"Orbitron",sans-serif;
}
.wb-pro-title{
  font-family:"Orbitron",sans-serif;font-size:13px;font-weight:700;
  letter-spacing:.1em;color:rgba(200,175,255,.95);
}
.wb-pro-close{
  margin-left:auto;width:26px;height:26px;border-radius:50%;
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:12px;color:rgba(255,255,255,.4);
  transition:all .15s;flex-shrink:0;
}
.wb-pro-close:hover{ background:rgba(255,61,86,.15);color:#ff3d56;border-color:rgba(255,61,86,.4); }

/* Tabs */
.wb-pro-tabs{
  display:flex;border-bottom:1px solid rgba(155,48,255,.12);flex-shrink:0;
  overflow-x:auto;
}
.wb-pro-tabs::-webkit-scrollbar{ height:0; }
.wb-pro-tab{
  flex:1;min-width:52px;padding:9px 4px;
  text-align:center;cursor:pointer;
  font-size:9px;letter-spacing:.08em;color:rgba(90,165,190,.5);
  border-bottom:2px solid transparent;
  transition:all .15s;white-space:nowrap;
  font-family:"Orbitron",sans-serif;
}
.wb-pro-tab:hover{ color:rgba(155,215,235,.75);background:rgba(255,255,255,.03); }
.wb-pro-tab.on{
  color:#c158ff;border-bottom-color:rgba(155,48,255,.8);
  background:rgba(155,48,255,.08);
}
.wb-pro-tab-icon{ font-size:14px;display:block;margin-bottom:3px; }

/* Content */
.wb-pro-content{
  flex:1;overflow-y:auto;overflow-x:hidden;padding:14px 16px;
  display:flex;flex-direction:column;gap:12px;
}
.wb-pro-content::-webkit-scrollbar{ width:5px; }
.wb-pro-content::-webkit-scrollbar-thumb{ background:rgba(155,48,255,.3);border-radius:3px; }

/* Section label */
.wb-sec-lbl{
  font-size:10px;letter-spacing:.18em;color:rgba(155,100,255,.6);
  margin-bottom:7px;text-transform:uppercase;font-weight:700;
}

/* Catalog grid */
.wb-catalog-grid{ display:grid;grid-template-columns:repeat(2,1fr);gap:9px; }
.wb-cat-item{
  background:rgba(155,48,255,.07);border:1px solid rgba(155,48,255,.16);
  border-radius:8px;padding:12px 8px;cursor:pointer;text-align:center;
  transition:all .18s;
}
.wb-cat-item:hover{
  background:rgba(155,48,255,.16);border-color:rgba(155,48,255,.42);
  transform:translateY(-2px);box-shadow:0 4px 14px rgba(155,48,255,.15);
}
.wb-cat-item.on{
  background:rgba(155,48,255,.25);border-color:#9b30ff;
  box-shadow:0 0 14px rgba(155,48,255,.4);
}
.wb-cat-icon{ font-size:24px;line-height:1.2; }
.wb-cat-name{
  font-size:11px;color:rgba(210,190,255,.8);margin-top:6px;
  line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;
  -webkit-box-orient:vertical;overflow:hidden;word-break:break-word;
}

/* Placed objects list (catalog + district anchors) */
.wb-scene-list{
  display:flex;flex-direction:column;gap:6px;
  max-height:180px;overflow-y:auto;padding-right:4px;
}
.wb-scene-list::-webkit-scrollbar{ width:4px; }
.wb-scene-list::-webkit-scrollbar-thumb{ background:rgba(155,48,255,.35);border-radius:2px; }
.wb-scene-item{
  background:rgba(0,232,255,.06);border:1px solid rgba(0,232,255,.15);
  border-radius:6px;padding:8px 10px;cursor:pointer;font-size:11px;
  color:rgba(200,225,255,.88);transition:all .15s;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.wb-scene-item:hover{
  background:rgba(0,232,255,.12);border-color:rgba(0,232,255,.35);
}
.wb-scene-item.on{
  background:rgba(155,48,255,.2);border-color:rgba(155,48,255,.55);
  box-shadow:0 0 10px rgba(155,48,255,.2);
}

/* Row control */
.wb-ctrl-row{ display:flex;align-items:center;gap:8px; }
.wb-ctrl-lbl{
  font-size:11px;color:rgba(155,215,235,.5);width:80px;flex-shrink:0;
  letter-spacing:.04em;
}
.wb-ctrl-inp{
  flex:1;background:rgba(0,0,0,.4);border:1px solid rgba(0,232,255,.18);
  border-radius:5px;padding:7px 10px;
  font-family:"Share Tech Mono",monospace;font-size:13px;
  color:rgba(200,225,255,.9);outline:none;width:0;min-width:0;
}
.wb-ctrl-inp:focus{ border-color:rgba(0,232,255,.5); }
input[type=range].wb-range{
  -webkit-appearance:none;flex:1;height:4px;
  background:rgba(255,255,255,.1);border-radius:2px;cursor:pointer;
  accent-color:#9b30ff;outline:none;
}
input[type=range].wb-range::-webkit-slider-thumb{
  -webkit-appearance:none;width:16px;height:16px;border-radius:50%;
  background:#c158ff;border:2px solid rgba(155,48,255,.6);
  box-shadow:0 0 8px rgba(155,48,255,.5);cursor:pointer;
}
input[type=color].wb-color{
  -webkit-appearance:none;width:32px;height:32px;border-radius:5px;
  border:1px solid rgba(0,232,255,.25);background:none;cursor:pointer;
  padding:2px;flex-shrink:0;
}
.wb-val-badge{
  font-size:11px;color:rgba(0,232,255,.7);width:38px;
  text-align:right;flex-shrink:0;
}

/* Buttons */
.wb-btn-row{ display:flex;gap:7px; }
.wb-pbtn{
  flex:1;padding:9px 8px;border-radius:6px;cursor:pointer;
  font-family:"Orbitron",sans-serif;font-size:9px;font-weight:700;
  letter-spacing:.08em;text-align:center;border:1px solid;
  transition:all .15s;user-select:none;
}
.wb-pbtn.cyan{ background:rgba(0,232,255,.08);border-color:rgba(0,232,255,.3);color:#00e8ff; }
.wb-pbtn.cyan:hover{ background:rgba(0,232,255,.17); }
.wb-pbtn.cyan.on{ background:rgba(0,232,255,.2);box-shadow:0 0 10px rgba(0,232,255,.25); }
.wb-pbtn.purple{ background:rgba(155,48,255,.09);border-color:rgba(155,48,255,.35);color:#c158ff; }
.wb-pbtn.purple:hover{ background:rgba(155,48,255,.2); }
.wb-pbtn.purple.on{ background:rgba(155,48,255,.22);box-shadow:0 0 10px rgba(155,48,255,.3); }
.wb-pbtn.red{ background:rgba(255,61,86,.08);border-color:rgba(255,61,86,.3);color:#ff3d56; }
.wb-pbtn.red:hover{ background:rgba(255,61,86,.18); }
.wb-pbtn.green{ background:rgba(0,255,136,.08);border-color:rgba(0,255,136,.3);color:#00ff88; }
.wb-pbtn.green:hover{ background:rgba(0,255,136,.18); }

/* Selected info box */
.wb-sel-box{
  background:rgba(155,48,255,.07);border:1px solid rgba(155,48,255,.22);
  border-radius:8px;padding:12px 14px;
}
.wb-sel-name{
  font-family:"Orbitron",sans-serif;font-size:12px;font-weight:700;
  letter-spacing:.07em;color:rgba(210,190,255,.95);
  margin-bottom:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}

/* Gizmo mode buttons */
.wb-gizmo-row{ display:flex;gap:6px;margin-bottom:6px; }
.wb-gizmo-btn{
  flex:1;padding:8px 4px;border-radius:5px;cursor:pointer;
  font-size:9px;font-family:"Orbitron",sans-serif;letter-spacing:.06em;
  border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);
  color:rgba(155,215,235,.5);transition:all .15s;text-align:center;
}
.wb-gizmo-btn.on{ background:rgba(0,232,255,.12);border-color:rgba(0,232,255,.4);color:#00e8ff; }
.wb-gizmo-btn:hover:not(.on){ color:rgba(155,215,235,.8);background:rgba(255,255,255,.07); }

/* Divider */
.wb-div{ height:1px;background:rgba(255,255,255,.06);margin:6px -16px;flex-shrink:0; }

/* Status bar */
.wb-pro-status{
  padding:10px 16px;border-top:1px solid rgba(155,48,255,.12);
  font-size:11px;color:rgba(155,180,255,.6);line-height:1.6;
  word-break:break-word;flex-shrink:0;min-height:44px;
}

/* Stats bar */
.wb-pro-stats{
  padding:4px 16px;font-size:10px;color:rgba(90,165,190,.35);
  letter-spacing:.04em;border-top:1px solid rgba(0,0,0,.3);flex-shrink:0;
}

/* Key hint row */
.wb-key-row{ display:flex;align-items:center;gap:8px;padding:3px 0; }
.wb-k{
  display:inline-flex;align-items:center;justify-content:center;
  min-width:28px;height:22px;padding:0 6px;border-radius:4px;
  font-size:10px;border:1px solid rgba(155,48,255,.4);
  background:rgba(40,10,80,.5);color:rgba(200,150,255,.9);
  font-family:"Share Tech Mono",monospace;
}
.wb-kl{ font-size:11px;color:rgba(155,120,200,.65);letter-spacing:.03em; }

/* Empty state */
.wb-empty{
  text-align:center;padding:24px 12px;
  font-size:12px;color:rgba(90,165,190,.35);line-height:1.8;
}

/* ── SAVE BUTTON ── */
.wb-save-btn{
  width:100%;padding:11px 12px;border-radius:6px;cursor:pointer;
  font-family:"Orbitron",sans-serif;font-size:11px;font-weight:700;
  letter-spacing:.12em;text-align:center;border:1px solid;
  transition:all .15s;user-select:none;
  background:linear-gradient(135deg,rgba(0,255,136,.12),rgba(0,200,100,.08));
  border-color:rgba(0,255,136,.45);color:#00ff88;
  box-shadow:0 0 14px rgba(0,255,136,.1);
}
.wb-save-btn:hover{
  background:linear-gradient(135deg,rgba(0,255,136,.24),rgba(0,200,100,.18));
  box-shadow:0 0 22px rgba(0,255,136,.25);transform:translateY(-1px);
}
.wb-save-btn.saved{
  background:linear-gradient(135deg,rgba(0,255,136,.32),rgba(0,200,100,.24));
  border-color:#00ff88;color:#fff;
  box-shadow:0 0 28px rgba(0,255,136,.4);pointer-events:none;
}

/* Save section wrapper */
.wb-save-section{
  padding:10px 0 2px;
  border-top:1px solid rgba(0,255,136,.12);
  margin-top:8px;
}

/* Sticky save bar */
#wb-save-bar{
  padding:10px 16px;
  border-top:2px solid rgba(0,255,136,.2);
  background:rgba(0,10,6,.85);
  backdrop-filter:blur(10px);
  flex-shrink:0;
  display:none;
}
#wb-save-bar.visible{ display:block !important; }
#wb-save-bar-btn{
  width:100%;padding:13px;border-radius:6px;cursor:pointer;
  font-family:"Orbitron",sans-serif;font-size:11px;font-weight:900;
  letter-spacing:.18em;text-align:center;
  background:linear-gradient(135deg,rgba(0,255,136,.2),rgba(0,200,100,.14));
  border:1px solid rgba(0,255,136,.55);color:#00ff88;
  box-shadow:0 0 18px rgba(0,255,136,.15);
  transition:all .15s;user-select:none;
}
#wb-save-bar-btn:hover{
  background:linear-gradient(135deg,rgba(0,255,136,.32),rgba(0,200,100,.24));
  box-shadow:0 0 32px rgba(0,255,136,.32);
}
#wb-save-bar-btn.saved{
  background:linear-gradient(135deg,rgba(0,255,136,.4),rgba(0,200,100,.3));
  color:#fff;pointer-events:none;border-color:#00ff88;
}
#wb-save-bar-name{
  font-size:10px;color:rgba(0,255,136,.5);text-align:center;
  margin-top:5px;letter-spacing:.1em;white-space:nowrap;
  overflow:hidden;text-overflow:ellipsis;
}
`;
    document.head.appendChild(style);
  }

  // ─────────────────────────────────────────────────────────
  // PANEL CONSTRUCTION
  // ─────────────────────────────────────────────────────────

  _buildPanel() {
    const panel = document.createElement('div');
    panel.id = 'wb-pro';
    panel.innerHTML = `
<div class="wb-pro-hdr">
  <div class="wb-pro-badge">ADMIN</div>
  <div class="wb-pro-title">WORLD BUILDER</div>
  <div id="wb-collab-count" style="font-size:7px;color:transparent;margin-left:4px;letter-spacing:.08em;"></div>
  <div class="wb-pro-close" id="wb-pro-close">✕</div>
</div>
<div style="display:flex;align-items:center;gap:6px;padding:5px 12px;border-bottom:1px solid rgba(155,48,255,.08);flex-shrink:0;">
  <div id="wb-undo-btn" title="Undo (Ctrl+Z)" style="cursor:pointer;font-size:13px;opacity:.3;transition:opacity .15s;" onclick="window._wbUndoProxy()">↩</div>
  <div id="wb-redo-btn" title="Redo (Ctrl+Y)" style="cursor:pointer;font-size:13px;opacity:.3;transition:opacity .15s;" onclick="window._wbRedoProxy()">↪</div>
  <div id="wb-multisel-badge" style="display:none;font-size:7px;color:#00ff88;letter-spacing:.08em;background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.2);padding:2px 7px;border-radius:10px;"></div>
  <div style="margin-left:auto;display:flex;gap:6px;">
    <div id="wb-snap-indicator" title="Press P to cycle snap mode" onclick="window._wbCycleSnap()" style="cursor:pointer;font-size:6.5px;color:rgba(0,232,255,.4);letter-spacing:.08em;padding:2px 6px;border:1px solid rgba(0,232,255,.12);border-radius:3px;">SNAP: SURFACE</div>
    <div title="Marketplace (M)" onclick="window._wbMarketProxy()" style="cursor:pointer;font-size:11px;opacity:.6;transition:opacity .15s;" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=.6">🛒</div>
    <div title="Scene Hierarchy (H)" onclick="window._wbHierProxy()" style="cursor:pointer;font-size:11px;opacity:.6;transition:opacity .15s;" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=.6">🌳</div>
    <div title="Terrain Tools (N)" onclick="window._wbTerrainProxy()" style="cursor:pointer;font-size:11px;opacity:.6;transition:opacity .15s;" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=.6">🏔</div>
  </div>
</div>
<div class="wb-pro-tabs" id="wb-pro-tabs"></div>
<div class="wb-pro-content" id="wb-pro-body"></div>
<div id="wb-save-bar">
  <div id="wb-save-bar-btn">💾 GUARDAR CAMBIOS AL OBJETO</div>
  <div id="wb-save-bar-name"></div>
</div>
<div class="wb-pro-status" id="wb-pro-status">Builder active.</div>
<div class="wb-pro-stats" id="wb-pro-stats"></div>
`;

    // Proxy globals for onclick (ES module scope limitation)
    window._wbUndoProxy    = () => this.builder.undoRedo?.undo();
    window._wbRedoProxy    = () => this.builder.undoRedo?.redo();
    window._wbMarketProxy  = () => this.builder.marketplace?.open();
    window._wbHierProxy    = () => this.builder.hierarchyPanel?.toggle();
    window._wbTerrainProxy = () => this.builder.terrainTools?.isActive()
      ? this.builder.terrainTools.deactivate()
      : this.builder.terrainTools.activate();
    window._wbCycleSnap    = () => {
      const modes = ['ground', 'surface', 'grid'];
      const next  = modes[(modes.indexOf(this.builder.surfaceSnap.getMode()) + 1) % modes.length];
      this.builder.surfaceSnap.setMode(next);
      this.refreshSnapMode();
    };

    // Wire global save bar button
    const saveBarBtn = panel.querySelector('#wb-save-bar-btn');
    if (saveBarBtn) {
      saveBarBtn.onclick = () => this._saveAllForSelected(saveBarBtn);
    }
    document.body.appendChild(panel);
    this._panel    = panel;
    this._statusEl = panel.querySelector('#wb-pro-status');
    this._statsEl  = panel.querySelector('#wb-pro-stats');

    panel.querySelector('#wb-pro-close').onclick = () => this.builder.deactivate();

    this._buildTabs();
    this._renderTab('objects');
  }

  _buildTabs() {
    const tabConfig = [
      { id: 'objects',     icon: '🧱', label: 'OBJECTS'  },
      { id: 'materials',   icon: '🎨', label: 'MATERIAL' },
      { id: 'lighting',    icon: '💡', label: 'LIGHTS'   },
      { id: 'environment', icon: '🌌', label: 'ENV'      },
      { id: 'scene',       icon: '🌐', label: 'SCENE'    },
      { id: 'camera',      icon: '📷', label: 'CAMERA'   },
    ];
    const tabsEl = document.getElementById('wb-pro-tabs');
    tabsEl.innerHTML = '';
    tabConfig.forEach(t => {
      const el = document.createElement('div');
      el.className = `wb-pro-tab${t.id === this._activeTab ? ' on' : ''}`;
      el.dataset.tab = t.id;
      el.innerHTML = `<span class="wb-pro-tab-icon">${t.icon}</span>${t.label}`;
      el.onclick = () => { this._renderTab(t.id); };
      tabsEl.appendChild(el);
    });
  }

  _renderTab(tabId) {
    this._activeTab = tabId;
    document.querySelectorAll('.wb-pro-tab').forEach(el => {
      el.classList.toggle('on', el.dataset.tab === tabId);
    });
    const body = document.getElementById('wb-pro-body');
    body.innerHTML = '';

    switch (tabId) {
      case 'objects':      this._renderObjectsTab(body);      break;
      case 'materials':    this._renderMaterialsTab(body);    break;
      case 'lighting':     this._renderLightingTab(body);     break;
      case 'environment':  this._renderEnvironmentTab(body);  break;
      case 'scene':        this._renderSceneTab(body);        break;
      case 'camera':       this._renderCameraTab(body);       break;
    }
  }

  // ─────────────────────────────────────────────────────────
  // TAB: OBJECTS
  // ─────────────────────────────────────────────────────────

  _renderObjectsTab(body) {
    const catalog = this.builder.catalogSystem;
    const sel = this.builder.transformSystem.selectedEntry;
    const placed = catalog.getObjects();

    // — Objects already in scene (furniture + district GLB anchors) —
    if (placed.length > 0) {
      body.appendChild(this._el('div', 'wb-sec-lbl', `EN ESCENA (${placed.length})`));
      const list = this._el('div', 'wb-scene-list');
      placed.forEach(entry => {
        const row = this._el('div', 'wb-scene-item');
        const isOn = sel?.id === entry.id;
        if (isOn) row.classList.add('on');
        const label = entry.label || entry.item_id || entry.id;
        row.textContent = entry.external ? `📍 ${label}` : label;
        row.title = String(entry.id);
        row.onclick = (e) => {
          e.stopPropagation();
          this.builder.multiSelect.clear();
          this.builder.transformSystem.select(entry);
          this.builder.hierarchyPanel?.refresh?.();
          this._renderTab('objects');
        };
        list.appendChild(row);
      });
      body.appendChild(list);
      body.appendChild(this._div());
    }

    // — Selected object info —
    if (sel) {
      const { mesh, item_id } = sel;
      const catEntry = catalog.findCatalogEntry(item_id);
      const name = sel.label || catEntry?.name || item_id;

      const selBox = this._el('div', 'wb-sel-box');
      selBox.innerHTML = `<div class="wb-sel-name">📌 ${name}</div>`;

      // Gizmo mode
      const gizmoRow = this._el('div', 'wb-gizmo-row');
      ['translate', 'rotate', 'scale'].forEach(mode => {
        const btn = this._el('div', `wb-gizmo-btn${this.builder.transformSystem._gizmoMode === mode ? ' on' : ''}`);
        btn.dataset.mode = mode;
        btn.textContent = { translate: 'MOVE', rotate: 'ROTATE', scale: 'SCALE' }[mode];
        btn.onclick = () => this.builder.transformSystem.setMode(mode);
        gizmoRow.appendChild(btn);
      });
      selBox.appendChild(gizmoRow);

      // Numeric inputs: Position
      selBox.appendChild(this._el('div', 'wb-sec-lbl', 'POSITION'));
      ['x', 'y', 'z'].forEach(axis => {
        const row = this._ctrlRow(axis.toUpperCase(), 'number');
        const inp = row.querySelector('input');
        inp.value = mesh.position[axis].toFixed(3);
        inp.id = `wb-pos-${axis}`;
        inp.step = '0.1';
        inp.onchange = () => this._applyTransform();
        selBox.appendChild(row);
      });

      // Rotation Y
      selBox.appendChild(this._el('div', 'wb-sec-lbl', 'ROTATION Y (rad)'));
      const rotRow = this._ctrlRow('ROT Y', 'number');
      const rotInp = rotRow.querySelector('input');
      rotInp.id = 'wb-rot-y';
      rotInp.value = mesh.rotation.y.toFixed(3);
      rotInp.step = '0.1';
      rotInp.onchange = () => this._applyTransform();
      selBox.appendChild(rotRow);

      // Scale
      selBox.appendChild(this._el('div', 'wb-sec-lbl', 'SCALE'));
      const scaleRow = this._ctrlRow('SCALE', 'number');
      const scaleInp = scaleRow.querySelector('input');
      scaleInp.id = 'wb-scale';
      scaleInp.value = mesh.scale.x.toFixed(3);
      scaleInp.step = '0.05';
      scaleInp.min = '0.05';
      scaleInp.max = '12';
      scaleInp.onchange = () => this._applyTransform();
      selBox.appendChild(scaleRow);

      // Save transform button
      const saveSec = this._el('div', 'wb-save-section');
      saveSec.appendChild(this._saveBtn('💾 GUARDAR POSICIÓN / ESCALA', () => {
        this._applyTransform();
      }));
      selBox.appendChild(saveSec);

      body.appendChild(selBox);

      // Action buttons (district anchors are external — no duplicate/delete)
      const actionRow = this._el('div', 'wb-btn-row');
      const focusBtn = this._pbtn('⊙ FOCUS', 'purple');
      focusBtn.onclick = () => this.builder.transformSystem.focusSelected();
      actionRow.appendChild(focusBtn);
      if (!sel.external) {
        const dupBtn = this._pbtn('📋 DUPLICATE', 'cyan');
        dupBtn.onclick = () => catalog.duplicateObject(sel);
        const delBtn = this._pbtn('✕ DELETE', 'red');
        delBtn.onclick = () => { this.builder.transformSystem.deselect(); catalog.deleteObject(sel); };
        actionRow.append(dupBtn, delBtn);
      }
      body.appendChild(actionRow);

      body.appendChild(this._div());
    } else {
      const hint = this._el('div', 'wb-empty');
      hint.textContent = 'No object selected.\nClick an object in the scene to select it, or choose from catalog below.';
      body.appendChild(hint);
    }

    // — Multi-select actions (when 2+ selected) —
    const multiSel = this.builder.multiSelect;
    if (multiSel && multiSel.hasMultiple()) {
      body.appendChild(this._div());
      body.appendChild(this._el('div', 'wb-sec-lbl', `MULTI-SELECT (${multiSel.selection.size} objects)`));
      const msRow = this._el('div', 'wb-btn-row');
      const dupAllBtn = this._pbtn('📋 DUPLICATE ALL', 'cyan');
      dupAllBtn.onclick = () => multiSel.duplicateAll();
      const delAllBtn = this._pbtn('✕ DELETE ALL', 'red');
      delAllBtn.onclick = () => multiSel.deleteAll();
      const grpBtn = this._pbtn('⊞ GROUP', 'purple');
      grpBtn.onclick = () => { this.builder.hierarchyPanel._groupSelected(); this.builder.hierarchyPanel.open(); };
      msRow.append(dupAllBtn, delAllBtn, grpBtn);
      body.appendChild(msRow);
      body.appendChild(this._div());
    }

    // — Catalog —
    body.appendChild(this._el('div', 'wb-sec-lbl', `CATALOG (${catalog.catalog.length})`));

    if (!catalog.catalog.length) {
      const empty = this._el('div', 'wb-empty');
      empty.textContent = 'Catalog empty. Check server connection.';
      body.appendChild(empty);
      return;
    }

    const grid = this._el('div', 'wb-catalog-grid');
    catalog.catalog.forEach(item => {
      const d = document.createElement('div');
      d.className = 'wb-cat-item';
      d.dataset.wbId = item.id;
      d.title = item.name;
      d.innerHTML = `<div class="wb-cat-icon">${item.icon || '🧱'}</div><div class="wb-cat-name">${item.name}</div>`;
      d.onclick = () => {
        document.querySelectorAll('.wb-cat-item').forEach(el => el.classList.remove('on'));
        d.classList.add('on');
        catalog.selectCatalogItem(item);
      };
      grid.appendChild(d);
    });
    body.appendChild(grid);

    // — Keyboard reference —
    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'SHORTCUTS'));
    const keys = [
      ['Click', 'place / select'],
      ['R', 'rotate 45°'],
      ['+ / −', 'scale'],
      ['T / Y / S', 'move / rotate / scale mode'],
      ['Ctrl+D', 'duplicate'],
      ['DEL', 'delete selected'],
      ['ESC', 'cancel / deselect'],
      ['G', 'grid snap toggle'],
      ['B', 'exit builder'],
    ];
    keys.forEach(([k, l]) => {
      const row = this._el('div', 'wb-key-row');
      row.innerHTML = `<span class="wb-k">${k}</span><span class="wb-kl">${l}</span>`;
      body.appendChild(row);
    });
  }

  _applyTransform() {
    const sel = this.builder.transformSystem.selectedEntry;
    if (!sel) return;
    const px = parseFloat(document.getElementById('wb-pos-x')?.value) || 0;
    const py = parseFloat(document.getElementById('wb-pos-y')?.value) || 0;
    const pz = parseFloat(document.getElementById('wb-pos-z')?.value) || 0;
    const ry = parseFloat(document.getElementById('wb-rot-y')?.value) || 0;
    const sc = parseFloat(document.getElementById('wb-scale')?.value)  || 1;
    this.builder.transformSystem.applyTransformFromInputs(px, py, pz, ry, sc);
  }

  // ─────────────────────────────────────────────────────────
  // TAB: MATERIALS
  // ─────────────────────────────────────────────────────────

  _renderMaterialsTab(body) {
    const sel = this.builder.transformSystem.selectedEntry;
    if (!sel) {
      body.appendChild(this._emptyState('Select an object to edit its materials.'));
      return;
    }

    const matSys = this.builder.materialSystem;
    const vals = matSys.getValues(sel);
    if (!vals) {
      body.appendChild(this._emptyState('No editable materials found on this object.'));
      return;
    }

    // Snapshot for restore
    matSys.snapshotMaterials(sel);

    body.appendChild(this._el('div', 'wb-sec-lbl', 'SURFACE'));
    body.appendChild(this._colorCtrl('Base Color', vals.color, v => matSys.setBaseColor(sel, v)));
    body.appendChild(this._colorCtrl('Emissive', vals.emissive, v => matSys.setEmissiveColor(sel, v)));
    body.appendChild(this._rangeCtrl('Emit Intens', 0, 5, 0.01, vals.emissiveIntensity, v => matSys.setEmissiveIntensity(sel, v)));

    // Save surface
    const saveSurface = this._el('div', 'wb-save-section');
    saveSurface.appendChild(this._saveBtn('💾 GUARDAR COLOR / EMISSIVE', () => matSys.flushEntry(sel)));
    body.appendChild(saveSurface);

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'PBR'));
    body.appendChild(this._rangeCtrl('Metalness', 0, 1, 0.01, vals.metalness, v => matSys.setMetalness(sel, v)));
    body.appendChild(this._rangeCtrl('Roughness', 0, 1, 0.01, vals.roughness, v => matSys.setRoughness(sel, v)));

    // Save PBR
    const savePbr = this._el('div', 'wb-save-section');
    savePbr.appendChild(this._saveBtn('💾 GUARDAR PBR', () => matSys.flushEntry(sel)));
    body.appendChild(savePbr);

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'TRANSPARENCY'));
    body.appendChild(this._rangeCtrl('Opacity', 0, 1, 0.01, vals.opacity, v => matSys.setOpacity(sel, v)));

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'DISPLAY'));
    const wireRow = this._el('div', 'wb-btn-row');
    const wireBtn = this._pbtn(`◫ WIREFRAME: ${vals.wireframe ? 'ON' : 'OFF'}`, vals.wireframe ? 'cyan' : 'purple');
    wireBtn.onclick = () => {
      const newVal = !matSys.getValues(sel)?.wireframe;
      matSys.setWireframe(sel, newVal);
      wireBtn.textContent = `◫ WIREFRAME: ${newVal ? 'ON' : 'OFF'}`;
      wireBtn.className = `wb-pbtn ${newVal ? 'cyan on' : 'purple'}`;
    };
    wireRow.appendChild(wireBtn);
    body.appendChild(wireRow);

    // Save opacity + display
    const saveDisp = this._el('div', 'wb-save-section');
    saveDisp.appendChild(this._saveBtn('💾 GUARDAR OPACIDAD / DISPLAY', () => matSys.flushEntry(sel)));
    body.appendChild(saveDisp);

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'OVERRIDE (DESTRUCTIVO)'));
    const overrideRow = this._el('div', 'wb-btn-row');
    const overrideBtn = this._pbtn('⚠ REEMPLAZAR CON STANDARD MAT', 'red');
    overrideBtn.onclick = () => {
      if (!confirm('¿Reemplazar todos los materiales? Esto elimina las texturas.')) return;
      matSys.overrideWithStandard(sel);
      this._renderTab('materials');
    };
    overrideRow.appendChild(overrideBtn);
    body.appendChild(overrideRow);

    const restoreRow = this._el('div', 'wb-btn-row');
    const restoreBtn = this._pbtn('↺ RESTAURAR ORIGINAL', 'purple');
    restoreBtn.onclick = () => { matSys.restoreSnapshot(sel); this._renderTab('materials'); };
    restoreRow.appendChild(restoreBtn);
    body.appendChild(restoreRow);

    if (vals.hasTexture) {
      const note = this._el('div', 'wb-empty');
      note.style.marginTop = '4px';
      note.textContent = '⚠ El objeto tiene texturas. Los cambios de color se mezclan con la textura.';
      body.appendChild(note);
    }
  }

  // ─────────────────────────────────────────────────────────
  // TAB: LIGHTING
  // ─────────────────────────────────────────────────────────

  _renderLightingTab(body) {
    const L = this.builder.lightSystem;
    const v = L.getGlobalValues();
    const sel = this.builder.transformSystem.selectedEntry;

    body.appendChild(this._el('div', 'wb-sec-lbl', 'AMBIENT'));
    body.appendChild(this._colorCtrl('Color', v.ambientColor, c => L.setAmbientColor(c)));
    body.appendChild(this._rangeCtrl('Intensity', 0, 10, 0.05, v.ambientIntensity, val => L.setAmbientIntensity(val)));

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'SUNLIGHT (DIRECTIONAL)'));
    body.appendChild(this._colorCtrl('Color', v.sunColor, c => L.setSunColor(c)));
    body.appendChild(this._rangeCtrl('Intensity', 0, 20, 0.1, v.sunIntensity, val => L.setSunIntensity(val)));

    // Sun position
    ['X', 'Y', 'Z'].forEach((axis, i) => {
      const row = this._ctrlRow(`SUN ${axis}`, 'number');
      const inp = row.querySelector('input');
      inp.value = [v.sunX, v.sunY, v.sunZ][i];
      inp.step = '1';
      inp.onchange = () => {
        const x = parseFloat(document.querySelectorAll('.sun-pos-inp')[0]?.value) || v.sunX;
        const y = parseFloat(document.querySelectorAll('.sun-pos-inp')[1]?.value) || v.sunY;
        const z = parseFloat(document.querySelectorAll('.sun-pos-inp')[2]?.value) || v.sunZ;
        L.setSunPosition(x, y, z);
      };
      inp.classList.add('sun-pos-inp');
      body.appendChild(row);
    });

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'HEMISPHERE'));
    body.appendChild(this._colorCtrl('Sky', v.hemiSkyColor, c => L.setHemiSkyColor(c)));
    body.appendChild(this._colorCtrl('Ground', v.hemiGroundColor, c => L.setHemiGroundColor(c)));
    body.appendChild(this._rangeCtrl('Intensity', 0, 10, 0.05, v.hemiIntensity, val => L.setHemiIntensity(val)));

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'SHADOWS'));
    const shadowRow = this._el('div', 'wb-btn-row');
    const shadowBtn = this._pbtn(`☀ SHADOWS: ${v.shadowsEnabled ? 'ON' : 'OFF'}`, v.shadowsEnabled ? 'cyan on' : 'purple');
    shadowBtn.onclick = () => {
      const enabled = !L._shadowsEnabled;
      L.toggleShadows(enabled);
      shadowBtn.textContent = `☀ SHADOWS: ${enabled ? 'ON' : 'OFF'}`;
      shadowBtn.className = `wb-pbtn ${enabled ? 'cyan on' : 'purple'}`;
    };
    shadowRow.appendChild(shadowBtn);
    body.appendChild(shadowRow);

    // Shadow quality
    const sqRow = this._el('div', 'wb-btn-row');
    [512, 1024, 2048, 4096].forEach(size => {
      const btn = this._pbtn(`${size}`, 'purple');
      btn.style.fontSize = '7px';
      btn.onclick = () => { L.setShadowQuality(size); this.setStatus(`Shadow map: ${size}px`); };
      sqRow.appendChild(btn);
    });
    body.appendChild(this._el('div', 'wb-sec-lbl', 'SHADOW QUALITY'));
    body.appendChild(sqRow);

    // Per-object light
    if (sel) {
      body.appendChild(this._div());
      body.appendChild(this._el('div', 'wb-sec-lbl', 'OBJECT LIGHT'));
      const objLightVals = L.getObjectLightValues(sel);

      const addRow = this._el('div', 'wb-btn-row');
      const addPointBtn = this._pbtn('+ POINT', 'cyan');
      addPointBtn.onclick = () => { L.addObjectLight(sel, 'point'); this._renderTab('lighting'); };
      const addSpotBtn = this._pbtn('+ SPOT', 'purple');
      addSpotBtn.onclick = () => { L.addObjectLight(sel, 'spot'); this._renderTab('lighting'); };
      addRow.append(addPointBtn, addSpotBtn);
      body.appendChild(addRow);

      if (objLightVals) {
        body.appendChild(this._colorCtrl('Light Color', objLightVals.color, c => L.setObjectLightColor(sel, c)));
        body.appendChild(this._rangeCtrl('Intensity', 0, 10, 0.1, objLightVals.intensity, val => L.setObjectLightIntensity(sel, val)));
        body.appendChild(this._rangeCtrl('Distance', 1, 40, 0.5, objLightVals.distance, val => L.setObjectLightDistance(sel, val)));
        body.appendChild(this._rangeCtrl('Height', 0, 10, 0.1, objLightVals.height, val => L.setObjectLightHeight(sel, val)));

        // Save object light
        const saveLightSec = this._el('div', 'wb-save-section');
        saveLightSec.appendChild(this._saveBtn('💾 GUARDAR LUZ DEL OBJETO', () => {
          const lv = L.getObjectLightValues(sel);
          if (lv) this.builder.catalogSystem.patchObject(sel.id, { light_data: JSON.stringify(lv) });
        }));
        body.appendChild(saveLightSec);

        const removeRow = this._el('div', 'wb-btn-row');
        const removeBtn = this._pbtn('✕ ELIMINAR LUZ', 'red');
        removeBtn.onclick = () => { L.removeObjectLight(sel); this._renderTab('lighting'); };
        removeRow.appendChild(removeBtn);
        body.appendChild(removeRow);
      }
    }
  }

  // ─────────────────────────────────────────────────────────
  // TAB: SCENE
  // ─────────────────────────────────────────────────────────

  _renderSceneTab(body) {
    const cat  = this.builder.catalogSystem;
    const state = this.builder.stateManager;
    const perf = this.builder.perfManager;
    const lod  = this.builder.lodSystem;
    const inst = this.builder.instanceManager;

    body.appendChild(this._el('div', 'wb-sec-lbl', `SCENE OBJECTS: ${cat.getObjects().length}`));

    // Save / Export
    const saveRow = this._el('div', 'wb-btn-row');
    const saveBtn = this._pbtn('💾 SAVE SCENE', 'cyan');
    saveBtn.onclick = () => state.save();
    const exportBtn = this._pbtn('📤 EXPORT JSON', 'purple');
    exportBtn.onclick = () => state.exportJSON();
    saveRow.append(saveBtn, exportBtn);
    body.appendChild(saveRow);

    // Import
    const importRow = this._el('div', 'wb-btn-row');
    const importBtn = this._pbtn('📥 IMPORT JSON', 'purple');
    importBtn.onclick = () => {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = '.json';
      input.onchange = e => { if (e.target.files[0]) state.importFromFile(e.target.files[0]); };
      input.click();
    };
    importRow.appendChild(importBtn);
    body.appendChild(importRow);

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'GRID'));
    const gridRow = this._el('div', 'wb-btn-row');
    const gridBtn = this._pbtn(
      `◫ GRID SNAP: ${cat._gridSnap ? 'ON' : 'OFF'}`,
      cat._gridSnap ? 'cyan on' : 'purple'
    );
    gridBtn.id = 'wb-grid-toggle-btn';
    gridBtn.onclick = () => {
      const on = cat.toggleGrid();
      gridBtn.textContent = `◫ GRID SNAP: ${on ? 'ON' : 'OFF'}`;
      gridBtn.className = `wb-pbtn ${on ? 'cyan on' : 'purple'}`;
    };
    gridRow.appendChild(gridBtn);
    body.appendChild(gridRow);

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'SNAP MODE'));
    const snapRow = this._el('div', 'wb-btn-row');
    ['ground', 'surface', 'grid'].forEach(mode => {
      const cur = this.builder.surfaceSnap?.getMode() === mode;
      const btn = this._pbtn(mode.toUpperCase(), cur ? 'cyan on' : 'purple');
      btn.onclick = () => { this.builder.surfaceSnap.setMode(mode); this._renderTab('scene'); };
      snapRow.appendChild(btn);
    });
    body.appendChild(snapRow);

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'TOOLS'));
    const toolRow = this._el('div', 'wb-btn-row');
    const hierBtn = this._pbtn('🌳 HIERARCHY', this.builder.hierarchyPanel?.isOpen() ? 'cyan on' : 'purple');
    hierBtn.onclick = () => this.builder.hierarchyPanel.toggle();
    const terrBtn = this._pbtn('🏔 TERRAIN', this.builder.terrainTools?.isActive() ? 'cyan on' : 'purple');
    terrBtn.onclick = () => this.builder.terrainTools.isActive() ? this.builder.terrainTools.deactivate() : this.builder.terrainTools.activate();
    const mktBtn = this._pbtn('🛒 MARKET', 'purple');
    mktBtn.onclick = () => this.builder.marketplace.open();
    toolRow.append(hierBtn, terrBtn, mktBtn);
    body.appendChild(toolRow);

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'PERFORMANCE'));

    const statsEl = this._el('div', '');
    statsEl.style.cssText = 'font-size:8px;color:rgba(90,165,190,.5);line-height:1.9;';
    const s = perf.getStats();
    const instStats = inst?.getStats() || { groups: 0, totalInstanced: 0 };
    const lodStats  = lod?.getStats()  || { total: 0 };
    statsEl.innerHTML = [
      `FPS: ${s.fps}`,
      `Triangles: ${(s.triangles/1000).toFixed(1)}k`,
      `Draw calls: ${s.calls}`,
      `World objects: ${s.objects}`,
      instStats.groups > 0 ? `Instanced: ${instStats.totalInstanced}× (${instStats.groups} groups)` : '',
      lodStats.total > 0 ? `LOD objects: ${lodStats.total}` : '',
    ].filter(Boolean).map(l => `<div>${l}</div>`).join('');
    body.appendChild(statsEl);

    // LOD toggle
    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'OPTIMIZATIONS'));
    const optRow = this._el('div', 'wb-btn-row');
    const lodEnabled = lod?._enabled ?? true;
    const lodBtn = this._pbtn(`LOD: ${lodEnabled ? 'ON' : 'OFF'}`, lodEnabled ? 'cyan on' : 'purple');
    lodBtn.onclick = () => { lod?.setEnabled(!lodEnabled); this._renderTab('scene'); };
    optRow.appendChild(lodBtn);
    body.appendChild(optRow);
  }

  // ─────────────────────────────────────────────────────────
  // TAB: CAMERA
  // ─────────────────────────────────────────────────────────

  _renderCameraTab(body) {
    const oc = this.ctx.orbitControls;
    const sel = this.builder.transformSystem.selectedEntry;

    body.appendChild(this._el('div', 'wb-sec-lbl', 'ORBIT CONTROLS'));

    // Zoom limits
    body.appendChild(this._rangeCtrl('Min Zoom', 0.05, 1, 0.05, oc.minZoom, v => { oc.minZoom = v; }));
    body.appendChild(this._rangeCtrl('Max Zoom', 1, 8, 0.5, oc.maxZoom, v => { oc.maxZoom = v; }));

    // Damping
    body.appendChild(this._rangeCtrl('Damping', 0.01, 0.5, 0.01, oc.dampingFactor, v => { oc.dampingFactor = v; }));

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'ACTIONS'));

    const actionRow = this._el('div', 'wb-btn-row');

    if (sel) {
      const focusBtn = this._pbtn('⊙ FOCUS SELECTED', 'cyan');
      focusBtn.onclick = () => this.builder.transformSystem.focusSelected();
      actionRow.appendChild(focusBtn);
    }

    const resetBtn = this._pbtn('↺ RESET CAMERA', 'purple');
    resetBtn.onclick = () => {
      oc.target.set(0, 0.6, 0);
      oc.update();
      this.setStatus('Camera reset to center.');
    };
    actionRow.appendChild(resetBtn);
    body.appendChild(actionRow);

    // Save / restore camera position
    const savedCam = this._getSavedCam();
    const camRow = this._el('div', 'wb-btn-row');
    const saveCamBtn = this._pbtn('📍 SAVE POS', 'purple');
    saveCamBtn.onclick = () => {
      const d = {
        tx: oc.target.x, ty: oc.target.y, tz: oc.target.z,
        px: this.ctx.cam.position.x, py: this.ctx.cam.position.y, pz: this.ctx.cam.position.z,
        zoom: this.ctx.cam.zoom,
      };
      try { localStorage.setItem('nexus-wb-cam', JSON.stringify(d)); } catch (_) {}
      this.setStatus('Camera position saved.');
    };
    const loadCamBtn = this._pbtn('📍 LOAD POS', savedCam ? 'cyan' : 'purple');
    loadCamBtn.onclick = () => {
      const d = this._getSavedCam();
      if (!d) { this.setStatus('No saved camera position.'); return; }
      oc.target.set(d.tx, d.ty, d.tz);
      this.ctx.cam.position.set(d.px, d.py, d.pz);
      this.ctx.cam.zoom = d.zoom;
      this.ctx.cam.updateProjectionMatrix();
      oc.update();
      this.setStatus('Camera position restored.');
    };
    camRow.append(saveCamBtn, loadCamBtn);
    body.appendChild(camRow);

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'INFO'));
    const infoEl = this._el('div', '');
    infoEl.style.cssText = 'font-size:8px;color:rgba(90,165,190,.4);line-height:1.8;';
    const cam = this.ctx.cam;
    infoEl.innerHTML = `
      <div>Type: Orthographic</div>
      <div>Zoom: ${cam.zoom.toFixed(2)}x</div>
      <div>Target: ${oc.target.x.toFixed(1)}, ${oc.target.y.toFixed(1)}, ${oc.target.z.toFixed(1)}</div>
    `;
    body.appendChild(infoEl);
  }

  // ─────────────────────────────────────────────────────────
  // TAB: ENVIRONMENT (HDRI, fog, background)
  // ─────────────────────────────────────────────────────────

  _renderEnvironmentTab(body) {
    const env = this.builder.envSystem;
    const v   = env.getValues();

    body.appendChild(this._el('div', 'wb-sec-lbl', 'HDRI PRESETS'));

    // Preset buttons grid
    const presetGrid = this._el('div', '');
    presetGrid.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px;';
    ENVIRONMENT_PRESETS.forEach(preset => {
      const btn = this._pbtn(preset.label, v.preset === preset.id ? 'cyan on' : 'purple');
      btn.onclick = async () => {
        await env.loadPreset(preset.id);
        this._renderTab('environment');
      };
      presetGrid.appendChild(btn);
    });
    body.appendChild(presetGrid);

    // Custom URL
    body.appendChild(this._el('div', 'wb-sec-lbl', 'CUSTOM HDR URL'));
    const urlRow = this._el('div', 'wb-ctrl-row');
    const urlInp = document.createElement('input');
    urlInp.type = 'text'; urlInp.className = 'wb-ctrl-inp';
    urlInp.placeholder = 'https://…/scene.hdr';
    const loadBtn = this._pbtn('LOAD', 'cyan');
    loadBtn.style.flexShrink = '0';
    loadBtn.onclick = () => { if (urlInp.value.trim()) env.loadCustomHDR(urlInp.value.trim()); };
    urlRow.append(urlInp, loadBtn);
    body.appendChild(urlRow);

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'ENVIRONMENT INTENSITY'));
    body.appendChild(this._rangeCtrl('Intensity', 0, 5, 0.05, v.envIntensity, val => env.setEnvIntensity(val)));

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'BACKGROUND'));
    const bgRow = this._el('div', 'wb-btn-row');
    ['original','hdri','color'].forEach(mode => {
      const btn = this._pbtn(mode.toUpperCase(), v.bgMode === mode ? 'cyan on' : 'purple');
      btn.onclick = () => { env.setBackgroundMode(mode); this._renderTab('environment'); };
      bgRow.appendChild(btn);
    });
    body.appendChild(bgRow);

    if (v.bgMode === 'color') {
      body.appendChild(this._colorCtrl('BG Color', v.bgColor, c => env.setBackgroundColor(c)));
    }

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'FOG'));
    const fogRow = this._el('div', 'wb-btn-row');
    const fogBtn = this._pbtn(`🌫 FOG: ${v.fogEnabled ? 'ON' : 'OFF'}`, v.fogEnabled ? 'cyan on' : 'purple');
    fogBtn.onclick = () => { env.setFogEnabled(!v.fogEnabled); this._renderTab('environment'); };
    fogRow.appendChild(fogBtn);
    body.appendChild(fogRow);

    if (v.fogEnabled) {
      body.appendChild(this._colorCtrl('Fog Color', v.fogColor, c => env.setFogColor(c)));
      body.appendChild(this._rangeCtrl('Density', 0, 0.05, 0.001, v.fogDensity, val => env.setFogDensity(val)));
    }

    if (v.loading) {
      const loadEl = this._el('div', 'wb-empty', '⏳ Loading HDR…');
      body.appendChild(loadEl);
    }
  }

  // ─────────────────────────────────────────────────────────
  // TAB: SCENE (extended with new AAA features)
  // ─────────────────────────────────────────────────────────

  _getSavedCam() {
    try { return JSON.parse(localStorage.getItem('nexus-wb-cam') || 'null'); } catch (_) { return null; }
  }

  // ─────────────────────────────────────────────────────────
  // UI HELPERS
  // ─────────────────────────────────────────────────────────

  _el(tag, cls, text = '') {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    if (text) el.textContent = text;
    return el;
  }

  _div() { return this._el('div', 'wb-div'); }

  _pbtn(label, cls) {
    const el = this._el('div', `wb-pbtn ${cls}`);
    el.textContent = label;
    return el;
  }

  _emptyState(text) {
    const el = this._el('div', 'wb-empty');
    el.textContent = text;
    return el;
  }

  _ctrlRow(label, type) {
    const row = this._el('div', 'wb-ctrl-row');
    const lbl = this._el('span', 'wb-ctrl-lbl', label);
    const inp = document.createElement('input');
    inp.type = type;
    inp.className = 'wb-ctrl-inp';
    row.append(lbl, inp);
    return row;
  }

  _colorCtrl(label, value, onChange) {
    const row = this._el('div', 'wb-ctrl-row');
    const lbl = this._el('span', 'wb-ctrl-lbl', label);
    const inp = document.createElement('input');
    inp.type = 'color';
    inp.className = 'wb-color';
    inp.value = value;
    const hexInp = document.createElement('input');
    hexInp.type = 'text';
    hexInp.className = 'wb-ctrl-inp';
    hexInp.value = value;
    hexInp.maxLength = 7;
    inp.oninput = () => { hexInp.value = inp.value; onChange(inp.value); };
    hexInp.onchange = () => {
      const v = hexInp.value.startsWith('#') ? hexInp.value : '#' + hexInp.value;
      inp.value = v; onChange(v);
    };
    row.append(lbl, inp, hexInp);
    return row;
  }

  _rangeCtrl(label, min, max, step, value, onChange) {
    const row = this._el('div', 'wb-ctrl-row');
    const lbl = this._el('span', 'wb-ctrl-lbl', label);
    const range = document.createElement('input');
    range.type = 'range';
    range.className = 'wb-range';
    range.min = min; range.max = max; range.step = step; range.value = value;
    const badge = this._el('span', 'wb-val-badge', String(Number(value).toFixed(2)));
    range.oninput = () => {
      badge.textContent = Number(range.value).toFixed(2);
      onChange(Number(range.value));
    };
    row.append(lbl, range, badge);
    return row;
  }

  // ─────────────────────────────────────────────────────────
  // SAVE HELPERS
  // ─────────────────────────────────────────────────────────

  /**
   * Creates a styled green save button with visual confirmation feedback.
   * @param {string} label    — button label
   * @param {Function} onSave — called on click; receives the button element
   */
  _saveBtn(label, onSave) {
    const btn = document.createElement('div');
    btn.className = 'wb-save-btn';
    btn.textContent = label;
    btn.onclick = () => {
      onSave(btn);
      this._saveFeedback(btn, label);
    };
    return btn;
  }

  /** Flash the button green with "✓ GUARDADO" for 2 seconds. */
  _saveFeedback(btn, originalLabel) {
    btn.textContent = '✓ GUARDADO';
    btn.classList.add('saved');
    clearTimeout(btn._saveTimer);
    btn._saveTimer = setTimeout(() => {
      btn.textContent = originalLabel;
      btn.classList.remove('saved');
    }, 2000);
  }

  /** Save ALL pending changes for selected object.
   *
   * NOTE: transform is NOT applied here — the gizmo's mouseUp and the
   * numeric input onchange handlers already persist transforms in real-time.
   * Applying transforms from the global bar is dangerous because the inputs
   * only exist in the DOM when the Objects tab is active; calling
   * _applyTransform() from any other tab would reset position to (0,0,0).
   */
  _saveAllForSelected(btn) {
    const sel = this.builder.transformSystem.selectedEntry;
    if (!sel) return;

    // Only apply transform if the user is on the Objects tab
    // (inputs exist in the DOM) — avoids resetting position to 0,0,0
    if (this._activeTab === 'objects' && document.getElementById('wb-pos-x')) {
      this._applyTransform();
    }

    // Flush material immediately (bypass 600ms debounce)
    this.builder.materialSystem.flushEntry(sel);

    // Lights are already persisted on each setter call

    this._saveFeedback(btn, '💾 GUARDAR CAMBIOS AL OBJETO');
    this.setStatus(`✓ Cambios guardados: ${sel.item_id}`);
    this.ctx.fireEv('💾', sel.item_id, 'cambios guardados', 'rgba(0,255,136,.65)');
  }

  /** Show / hide the sticky save bar based on whether an object is selected. */
  _refreshSaveBar(entry) {
    const bar  = document.getElementById('wb-save-bar');
    const name = document.getElementById('wb-save-bar-name');
    const btn  = document.getElementById('wb-save-bar-btn');
    if (!bar) return;
    if (entry) {
      const catEntry = this.builder.catalogSystem.findCatalogEntry(entry.item_id);
      // Double approach: class + inline style to survive CSS specificity issues
      bar.classList.add('visible');
      bar.style.display = 'block';
      if (name) name.textContent = (catEntry?.name || entry.item_id).toUpperCase();
      if (btn)  btn.classList.remove('saved');
    } else {
      bar.classList.remove('visible');
      bar.style.display = 'none';
    }
  }

  // ─────────────────────────────────────────────────────────
  // PUBLIC API CALLED BY OTHER SYSTEMS
  // ─────────────────────────────────────────────────────────

  show() { this._panel.classList.add('open'); }
  hide() { this._panel.classList.remove('open'); }

  setStatus(msg) {
    if (this._statusEl) this._statusEl.textContent = msg;
  }

  updateStats(text) {
    if (this._statsEl) this._statsEl.textContent = text;
  }

  onObjectSelected(entry) {
    this._refreshSaveBar(entry);
    if (this._activeTab === 'objects' || this._activeTab === 'materials') {
      this._renderTab(this._activeTab);
    } else {
      this._renderTab('objects');
    }
  }

  onObjectDeselected() {
    this._refreshSaveBar(null);
    this._renderTab(this._activeTab);
  }

  refreshObjectsTab() {
    if (this._activeTab === 'objects') this._renderTab('objects');
  }

  refreshMaterialsTab() {
    if (this._activeTab === 'materials') this._renderTab('materials');
  }

  refreshLightingTab() {
    if (this._activeTab === 'lighting') this._renderTab('lighting');
  }

  refreshSceneTab() {
    if (this._activeTab === 'scene') this._renderTab('scene');
  }

  refreshTransformInputs() {
    const sel = this.builder.transformSystem.selectedEntry;
    if (!sel) return;
    const { mesh } = sel;
    const update = (id, val) => { const el = document.getElementById(id); if (el) el.value = val.toFixed(3); };
    update('wb-pos-x', mesh.position.x);
    update('wb-pos-y', mesh.position.y);
    update('wb-pos-z', mesh.position.z);
    update('wb-rot-y', mesh.rotation.y);
    update('wb-scale', mesh.scale.x);
  }

  refreshGizmoModeButtons(mode) {
    document.querySelectorAll('.wb-gizmo-btn').forEach(btn => {
      btn.classList.toggle('on', btn.dataset.mode === mode);
    });
  }

  // ─────────────────────────────────────────────────────────
  // UNDO/REDO UI
  // ─────────────────────────────────────────────────────────

  refreshUndoRedoState(canUndo, canRedo) {
    const undoBtn = document.getElementById('wb-undo-btn');
    const redoBtn = document.getElementById('wb-redo-btn');
    if (undoBtn) undoBtn.style.opacity = canUndo ? '1' : '0.3';
    if (redoBtn) redoBtn.style.opacity = canRedo ? '1' : '0.3';
  }

  // ─────────────────────────────────────────────────────────
  // MULTI-SELECT UI
  // ─────────────────────────────────────────────────────────

  onMultiSelectChanged(count) {
    const el = document.getElementById('wb-multisel-badge');
    if (!el) return;
    if (count > 1) {
      el.textContent = `${count} SELECTED`;
      el.style.display = 'block';
    } else {
      el.style.display = 'none';
    }
    if (this._activeTab === 'objects') this._renderTab('objects');
  }

  // ─────────────────────────────────────────────────────────
  // COLLAB PEERS
  // ─────────────────────────────────────────────────────────

  refreshCollabPeers(peers) {
    const el = document.getElementById('wb-collab-count');
    if (!el) return;
    const count = peers.size;
    el.textContent = count > 0 ? `🤝 ${count} ONLINE` : '';
    el.style.color = count > 0 ? '#00ff88' : 'transparent';
  }

  // ─────────────────────────────────────────────────────────
  // SNAP MODE INDICATOR
  // ─────────────────────────────────────────────────────────

  refreshSnapMode() {
    const el = document.getElementById('wb-snap-indicator');
    if (!el) return;
    const mode = this.builder.surfaceSnap.getMode();
    el.textContent = `SNAP: ${mode.toUpperCase()}`;
  }
}
