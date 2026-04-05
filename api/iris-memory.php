<?php
/**
 * Iris Memory API
 *
 * GET    /api/iris-memory.php            → list all facts for current user
 * DELETE /api/iris-memory.php?key=X      → delete a specific fact
 * POST   /api/iris-memory.php            → create/update a fact  {key, value}
 *
 * Requires user to be logged in.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$userId = (int) current_user_id();

function mem_db(): PDO
{
    $pdo = getDBConnection();
    if (!$pdo) throw new RuntimeException('DB unavailable');
    return $pdo;
}

function mem_ensure_table(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS iris_user_memory (
            user_id    INT UNSIGNED  NOT NULL,
            fact_key   VARCHAR(100)  NOT NULL,
            fact_value VARCHAR(1000) NOT NULL,
            updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, fact_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = mem_db();
    mem_ensure_table($pdo);

    // ── GET ───────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT fact_key, fact_value, updated_at FROM iris_user_memory
             WHERE user_id = ? ORDER BY updated_at DESC'
        );
        $stmt->execute([$userId]);
        $facts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['facts' => $facts], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        $key = $_GET['key'] ?? '';
        if ($key === '') {
            http_response_code(400);
            echo json_encode(['error' => 'key is required']);
            exit;
        }

        $pdo->prepare('DELETE FROM iris_user_memory WHERE user_id = ? AND fact_key = ?')
            ->execute([$userId, $key]);

        echo json_encode(['deleted' => true, 'key' => $key]);
        exit;
    }

    // ── POST ──────────────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);

        $key   = isset($data['key'])   && is_string($data['key'])   ? trim($data['key'])   : '';
        $value = isset($data['value']) && is_string($data['value']) ? trim($data['value']) : '';

        if ($key === '' || $value === '') {
            http_response_code(400);
            echo json_encode(['error' => 'key and value are required']);
            exit;
        }

        $key   = mb_substr($key,   0, 100);
        $value = mb_substr($value, 0, 1000);

        $pdo->prepare(
            'INSERT INTO iris_user_memory (user_id, fact_key, fact_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE fact_value = VALUES(fact_value), updated_at = NOW()'
        )->execute([$userId, $key, $value]);

        echo json_encode(['saved' => true, 'key' => $key, 'value' => $value]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    error_log('[iris-mem-api] ' . $e->getMessage());
}
