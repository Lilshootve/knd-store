/**
 * WorldBuilderPro — main entry point (AAA Edition).
 * Wires all sub-systems together and manages the builder lifecycle.
 *
 * Usage (nexus-city.html):
 *   import { WorldBuilderPro } from './js/world-builder/index.js';
 *   const builder = new WorldBuilderPro({ scene, cam, renderer, orbitControls, ... });
 *   await builder.ensureWorldObjectsLoaded();
 *   if (data.is_admin) await builder.initAdmin();
 *   // In animation loop: builder.tick(dt);
 */

import { CatalogSystem }      from './catalog-system.js?v=20250411';
import { TransformSystem }    from './transform-system.js';
import { MaterialSystem }     from './material-system.js';
import { LightSystem }        from './light-system.js';
import { StateManager }       from './state-manager.js';
import { PerformanceManager } from './performance-manager.js';
import { BuilderUI }          from './builder-ui.js';
import { UndoRedoManager }    from './undo-redo.js';
import { MultiSelectSystem }  from './multi-select.js';
import { EnvironmentSystem }  from './environment-system.js';
import { LODSystem }          from './lod-system.js';
import { InstanceManager }    from './instance-manager.js';
import { CollabSystem }       from './collab-system.js';
import { Marketplace }        from './marketplace.js';
import { SurfaceSnap }        from './surface-snap.js';
import { HierarchyPanel }     from './hierarchy-panel.js';
import { TerrainTools }       from './terrain-tools.js';

export class WorldBuilderPro {
  /**
   * @param {{
   *   scene, cam, renderer, orbitControls,
   *   fireEv, normalizeGltfForRenderer,
   *   applyHologramEffect, addGroundRing,
   *   applyGlowToObject3D, disposeMaterialSafe,
   *   dracoLoader, webglLow
   * }} ctx
   */
  constructor(ctx) {
    this.ctx    = ctx;
    this.active = false;

    // Core systems (order matters for cross-references)
    this.catalogSystem   = new CatalogSystem(this);
    this.transformSystem = new TransformSystem(this);
    this.materialSystem  = new MaterialSystem(this);
    this.lightSystem     = new LightSystem(this);
    this.stateManager    = new StateManager(this);
    this.perfManager     = new PerformanceManager(this);

    // AAA systems
    this.undoRedo        = new UndoRedoManager(this);
    this.multiSelect     = new MultiSelectSystem(this);
    this.envSystem       = new EnvironmentSystem(this);
    this.lodSystem       = new LODSystem(this);
    this.instanceManager = new InstanceManager(this);
    this.collabSystem    = new CollabSystem(this);
    this.marketplace     = new Marketplace(this);
    this.surfaceSnap     = new SurfaceSnap(this);
    this.hierarchyPanel  = new HierarchyPanel(this);
    this.terrainTools    = new TerrainTools(this);

    // UI last (depends on all systems being created)
    this.ui              = new BuilderUI(this);

    // Bound event handlers
    this._boundKeyDown   = this._onKeyDown.bind(this);
    this._boundMouseMove = this._onMouseMove.bind(this);
    this._boundClick     = this._onClick.bind(this);
  }

  // ─────────────────────────────────────────────────────────
  // LIFECYCLE
  // ─────────────────────────────────────────────────────────

  async ensureWorldObjectsLoaded() {
    await this.catalogSystem.ensureLoaded();
  }

  async initAdmin() {
    if (!window.IS_ADMIN) return;
    await this.catalogSystem.fetchCatalog();
    this.collabSystem.attach(); // connect to WS for real-time collab
    this.ui.setStatus('World Builder Pro ready. Press [B] to activate.');
    this.ctx.fireEv('🔧', 'ADMIN', 'World Builder Pro (AAA) loaded', 'rgba(155,48,255,.8)');
  }

  // ─────────────────────────────────────────────────────────
  // ACTIVATION
  // ─────────────────────────────────────────────────────────

  toggle()     { if (!window.IS_ADMIN) return; this.active ? this.deactivate() : this.activate(); }

  activate() {
    if (!window.IS_ADMIN || this.active) return;
    this.active = true;
    this.ui.show();

    this.ctx.orbitControls.enablePan    = false;
    this.ctx.orbitControls.enableRotate = false;

    window.addEventListener('keydown',        this._boundKeyDown);
    this.ctx.renderer.domElement.addEventListener('mousemove', this._boundMouseMove);
    this.ctx.renderer.domElement.addEventListener('click',     this._boundClick);

    this.ui.setStatus('Builder active. Click catalog item to place, or click object to select.');
    this.ctx.fireEv('🔧', 'WORLD BUILDER', 'mode activated', 'rgba(155,48,255,.75)');
  }

  deactivate() {
    if (!this.active) return;
    this.active = false;

    this.catalogSystem.cancelPlace();
    this.transformSystem.deselect();
    this.multiSelect.clear();
    this.terrainTools.deactivate();

    this.ui.hide();

    window.removeEventListener('keydown',        this._boundKeyDown);
    this.ctx.renderer.domElement.removeEventListener('mousemove', this._boundMouseMove);
    this.ctx.renderer.domElement.removeEventListener('click',     this._boundClick);

    // Restore orbit
    this.ctx.orbitControls.enablePan    = false;
    this.ctx.orbitControls.enableRotate = false;

    this.ctx.fireEv('🔧', 'WORLD BUILDER', 'mode deactivated', 'rgba(155,48,255,.6)');
  }

  // ─────────────────────────────────────────────────────────
  // ANIMATION LOOP
  // ─────────────────────────────────────────────────────────

  tick(dt) {
    this.catalogSystem.tick(dt);
    this.collabSystem.tick();

    if (this.active) {
      this.transformSystem.tick();
      this.multiSelect.tick();
      this.perfManager.tick();
      this.lodSystem.tick(dt);
      this.instanceManager.tick();
      this.ui.updateStats(this.perfManager.formatStats());
    }
  }

  // ─────────────────────────────────────────────────────────
  // INPUT HANDLERS
  // ─────────────────────────────────────────────────────────

  _onKeyDown(e) {
    if (!this.active) return;
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

    // ── Undo / Redo (Ctrl+Z / Ctrl+Y) ──
    if (this.undoRedo.handleKey(e)) { e.preventDefault(); return; }

    // ── Ctrl+D — duplicate ──
    if ((e.ctrlKey || e.metaKey) && e.code === 'KeyD') {
      e.preventDefault();
      const sel = this.transformSystem.selectedEntry;
      if (sel) this.catalogSystem.duplicateObject(sel);
      return;
    }

    // ── Ctrl+A — select all ──
    if ((e.ctrlKey || e.metaKey) && e.code === 'KeyA') {
      e.preventDefault();
      this.catalogSystem.getObjects().forEach(entry => this.multiSelect.add(entry));
      return;
    }

    // ── Gizmo mode keys ──
    if (this.transformSystem.handleKey(e.code)) { e.preventDefault(); return; }

    // ── Marketplace ──
    if (e.code === 'KeyM' && !e.ctrlKey) { this.marketplace.open(); e.preventDefault(); return; }

    // ── Hierarchy panel ──
    if (e.code === 'KeyH' && !e.ctrlKey) { this.hierarchyPanel.toggle(); e.preventDefault(); return; }

    // ── Terrain toggle ──
    if (e.code === 'KeyN' && !e.ctrlKey) {
      this.terrainTools.isActive() ? this.terrainTools.deactivate() : this.terrainTools.activate();
      e.preventDefault(); return;
    }

    // ── Snap mode cycle ──
    if (e.code === 'KeyP') {
      const modes = ['ground', 'surface', 'grid'];
      const next  = modes[(modes.indexOf(this.surfaceSnap.getMode()) + 1) % modes.length];
      this.surfaceSnap.setMode(next);
      this.ui.setStatus(`Snap mode: ${next.toUpperCase()}`);
      e.preventDefault(); return;
    }

    // ── Multi-select delete ──
    if ((e.code === 'Delete' || e.code === 'Backspace') && this.multiSelect.hasMultiple()) {
      e.preventDefault();
      this.multiSelect.deleteAll();
      return;
    }

    // ── Catalog / placement keys ──
    if (this.catalogSystem.handleKey(e.code)) { e.preventDefault(); return; }
  }

  _onMouseMove(e) {
    if (!this.active) return;

    const cat    = this.catalogSystem;
    const snap   = this.surfaceSnap;

    if (cat._placing && cat._ghost) {
      const { point } = snap.snap(e.clientX, e.clientY, cat._ghost);
      if (cat._gridSnap) {
        cat._ghost.position.x = Math.round(point.x);
        cat._ghost.position.z = Math.round(point.z);
      } else {
        cat._ghost.position.x = point.x;
        cat._ghost.position.z = point.z;
      }
      cat._ghost.position.y = point.y;

      // Broadcast cursor position to collaborators
      this.collabSystem.broadcastCursorThrottled(point.x, point.y, point.z);
    }
  }

  _onClick(e) {
    if (!this.active) return;

    const cat = this.catalogSystem;

    // Ghost active → place with surface snap
    if (cat._placing && cat._ghost) {
      const { point } = this.surfaceSnap.snap(e.clientX, e.clientY, cat._ghost);
      cat.placeObject(point);
      return;
    }

    // Raycast placed objects
    const id = cat.raycastObjects(e.clientX, e.clientY);

    if (id) {
      const entry = cat.getEntryById(id);
      if (entry) {
        if (e.shiftKey) {
          // Shift+click → multi-select
          this.multiSelect.add(entry);
        } else {
          this.multiSelect.clear();
          this.transformSystem.select(entry);
          this.hierarchyPanel.refresh();
        }
      }
    } else {
      if (!e.shiftKey) {
        this.transformSystem.deselect();
        this.multiSelect.clear();
      }
    }
  }
}
