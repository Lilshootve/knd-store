/**
 * TerrainTools — procedural height-map brush for vertex deformation.
 *
 * Creates a subdivided PlaneGeometry that lives on top of the platform.
 * The height brush modifies vertex Y positions in real-time on mouse drag.
 *
 * Brush modes: RAISE, LOWER, SMOOTH, FLATTEN, PAINT (vertex color)
 * Export: terrain state as JSON { vertices, width, height, segments }
 */
import * as THREE from 'three';

const DEFAULT_SIZE     = 60;
const DEFAULT_SEGMENTS = 80;

const MODES = { RAISE: 'raise', LOWER: 'lower', SMOOTH: 'smooth', FLATTEN: 'flatten' };

export class TerrainTools {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx     = builder.ctx;

    this._active     = false;
    this._mode       = MODES.RAISE;
    this._brushSize  = 6;
    this._brushStr   = 0.4;
    this._targetY    = 0; // flatten target height

    this._terrain    = null;   // THREE.Mesh
    this._positions  = null;   // Float32Array (direct ref to geometry buffer)
    this._origPos    = null;   // Float32Array snapshot for undo
    this._width      = DEFAULT_SIZE;
    this._height     = DEFAULT_SIZE;
    this._segments   = DEFAULT_SEGMENTS;

    this._raycaster  = new THREE.Raycaster();
    this._mouse      = new THREE.Vector2();
    this._painting   = false;

    // Brush indicator circle
    this._brushCircle = this._makeBrushCircle();
    this._brushCircle.visible = false;
    this.ctx.scene.add(this._brushCircle);

    // Panel
    this._panel = null;
    this._injectStyles();
    this._buildPanel();

    // Mouse events
    this._boundMove  = this._onMouseMove.bind(this);
    this._boundDown  = this._onMouseDown.bind(this);
    this._boundUp    = this._onMouseUp.bind(this);
  }

  // ─────────────────────────────────────────────────────────
  // PANEL
  // ─────────────────────────────────────────────────────────

  _injectStyles() {
    if (document.getElementById('wb-terrain-styles')) return;
    const s = document.createElement('style');
    s.id = 'wb-terrain-styles';
    s.textContent = `
#wb-terrain-panel{
  position:fixed;bottom:68px;left:50%;transform:translateX(-50%);
  background:rgba(3,6,20,.97);border:1px solid rgba(155,48,255,.2);
  border-radius:8px;padding:12px 16px;z-index:70;
  display:none;align-items:center;gap:14px;flex-wrap:wrap;
  font-family:"Share Tech Mono",monospace;font-size:8px;
  backdrop-filter:blur(14px);min-width:600px;
}
#wb-terrain-panel.open{ display:flex; }
.wbt-label{ color:rgba(155,100,255,.6);letter-spacing:.12em;white-space:nowrap; }
.wbt-mode-row{ display:flex;gap:5px; }
.wbt-mode-btn{
  padding:5px 9px;border-radius:4px;cursor:pointer;
  font-size:7.5px;letter-spacing:.08em;border:1px solid rgba(155,48,255,.2);
  background:rgba(155,48,255,.05);color:rgba(155,100,255,.5);
  transition:all .15s;font-family:"Orbitron",sans-serif;
}
.wbt-mode-btn:hover{ background:rgba(155,48,255,.15);color:#c158ff; }
.wbt-mode-btn.on{ background:rgba(155,48,255,.2);border-color:#9b30ff;color:#c158ff; }
.wbt-slider-wrap{ display:flex;align-items:center;gap:8px; }
input[type=range].wbt-range{
  width:80px;height:3px;accent-color:#9b30ff;
  -webkit-appearance:none;background:rgba(255,255,255,.08);border-radius:2px;
}
.wbt-val{ color:rgba(155,48,255,.7);width:28px;text-align:right; }
.wbt-sep{ width:1px;height:20px;background:rgba(155,48,255,.15); }
.wbt-action-row{ display:flex;gap:6px; }
.wbt-btn{
  padding:5px 10px;border-radius:4px;cursor:pointer;
  font-size:7px;font-family:"Orbitron",sans-serif;letter-spacing:.08em;
  border:1px solid rgba(0,232,255,.2);background:rgba(0,232,255,.06);
  color:#00e8ff;transition:all .15s;
}
.wbt-btn:hover{ background:rgba(0,232,255,.15); }
.wbt-close{
  margin-left:auto;padding:4px 8px;border-radius:4px;cursor:pointer;
  font-size:7px;border:1px solid rgba(255,255,255,.08);
  background:rgba(255,255,255,.04);color:rgba(255,255,255,.3);
  transition:all .15s;
}
.wbt-close:hover{ background:rgba(255,61,86,.1);color:#ff3d56; }
`;
    document.head.appendChild(s);
  }

  _buildPanel() {
    const panel = document.createElement('div');
    panel.id = 'wb-terrain-panel';
    panel.innerHTML = `
<span class="wbt-label">TERRAIN BRUSH</span>
<div class="wbt-mode-row" id="wbt-modes">
  <div class="wbt-mode-btn on" data-mode="raise">▲ RAISE</div>
  <div class="wbt-mode-btn" data-mode="lower">▼ LOWER</div>
  <div class="wbt-mode-btn" data-mode="smooth">~ SMOOTH</div>
  <div class="wbt-mode-btn" data-mode="flatten">= FLATTEN</div>
</div>
<div class="wbt-sep"></div>
<div class="wbt-slider-wrap">
  <span class="wbt-label">SIZE</span>
  <input type="range" class="wbt-range" id="wbt-size" min="1" max="20" step="0.5" value="6">
  <span class="wbt-val" id="wbt-size-val">6</span>
</div>
<div class="wbt-slider-wrap">
  <span class="wbt-label">STRENGTH</span>
  <input type="range" class="wbt-range" id="wbt-str" min="0.05" max="2" step="0.05" value="0.4">
  <span class="wbt-val" id="wbt-str-val">0.40</span>
</div>
<div class="wbt-sep"></div>
<div class="wbt-action-row">
  <div class="wbt-btn" id="wbt-create">+ CREATE TERRAIN</div>
  <div class="wbt-btn" id="wbt-reset">↺ RESET</div>
  <div class="wbt-btn" id="wbt-export">📤 EXPORT</div>
  <div class="wbt-btn" id="wbt-import">📥 IMPORT</div>
</div>
<div class="wbt-close" id="wbt-close">✕</div>
`;
    document.body.appendChild(panel);
    this._panel = panel;

    // Mode buttons
    panel.querySelectorAll('.wbt-mode-btn').forEach(btn => {
      btn.onclick = () => {
        panel.querySelectorAll('.wbt-mode-btn').forEach(b => b.classList.remove('on'));
        btn.classList.add('on');
        this._mode = btn.dataset.mode;
      };
    });

    // Sliders
    const sizeInp = panel.querySelector('#wbt-size');
    const sizeVal = panel.querySelector('#wbt-size-val');
    sizeInp.oninput = () => {
      this._brushSize = Number(sizeInp.value);
      sizeVal.textContent = this._brushSize;
      this._brushCircle.scale.setScalar(this._brushSize);
    };

    const strInp = panel.querySelector('#wbt-str');
    const strVal = panel.querySelector('#wbt-str-val');
    strInp.oninput = () => {
      this._brushStr = Number(strInp.value);
      strVal.textContent = this._brushStr.toFixed(2);
    };

    panel.querySelector('#wbt-create').onclick  = () => this._createTerrain();
    panel.querySelector('#wbt-reset').onclick   = () => this._resetTerrain();
    panel.querySelector('#wbt-export').onclick  = () => this._exportTerrain();
    panel.querySelector('#wbt-import').onclick  = () => this._importTerrain();
    panel.querySelector('#wbt-close').onclick   = () => this.deactivate();
  }

  // ─────────────────────────────────────────────────────────
  // ACTIVATION
  // ─────────────────────────────────────────────────────────

  activate() {
    if (this._active) return;
    this._active = true;
    this._panel.classList.add('open');
    this.ctx.renderer.domElement.addEventListener('mousemove', this._boundMove);
    this.ctx.renderer.domElement.addEventListener('mousedown', this._boundDown);
    window.addEventListener('mouseup', this._boundUp);
    this._brushCircle.visible = !!this._terrain;
    this.builder.ui.setStatus('Terrain Tools active. Create a terrain first, then paint.');
  }

  deactivate() {
    if (!this._active) return;
    this._active = false;
    this._panel.classList.remove('open');
    this.ctx.renderer.domElement.removeEventListener('mousemove', this._boundMove);
    this.ctx.renderer.domElement.removeEventListener('mousedown', this._boundDown);
    window.removeEventListener('mouseup', this._boundUp);
    this._brushCircle.visible = false;
    this._painting = false;
  }

  // ─────────────────────────────────────────────────────────
  // TERRAIN CREATION
  // ─────────────────────────────────────────────────────────

  _createTerrain() {
    if (this._terrain) {
      if (!confirm('Replace existing terrain?')) return;
      this._destroyTerrain();
    }

    const geo = new THREE.PlaneGeometry(
      this._width, this._height,
      this._segments, this._segments
    );
    geo.rotateX(-Math.PI / 2);

    const mat = new THREE.MeshStandardMaterial({
      color:     0x1a2a18,
      roughness: 0.88,
      metalness: 0.02,
      wireframe: false,
      side:      THREE.DoubleSide,
    });

    const mesh = new THREE.Mesh(geo, mat);
    mesh.name          = '_wbTerrain';
    mesh.receiveShadow = true;
    mesh.castShadow    = false;
    mesh.position.y    = 0.05; // sit just above ground plane

    this.ctx.scene.add(mesh);
    this._terrain  = mesh;
    this._positions = geo.attributes.position.array;
    this._origPos   = new Float32Array(this._positions);
    this._brushCircle.visible = true;

    this.builder.ui.setStatus('Terrain created. Hold and drag to sculpt.');
  }

  _destroyTerrain() {
    if (!this._terrain) return;
    this.ctx.scene.remove(this._terrain);
    this._terrain.geometry.dispose();
    this._terrain.material.dispose();
    this._terrain = null;
    this._positions = null;
    this._origPos = null;
    this._brushCircle.visible = false;
  }

  _resetTerrain() {
    if (!this._terrain || !this._origPos) return;
    this._positions.set(this._origPos);
    this._terrain.geometry.attributes.position.needsUpdate = true;
    this._terrain.geometry.computeVertexNormals();
    this.builder.ui.setStatus('Terrain reset to flat.');
  }

  // ─────────────────────────────────────────────────────────
  // BRUSH APPLICATION
  // ─────────────────────────────────────────────────────────

  _applyBrush(worldPos, dt) {
    if (!this._terrain || !this._positions) return;

    const geo   = this._terrain.geometry;
    const pos   = this._positions;
    const count = pos.length / 3;
    const bsq   = this._brushSize * this._brushSize;
    const str   = this._brushStr * dt * 60; // time-normalize
    const bx    = worldPos.x - this._terrain.position.x;
    const bz    = worldPos.z - this._terrain.position.z;

    for (let i = 0; i < count; i++) {
      const ix = pos[i * 3];
      const iy = pos[i * 3 + 1];
      const iz = pos[i * 3 + 2];

      const dx = ix - bx, dz = iz - bz;
      const dsq = dx * dx + dz * dz;
      if (dsq > bsq) continue;

      // Falloff: smoothstep
      const t = 1 - (dsq / bsq);
      const falloff = t * t * (3 - 2 * t);

      switch (this._mode) {
        case MODES.RAISE:
          pos[i * 3 + 1] += str * falloff;
          break;
        case MODES.LOWER:
          pos[i * 3 + 1] -= str * falloff;
          break;
        case MODES.SMOOTH: {
          // Average height in radius
          let sum = 0, cnt = 0;
          for (let j = 0; j < count; j++) {
            const jx = pos[j*3]-bx, jz = pos[j*3+2]-bz;
            if (jx*jx+jz*jz <= bsq) { sum += pos[j*3+1]; cnt++; }
          }
          const avg = cnt > 0 ? sum / cnt : iy;
          pos[i * 3 + 1] += (avg - iy) * str * falloff * 0.1;
          break;
        }
        case MODES.FLATTEN:
          pos[i * 3 + 1] += (this._targetY - iy) * str * falloff * 0.15;
          break;
      }
    }

    geo.attributes.position.needsUpdate = true;
    geo.computeVertexNormals();
  }

  // ─────────────────────────────────────────────────────────
  // MOUSE EVENTS
  // ─────────────────────────────────────────────────────────

  _lastPaint = 0;

  _onMouseMove(e) {
    if (!this._active) return;
    const hitPos = this._raycastTerrain(e.clientX, e.clientY);
    if (hitPos) {
      this._brushCircle.position.copy(hitPos);
      this._brushCircle.position.y += 0.05;
      this._brushCircle.visible = true;

      if (this._painting) {
        const now = performance.now();
        const dt  = (now - this._lastPaint) / 1000;
        this._lastPaint = now;
        if (dt < 0.1) this._applyBrush(hitPos, dt);
      }
    } else {
      this._brushCircle.visible = !!this._terrain && false;
    }
  }

  _onMouseDown(e) {
    if (!this._active || !this._terrain) return;
    this._painting   = true;
    this._lastPaint  = performance.now();
    // Flatten mode: set target to current terrain height at brush center
    if (this._mode === MODES.FLATTEN) {
      const hitPos = this._raycastTerrain(e.clientX, e.clientY);
      if (hitPos) this._targetY = hitPos.y;
    }
  }

  _onMouseUp() { this._painting = false; }

  _raycastTerrain(clientX, clientY) {
    if (!this._terrain) return null;
    const rect = this.ctx.renderer.domElement.getBoundingClientRect();
    this._mouse.x =  ((clientX - rect.left) / rect.width)  * 2 - 1;
    this._mouse.y = -((clientY - rect.top)  / rect.height) * 2 + 1;
    this._raycaster.setFromCamera(this._mouse, this.ctx.cam);
    const hits = this._raycaster.intersectObject(this._terrain);
    return hits.length ? hits[0].point : null;
  }

  // ─────────────────────────────────────────────────────────
  // BRUSH CIRCLE INDICATOR
  // ─────────────────────────────────────────────────────────

  _makeBrushCircle() {
    const geo = new THREE.RingGeometry(0.85, 1.0, 32);
    const mat = new THREE.MeshBasicMaterial({
      color: 0xc158ff, side: THREE.DoubleSide,
      transparent: true, opacity: 0.65, depthWrite: false,
    });
    const mesh = new THREE.Mesh(geo, mat);
    mesh.rotation.x = -Math.PI / 2;
    mesh.scale.setScalar(this._brushSize);
    mesh.name = '_wbBrushCircle';
    return mesh;
  }

  // ─────────────────────────────────────────────────────────
  // EXPORT / IMPORT
  // ─────────────────────────────────────────────────────────

  _exportTerrain() {
    if (!this._terrain || !this._positions) {
      this.builder.ui.setStatus('No terrain to export.'); return;
    }
    const data = {
      width: this._width, height: this._height, segments: this._segments,
      vertices: Array.from(this._positions),
    };
    const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
    const url  = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = `nexus-terrain-${Date.now()}.json`; a.click();
    URL.revokeObjectURL(url);
    this.builder.ui.setStatus('Terrain exported.');
  }

  _importTerrain() {
    const input = document.createElement('input');
    input.type = 'file'; input.accept = '.json';
    input.onchange = e => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = ev => {
        try {
          const data = JSON.parse(ev.target.result);
          if (!data.vertices) throw new Error('Invalid terrain file');
          this._destroyTerrain();
          this._width    = data.width    || DEFAULT_SIZE;
          this._height   = data.height   || DEFAULT_SIZE;
          this._segments = data.segments || DEFAULT_SEGMENTS;
          this._createTerrain();
          // Apply loaded vertex data
          const src = new Float32Array(data.vertices);
          this._positions.set(src.subarray(0, Math.min(src.length, this._positions.length)));
          this._terrain.geometry.attributes.position.needsUpdate = true;
          this._terrain.geometry.computeVertexNormals();
          this.builder.ui.setStatus('Terrain imported.');
        } catch (err) {
          this.builder.ui.setStatus('⚠ Failed to import terrain.');
        }
      };
      reader.readAsText(file);
    };
    input.click();
  }

  isActive() { return this._active; }
}
