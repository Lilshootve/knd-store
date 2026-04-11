/**
 * MaterialSystem — runtime editing of materials on placed GLB objects.
 * Works with MeshStandardMaterial (all GLBs are normalized to Std in loader).
 * Preserves original textures unless user explicitly overrides.
 *
 * PERSISTENCE: After every setter call a 600 ms debounce fires and sends
 * the full material state to /api/nexus/world_builder.php via patchObject().
 * On scene load, catalog-system applies stored material_data automatically.
 */
import * as THREE from 'three';

export class MaterialSystem {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx     = builder.ctx;

    // Map of entryId → snapshot of original material properties (for undo)
    this._snapshots = new Map();

    // Debounce timers: entryId → setTimeout handle
    this._patchTimers = new Map();
  }

  // ─────────────────────────────────────────────────────────
  // MATERIAL COLLECTION
  // ─────────────────────────────────────────────────────────

  /** Returns all unique editable materials from an object (deduplicated). */
  getMaterials(mesh) {
    const seen   = new Set();
    const result = [];
    mesh.traverse(o => {
      if (!o.isMesh || !o.material) return;
      const mats = Array.isArray(o.material) ? o.material : [o.material];
      mats.forEach(m => {
        if (!m || seen.has(m.uuid)) return;
        seen.add(m.uuid);
        if (this._isEditable(m)) result.push(m);
      });
    });
    return result;
  }

  _isEditable(m) {
    return m.isMeshStandardMaterial || m.isMeshPhysicalMaterial ||
           m.isMeshLambertMaterial  || m.isMeshPhongMaterial;
  }

  // ─────────────────────────────────────────────────────────
  // SNAPSHOT (for restore)
  // ─────────────────────────────────────────────────────────

  snapshotMaterials(entry) {
    const mats = this.getMaterials(entry.mesh);
    const snap = mats.map(m => ({
      uuid:              m.uuid,
      color:             m.color.clone(),
      emissive:          m.emissive ? m.emissive.clone() : new THREE.Color(0),
      emissiveIntensity: m.emissiveIntensity ?? 0,
      metalness:         m.metalness ?? 0,
      roughness:         m.roughness ?? 1,
      opacity:           m.opacity ?? 1,
      transparent:       m.transparent ?? false,
      wireframe:         m.wireframe ?? false,
    }));
    this._snapshots.set(entry.id, snap);
  }

  restoreSnapshot(entry) {
    const snap = this._snapshots.get(entry.id);
    if (!snap) return;
    const mats = this.getMaterials(entry.mesh);
    snap.forEach(s => {
      const m = mats.find(x => x.uuid === s.uuid);
      if (!m) return;
      m.color.copy(s.color);
      if (m.emissive) m.emissive.copy(s.emissive);
      m.emissiveIntensity = s.emissiveIntensity;
      m.metalness  = s.metalness;
      m.roughness  = s.roughness;
      m.opacity    = s.opacity;
      m.transparent = s.transparent;
      m.wireframe  = s.wireframe;
      m.needsUpdate = true;
    });
    // Clear stored override so next load uses original
    this._schedulePatch(entry, true);
  }

  // ─────────────────────────────────────────────────────────
  // MATERIAL PROPERTY SETTERS
  // All setters apply to ALL materials on the selected mesh,
  // then schedule a debounced DB persist.
  // ─────────────────────────────────────────────────────────

  setBaseColor(entry, hexStr) {
    const c = new THREE.Color(hexStr);
    this.getMaterials(entry.mesh).forEach(m => { m.color.copy(c); m.needsUpdate = true; });
    this._schedulePatch(entry);
  }

  setEmissiveColor(entry, hexStr) {
    const c = new THREE.Color(hexStr);
    this.getMaterials(entry.mesh).forEach(m => {
      if (m.emissive) { m.emissive.copy(c); m.needsUpdate = true; }
    });
    this._schedulePatch(entry);
  }

  setEmissiveIntensity(entry, value) {
    const v = Math.max(0, Math.min(5, Number(value)));
    this.getMaterials(entry.mesh).forEach(m => { m.emissiveIntensity = v; m.needsUpdate = true; });
    this._schedulePatch(entry);
  }

  setMetalness(entry, value) {
    const v = Math.max(0, Math.min(1, Number(value)));
    this.getMaterials(entry.mesh).forEach(m => {
      if (m.metalness != null) { m.metalness = v; m.needsUpdate = true; }
    });
    this._schedulePatch(entry);
  }

  setRoughness(entry, value) {
    const v = Math.max(0, Math.min(1, Number(value)));
    this.getMaterials(entry.mesh).forEach(m => {
      if (m.roughness != null) { m.roughness = v; m.needsUpdate = true; }
    });
    this._schedulePatch(entry);
  }

  setOpacity(entry, value) {
    const v = Math.max(0, Math.min(1, Number(value)));
    this.getMaterials(entry.mesh).forEach(m => {
      m.opacity     = v;
      m.transparent = v < 1.0;
      m.needsUpdate = true;
    });
    this._schedulePatch(entry);
  }

  setWireframe(entry, enabled) {
    this.getMaterials(entry.mesh).forEach(m => { m.wireframe = enabled; m.needsUpdate = true; });
    this._schedulePatch(entry);
  }

  /** Replaces ALL materials with a fresh MeshStandardMaterial. Destroys original textures. */
  overrideWithStandard(entry, options = {}) {
    const {
      color             = '#888888',
      metalness         = 0.5,
      roughness         = 0.5,
      emissive          = '#000000',
      emissiveIntensity = 0,
    } = options;

    entry.mesh.traverse(o => {
      if (!o.isMesh) return;
      const oldMats = Array.isArray(o.material) ? o.material : [o.material];
      oldMats.forEach(m => this.ctx.disposeMaterialSafe(m));
      o.material = new THREE.MeshStandardMaterial({
        color:             new THREE.Color(color),
        metalness,
        roughness,
        emissive:          new THREE.Color(emissive),
        emissiveIntensity,
      });
    });
    this._schedulePatch(entry);
  }

  // ─────────────────────────────────────────────────────────
  // APPLY MATERIAL DATA (called by catalog-system on load)
  // ─────────────────────────────────────────────────────────

  /**
   * Re-applies stored material overrides from DB to a freshly-loaded mesh.
   * Called by catalog-system.js in loadObjects() when material_data is present.
   * Does NOT schedule a patch (data is already in DB).
   */
  applyStoredData(mesh, materialData) {
    if (!materialData) return;
    let data;
    try {
      data = typeof materialData === 'string' ? JSON.parse(materialData) : materialData;
    } catch (_) { return; }
    if (!data || typeof data !== 'object') return;

    const mats = this.getMaterials(mesh);
    if (!mats.length) return;

    mats.forEach(m => {
      if (data.color      != null) m.color.set(data.color);
      if (data.emissive   != null && m.emissive) m.emissive.set(data.emissive);
      if (data.emissiveIntensity != null) m.emissiveIntensity = Number(data.emissiveIntensity);
      if (data.metalness  != null && m.metalness  != null) m.metalness  = Number(data.metalness);
      if (data.roughness  != null && m.roughness  != null) m.roughness  = Number(data.roughness);
      if (data.opacity    != null) {
        m.opacity     = Number(data.opacity);
        m.transparent = m.opacity < 1.0;
      }
      if (data.wireframe  != null) m.wireframe = Boolean(data.wireframe);
      m.needsUpdate = true;
    });
  }

  // ─────────────────────────────────────────────────────────
  // GETTERS (for UI)
  // ─────────────────────────────────────────────────────────

  /** Returns current values of the first material for UI display. */
  getValues(entry) {
    const mats = this.getMaterials(entry.mesh);
    if (!mats.length) return null;
    const m = mats[0];
    return {
      color:             '#' + m.color.getHexString(),
      emissive:          m.emissive ? '#' + m.emissive.getHexString() : '#000000',
      emissiveIntensity: m.emissiveIntensity ?? 0,
      metalness:         m.metalness ?? 0,
      roughness:         m.roughness ?? 1,
      opacity:           m.opacity ?? 1,
      wireframe:         m.wireframe ?? false,
      hasTexture:        !!m.map,
    };
  }

  // ─────────────────────────────────────────────────────────
  // PERSISTENCE — debounced patch to server
  // ─────────────────────────────────────────────────────────

  /**
   * Schedules a DB save of the current material state.
   * Debounced 600 ms so rapid slider drags don't flood the server.
   * @param {object} entry  - { id, item_id, mesh }
   * @param {boolean} clear - If true, saves null (removes override)
   */
  _schedulePatch(entry, clear = false) {
    const id = entry.id;

    // Cancel any pending timer for this entry
    const existing = this._patchTimers.get(id);
    if (existing) clearTimeout(existing);

    // Skip tmp objects (not yet saved to server)
    if (String(id).startsWith('tmp_')) return;

    const timer = setTimeout(() => {
      this._patchTimers.delete(id);
      if (clear) {
        this.builder.catalogSystem.patchObject(id, { material_data: null });
      } else {
        const vals = this.getValues(entry);
        if (!vals) return;
        this.builder.catalogSystem.patchObject(id, {
          material_data: JSON.stringify(vals),
        });
      }
    }, 600);

    this._patchTimers.set(id, timer);
  }

  /**
   * Immediately flush the pending patch for a specific entry (no debounce wait).
   * Called by save buttons in builder-ui.js.
   */
  flushEntry(entry) {
    const id = entry.id;
    if (String(id).startsWith('tmp_')) return;

    // Cancel pending debounce timer
    const existing = this._patchTimers.get(id);
    if (existing) { clearTimeout(existing); this._patchTimers.delete(id); }

    const vals = this.getValues(entry);
    if (!vals) return;

    this.builder.catalogSystem.patchObject(id, {
      material_data: JSON.stringify(vals),
    });
  }

  /** Flush all pending patches immediately (call before page unload). */
  flushAll() {
    this._patchTimers.forEach((timer, id) => {
      clearTimeout(timer);
      this._patchTimers.delete(id);
      const entry = this.builder.catalogSystem._objectMap.get(id);
      if (!entry || String(id).startsWith('tmp_')) return;
      const vals = this.getValues(entry);
      if (!vals) return;
      this.builder.catalogSystem.patchObject(id, {
        material_data: JSON.stringify(vals),
      });
    });
  }
}
