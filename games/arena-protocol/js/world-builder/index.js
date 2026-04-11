/**
 * WorldBuilderPro — main entry point.
 * Wires all sub-systems together and manages the builder lifecycle.
 *
 * Usage (nexus-city.html):
 *   import { WorldBuilderPro } from './js/world-builder/index.js';
 *   const builder = new WorldBuilderPro({ scene, cam, renderer, orbitControls,
 *     fireEv, normalizeGltfForRenderer, applyHologramEffect, addGroundRing,
 *     applyGlowToObject3D, disposeMaterialSafe, dracoLoader, webglLow });
 *   // In loadWorld():
 *   await builder.ensureWorldObjectsLoaded();
 *   if (data.is_admin) await builder.initAdmin();
 *   // In animation loop:
 *   builder.tick(dt);
 */

import { CatalogSystem }      from './catalog-system.js';
import { TransformSystem }    from './transform-system.js';
import { MaterialSystem }     from './material-system.js';
import { LightSystem }        from './light-system.js';
import { StateManager }       from './state-manager.js';
import { PerformanceManager } from './performance-manager.js';
import { BuilderUI }          from './builder-ui.js';

export class WorldBuilderPro {
  /**
   * @param {{
   *   scene: THREE.Scene,
   *   cam: THREE.Camera,
   *   renderer: THREE.WebGLRenderer,
   *   orbitControls: OrbitControls,
   *   fireEv: Function,
   *   normalizeGltfForRenderer: Function,
   *   applyHologramEffect: Function,
   *   addGroundRing: Function,
   *   applyGlowToObject3D: Function,
   *   disposeMaterialSafe: Function,
   *   dracoLoader: DRACOLoader,
   *   webglLow: boolean,
   * }} ctx
   */
  constructor(ctx) {
    this.ctx    = ctx;
    this.active = false;

    // Sub-systems (order matters for cross-references)
    this.catalogSystem   = new CatalogSystem(this);
    this.transformSystem = new TransformSystem(this);
    this.materialSystem  = new MaterialSystem(this);
    this.lightSystem     = new LightSystem(this);
    this.stateManager    = new StateManager(this);
    this.perfManager     = new PerformanceManager(this);
    this.ui              = new BuilderUI(this);

    // Event listeners (only active when builder is on)
    this._boundKeyDown   = this._onKeyDown.bind(this);
    this._boundMouseMove = this._onMouseMove.bind(this);
    this._boundClick     = this._onClick.bind(this);
  }

  // ─────────────────────────────────────────────────────────
  // LIFECYCLE
  // ─────────────────────────────────────────────────────────

  /** Load world objects for ALL visitors (not just admin). Call from loadWorld(). */
  async ensureWorldObjectsLoaded() {
    await this.catalogSystem.ensureLoaded();
  }

  /** Initialize admin-only features (catalog UI). Call from loadWorld() when is_admin. */
  async initAdmin() {
    if (!window.IS_ADMIN) return;
    await this.catalogSystem.fetchCatalog();
    this.ui.setStatus('World Builder ready. Press [B] to activate.');
    this.ctx.fireEv('🔧', 'ADMIN', 'World Builder Pro loaded', 'rgba(155,48,255,.8)');
  }

  // ─────────────────────────────────────────────────────────
  // ACTIVATION TOGGLE (B key, admin only)
  // ─────────────────────────────────────────────────────────

  toggle() {
    if (!window.IS_ADMIN) return;
    this.active ? this.deactivate() : this.activate();
  }

  activate() {
    if (!window.IS_ADMIN || this.active) return;
    this.active = true;
    this.ui.show();
    // Lock OrbitControls pan (keep zoom) while builder is open
    this.ctx.orbitControls.enablePan    = false;
    this.ctx.orbitControls.enableRotate = false;

    // Add event listeners
    window.addEventListener('keydown', this._boundKeyDown);
    this.ctx.renderer.domElement.addEventListener('mousemove', this._boundMouseMove);
    this.ctx.renderer.domElement.addEventListener('click', this._boundClick);

    this.ui.setStatus('Builder active. Click catalog item to place, or click object to select.');
    this.ctx.fireEv('🔧', 'WORLD BUILDER', 'mode activated', 'rgba(155,48,255,.75)');
  }

  deactivate() {
    if (!this.active) return;
    this.active = false;

    this.catalogSystem.cancelPlace();
    this.transformSystem.deselect();

    this.ui.hide();

    // Remove event listeners
    window.removeEventListener('keydown', this._boundKeyDown);
    this.ctx.renderer.domElement.removeEventListener('mousemove', this._boundMouseMove);
    this.ctx.renderer.domElement.removeEventListener('click', this._boundClick);

    this.ctx.fireEv('🔧', 'WORLD BUILDER', 'mode deactivated', 'rgba(155,48,255,.6)');
  }

  // ─────────────────────────────────────────────────────────
  // ANIMATION LOOP
  // ─────────────────────────────────────────────────────────

  tick(dt) {
    this.catalogSystem.tick(dt);
    if (this.active) {
      this.transformSystem.tick();
      this.perfManager.tick();
      this.ui.updateStats(this.perfManager.formatStats());
    }
  }

  // ─────────────────────────────────────────────────────────
  // INPUT HANDLERS
  // ─────────────────────────────────────────────────────────

  _onKeyDown(e) {
    if (!this.active) return;
    // Skip if typing in an input
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

    // Ctrl+D — duplicate selected
    if (e.ctrlKey && e.code === 'KeyD') {
      e.preventDefault();
      const sel = this.transformSystem.selectedEntry;
      if (sel) this.catalogSystem.duplicateObject(sel);
      return;
    }

    // Gizmo mode keys (T/Y/S)
    if (this.transformSystem.handleKey(e.code)) {
      e.preventDefault();
      return;
    }

    // Catalog / placement keys
    if (this.catalogSystem.handleKey(e.code)) {
      e.preventDefault();
      return;
    }

    // B is handled by the parent page keydown listener (nexus-city.html)
    // to avoid double-fire. No action needed here.
  }

  _onMouseMove(e) {
    if (!this.active) return;
    this.catalogSystem.onMouseMove(e);
  }

  _onClick(e) {
    if (!this.active) return;

    const cat = this.catalogSystem;

    // Ghost active → place object
    if (cat._placing && cat._ghost) {
      const pos = cat._ghost.position.clone();
      cat.placeObject(pos);
      return;
    }

    // No ghost → try to select an existing world object
    const id = cat.raycastObjects(e.clientX, e.clientY);
    if (id) {
      const entry = cat.getEntryById(id);
      if (entry) this.transformSystem.select(entry);
    } else {
      this.transformSystem.deselect();
    }
  }
}
