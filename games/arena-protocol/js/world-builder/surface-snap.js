/**
 * SurfaceSnap — raycasts against actual scene geometry (platform, existing objects)
 * instead of the flat Y=0 ground plane, so placed objects land on slopes,
 * rooftops, stairs, and other surfaces.
 *
 * Modes:
 *   'ground'  — flat Y=0 plane (original behaviour)
 *   'surface' — raycast against all opaque scene meshes
 *   'grid'    — surface + snap to 1-unit grid
 */
import * as THREE from 'three';

export class SurfaceSnap {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx     = builder.ctx;

    this._mode = 'surface'; // 'ground' | 'surface' | 'grid'

    this._raycaster   = new THREE.Raycaster();
    this._mouse       = new THREE.Vector2();
    this._ray         = new THREE.Ray();

    // Normal indicator arrow (shows surface normal at hit point)
    this._normalArrow = new THREE.ArrowHelper(
      new THREE.Vector3(0, 1, 0),
      new THREE.Vector3(0, 0, 0),
      0.8,
      0x00ff88,
      0.25,
      0.12
    );
    this._normalArrow.name = '_wbNormalArrow';
    this._normalArrow.visible = false;
    this.ctx.scene.add(this._normalArrow);

    // Exclusion set — meshes to skip during raycast
    this._excluded = new Set();
  }

  // ─────────────────────────────────────────────────────────
  // CONFIGURATION
  // ─────────────────────────────────────────────────────────

  setMode(mode) {
    this._mode = mode;
    this._normalArrow.visible = (mode === 'surface');
    this.builder.ui.refreshSceneTab();
  }

  getMode() { return this._mode; }

  /** Add a mesh to the exclusion list (e.g., the ghost preview). */
  exclude(object3D) { this._excluded.add(object3D); }
  include(object3D) { this._excluded.delete(object3D); }

  // ─────────────────────────────────────────────────────────
  // RAYCAST
  // ─────────────────────────────────────────────────────────

  /**
   * Given screen coordinates, return the world-space snap position.
   * @returns {{ point: THREE.Vector3, normal: THREE.Vector3, hit: boolean }}
   */
  snap(clientX, clientY, ghost = null) {
    const rect = this.ctx.renderer.domElement.getBoundingClientRect();
    this._mouse.x =  ((clientX - rect.left) / rect.width)  * 2 - 1;
    this._mouse.y = -((clientY - rect.top)  / rect.height) * 2 + 1;
    this._raycaster.setFromCamera(this._mouse, this.ctx.cam);

    if (this._mode === 'ground') {
      return this._snapGround();
    }
    return this._snapSurface(ghost);
  }

  _snapGround() {
    const plane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);
    const pt    = new THREE.Vector3();
    this._raycaster.ray.intersectPlane(plane, pt);
    if (!pt) return { point: new THREE.Vector3(), normal: new THREE.Vector3(0,1,0), hit: false };

    const snapped = this._applyGridSnap(pt);
    this._normalArrow.visible = false;
    return { point: snapped, normal: new THREE.Vector3(0, 1, 0), hit: true };
  }

  _snapSurface(ghost) {
    // Build list of intersectable meshes (exclude ghost, builder helpers)
    const candidates = [];
    this.ctx.scene.traverse(o => {
      if (!o.isMesh || !o.visible) return;
      if (o.name.startsWith('_wb') || o.name.startsWith('_lod')) return;
      if (this._excluded.has(o)) return;
      if (ghost && this._isDescendantOf(o, ghost)) return;
      if (o.userData.instMgr) return; // skip InstancedMesh manager markers
      candidates.push(o);
    });

    const hits = this._raycaster.intersectObjects(candidates, false);

    if (!hits.length) {
      // Fallback to ground plane
      this._normalArrow.visible = false;
      return this._snapGround();
    }

    const hit    = hits[0];
    const point  = hit.point.clone();
    const normal = hit.face?.normal
      ? hit.face.normal.clone().transformDirection(hit.object.matrixWorld).normalize()
      : new THREE.Vector3(0, 1, 0);

    // Show normal arrow
    this._normalArrow.position.copy(point);
    this._normalArrow.setDirection(normal);
    this._normalArrow.visible = true;

    const snapped = this._applyGridSnap(point);
    return { point: snapped, normal, hit: true };
  }

  _applyGridSnap(point) {
    if (this._mode !== 'grid') return point;
    return new THREE.Vector3(
      Math.round(point.x),
      point.y,
      Math.round(point.z)
    );
  }

  _isDescendantOf(obj, ancestor) {
    let cur = obj.parent;
    while (cur) {
      if (cur === ancestor) return true;
      cur = cur.parent;
    }
    return false;
  }

  // ─────────────────────────────────────────────────────────
  // ALIGN TO NORMAL
  // ─────────────────────────────────────────────────────────

  /** Optionally rotate the ghost to align its up axis to the surface normal. */
  alignToNormal(object3D, normal) {
    const up    = new THREE.Vector3(0, 1, 0);
    const quat  = new THREE.Quaternion();
    quat.setFromUnitVectors(up, normal);
    object3D.quaternion.copy(quat);
  }

  dispose() {
    this.ctx.scene.remove(this._normalArrow);
  }
}
