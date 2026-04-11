/**
 * CollabSystem — real-time collaborative world editing via WebSocket.
 *
 * Hooks into the existing Nexus WebSocket connection (window._nexusWs).
 * Message format: { type: 'wb_action', payload: { action, data, editor_id } }
 *
 * Actions broadcast: wb_place, wb_delete, wb_patch
 * On receive: apply action to local scene without re-broadcasting (to avoid loops).
 *
 * Conflict resolution: Last-write-wins (server timestamp arbitrates ordering).
 * Future: vector-clock OT for true conflict resolution.
 */

const MSG_TYPE = 'wb_action';
const COLLAB_COLORS = ['#ff3d56','#ffd600','#00ff88','#4488ff','#c158ff','#ff8833'];

export class CollabSystem {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder    = builder;
    this.ctx        = builder.ctx;
    this._editorId  = `ed_${Math.random().toString(36).slice(2,8)}`;
    this._enabled   = false;
    this._ws        = null;
    this._peers     = new Map(); // editorId → { name, color, cursor }

    // Cursor dots for remote editors (small colored spheres at their ghost position)
    this._cursorMeshes = new Map();

    this._pendingOps = []; // queue of remote ops received before scene is ready
  }

  // ─────────────────────────────────────────────────────────
  // CONNECTION
  // ─────────────────────────────────────────────────────────

  /** Connect to the existing Nexus WebSocket once it is ready. */
  attach() {
    // Poll for the existing WS connection used by the game
    const tryAttach = () => {
      const ws = window._nexusWs;
      if (!ws || ws.readyState !== WebSocket.OPEN) {
        setTimeout(tryAttach, 1000);
        return;
      }
      this._ws = ws;
      this._patchWsHandler();
      this._enabled = true;
      this._announce();
      console.log('[Collab] Attached to Nexus WebSocket');
    };
    tryAttach();
  }

  /** Patch the existing WS onmessage to intercept wb_action messages. */
  _patchWsHandler() {
    const ws = this._ws;
    const original = ws.onmessage;
    ws.onmessage = (event) => {
      try {
        const msg = JSON.parse(event.data);
        if (msg.type === MSG_TYPE) {
          this._onRemoteAction(msg.payload);
          return; // don't forward to game handler
        }
        if (msg.type === 'wb_cursor') {
          this._onRemoteCursor(msg.payload);
          return;
        }
        if (msg.type === 'wb_peer') {
          this._onPeerAnnounce(msg.payload);
          return;
        }
      } catch (_) {}
      if (original) original.call(ws, event);
    };
  }

  _announce() {
    this._send({
      type: 'wb_peer',
      payload: {
        editor_id: this._editorId,
        name:      window.gName || 'ADMIN',
        color:     COLLAB_COLORS[Math.floor(Math.random() * COLLAB_COLORS.length)],
      },
    });
  }

  // ─────────────────────────────────────────────────────────
  // BROADCAST
  // ─────────────────────────────────────────────────────────

  /** Broadcast a Command's side-effects to remote peers. */
  broadcastCommand(command) {
    if (!this._enabled) return;
    const sel = this.builder.transformSystem.selectedEntry;

    // Map command class name to protocol action
    const cls = command.constructor.name;
    if      (cls === 'PlaceObjectCommand')     this._broadcastPlace(command);
    else if (cls === 'DeleteObjectCommand')    this._broadcastDelete(command);
    else if (cls === 'TransformObjectCommand') this._broadcastPatch(command);
    // Material, light, etc.: not broadcast in this version (local-only)
  }

  _broadcastPlace(cmd) {
    if (!cmd._entry) return;
    const { id, item_id, mesh } = cmd._entry;
    this._send({
      type: MSG_TYPE,
      payload: {
        action:    'wb_place',
        editor_id: this._editorId,
        data: {
          server_id: id,
          item_id,
          model_url: cmd.item.model || null,
          pos_x:     mesh.position.x,
          pos_y:     mesh.position.y,
          pos_z:     mesh.position.z,
          rot_y:     mesh.rotation.y,
          scale:     mesh.scale.x,
        },
      },
    });
  }

  _broadcastDelete(cmd) {
    this._send({
      type: MSG_TYPE,
      payload: {
        action:    'wb_delete',
        editor_id: this._editorId,
        data: { id: cmd._entry.id },
      },
    });
  }

  _broadcastPatch(cmd) {
    const { id, mesh } = cmd.entry;
    this._send({
      type: MSG_TYPE,
      payload: {
        action:    'wb_patch',
        editor_id: this._editorId,
        data: {
          id,
          pos_x: mesh.position.x, pos_y: mesh.position.y, pos_z: mesh.position.z,
          rot_y: mesh.rotation.y,  scale: mesh.scale.x,
        },
      },
    });
  }

  /** Broadcast cursor position (ghost location while placing). */
  broadcastCursor(x, y, z) {
    if (!this._enabled) return;
    this._send({
      type: 'wb_cursor',
      payload: { editor_id: this._editorId, x, y, z },
    });
  }

  _send(msg) {
    if (!this._ws || this._ws.readyState !== WebSocket.OPEN) return;
    try { this._ws.send(JSON.stringify(msg)); } catch (_) {}
  }

  // ─────────────────────────────────────────────────────────
  // RECEIVE
  // ─────────────────────────────────────────────────────────

  async _onRemoteAction(payload) {
    if (payload.editor_id === this._editorId) return; // own echo

    const { action, data } = payload;
    const cat = this.builder.catalogSystem;

    if (action === 'wb_place') {
      // Check if already in scene (server persistence loaded it)
      if (cat._objectMap.has(data.server_id)) return;

      const item = cat.findCatalogEntry(data.item_id) || {
        id: data.item_id, name: data.item_id, model: data.model_url,
      };
      const mesh = await cat.buildWorldObject({ ...item, _noVariation: true });
      mesh.position.set(data.pos_x, data.pos_y, data.pos_z);
      mesh.rotation.y = data.rot_y;
      mesh.scale.setScalar(data.scale);
      mesh.userData.worldObjectId = data.server_id;
      this.ctx.scene.add(mesh);
      const entry = { id: data.server_id, item_id: data.item_id, mesh };
      cat._objects.push(entry);
      cat._objectMap.set(data.server_id, entry);
      this.builder.ui.refreshObjectsTab();
      this.ctx.fireEv('🤝', 'COLLAB', `${payload.editor_id} placed ${item.name}`, 'rgba(0,255,136,.6)');

    } else if (action === 'wb_delete') {
      const entry = cat._objectMap.get(data.id);
      if (!entry) return;
      this.ctx.scene.remove(entry.mesh);
      cat.disposeGroup(entry.mesh);
      const idx = cat._objects.indexOf(entry);
      if (idx >= 0) cat._objects.splice(idx, 1);
      cat._objectMap.delete(data.id);
      this.builder.ui.refreshObjectsTab();

    } else if (action === 'wb_patch') {
      const entry = cat._objectMap.get(data.id);
      if (!entry) return;
      entry.mesh.position.set(data.pos_x, data.pos_y, data.pos_z);
      entry.mesh.rotation.y = data.rot_y;
      entry.mesh.scale.setScalar(data.scale);
    }
  }

  _onRemoteCursor(payload) {
    if (payload.editor_id === this._editorId) return;
    const peer = this._peers.get(payload.editor_id);
    const color = peer?.color || '#00ff88';

    let dot = this._cursorMeshes.get(payload.editor_id);
    if (!dot) {
      const THREE = window._THREE_REF; // three is already in scope via module
      // Use a small glowing sphere
      dot = this._makeCursorDot(color);
      this.ctx.scene.add(dot);
      this._cursorMeshes.set(payload.editor_id, dot);
    }
    dot.position.set(payload.x, payload.y + 0.5, payload.z);
  }

  _makeCursorDot(color) {
    try {
      const g = new THREE.SphereGeometry(0.25, 8, 6);
      const m = new THREE.MeshStandardMaterial({ color, emissive: color, emissiveIntensity: 2 });
      return new THREE.Mesh(g, m);
    } catch (_) {
      return new THREE.Group();
    }
  }

  _onPeerAnnounce(payload) {
    this._peers.set(payload.editor_id, {
      name:  payload.name,
      color: payload.color,
    });
    this.builder.ui.refreshCollabPeers(this._peers);
  }

  // ─────────────────────────────────────────────────────────
  // PEERS
  // ─────────────────────────────────────────────────────────

  getPeers() { return this._peers; }
  isEnabled() { return this._enabled; }

  // ─────────────────────────────────────────────────────────
  // TICK — remove stale cursor dots
  // ─────────────────────────────────────────────────────────

  _cursorTimestamps = new Map();

  broadcastCursorThrottled(x, y, z) {
    const now = performance.now();
    const last = this._lastCursorBroadcast || 0;
    if (now - last < 100) return; // 10 fps max
    this._lastCursorBroadcast = now;
    this.broadcastCursor(x, y, z);
    this._cursorTimestamps.set(this._editorId, now);
  }

  tick() {
    // Remove cursor dots for peers that haven't moved in 5s
    const now = performance.now();
    this._cursorTimestamps.forEach((ts, id) => {
      if (now - ts > 5000) {
        const dot = this._cursorMeshes.get(id);
        if (dot) { this.ctx.scene.remove(dot); this._cursorMeshes.delete(id); }
        this._cursorTimestamps.delete(id);
      }
    });
  }
}
