/**
 * nexus-ws.js — KND Nexus WebSocket server
 *
 * Events (client → server):
 *   join          { user_id, display_name, color_body, color_visor, color_echo, pos_x, pos_z }
 *   move          { pos_x, pos_z, dir }
 *   chat          { channel, message }   (global | agora | district id)
 *   district_enter { district_id }
 *   heartbeat     {}
 *
 * Events (server → client):
 *   welcome       { player_id, players: [...] }
 *   player_join   { player }
 *   player_move   { player_id, pos_x, pos_z, dir }
 *   player_leave  { player_id }
 *   chat          { id, player_id, display_name, color, channel, message, ts }
 *   district_enter { player_id, district_id }
 *   echo_decay    { updates: [{avatar_id, resonance, status}] }  (broadcast every 30s)
 *   error         { code, message }
 *
 * Run (from this folder):
 *   npm install
 *   npm start
 * Port: Railway inyecta PORT; fallback 3000 (no usar 8765 en Railway).
 *
 * Producción HTTPS:
 *   El HTML en HTTPS no puede abrir ws:// al mismo host: otro puerto sin TLS falla.
 *   Opciones: (1) Nginx/Caddy proxy_pass a este proceso y exponer wss://tudominio.com/nexus-ws
 *   con Upgrade/Connection headers; (2) TLS en Node (no incluido aquí).
 *   Tras el proxy, pon en la página: <meta name="nexus-ws-url" content="wss://tudominio.com/nexus-ws">
 */

'use strict';

const http = require('http');
const WebSocket = require('ws');

// Railway inyecta PORT (string); sin PORT → 3000 en local.
const PORT = process.env.PORT || 3000;

// Toda petición HTTP (/, /health, /favicon.ico, probes Railway) → 200, sin rutas ni condiciones.
const server = http.createServer((req, res) => {
    res.writeHead(200, {
        'Content-Type': 'text/plain',
        'Cache-Control': 'no-store',
    });
    res.end('nexus-ws ok');
});

const wss = new WebSocket.Server({ server });

// ────────────────────────────────────────────────
// State
// ────────────────────────────────────────────────

/** @type {Map<string, {ws, user_id, display_name, color_body, color_visor, color_echo, pos_x, pos_z, dir, district_id, last_active}>} */
const players = new Map(); // key = socket id (internal)

let _next_id = 1;
function newId() { return String(_next_id++); }

// ────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────

function send(ws, type, payload) {
    if (ws.readyState !== WebSocket.OPEN) return;
    try { ws.send(JSON.stringify({ type, ...payload })); }
    catch (_) {}
}

function broadcast(type, payload, excludeId = null) {
    for (const [id, p] of players) {
        if (id !== excludeId) send(p.ws, type, payload);
    }
}

function broadcastToDistrict(district_id, type, payload, excludeId = null) {
    for (const [id, p] of players) {
        if (id !== excludeId && p.district_id === district_id) {
            send(p.ws, type, payload);
        }
    }
}

function publicPlayer(p) {
    return {
        player_id:    p.id,
        user_id:      p.user_id,
        display_name: p.display_name,
        color_body:   p.color_body,
        color_visor:  p.color_visor,
        color_echo:   p.color_echo,
        pos_x:        p.pos_x,
        pos_z:        p.pos_z,
        dir:          p.dir,
        district_id:  p.district_id,
    };
}

// Sanitize chat: strip HTML-like chars, trim, limit length
function sanitizeMessage(msg) {
    if (typeof msg !== 'string') return '';
    return msg.replace(/[<>]/g, '').trim().slice(0, 200);
}

// ────────────────────────────────────────────────
// Connection handler
// ────────────────────────────────────────────────

wss.on('connection', (ws, req) => {
    const pid = newId();
    ws._pid   = pid;

    // Placeholder until 'join' received
    players.set(pid, {
        id:           pid,
        ws,
        user_id:      null,
        display_name: 'Anon',
        color_body:   '#00e8ff',
        color_visor:  '#00e8ff',
        color_echo:   '#ffd600',
        pos_x:        0,
        pos_z:        0,
        dir:          0,
        district_id:  'central',
        last_active:  Date.now(),
        joined:       false,
    });

    ws.on('message', (raw) => {
        let data;
        try { data = JSON.parse(raw); }
        catch (_) { return send(ws, 'error', { code: 'PARSE_ERROR', message: 'Invalid JSON' }); }

        const p = players.get(pid);
        if (!p) return;
        p.last_active = Date.now();

        const { type } = data;

        // ── join ──────────────────────────────────
        if (type === 'join') {
            const user_id = parseInt(data.user_id, 10);
            if (!user_id || isNaN(user_id)) {
                return send(ws, 'error', { code: 'INVALID_JOIN', message: 'user_id required' });
            }

            // Update player record
            p.user_id      = user_id;
            p.display_name = sanitizeMessage(data.display_name || 'Player').slice(0, 20) || 'Player';
            p.color_body   = /^#[0-9a-fA-F]{6}$/.test(data.color_body)  ? data.color_body  : '#00e8ff';
            p.color_visor  = /^#[0-9a-fA-F]{6}$/.test(data.color_visor) ? data.color_visor : '#00e8ff';
            p.color_echo   = /^#[0-9a-fA-F]{6}$/.test(data.color_echo)  ? data.color_echo  : '#ffd600';
            p.pos_x        = typeof data.pos_x === 'number' ? data.pos_x : 0;
            p.pos_z        = typeof data.pos_z === 'number' ? data.pos_z : 0;
            p.dir          = typeof data.dir   === 'number' ? data.dir   : 0;
            p.district_id  = 'central';
            p.joined       = true;

            // Send welcome with current player list
            const current = [];
            for (const [id, op] of players) {
                if (id !== pid && op.joined) current.push(publicPlayer(op));
            }
            send(ws, 'welcome', { player_id: pid, players: current });

            // Announce to others
            broadcast('player_join', { player: publicPlayer(p) }, pid);

            console.log(`[join] pid=${pid} user=${user_id} name="${p.display_name}"`);
            return;
        }

        // All subsequent events require join
        if (!p.joined) {
            return send(ws, 'error', { code: 'NOT_JOINED', message: 'Send join first' });
        }

        // ── move ──────────────────────────────────
        if (type === 'move') {
            const pos_x = typeof data.pos_x === 'number' ? data.pos_x : p.pos_x;
            const pos_z = typeof data.pos_z === 'number' ? data.pos_z : p.pos_z;
            const dir   = typeof data.dir   === 'number' ? data.dir   : p.dir;

            // Basic bounds check (world radius ~60 units)
            p.pos_x = Math.max(-60, Math.min(60, pos_x));
            p.pos_z = Math.max(-60, Math.min(60, pos_z));
            p.dir   = dir;

            broadcast('player_move', {
                player_id: pid,
                pos_x: p.pos_x,
                pos_z: p.pos_z,
                dir:   p.dir,
            }, pid);
            return;
        }

        // ── chat ──────────────────────────────────
        if (type === 'chat') {
            const VALID_CHANNELS = ['global', 'agora', 'olimpo', 'tesla', 'casino', 'central', 'sanctum'];
            const channel = VALID_CHANNELS.includes(data.channel) ? data.channel : 'global';
            const message = sanitizeMessage(data.message);

            if (!message) return;

            // Simple rate limit in-memory: track timestamps
            if (!p._msg_times) p._msg_times = [];
            const now = Date.now();
            p._msg_times = p._msg_times.filter(t => now - t < 3000);
            if (p._msg_times.length >= 3) {
                return send(ws, 'error', { code: 'RATE_LIMIT', message: 'Too fast' });
            }
            p._msg_times.push(now);

            const payload = {
                id:           now,
                player_id:    pid,
                user_id:      p.user_id,
                display_name: p.display_name,
                color:        p.color_body,
                channel,
                message,
                ts:           Math.floor(now / 1000),
            };

            if (channel === 'global') {
                broadcast('chat', payload); // include sender so they see their own msg
            } else if (channel === 'agora') {
                // Ágora bubble: todos ven, pero marcado para burbuja
                broadcast('chat', payload);
            } else {
                // Canal de distrito: solo jugadores en ese distrito
                broadcastToDistrict(channel, 'chat', payload);
            }
            return;
        }

        // ── district_enter ────────────────────────
        if (type === 'district_enter') {
            const VALID_DISTRICTS = ['central', 'olimpo', 'tesla', 'casino', 'agora', 'sanctum'];
            const district_id = VALID_DISTRICTS.includes(data.district_id)
                ? data.district_id
                : 'central';

            p.district_id = district_id;

            broadcast('district_enter', { player_id: pid, district_id }, pid);
            console.log(`[district] pid=${pid} → ${district_id}`);
            return;
        }

        // ── heartbeat ─────────────────────────────
        if (type === 'heartbeat') {
            send(ws, 'heartbeat', { ts: Date.now() });
            return;
        }

        send(ws, 'error', { code: 'UNKNOWN_TYPE', message: `Unknown event type: ${type}` });
    });

    ws.on('close', () => {
        players.delete(pid);
        broadcast('player_leave', { player_id: pid });
        console.log(`[leave] pid=${pid}`);
    });

    ws.on('error', (err) => {
        console.error(`[ws error] pid=${pid}:`, err.message);
        players.delete(pid);
        broadcast('player_leave', { player_id: pid });
    });
});

// ────────────────────────────────────────────────
// Echo decay broadcast (every 30s)
// Reads from MySQL and broadcasts resonance updates to all clients.
// Requires: npm install mysql2
// Falls back gracefully if DB not configured.
// ────────────────────────────────────────────────

let mysql2;
try { mysql2 = require('mysql2/promise'); } catch (_) { mysql2 = null; }

const DB_CONFIG = {
    host:     process.env.DB_HOST     || 'localhost',
    user:     process.env.DB_USER     || 'root',
    password: process.env.DB_PASS     || '',
    database: process.env.DB_NAME     || 'u354862096_kndstore',
};

async function broadcastEchoDecay() {
    if (!mysql2 || players.size === 0) return;

    let conn;
    try {
        conn = await mysql2.createConnection(DB_CONFIG);

        // Apply decay: resonancia baja 0.5 por cada 30s = 1 punto/min
        await conn.execute(`
            UPDATE nexus_echo
            SET resonance = GREATEST(0, resonance - 0.5),
                status = CASE
                    WHEN resonance - 0.5 >= 60 THEN 'active'
                    WHEN resonance - 0.5 >= 30 THEN 'ghost'
                    ELSE 'forgotten'
                END
        `);

        // Obtener actualizaciones para broadcast
        const [rows] = await conn.execute(
            'SELECT avatar_id, resonance, status FROM nexus_echo ORDER BY avatar_id'
        );

        broadcast('echo_decay', { updates: rows });

    } catch (err) {
        console.error('[echo_decay] DB error:', err.message);
    } finally {
        if (conn) conn.end();
    }
}

setInterval(broadcastEchoDecay, 30_000);

// ────────────────────────────────────────────────
// Idle player cleanup (every 2 min)
// ────────────────────────────────────────────────
setInterval(() => {
    const cutoff = Date.now() - 2 * 60 * 1000;
    for (const [pid, p] of players) {
        if (p.last_active < cutoff) {
            console.log(`[timeout] pid=${pid} idle`);
            p.ws.terminate();
            players.delete(pid);
            broadcast('player_leave', { player_id: pid });
        }
    }
}, 60_000);

// ────────────────────────────────────────────────
// Start
// ────────────────────────────────────────────────
server.listen(PORT, '0.0.0.0', () => {
    console.log(`[nexus-ws] listening on port ${PORT} (HTTP + WebSocket)`);
});
