/**
 * PerformanceManager — frustum culling, dynamic light limiting,
 * and basic performance monitoring.
 */
import * as THREE from 'three';

const MAX_ACTIVE_POINT_LIGHTS = 8;

export class PerformanceManager {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx = builder.ctx;

    this._frustum    = new THREE.Frustum();
    this._projMatrix = new THREE.Matrix4();
    this._boxHelper  = new THREE.Box3();

    // Stats
    this._frameCount   = 0;
    this._fpsLastTime  = performance.now();
    this._fps          = 0;
  }

  // ─────────────────────────────────────────────────────────
  // TICK
  // ─────────────────────────────────────────────────────────

  tick() {
    this._frameCount++;
    const now = performance.now();
    if (now - this._fpsLastTime >= 1000) {
      this._fps         = this._frameCount;
      this._frameCount  = 0;
      this._fpsLastTime = now;
    }

    this._updateFrustumCulling();
    this._limitActiveLights();
  }

  // ─────────────────────────────────────────────────────────
  // FRUSTUM CULLING
  // Objects outside camera frustum are made invisible (skip GPU)
  // Three.js does built-in frustum culling for individual meshes,
  // but we additionally cull entire world-object groups for lights.
  // ─────────────────────────────────────────────────────────

  _updateFrustumCulling() {
    this._projMatrix.multiplyMatrices(
      this.ctx.cam.projectionMatrix,
      this.ctx.cam.matrixWorldInverse
    );
    this._frustum.setFromProjectionMatrix(this._projMatrix);

    this.builder.catalogSystem.getObjects().forEach(entry => {
      if (!entry.mesh) return;
      this._boxHelper.setFromObject(entry.mesh);
      // Expand slightly to avoid pop-in at frustum edges
      this._boxHelper.expandByScalar(2);
      const visible = this._frustum.intersectsBox(this._boxHelper);
      // Only update if changed (avoids unnecessary GPU state changes)
      if (entry.mesh.visible !== visible) entry.mesh.visible = visible;
    });
  }

  // ─────────────────────────────────────────────────────────
  // LIGHT LIMITING
  // Too many PointLights kill performance. Keep only the N closest
  // to the camera target active at a time.
  // ─────────────────────────────────────────────────────────

  _limitActiveLights() {
    const camTarget = this.ctx.orbitControls.target;

    const lights = [];
    this.builder.catalogSystem.getObjects().forEach(entry => {
      if (!entry.mesh) return;
      entry.mesh.traverse(c => {
        if ((c.isPointLight || c.isSpotLight) && c.userData.nexusDynamicLight) {
          const wp = new THREE.Vector3();
          c.getWorldPosition(wp);
          lights.push({ light: c, dist: wp.distanceTo(camTarget) });
        }
      });
    });

    // Sort by distance (closest first)
    lights.sort((a, b) => a.dist - b.dist);

    lights.forEach(({ light }, i) => {
      light.visible = i < MAX_ACTIVE_POINT_LIGHTS;
    });
  }

  // ─────────────────────────────────────────────────────────
  // STATS
  // ─────────────────────────────────────────────────────────

  getStats() {
    const renderer = this.ctx.renderer;
    const info = renderer.info;
    return {
      fps:         this._fps,
      triangles:   info.render?.triangles ?? 0,
      calls:       info.render?.calls ?? 0,
      geometries:  info.memory?.geometries ?? 0,
      textures:    info.memory?.textures ?? 0,
      objects:     this.builder.catalogSystem.getObjects().length,
    };
  }

  formatStats() {
    const s = this.getStats();
    return `FPS: ${s.fps} | Tris: ${(s.triangles / 1000).toFixed(1)}k | DC: ${s.calls} | Objs: ${s.objects}`;
  }
}
