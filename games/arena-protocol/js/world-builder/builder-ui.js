/**
 * BuilderUI — injects and manages the full left-panel editor UI.
 * Tabs: Objects | Materials | Lighting | Scene | Camera
 * All UI is generated via JS — zero HTML changes required in nexus-city.html.
 */

const TABS = ['objects', 'materials', 'lighting', 'scene', 'camera'];

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
    if (document.getElementById('wb-pro-styles')) return;
    const style = document.createElement('style');
    style.id = 'wb-pro-styles';
    style.textContent = `
/* ── WB PRO PANEL ── */
#wb-pro{
  position:fixed;left:0;top:48px;bottom:56px;
  width:min(300px,42vw);max-width:100%;
  z-index:60;
  background:rgba(3,6,18,.97);
  border-right:1px solid rgba(155,48,255,.2);
  backdrop-filter:blur(18px);
  display:flex;flex-direction:column;
  font-family:"Share Tech Mono",monospace;
  transform:translateX(-100%);
  transition:transform .28s cubic-bezier(.2,.8,.2,1);
  overflow:hidden;
}
#wb-pro.open{ transform:translateX(0); }

/* Header */
.wb-pro-hdr{
  padding:10px 12px 8px;
  border-bottom:1px solid rgba(155,48,255,.15);
  display:flex;align-items:center;gap:8px;flex-shrink:0;
}
.wb-pro-badge{
  background:rgba(155,48,255,.18);border:1px solid rgba(155,48,255,.5);
  border-radius:4px;padding:2px 7px;
  font-size:8px;font-weight:700;letter-spacing:.14em;color:#c158ff;
  font-family:"Orbitron",sans-serif;
}
.wb-pro-title{
  font-family:"Orbitron",sans-serif;font-size:10px;font-weight:700;
  letter-spacing:.12em;color:rgba(200,175,255,.95);
}
.wb-pro-close{
  margin-left:auto;width:20px;height:20px;border-radius:50%;
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:9px;color:rgba(255,255,255,.35);
  transition:all .15s;flex-shrink:0;
}
.wb-pro-close:hover{ background:rgba(255,61,86,.12);color:#ff3d56;border-color:rgba(255,61,86,.3); }

/* Tabs */
.wb-pro-tabs{
  display:flex;border-bottom:1px solid rgba(155,48,255,.1);flex-shrink:0;
  overflow-x:auto;
}
.wb-pro-tabs::-webkit-scrollbar{ height:0; }
.wb-pro-tab{
  flex:1;min-width:50px;padding:6px 4px;
  text-align:center;cursor:pointer;
  font-size:6.5px;letter-spacing:.1em;color:rgba(90,165,190,.4);
  border-bottom:2px solid transparent;
  transition:all .15s;white-space:nowrap;
  font-family:"Orbitron",sans-serif;
}
.wb-pro-tab:hover{ color:rgba(155,215,235,.65);background:rgba(255,255,255,.02); }
.wb-pro-tab.on{
  color:#c158ff;border-bottom-color:rgba(155,48,255,.7);
  background:rgba(155,48,255,.06);
}
.wb-pro-tab-icon{ font-size:11px;display:block;margin-bottom:2px; }

/* Content */
.wb-pro-content{
  flex:1;overflow-y:auto;overflow-x:hidden;padding:10px 12px;
  display:flex;flex-direction:column;gap:10px;
}
.wb-pro-content::-webkit-scrollbar{ width:4px; }
.wb-pro-content::-webkit-scrollbar-thumb{ background:rgba(155,48,255,.25);border-radius:2px; }

/* Section */
.wb-sec-lbl{
  font-size:7px;letter-spacing:.2em;color:rgba(155,100,255,.5);
  margin-bottom:5px;text-transform:uppercase;
}

/* Catalog grid */
.wb-catalog-grid{ display:grid;grid-template-columns:repeat(2,1fr);gap:7px; }
.wb-cat-item{
  background:rgba(155,48,255,.06);border:1px solid rgba(155,48,255,.14);
  border-radius:6px;padding:8px 5px;cursor:pointer;text-align:center;
  transition:all .18s;
}
.wb-cat-item:hover{
  background:rgba(155,48,255,.14);border-color:rgba(155,48,255,.38);
  transform:translateY(-1px);
}
.wb-cat-item.on{
  background:rgba(155,48,255,.22);border-color:#9b30ff;
  box-shadow:0 0 10px rgba(155,48,255,.35);
}
.wb-cat-icon{ font-size:20px;line-height:1.2; }
.wb-cat-name{
  font-size:8.5px;color:rgba(210,190,255,.75);margin-top:4px;
  line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;
  -webkit-box-orient:vertical;overflow:hidden;word-break:break-word;
}

/* Row control */
.wb-ctrl-row{
  display:flex;align-items:center;gap:6px;
}
.wb-ctrl-lbl{
  font-size:7.5px;color:rgba(155,215,235,.4);width:65px;flex-shrink:0;
  letter-spacing:.06em;
}
.wb-ctrl-inp{
  flex:1;background:rgba(0,0,0,.35);border:1px solid rgba(0,232,255,.14);
  border-radius:4px;padding:4px 7px;
  font-family:"Share Tech Mono",monospace;font-size:10px;
  color:rgba(200,225,255,.85);outline:none;width:0;min-width:0;
}
.wb-ctrl-inp:focus{ border-color:rgba(0,232,255,.4); }
input[type=range].wb-range{
  -webkit-appearance:none;flex:1;height:3px;
  background:rgba(255,255,255,.08);border-radius:2px;cursor:pointer;
  accent-color:#9b30ff;outline:none;
}
input[type=range].wb-range::-webkit-slider-thumb{
  -webkit-appearance:none;width:12px;height:12px;border-radius:50%;
  background:#c158ff;border:2px solid rgba(155,48,255,.5);
  box-shadow:0 0 6px rgba(155,48,255,.4);
}
input[type=color].wb-color{
  -webkit-appearance:none;width:24px;height:24px;border-radius:4px;
  border:1px solid rgba(0,232,255,.2);background:none;cursor:pointer;
  padding:1px;flex-shrink:0;
}
.wb-val-badge{
  font-size:8.5px;color:rgba(0,232,255,.6);width:32px;
  text-align:right;flex-shrink:0;letter-spacing:0;
}

/* Buttons */
.wb-btn-row{ display:flex;gap:6px; }
.wb-pbtn{
  flex:1;padding:7px 6px;border-radius:5px;cursor:pointer;
  font-family:"Orbitron",sans-serif;font-size:7.5px;font-weight:700;
  letter-spacing:.1em;text-align:center;border:1px solid;
  transition:all .15s;user-select:none;
}
.wb-pbtn.cyan{
  background:rgba(0,232,255,.07);border-color:rgba(0,232,255,.28);
  color:#00e8ff;
}
.wb-pbtn.cyan:hover{ background:rgba(0,232,255,.15); }
.wb-pbtn.cyan.on{ background:rgba(0,232,255,.18);box-shadow:0 0 8px rgba(0,232,255,.2); }
.wb-pbtn.purple{
  background:rgba(155,48,255,.08);border-color:rgba(155,48,255,.3);
  color:#c158ff;
}
.wb-pbtn.purple:hover{ background:rgba(155,48,255,.18); }
.wb-pbtn.purple.on{ background:rgba(155,48,255,.2);box-shadow:0 0 8px rgba(155,48,255,.25); }
.wb-pbtn.red{
  background:rgba(255,61,86,.07);border-color:rgba(255,61,86,.28);
  color:#ff3d56;
}
.wb-pbtn.red:hover{ background:rgba(255,61,86,.15); }
.wb-pbtn.green{
  background:rgba(0,255,136,.07);border-color:rgba(0,255,136,.28);
  color:#00ff88;
}
.wb-pbtn.green:hover{ background:rgba(0,255,136,.15); }

/* Selected info box */
.wb-sel-box{
  background:rgba(155,48,255,.06);border:1px solid rgba(155,48,255,.18);
  border-radius:6px;padding:8px 10px;
}
.wb-sel-name{
  font-family:"Orbitron",sans-serif;font-size:9px;font-weight:700;
  letter-spacing:.08em;color:rgba(210,190,255,.9);
  margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}

/* Transform gizmo mode buttons */
.wb-gizmo-row{ display:flex;gap:5px;margin-bottom:4px; }
.wb-gizmo-btn{
  flex:1;padding:6px 4px;border-radius:4px;cursor:pointer;
  font-size:7px;font-family:"Orbitron",sans-serif;letter-spacing:.08em;
  border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);
  color:rgba(155,215,235,.4);transition:all .15s;text-align:center;
}
.wb-gizmo-btn.on{
  background:rgba(0,232,255,.1);border-color:rgba(0,232,255,.35);color:#00e8ff;
}
.wb-gizmo-btn:hover:not(.on){ color:rgba(155,215,235,.7);background:rgba(255,255,255,.05); }

/* Divider */
.wb-div{
  height:1px;background:rgba(255,255,255,.05);margin:4px -12px;
  flex-shrink:0;
}

/* Status bar */
.wb-pro-status{
  padding:7px 12px;border-top:1px solid rgba(155,48,255,.1);
  font-size:8px;color:rgba(155,180,255,.55);line-height:1.5;
  word-break:break-word;flex-shrink:0;min-height:38px;
}

/* Stats bar */
.wb-pro-stats{
  padding:2px 12px;font-size:7px;color:rgba(90,165,190,.3);
  letter-spacing:.05em;border-top:1px solid rgba(0,0,0,.3);
  flex-shrink:0;
}

/* Key hint row */
.wb-key-row{ display:flex;align-items:center;gap:7px;padding:2px 0; }
.wb-k{
  display:inline-flex;align-items:center;justify-content:center;
  min-width:22px;height:17px;padding:0 4px;border-radius:3px;
  font-size:7.5px;border:1px solid rgba(155,48,255,.38);
  background:rgba(40,10,80,.5);color:rgba(200,150,255,.9);
  font-family:"Share Tech Mono",monospace;
}
.wb-kl{ font-size:7.5px;color:rgba(155,120,200,.6);letter-spacing:.04em; }

/* Empty state */
.wb-empty{
  text-align:center;padding:20px 10px;
  font-size:8.5px;color:rgba(90,165,190,.3);line-height:1.7;
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
  <div class="wb-pro-close" id="wb-pro-close">✕</div>
</div>
<div class="wb-pro-tabs" id="wb-pro-tabs"></div>
<div class="wb-pro-content" id="wb-pro-body"></div>
<div class="wb-pro-status" id="wb-pro-status">Builder active.</div>
<div class="wb-pro-stats" id="wb-pro-stats"></div>
`;
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
      { id: 'objects',   icon: '🧱', label: 'OBJECTS'   },
      { id: 'materials', icon: '🎨', label: 'MATERIALS'  },
      { id: 'lighting',  icon: '💡', label: 'LIGHTING'   },
      { id: 'scene',     icon: '🌐', label: 'SCENE'      },
      { id: 'camera',    icon: '📷', label: 'CAMERA'     },
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
      case 'objects':   this._renderObjectsTab(body);   break;
      case 'materials': this._renderMaterialsTab(body); break;
      case 'lighting':  this._renderLightingTab(body);  break;
      case 'scene':     this._renderSceneTab(body);     break;
      case 'camera':    this._renderCameraTab(body);    break;
    }
  }

  // ─────────────────────────────────────────────────────────
  // TAB: OBJECTS
  // ─────────────────────────────────────────────────────────

  _renderObjectsTab(body) {
    const catalog = this.builder.catalogSystem;
    const sel = this.builder.transformSystem.selectedEntry;

    // — Selected object info —
    if (sel) {
      const { mesh, item_id } = sel;
      const catEntry = catalog.findCatalogEntry(item_id);
      const name = catEntry?.name || item_id;

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

      body.appendChild(selBox);

      // Action buttons
      const actionRow = this._el('div', 'wb-btn-row');
      const dupBtn = this._pbtn('📋 DUPLICATE', 'cyan');
      dupBtn.onclick = () => catalog.duplicateObject(sel);
      const focusBtn = this._pbtn('⊙ FOCUS', 'purple');
      focusBtn.onclick = () => this.builder.transformSystem.focusSelected();
      const delBtn = this._pbtn('✕ DELETE', 'red');
      delBtn.onclick = () => { this.builder.transformSystem.deselect(); catalog.deleteObject(sel); };
      actionRow.append(dupBtn, focusBtn, delBtn);
      body.appendChild(actionRow);

      body.appendChild(this._div());
    } else {
      const hint = this._el('div', 'wb-empty');
      hint.textContent = 'No object selected.\nClick an object in the scene to select it, or choose from catalog below.';
      body.appendChild(hint);
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

    // Base color
    body.appendChild(this._colorCtrl('Base Color', vals.color, v => matSys.setBaseColor(sel, v)));

    // Emissive color + intensity
    body.appendChild(this._colorCtrl('Emissive', vals.emissive, v => matSys.setEmissiveColor(sel, v)));
    body.appendChild(this._rangeCtrl('Emit Intens', 0, 5, 0.01, vals.emissiveIntensity, v => {
      matSys.setEmissiveIntensity(sel, v);
    }));

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'PBR'));
    body.appendChild(this._rangeCtrl('Metalness', 0, 1, 0.01, vals.metalness, v => matSys.setMetalness(sel, v)));
    body.appendChild(this._rangeCtrl('Roughness', 0, 1, 0.01, vals.roughness, v => matSys.setRoughness(sel, v)));

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'TRANSPARENCY'));
    body.appendChild(this._rangeCtrl('Opacity', 0, 1, 0.01, vals.opacity, v => matSys.setOpacity(sel, v)));

    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'DISPLAY'));

    // Wireframe toggle
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

    body.appendChild(this._div());

    // Override to MeshStandard
    body.appendChild(this._el('div', 'wb-sec-lbl', 'OVERRIDE (DESTRUCTIVE)'));
    const overrideRow = this._el('div', 'wb-btn-row');
    const overrideBtn = this._pbtn('⚠ REPLACE WITH STANDARD MAT', 'red');
    overrideBtn.onclick = () => {
      if (!confirm('Replace all materials with a new MeshStandardMaterial? This will remove all textures.')) return;
      matSys.overrideWithStandard(sel);
      this._renderTab('materials');
    };
    overrideRow.appendChild(overrideBtn);
    body.appendChild(overrideRow);

    // Restore
    const restoreRow = this._el('div', 'wb-btn-row');
    const restoreBtn = this._pbtn('↺ RESTORE SNAPSHOT', 'purple');
    restoreBtn.onclick = () => { matSys.restoreSnapshot(sel); this._renderTab('materials'); };
    restoreRow.appendChild(restoreBtn);
    body.appendChild(restoreRow);

    if (vals.hasTexture) {
      const note = this._el('div', 'wb-empty');
      note.style.marginTop = '4px';
      note.textContent = '⚠ Object has textures. Color edits blend with texture.';
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

        const removeRow = this._el('div', 'wb-btn-row');
        const removeBtn = this._pbtn('✕ REMOVE LIGHT', 'red');
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
    const cat = this.builder.catalogSystem;
    const state = this.builder.stateManager;
    const perf = this.builder.perfManager;

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
    body.appendChild(this._el('div', 'wb-sec-lbl', 'PERFORMANCE'));

    const statsEl = this._el('div', '');
    statsEl.style.cssText = 'font-size:8px;color:rgba(90,165,190,.5);line-height:1.9;';
    const s = perf.getStats();
    statsEl.innerHTML = [
      `FPS: ${s.fps}`,
      `Triangles: ${(s.triangles/1000).toFixed(1)}k`,
      `Draw calls: ${s.calls}`,
      `Geometries: ${s.geometries}`,
      `Textures: ${s.textures}`,
      `World objects: ${s.objects}`,
    ].map(l => `<div>${l}</div>`).join('');
    body.appendChild(statsEl);

    // Fog toggle
    body.appendChild(this._div());
    body.appendChild(this._el('div', 'wb-sec-lbl', 'ATMOSPHERE'));
    const fogRow = this._el('div', 'wb-btn-row');
    const hasFog = !!this.ctx.scene.fog;
    const fogBtn = this._pbtn(`🌫 FOG: ${hasFog ? 'ON' : 'OFF'}`, hasFog ? 'cyan on' : 'purple');
    fogBtn.onclick = () => {
      if (this.ctx.scene.fog) {
        this.ctx.scene.userData._savedFog = this.ctx.scene.fog;
        this.ctx.scene.fog = null;
        fogBtn.textContent = '🌫 FOG: OFF';
        fogBtn.className = 'wb-pbtn purple';
      } else {
        this.ctx.scene.fog = this.ctx.scene.userData._savedFog || null;
        fogBtn.textContent = '🌫 FOG: ON';
        fogBtn.className = 'wb-pbtn cyan on';
      }
    };
    fogRow.appendChild(fogBtn);
    body.appendChild(fogRow);
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
    if (this._activeTab === 'objects' || this._activeTab === 'materials') {
      this._renderTab(this._activeTab);
    } else {
      this._renderTab('objects');
    }
  }

  onObjectDeselected() {
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
}
