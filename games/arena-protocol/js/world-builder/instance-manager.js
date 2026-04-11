/**
 * InstanceManager — automatically switches repeated models to THREE.InstancedMesh
 * when the same model_url appears 3 or more times in the scene.
 *
 * Flow:
 *   onObjectPlaced(entry, modelUrl) → count++
 *     If count >= INSTANCE_THRESHOLD → switchToInstancing(modelUrl)
 *   onObjectDeleted(entry, modelUrl) → count--
 *     If count < INSTANCE_THRESHOLD → switchToIndividual(modelUrl)
 *
 * Instancing constraints:
 *   - All instances share the same geometry + material from the first loaded mesh.
 *   - Per-object material overrides are NOT preserved in instanced mode.
 *   - Individual transform (pos/rot/scale) is preserved via InstancedMesh.setMatrixAt.
 */
import * as THREE from 'three';

const INSTANCE_THRESHOLD = 3;
const MAX_INSTANCE_COUNT = 256; // pre-allocate InstancedMesh capacity

export class InstanceManager {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder = builder;
    this.ctx     = builder.ctx;

    /** modelUrl → { entries: Set, instancedMesh: THREE.InstancedMesh|null, isInstanced: bool } */
    this._groups = new Map();

    this._dummy = new THREE.Object3D();
  }

  // ─────────────────────────────────────────────────────────
  // TRACKING
  // ─────────────────────────────────────────────────────────

  onObjectPlaced(entry, modelUrl) {
    if (!modelUrl) return; // procedural fallback objects, skip
    if (!this._groups.has(modelUrl)) {
      this._groups.set(modelUrl, { entries: new Set(), instancedMesh: null, isInstanced: false });
    }
    const group = this._groups.get(modelUrl);
    group.entries.add(entry);

    if (!group.isInstanced && group.entries.size >= INSTANCE_THRESHOLD) {
      this._switchToInstancing(modelUrl, group);
    } else if (group.isInstanced) {
      // Add new instance to existing InstancedMesh
      this._updateInstanceMatrices(group);
    }
  }

  onObjectDeleted(entry, modelUrl) {
    if (!modelUrl) return;
    const group = this._groups.get(modelUrl);
    if (!group) return;
    group.entries.delete(entry);

    if (group.isInstanced && group.entries.size < INSTANCE_THRESHOLD) {
      this._switchToIndividual(modelUrl, group);
    } else if (group.isInstanced) {
      this._updateInstanceMatrices(group);
    }

    if (group.entries.size === 0) this._groups.delete(modelUrl);
  }

  // ─────────────────────────────────────────────────────────
  // SWITCHING TO INSTANCED
  // ─────────────────────────────────────────────────────────

  _switchToInstancing(modelUrl, group) {
    // Get geometry + material from the first entry's mesh
    const firstEntry = [...group.entries][0];
    const sourceMesh = this._findFirstMesh(firstEntry.mesh);
    if (!sourceMesh) return; // no renderable mesh, skip

    const geo  = sourceMesh.geometry;
    const mat  = sourceMesh.material;
    if (!geo || !mat) return;

    // Create InstancedMesh with capacity for MAX_INSTANCE_COUNT
    const iMesh = new THREE.InstancedMesh(geo, mat, MAX_INSTANCE_COUNT);
    iMesh.name              = `_instanced_${Date.now()}`;
    iMesh.castShadow        = true;
    iMesh.receiveShadow     = true;
    iMesh.userData.instMgr  = true;
    iMesh.userData.modelUrl = modelUrl;

    group.instancedMesh = iMesh;
    group.isInstanced   = true;

    // Hide all individual meshes and record their transforms in the InstancedMesh
    this._updateInstanceMatrices(group);

    this.ctx.scene.add(iMesh);

    // Make individual meshes invisible (keep in scene for selection/raycasting)
    group.entries.forEach(entry => { entry.mesh.visible = false; });

    this.builder.ui.setStatus(
      `⚡ Instancing ${group.entries.size}× ${modelUrl.split('/').pop()}`
    );
    console.log(`[InstanceMgr] Switched to instancing: ${modelUrl} (${group.entries.size} instances)`);
  }

  _switchToIndividual(modelUrl, group) {
    if (group.instancedMesh) {
      this.ctx.scene.remove(group.instancedMesh);
      group.instancedMesh.dispose();
      group.instancedMesh = null;
    }
    group.isInstanced = false;

    // Re-show all individual meshes
    group.entries.forEach(entry => { entry.mesh.visible = true; });

    console.log(`[InstanceMgr] Reverted to individual: ${modelUrl} (${group.entries.size} instances)`);
  }

  // ─────────────────────────────────────────────────────────
  // MATRIX UPDATE
  // ─────────────────────────────────────────────────────────

  _updateInstanceMatrices(group) {
    const iMesh = group.instancedMesh;
    if (!iMesh) return;

    let i = 0;
    group.entries.forEach(entry => {
      if (i >= MAX_INSTANCE_COUNT) return;
      this._dummy.position.copy(entry.mesh.position);
      this._dummy.rotation.copy(entry.mesh.rotation);
      this._dummy.scale.copy(entry.mesh.scale);
      this._dummy.updateMatrix();
      iMesh.setMatrixAt(i, this._dummy.matrix);
      i++;
    });

    iMesh.count          = i;
    iMesh.instanceMatrix.needsUpdate = true;
  }

  /** Update specific entry's matrix after a transform change. */
  updateEntry(entry, modelUrl) {
    if (!modelUrl) return;
    const group = this._groups.get(modelUrl);
    if (!group?.isInstanced) return;
    this._updateInstanceMatrices(group);
  }

  // ─────────────────────────────────────────────────────────
  // HELPERS
  // ─────────────────────────────────────────────────────────

  _findFirstMesh(object3D) {
    let found = null;
    object3D.traverse(o => {
      if (!found && o.isMesh && o.geometry && o.material) found = o;
    });
    return found;
  }

  // ─────────────────────────────────────────────────────────
  // TICK — sync matrices every frame when instanced objects are being transformed
  // ─────────────────────────────────────────────────────────

  tick() {
    if (!this.builder.transformSystem.selectedEntry) return;
    const entry    = this.builder.transformSystem.selectedEntry;
    const item     = this.builder.catalogSystem.findCatalogEntry(entry.item_id);
    const modelUrl = item?.model;
    if (modelUrl) this.updateEntry(entry, modelUrl);
  }

  getStats() {
    let totalInstanced = 0;
    let groups = 0;
    this._groups.forEach(g => {
      if (g.isInstanced) { groups++; totalInstanced += g.entries.size; }
    });
    return { groups, totalInstanced };
  }
}
