/**
 * EnvironmentSystem — HDRI environment maps, sky presets, and fog control.
 * Uses RGBELoader + PMREMGenerator for physically correct IBL reflections.
 */
import * as THREE from 'three';
import { RGBELoader } from 'three/addons/loaders/RGBELoader.js';

/** Built-in sky/HDRI presets. Add more .hdr/.exr URLs as needed. */
export const ENVIRONMENT_PRESETS = [
  { id: 'none',        label: 'None',            hdr: null },
  { id: 'studio',      label: 'Studio',          hdr: 'https://dl.polyhaven.org/file/ph-assets/HDRIs/hdr/1k/studio_small_08_1k.hdr' },
  { id: 'sunset',      label: 'Sunset',          hdr: 'https://dl.polyhaven.org/file/ph-assets/HDRIs/hdr/1k/sunset_jhbcentral_1k.hdr' },
  { id: 'city_night',  label: 'City Night',      hdr: 'https://dl.polyhaven.org/file/ph-assets/HDRIs/hdr/1k/night_street_1k.hdr' },
  { id: 'space',       label: 'Space',           hdr: 'https://dl.polyhaven.org/file/ph-assets/HDRIs/hdr/1k/space_1k.hdr' },
  { id: 'overcast',    label: 'Overcast Sky',    hdr: 'https://dl.polyhaven.org/file/ph-assets/HDRIs/hdr/1k/overcast_soil_puresky_1k.hdr' },
];

export class EnvironmentSystem {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder   = builder;
    this.ctx       = builder.ctx;

    this._loader   = new RGBELoader();
    this._pmrem    = new THREE.PMREMGenerator(this.ctx.renderer);
    this._pmrem.compileEquirectangularShader();

    this._currentPreset   = 'none';
    this._envTexture      = null;
    this._envIntensity    = 1.0;
    this._bgMode          = 'original'; // 'original' | 'hdri' | 'color'
    this._bgColor         = '#010508';
    this._originalBg      = this.ctx.scene.background;
    this._originalEnv     = this.ctx.scene.environment;
    this._loading         = false;

    // Fog
    this._fogEnabled      = !!this.ctx.scene.fog;
    this._fogColor        = this.ctx.scene.fog?.color?.getHex?.() ?? 0x010614;
    this._fogDensity      = this.ctx.scene.fog?.density ?? 0.007;
  }

  // ─────────────────────────────────────────────────────────
  // HDRI LOADING
  // ─────────────────────────────────────────────────────────

  async loadPreset(presetId) {
    const preset = ENVIRONMENT_PRESETS.find(p => p.id === presetId);
    if (!preset) return;
    this._currentPreset = presetId;

    if (!preset.hdr) {
      this.clearEnvironment();
      this.builder.ui.setStatus('Environment: None');
      return;
    }

    this.builder.ui.setStatus(`Loading environment: ${preset.label}…`);
    this._loading = true;

    try {
      const texture = await new Promise((res, rej) =>
        this._loader.load(preset.hdr, res, null, rej)
      );
      this._applyHdrTexture(texture, preset.label);
    } catch (err) {
      console.warn('[EnvSystem] HDR load failed:', err.message || err);
      this.builder.ui.setStatus(`⚠ Failed to load ${preset.label}`);
    } finally {
      this._loading = false;
    }
  }

  async loadCustomHDR(url) {
    this.builder.ui.setStatus('Loading custom HDR…');
    this._loading = true;
    try {
      const texture = await new Promise((res, rej) =>
        this._loader.load(url, res, null, rej)
      );
      this._applyHdrTexture(texture, 'Custom');
      this._currentPreset = 'custom';
    } catch (err) {
      console.warn('[EnvSystem] custom HDR load failed:', err);
      this.builder.ui.setStatus('⚠ HDR load failed');
    } finally {
      this._loading = false;
    }
  }

  _applyHdrTexture(texture, label) {
    // Dispose old env texture
    if (this._envTexture) { this._envTexture.dispose(); this._envTexture = null; }

    const envMap = this._pmrem.fromEquirectangular(texture).texture;
    texture.dispose();

    this._envTexture = envMap;
    this.ctx.scene.environment = envMap;

    if (this._bgMode === 'hdri') {
      this.ctx.scene.background = envMap;
    }

    // Apply envMapIntensity to all standard materials
    this._setEnvIntensity(this._envIntensity);

    this.builder.ui.setStatus(`Environment: ${label}`);
    this.builder.ui.refreshLightingTab();
  }

  clearEnvironment() {
    if (this._envTexture) { this._envTexture.dispose(); this._envTexture = null; }
    this.ctx.scene.environment = this._originalEnv;
    if (this._bgMode === 'hdri') this.setBackgroundMode('original');
    this._currentPreset = 'none';
  }

  // ─────────────────────────────────────────────────────────
  // BACKGROUND MODE
  // ─────────────────────────────────────────────────────────

  setBackgroundMode(mode) {
    this._bgMode = mode;
    switch (mode) {
      case 'hdri':
        this.ctx.scene.background = this._envTexture || this._originalBg;
        break;
      case 'color':
        this.ctx.scene.background = new THREE.Color(this._bgColor);
        break;
      case 'original':
      default:
        this.ctx.scene.background = this._originalBg;
        break;
    }
  }

  setBackgroundColor(hexStr) {
    this._bgColor = hexStr;
    if (this._bgMode === 'color') this.ctx.scene.background = new THREE.Color(hexStr);
  }

  // ─────────────────────────────────────────────────────────
  // ENV INTENSITY
  // ─────────────────────────────────────────────────────────

  setEnvIntensity(value) {
    this._envIntensity = Math.max(0, Math.min(5, Number(value)));
    this._setEnvIntensity(this._envIntensity);
  }

  _setEnvIntensity(value) {
    this.ctx.scene.traverse(o => {
      if (!o.isMesh || !o.material) return;
      const mats = Array.isArray(o.material) ? o.material : [o.material];
      mats.forEach(m => {
        if (m.isMeshStandardMaterial || m.isMeshPhysicalMaterial) {
          m.envMapIntensity = value;
          m.needsUpdate = true;
        }
      });
    });
  }

  // ─────────────────────────────────────────────────────────
  // FOG
  // ─────────────────────────────────────────────────────────

  setFogEnabled(enabled) {
    this._fogEnabled = enabled;
    if (enabled) {
      this.ctx.scene.fog = new THREE.FogExp2(this._fogColor, this._fogDensity);
    } else {
      this.ctx.scene.fog = null;
    }
  }

  setFogColor(hexStr) {
    this._fogColor = new THREE.Color(hexStr).getHex();
    if (this.ctx.scene.fog) this.ctx.scene.fog.color.set(hexStr);
  }

  setFogDensity(value) {
    this._fogDensity = Math.max(0, Math.min(0.1, Number(value)));
    if (this.ctx.scene.fog) this.ctx.scene.fog.density = this._fogDensity;
  }

  // ─────────────────────────────────────────────────────────
  // STATE
  // ─────────────────────────────────────────────────────────

  getValues() {
    return {
      preset:       this._currentPreset,
      bgMode:       this._bgMode,
      bgColor:      this._bgColor,
      envIntensity: this._envIntensity,
      fogEnabled:   this._fogEnabled,
      fogColor:     '#' + new THREE.Color(this._fogColor).getHexString(),
      fogDensity:   this._fogDensity,
      loading:      this._loading,
    };
  }

  dispose() {
    this._pmrem.dispose();
    if (this._envTexture) this._envTexture.dispose();
  }
}
