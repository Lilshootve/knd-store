/**
 * MultiSelectSystem — Shift+click to add/remove objects from selection.
 * Group transform via a virtual pivot Group at the centroid of all selected objects.
 * TransformControls attaches to the pivot; on drag-end all member objects are
 * updated and their transforms are persisted.
 */
import * as THREE from 'three';

export class MultiSelectSystem {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx     = builder.ctx;

    /** @type {Set<{id, item_id, mesh}>} */
    this.selection = new Set();

    // Virtual pivot group — TransformControls attaches here
    this._pivot = new THREE.Group();
    this._pivot.name = '_wbMultiPivot';
    this.ctx.scene.add(this._pivot);
    this._pivot.visible = false;

    // Snapshot of each member's world-space offset from pivot (recorded on drag start)
    this._offsets = new Map(); // entry.id → THREE.Vector3 local offset

    // BoxHelper for each selected object
    this._boxHelpers = new Map(); // entry.id → BoxHelper

    this._initPivotGizmo();
  }

  // ─────────────────────────────────────────────────────────
  // PIVOT GIZMO
  // ─────────────────────────────────────────────────────────

  _initPivotGizmo() {
    const gizmo = this.builder.transformSystem._gizmo;

    gizmo.addEventListener('mouseDown', () => {
      if (!this._pivot.visible) return;
      // Record world-space offsets of all members relative to pivot
      this._offsets.clear();
      const pivotWPos = new THREE.Vector3();
      this._pivot.getWorldPosition(pivotWPos);
      this.selection.forEach(entry => {
        const memberWPos = new THREE.Vector3();
        entry.mesh.getWorldPosition(memberWPos);
        this._offsets.set(entry.id, memberWPos.sub(pivotWPos));
      });
      this._pivotStartPos = pivotWPos.clone();
      this._pivotStartRot = this._pivot.rotation.y;
      this._pivotStartScale = this._pivot.scale.x;
    });

    gizmo.addEventListener('mouseUp', () => {
      if (!this._pivot.visible) return;
      this._syncMembersFromPivot();
    });
  }

  // ─────────────────────────────────────────────────────────
  // SELECTION MANAGEMENT
  // ─────────────────────────────────────────────────────────

  /** Add entry to multi-selection (Shift+click). */
  add(entry) {
    if (this.selection.has(entry)) {
      this.remove(entry);
      return;
    }
    this.selection.add(entry);
    this._addBoxHelper(entry);
    this._updatePivot();
    this.builder.ui.onMultiSelectChanged(this.selection.size);
  }

  /** Remove entry from multi-selection. */
  remove(entry) {
    this.selection.delete(entry);
    this._removeBoxHelper(entry);
    this._updatePivot();
    this.builder.ui.onMultiSelectChanged(this.selection.size);
  }

  /** Clear all multi-selection. */
  clear() {
    this._boxHelpers.forEach(bh => this.ctx.scene.remove(bh));
    this._boxHelpers.clear();
    this.selection.clear();
    this._hidePivot();
    this.builder.ui.onMultiSelectChanged(0);
  }

  isSelected(entry) { return this.selection.has(entry); }
  hasMultiple()     { return this.selection.size > 1; }

  // ─────────────────────────────────────────────────────────
  // BOX HELPERS
  // ─────────────────────────────────────────────────────────

  _addBoxHelper(entry) {
    const bh = new THREE.BoxHelper(entry.mesh, 0x00ff88);
    this.ctx.scene.add(bh);
    this._boxHelpers.set(entry.id, bh);
  }

  _removeBoxHelper(entry) {
    const bh = this._boxHelpers.get(entry.id);
    if (bh) { this.ctx.scene.remove(bh); this._boxHelpers.delete(entry.id); }
  }

  // ─────────────────────────────────────────────────────────
  // PIVOT MANAGEMENT
  // ─────────────────────────────────────────────────────────

  /** Recompute pivot position at centroid of all selected objects. */
  _updatePivot() {
    if (this.selection.size < 2) {
      this._hidePivot();
      return;
    }

    const centroid = new THREE.Vector3();
    this.selection.forEach(entry => centroid.add(entry.mesh.position));
    centroid.divideScalar(this.selection.size);

    this._pivot.position.copy(centroid);
    this._pivot.rotation.set(0, 0, 0);
    this._pivot.scale.set(1, 1, 1);
    this._pivot.visible = true;

    // Attach gizmo to pivot (r163+ API: visibility via getHelper())
    const ts = this.builder.transformSystem;
    ts._gizmo.attach(this._pivot);
    if (ts._gizmoHelper) ts._gizmoHelper.visible = true;
  }

  _hidePivot() {
    this._pivot.visible = false;
    const ts = this.builder.transformSystem;
    ts._gizmo.detach();
    if (ts._gizmoHelper) ts._gizmoHelper.visible = false;
  }

  // ─────────────────────────────────────────────────────────
  // SYNC MEMBERS WHEN PIVOT IS TRANSFORMED
  // ─────────────────────────────────────────────────────────

  _syncMembersFromPivot() {
    const pivotPos   = new THREE.Vector3();
    const pivotDeltaRot   = this._pivot.rotation.y - this._pivotStartRot;
    const pivotDeltaScale = this._pivot.scale.x / this._pivotStartScale;

    this._pivot.getWorldPosition(pivotPos);
    const pivotDeltaPos = pivotPos.clone().sub(this._pivotStartPos);

    this.selection.forEach(entry => {
      const offset = this._offsets.get(entry.id);
      if (!offset) return;

      // Rotate offset around pivot
      const rotatedOffset = offset.clone().applyAxisAngle(
        new THREE.Vector3(0, 1, 0), pivotDeltaRot
      ).multiplyScalar(pivotDeltaScale);

      entry.mesh.position.copy(pivotPos).add(rotatedOffset);
      entry.mesh.rotation.y += pivotDeltaRot;
      const newScale = entry.mesh.scale.x * pivotDeltaScale;
      entry.mesh.scale.setScalar(Math.max(0.05, Math.min(12, newScale)));

      // Persist
      this.builder.catalogSystem.patchObject(entry.id, {
        pos_x: entry.mesh.position.x,
        pos_y: entry.mesh.position.y,
        pos_z: entry.mesh.position.z,
        rot_y: entry.mesh.rotation.y,
        scale: entry.mesh.scale.x,
      });
    });

    // Reset pivot to final position
    this._pivotStartPos   = pivotPos.clone();
    this._pivotStartRot   = this._pivot.rotation.y;
    this._pivotStartScale = this._pivot.scale.x;
    this._offsets.clear();
  }

  // ─────────────────────────────────────────────────────────
  // GROUP OPERATIONS
  // ─────────────────────────────────────────────────────────

  /** Delete all selected objects. */
  async deleteAll() {
    const entries = [...this.selection];
    this.clear();
    for (const entry of entries) {
      await this.builder.catalogSystem.deleteObject(entry);
    }
  }

  /** Duplicate all selected objects with offset. */
  async duplicateAll() {
    const entries = [...this.selection];
    this.clear();
    for (const entry of entries) {
      await this.builder.catalogSystem.duplicateObject(entry);
    }
  }

  /** Move all selected objects by delta vector. */
  async translateAll(delta) {
    this.selection.forEach(entry => {
      entry.mesh.position.add(delta);
      this.builder.catalogSystem.patchObject(entry.id, {
        pos_x: entry.mesh.position.x,
        pos_y: entry.mesh.position.y,
        pos_z: entry.mesh.position.z,
      });
    });
  }

  // ─────────────────────────────────────────────────────────
  // TICK
  // ─────────────────────────────────────────────────────────

  tick() {
    this._boxHelpers.forEach((bh, id) => {
      const entry = this.builder.catalogSystem._objectMap.get(id);
      if (entry) bh.update();
    });
  }
}
