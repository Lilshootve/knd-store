/**
 * Marketplace — in-panel asset browser with search, category filter,
 * server-side pagination, rarity badges, and 3D mini-preview.
 *
 * Renders as a modal overlay on top of the builder panel.
 * Talks to /api/nexus/world_builder_catalog.php (catálogo 3D admin, no Sanctum).
 * using ?search=&category=&page=&limit= query params.
 */
import * as THREE from 'three';

const PAGE_SIZE    = 12;
const RARITY_COLOR = { legendary:'#ffd600', epic:'#c158ff', rare:'#4488ff', special:'#00ffcc', common:'#44aaaa' };

export class Marketplace {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder     = builder;
    this.ctx         = builder.ctx;

    this._isOpen     = false;
    this._page       = 1;
    this._totalPages = 1;
    this._results    = [];
    this._query      = '';
    this._category   = '';
    this._loading    = false;

    this._panel      = null;
    this._previewRenderer = null;
    this._previewScene    = null;
    this._previewCam      = null;
    this._previewObj      = null;
    this._previewRafId    = null;

    this._injectStyles();
    this._buildPanel();
  }

  // ─────────────────────────────────────────────────────────
  // PANEL CONSTRUCTION
  // ─────────────────────────────────────────────────────────

  _injectStyles() {
    if (document.getElementById('wb-market-styles')) return;
    const s = document.createElement('style');
    s.id = 'wb-market-styles';
    s.textContent = `
#wb-market-overlay{
  position:fixed;inset:0;z-index:200;
  background:rgba(0,0,0,.72);backdrop-filter:blur(12px);
  display:none;align-items:center;justify-content:center;
}
#wb-market-overlay.open{ display:flex; }
#wb-market-panel{
  width:min(820px,96vw);max-height:90vh;
  background:rgba(3,6,20,.98);border:1px solid rgba(0,232,255,.18);
  border-radius:10px;display:flex;flex-direction:column;overflow:hidden;
  box-shadow:0 0 80px rgba(0,232,255,.08);
  font-family:"Share Tech Mono",monospace;
}
.wbm-hdr{
  padding:14px 18px;border-bottom:1px solid rgba(0,232,255,.1);
  display:flex;align-items:center;gap:12px;
}
.wbm-title{
  font-family:"Orbitron",sans-serif;font-size:11px;font-weight:900;
  letter-spacing:.2em;color:#00e8ff;
}
.wbm-close{
  margin-left:auto;width:24px;height:24px;border-radius:50%;
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;color:rgba(255,255,255,.4);font-size:11px;
  transition:all .15s;
}
.wbm-close:hover{background:rgba(255,61,86,.12);color:#ff3d56;}
.wbm-toolbar{
  padding:10px 18px;border-bottom:1px solid rgba(0,232,255,.07);
  display:flex;gap:10px;align-items:center;flex-wrap:wrap;
}
.wbm-search{
  flex:1;min-width:160px;background:rgba(0,0,0,.4);
  border:1px solid rgba(0,232,255,.2);border-radius:5px;
  padding:6px 12px;font-family:"Share Tech Mono",monospace;
  font-size:11px;color:rgba(200,225,255,.9);outline:none;
}
.wbm-search:focus{border-color:rgba(0,232,255,.5);}
.wbm-cats{display:flex;gap:5px;flex-wrap:wrap;}
.wbm-cat{
  padding:4px 10px;border-radius:4px;cursor:pointer;
  font-size:7.5px;letter-spacing:.1em;
  border:1px solid rgba(255,255,255,.08);
  background:rgba(255,255,255,.03);color:rgba(155,215,235,.4);
  transition:all .15s;font-family:"Orbitron",sans-serif;
}
.wbm-cat:hover{color:rgba(155,215,235,.75);background:rgba(255,255,255,.07);}
.wbm-cat.on{background:rgba(0,232,255,.1);border-color:rgba(0,232,255,.35);color:#00e8ff;}
.wbm-body{display:flex;flex:1;min-height:0;}
.wbm-grid-wrap{flex:1;overflow-y:auto;padding:14px 18px;}
.wbm-grid-wrap::-webkit-scrollbar{width:4px;}
.wbm-grid-wrap::-webkit-scrollbar-thumb{background:rgba(0,232,255,.15);}
.wbm-grid{
  display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;
}
.wbm-card{
  background:rgba(0,232,255,.04);border:1px solid rgba(0,232,255,.1);
  border-radius:7px;padding:10px 8px;cursor:pointer;text-align:center;
  transition:all .18s;position:relative;
}
.wbm-card:hover{
  background:rgba(0,232,255,.1);border-color:rgba(0,232,255,.35);
  transform:translateY(-2px);box-shadow:0 4px 20px rgba(0,232,255,.12);
}
.wbm-card.on{background:rgba(155,48,255,.2);border-color:#9b30ff;}
.wbm-card-icon{font-size:28px;line-height:1.3;margin-bottom:4px;}
.wbm-card-name{
  font-size:8.5px;color:rgba(200,225,255,.8);line-height:1.3;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
}
.wbm-card-rarity{
  position:absolute;top:6px;right:6px;
  font-size:7px;padding:2px 5px;border-radius:3px;
  font-family:"Orbitron",sans-serif;letter-spacing:.08em;
  border:1px solid currentColor;opacity:.8;
}
.wbm-card-cat{
  font-size:6.5px;color:rgba(90,165,190,.4);margin-top:3px;letter-spacing:.06em;
}
.wbm-preview{
  width:200px;flex-shrink:0;
  border-left:1px solid rgba(0,232,255,.07);
  padding:14px;display:flex;flex-direction:column;gap:10px;
}
.wbm-preview-canvas-wrap{
  width:100%;aspect-ratio:1;background:rgba(0,0,0,.4);
  border:1px solid rgba(0,232,255,.1);border-radius:6px;overflow:hidden;
}
canvas.wbm-preview-canvas{ width:100%!important;height:100%!important; }
.wbm-preview-name{
  font-family:"Orbitron",sans-serif;font-size:8px;font-weight:700;
  letter-spacing:.1em;color:rgba(200,225,255,.9);
}
.wbm-preview-info{font-size:7.5px;color:rgba(90,165,190,.5);line-height:1.7;}
.wbm-add-btn{
  padding:9px;border-radius:5px;cursor:pointer;text-align:center;
  font-family:"Orbitron",sans-serif;font-size:8px;font-weight:700;
  letter-spacing:.15em;
  background:linear-gradient(135deg,rgba(0,232,255,.15),rgba(155,48,255,.1));
  border:1px solid rgba(0,232,255,.4);color:#00e8ff;
  transition:all .15s;
}
.wbm-add-btn:hover{box-shadow:0 0 18px rgba(0,232,255,.2);}
.wbm-footer{
  padding:10px 18px;border-top:1px solid rgba(0,232,255,.07);
  display:flex;align-items:center;gap:10px;
}
.wbm-page-info{flex:1;font-size:8px;color:rgba(90,165,190,.4);}
.wbm-page-btn{
  padding:5px 12px;border-radius:4px;cursor:pointer;font-size:8px;
  border:1px solid rgba(0,232,255,.2);background:rgba(0,232,255,.06);
  color:#00e8ff;transition:all .15s;
}
.wbm-page-btn:hover{background:rgba(0,232,255,.14);}
.wbm-page-btn:disabled{opacity:.3;cursor:default;}
.wbm-loading{
  text-align:center;padding:40px;
  font-size:9px;color:rgba(90,165,190,.4);letter-spacing:.1em;
}
`;
    document.head.appendChild(s);
  }

  _buildPanel() {
    const overlay = document.createElement('div');
    overlay.id = 'wb-market-overlay';
    overlay.innerHTML = `
<div id="wb-market-panel">
  <div class="wbm-hdr">
    <div class="wbm-title">⬡ ASSET MARKETPLACE</div>
    <div id="wbm-peer-count" style="font-size:8px;color:rgba(0,232,255,.4);"></div>
    <div class="wbm-close" id="wbm-close">✕</div>
  </div>
  <div class="wbm-toolbar">
    <input class="wbm-search" id="wbm-search" placeholder="Search assets…" autocomplete="off">
    <div class="wbm-cats" id="wbm-cats"></div>
  </div>
  <div class="wbm-body">
    <div class="wbm-grid-wrap">
      <div class="wbm-grid" id="wbm-grid"></div>
    </div>
    <div class="wbm-preview" id="wbm-preview-panel">
      <div class="wbm-preview-canvas-wrap" id="wbm-canvas-wrap"></div>
      <div class="wbm-preview-name" id="wbm-prev-name">—</div>
      <div class="wbm-preview-info" id="wbm-prev-info"></div>
      <div class="wbm-add-btn" id="wbm-add-btn">+ SELECT FOR PLACEMENT</div>
    </div>
  </div>
  <div class="wbm-footer">
    <div class="wbm-page-info" id="wbm-page-info"></div>
    <button class="wbm-page-btn" id="wbm-prev-page">← PREV</button>
    <button class="wbm-page-btn" id="wbm-next-page">NEXT →</button>
  </div>
</div>
`;
    document.body.appendChild(overlay);
    this._panel = overlay;

    // Category buttons
    const cats = ['all', 'floor', 'wall', 'decoration', 'interactive', 'rare'];
    const catsEl = overlay.querySelector('#wbm-cats');
    cats.forEach(c => {
      const btn = document.createElement('div');
      btn.className = `wbm-cat${c === 'all' ? ' on' : ''}`;
      btn.textContent = c.toUpperCase();
      btn.onclick = () => {
        catsEl.querySelectorAll('.wbm-cat').forEach(x => x.classList.remove('on'));
        btn.classList.add('on');
        this._category = c === 'all' ? '' : c;
        this._page = 1;
        this._fetch();
      };
      catsEl.appendChild(btn);
    });

    // Search debounce
    let _searchTimer;
    overlay.querySelector('#wbm-search').oninput = e => {
      clearTimeout(_searchTimer);
      _searchTimer = setTimeout(() => {
        this._query = e.target.value.trim();
        this._page  = 1;
        this._fetch();
      }, 350);
    };

    overlay.querySelector('#wbm-close').onclick = () => this.close();
    overlay.onclick = e => { if (e.target === overlay) this.close(); };

    overlay.querySelector('#wbm-prev-page').onclick = () => { if (this._page > 1) { this._page--; this._fetch(); } };
    overlay.querySelector('#wbm-next-page').onclick = () => { if (this._page < this._totalPages) { this._page++; this._fetch(); } };

    overlay.querySelector('#wbm-add-btn').onclick = () => {
      if (!this._selectedItem) return;
      this.builder.catalogSystem.selectCatalogItem(this._selectedItem);
      this.close();
    };
  }

  // ─────────────────────────────────────────────────────────
  // OPEN / CLOSE
  // ─────────────────────────────────────────────────────────

  open() {
    this._isOpen = true;
    this._panel.classList.add('open');
    this._initPreviewRenderer();
    if (!this._results.length) this._fetch();
  }

  close() {
    this._isOpen = false;
    this._panel.classList.remove('open');
    this._stopPreviewLoop();
  }

  // ─────────────────────────────────────────────────────────
  // DATA FETCH
  // ─────────────────────────────────────────────────────────

  async _fetch() {
    this._loading = true;
    this._renderGrid([]);
    document.getElementById('wbm-grid').innerHTML = '<div class="wbm-loading">LOADING…</div>';

    try {
      const params = new URLSearchParams({
        search:   this._query,
        category: this._category,
        page:     this._page,
        limit:    PAGE_SIZE,
      });
      const res = await fetch(`/api/nexus/world_builder_catalog.php?${params}`, { credentials: 'same-origin' });
      const j   = await res.json();
      if (res.status === 403) throw new Error('World builder access required');
      if (!j.ok) throw new Error(j.error?.message);

      // Support both paginated and flat responses
      const catalog = j.data?.catalog || j.data || [];
      const total   = j.data?.total   || catalog.length;
      this._totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
      this._results    = catalog.map(r => this.builder.catalogSystem._rowToItem(r));
    } catch (err) {
      console.warn('[Marketplace] fetch error:', err);
      // Fallback: use already-loaded local catalog
      const local = this.builder.catalogSystem.catalog;
      let filtered = local;
      if (this._query) filtered = filtered.filter(i => i.name.toLowerCase().includes(this._query.toLowerCase()));
      if (this._category) filtered = filtered.filter(i => i.category === this._category);
      const start = (this._page - 1) * PAGE_SIZE;
      this._results    = filtered.slice(start, start + PAGE_SIZE);
      this._totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    }

    this._loading = false;
    this._renderGrid(this._results);
    this._updateFooter();
  }

  _renderGrid(items) {
    const grid = document.getElementById('wbm-grid');
    if (!grid) return;
    grid.innerHTML = '';

    if (!items.length) {
      grid.innerHTML = '<div class="wbm-loading">No assets found.</div>';
      return;
    }

    items.forEach(item => {
      const card = document.createElement('div');
      card.className = 'wbm-card';
      const rarityColor = RARITY_COLOR[item.rarity] || '#44aaaa';
      card.innerHTML = `
        <div class="wbm-card-icon">${item.icon || '📦'}</div>
        <div class="wbm-card-name">${item.name}</div>
        <div class="wbm-card-cat">${item.category || ''}</div>
        ${item.rarity ? `<div class="wbm-card-rarity" style="color:${rarityColor}">${item.rarity.toUpperCase()}</div>` : ''}
      `;
      card.onclick = () => this._selectItem(item, card);
      grid.appendChild(card);
    });
  }

  _updateFooter() {
    const info = document.getElementById('wbm-page-info');
    if (info) info.textContent = `Page ${this._page} of ${this._totalPages} · ${this._results.length} assets`;
    const prev = document.getElementById('wbm-prev-page');
    const next = document.getElementById('wbm-next-page');
    if (prev) prev.disabled = this._page <= 1;
    if (next) next.disabled = this._page >= this._totalPages;
  }

  // ─────────────────────────────────────────────────────────
  // ITEM SELECTION + PREVIEW
  // ─────────────────────────────────────────────────────────

  _selectedItem = null;

  _selectItem(item, cardEl) {
    this._selectedItem = item;
    document.querySelectorAll('.wbm-card').forEach(c => c.classList.remove('on'));
    cardEl.classList.add('on');

    // Info panel
    const rarityColor = RARITY_COLOR[item.rarity] || '#44aaaa';
    document.getElementById('wbm-prev-name').textContent  = item.name;
    document.getElementById('wbm-prev-info').innerHTML    = [
      item.rarity ? `<div style="color:${rarityColor}">${item.rarity.toUpperCase()}</div>` : '',
      item.category ? `<div>Category: ${item.category}</div>` : '',
      item.model    ? `<div style="color:rgba(0,232,255,.5)">GLB model</div>` : '<div>Procedural</div>',
    ].join('');

    // 3D preview
    this._loadPreviewModel(item);
  }

  // ─────────────────────────────────────────────────────────
  // MINI-PREVIEW RENDERER
  // ─────────────────────────────────────────────────────────

  _initPreviewRenderer() {
    if (this._previewRenderer) return;
    const wrap = document.getElementById('wbm-canvas-wrap');
    if (!wrap) return;

    const size = 172;
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(size, size);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.4;
    renderer.domElement.className = 'wbm-preview-canvas';
    wrap.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x050c18);
    scene.add(new THREE.AmbientLight(0x2a4060, 2));
    const sun = new THREE.DirectionalLight(0x90b8ec, 3);
    sun.position.set(1, 2, 1);
    scene.add(sun);
    scene.add(new THREE.HemisphereLight(0x396080, 0x101a30, 1.2));

    const cam = new THREE.PerspectiveCamera(45, 1, 0.01, 200);
    cam.position.set(3, 2.5, 3);
    cam.lookAt(0, 1, 0);

    this._previewRenderer = renderer;
    this._previewScene    = scene;
    this._previewCam      = cam;

    this._startPreviewLoop();
  }

  _loadPreviewModel(item) {
    if (!this._previewScene) return;

    // Clear old preview object
    if (this._previewObj) {
      this._previewScene.remove(this._previewObj);
      this._previewObj = null;
    }

    this.builder.catalogSystem.buildWorldObject({ ...item, _noVariation: true })
      .then(mesh => {
        if (!this._previewScene) return;
        // Center in preview
        const box = new THREE.Box3().setFromObject(mesh);
        const center = new THREE.Vector3();
        box.getCenter(center);
        mesh.position.sub(center);
        mesh.position.y += 0.5;

        this._previewObj = mesh;
        this._previewScene.add(mesh);

        // Fit camera
        const size = new THREE.Vector3();
        box.getSize(size);
        const maxDim = Math.max(size.x, size.y, size.z);
        this._previewCam.position.setLength(maxDim * 2.2);
        this._previewCam.lookAt(0, 0, 0);
      })
      .catch(() => {});
  }

  _startPreviewLoop() {
    const loop = () => {
      this._previewRafId = requestAnimationFrame(loop);
      if (this._previewObj) this._previewObj.rotation.y += 0.01;
      this._previewRenderer?.render(this._previewScene, this._previewCam);
    };
    loop();
  }

  _stopPreviewLoop() {
    if (this._previewRafId) { cancelAnimationFrame(this._previewRafId); this._previewRafId = null; }
  }

  isOpen() { return this._isOpen; }
}
