/**
 * LightSystem — discovers and controls existing scene lights,
 * and manages per-object Point/SpotLights.
 */
import * as THREE from 'three';

export class LightSystem {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx = builder.ctx;

    // Discovered scene lights (read from scene on init)
    this.ambient    = null;  // THREE.AmbientLight
    this.sun        = null;  // THREE.DirectionalLight (main)
    this.fill       = null;  // THREE.DirectionalLight (fill)
    this.zenith     = null;  // THREE.DirectionalLight (zenith)
    this.hemi       = null;  // THREE.HemisphereLight
    this._shadowsEnabled = true;

    this._discoverSceneLights();
  }

  // ─────────────────────────────────────────────────────────
  // DISCOVERY
  // ─────────────────────────────────────────────────────────

  _discoverSceneLights() {
    const dirs = [];
    this.ctx.scene.traverse(obj => {
      if (obj.isAmbientLight)      this.ambient = obj;
      else if (obj.isHemisphereLight) this.hemi = obj;
      else if (obj.isDirectionalLight) dirs.push(obj);
    });

    // Heuristic: sort directionals by intensity descending
    dirs.sort((a, b) => b.intensity - a.intensity);
    if (dirs.length > 0) this.sun    = dirs[0];  // strongest = sun
    if (dirs.length > 1) this.zenith = dirs[1];  // zenith
    if (dirs.length > 2) this.fill   = dirs[2];  // fill
  }

  // ─────────────────────────────────────────────────────────
  // GLOBAL CONTROLS
  // ─────────────────────────────────────────────────────────

  setAmbientColor(hexStr) {
    if (!this.ambient) return;
    this.ambient.color.set(hexStr);
  }

  setAmbientIntensity(value) {
    if (!this.ambient) return;
    this.ambient.intensity = Math.max(0, Math.min(10, Number(value)));
  }

  setSunColor(hexStr) {
    if (!this.sun) return;
    this.sun.color.set(hexStr);
  }

  setSunIntensity(value) {
    if (!this.sun) return;
    this.sun.intensity = Math.max(0, Math.min(20, Number(value)));
  }

  setSunPosition(x, y, z) {
    if (!this.sun) return;
    this.sun.position.set(Number(x), Number(y), Number(z));
  }

  setHemiIntensity(value) {
    if (!this.hemi) return;
    this.hemi.intensity = Math.max(0, Math.min(10, Number(value)));
  }

  setHemiSkyColor(hexStr) {
    if (!this.hemi) return;
    this.hemi.color.set(hexStr);
  }

  setHemiGroundColor(hexStr) {
    if (!this.hemi) return;
    this.hemi.groundColor.set(hexStr);
  }

  toggleShadows(enabled) {
    this._shadowsEnabled = enabled;
    this.ctx.renderer.shadowMap.enabled = enabled;
    if (this.sun) this.sun.castShadow = enabled;
    this.ctx.scene.traverse(o => {
      if (o.isMesh) {
        o.castShadow    = enabled;
        o.receiveShadow = enabled;
      }
    });
  }

  setShadowQuality(size) {
    // size: 512 | 1024 | 2048 | 4096
    if (!this.sun?.shadow) return;
    this.sun.shadow.mapSize.set(size, size);
    this.sun.shadow.map?.dispose();
    this.sun.shadow.map = null;
  }

  getGlobalValues() {
    return {
      ambientColor:    this.ambient ? '#' + this.ambient.color.getHexString()  : '#2a4060',
      ambientIntensity: this.ambient ? this.ambient.intensity                  : 2.0,
      sunColor:        this.sun     ? '#' + this.sun.color.getHexString()      : '#90b8ec',
      sunIntensity:    this.sun     ? this.sun.intensity                       : 3.1,
      sunX:            this.sun     ? this.sun.position.x                      : 28,
      sunY:            this.sun     ? this.sun.position.y                      : 52,
      sunZ:            this.sun     ? this.sun.position.z                      : 18,
      hemiIntensity:   this.hemi    ? this.hemi.intensity                      : 1.35,
      hemiSkyColor:    this.hemi    ? '#' + this.hemi.color.getHexString()     : '#396080',
      hemiGroundColor: this.hemi    ? '#' + this.hemi.groundColor.getHexString() : '#101a30',
      shadowsEnabled:  this._shadowsEnabled,
    };
  }

  // ─────────────────────────────────────────────────────────
  // PER-OBJECT LIGHTS
  // ─────────────────────────────────────────────────────────

  /** Adds a PointLight or SpotLight to the selected object. */
  addObjectLight(entry, type = 'point', config = {}) {
    // Remove any existing dynamic light first
    this.removeObjectLight(entry);

    const {
      color     = 0xffffff,
      intensity = 1.5,
      distance  = 12,
      height    = 1.5,
    } = config;

    let light;
    if (type === 'spot') {
      light = new THREE.SpotLight(color, intensity, distance, Math.PI / 6, 0.3, 2);
      light.target.position.set(0, 0, 0);
      entry.mesh.add(light.target);
    } else {
      light = new THREE.PointLight(color, intensity, distance, 2);
    }
    light.castShadow = false;
    light.position.set(0, height, 0);
    light.userData.nexusDynamicLight = true;
    entry.mesh.add(light);

    // Ground glow halo
    const glowMesh = new THREE.Mesh(
      new THREE.CircleGeometry(1.8, 32),
      new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.22, depthWrite: false, side: THREE.DoubleSide })
    );
    glowMesh.rotation.x = -Math.PI / 2;
    glowMesh.position.y = 0.02;
    glowMesh.userData.wbPulseBase = 0.22;
    entry.mesh.userData.wbGlowMesh = glowMesh;
    entry.mesh.add(glowMesh);

    return light;
  }

  removeObjectLight(entry) {
    const toRemove = [];
    entry.mesh.traverse(c => {
      if (c.isPointLight  && c.userData.nexusDynamicLight) toRemove.push(c);
      if (c.isSpotLight   && c.userData.nexusDynamicLight) toRemove.push(c);
    });
    toRemove.forEach(c => entry.mesh.remove(c));

    // Remove glow halo if present
    const glow = entry.mesh.userData.wbGlowMesh;
    if (glow) { entry.mesh.remove(glow); delete entry.mesh.userData.wbGlowMesh; }
  }

  getObjectLightValues(entry) {
    let light = null;
    entry.mesh.traverse(c => {
      if ((c.isPointLight || c.isSpotLight) && c.userData.nexusDynamicLight) light = c;
    });
    if (!light) return null;
    return {
      type:      light.isSpotLight ? 'spot' : 'point',
      color:     '#' + light.color.getHexString(),
      intensity: light.intensity,
      distance:  light.distance,
      height:    light.position.y,
    };
  }

  setObjectLightColor(entry, hexStr) {
    entry.mesh.traverse(c => {
      if ((c.isPointLight || c.isSpotLight) && c.userData.nexusDynamicLight) c.color.set(hexStr);
    });
  }

  setObjectLightIntensity(entry, value) {
    entry.mesh.traverse(c => {
      if ((c.isPointLight || c.isSpotLight) && c.userData.nexusDynamicLight) c.intensity = Number(value);
    });
  }

  setObjectLightDistance(entry, value) {
    entry.mesh.traverse(c => {
      if ((c.isPointLight || c.isSpotLight) && c.userData.nexusDynamicLight) c.distance = Number(value);
    });
  }

  setObjectLightHeight(entry, value) {
    entry.mesh.traverse(c => {
      if ((c.isPointLight || c.isSpotLight) && c.userData.nexusDynamicLight) c.position.y = Number(value);
    });
  }
}
