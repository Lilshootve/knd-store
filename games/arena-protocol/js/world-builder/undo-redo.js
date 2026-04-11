/**
 * UndoRedo — CommandPattern implementation.
 * All builder mutations go through commands so they can be reversed.
 *
 * Usage:
 *   builder.undoRedo.execute(new PlaceObjectCommand(builder, item, pos, rot, scale));
 *   builder.undoRedo.undo();   // Ctrl+Z
 *   builder.undoRedo.redo();   // Ctrl+Y
 */

const MAX_HISTORY = 60;

// ─────────────────────────────────────────────────────────────────────────────
// BASE COMMAND
// ─────────────────────────────────────────────────────────────────────────────
export class Command {
  constructor(description = '') { this.description = description; }
  async execute() {}
  async undo()    {}
}

// ─────────────────────────────────────────────────────────────────────────────
// CONCRETE COMMANDS
// ─────────────────────────────────────────────────────────────────────────────

/** Place a single world object. */
export class PlaceObjectCommand extends Command {
  constructor(builder, item, pos, rotY, scale) {
    super(`Place ${item.name}`);
    this.builder = builder;
    this.item    = item;
    this.pos     = pos.clone();
    this.rotY    = rotY;
    this.scale   = scale;
    this._entry  = null;
  }

  async execute() {
    const cat  = this.builder.catalogSystem;
    const mesh = await cat.buildWorldObject({ ...this.item, _noVariation: true });
    mesh.position.copy(this.pos);
    mesh.rotation.y = this.rotY;
    mesh.scale.setScalar(this.scale);
    this.builder.ctx.scene.add(mesh);

    try {
      const body = {
        action: 'place', item_id: this.item.id,
        model_url: this.item.model || null,
        pos_x: this.pos.x, pos_y: this.pos.y, pos_z: this.pos.z,
        rot_y: this.rotY,  scale: this.scale,
      };
      if (this.item.light) body.light_data = JSON.stringify(this.item.light);
      const res = await fetch('/api/nexus/world_builder.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin', body: JSON.stringify(body),
      });
      const j = await res.json();
      if (j.ok) {
        mesh.userData.worldObjectId = j.data.id;
        this._entry = { id: j.data.id, item_id: this.item.id, mesh };
      } else {
        const tmp = 'tmp_' + Date.now();
        mesh.userData.worldObjectId = tmp;
        this._entry = { id: tmp, item_id: this.item.id, mesh };
      }
    } catch (_) {
      const tmp = 'tmp_' + Date.now();
      mesh.userData.worldObjectId = tmp;
      this._entry = { id: tmp, item_id: this.item.id, mesh };
    }

    cat._objects.push(this._entry);
    cat._objectMap.set(this._entry.id, this._entry);
    this.builder.instanceManager.onObjectPlaced(this._entry, this.item.model);
    this.builder.ui.refreshObjectsTab();
  }

  async undo() {
    if (!this._entry) return;
    this.builder.transformSystem.deselect();
    const { mesh, id } = this._entry;
    this.builder.ctx.scene.remove(mesh);
    this.builder.catalogSystem.disposeGroup(mesh);
    const idx = this.builder.catalogSystem._objects.indexOf(this._entry);
    if (idx >= 0) this.builder.catalogSystem._objects.splice(idx, 1);
    this.builder.catalogSystem._objectMap.delete(id);
    this.builder.instanceManager.onObjectDeleted(this._entry, this.item.model);
    if (!String(id).startsWith('tmp_')) {
      try {
        await fetch('/api/nexus/world_builder.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ action: 'delete', id }),
        });
      } catch (_) {}
    }
    this.builder.ui.refreshObjectsTab();
  }
}

/** Delete a single world object. */
export class DeleteObjectCommand extends Command {
  constructor(builder, entry) {
    super(`Delete ${entry.item_id}`);
    this.builder = builder;
    this._entry  = entry;
    const { mesh } = entry;
    this._snapshot = {
      pos:   mesh.position.clone(),
      rotY:  mesh.rotation.y,
      scale: mesh.scale.x,
    };
    this._item = builder.catalogSystem.findCatalogEntry(entry.item_id) || { id: entry.item_id, name: entry.item_id };
  }

  async execute() {
    this.builder.transformSystem.deselect();
    const { mesh, id } = this._entry;
    this.builder.ctx.scene.remove(mesh);
    this.builder.catalogSystem.disposeGroup(mesh);
    const idx = this.builder.catalogSystem._objects.indexOf(this._entry);
    if (idx >= 0) this.builder.catalogSystem._objects.splice(idx, 1);
    this.builder.catalogSystem._objectMap.delete(id);
    this.builder.instanceManager.onObjectDeleted(this._entry, this._item.model);
    if (!String(id).startsWith('tmp_')) {
      try {
        await fetch('/api/nexus/world_builder.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ action: 'delete', id }),
        });
      } catch (_) {}
    }
    this.builder.ui.refreshObjectsTab();
  }

  async undo() {
    // Re-place the object
    const placeCmd = new PlaceObjectCommand(
      this.builder, this._item,
      this._snapshot.pos, this._snapshot.rotY, this._snapshot.scale
    );
    await placeCmd.execute();
  }
}

/** Move/rotate/scale an existing object (records before + after). */
export class TransformObjectCommand extends Command {
  constructor(builder, entry, before, after) {
    super(`Transform ${entry.item_id}`);
    this.builder = builder;
    this.entry   = entry;
    this.before  = { ...before }; // { pos, rotY, scale }
    this.after   = { ...after };
  }

  async execute() { this._apply(this.after);  }
  async undo()    { this._apply(this.before); }

  _apply(state) {
    const { mesh, id } = this.entry;
    mesh.position.copy(state.pos);
    mesh.rotation.y = state.rotY;
    mesh.scale.setScalar(state.scale);
    this.builder.catalogSystem.patchObject(id, {
      pos_x: state.pos.x, pos_y: state.pos.y, pos_z: state.pos.z,
      rot_y: state.rotY, scale: state.scale,
    });
    this.builder.ui.refreshTransformInputs();
  }
}

/** Material property change. */
export class MaterialChangeCommand extends Command {
  constructor(builder, entry, propName, valueBefore, valueAfter, applyFn) {
    super(`Material ${propName}`);
    this.builder      = builder;
    this.entry        = entry;
    this.propName     = propName;
    this.valueBefore  = valueBefore;
    this.valueAfter   = valueAfter;
    this.applyFn      = applyFn; // (value) => void
  }

  async execute() { this.applyFn(this.valueAfter);  }
  async undo()    { this.applyFn(this.valueBefore); }
}

/** Duplicate command — wraps PlaceObjectCommand. */
export class DuplicateObjectCommand extends Command {
  constructor(builder, entry) {
    super(`Duplicate ${entry.item_id}`);
    this.builder     = builder;
    this.sourceEntry = entry;
    this._innerCmd   = null;
  }

  async execute() {
    const cat  = this.builder.catalogSystem;
    const item = cat.findCatalogEntry(this.sourceEntry.item_id);
    if (!item) return;
    const { mesh } = this.sourceEntry;
    const pos = mesh.position.clone().add(new THREE.Vector3(1.5, 0, 0));
    this._innerCmd = new PlaceObjectCommand(this.builder, item, pos, mesh.rotation.y, mesh.scale.x);
    await this._innerCmd.execute();
  }

  async undo() { if (this._innerCmd) await this._innerCmd.undo(); }
}

/** Reparent object in hierarchy. */
export class ReparentCommand extends Command {
  constructor(builder, entry, oldParentId, newParentId) {
    super(`Reparent ${entry.item_id}`);
    this.builder     = builder;
    this.entry       = entry;
    this.oldParentId = oldParentId;
    this.newParentId = newParentId;
  }

  async execute() { this._applyParent(this.newParentId); }
  async undo()    { this._applyParent(this.oldParentId); }

  _applyParent(parentId) {
    const cat    = this.builder.catalogSystem;
    const child  = this.entry.mesh;
    const parent = parentId ? cat._objectMap.get(parentId)?.mesh : null;

    child.parent?.remove(child);
    if (parent) {
      parent.attach(child);
    } else {
      this.builder.ctx.scene.add(child);
    }
    this.builder.hierarchyPanel?.refresh();
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// UNDO-REDO MANAGER
// ─────────────────────────────────────────────────────────────────────────────
export class UndoRedoManager {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder  = builder;
    this._past    = []; // executed commands
    this._future  = []; // undone commands (for redo)
  }

  /** Execute a command and push it to history. Clears redo stack. */
  async execute(command) {
    await command.execute();
    this._past.push(command);
    if (this._past.length > MAX_HISTORY) this._past.shift();
    this._future = [];
    this._updateUI();
    this.builder.collabSystem.broadcastCommand(command);
  }

  async undo() {
    if (!this._past.length) return;
    const cmd = this._past.pop();
    await cmd.undo();
    this._future.push(cmd);
    this._updateUI();
    this.builder.ui.setStatus(`↩ Undone: ${cmd.description}`);
  }

  async redo() {
    if (!this._future.length) return;
    const cmd = this._future.pop();
    await cmd.execute();
    this._past.push(cmd);
    this._updateUI();
    this.builder.ui.setStatus(`↪ Redone: ${cmd.description}`);
  }

  /** Called for remote (collab) commands — does not push to local past. */
  async applyRemote(command) {
    await command.execute();
    this._updateUI();
  }

  canUndo() { return this._past.length > 0; }
  canRedo() { return this._future.length > 0; }
  getHistory() { return this._past.slice().reverse().map(c => c.description); }

  _updateUI() {
    this.builder.ui.refreshUndoRedoState(this.canUndo(), this.canRedo());
  }

  /** Handle keyboard shortcuts. Returns true if consumed. */
  handleKey(e) {
    if (!e.ctrlKey && !e.metaKey) return false;
    if (e.code === 'KeyZ') { this.undo(); return true; }
    if (e.code === 'KeyY') { this.redo(); return true; }
    return false;
  }
}
