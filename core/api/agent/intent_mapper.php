<?php
/**
 * KND Agent — Intent Mapper
 *
 * Maps natural-language messages to tool calls WITHOUT calling an LLM.
 * Uses keyword pattern matching — zero token cost, instant response.
 *
 * intent_map(string $message): ?array
 *   Returns: { tool: string, input: array } or null if no match found.
 *
 * Only include this file; do not call it directly via HTTP.
 */

declare(strict_types=1);

if (!function_exists('intent_map')) {

/**
 * Map a natural-language message to a tool+input pair.
 * Returns null when no pattern matches (caller should fall back to LLM or error).
 */
function intent_map(string $message): ?array
{
    $msg = trim(strtolower($message));

    // ── Shortcut: already looks like SQL ─────────────────────────────────────
    if (preg_match('/^\s*(select|insert|update|delete)\b/i', $msg)) {
        $op = strtoupper(explode(' ', ltrim($msg))[0]);
        if ($op === 'SELECT') {
            return ['tool' => 'db_query', 'input' => ['sql' => trim($message), 'params' => []]];
        }
        return ['tool' => 'db_execute', 'input' => ['sql' => trim($message), 'params' => []]];
    }

    // ── USER queries ──────────────────────────────────────────────────────────
    if (_matches($msg, ['usuarios', 'users', 'user list', 'lista de usuarios', 'dame usuarios', 'show users'])) {
        return _db_query('SELECT id, username, email, created_at FROM users ORDER BY id DESC LIMIT 20');
    }

    if (_matches($msg, ['cuántos usuarios', 'how many users', 'total users', 'total de usuarios', 'count users'])) {
        return _db_query('SELECT COUNT(*) AS total_users FROM users');
    }

    if (_matches($msg, ['nuevos usuarios', 'new users', 'usuarios recientes', 'recent users', 'últimos usuarios'])) {
        return _db_query('SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 10');
    }

    if (_matches($msg, ['usuarios activos', 'active users', 'online users', 'usuarios online', 'usuarios conectados'])) {
        return _db_query('SELECT id, username, last_active FROM users WHERE last_active > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY last_active DESC LIMIT 20');
    }

    // ── GAME — Mind Wars ─────────────────────────────────────────────────────
    if (_matches($msg, ['mind wars', 'batallas', 'battles', 'partidas recientes', 'recent battles'])) {
        return _db_query('SELECT * FROM mind_wars_battles ORDER BY created_at DESC LIMIT 20');
    }

    if (_matches($msg, ['leaderboard', 'ranking', 'top jugadores', 'top players', 'mejores jugadores'])) {
        return _db_query('SELECT u.username, mw.wins, mw.losses, mw.elo FROM users u JOIN mind_wars_stats mw ON u.id = mw.user_id ORDER BY mw.elo DESC LIMIT 20');
    }

    // ── GAME — Knowledge Duel ────────────────────────────────────────────────
    if (_matches($msg, ['knowledge duel', 'duelos', 'duels', 'trivia'])) {
        return _db_query('SELECT * FROM knowledge_duel_sessions ORDER BY created_at DESC LIMIT 20');
    }

    // ── AVATARS ───────────────────────────────────────────────────────────────
    if (_matches($msg, ['avatares', 'avatars', 'skins', 'items de avatar', 'avatar items'])) {
        return _db_query('SELECT id, name, rarity, cost_kp, active FROM avatar_items ORDER BY rarity DESC, id DESC LIMIT 50');
    }

    // ── KP / ECONOMY ─────────────────────────────────────────────────────────
    if (_matches($msg, ['kp', 'puntos', 'points', 'balance', 'saldo', 'knd points', 'economía', 'economy'])) {
        return _db_query('SELECT u.id, u.username, w.kp_balance FROM users u JOIN wallets w ON u.id = w.user_id ORDER BY w.kp_balance DESC LIMIT 20');
    }

    if (_matches($msg, ['transacciones', 'transactions', 'movimientos', 'transfers'])) {
        return _db_query('SELECT * FROM kp_transactions ORDER BY created_at DESC LIMIT 20');
    }

    // ── AI LABS ───────────────────────────────────────────────────────────────
    if (_matches($msg, ['lab jobs', 'trabajos de lab', 'image jobs', 'jobs de imagen', 'ai jobs', 'generaciones recientes'])) {
        return _db_query('SELECT id, user_id, status, provider, created_at FROM lab_jobs ORDER BY created_at DESC LIMIT 20');
    }

    if (_matches($msg, ['jobs pendientes', 'pending jobs', 'queue', 'cola de trabajos'])) {
        return _db_query("SELECT id, user_id, status, provider, created_at FROM lab_jobs WHERE status IN ('pending','queued') ORDER BY created_at ASC LIMIT 50");
    }

    if (_matches($msg, ['3d jobs', 'trabajos 3d', '3d lab', 'instantmesh', 'triposr'])) {
        return _db_query('SELECT id, user_id, status, created_at FROM lab_3d_jobs ORDER BY created_at DESC LIMIT 20');
    }

    // ── ORDERS / PAYMENTS ────────────────────────────────────────────────────
    if (_matches($msg, ['orders', 'pedidos', 'órdenes', 'compras', 'purchases'])) {
        return _db_query('SELECT id, user_id, total, status, created_at FROM orders ORDER BY created_at DESC LIMIT 20');
    }

    if (_matches($msg, ['pagos', 'payments', 'paypal', 'ingresos', 'revenue'])) {
        return _db_query('SELECT id, user_id, amount_usd, method, status, created_at FROM support_credit_payments ORDER BY created_at DESC LIMIT 20');
    }

    // ── LOGS ─────────────────────────────────────────────────────────────────
    if (_matches($msg, ['agent logs', 'logs de agente', 'execution logs', 'historial de ejecuciones'])) {
        return _db_query('SELECT id, timestamp, tool, status, action FROM knd_agent_logs ORDER BY id DESC LIMIT 30');
    }

    if (_matches($msg, ['error logs', 'errores', 'failed executions', 'ejecuciones fallidas'])) {
        return _db_query("SELECT id, timestamp, tool, action, result FROM knd_agent_logs WHERE status = 'error' ORDER BY id DESC LIMIT 20");
    }

    // ── SYSTEM / TABLES ───────────────────────────────────────────────────────
    if (_matches($msg, ['tablas', 'tables', 'database tables', 'show tables', 'db tables'])) {
        return _db_query('SHOW TABLES');
    }

    if (_matches($msg, ['tabla de usuarios', 'users table structure', 'estructura de users', 'describe users'])) {
        return _db_query('DESCRIBE users');
    }

    // ── FILE operations ───────────────────────────────────────────────────────
    if (_matches($msg, ['listar uploads', 'list uploads', 'archivos subidos', 'uploaded files'])) {
        return [
            'tool'  => 'file_manager',
            'input' => ['action' => 'list', 'path' => 'uploads/'],
        ];
    }

    // ── IRIS chat passthrough ─────────────────────────────────────────────────
    if (_matches($msg, ['pregunta iris', 'ask iris', 'habla con iris', 'chat with iris', 'iris responde'])) {
        // Strip the trigger phrase to get the actual message
        $clean = preg_replace('/^(pregunta iris|ask iris|habla con iris|chat with iris|iris responde)[:\s]*/i', '', $message);
        if (trim($clean) !== '') {
            return ['tool' => 'iris_chat', 'input' => ['message' => trim($clean)]];
        }
    }

    // No match
    return null;
}

/**
 * Check if message contains any of the given keywords/phrases.
 */
function _matches(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (str_contains($haystack, strtolower($needle))) {
            return true;
        }
    }
    return false;
}

/**
 * Shorthand builder for db_query intents.
 */
function _db_query(string $sql, array $params = []): array
{
    return [
        'tool'  => 'db_query',
        'input' => ['sql' => $sql, 'params' => $params],
    ];
}

} // end if (!function_exists)
