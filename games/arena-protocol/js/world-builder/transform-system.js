/**
 * TransformSystem — wraps Three.js TransformControls r163+ API.
 *
 * Three.js r163+: TransformControls is no longer an Object3D.
 * Use gizmo.getHelper() to get the Object3D that goes into the scene.
 * Falls back gracefully if getHelper() is unavailable (older builds).
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
    this._gizmo       = null;
    this._gizmoHelper = null;  // Object3D added to scene
    this._gizmoMode   = 'translate';

    this._initGizmo();
  }

  // ─────────────────────────────────────────────────────────
  // INIT
  // ─────────────────────────────────────────────────────────

  _initGizmo() {
    let gizmo;
    try {
      gizmo = new TransformControls(this.ctx.cam, this.ctx.renderer.domElement);
    } catch (e) {
      console.warn('[WB] TransformControls init failed:', e);
      return;
    }

    try { gizmo.setSize(0.9); }   catch (_) {}
    try { gizmo.setSpace('local'); } catch (_) {}
    try { gizmo.setMode(this._gizmoMode); } catch (_) {}

    // Disable OrbitControls while dragging
    gizmo.addEventListener('dragging-changed', e => {
      this.ctx.orbitControls.enabled = !e.value;
    });

    // Persist to DB after each drag ends
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

    this._gizmo = gizmo;

    // r163+ API: getHelper() returns the Object3D for the scene
    // Fallback: if getHelper() doesn't exist this is an older build
    try {
      if (typeof gizmo.getHelper === 'function') {
        const helper = gizmo.getHelper();
        if (helper && helper.isObject3D) {
          helper.visible = false;
          this.ctx.scene.add(helper);
          this._gizmoHelper = helper;
        } else {
          // getHelper() returned something unexpected — skip adding to scene
          console.warn('[WB] TransformControls.getHelper() did not return an Object3D');
        }
      } else {
        // Pre-r163: gizmo itself is an Object3D
        if (gizmo.isObject3D) {
          gizmo.visible = false;
          this.ctx.scene.add(gizmo);
          this._gizmoHelper = gizmo;
        }
      }
    } catch (e) {
      console.warn('[WB] TransformControls helper setup failed (non-fatal):', e.message);
    }
  }

  // ─────────────────────────────────────────────────────────
  // SELECTION
  // ─────────────────────────────────────────────────────────

  select(entry) {
    if (this.selectedEntry?.id === entry.id) return;
    this.deselect();
    this.selectedEntry = entry;

    // BoxHelper outline
    this._boxHelper = new THREE.BoxHelper(entry.mesh, 0x00e8ff);
    this.ctx.scene.add(this._boxHelper);

    this._applySelectionHighlight(entry.mesh, true);

    if (this._gizmo) {
      try { this._gizmo.attach(entry.mesh); } catch (_) {}
    }
    if (this._gizmoHelper) this._gizmoHelper.visible = true;

    this.builder.ui.onObjectSelected(entry);
  }

  deselect() {
    if (!this.selectedEntry) return;

    if (this._boxHelper) {
      this.ctx.scene.remove(this._boxHelper);
      this._boxHelper = null;
    }

    this._applySelectionHighlight(this.selectedEntry.mesh, false);

    if (this._gizmo) {
      try { this._gizmo.detach(); } catch (_) {}
    }
    if (this._gizmoHelper) this._gizmoHelper.visible = false;

    this.selectedEntry = null;
    this.builder.ui.onObjectDeselected();
  }

  _applySelectionHighlight(mesh, active) {
    mesh.traverse(o => {
      if (!o.isMesh || !o.material) return;
      const mats = Array.isArray(o.material) ? o.material : [o.material];
      mats.forEach(m => {
        const ok = m.isMeshStandardMaterial || m.isMeshPhysicalMaterial ||
                   m.isMeshLambertMaterial  || m.isMeshPhongMaterial || m.isMeshToonMaterial;
        if (!ok) return;
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
  // MODE
  // ─────────────────────────────────────────────────────────

  setMode(mode) {
    this._gizmoMode = mode;
    if (this._gizmo) try { this._gizmo.setMode(mode); } catch (_) {}
    this.builder.ui.refreshGizmoModeButtons(mode);
  }

  cycleMode() {
    const modes = ['translate', 'rotate', 'scale'];
    const next  = modes[(modes.indexOf(this._gizmoMode) + 1) % modes.length];
    this.setMode(next);
    this.builder.ui.setStatus(`Modo: ${next.toUpperCase()}  [T/Y/S]`);
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

  focusSelected() {
    if (!this.selectedEntry) return;
    const box    = new THREE.Box3().setFromObject(this.selectedEntry.mesh);
    const center = new THREE.Vector3();
    box.getCenter(center);
    this.ctx.orbitControls.target.copy(center);
    this.ctx.orbitControls.update();
  }

  // ─────────────────────────────────────────────────────────
  // TICK + KEYS
  // ─────────────────────────────────────────────────────────

  tick() {
    if (this._boxHelper && this.selectedEntry) this._boxHelper.update();
  }

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
