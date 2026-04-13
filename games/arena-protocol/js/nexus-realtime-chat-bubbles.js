/**
 * In-world chat bubbles + typing indicators + proximity chat blips for kndstore/nexus-ws:
 *
 * Chat:  { type: "chat", v, origin?: { x, z }, payload: { user_id, username, message, timestamp, avatar, color } }
 * Typing:{ type: "typing", v, origin?: { x, z }, payload: { user_id, isTyping } }
 *
 * Usage:
 *   const audio = createProximityChatAudio({ maxDistance: 30, maxVoices: 3 });
 *   const bubbles = createNexusRealtimeChatBubbles({
 *     getListenerWorldXZ: () => ({ x: localPlayer.position.x, z: localPlayer.position.z }),
 *     proximityAudio: audio,
 *   });
 *   bubbles.registerPlayer(userId, playerRootGroup);
 *   ws.addEventListener('message', (ev) => bubbles.handleMessage(ev.data));
 *   in render loop: bubbles.update(deltaSeconds);
 *
 * Send typing: ws.send(JSON.stringify({ type: 'typing', isTyping: true }))  // server rate-limits true-starts
 */

import * as THREE from 'three';

const TYPING_AUTO_HIDE_SEC = 5;
const TYPING_PULSE_HZ = 2.2;

const MAX_MSG_LINES = 7;
const CANVAS_TEXT_W = 300;

/**
 * @param {CanvasRenderingContext2D} ctx
 * @param {string} text
 * @param {number} maxW
 */
function wrapLines(ctx, text, maxW) {
  const words = String(text).split(/\s+/).filter(Boolean);
  const lines = [];
  let line = '';
  for (const w of words) {
    const test = line ? `${line} ${w}` : w;
    if (ctx.measureText(test).width <= maxW) {
      line = test;
    } else {
      if (line) lines.push(line);
      line = w;
      if (lines.length >= MAX_MSG_LINES) break;
    }
  }
  if (line && lines.length < MAX_MSG_LINES) lines.push(line);
  return lines.slice(0, MAX_MSG_LINES);
}

/**
 * @param {string} username
 * @param {string} message
 * @param {string} accentHex
 */
function makeBubbleTexture(username, message, accentHex) {
  const pad = 12;
  const c = document.createElement('canvas');
  const ctx = c.getContext('2d');
  ctx.font = 'bold 15px system-ui, "Segoe UI", sans-serif';
  const userLine = String(username || '?').slice(0, 22);
  const msgLines = wrapLines(ctx, String(message || ''), CANVAS_TEXT_W);
  const lineH = 19;
  const titleH = 20;
  const w = CANVAS_TEXT_W + pad * 2;
  const h = pad * 2 + titleH + msgLines.length * lineH + 6;
  c.width = w;
  c.height = h;

  ctx.fillStyle = 'rgba(6,10,18,0.88)';
  ctx.fillRect(2, 2, w - 4, h - 4);

  ctx.strokeStyle = accentHex || '#5eead4';
  ctx.lineWidth = 2;
  ctx.strokeRect(2, 2, w - 4, h - 4);

  ctx.font = 'bold 15px system-ui, "Segoe UI", sans-serif';
  ctx.fillStyle = accentHex || '#7dd3fc';
  ctx.textAlign = 'left';
  ctx.textBaseline = 'top';
  ctx.fillText(userLine, pad, pad);

  ctx.fillStyle = '#e8f1ff';
  ctx.font = '14px system-ui, "Segoe UI", sans-serif';
  let y = pad + titleH;
  for (const ln of msgLines) {
    ctx.fillText(ln, pad, y);
    y += lineH;
  }

  const tex = new THREE.CanvasTexture(c);
  if ('colorSpace' in tex) {
    tex.colorSpace = THREE.SRGBColorSpace;
  }
  tex.needsUpdate = true;
  return { tex, w, h };
}

function makeTypingTexture(accentHex) {
  const c = document.createElement('canvas');
  c.width = 112;
  c.height = 44;
  const ctx = c.getContext('2d');
  ctx.fillStyle = 'rgba(6,10,18,0.9)';
  ctx.fillRect(2, 2,108, 40);
  ctx.strokeStyle = accentHex || '#5eead4';
  ctx.lineWidth = 2;
  ctx.strokeRect(2, 2, 108, 40);
  ctx.fillStyle = '#e8f1ff';
  ctx.font = 'bold 20px system-ui, "Segoe UI", sans-serif';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText('…', 56, 23);
  const tex = new THREE.CanvasTexture(c);
  if ('colorSpace' in tex) {
    tex.colorSpace = THREE.SRGBColorSpace;
  }
  tex.needsUpdate = true;
  return { tex, w: c.width, h: c.height };
}

/**
 * Short non-overlapping “blip” with gain scaled by distance (0 at maxDistance).
 * Call from a user gesture once so AudioContext can resume on some browsers.
 *
 * @param {object} [opts]
 * @param {number} [opts.maxDistance=30] match server NEXUS_CHAT_PROXIMITY_RADIUS
 * @param {number} [opts.maxVoices=3] cap simultaneous one-shots
 * @param {number} [opts.masterGain=0.35]
 */
export function createProximityChatAudio(opts = {}) {
  const maxDistance = opts.maxDistance ?? 30;
  const maxVoices = opts.maxVoices ?? 3;
  const masterGainVal = opts.masterGain ?? 0.35;

  const AC = typeof window !== 'undefined' ? window.AudioContext || window.webkitAudioContext : null;
  /** @type {AudioContext | null} */
  let ctx = null;
  /** @type {GainNode | null} */
  let master = null;
  let activeVoices = 0;

  function ensureCtx() {
    if (!AC) return null;
    if (!ctx) {
      ctx = new AC();
      master = ctx.createGain();
      master.gain.value = masterGainVal;
      master.connect(ctx.destination);
    }
    return ctx;
  }

  /**
   * @param {number} distance planar distance in world units (XZ)
   */
  function playChatReceived(distance) {
    const audioCtx = ensureCtx();
    if (!audioCtx || !master) return;
    if (audioCtx.state === 'suspended') {
      audioCtx.resume().catch(() => {});
    }
    if (activeVoices >= maxVoices) return;

    const d = Math.max(0, Math.min(maxDistance, Number(distance) || 0));
    const proximity = 1 - d / maxDistance;
    if (proximity <= 0.02) return;

    const t0 = audioCtx.currentTime;
    const osc = audioCtx.createOscillator();
    const g = audioCtx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(740, t0);
    osc.frequency.exponentialRampToValueAtTime(380, t0 + 0.07);
    const peak = proximity * 0.22;
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(peak, t0 + 0.015);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.16);
    osc.connect(g);
    g.connect(master);
    osc.start(t0);
    osc.stop(t0 + 0.19);

    activeVoices += 1;
    window.setTimeout(() => {
      activeVoices = Math.max(0, activeVoices - 1);
    }, 220);
  }

  function dispose() {
    try {
      if (ctx && ctx.state !== 'closed') ctx.close();
    } catch (_) {}
    ctx = null;
    master = null;
    activeVoices = 0;
  }

  return { playChatReceived, dispose, ensureCtx };
}

/**
 * @param {object} [opts]
 * @param {() => { x: number, z: number }} [opts.getListenerWorldXZ] local listener position for proximity SFX
 * @param {ReturnType<typeof createProximityChatAudio> | null} [opts.proximityAudio] pass null to disable sound
 * @param {object} [opts.audio] options forwarded to createProximityChatAudio when proximityAudio omitted
 * @param {number} [opts.maxPerPlayer=3]
 * @param {number} [opts.fadeSeconds=4] full fade (opacity 1 → 0)
 * @param {number} [opts.stackSpacing=0.42] local Y between stacked bubbles
 * @param {number} [opts.baseOffsetY=2.15] local Y of anchor above player root
 * @param {number} [opts.bubbleWorldWidth=1.85] world-space width of sprite
 */
export function createNexusRealtimeChatBubbles(opts = {}) {
  const maxPerPlayer = opts.maxPerPlayer ?? 3;
  const fadeSeconds = Math.max(2.5, Math.min(5.5, opts.fadeSeconds ?? 4));
  const stackSpacing = opts.stackSpacing ?? 0.42;
  const baseOffsetY = opts.baseOffsetY ?? 2.15;
  const bubbleWorldWidth = opts.bubbleWorldWidth ?? 1.85;
  const typingBubbleWidth = opts.typingBubbleWidth ?? 0.72;
  const getListenerWorldXZ = typeof opts.getListenerWorldXZ === 'function' ? opts.getListenerWorldXZ : null;
  let proximityAudio = opts.proximityAudio;
  let ownProximityAudio = false;
  if (proximityAudio === undefined && getListenerWorldXZ) {
    proximityAudio = createProximityChatAudio(opts.audio || {});
    ownProximityAudio = true;
  }

  /** @type {Map<string, { anchor: THREE.Group, stack: Array<{ group: THREE.Group, mat: THREE.SpriteMaterial, life: number }>, typing: null | { group: THREE.Group, mat: THREE.SpriteMaterial, hideTimer: ReturnType<typeof setTimeout> | null, pulse: number } }>} */
  const entries = new Map();

  function relayout(e) {
    for (let i = 0; i < e.stack.length; i++) {
      e.stack[i].group.position.set(0, i * stackSpacing, 0);
    }
    if (e.typing && e.typing.group) {
      e.typing.group.position.set(0, e.stack.length * stackSpacing + 0.32, 0);
    }
  }

  function clearTyping(e) {
    if (!e.typing) return;
    if (e.typing.hideTimer) {
      clearTimeout(e.typing.hideTimer);
      e.typing.hideTimer = null;
    }
    if (e.typing.mat.map) e.typing.mat.map.dispose();
    e.typing.mat.dispose();
    e.typing.group.removeFromParent();
    e.typing = null;
  }

  function showTypingIndicator(userId, accentHex) {
    const id = String(userId);
    const e = entries.get(id);
    if (!e) return;

    if (e.typing) {
      if (e.typing.hideTimer) {
        clearTimeout(e.typing.hideTimer);
        e.typing.hideTimer = null;
      }
    } else {
      const { tex, w, h } = makeTypingTexture(accentHex);
      const mat = new THREE.SpriteMaterial({
        map: tex,
        transparent: true,
        depthTest: false,
        depthWrite: false,
      });
      mat.opacity = 0.9;
      const sprite = new THREE.Sprite(mat);
      const aspect = w / h;
      sprite.scale.set(typingBubbleWidth, typingBubbleWidth / aspect, 1);
      const holder = new THREE.Group();
      holder.add(sprite);
      e.anchor.add(holder);
      e.typing = { group: holder, mat, hideTimer: null, pulse: 0 };
    }

    e.typing.hideTimer = setTimeout(() => {
      if (e.typing) clearTyping(e);
      relayout(e);
    }, TYPING_AUTO_HIDE_SEC * 1000);
    relayout(e);
  }

  function hideTypingIndicator(userId) {
    const id = String(userId);
    const e = entries.get(id);
    if (!e) return;
    clearTyping(e);
    relayout(e);
  }

  /**
   * @param {string|number} userId
   * @param {THREE.Object3D} playerRootGroup
   */
  function registerPlayer(userId, playerRootGroup) {
    const id = String(userId);
    unregisterPlayer(id);
    const anchor = new THREE.Group();
    anchor.name = `nexus-chat-anchor-${id}`;
    anchor.position.set(0, baseOffsetY, 0);
    playerRootGroup.add(anchor);
    entries.set(id, { anchor, stack: [], typing: null });
  }

  function unregisterPlayer(userId) {
    const id = String(userId);
    const e = entries.get(id);
    if (!e) return;
    clearTyping(e);
    for (const b of e.stack) {
      if (b.mat.map) b.mat.map.dispose();
      b.mat.dispose();
      b.group.removeFromParent();
    }
    e.stack.length = 0;
    e.anchor.removeFromParent();
    entries.delete(id);
  }

  /**
   * @param {string|number} userId
   * @param {object} payload
   */
  function showMessageForPlayer(userId, payload) {
    const id = String(userId);
    const e = entries.get(id);
    if (!e) return;

    const accent = typeof payload.color === 'string' && payload.color.startsWith('#') ? payload.color : '#5eead4';
    hideTypingIndicator(id);
    const { tex, w, h } = makeBubbleTexture(payload.username, payload.message, accent);
    const mat = new THREE.SpriteMaterial({
      map: tex,
      transparent: true,
      depthTest: false,
      depthWrite: false,
    });
    mat.opacity = 1;

    const sprite = new THREE.Sprite(mat);
    const aspect = w / h;
    sprite.scale.set(bubbleWorldWidth, bubbleWorldWidth / aspect, 1);

    const holder = new THREE.Group();
    holder.add(sprite);
    e.anchor.add(holder);
    e.stack.push({ group: holder, mat, life: 0 });
    while (e.stack.length > maxPerPlayer) {
      const old = e.stack.shift();
      if (old.mat.map) old.mat.map.dispose();
      old.mat.dispose();
      old.group.removeFromParent();
    }
    relayout(e);
  }

  /**
   * @param {string|object} raw WebSocket message data or parsed object
   */
  function handleMessage(raw) {
    let data;
    try {
      data = typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch {
      return;
    }
    if (!data || !data.type) return;

    if (data.type === 'typing' && data.payload && data.payload.user_id != null) {
      const uid = String(data.payload.user_id);
      if (data.payload.isTyping === true) {
        showTypingIndicator(uid, '#5eead4');
      } else {
        hideTypingIndicator(uid);
      }
      return;
    }

    if (data.type === 'chat' && data.payload) {
      const uid = data.payload.user_id;
      if (uid == null) return;
      if (
        proximityAudio &&
        getListenerWorldXZ &&
        data.origin &&
        Number.isFinite(data.origin.x) &&
        Number.isFinite(data.origin.z)
      ) {
        const lp = getListenerWorldXZ();
        const dist = Math.hypot(data.origin.x - lp.x, data.origin.z - lp.z);
        proximityAudio.playChatReceived(dist);
      }
      showMessageForPlayer(uid, data.payload);
    }
  }

  function update(dt) {
    const d = Math.min(0.1, Math.max(0, Number(dt) || 0.016));
    entries.forEach((e) => {
      if (e.typing) {
        e.typing.pulse += d * TYPING_PULSE_HZ * Math.PI * 2;
        const wobble = 0.72 + 0.28 * Math.sin(e.typing.pulse);
        e.typing.mat.opacity = wobble;
      }
      for (let i = e.stack.length - 1; i >= 0; i--) {
        const b = e.stack[i];
        b.life += d;
        const u = Math.max(0, 1 - b.life / fadeSeconds);
        b.mat.opacity = u * u;
        if (b.life >= fadeSeconds) {
          if (b.mat.map) b.mat.map.dispose();
          b.mat.dispose();
          b.group.removeFromParent();
          e.stack.splice(i, 1);
        }
      }
      relayout(e);
    });
  }

  function dispose() {
    [...entries.keys()].forEach((id) => unregisterPlayer(id));
    if (ownProximityAudio && proximityAudio && typeof proximityAudio.dispose === 'function') {
      proximityAudio.dispose();
    }
  }

  return {
    registerPlayer,
    unregisterPlayer,
    showMessageForPlayer,
    showTypingIndicator,
    hideTypingIndicator,
    handleMessage,
    update,
    dispose,
    proximityAudio,
  };
}
