/**
 * Nexus district rooms — WebSocket presence + remote avatars (nexus-ws.js protocol).
 * Filters peers by district_id on the client; move events are global from the server.
 */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

function normalizeNexusWsUrl(raw) {
  let u = String(raw || '').trim();
  if (!u) return '';
  while (u.endsWith('/')) u = u.slice(0, -1);
  try {
    const p = new URL(u);
    if ((p.pathname === '/' || p.pathname === '') && (p.protocol === 'wss:' || p.protocol === 'ws:')) {
      u = `${p.protocol}//${p.host}`;
    }
  } catch (_) { /* keep u */ }
  return u;
}

function getNexusWsUrl() {
  if (typeof window.NEXUS_WS_URL === 'string' && window.NEXUS_WS_URL.trim()) {
    return normalizeNexusWsUrl(window.NEXUS_WS_URL.trim());
  }
  const meta = document.querySelector('meta[name="nexus-ws-url"]');
  const fromMeta = meta && meta.getAttribute('content') && meta.getAttribute('content').trim();
  if (fromMeta) return normalizeNexusWsUrl(fromMeta);
  const proto = location.protocol === 'https:' ? 'wss' : 'ws';
  return `${proto}://${location.hostname}:8765`;
}

function makeLabelSprite(name, color) {
  const c = document.createElement('canvas');
  c.width = 256;
  c.height = 52;
  const ctx = c.getContext('2d');
  ctx.fillStyle = 'rgba(0,0,0,0.55)';
  ctx.fillRect(6, 6, 244, 40);
  ctx.strokeStyle = color;
  ctx.lineWidth = 1.5;
  ctx.strokeRect(6, 6, 244, 40);
  ctx.fillStyle = '#fff';
  ctx.font = 'bold 13px Orbitron, Share Tech Mono, monospace';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(String(name || '?').slice(0, 18), 128, 26);
  const tex = new THREE.CanvasTexture(c);
  const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false });
  const sprite = new THREE.Sprite(mat);
  sprite.scale.set(2.4, 0.45, 1);
  return sprite;
}

function normalizeGltfScene(root) {
  root.traverse((o) => {
    if (!o.isMesh) return;
    const m = o.material;
    if (!m) return;
    if (m.isMeshPhysicalMaterial) {
      o.material = new THREE.MeshStandardMaterial({
        color: m.color,
        map: m.map,
        normalMap: m.normalMap,
        roughnessMap: m.roughnessMap,
        metalnessMap: m.metalnessMap,
        emissiveMap: m.emissiveMap,
        emissive: m.emissive,
        roughness: m.roughness ?? 0.7,
        metalness: m.metalness ?? 0,
        transparent: m.transparent,
        opacity: m.opacity,
        side: m.side,
      });
    }
    ['map', 'emissiveMap'].forEach((k) => {
      if (m[k]) m[k].colorSpace = THREE.SRGBColorSpace;
    });
    o.castShadow = true;
    o.receiveShadow = true;
  });
}

function capColor(hex) {
  return typeof hex === 'string' && /^#[0-9a-fA-F]{6}$/.test(hex) ? hex : '#00e8ff';
}

/**
 * @param {object} o
 * @param {THREE.Scene} o.scene
 * @param {string} o.districtId - casino | agora | tesla | olimpo | sanctum
 * @param {number} o.userId
 * @param {string} o.displayName
 * @param {string} [o.colorBody]
 * @param {string} [o.colorVisor]
 * @param {string} [o.colorEcho]
 * @param {string|null} [o.heroModelUrl]
 * @param {() => {x:number,z:number}} o.getPosition
 * @param {() => number} o.getRotationY
 * @param {number} [o.remoteHeight=1.8]
 */
export function createNexusDistrictRealtime(o) {
  const districtId = o.districtId;
  const userId = o.userId | 0;
  const displayName = (o.displayName || 'PLAYER').slice(0, 20);
  const colorBody = capColor(o.colorBody);
  const colorVisor = capColor(o.colorVisor);
  const colorEcho = capColor(o.colorEcho);
  const heroModelUrl = o.heroModelUrl || null;
  const getPosition = o.getPosition;
  const getRotationY = o.getRotationY;
  const remoteHeight = o.remoteHeight ?? 1.8;

  const playerData = new Map();
  const remoteEntries = new Map();
  const loader = new GLTFLoader();

  const pidKey = (id) => (id == null ? '' : String(id));

  let ws = null;
  let myPid = null;
  let disposed = false;
  let moveTimer = null;
  let hbTimer = null;
  let reconnectTimer = null;
  let reconnectAttempt = 0;

  function shouldShow(rec) {
    return rec && rec.player_id != null && String(rec.player_id) !== String(myPid) && rec.district_id === districtId;
  }

  function mergePlayer(pl) {
    if (!pl || pl.player_id == null) return;
    const k = pidKey(pl.player_id);
    const prev = playerData.get(k) || {};
    playerData.set(k, { ...prev, ...pl, player_id: pl.player_id });
  }

  function scheduleReconnect() {
    if (disposed || userId <= 0) return;
    if (reconnectTimer) return;
    const base = Math.min(3e4, 1200 * Math.pow(1.85, reconnectAttempt));
    const delay = Math.round(base + base * 0.15 * Math.random());
    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      reconnectAttempt = Math.min(reconnectAttempt + 1, 12);
      if (!ws || ws.readyState === WebSocket.CLOSED) connect();
    }, delay);
  }

  function stripRemoteMesh(pid) {
    const e = remoteEntries.get(pidKey(pid));
    if (e) {
      if (e.group && o.scene) o.scene.remove(e.group);
      if (e.label && e.label.material && e.label.material.map) e.label.material.map.dispose();
      if (e.label && e.label.material) e.label.material.dispose();
      remoteEntries.delete(pidKey(pid));
    }
  }

  function removeRemote(pid) {
    const k = pidKey(pid);
    stripRemoteMesh(pid);
    playerData.delete(k);
  }

  async function ensureRemoteMesh(rec) {
    const k = pidKey(rec.player_id);
    if (!shouldShow(rec) || remoteEntries.has(k)) return;

    const tx = typeof rec.pos_x === 'number' ? rec.pos_x : 0;
    const tz = typeof rec.pos_z === 'number' ? rec.pos_z : 0;
    const tyRot = typeof rec.dir === 'number' ? rec.dir : 0;

    const entry = {
      group: null,
      mixer: null,
      tx,
      tz,
      tyRot,
      pending: true,
    };
    remoteEntries.set(k, entry);

    const wrap = new THREE.Group();
    wrap.position.set(tx, 0, tz);
    wrap.rotation.y = tyRot;

    try {
      const url = rec.hero_model_url;
      if (url) {
        const gltf = await loader.loadAsync(url);
        normalizeGltfScene(gltf.scene);
        const model = gltf.scene;
        const box = new THREE.Box3().setFromObject(model);
        const h = box.max.y - box.min.y;
        model.scale.setScalar(remoteHeight / Math.max(h, 0.001));
        const box2 = new THREE.Box3().setFromObject(model);
        model.position.y = -box2.min.y;
        wrap.add(model);
        if (gltf.animations && gltf.animations.length) {
          entry.mixer = new THREE.AnimationMixer(model);
          entry.mixer.clipAction(gltf.animations[0]).play();
        }
      } else {
        throw new Error('no url');
      }
    } catch (_) {
      const col = parseInt(String(rec.color_body || colorBody).replace('#', ''), 16);
      const c = Number.isFinite(col) ? col : 0x00e8ff;
      const body = new THREE.Mesh(
        new THREE.CapsuleGeometry(0.35, 1.05, 4, 8),
        new THREE.MeshStandardMaterial({
          color: c,
          emissive: c,
          emissiveIntensity: 0.25,
          roughness: 0.55,
        }),
      );
      body.position.y = 0.9;
      wrap.add(body);
    }

    const lbl = makeLabelSprite(rec.display_name || '?', capColor(rec.color_body));
    lbl.position.set(0, 2.4, 0);
    wrap.add(lbl);
    entry.label = lbl;

    entry.group = wrap;
    entry.pending = false;
    o.scene.add(wrap);

    const latest = playerData.get(k);
    if (latest) {
      entry.tx = latest.pos_x;
      entry.tz = latest.pos_z;
      entry.tyRot = latest.dir;
    }
  }

  function onMessage(raw) {
    let msg;
    try {
      msg = JSON.parse(raw);
    } catch (_) {
      return;
    }
    switch (msg.type) {
      case 'welcome':
        myPid = msg.player_id;
        for (const pl of msg.players || []) {
          mergePlayer(pl);
          const row = playerData.get(pidKey(pl.player_id));
          if (shouldShow(row)) ensureRemoteMesh(row);
        }
        if (ws && ws.readyState === WebSocket.OPEN) {
          ws.send(JSON.stringify({ type: 'district_enter', district_id: districtId }));
        }
        reconnectAttempt = 0;
        break;
      case 'player_join':
        if (msg.player) {
          mergePlayer(msg.player);
          const r = playerData.get(pidKey(msg.player.player_id));
          if (shouldShow(r)) ensureRemoteMesh(r);
        }
        break;
      case 'player_leave':
        if (msg.player_id) removeRemote(msg.player_id);
        break;
      case 'player_move': {
        const pid = msg.player_id;
        const rec = playerData.get(pidKey(pid));
        if (!rec || rec.district_id !== districtId) break;
        if (typeof msg.pos_x === 'number') rec.pos_x = msg.pos_x;
        if (typeof msg.pos_z === 'number') rec.pos_z = msg.pos_z;
        if (typeof msg.dir === 'number') rec.dir = msg.dir;
        const e = remoteEntries.get(pidKey(pid));
        if (e) {
          e.tx = rec.pos_x;
          e.tz = rec.pos_z;
          e.tyRot = rec.dir;
        }
        break;
      }
      case 'player_update':
        if (msg.player) {
          mergePlayer(msg.player);
          const r = playerData.get(pidKey(msg.player.player_id));
          if (shouldShow(r)) ensureRemoteMesh(r);
        }
        break;
      case 'district_enter': {
        const pid = msg.player_id;
        const did = msg.district_id;
        const pk = pidKey(pid);
        let rec = playerData.get(pk);
        if (rec) rec.district_id = did;
        else {
          rec = { player_id: pid, district_id: did, pos_x: 0, pos_z: 0, dir: 0, display_name: '?', color_body: '#888888' };
          playerData.set(pk, rec);
        }
        if (String(pid) === String(myPid)) break;
        if (did !== districtId) {
          stripRemoteMesh(pid);
        } else if (shouldShow(rec)) {
          ensureRemoteMesh(playerData.get(pk));
        }
        break;
      }
      default:
        break;
    }
  }

  function connect() {
    if (disposed || userId <= 0) return;
    const url = getNexusWsUrl();
    if (!url) {
      scheduleReconnect();
      return;
    }
    try {
      ws = new WebSocket(url);
    } catch (e) {
      console.warn('[nexus-district-rt] WebSocket:', e.message);
      scheduleReconnect();
      return;
    }

    ws.addEventListener('open', () => {
      const pos = getPosition();
      const dir = getRotationY();
      ws.send(
        JSON.stringify({
          type: 'join',
          user_id: userId,
          display_name: displayName,
          color_body: colorBody,
          color_visor: colorVisor,
          color_echo: colorEcho,
          hero_model_url: heroModelUrl || null,
          pos_x: pos.x,
          pos_z: pos.z,
          dir,
        }),
      );
    });

    ws.addEventListener('message', (ev) => onMessage(ev.data));
    ws.addEventListener('close', (ev) => {
      ws = null;
      myPid = null;
      remoteEntries.forEach((_, k) => removeRemote(k));
      if (!disposed && ev.code !== 1000) scheduleReconnect();
    });
    ws.addEventListener('error', () => {
      try {
        ws && ws.close();
      } catch (_) {}
    });
  }

  function startTimers() {
    if (moveTimer) return;
    moveTimer = setInterval(() => {
      if (!ws || ws.readyState !== WebSocket.OPEN || myPid == null) return;
      const pos = getPosition();
      const dir = getRotationY();
      ws.send(
        JSON.stringify({
          type: 'move',
          pos_x: pos.x,
          pos_z: pos.z,
          dir,
        }),
      );
    }, 100);
    hbTimer = setInterval(() => {
      if (ws && ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify({ type: 'heartbeat' }));
    }, 3e4);
  }

  function stopTimers() {
    if (moveTimer) {
      clearInterval(moveTimer);
      moveTimer = null;
    }
    if (hbTimer) {
      clearInterval(hbTimer);
      hbTimer = null;
    }
    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }
  }

  function update(dt) {
    const d = Math.min(0.05, dt || 0.016);
    const kPos = 1 - Math.exp(-14 * d);
    const kRot = 1 - Math.exp(-10 * d);
    remoteEntries.forEach((e) => {
      if (!e.group || e.pending) return;
      e.group.position.x += (e.tx - e.group.position.x) * kPos;
      e.group.position.z += (e.tz - e.group.position.z) * kPos;
      let dr = e.tyRot - e.group.rotation.y;
      while (dr > Math.PI) dr -= Math.PI * 2;
      while (dr < -Math.PI) dr += Math.PI * 2;
      e.group.rotation.y += dr * kRot;
      if (e.mixer) e.mixer.update(d);
    });
  }

  return {
    start() {
      if (userId <= 0) return;
      disposed = false;
      connect();
      startTimers();
    },
    update,
    dispose() {
      disposed = true;
      stopTimers();
      try {
        ws && ws.close(1000);
      } /* ignore */ catch (_) {}
      ws = null;
      remoteEntries.forEach((_, pid) => removeRemote(pid));
    },
  };
}
