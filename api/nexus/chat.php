<?php
// api/nexus/chat.php
// GET  — últimos 50 mensajes de un canal (global | agora | district:<id>)
// POST — enviar mensaje al canal (requiere login)
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

$pdo    = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Canales válidos
define('CHAT_CHANNELS', ['global', 'agora', 'olimpo', 'tesla', 'casino', 'central']);

// ──────────────────────────────────────────────────────────────
// GET — historial de mensajes
// ──────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $channel = isset($_GET['channel']) ? trim($_GET['channel']) : 'global';
    if (!in_array($channel, CHAT_CHANNELS, true)) {
        json_error('INVALID_CHANNEL', 'Unknown channel');
    }

    $limit  = min((int)($_GET['limit']  ?? 50), 100);
    $before = isset($_GET['before']) && is_numeric($_GET['before'])
        ? (float)$_GET['before']
        : null;

    try {
        $params = [$channel, $limit];
        $where  = 'channel = ?';

        if ($before !== null) {
            $where   .= ' AND UNIX_TIMESTAMP(sent_at) < ?';
            $params   = [$channel, $before, $limit];
        }

        $sql = "
            SELECT
                cl.id,
                cl.user_id,
                COALESCE(npa.display_name, u.username) AS display_name,
                COALESCE(npa.color_body, '#00e8ff')    AS color,
                cl.message,
                cl.channel,
                UNIX_TIMESTAMP(cl.sent_at)             AS ts
            FROM nexus_chat_log cl
            JOIN users u ON u.id = cl.user_id
            LEFT JOIN nexus_player_appearance npa ON npa.user_id = cl.user_id
            WHERE {$where}
            ORDER BY cl.sent_at DESC
            LIMIT ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Devolver en orden cronológico (más viejo primero)
        $messages = array_reverse($messages);

        json_success(['messages' => $messages, 'channel' => $channel]);

    } catch (PDOException $e) {
        error_log('nexus/chat GET error: ' . $e->getMessage());
        json_error('DB_ERROR', 'Failed to fetch messages', 500);
    }
}

// ──────────────────────────────────────────────────────────────
// POST — enviar mensaje
// ──────────────────────────────────────────────────────────────
elseif ($method === 'POST') {
    api_require_login();
    $uid = (int)$_SESSION['user_id'];

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        json_error('INVALID_INPUT', 'JSON body required');
    }

    $channel = isset($input['channel']) ? trim($input['channel']) : 'global';
    if (!in_array($channel, CHAT_CHANNELS, true)) {
        json_error('INVALID_CHANNEL', 'Unknown channel');
    }

    $message = isset($input['message']) ? trim($input['message']) : '';
    if ($message === '') {
        json_error('EMPTY_MESSAGE', 'Message cannot be empty');
    }
    // Sanitize: strip HTML, limit length
    $message = mb_substr(strip_tags($message), 0, 200, 'UTF-8');
    if ($message === '') {
        json_error('EMPTY_MESSAGE', 'Message cannot be empty after sanitization');
    }

    try {
        // Rate limit: máx 3 mensajes en los últimos 3 segundos por usuario
        $rate = $pdo->prepare("
            SELECT COUNT(*) FROM nexus_chat_log
            WHERE user_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 3 SECOND)
        ");
        $rate->execute([$uid]);
        if ((int)$rate->fetchColumn() >= 3) {
            json_error('RATE_LIMIT', 'Slow down — too many messages', 429);
        }

        $pdo->prepare("
            INSERT INTO nexus_chat_log (user_id, channel, message, sent_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([$uid, $channel, $message]);

        $msg_id = (int)$pdo->lastInsertId();

        // Obtener apariencia para devolver al cliente (WebSocket broadcast usa esto)
        $appear = $pdo->prepare("
            SELECT COALESCE(npa.display_name, u.username) AS display_name,
                   COALESCE(npa.color_body, '#00e8ff')    AS color
            FROM users u
            LEFT JOIN nexus_player_appearance npa ON npa.user_id = u.id
            WHERE u.id = ?
        ");
        $appear->execute([$uid]);
        $sender = $appear->fetch(PDO::FETCH_ASSOC);

        json_success([
            'sent'         => true,
            'id'           => $msg_id,
            'user_id'      => $uid,
            'display_name' => $sender['display_name'] ?? 'Player',
            'color'        => $sender['color']        ?? '#00e8ff',
            'channel'      => $channel,
            'message'      => $message,
            'ts'           => time(),
        ]);

    } catch (PDOException $e) {
        error_log('nexus/chat POST error: ' . $e->getMessage());
        json_error('DB_ERROR', 'Failed to send message', 500);
    }

} else {
    json_error('METHOD_NOT_ALLOWED', 'Only GET or POST allowed', 405);
}
