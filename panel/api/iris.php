<?php
/**
 * Iris — same-origin proxy + conversation & memory manager.
 *
 * Responsibilities:
 *  1. Fix session key: uses $_SESSION['dr_user_id'] (kndstore user system)
 *  2. Auto-create MySQL tables on first request (DDL-on-demand)
 *  3. Load user memory facts from MySQL → pass as user_memory to Next.js
 *  4. Manage conversation_id: create new / validate existing
 *  5. Load conversation history from MySQL → pass as conversation_history
 *  6. Forward enriched body to Next.js
 *  7. Save user+assistant turn to MySQL conversation
 *  8. Save memory_updates returned by Next.js to MySQL
 *  9. Inject conversation_id back into the response for the browser
 *
 * Guest users (not logged in): no conversation/memory management.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/env.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/iris_memory_mysql.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['type' => 'chat', 'response' => 'System unavailable']);
    exit;
}

function iris_log(string $msg): void { error_log('[iris-proxy] ' . $msg); }

function iris_fail(int $code, string $logDetail): void
{
    iris_log($logDetail);
    http_response_code($code);
    echo json_encode(['type' => 'chat', 'response' => 'System unavailable']);
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') iris_fail(400, 'empty body');

$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['message']) || !is_string($body['message']) || trim($body['message']) === '') {
    iris_fail(400, 'invalid json or missing message');
}

$message    = mb_substr(trim($body['message']), 0, 16000);
$context    = (isset($body['context']) && is_array($body['context'])) ? $body['context'] : ['includeLastRun' => true];
$confirmMode = isset($body['confirm']) && $body['confirm'] === true;
$confirmId   = isset($body['confirm_id']) && is_string($body['confirm_id']) ? $body['confirm_id'] : null;
$clientConvId = isset($body['conversation_id']) && is_int($body['conversation_id'])
    ? (int)$body['conversation_id'] : null;

// ── Auth ──────────────────────────────────────────────────────────────────────

$userId    = current_user_id();   // int|null — uses $_SESSION['dr_user_id']
$isLoggedIn = $userId !== null;

$irisMode  = !empty($_SESSION['admin_logged_in']) ? 'admin' : 'public';

// ── DB helpers ────────────────────────────────────────────────────────────────

/**
 * Ensure the three Iris tables exist. Called lazily on first request.
 * Uses the same DDL-on-demand pattern as execute.php / agent_log_entry.
 */
function iris_ensure_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS iris_conversations (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL,
            title      VARCHAR(255) NOT NULL DEFAULT 'Nueva conversación',
            mode       VARCHAR(20)  NOT NULL DEFAULT 'public',
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_updated (user_id, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS iris_messages (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            conversation_id BIGINT UNSIGNED NOT NULL,
            role            VARCHAR(20)     NOT NULL,
            content         TEXT            NOT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conv (conversation_id),
            FOREIGN KEY (conversation_id) REFERENCES iris_conversations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!knd_iris_ensure_user_memory_table($pdo)) {
        throw new RuntimeException('iris_user_memory schema ensure failed');
    }
}

function iris_load_memory(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT fact_key, fact_value FROM iris_user_memory WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
}

function iris_save_memory(PDO $pdo, int $userId, array $updates): void
{
    if (empty($updates)) return;
    $stmt = $pdo->prepare(
        'INSERT INTO iris_user_memory (user_id, fact_key, fact_value)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE fact_value = VALUES(fact_value), updated_at = NOW()'
    );
    foreach ($updates as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $stmt->execute([$userId, $key, mb_substr($value, 0, 1000)]);
        }
    }
}

function iris_delete_memory(PDO $pdo, int $userId, string $factKey): void
{
    $pdo->prepare('DELETE FROM iris_user_memory WHERE user_id = ? AND fact_key = ?')
        ->execute([$userId, $factKey]);
}

function iris_get_or_create_conversation(PDO $pdo, ?int $convId, int $userId, string $mode): int
{
    if ($convId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM iris_conversations WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$convId, $userId]);
        $found = $stmt->fetchColumn();
        if ($found) return (int)$found;
    }
    $pdo->prepare('INSERT INTO iris_conversations (user_id, mode) VALUES (?, ?)')
        ->execute([$userId, $mode]);
    return (int)$pdo->lastInsertId();
}

function iris_load_history(PDO $pdo, int $convId, int $limit = 30): array
{
    $stmt = $pdo->prepare(
        'SELECT role, content FROM iris_messages
         WHERE conversation_id = ?
         ORDER BY id DESC LIMIT ?'
    );
    $stmt->execute([$convId, $limit]);
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    return array_map(fn($r) => ['role' => $r['role'], 'content' => $r['content']], $rows);
}

function iris_save_turn(PDO $pdo, int $convId, string $userMsg, string $assistantMsg): void
{
    $insert = $pdo->prepare(
        'INSERT INTO iris_messages (conversation_id, role, content) VALUES (?, ?, ?)'
    );
    $insert->execute([$convId, 'user',      mb_substr($userMsg,      0, 65535)]);
    $insert->execute([$convId, 'assistant', mb_substr($assistantMsg, 0, 65535)]);

    $pdo->prepare('UPDATE iris_conversations SET updated_at = NOW() WHERE id = ?')
        ->execute([$convId]);
}

function iris_update_title(PDO $pdo, int $convId, string $firstMessage): void
{
    $title = mb_substr(trim($firstMessage), 0, 80);
    if ($title === '') $title = 'Nueva conversación';
    $pdo->prepare(
        "UPDATE iris_conversations SET title = ? WHERE id = ? AND title = 'Nueva conversación'"
    )->execute([$title, $convId]);
}

// ── Conversation & memory management (logged-in users only) ──────────────────

$convId     = null;
$history    = [];
$userMemory = [];

if ($isLoggedIn && !$confirmMode) {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            iris_ensure_tables($pdo);

            $userMemory = iris_load_memory($pdo, $userId);
            $convId     = iris_get_or_create_conversation($pdo, $clientConvId, $userId, $irisMode);
            $history    = iris_load_history($pdo, $convId);
        }
    } catch (Throwable $e) {
        iris_log('DB init failed: ' . $e->getMessage());
        // Graceful degradation: continue without DB features
        $convId     = null;
        $history    = [];
        $userMemory = [];
    }
}

// On confirm requests just forward with the same conversation state
if ($confirmMode && $isLoggedIn && $clientConvId !== null) {
    try {
        $pdo = $pdo ?? getDBConnection();
        if ($pdo) {
            iris_ensure_tables($pdo);
            $convId  = $clientConvId;
            $history = iris_load_history($pdo, $convId);
        }
    } catch (Throwable $e) {
        iris_log('DB conv reload failed: ' . $e->getMessage());
    }
}

// ── Retail business context (optional enrichment) ─────────────────────────────

$retailBiz = null;
if ($isLoggedIn && !empty($pdo) && $userId !== null) {
    try {
        $retailAuthFile = BASE_PATH . '/core/retail/auth.php';
        if (file_exists($retailAuthFile)) {
            require_once $retailAuthFile;
            $retailBiz = retail_resolve_business_for_gateway($pdo, $userId);
        }
    } catch (Throwable $e) {
        iris_log('retail business resolve failed: ' . $e->getMessage());
    }
}

// ── Build payload for Next.js ─────────────────────────────────────────────────

$nextPayload = [
    'message'              => $message,
    'context'              => $context,
    'conversation_history' => $history,
    'iris_mode'            => $irisMode,
    'user_id'              => $userId !== null ? (string)$userId : null,
    'user_memory'          => empty($userMemory) ? null : $userMemory,
    'business_type'        => $retailBiz ? 'retail' : null,
    'business_id'          => $retailBiz ? (int)($retailBiz['id'] ?? 0) : null,
    'business_currency'    => $retailBiz ? ($retailBiz['base_currency'] ?? null) : null,
];

if ($confirmMode && $confirmId !== null) {
    $nextPayload['confirm']    = true;
    $nextPayload['confirm_id'] = $confirmId;
}
if (isset($body['session_id']) && is_string($body['session_id'])) {
    $nextPayload['session_id'] = $body['session_id'];
}

try {
    $payload = json_encode($nextPayload, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    iris_fail(400, 'payload encode failed: ' . $e->getMessage());
}

// ── Forward to Next.js ────────────────────────────────────────────────────────
// Producción: definir en .env IRIS_AGENTS_CHAT_URL=https://tu-dominio.com/api/iris/chat
// (si falta, el default 127.0.0.1 provoca 503 en el servidor público).

$upstream = knd_env('IRIS_AGENTS_CHAT_URL', 'http://127.0.0.1:3000/api/iris/chat') ?? 'http://127.0.0.1:3000/api/iris/chat';
$upstream = trim($upstream) ?: 'http://127.0.0.1:3000/api/iris/chat';

$headers = ['Content-Type: application/json'];
$apiKey  = knd_env('IRIS_AGENTS_API_KEY', null);
if ($apiKey !== null && trim($apiKey) !== '') {
    $headers[] = 'X-API-Key: ' . trim($apiKey);
}

$ch = curl_init($upstream);
if ($ch === false) iris_fail(503, 'curl_init failed');

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
]);

$response = curl_exec($ch);
$errno    = curl_errno($ch);
$errstr   = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno !== 0 || $response === false) {
    iris_fail(503, sprintf('curl errno=%d error=%s http=%d upstream=%s', $errno, $errstr, $httpCode, $upstream));
}

$response = preg_replace('/^\xEF\xBB\xBF/', '', (string)$response) ?? '';
if ($response === '') iris_fail(503, 'empty upstream body http=' . $httpCode);

try {
    $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    iris_fail(503, 'upstream not json: ' . mb_substr($response, 0, 200));
}

if (!is_array($decoded) || !isset($decoded['type']) || !is_string($decoded['type'])) {
    iris_fail(503, 'upstream json missing type http=' . $httpCode);
}

// ── Post-response: save turn + memory updates ─────────────────────────────────

if ($isLoggedIn && $convId !== null && !$confirmMode) {
    $assistantText = '';
    if (isset($decoded['response']) && is_string($decoded['response'])) {
        $assistantText = $decoded['response'];
    }

    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            $pdo = getDBConnection();
        }
        if ($pdo && $assistantText !== '') {
            iris_save_turn($pdo, $convId, $message, $assistantText);
            // Update title from first user message
            if (count($history) === 0) {
                iris_update_title($pdo, $convId, $message);
            }
        }

        // Save any memory facts detected by Next.js
        if (
            $pdo &&
            isset($decoded['memory_updates']) &&
            is_array($decoded['memory_updates']) &&
            !empty($decoded['memory_updates'])
        ) {
            iris_save_memory($pdo, $userId, $decoded['memory_updates']);
        }
    } catch (Throwable $e) {
        iris_log('post-response DB failed: ' . $e->getMessage());
    }
}

// ── Inject conversation_id into response ──────────────────────────────────────

if ($convId !== null) {
    $decoded['conversation_id'] = $convId;
}

// Remove internal fields not needed by the browser
unset($decoded['memory_updates']);

http_response_code(200);
echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
