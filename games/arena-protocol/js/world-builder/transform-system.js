/**
 * TransformSystem — wraps Three.js TransformControls, manages selection,
 * BoxHelper outline, and numeric transform inputs.
 */
import * as THREE from 'three';
import { TransformControls } from 'three/addons/controls/TransformControls.js';

export class TransformSystem {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx = builder.ctx;

    /** @type {{id, item_id, mesh}|null} */
    this.selectedEntry = null;

    this._boxHelper = null;
    this._gizmo = null;
    this._gizmoMode = 'translate'; // translate | rotate | scale

    this._initGizmo();
  }

  _initGizmo() {
    const gizmo = new TransformControls(this.ctx.cam, this.ctx.renderer.domElement);
    gizmo.setSize(0.9);
    gizmo.setSpace('local');
    gizmo.setMode(this._gizmoMode);

    // Disable OrbitControls while dragging with gizmo
    gizmo.addEventListener('dragging-changed', e => {
      this.ctx.orbitControls.enabled = !e.value;
    });

    // Persist transform after each gizmo interaction ends
    gizmo.addEventListener('mouseUp', () => {
      if (!this.selectedEntry) return;
      const { mesh, id } = this.selectedEntry;
      this.builder.catalogSystem.patchObject(id, {
        pos_x:  mesh.position.x,
        pos_y:  mesh.position.y,
        pos_z:  mesh.position.z,
        rot_y:  mesh.rotation.y,
        scale:  mesh.scale.x,
      });
      this.builder.ui.refreshTransformInputs();
    });

    this.ctx.scene.add(gizmo);
    this._gizmo = gizmo;
  }

  // ─────────────────────────────────────────────────────────
  // SELECTION
  // ─────────────────────────────────────────────────────────

  select(entry) {
    if (this.selectedEntry?.id === entry.id) return; // already selected
    this.deselect();
    this.selectedEntry = entry;

    // BoxHelper outline (cyan glow)
    this._boxHelper = new THREE.BoxHelper(entry.mesh, 0x00e8ff);
    this.ctx.scene.add(this._boxHelper);

    // Emissive boost for selection feedback
    this._applySelectionHighlight(entry.mesh, true);

    // Attach gizmo
    this._gizmo.attach(entry.mesh);
    this._gizmo.visible = true;

    // Update UI
    this.builder.ui.onObjectSelected(entry);
  }

  deselect() {
    if (!this.selectedEntry) return;

    // Remove BoxHelper
    if (this._boxHelper) {
      this.ctx.scene.remove(this._boxHelper);
      this._boxHelper = null;
    }

    // Restore emissive
    this._applySelectionHighlight(this.selectedEntry.mesh, false);

    // Detach gizmo
    this._gizmo.detach();
    this._gizmo.visible = false;

    this.selectedEntry = null;
    this.builder.ui.onObjectDeselected();
  }

  _applySelectionHighlight(mesh, active) {
    mesh.traverse(o => {
      if (!o.isMesh || !o.material) return;
      const mats = Array.isArray(o.material) ? o.material : [o.material];
      mats.forEach(m => {
        const canHighlight =
          m.isMeshStandardMaterial || m.isMeshPhysicalMaterial ||
          m.isMeshLambertMaterial  || m.isMeshPhongMaterial   || m.isMeshToonMaterial;
        if (!canHighlight) return;
        if (active) {
          m._wbOrigEI = m.emissiveIntensity ?? 0;
          m.emissiveIntensity = Math.min(3.5, (m._wbOrigEI ?? 0) + 0.9);
        } else {
          if (m._wbOrigEI !== undefined) { m.emissiveIntensity = m._wbOrigEI; delete m._wbOrigEI; }
        }
      });
    });
  }

  // ─────────────────────────────────────────────────────────
  // GIZMO MODE
  // ─────────────────────────────────────────────────────────

  setMode(mode) {
    this._gizmoMode = mode;
    this._gizmo.setMode(mode);
    this.builder.ui.refreshGizmoModeButtons(mode);
  }

  cycleMode() {
    const modes = ['translate', 'rotate', 'scale'];
    const next = modes[(modes.indexOf(this._gizmoMode) + 1) % modes.length];
    this.setMode(next);
    this.builder.ui.setStatus(`Transform mode: ${next.toUpperCase()}  [T/R/S] to switch`);
  }

  // ─────────────────────────────────────────────────────────
  // NUMERIC TRANSFORM INPUTS
  // Called from UI when user types in position/rotation/scale inputs
  // ─────────────────────────────────────────────────────────

  applyTransformFromInputs(px, py, pz, ry, sc) {
    if (!this.selectedEntry) return;
    const { mesh, id } = this.selectedEntry;
    mesh.position.set(px, py, pz);
    mesh.rotation.y = ry;
    mesh.scale.setScalar(sc);
    if (this._boxHelper) this._boxHelper.update();
    this.builder.catalogSystem.patchObject(id, {
      pos_x: px, pos_y: py, pos_z: pz, rot_y: ry, scale: sc,
    });
  }

  // Focus camera on selected object
  focusSelected() {
    if (!this.selectedEntry) return;
    const { mesh } = this.selectedEntry;
    const box = new THREE.Box3().setFromObject(mesh);
    const center = new THREE.Vector3();
    box.getCenter(center);
    this.ctx.orbitControls.target.copy(center);
    this.ctx.orbitControls.update();
  }

  // ─────────────────────────────────────────────────────────
  // TICK
  // ─────────────────────────────────────────────────────────

  tick() {
    if (this._boxHelper && this.selectedEntry) this._boxHelper.update();
  }

  // Key handler (T, R, S for modes; Tab to cycle)
  handleKey(code) {
    switch (code) {
      case 'KeyT': if (this.selectedEntry) { this.setMode('translate'); return true; } break;
      case 'KeyY': if (this.selectedEntry) { this.setMode('rotate');    return true; } break;
      case 'KeyS': if (this.selectedEntry) { this.setMode('scale');     return true; } break;
      case 'Tab':  if (this.selectedEntry) { this.cycleMode();          return true; } break;
    }
    return false;
  }
}
