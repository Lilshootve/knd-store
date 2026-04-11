/**
 * StateManager — saves and loads the complete scene state as JSON.
 * Priority: API (server DB) → localStorage fallback.
 */

const LS_KEY = 'nexus-wb-scene-v1';

export class StateManager {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx = builder.ctx;
  }

  // ─────────────────────────────────────────────────────────
  // BUILD STATE
  // ─────────────────────────────────────────────────────────

  buildSceneState() {
    const catalog = this.builder.catalogSystem;
    const lights  = this.builder.lightSystem;
    const lv      = lights.getGlobalValues();

    const objects = catalog.getObjects().map(entry => {
      const { mesh, id, item_id } = entry;
      const objLight = lights.getObjectLightValues(entry);
      const matVals  = this.builder.materialSystem.getValues(entry);
      return {
        id,
        item_id,
        pos_x:     mesh.position.x,
        pos_y:     mesh.position.y,
        pos_z:     mesh.position.z,
        rot_y:     mesh.rotation.y,
        scale:     mesh.scale.x,
        material:  matVals,
        light:     objLight,
      };
    });

    return {
      version:  2,
      saved_at: new Date().toISOString(),
      lights: {
        ambientColor:    lv.ambientColor,
        ambientIntensity: lv.ambientIntensity,
        sunColor:        lv.sunColor,
        sunIntensity:    lv.sunIntensity,
        sunX:            lv.sunX,
        sunY:            lv.sunY,
        sunZ:            lv.sunZ,
        hemiIntensity:   lv.hemiIntensity,
        hemiSkyColor:    lv.hemiSkyColor,
        hemiGroundColor: lv.hemiGroundColor,
        shadowsEnabled:  lv.shadowsEnabled,
      },
      objects,
    };
  }

  // ─────────────────────────────────────────────────────────
  // SAVE
  // ─────────────────────────────────────────────────────────

  async save() {
    const state = this.buildSceneState();
    const json  = JSON.stringify(state, null, 2);

    // Try localStorage as lightweight backup (server DB is the primary)
    try { localStorage.setItem(LS_KEY, json); } catch (_) {}

    this.ctx.fireEv('💾', 'WB', `Scene saved (${state.objects.length} objects)`, 'rgba(0,232,255,.7)');
    return state;
  }

  /** Export current state as downloadable JSON file. */
  exportJSON() {
    const state = this.buildSceneState();
    const blob  = new Blob([JSON.stringify(state, null, 2)], { type: 'application/json' });
    const url   = URL.createObjectURL(blob);
    const a     = document.createElement('a');
    a.href      = url;
    a.download  = `nexus-scene-${Date.now()}.json`;
    a.click();
    URL.revokeObjectURL(url);
    this.ctx.fireEv('📤', 'WB', 'Scene exported as JSON', 'rgba(155,48,255,.7)');
  }

  // ─────────────────────────────────────────────────────────
  // LOAD
  // ─────────────────────────────────────────────────────────

  loadFromLocalStorage() {
    try {
      const raw = localStorage.getItem(LS_KEY);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (_) { return null; }
  }

  /** Applies a scene state object (restores lights only; objects are already loaded by catalogSystem). */
  applyLightsFromState(state) {
    if (!state?.lights) return;
    const ls = state.lights;
    const L  = this.builder.lightSystem;
    if (ls.ambientColor)    L.setAmbientColor(ls.ambientColor);
    if (ls.ambientIntensity != null) L.setAmbientIntensity(ls.ambientIntensity);
    if (ls.sunColor)        L.setSunColor(ls.sunColor);
    if (ls.sunIntensity != null)    L.setSunIntensity(ls.sunIntensity);
    if (ls.sunX != null)    L.setSunPosition(ls.sunX, ls.sunY, ls.sunZ);
    if (ls.hemiIntensity != null)   L.setHemiIntensity(ls.hemiIntensity);
    if (ls.hemiSkyColor)    L.setHemiSkyColor(ls.hemiSkyColor);
    if (ls.hemiGroundColor) L.setHemiGroundColor(ls.hemiGroundColor);
    if (ls.shadowsEnabled != null)  L.toggleShadows(ls.shadowsEnabled);
    this.builder.ui.refreshLightingTab();
  }

  /** Import from a JSON file picked by user. */
  importFromFile(file) {
    const reader = new FileReader();
    reader.onload = e => {
      try {
        const state = JSON.parse(e.target.result);
        this.applyLightsFromState(state);
        this.ctx.fireEv('📥', 'WB', 'Scene lights imported', 'rgba(0,232,255,.6)');
      } catch (err) {
        this.ctx.fireEv('⚠', 'WB', 'Invalid scene file', 'rgba(255,160,80,.8)');
      }
    };
    reader.readAsText(file);
  }
}
