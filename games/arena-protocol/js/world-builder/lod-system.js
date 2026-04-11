/**
 * LODSystem — wraps placed objects in THREE.LOD for distance-based detail switching.
 * LOD Levels:
 *   - L0 (full):      distance < nearThreshold   — original mesh
 *   - L1 (medium):    nearThreshold–farThreshold  — simplified bounding-box proxy
 *   - L2 (billboard): distance > farThreshold     — flat sprite quad (optional)
 *
 * Auto-generate simplified LOD levels using BoundingBox proxy meshes,
 * since runtime geometry decimation is not available in Three.js without a WASM codec.
 */
import * as THREE from 'three';

const NEAR_THRESHOLD = 30;  // units — full detail within this range
const FAR_THRESHOLD  = 80;  // units — hidden/billboard beyond this
const UPDATE_INTERVAL = 0.25; // seconds between LOD updates (not every frame)

export class LODSystem {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder   = builder;
    this.ctx       = builder.ctx;

    /** @type {Map<string, THREE.LOD>} entry.id → LOD wrapper */
    this._lodMap   = new Map();
    this._enabled  = true;
    this._nearDist = NEAR_THRESHOLD;
    this._farDist  = FAR_THRESHOLD;
    this._timer    = 0;
  }

  // ─────────────────────────────────────────────────────────
  // WRAP
  // ─────────────────────────────────────────────────────────

  /** Call when a world object is added to the scene. */
  wrapObject(entry) {
    if (!this._enabled) return;
    const { mesh } = entry;
    if (mesh.isLOD) return; // already wrapped

    const lod = new THREE.LOD();
    lod.name  = `_lod_${entry.id}`;

    // L0 — full detail (original mesh)
    lod.addLevel(mesh.clone ? mesh : mesh, this._nearDist);

    // L1 — bbox proxy (very cheap)
    const proxy = this._makeBBoxProxy(mesh);
    lod.addLevel(proxy, this._farDist);

    // L2 — empty object (invisible culled)
    lod.addLevel(new THREE.Object3D(), this._farDist * 1.5);

    // Copy transform
    lod.position.copy(mesh.position);
    lod.rotation.copy(mesh.rotation);
    lod.scale.copy(mesh.scale);

    this._lodMap.set(entry.id, lod);
  }

  /** Remove LOD wrapper when object is deleted. */
  unwrapObject(entry) {
    const lod = this._lodMap.get(entry.id);
    if (lod) {
      this.ctx.scene.remove(lod);
      this._lodMap.delete(entry.id);
    }
  }

  _makeBBoxProxy(mesh) {
    const box = new THREE.Box3().setFromObject(mesh);
    const size = new THREE.Vector3();
    box.getSize(size);
    const center = new THREE.Vector3();
    box.getCenter(center);

    // Simple box with a cheap BasicMaterial (no lighting calc)
    const geo = new THREE.BoxGeometry(size.x, size.y, size.z);
    const mat = new THREE.MeshBasicMaterial({
      color: 0x334466,
      transparent: true,
      opacity: 0.45,
      depthWrite: false,
    });
    const proxy = new THREE.Mesh(geo, mat);
    // Center proxy at bbox center relative to parent origin
    proxy.position.copy(center.sub(mesh.position));
    return proxy;
  }

  // ─────────────────────────────────────────────────────────
  // CONFIGURATION
  // ─────────────────────────────────────────────────────────

  setEnabled(enabled) {
    this._enabled = enabled;
    // When disabled, restore all objects to full visibility
    if (!enabled) {
      this._lodMap.forEach(lod => { lod.levels.forEach(l => { l.object.visible = true; }); });
    }
  }

  setNearDistance(v) { this._nearDist = Math.max(5, Number(v)); this._rebuildLODs(); }
  setFarDistance(v)  { this._farDist  = Math.max(this._nearDist + 10, Number(v)); this._rebuildLODs(); }

  _rebuildLODs() {
    // Update threshold distances on existing LODs
    this._lodMap.forEach(lod => {
      if (lod.levels[0]) lod.levels[0].distance = this._nearDist;
      if (lod.levels[1]) lod.levels[1].distance = this._farDist;
      if (lod.levels[2]) lod.levels[2].distance = this._farDist * 1.5;
    });
  }

  // ─────────────────────────────────────────────────────────
  // TICK
  // ─────────────────────────────────────────────────────────

  tick(dt) {
    if (!this._enabled) return;
    this._timer += dt;
    if (this._timer < UPDATE_INTERVAL) return;
    this._timer = 0;

    const camPos = new THREE.Vector3();
    this.ctx.cam.getWorldPosition(camPos);

    this._lodMap.forEach(lod => {
      lod.update(this.ctx.cam);
    });
  }

  getStats() {
    return {
      total:   this._lodMap.size,
      enabled: this._enabled,
      near:    this._nearDist,
      far:     this._farDist,
    };
  }
}
