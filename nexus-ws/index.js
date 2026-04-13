/**
 * Nexus realtime WebSocket server
 *
 * - One connection = one player
 * - Rooms keyed by district (public districts or sanctum:{userId})
 * - Server-authoritative movement: clients send input only; positions updated on a fixed tick
 * - Per-recipient state: only nearby players (50 units) for bandwidth
 * - HTTP POST /internal/event (header x-internal-key) for PHP-triggered room broadcasts
 *
 * Extension hooks (future): client-originated chat/emotes/combat — see handleExtensionMessage / EXTENSION_TYPES
 */

'use strict';

const http = require('http');
const crypto = require('crypto');
const { WebSocketServer, WebSocket } = require('ws');

// ---------------------------------------------------------------------------
// Config (tune for your Three.js scale)
// ---------------------------------------------------------------------------

const TICK_HZ = 20;
const TICK_MS = 1000 / TICK_HZ;
/** World units: peers farther than this are omitted from your `state` (bandwidth). Dev: NEXUS_NEARBY_RADIUS=200 */
const NEARBY_RADIUS = (() => {
  const n = Number(process.env.NEXUS_NEARBY_RADIUS);
  return Number.isFinite(n) && n > 0 ? n : 50;
})();
const NEARBY_RADIUS_SQ = NEARBY_RADIUS * NEARBY_RADIUS;

/** Walk speed in world units per second */
const MOVE_SPEED = 8;
/** Multiplier when run === true */
const RUN_MULT = 1.65;
/** Radians per second when turning with left/right held */
const TURN_SPEED = 2.8;

/** Max WebSocket messages accepted per second per socket (input can be high-frequency) */
const MAX_MSG_PER_SEC = 150;
/** Sliding window for rate limit (ms) */
const RATE_WINDOW_MS = 1000;
/** Disconnect after this many invalid messages in a row */
const MAX_INVALID_STREAK = 12;

const PUBLIC_DISTRICTS = new Set(['casino', 'agora', 'tesla', 'olimpo', 'central']);

/** Client extension messages (after join); `typing` handled explicitly before this set */
const EXTENSION_TYPES = new Set(['chat', 'emote', 'world_patch', 'combat_action']);

/** Allowed types for POST /internal/event (PHP → Node → WebSocket clients) */
const INTERNAL_EVENT_TYPES = new Set(['world_patch', 'chat', 'emote', 'sanctum_update', 'echo_event']);

const INTERNAL_KEY_HEADER = 'x-internal-key';
/** Max JSON body size for internal endpoint (bytes) */
const INTERNAL_BODY_LIMIT = 512 * 1024;

/** Bump when internal broadcast envelope shape changes (clients can switch on `v`) */
const INTERNAL_EVENT_VERSION = 2;

/** Last N chat lines kept per room (join replay). */
const CHAT_HISTORY_MAX = 20;

const AVATAR_URL_MAX = 2048;

/** Default chat nameplate color when join omits `color` */
const DEFAULT_CHAT_COLOR = '#94a3b8';

/** Chat / typing delivery radius in world XZ units when envelope includes `origin`. */
function getChatProximityRadius() {
  const n = Number(process.env.NEXUS_CHAT_PROXIMITY_RADIUS);
  return Number.isFinite(n) && n > 0 ? n : 30;
}

/** Min ms between `isTyping: true` broadcasts per connection (false always clears immediately). */
function getTypingBroadcastMinMs() {
  const n = Number(process.env.NEXUS_TYPING_MIN_MS);
  return Number.isFinite(n) && n >= 0 ? n : 500;
}

/**
 * If true (default), internal chat only uses identity + position from the live WebSocket session;
 * PHP cannot spoof display name / avatar / color for that user_id. Set NEXUS_CHAT_BIND_SESSION=0 to allow legacy PHP-only identity.
 */
function chatRequirePlayerBinding() {
  return String(process.env.NEXUS_CHAT_BIND_SESSION || '1') !== '0';
}

/**
 * Max accepted POST /internal/event calls per rolling window, per event type (PHP → Node flood control).
 * Override with env: NEXUS_INTERNAL_RL_WORLD_PATCH, NEXUS_INTERNAL_RL_CHAT, NEXUS_INTERNAL_RL_DEFAULT
 */
const INTERNAL_RATE_LIMIT = {
  world_patch: { max: 12, windowMs: 1000 },
  chat: { max: 40, windowMs: 1000 },
  emote: { max: 60, windowMs: 1000 },
  sanctum_update: { max: 30, windowMs: 1000 },
  echo_event: { max: 20, windowMs: 1000 },
  default: { max: 80, windowMs: 1000 },
};

const CHAT_USERNAME_MAX = 20;
const CHAT_MESSAGE_MAX = 200;
/** Minimum milliseconds between chat internal events per (room, user) */
const CHAT_COOLDOWN_MS = 1000;

// ---------------------------------------------------------------------------
// Room registry: roomKey -> Map<playerId, PlayerState>
// ---------------------------------------------------------------------------

const rooms = new Map();

/** @type {Map<string, number>} key "roomKey|userId" -> last chat internal event ms */
const chatCooldownMap = new Map();

/** @type {Map<string, number[]>} event type -> timestamps in window (internal HTTP rate limit) */
const internalRateTimestamps = new Map();

/** @type {Map<string, Array<{ v: number, origin: object | null, payload: object }>>} */
const chatHistoryByRoom = new Map();

function getOrCreateRoom(roomKey) {
  if (!rooms.has(roomKey)) {
    rooms.set(roomKey, new Map());
  }
  return rooms.get(roomKey);
}

function removePlayerFromRoom(roomKey, playerId) {
  const room = rooms.get(roomKey);
  if (!room) return;
  room.delete(playerId);
  if (room.size === 0) {
    rooms.delete(roomKey);
  }
}

// ---------------------------------------------------------------------------
// District / room key resolution
// ---------------------------------------------------------------------------

/**
 * Build canonical room key from join payload.
 * - Public: district_id is one of PUBLIC_DISTRICTS
 * - Sanctum: "sanctum:{ownerId}" or district_id "sanctum" -> sanctum:{user_id} (caller's instance)
 */
function resolveRoomKey(userId, districtId) {
  const d = String(districtId || '').trim().toLowerCase();
  if (PUBLIC_DISTRICTS.has(d)) {
    return d;
  }
  const sanctumPrefix = 'sanctum:';
  if (d.startsWith(sanctumPrefix)) {
    const owner = d.slice(sanctumPrefix.length).replace(/[^a-zA-Z0-9_-]/g, '');
    if (!owner) return null;
    return `${sanctumPrefix}${owner}`;
  }
  if (d === 'sanctum') {
    const uid = String(userId || '').replace(/[^a-zA-Z0-9_-]/g, '');
    if (!uid) return null;
    return `${sanctumPrefix}${uid}`;
  }
  return null;
}

/**
 * Canonical room key for internal (PHP) events — must match keys used by WebSocket joins.
 */
function normalizeInternalRoomKey(raw) {
  const r = String(raw || '').trim().toLowerCase();
  if (PUBLIC_DISTRICTS.has(r)) return r;
  const sanctumPrefix = 'sanctum:';
  if (r.startsWith(sanctumPrefix)) {
    const owner = r.slice(sanctumPrefix.length).replace(/[^a-zA-Z0-9_-]/g, '');
    if (!owner) return null;
    return `${sanctumPrefix}${owner}`;
  }
  return null;
}

// ---------------------------------------------------------------------------
// Player model (server-side)
// ---------------------------------------------------------------------------

function createPlayer(id) {
  return {
    id,
    x: 0,
    z: 0,
    ry: 0,
    /** Set on join: canonical display for chat when bound to this connection */
    displayName: '',
    avatarUrl: '',
    chatColor: '',
    /** Correlates joined WS session with server-side identity (joined ack) */
    sessionId: '',
    input: {
      forward: false,
      backward: false,
      left: false,
      right: false,
      run: false,
    },
    /** @type {import('ws').WebSocket | null} */
    socket: null,
    roomKey: null,
    invalidStreak: 0,
    /** timestamps of received messages for rate limiting */
    msgTimestamps: [],
    /** last time a typing (isTyping true) event was broadcast to peers */
    lastTypingBroadcastAt: 0,
  };
}

function distSq(ax, az, bx, bz) {
  const dx = ax - bx;
  const dz = az - bz;
  return dx * dx + dz * dz;
}

// ---------------------------------------------------------------------------
// Message validation
// ---------------------------------------------------------------------------

function isPlainObject(v) {
  return v !== null && typeof v === 'object' && !Array.isArray(v);
}

function sanitizeUserId(raw) {
  const s = String(raw ?? '').trim();
  if (s.length === 0 || s.length > 96) return null;
  if (!/^[a-zA-Z0-9_-]+$/.test(s)) return null;
  return s;
}

function parseIncoming(data) {
  if (typeof data === 'string') {
    try {
      return JSON.parse(data);
    } catch {
      return null;
    }
  }
  if (Buffer.isBuffer(data)) {
    try {
      return JSON.parse(data.toString('utf8'));
    } catch {
      return null;
    }
  }
  return null;
}

function isSafeAvatarUrl(s) {
  if (s.length === 0) return true;
  if (s.length > AVATAR_URL_MAX) return false;
  if (/^https:\/\//i.test(s)) return true;
  if (/^http:\/\//i.test(s)) return true;
  if (/^\/[\w\-./%#?&=+~:@]*$/i.test(s)) return true;
  return false;
}

/**
 * Optional visual identity from the browser (authoritative for chat when session-bound).
 */
function parseJoinProfile(msg, userId) {
  let displayName = '';
  if (typeof msg.username === 'string') {
    const t = msg.username.trim();
    if (t.length > 0 && t.length <= CHAT_USERNAME_MAX) displayName = t;
  }
  if (!displayName) displayName = userId;

  let avatarUrl = '';
  if (typeof msg.avatar === 'string') {
    const a = msg.avatar.trim();
    if (isSafeAvatarUrl(a)) avatarUrl = a;
  }

  let chatColor = '';
  if (typeof msg.color === 'string') {
    const c = msg.color.trim();
    if (/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(c)) chatColor = c;
  }

  return { displayName, avatarUrl, chatColor };
}

/** Legacy hub / clients send spawn in join; clamp to keep server state in world bounds. */
const JOIN_SPAWN_EXTENT = 120;

function clampJoinSpawnAxis(v) {
  if (typeof v !== 'number' || !Number.isFinite(v)) return null;
  return Math.max(-JOIN_SPAWN_EXTENT, Math.min(JOIN_SPAWN_EXTENT, v));
}

/**
 * Apply optional spawn from join payload (pos_x, pos_z, ry or dir).
 * @param {ReturnType<createPlayer>} player
 * @param {object} msg raw join message
 */
function applyJoinSpawn(player, msg) {
  const cx = clampJoinSpawnAxis(msg.pos_x);
  const cz = clampJoinSpawnAxis(msg.pos_z);
  if (cx !== null) player.x = cx;
  if (cz !== null) player.z = cz;
  let ry = null;
  if (typeof msg.ry === 'number' && Number.isFinite(msg.ry)) ry = msg.ry;
  else if (typeof msg.dir === 'number' && Number.isFinite(msg.dir)) ry = msg.dir;
  if (ry !== null) player.ry = ry;
}

function validateJoin(msg) {
  if (!isPlainObject(msg) || msg.type !== 'join') return null;
  const user_id = sanitizeUserId(msg.user_id);
  const district_id = msg.district_id;
  if (!user_id || district_id === undefined || district_id === null) return null;
  const roomKey = resolveRoomKey(user_id, district_id);
  if (!roomKey) return null;
  const profile = parseJoinProfile(msg, user_id);
  return { user_id, district_id: String(district_id).trim(), roomKey, profile };
}

function validateInput(msg) {
  if (!isPlainObject(msg) || msg.type !== 'input') return null;
  const keys = ['forward', 'backward', 'left', 'right', 'run'];
  const out = { type: 'input' };
  for (const k of keys) {
    if (typeof msg[k] !== 'boolean') return null;
    out[k] = msg[k];
  }
  return out;
}

function validateTyping(msg) {
  if (!isPlainObject(msg) || msg.type !== 'typing') return null;
  if (typeof msg.isTyping !== 'boolean') return null;
  return { isTyping: msg.isTyping };
}

// ---------------------------------------------------------------------------
// Rate limiting (per socket)
// ---------------------------------------------------------------------------

function pruneRateWindow(player, now) {
  const cutoff = now - RATE_WINDOW_MS;
  while (player.msgTimestamps.length > 0 && player.msgTimestamps[0] < cutoff) {
    player.msgTimestamps.shift();
  }
}

function allowMessage(player, now) {
  pruneRateWindow(player, now);
  if (player.msgTimestamps.length >= MAX_MSG_PER_SEC) {
    return false;
  }
  player.msgTimestamps.push(now);
  return true;
}

// ---------------------------------------------------------------------------
// Movement (authoritative, XZ plane — matches typical Three.js ground)
// ---------------------------------------------------------------------------

function applyMovement(player, dt) {
  const inp = player.input;
  let move = 0;
  if (inp.forward) move += 1;
  if (inp.backward) move -= 1;

  let turn = 0;
  if (inp.left) turn += 1;
  if (inp.right) turn -= 1;

  if (turn !== 0) {
    player.ry += turn * TURN_SPEED * dt;
  }

  const speed = MOVE_SPEED * (inp.run ? RUN_MULT : 1);
  if (move !== 0) {
    const dx = Math.sin(player.ry) * move * speed * dt;
    const dz = Math.cos(player.ry) * move * speed * dt;
    player.x += dx;
    player.z += dz;
  }
}

// ---------------------------------------------------------------------------
// Broadcasting (per-room, distance-culled, interpolation-friendly)
// ---------------------------------------------------------------------------

function broadcastFullStateToAllPeers() {
  return String(process.env.NEXUS_BROADCAST_FULL_STATE || '') === '1';
}

function buildStatePayload(viewer, room, tick, serverTime) {
  const fullRoom = broadcastFullStateToAllPeers();
  const players = [];
  for (const other of room.values()) {
    if (
      !fullRoom &&
      distSq(viewer.x, viewer.z, other.x, other.z) > NEARBY_RADIUS_SQ &&
      other.id !== viewer.id
    ) {
      continue;
    }
    players.push({
      id: other.id,
      x: other.x,
      z: other.z,
      ry: other.ry,
    });
  }
  return {
    type: 'state',
    tick,
    t: serverTime,
    dt: TICK_MS,
    players,
  };
}

function broadcastRoomState(roomKey, tick, serverTime) {
  const room = rooms.get(roomKey);
  if (!room) return;

  for (const player of room.values()) {
    if (!player.socket || player.socket.readyState !== WebSocket.OPEN) continue;
    const payload = buildStatePayload(player, room, tick, serverTime);
    player.socket.send(JSON.stringify(payload));
  }
}

function envPositiveInt(name, fallback) {
  const n = Number(process.env[name]);
  return Number.isFinite(n) && n > 0 ? Math.floor(n) : fallback;
}

function getInternalRateConfig(type) {
  const base = INTERNAL_RATE_LIMIT[type] || INTERNAL_RATE_LIMIT.default;
  if (type === 'world_patch') {
    return { max: envPositiveInt('NEXUS_INTERNAL_RL_WORLD_PATCH', base.max), windowMs: base.windowMs };
  }
  if (type === 'chat') {
    return { max: envPositiveInt('NEXUS_INTERNAL_RL_CHAT', base.max), windowMs: base.windowMs };
  }
  return {
    max: envPositiveInt('NEXUS_INTERNAL_RL_DEFAULT', base.max),
    windowMs: base.windowMs,
  };
}

/**
 * Sliding-window limiter for POST /internal/event (per event `type`).
 * @returns {boolean} false if this request should be rejected (429)
 */
function allowInternalHttpEvent(type) {
  const { max, windowMs } = getInternalRateConfig(type);
  const now = Date.now();
  const cutoff = now - windowMs;
  let arr = internalRateTimestamps.get(type);
  if (!arr) {
    arr = [];
    internalRateTimestamps.set(type, arr);
  }
  while (arr.length > 0 && arr[0] < cutoff) {
    arr.shift();
  }
  if (arr.length >= max) {
    return false;
  }
  arr.push(now);
  return true;
}

const HTML_ESCAPE = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
};

/** Escape text for safe HTML/UI rendering (entities; prefer over tag-stripping). */
function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (ch) => HTML_ESCAPE[ch] || ch);
}

/**
 * Optional spatial anchor on internal envelopes (nearby filters, proximity chat, world events).
 * Omit or null when not applicable.
 */
function makeInternalEnvelope(type, payload, origin) {
  const env = { type, v: INTERNAL_EVENT_VERSION, payload };
  if (origin && Number.isFinite(origin.x) && Number.isFinite(origin.z)) {
    env.origin = { x: origin.x, z: origin.z };
  }
  return env;
}

function validateOptionalBodyOrigin(body) {
  if (body.origin === undefined) return null;
  if (!isPlainObject(body.origin)) return 'origin must be an object with x and z';
  if (typeof body.origin.x !== 'number' || !Number.isFinite(body.origin.x)) {
    return 'origin.x must be a finite number';
  }
  if (typeof body.origin.z !== 'number' || !Number.isFinite(body.origin.z)) {
    return 'origin.z must be a finite number';
  }
  return null;
}

function originFromInternalBody(body) {
  if (!body || !isPlainObject(body.origin)) return null;
  const { x, z } = body.origin;
  if (!Number.isFinite(x) || !Number.isFinite(z)) return null;
  return { x, z };
}

function getChatHistory(roomKey) {
  return (chatHistoryByRoom.get(roomKey) || []).map((e) => ({ ...e }));
}

function appendChatHistory(roomKey, envelope) {
  if (!chatHistoryByRoom.has(roomKey)) {
    chatHistoryByRoom.set(roomKey, []);
  }
  const arr = chatHistoryByRoom.get(roomKey);
  arr.push({
    v: envelope.v,
    origin: envelope.origin !== undefined ? envelope.origin : null,
    payload: envelope.payload,
  });
  while (arr.length > CHAT_HISTORY_MAX) {
    arr.shift();
  }
}

/**
 * Deliver an internal envelope to players in a room.
 *
 * - `scope: 'room'` — every connected player in the room (backward compatible).
 * - `scope: 'nearby'` — only players whose authoritative (x,z) is within `radius` of `origin`
 *   (used for chat when `envelope.origin` is set).
 * - `world_patch` / other types: keep using full room unless you explicitly add nearby later.
 *
 * @param {Map<string, object>|undefined} room
 * @param {object} envelope { type, v, payload, origin? }
 * @param {{ scope?: 'room' | 'nearby', origin?: {x:number,z:number}, radius?: number, excludePlayerId?: string }} [options]
 * @returns {number} sockets that accepted the message */
function broadcastInternalEventInRoom(room, envelope, options = {}) {
  const scope = options.scope || 'room';
  if (scope !== 'room' && scope !== 'nearby') {
    console.warn('[nexus-ws] internal broadcast scope "%s" not supported', scope);
    return 0;
  }
  if (!room || room.size === 0) return 0;

  let origin = options.origin;
  if (scope === 'nearby' && (!origin || !Number.isFinite(origin.x) || !Number.isFinite(origin.z))) {
    origin = envelope.origin;
  }
  const radius = options.radius != null && Number.isFinite(options.radius) ? options.radius : getChatProximityRadius();
  const radiusSq = radius * radius;
  const useNearby = scope === 'nearby' && origin && Number.isFinite(origin.x) && Number.isFinite(origin.z);

  if (scope === 'nearby' && !useNearby) {
    console.warn('[nexus-ws] nearby broadcast missing valid origin; falling back to room scope');
  }

  const excludeId =
    options.excludePlayerId !== undefined && options.excludePlayerId !== null
      ? String(options.excludePlayerId)
      : null;

  const data = JSON.stringify(envelope);
  let delivered = 0;
  for (const player of room.values()) {
    if (!player.socket || player.socket.readyState !== WebSocket.OPEN) continue;
    if (excludeId !== null && String(player.id) === excludeId) {
      continue;
    }
    if (useNearby && distSq(player.x, player.z, origin.x, origin.z) > radiusSq) {
      continue;
    }
    try {
      player.socket.send(data);
      delivered += 1;
    } catch (_) {
      /* ignore broken pipe / backpressure */
    }
  }
  return delivered;
}

/**
 * Proximity typing indicator from a connected client (excludes sender).
 */
function broadcastTypingFromPlayer(player, roomKey, room, isTyping) {
  if (!room || !roomKey) return;
  const now = Date.now();
  const minMs = getTypingBroadcastMinMs();
  if (isTyping) {
    if (now - player.lastTypingBroadcastAt < minMs) {
      return;
    }
  }
  player.lastTypingBroadcastAt = now;

  const origin = { x: player.x, z: player.z };
  const envelope = makeInternalEnvelope(
    'typing',
    { user_id: player.id, isTyping },
    origin,
  );
  broadcastInternalEventInRoom(room, envelope, {
    scope: 'nearby',
    origin,
    radius: getChatProximityRadius(),
    excludePlayerId: player.id,
  });
}

// ---------------------------------------------------------------------------
// Internal HTTP API (PHP → Node)
// ---------------------------------------------------------------------------

function timingSafeKeyEqual(provided, expected) {
  if (typeof provided !== 'string' || typeof expected !== 'string') return false;
  const a = Buffer.from(provided, 'utf8');
  const b = Buffer.from(expected, 'utf8');
  if (a.length !== b.length) return false;
  return crypto.timingSafeEqual(a, b);
}

function readJsonBody(req, limit) {
  return new Promise((resolve, reject) => {
    let size = 0;
    const chunks = [];
    req.on('data', (chunk) => {
      size += chunk.length;
      if (size > limit) {
        reject(Object.assign(new Error('payload too large'), { code: 'LIMIT' }));
        req.destroy();
        return;
      }
      chunks.push(chunk);
    });
    req.on('end', () => {
      try {
        const raw = Buffer.concat(chunks).toString('utf8');
        if (!raw) {
          resolve(null);
          return;
        }
        resolve(JSON.parse(raw));
      } catch (e) {
        reject(e);
      }
    });
    req.on('error', reject);
  });
}

function sendHttpJson(res, status, body) {
  const payload = JSON.stringify(body);
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(payload),
  });
  res.end(payload);
}

/**
 * Normalize chat HTTP payload (mutates in place). User-visible length limits apply before HTML escape.
 * When session binding is on, username from PHP is ignored at broadcast time.
 * @returns {string|null} error message or null on success
 */
function normalizeChatPayloadInPlace(payload, opts = {}) {
  const requireUsername = opts.requirePhpUsername === true;

  const uidRaw = payload.user_id;
  if (typeof uidRaw === 'number') {
    if (!Number.isFinite(uidRaw)) return 'chat: user_id must be a finite number';
  } else if (typeof uidRaw === 'string') {
    if (uidRaw.trim().length === 0) return 'chat: user_id must be non-empty';
  } else {
    return 'chat: user_id must be a string or number';
  }
  const user_id = String(uidRaw).trim();
  if (user_id.length > 96) return 'chat: user_id too long';
  payload.user_id = user_id;

  if (typeof payload.message !== 'string') return 'chat: message must be a string';
  const msgTrim = payload.message.trim();
  if (msgTrim.length === 0) return 'chat: message empty after trim';
  if (msgTrim.length > CHAT_MESSAGE_MAX) return 'chat: message exceeds max length';
  payload.message = escapeHtml(msgTrim);

  if (requireUsername) {
    if (typeof payload.username !== 'string') return 'chat: username must be a string';
    const uTrim = payload.username.trim();
    if (uTrim.length === 0) return 'chat: username empty after trim';
    if (uTrim.length > CHAT_USERNAME_MAX) return 'chat: username exceeds max length';
    payload.username = escapeHtml(uTrim);
  } else {
    delete payload.username;
  }

  return null;
}

/**
 * @returns {boolean} true if cooldown slot taken (allowed), false if still cooling down
 */
function consumeChatCooldown(roomKey, userId) {
  const key = `${roomKey}|${userId}`;
  const now = Date.now();
  const last = chatCooldownMap.get(key);
  if (last !== undefined && now - last < CHAT_COOLDOWN_MS) {
    return false;
  }
  chatCooldownMap.set(key, now);
  return true;
}

function validateWorldPatchPayload(payload) {
  if (typeof payload.action !== 'string' || payload.action.length === 0) {
    return 'world_patch: payload.action must be a non-empty string';
  }
  if (!isPlainObject(payload.object)) {
    return 'world_patch: payload.object must be an object';
  }
  const o = payload.object;
  if (typeof o.id !== 'number' && typeof o.id !== 'string') {
    return 'world_patch: object.id must be a number or string';
  }
  if (o.model_url !== undefined && typeof o.model_url !== 'string') {
    return 'world_patch: object.model_url must be a string';
  }
  for (const k of ['pos_x', 'pos_y', 'pos_z', 'rot_y', 'scale']) {
    if (o[k] !== undefined && typeof o[k] !== 'number') {
      return `world_patch: object.${k} must be a number`;
    }
  }
  return null;
}

/**
 * Validate POST /internal/event body after JSON parse.
 * @returns {null | string} error message or null if ok
 */
function validateInternalEventBody(body) {
  if (!isPlainObject(body)) return 'body must be a JSON object';
  const { type, room, payload } = body;
  if (typeof type !== 'string' || !INTERNAL_EVENT_TYPES.has(type)) {
    return 'invalid or unsupported type';
  }
  if (typeof room !== 'string') return 'room must be a string';
  const roomKey = normalizeInternalRoomKey(room);
  if (!roomKey) return 'invalid room';
  if (!isPlainObject(payload)) return 'payload must be an object';

  if (type === 'world_patch') {
    const err = validateWorldPatchPayload(payload);
    if (err) return err;
  }

  const originErr = validateOptionalBodyOrigin(body);
  if (originErr) return originErr;

  if (type === 'chat') {
    const err = normalizeChatPayloadInPlace(payload, {
      requirePhpUsername: !chatRequirePlayerBinding(),
    });
    if (err) return err;
  }

  return null;
}

/**
 * Route internal events; extend with per-type side effects before/after broadcast.
 *
 * @param {string} type
 * @param {object} payload
 * @param {string} roomKey normalized room key
 * @param {Map<string, object>|undefined} room players map (may be missing if empty)
 * @param {{ internalBody?: object, boundPlayer?: object | null }} [meta]
 * @returns {number} WebSocket deliveries (0 if room empty or no open sockets)
 */
function handleInternalEvent(type, payload, roomKey, room, meta = {}) {
  const internalBody = meta.internalBody || {};
  const boundPlayer = meta.boundPlayer !== undefined ? meta.boundPlayer : null;
  let delivered = 0;
  switch (type) {
    case 'world_patch': {
      const origin = originFromInternalBody(internalBody);
      const envelope = makeInternalEnvelope(type, payload, origin);
      delivered = broadcastInternalEventInRoom(room, envelope);
      break;
    }
    case 'chat': {
      const ts = Date.now();
      let username;
      let avatar;
      let color;
      let origin;

      if (boundPlayer && boundPlayer.socket && boundPlayer.socket.readyState === WebSocket.OPEN) {
        username = escapeHtml(boundPlayer.displayName || boundPlayer.id);
        avatar = boundPlayer.avatarUrl || '';
        color = boundPlayer.chatColor || DEFAULT_CHAT_COLOR;
        origin = { x: boundPlayer.x, z: boundPlayer.z };
      } else {
        username = payload.username;
        avatar = '';
        color = DEFAULT_CHAT_COLOR;
        origin = originFromInternalBody(internalBody);
      }

      const out = {
        user_id: payload.user_id,
        username,
        message: payload.message,
        timestamp: ts,
        avatar,
        color,
      };
      const envelope = makeInternalEnvelope('chat', out, origin);
      const hasSpatialOrigin =
        envelope.origin && Number.isFinite(envelope.origin.x) && Number.isFinite(envelope.origin.z);
      if (hasSpatialOrigin) {
        delivered = broadcastInternalEventInRoom(room, envelope, {
          scope: 'nearby',
          origin: envelope.origin,
          radius: getChatProximityRadius(),
        });
      } else {
        delivered = broadcastInternalEventInRoom(room, envelope);
      }
      appendChatHistory(roomKey, envelope);
      console.log(
        `[nexus-ws] chat room=${roomKey} user_id=${out.user_id} username=${JSON.stringify(out.username)} bound=${Boolean(boundPlayer)} delivered=${delivered}`
      );
      break;
    }
    case 'emote': {
      const origin = originFromInternalBody(internalBody);
      delivered = broadcastInternalEventInRoom(room, makeInternalEnvelope(type, payload, origin));
      break;
    }
    case 'sanctum_update': {
      const origin = originFromInternalBody(internalBody);
      delivered = broadcastInternalEventInRoom(room, makeInternalEnvelope(type, payload, origin));
      break;
    }
    case 'echo_event': {
      const origin = originFromInternalBody(internalBody);
      delivered = broadcastInternalEventInRoom(room, makeInternalEnvelope(type, payload, origin));
      break;
    }
    default:
      break;
  }
  return delivered;
}

function createInternalHttpHandler(getInternalKey) {
  return async function internalHttpHandler(req, res) {
    const url = new URL(req.url || '/', `http://${req.headers.host || 'localhost'}`);

    if (req.method === 'POST' && url.pathname === '/internal/event') {
      const expectedKey = getInternalKey();
      if (!expectedKey || expectedKey.length === 0) {
        console.warn('[nexus-ws] rejected internal event: NEXUS_INTERNAL_KEY is not set');
        sendHttpJson(res, 503, { error: 'internal API disabled' });
        return;
      }

      const headerVal = req.headers[INTERNAL_KEY_HEADER];
      const provided = Array.isArray(headerVal) ? headerVal[0] : headerVal;
      if (!timingSafeKeyEqual(String(provided || ''), expectedKey)) {
        console.warn('[nexus-ws] rejected internal event: invalid or missing x-internal-key');
        sendHttpJson(res, 401, { error: 'unauthorized' });
        return;
      }

      let body;
      try {
        body = await readJsonBody(req, INTERNAL_BODY_LIMIT);
      } catch (e) {
        const msg = e && e.code === 'LIMIT' ? 'payload too large' : 'invalid JSON body';
        console.warn(`[nexus-ws] rejected internal event: ${msg}`);
        sendHttpJson(res, 400, { error: msg });
        return;
      }

      const schemaErr = validateInternalEventBody(body);
      if (schemaErr) {
        console.warn(`[nexus-ws] rejected internal event: ${schemaErr}`);
        sendHttpJson(res, 400, { error: schemaErr });
        return;
      }

      const roomKey = normalizeInternalRoomKey(body.room);
      const room = rooms.get(roomKey);

      if (!allowInternalHttpEvent(body.type)) {
        console.warn(`[nexus-ws] rejected internal event: HTTP rate limit type=${body.type}`);
        sendHttpJson(res, 429, { error: 'internal rate limited' });
        return;
      }

      let boundPlayer = null;
      if (body.type === 'chat') {
        const uid = String(body.payload.user_id);
        const p = room?.get(uid);
        if (p && p.socket && p.socket.readyState === WebSocket.OPEN) {
          boundPlayer = p;
        }
        if (chatRequirePlayerBinding()) {
          if (!boundPlayer) {
            console.warn(`[nexus-ws] rejected chat: user not connected room=${roomKey} user_id=${uid}`);
            sendHttpJson(res, 403, { error: 'chat: user not connected in this room' });
            return;
          }
        }
        if (!consumeChatCooldown(roomKey, uid)) {
          console.warn(`[nexus-ws] rejected chat (cooldown) room=${roomKey} user_id=${uid}`);
          sendHttpJson(res, 429, { error: 'chat rate limited' });
          return;
        }
      }

      const delivered = handleInternalEvent(body.type, body.payload, roomKey, room, {
        internalBody: body,
        boundPlayer,
      });
      console.log(`[nexus-ws] internal event type=${body.type} room=${roomKey} delivered=${delivered}`);

      sendHttpJson(res, 200, { success: true, delivered });
      return;
    }

    if (req.method === 'GET' && url.pathname === '/health') {
      sendHttpJson(res, 200, { ok: true });
      return;
    }

    sendHttpJson(res, 404, { error: 'not found' });
  };
}

// ---------------------------------------------------------------------------
// Connection lifecycle
// ---------------------------------------------------------------------------

function attachSocketHandlers(ws, getPlayer) {
  let joined = false;

  ws.on('message', (raw) => {
    const now = Date.now();
    const player = getPlayer();
    if (!player) return;

    if (!allowMessage(player, now)) {
      ws.close(1008, 'rate limit');
      return;
    }

    const msg = parseIncoming(raw);
    if (!msg || typeof msg.type !== 'string') {
      player.invalidStreak += 1;
      if (player.invalidStreak >= MAX_INVALID_STREAK) {
        ws.close(1008, 'invalid messages');
      }
      return;
    }

    if (!joined) {
      if (msg.type !== 'join') {
        player.invalidStreak += 1;
        if (player.invalidStreak >= MAX_INVALID_STREAK) ws.close(1008, 'expected join');
        return;
      }
      const join = validateJoin(msg);
      if (!join) {
        player.invalidStreak += 1;
        if (player.invalidStreak >= MAX_INVALID_STREAK) ws.close(1008, 'bad join');
        return;
      }

      player.id = join.user_id;
      player.roomKey = join.roomKey;
      player.displayName = join.profile.displayName;
      player.avatarUrl = join.profile.avatarUrl;
      player.chatColor = join.profile.chatColor;
      applyJoinSpawn(player, msg);
      player.sessionId = crypto.randomUUID();
      const room = getOrCreateRoom(join.roomKey);
      if (room.has(player.id)) {
        const existing = room.get(player.id);
        if (existing.socket && existing.socket !== ws) {
          try {
            existing.socket.close(1000, 'replaced');
          } catch (_) {}
        }
      }
      room.set(player.id, player);
      player.socket = ws;
      joined = true;
      player.invalidStreak = 0;
      ws.send(
        JSON.stringify({
          type: 'joined',
          v: INTERNAL_EVENT_VERSION,
          roomKey: join.roomKey,
          tickRate: TICK_HZ,
          sessionId: player.sessionId,
        })
      );
      ws.send(
        JSON.stringify({
          type: 'chat_history',
          v: INTERNAL_EVENT_VERSION,
          messages: getChatHistory(join.roomKey),
        })
      );
      return;
    }

    if (msg.type === 'input') {
      const input = validateInput(msg);
      if (!input) {
        player.invalidStreak += 1;
        if (player.invalidStreak >= MAX_INVALID_STREAK) ws.close(1008, 'bad input');
        return;
      }
      player.invalidStreak = 0;
      player.input.forward = input.forward;
      player.input.backward = input.backward;
      player.input.left = input.left;
      player.input.right = input.right;
      player.input.run = input.run;
      return;
    }

    if (msg.type === 'typing') {
      const typing = validateTyping(msg);
      if (!typing) {
        player.invalidStreak += 1;
        if (player.invalidStreak >= MAX_INVALID_STREAK) ws.close(1008, 'bad typing');
        return;
      }
      player.invalidStreak = 0;
      if (!player.roomKey) return;
      const room = rooms.get(player.roomKey);
      if (!room) return;
      broadcastTypingFromPlayer(player, player.roomKey, room, typing.isTyping);
      return;
    }

    if (EXTENSION_TYPES.has(msg.type)) {
      handleExtensionMessage(player, msg);
      return;
    }

    player.invalidStreak += 1;
    if (player.invalidStreak >= MAX_INVALID_STREAK) {
      ws.close(1008, 'unknown type');
    }
  });

  ws.on('close', () => {
    const player = getPlayer();
    if (!player || !player.roomKey) return;
    const room = rooms.get(player.roomKey);
    const current = room?.get(player.id);
    if (current === player && player.socket === ws) {
      removePlayerFromRoom(player.roomKey, player.id);
    }
  });

  ws.on('error', () => {
    /* close handler cleans up */
  });
}

/**
 * Stub for future features: chat, emotes, world deltas from PHP, combat.
 * Keep validation strict when you implement each type.
 */
function handleExtensionMessage(player, msg) {
  switch (msg.type) {
    case 'chat':
    case 'emote':
    case 'world_patch':
    case 'combat_action':
    default:
      // Intentionally no-op: extend with room-scoped broadcast + auth
      break;
  }
}

// ---------------------------------------------------------------------------
// Game loop
// ---------------------------------------------------------------------------

function startGameLoop() {
  let tick = 0;
  setInterval(() => {
    tick += 1;
    const serverTime = Date.now();
    const dt = 1 / TICK_HZ;

    for (const room of rooms.values()) {
      for (const player of room.values()) {
        applyMovement(player, dt);
      }
    }

    for (const roomKey of rooms.keys()) {
      broadcastRoomState(roomKey, tick, serverTime);
    }
  }, TICK_MS);
}

// ---------------------------------------------------------------------------
// Server bootstrap
// ---------------------------------------------------------------------------

function createNexusServer(options = {}) {
  const envPort = Number(process.env.NEXUS_WS_PORT);
  const port =
    options.port !== undefined && options.port !== null
      ? options.port
      : Number.isFinite(envPort) && envPort > 0
        ? envPort
        : 8080;
  const host =
    options.host !== undefined && options.host !== null
      ? options.host
      : process.env.NEXUS_WS_HOST || '0.0.0.0';

  const getInternalKey =
    typeof options.internalKey === 'string'
      ? () => options.internalKey
      : () => String(process.env.NEXUS_INTERNAL_KEY || '');

  const httpServer = http.createServer(createInternalHttpHandler(getInternalKey));

  const wss = new WebSocketServer({ server: httpServer });

  wss.on('connection', (ws) => {
    const placeholderId = `pending_${Math.random().toString(36).slice(2)}`;
    const player = createPlayer(placeholderId);

    attachSocketHandlers(ws, () => player);
  });

  httpServer.listen(port, host, () => {
    // eslint-disable-next-line no-console
    console.log(
      `[nexus-ws] HTTP+WS on http://${host}:${port} (WS upgrade, POST /internal/event) — ${TICK_HZ} Hz, nearby ${NEARBY_RADIUS}u` +
        (broadcastFullStateToAllPeers() ? ' (NEXUS_BROADCAST_FULL_STATE=1: no distance cull)' : '')
    );
  });

  startGameLoop();
  return { wss, server: httpServer };
}

// Run if executed directly
if (require.main === module) {
  createNexusServer();
}

module.exports = {
  createNexusServer,
  resolveRoomKey,
  PUBLIC_DISTRICTS,
  normalizeInternalRoomKey,
  INTERNAL_EVENT_TYPES,
  handleInternalEvent,
  INTERNAL_EVENT_VERSION,
  makeInternalEnvelope,
  broadcastInternalEventInRoom,
  allowInternalHttpEvent,
  escapeHtml,
  chatRequirePlayerBinding,
  getChatHistory,
  getChatProximityRadius,
  getTypingBroadcastMinMs,
  broadcastTypingFromPlayer,
};
