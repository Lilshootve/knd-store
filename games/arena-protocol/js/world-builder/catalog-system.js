/**
 * CatalogSystem — handles furniture catalog fetching, ghost preview,
 * GLB loading, object placement and world object loading.
 * Mirrors and replaces the original _wbCatalog / buildWorldObject / ghost logic.
 */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

export class CatalogSystem {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx = builder.ctx;

    /** @type {Array<CatalogItem>} */
    this.catalog = [];

    this.loader = new GLTFLoader();
    this.loader.setDRACOLoader(this.ctx.dracoLoader);

    // Ghost state
    this._ghost = null;
    this._ghostRotY = 0;
    this._ghostScale = 1.0;
    this._selectedItem = null;
    this._placing = false;

    // World objects list  [{id, item_id, mesh}]
    this._objects = [];
    this._objectMap = new Map(); // id → entry
    this._loaded = false;

    // External objects registered from outside the catalog (e.g. district GLB anchors).
    // id → { onPatch(patch), label, noDelete }
    this._externalCallbacks = new Map();

    // Ground plane for raycasting (invisible, Y=0)
    this._groundPlane = (() => {
      const m = new THREE.Mesh(
        new THREE.PlaneGeometry(500, 500),
        new THREE.MeshBasicMaterial({ visible: false, side: THREE.DoubleSide })
      );
      m.rotation.x = -Math.PI / 2;
      m.name = '_wbGroundPlane';
      this.ctx.scene.add(m);
      return m;
    })();

    this._raycaster = new THREE.Raycaster();
    this._mouse = new THREE.Vector2();
    this._gridSnap = false;
    this._gridHelper = null;
  }

  // ─────────────────────────────────────────────────────────
  // CATALOG
  // ─────────────────────────────────────────────────────────

  /** Fetches furniture catalog from server and populates this.catalog. */
  async fetchCatalog() {
    this.catalog = [];
    try {
      const res = await fetch('/api/nexus/world_builder_catalog.php', { credentials: 'same-origin' });
      const j = await res.json();
      if (res.status === 403) return;
      if (!j.ok) { console.warn('[WB] world_builder_catalog:', j.error?.code, j.error?.message); return; }
      const rows = j.data?.catalog;
      if (!Array.isArray(rows)) { console.warn('[WB] world_builder_catalog: no data.catalog[]'); return; }
      this.catalog = rows
        .map(r => this._rowToItem(r))
        .sort((a, b) => Number(b.furniture_id) - Number(a.furniture_id));
    } catch (e) {
      console.warn('[WB] world_builder_catalog fetch:', e);
    }
  }

  _rowToItem(row) {
    const ad = (row.asset_data && typeof row.asset_data === 'object') ? row.asset_data : {};
    const item = {
      id:          row.code,
      name:        row.name,
      icon:        this._categoryIcon(row.category),
      model:       ad.model || ad.model_url || null,
      color:       ad.color != null && ad.color !== '' ? ad.color : undefined,
      scale:       typeof ad.wb_scale === 'number' ? ad.wb_scale : (typeof ad.scale === 'number' ? ad.scale : 1.0),
      furniture_id: row.id,
      rarity:      row.rarity,
      category:    row.category,
      hologram:    ad.hologram === true || ad.fx === 'hologram',
    };
    const light = this._mapLightData(ad.light_data);
    if (light) item.light = light;
    return item;
  }

  _categoryIcon(cat) {
    return { floor: '🟦', wall: '🧱', decoration: '✦', interactive: '⚡', rare: '💠' }[cat] || '📦';
  }

  _mapLightData(ld) {
    if (!ld || typeof ld !== 'object') return null;
    const out = { ...ld };
    if (out.color != null) out.color = this._colorToNumber(out.color);
    if (!out.type) out.type = 'point';
    return out;
  }

  _colorToNumber(c) {
    if (c == null || c === '') return 0xffffff;
    if (typeof c === 'number' && !Number.isNaN(c)) return c;
    const s = String(c).trim();
    if (s.startsWith('#')) { const n = parseInt(s.slice(1), 16); return Number.isNaN(n) ? 0xffffff : n; }
    if (s.toLowerCase().startsWith('0x')) { const n = parseInt(s.slice(2), 16); return Number.isNaN(n) ? 0xffffff : n; }
    return 0xffffff;
  }

  findCatalogEntry(itemId) {
    if (!itemId || !this.catalog.length) return null;
    return this.catalog.find(c => c.id === itemId) || null;
  }

  // ─────────────────────────────────────────────────────────
  // WORLD OBJECT LOADING (all users see placed objects)
  // ─────────────────────────────────────────────────────────

  async ensureLoaded() {
    if (this._loaded) return;
    this._loaded = true;
    try {
      if ((window._userId | 0) > 0) await this.fetchCatalog();
      await this.loadObjects();
    } catch (e) {
      console.warn('[WB] ensureLoaded:', e);
    }
  }

  async loadObjects() {
    try {
      const res = await fetch('/api/nexus/world_builder.php?action=load', { credentials: 'same-origin' });
      const j = await res.json();
      if (!j.ok) return;
      for (const obj of (j.data.objects || [])) {
        const cat = this.findCatalogEntry(obj.item_id);
        const storedUrl = (obj.model_url && String(obj.model_url).trim()) || '';
        const modelResolved = storedUrl || (cat && cat.model) || null;

        let light = null;
        if (obj.light_data) {
          try { light = JSON.parse(obj.light_data); } catch (_) {}
        }
        if (light && typeof light.color === 'string') light = { ...light, color: this._colorToNumber(light.color) };
        if (!light && cat && cat.light) light = { ...cat.light };

        const item = {
          id:           obj.item_id,
          name:         cat ? cat.name : obj.item_id,
          model:        modelResolved,
          model_url:    modelResolved,
          scale:        parseFloat(obj.scale) || (cat && cat.scale) || 1.0,
          light_data:   obj.light_data || null,
          light,
          rarity:       cat ? cat.rarity : undefined,
          hologram:     !!(cat && cat.hologram),
          color:        (cat && cat.color) || undefined,
          _noVariation: true,
        };
        const mesh = await this.buildWorldObject(item);
        mesh.position.set(parseFloat(obj.pos_x) || 0, parseFloat(obj.pos_y) || 0, parseFloat(obj.pos_z) || 0);
        mesh.rotation.y = parseFloat(obj.rot_y) || 0;
        mesh.scale.setScalar(parseFloat(obj.scale) || 1.0);
        mesh.userData.worldObjectId = obj.id;
        this.ctx.scene.add(mesh);

        const entry = { id: obj.id, item_id: obj.item_id, mesh };
        this._objects.push(entry);
        this._objectMap.set(obj.id, entry);

        // ── Apply stored material overrides ──────────────────────────────
        if (obj.material_data) {
          try {
            this.builder.materialSystem.applyStoredData(mesh, obj.material_data);
          } catch (e) {
            console.warn('[WB] applyStoredData failed for', obj.item_id, ':', e);
          }
        }
      }
      if (j.data.objects?.length) console.log(`[WB] ${j.data.objects.length} world objects loaded`);
    } catch (err) {
      console.warn('[WB] loadObjects error:', err);
    }
  }

  // ─────────────────────────────────────────────────────────
  // BUILD WORLD OBJECT (THREE.Group from catalog item)
  // ─────────────────────────────────────────────────────────

  async buildWorldObject(item) {
    const g = new THREE.Group();
    g.userData.worldItem = item;

    const modelUrl = item.model || item.model_url || null;
    if (modelUrl) {
      try {
        const gltf = await new Promise((res, rej) => this.loader.load(modelUrl, res, null, rej));
        const model = gltf.scene;
        this.ctx.normalizeGltfForRenderer(model);

        const box = new THREE.Box3().setFromObject(model);
        const sz = new THREE.Vector3();
        box.getSize(sz);
        const maxD = Math.max(sz.x, sz.y, sz.z);
        if (maxD > 0) model.scale.setScalar((item.scale || 1.0) * 2.0 / maxD);

        const box2 = new THREE.Box3().setFromObject(model);
        model.position.y -= box2.min.y;

        model.traverse(o => { if (o.isMesh) { o.castShadow = true; o.receiveShadow = true; } });

        if (gltf.animations?.length) {
          const mixer = new THREE.AnimationMixer(model);
          mixer.clipAction(gltf.animations[0]).play();
          g.userData.mixer = mixer;
        }

        g.add(model);
        g.userData.hasGlbModel = true;
      } catch (err) {
        console.warn('[WB] GLB load failed:', item.name, err);
        this._fallbackMesh(g, item);
      }
    } else {
      this._fallbackMesh(g, item);
    }

    // Per-object lighting
    let parsedLight = null;
    if (item.light_data != null) {
      try { parsedLight = typeof item.light_data === 'string' ? JSON.parse(item.light_data) : item.light_data; } catch (_) {}
    }
    const lightCfg = item.light || parsedLight;
    if (lightCfg) {
      const lc = lightCfg.color || 0xffffff;
      const li = lightCfg.intensity || 1.0;
      const ld = lightCfg.distance || 12;
      const lh = lightCfg.height || 1.5;
      let light;
      if (lightCfg.type === 'spot') {
        light = new THREE.SpotLight(lc, li, ld, Math.PI / 6, 0.3, 2);
        light.target.position.set(0, 0, 0);
        g.add(light.target);
      } else {
        light = new THREE.PointLight(lc, li, ld, 2);
      }
      light.castShadow = false;
      light.position.set(0, lh, 0);
      light.userData.nexusDynamicLight = true;
      g.add(light);

      // Ground glow halo
      const glowRadius = lightCfg.glowRadius || 1.8;
      const glowColor = lightCfg.glowColor || lc;
      const glowMesh = new THREE.Mesh(
        new THREE.CircleGeometry(glowRadius, 32),
        new THREE.MeshBasicMaterial({ color: glowColor, transparent: true, opacity: 0.22, depthWrite: false, side: THREE.DoubleSide })
      );
      glowMesh.rotation.x = -Math.PI / 2;
      glowMesh.position.y = 0.02;
      glowMesh.userData.wbPulse = true;
      glowMesh.userData.wbPulseBase = 0.22;
      g.userData.wbGlowMesh = glowMesh;
      g.add(glowMesh);

      this.ctx.applyGlowToObject3D(g, lc, 1.6);
    } else if (item.rarity === 'legendary' || item.legendary === true) {
      this.ctx.applyGlowToObject3D(g, 0xffd600, 2.0);
    }

    if (item.hologram === true) {
      this.ctx.applyHologramEffect(g);
      this.ctx.addGroundRing(g);
    }

    // Organic variation (skip for loaded objects with _noVariation)
    if (!item._noVariation) {
      g.rotation.y = Math.random() * Math.PI * 2;
      const sv = 0.9 + Math.random() * 0.2;
      g.scale.setScalar((item.scale || 1.0) * sv);
    }

    return g;
  }

  _fallbackMesh(group, item) {
    const col = item.color ? parseInt(String(item.color).replace('#', ''), 16) : 0x334466;
    const fb = new THREE.Mesh(
      new THREE.BoxGeometry(1.2, 2.4, 1.2),
      new THREE.MeshStandardMaterial({ color: col, emissive: col, emissiveIntensity: 0.18, metalness: 0.45, roughness: 0.55 })
    );
    fb.position.y = 1.2;
    fb.castShadow = true;
    group.add(fb);
  }

  disposeGroup(group) {
    group.traverse(o => {
      if (o.isMesh) {
        o.geometry?.dispose();
        const mats = Array.isArray(o.material) ? o.material : [o.material];
        mats.forEach(m => { m?.map?.dispose(); m?.dispose?.(); });
      }
      if (o.userData?.mixer) { try { o.userData.mixer.stopAllAction(); } catch (_) {} }
    });
  }

  // ─────────────────────────────────────────────────────────
  // GHOST SYSTEM
  // ─────────────────────────────────────────────────────────

  async selectCatalogItem(item) {
    this._selectedItem = item;
    this._placing = true;
    this._ghostRotY = 0;
    this._ghostScale = item.scale || 1.0;
    await this._spawnGhost(item);
    this.builder.ui.setStatus(`Placing: ${item.name}  [Click] place  [R] rotate  [+/-] scale  [ESC] cancel`);
  }

  async _spawnGhost(item) {
    this._clearGhost();
    this._ghost = await this.buildWorldObject(item);
    this._setGhostOpacity(this._ghost, true);
    this._ghost.rotation.y = this._ghostRotY;
    this._ghost.scale.setScalar(this._ghostScale);
    this.ctx.scene.add(this._ghost);
  }

  _clearGhost() {
    if (this._ghost) {
      this.ctx.scene.remove(this._ghost);
      this.disposeGroup(this._ghost);
      this._ghost = null;
    }
  }

  _setGhostOpacity(group, transparent) {
    group.traverse(o => {
      if (o.isMesh && o.material) {
        const mats = Array.isArray(o.material) ? o.material : [o.material];
        mats.forEach(m => {
          m.transparent = transparent;
          m.opacity = transparent ? 0.42 : 1.0;
          m.depthWrite = !transparent;
        });
      }
    });
  }

  cancelPlace() {
    this._placing = false;
    this._selectedItem = null;
    this._clearGhost();
    this.builder.ui.setStatus('Builder active  [Click object] select  [B] deactivate');
  }

  // ─────────────────────────────────────────────────────────
  // PLACEMENT
  // ─────────────────────────────────────────────────────────

  async placeObject(pos) {
    if (!this._selectedItem) return;
    const item = this._selectedItem;

    const mesh = await this.buildWorldObject({ ...item, _noVariation: true });
    mesh.position.copy(pos);
    mesh.rotation.y = this._ghostRotY;
    mesh.scale.setScalar(this._ghostScale);
    this.ctx.scene.add(mesh);

    try {
      const body = {
        action:    'place',
        item_id:   item.id,
        model_url: item.model || null,
        pos_x:     pos.x,
        pos_y:     pos.y,
        pos_z:     pos.z,
        rot_y:     this._ghostRotY,
        scale:     this._ghostScale,
      };
      if (item.light) body.light_data = JSON.stringify(item.light);
      const res = await fetch('/api/nexus/world_builder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
      });
      const j = await res.json();
      if (j.ok) {
        mesh.userData.worldObjectId = j.data.id;
        const entry = { id: j.data.id, item_id: item.id, mesh };
        this._objects.push(entry);
        this._objectMap.set(j.data.id, entry);
        this.ctx.fireEv('🧱', item.name, 'object saved', 'rgba(155,48,255,.7)');
      } else {
        this._addLocal(mesh, item);
        this.builder.ui.setStatus('Warning: server error. Object placed locally only.');
      }
    } catch (_) {
      this._addLocal(mesh, item);
    }

    this.builder.ui.refreshObjectsTab();
  }

  _addLocal(mesh, item) {
    const tmpId = 'tmp_' + Date.now();
    mesh.userData.worldObjectId = tmpId;
    const entry = { id: tmpId, item_id: item.id, mesh };
    this._objects.push(entry);
    this._objectMap.set(tmpId, entry);
  }

  // ─────────────────────────────────────────────────────────
  // DUPLICATION
  // ─────────────────────────────────────────────────────────

  async duplicateObject(entry) {
    if (!entry) return;
    const { mesh } = entry;
    const catalogEntry = this.findCatalogEntry(entry.item_id);
    if (!catalogEntry) { this.builder.ui.setStatus('Cannot duplicate: item not in catalog.'); return; }

    const offset = new THREE.Vector3(1.5, 0, 0);
    const newMesh = await this.buildWorldObject({ ...catalogEntry, _noVariation: true });
    newMesh.position.copy(mesh.position).add(offset);
    newMesh.rotation.y = mesh.rotation.y;
    newMesh.scale.copy(mesh.scale);
    this.ctx.scene.add(newMesh);

    try {
      const body = {
        action:    'place',
        item_id:   catalogEntry.id,
        model_url: catalogEntry.model || null,
        pos_x:     newMesh.position.x,
        pos_y:     newMesh.position.y,
        pos_z:     newMesh.position.z,
        rot_y:     newMesh.rotation.y,
        scale:     newMesh.scale.x,
      };
      if (catalogEntry.light) body.light_data = JSON.stringify(catalogEntry.light);
      const res = await fetch('/api/nexus/world_builder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
      });
      const j = await res.json();
      if (j.ok) {
        newMesh.userData.worldObjectId = j.data.id;
        const newEntry = { id: j.data.id, item_id: catalogEntry.id, mesh: newMesh };
        this._objects.push(newEntry);
        this._objectMap.set(j.data.id, newEntry);
        this.ctx.fireEv('📋', 'WB', `Duplicated: ${catalogEntry.name}`, 'rgba(0,232,255,.6)');
        this.builder.transformSystem.select(newEntry);
      } else {
        this._addLocal(newMesh, catalogEntry);
      }
    } catch (_) {
      this._addLocal(newMesh, catalogEntry);
    }

    this.builder.ui.refreshObjectsTab();
  }

  // ─────────────────────────────────────────────────────────
  // DELETION
  // ─────────────────────────────────────────────────────────

  async deleteObject(entry) {
    if (!entry) return;

    // External objects (district anchors, etc.) cannot be deleted from the builder
    const ext = this._externalCallbacks.get(entry.id);
    if (ext?.noDelete) {
      this.ctx.fireEv('⚠', 'WB', `"${entry.label ?? entry.id}" es un anchor de distrito — no se puede eliminar desde aquí`, 'rgba(255,160,80,.8)');
      return;
    }

    this.ctx.scene.remove(entry.mesh);
    this.disposeGroup(entry.mesh);
    const idx = this._objects.indexOf(entry);
    if (idx >= 0) this._objects.splice(idx, 1);
    this._objectMap.delete(entry.id);

    if (!String(entry.id).startsWith('tmp_')) {
      try {
        await fetch('/api/nexus/world_builder.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ action: 'delete', id: entry.id }),
        });
      } catch (_) {}
    }
    this.ctx.fireEv('🗑️', 'WB', 'Object deleted', 'rgba(255,61,86,.65)');
    this.builder.ui.refreshObjectsTab();
  }

  // ─────────────────────────────────────────────────────────
  // PATCH (persist transform + material + light changes)
  // ─────────────────────────────────────────────────────────

  async patchObject(id, patch) {
    if (String(id).startsWith('tmp_')) return;

    // External objects route to their own callback instead of the world_builder API
    const ext = this._externalCallbacks.get(id);
    if (ext) {
      try { ext.onPatch?.(patch); } catch (e) { console.warn('[WB] external patch callback error:', e); }
      return;
    }

    try {
      const body = { action: 'patch', id, ...patch };
      const res = await fetch('/api/nexus/world_builder.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
      });
      const j = await res.json();
      if (!j.ok) console.warn('[WB] patch failed:', j.error?.message);
    } catch (err) {
      console.warn('[WB] patchObject network error:', err.message);
    }
  }

  // ─────────────────────────────────────────────────────────
  // GRID SNAP
  // ─────────────────────────────────────────────────────────

  toggleGrid() {
    this._gridSnap = !this._gridSnap;
    if (this._gridSnap && !this._gridHelper) {
      this._gridHelper = new THREE.GridHelper(200, 200, 0x9b30ff, 0x6622aa);
      this._gridHelper.position.y = 0.05;
      [this._gridHelper.material].flat().forEach(m => { m.transparent = true; m.opacity = 0.25; m.fog = false; });
      this.ctx.scene.add(this._gridHelper);
    } else if (!this._gridSnap && this._gridHelper) {
      this.ctx.scene.remove(this._gridHelper);
      this._gridHelper.geometry.dispose();
      this._gridHelper = null;
    }
    return this._gridSnap;
  }

  setGridVisible(visible) {
    if (this._gridHelper) this._gridHelper.visible = visible;
  }

  // ─────────────────────────────────────────────────────────
  // RAYCASTING
  // ─────────────────────────────────────────────────────────

  raycastGround(clientX, clientY) {
    const rect = this.ctx.renderer.domElement.getBoundingClientRect();
    this._mouse.x = ((clientX - rect.left) / rect.width) * 2 - 1;
    this._mouse.y = -((clientY - rect.top) / rect.height) * 2 + 1;
    this._raycaster.setFromCamera(this._mouse, this.ctx.cam);
    const hits = this._raycaster.intersectObject(this._groundPlane);
    return hits.length > 0 ? hits[0].point : null;
  }

  raycastObjects(clientX, clientY) {
    const rect = this.ctx.renderer.domElement.getBoundingClientRect();
    this._mouse.x = ((clientX - rect.left) / rect.width) * 2 - 1;
    this._mouse.y = -((clientY - rect.top) / rect.height) * 2 + 1;
    this._raycaster.setFromCamera(this._mouse, this.ctx.cam);
    const meshArr = this._objects.map(o => o.mesh).filter(Boolean);
    if (!meshArr.length) return null;
    const hits = this._raycaster.intersectObjects(meshArr, true);
    if (!hits.length) return null;
    let obj = hits[0].object;
    while (obj && !obj.userData.worldObjectId && obj.parent) obj = obj.parent;
    return obj?.userData.worldObjectId || null;
  }

  getEntryById(id) { return this._objectMap.get(id) || null; }
  getObjects()     { return this._objects; }

  /**
   * Register an external Three.js Object3D (e.g. a district GLB) so the
   * world-builder can select, move, rotate and scale it with its gizmo.
   * Changes are routed to `onPatch(patch)` instead of the world_builder API.
   *
   * @param {THREE.Object3D} mesh   - The object to make selectable.
   * @param {string}         id     - Unique ID (e.g. 'district:olimpo').
   * @param {{
   *   label?:    string,
   *   onPatch?:  (patch: {pos_x?,pos_y?,pos_z?,rot_y?,scale?}) => void,
   *   noDelete?: boolean
   * }} opts
   */
  registerExternalObject(mesh, id, opts = {}) {
    if (this._objectMap.has(id)) return; // already registered

    mesh.userData.worldObjectId = id;
    mesh.userData.wbLabel = opts.label ?? id;

    const entry = { id, item_id: id, mesh, label: opts.label ?? id, external: true };
    this._objects.push(entry);
    this._objectMap.set(id, entry);
    this._externalCallbacks.set(id, {
      onPatch:  opts.onPatch  ?? null,
      noDelete: opts.noDelete ?? true,
    });
    this.builder.ui.refreshObjectsTab?.();
  }

  /** Remove a previously registered external object from the builder (does NOT dispose geometry). */
  unregisterExternalObject(id) {
    const entry = this._objectMap.get(id);
    if (!entry?.external) return;
    const idx = this._objects.indexOf(entry);
    if (idx >= 0) this._objects.splice(idx, 1);
    this._objectMap.delete(id);
    this._externalCallbacks.delete(id);
    if (entry.mesh) delete entry.mesh.userData.worldObjectId;
    this.builder.ui.refreshObjectsTab?.();
  }

  // ─────────────────────────────────────────────────────────
  // ANIMATION TICK
  // ─────────────────────────────────────────────────────────

  _pulseT = 0;
  tick(dt) {
    this._pulseT += dt;
    const t = performance.now() * 0.002;
    this._objects.forEach(o => {
      if (o.mesh?.userData?.mixer) o.mesh.userData.mixer.update(dt);

      const glow = o.mesh?.userData?.wbGlowMesh;
      if (glow) {
        const base = glow.userData.wbPulseBase ?? 0.22;
        glow.material.opacity = base + Math.sin(this._pulseT * 1.8) * 0.07;
      }
      if (o.mesh?.userData?.scanline) o.mesh.userData.scanline.position.y = Math.sin(t) * 0.5 + 1;
      if (o.mesh?.userData?.ring) o.mesh.userData.ring.scale.setScalar(1 + Math.sin(t) * 0.05);
    });
  }

  // Mouse move — update ghost position
  onMouseMove(e) {
    if (!this._placing || !this._ghost) return;
    const pt = this.raycastGround(e.clientX, e.clientY);
    if (!pt) return;
    if (this._gridSnap) {
      this._ghost.position.x = Math.round(pt.x);
      this._ghost.position.z = Math.round(pt.z);
    } else {
      this._ghost.position.x = pt.x;
      this._ghost.position.z = pt.z;
    }
    this._ghost.position.y = 0;
  }

  // Key handler — returns true if consumed
  handleKey(code) {
    const sel = this.builder.transformSystem.selectedEntry;
    switch (code) {
      case 'KeyR':
        if (this._placing && this._ghost) {
          this._ghostRotY += Math.PI / 4;
          this._ghost.rotation.y = this._ghostRotY;
        } else if (sel) {
          sel.mesh.rotation.y += Math.PI / 4;
          this.patchObject(sel.id, { rot_y: sel.mesh.rotation.y });
          this.builder.ui.refreshTransformInputs();
        }
        return true;

      case 'Equal': case 'NumpadAdd':
        if (this._placing && this._ghost) {
          this._ghostScale = Math.min(12, this._ghostScale + 0.1);
          this._ghost.scale.setScalar(this._ghostScale);
        } else if (sel) {
          const s = Math.min(12, sel.mesh.scale.x + 0.1);
          sel.mesh.scale.setScalar(s);
          this.patchObject(sel.id, { scale: s });
          this.builder.ui.refreshTransformInputs();
        }
        return true;

      case 'Minus': case 'NumpadSubtract':
        if (this._placing && this._ghost) {
          this._ghostScale = Math.max(0.1, this._ghostScale - 0.1);
          this._ghost.scale.setScalar(this._ghostScale);
        } else if (sel) {
          const s = Math.max(0.1, sel.mesh.scale.x - 0.1);
          sel.mesh.scale.setScalar(s);
          this.patchObject(sel.id, { scale: s });
          this.builder.ui.refreshTransformInputs();
        }
        return true;

      case 'Delete': case 'Backspace':
        if (sel) { this.builder.transformSystem.deselect(); this.deleteObject(sel); return true; }
        return false;

      case 'Escape':
        if (this._placing) { this.cancelPlace(); return true; }
        if (sel) { this.builder.transformSystem.deselect(); return true; }
        return false;

      case 'KeyG':
        this.toggleGrid();
        this.builder.ui.refreshSceneTab();
        return true;

      case 'KeyD':
        // Ctrl+D — duplicate handled in index.js keydown (checks ctrlKey)
        return false;

      default:
        return false;
    }
  }
}
