/**
 * TransformSystem — wraps Three.js TransformControls, manages selection,
 * BoxHelper outline, and numeric transform inputs.
 *
 * Three.js r163+ API change:
 *   TransformControls is no longer an Object3D itself.
 *   Add gizmo.getHelper() to the scene, NOT gizmo directly.
 *   gizmo.attach() / gizmo.detach() / gizmo.setMode() still work on gizmo.
 *   Visibility is controlled via gizmo.getHelper().visible.
 */
import * as THREE from 'three';
import { TransformControls } from 'three/addons/controls/TransformControls.js';

export class TransformSystem {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx     = builder.ctx;

    /** @type {{id, item_id, mesh}|null} */
    this.selectedEntry = null;

    this._boxHelper   = null;
    this._gizmo       = null;   // TransformControls (for attach/detach/setMode)
    this._gizmoHelper = null;   // Object3D added to scene (r163+ API)
    this._gizmoMode   = 'translate'; // translate | rotate | scale

    this._initGizmo();
  }

  _initGizmo() {
    const gizmo = new TransformControls(this.ctx.cam, this.ctx.renderer.domElement);
    gizmo.setSize(0.9);
    gizmo.setSpace('local');
    gizmo.setMode(this._gizmoMode);

    // Disable OrbitControls while dragging
    gizmo.addEventListener('dragging-changed', e => {
      this.ctx.orbitControls.enabled = !e.value;
    });

    // Persist transform to DB after each gizmo drag ends
    gizmo.addEventListener('mouseUp', () => {
      if (!this.selectedEntry) return;
      const { mesh, id } = this.selectedEntry;
      this.builder.catalogSystem.patchObject(id, {
        pos_x: mesh.position.x,
        pos_y: mesh.position.y,
        pos_z: mesh.position.z,
        rot_y: mesh.rotation.y,
        scale: mesh.scale.x,
      });
      this.builder.ui.refreshTransformInputs();
    });

    // r163+ API: getHelper() returns the Object3D that goes into the scene
    const helper = gizmo.getHelper();
    helper.visible = false;
    this.ctx.scene.add(helper);

    this._gizmo       = gizmo;
    this._gizmoHelper = helper;
  }

  // ─────────────────────────────────────────────────────────
  // SELECTION
  // ─────────────────────────────────────────────────────────

  select(entry) {
    if (this.selectedEntry?.id === entry.id) return;
    this.deselect();
    this.selectedEntry = entry;

    // BoxHelper outline (cyan)
    this._boxHelper = new THREE.BoxHelper(entry.mesh, 0x00e8ff);
    this.ctx.scene.add(this._boxHelper);

    // Emissive boost for selection feedback
    this._applySelectionHighlight(entry.mesh, true);

    // Attach gizmo + show helper
    this._gizmo.attach(entry.mesh);
    this._gizmoHelper.visible = true;

    this.builder.ui.onObjectSelected(entry);
  }

  deselect() {
    if (!this.selectedEntry) return;

    if (this._boxHelper) {
      this.ctx.scene.remove(this._boxHelper);
      this._boxHelper = null;
    }

    this._applySelectionHighlight(this.selectedEntry.mesh, false);

    this._gizmo.detach();
    this._gizmoHelper.visible = false;

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
    const next  = modes[(modes.indexOf(this._gizmoMode) + 1) % modes.length];
    this.setMode(next);
    this.builder.ui.setStatus(`Transform mode: ${next.toUpperCase()}  [T/Y/S] to switch`);
  }

  // ─────────────────────────────────────────────────────────
  // NUMERIC INPUTS
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
    const box    = new THREE.Box3().setFromObject(this.selectedEntry.mesh);
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

  // Key handler
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
