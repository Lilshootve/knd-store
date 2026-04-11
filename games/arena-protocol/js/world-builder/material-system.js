/**
 * MaterialSystem — runtime editing of materials on placed GLB objects.
 * Works with MeshStandardMaterial (all GLBs are normalized to Std in loader).
 * Preserves original textures unless user explicitly overrides.
 */
import * as THREE from 'three';

export class MaterialSystem {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx = builder.ctx;
    // Map of objectId → Array<original material snapshots> for undo
    this._snapshots = new Map();
  }

  // ─────────────────────────────────────────────────────────
  // MATERIAL COLLECTION
  // ─────────────────────────────────────────────────────────

  /** Returns all unique editable materials from an object (deduplicated). */
  getMaterials(mesh) {
    const seen = new Set();
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

  /** Returns a snapshot of all material properties for undo. */
  snapshotMaterials(entry) {
    const mats = this.getMaterials(entry.mesh);
    const snap = mats.map(m => ({
      uuid:                m.uuid,
      color:               m.color.clone(),
      emissive:            m.emissive ? m.emissive.clone() : new THREE.Color(0),
      emissiveIntensity:   m.emissiveIntensity ?? 0,
      metalness:           m.metalness ?? 0,
      roughness:           m.roughness ?? 1,
      opacity:             m.opacity ?? 1,
      transparent:         m.transparent ?? false,
      wireframe:           m.wireframe ?? false,
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
      m.metalness = s.metalness;
      m.roughness = s.roughness;
      m.opacity = s.opacity;
      m.transparent = s.transparent;
      m.wireframe = s.wireframe;
      m.needsUpdate = true;
    });
  }

  // ─────────────────────────────────────────────────────────
  // MATERIAL PROPERTY SETTERS
  // All setters apply to ALL materials on the selected mesh.
  // ─────────────────────────────────────────────────────────

  setBaseColor(entry, hexStr) {
    const c = new THREE.Color(hexStr);
    this.getMaterials(entry.mesh).forEach(m => { m.color.copy(c); m.needsUpdate = true; });
  }

  setEmissiveColor(entry, hexStr) {
    const c = new THREE.Color(hexStr);
    this.getMaterials(entry.mesh).forEach(m => {
      if (m.emissive) { m.emissive.copy(c); m.needsUpdate = true; }
    });
  }

  setEmissiveIntensity(entry, value) {
    const v = Math.max(0, Math.min(5, Number(value)));
    this.getMaterials(entry.mesh).forEach(m => { m.emissiveIntensity = v; m.needsUpdate = true; });
  }

  setMetalness(entry, value) {
    const v = Math.max(0, Math.min(1, Number(value)));
    this.getMaterials(entry.mesh).forEach(m => {
      if (m.metalness != null) { m.metalness = v; m.needsUpdate = true; }
    });
  }

  setRoughness(entry, value) {
    const v = Math.max(0, Math.min(1, Number(value)));
    this.getMaterials(entry.mesh).forEach(m => {
      if (m.roughness != null) { m.roughness = v; m.needsUpdate = true; }
    });
  }

  setOpacity(entry, value) {
    const v = Math.max(0, Math.min(1, Number(value)));
    this.getMaterials(entry.mesh).forEach(m => {
      m.opacity = v;
      m.transparent = v < 1.0;
      m.needsUpdate = true;
    });
  }

  setWireframe(entry, enabled) {
    this.getMaterials(entry.mesh).forEach(m => {
      m.wireframe = enabled;
      m.needsUpdate = true;
    });
  }

  /** Replaces ALL materials with a fresh MeshStandardMaterial. Destroys original textures. */
  overrideWithStandard(entry, options = {}) {
    const {
      color            = '#888888',
      metalness        = 0.5,
      roughness        = 0.5,
      emissive         = '#000000',
      emissiveIntensity = 0,
    } = options;

    entry.mesh.traverse(o => {
      if (!o.isMesh) return;
      const oldMats = Array.isArray(o.material) ? o.material : [o.material];
      // Dispose old materials (textures included)
      oldMats.forEach(m => this.ctx.disposeMaterialSafe(m));
      o.material = new THREE.MeshStandardMaterial({
        color:             new THREE.Color(color),
        metalness,
        roughness,
        emissive:          new THREE.Color(emissive),
        emissiveIntensity,
      });
    });
  }

  /** Returns the current values of the first material on the object for UI display. */
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
}
