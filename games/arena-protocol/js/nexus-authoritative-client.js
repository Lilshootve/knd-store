/**
 * Nexus authoritative WebSocket client (nexus-ws/index.js protocol).
 * Join + input on change; dispatch joined, chat_history, state, chat, typing.
 * Toggle default: set USE_NEW_WS true in importers when ready for production.
 */

export const USE_NEW_WS = false;

function normalizeNexusWsUrl(raw) {
  let u = String(raw || '').trim();
  if (!u) return '';
  while (u.endsWith('/')) u = u.slice(0, -1);
  try {
    const p = new URL(u);
    if ((p.pathname === '/' || p.pathname === '') && (p.protocol === 'wss:' || p.protocol === 'ws:')) {
      u = `${p.protocol}//${p.host}`;
    }
  } catch (_) {
    /* keep u */
  }
  return u;
}

/** HTTPS pages cannot use ws:// (mixed content); upgrade to wss:// same host:port. Safe for http:// local dev (no change). */
function upgradeNexusWsUrlForHttpsPage(url) {
  if (!url || typeof location === 'undefined' || location.protocol !== 'https:') return url;
  try {
    const p = new URL(url);
    if (p.protocol === 'ws:') {
      return `wss://${p.host}`;
    }
  } catch (_) {
    /* keep url */
  }
  if (String(url).startsWith('ws://')) {
    console.warn('Running HTTPS with ws:// may fail. Consider wss://');
  }
  return url;
}

export function getNexusAuthoritativeWsUrl() {
  let u = '';
  if (typeof window !== 'undefined' && typeof window.NEXUS_WS_URL === 'string' && window.NEXUS_WS_URL.trim()) {
    u = normalizeNexusWsUrl(window.NEXUS_WS_URL.trim());
  } else if (typeof document !== 'undefined') {
    const meta = document.querySelector('meta[name="nexus-ws-url"]');
    const fromMeta = meta && meta.getAttribute('content') && meta.getAttribute('content').trim();
    if (fromMeta) u = normalizeNexusWsUrl(fromMeta);
  }
  if (!u && typeof location !== 'undefined') {
    const proto = location.protocol === 'https:' ? 'wss' : 'ws';
    u = `${proto}://${location.hostname}:8765`;
  }
  return upgradeNexusWsUrlForHttpsPage(u);
}

/**
 * @param {object} opts
 * @param {string|number} opts.userId
 * @param {string} opts.districtId
 * @param {string} [opts.username]
 * @param {string} [opts.avatar] hero / avatar URL
 * @param {string} [opts.color] hex chat color
 * @param {() => { x?: number, z?: number, ry?: number, dir?: number }} [opts.getSpawn]
 * @param {(msg: object) => void} [opts.onJoined]
 * @param {(msg: object) => void} [opts.onChatHistory]
 * @param {(msg: object) => void} [opts.onState]
 * @param {(msg: object) => void} [opts.onChat]
 * @param {(msg: object) => void} [opts.onTyping]
 * @param {() => void} [opts.onOpen]
 * @param {(ev: CloseEvent) => void} [opts.onClose]
 * @param {() => void} [opts.onConnectionError]
 */
export function createNexusAuthoritativeClient(opts) {
  const userId = opts.userId;
  const districtId = String(opts.districtId || '').trim();
  const username = typeof opts.username === 'string' ? opts.username : '';
  const avatar = typeof opts.avatar === 'string' ? opts.avatar : '';
  const color = typeof opts.color === 'string' ? opts.color : '';
  const getSpawn = typeof opts.getSpawn === 'function' ? opts.getSpawn : null;
  const onJoined = opts.onJoined;
  const onChatHistory = opts.onChatHistory;
  const onState = opts.onState;
  const onChat = opts.onChat;
  const onTyping = opts.onTyping;
  const onOpen = opts.onOpen;
  const onClose = opts.onClose;
  const onConnectionError = opts.onConnectionError;

  let ws = null;
  let reconnectTimer = null;
  let reconnectAttempt = 0;
  let disposed = false;
  let lastInputJson = '';

  function scheduleReconnect() {
    if (disposed || reconnectTimer) return;
    const base = Math.min(30000, 1200 * Math.pow(1.85, reconnectAttempt));
    const delay = Math.round(base + base * 0.15 * Math.random());
    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      reconnectAttempt = Math.min(reconnectAttempt + 1, 12);
      if (!ws || ws.readyState === WebSocket.CLOSED) connect();
    }, delay);
  }

  function buildJoinPayload() {
    const uid = String(userId ?? '').trim();
    const base = {
      type: 'join',
      user_id: uid,
      district_id: districtId,
      username: username.slice(0, 20) || uid,
      avatar: avatar.slice(0, 2048),
      color: color,
    };
    const sp = getSpawn ? getSpawn() : null;
    if (sp && typeof sp === 'object') {
      const x = sp.x;
      const z = sp.z;
      if (typeof x === 'number' && Number.isFinite(x)) base.pos_x = x;
      if (typeof z === 'number' && Number.isFinite(z)) base.pos_z = z;
      if (typeof sp.ry === 'number' && Number.isFinite(sp.ry)) base.ry = sp.ry;
      else if (typeof sp.dir === 'number' && Number.isFinite(sp.dir)) base.ry = sp.dir;
    }
    return base;
  }

  function connect() {
    if (disposed) return;
    const uidStr = String(userId ?? '').trim();
    if (!districtId || !uidStr) return;
    if (typeof userId === 'number' && userId <= 0) return;

    const url = getNexusAuthoritativeWsUrl();
    if (!url) {
      scheduleReconnect();
      return;
    }

    try {
      ws = new WebSocket(url);
    } catch (e) {
      if (typeof console !== 'undefined' && console.warn) console.warn('[nexus-auth-ws]', e.message);
      scheduleReconnect();
      return;
    }

    ws.addEventListener('open', () => {
      reconnectAttempt = 0;
      lastInputJson = '';
      console.log('[NEXUS NEW] connected');
      try {
        ws.send(JSON.stringify(buildJoinPayload()));
      } catch (_) {}
      if (onOpen) onOpen();
    });

    ws.addEventListener('message', (ev) => {
      let msg;
      try {
        msg = JSON.parse(ev.data);
      } catch (_) {
        return;
      }
      if (!msg || typeof msg.type !== 'string') return;
      console.log('[NEXUS NEW]', msg.type);
      switch (msg.type) {
        case 'joined':
          if (onJoined) onJoined(msg);
          break;
        case 'chat_history':
          if (onChatHistory) onChatHistory(msg);
          break;
        case 'state':
          if (onState) onState(msg);
          break;
        case 'chat':
          if (onChat) onChat(msg);
          break;
        case 'typing':
          if (onTyping) onTyping(msg);
          break;
        default:
          break;
      }
    });

    ws.addEventListener('close', (ev) => {
      ws = null;
      lastInputJson = '';
      if (onClose) onClose(ev);
      if (!disposed && ev.code !== 1000) scheduleReconnect();
    });

    ws.addEventListener('error', () => {
      if (onConnectionError) onConnectionError();
      try {
        if (ws) ws.close();
      } catch (_) {}
    });
  }

  return {
    start() {
      const uidStr = String(userId ?? '').trim();
      if (!districtId || !uidStr) return;
      if (typeof userId === 'number' && userId <= 0) return;
      disposed = false;
      connect();
    },

    stop() {
      disposed = true;
      if (reconnectTimer) {
        clearTimeout(reconnectTimer);
        reconnectTimer = null;
      }
      try {
        if (ws && ws.readyState === WebSocket.OPEN) ws.close(1000);
      } catch (_) {}
      ws = null;
      lastInputJson = '';
    },

    /**
     * Send input only when the boolean snapshot changes (server expects all five booleans).
     * @param {{ forward?: boolean, backward?: boolean, left?: boolean, right?: boolean, run?: boolean }} input
     */
    tickInput(input) {
      if (!ws || ws.readyState !== WebSocket.OPEN) return;
      const payload = {
        type: 'input',
        forward: !!input.forward,
        backward: !!input.backward,
        left: !!input.left,
        right: !!input.right,
        run: !!input.run,
      };
      const j = JSON.stringify(payload);
      if (j === lastInputJson) return;
      lastInputJson = j;
      try {
        ws.send(j);
      } catch (_) {}
    },

    sendTyping(isTyping) {
      if (!ws || ws.readyState !== WebSocket.OPEN) return;
      try {
        ws.send(JSON.stringify({ type: 'typing', isTyping: !!isTyping }));
      } catch (_) {}
    },

    isOpen() {
      return !!(ws && ws.readyState === WebSocket.OPEN);
    },
  };
}
