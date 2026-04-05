<?php
/**
 * KND Agent — One-time DB setup for knd_agent_logs table.
 * GET /api/agent/setup_logs.php?token=<KND_WORKER_TOKEN>
 *
 * Run once after deployment to ensure the log table exists.
 * Safe to run multiple times (CREATE TABLE IF NOT EXISTS).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';

header('Content-Type: application/json; charset=utf-8');

$token    = getenv('KND_WORKER_TOKEN') ?: '';
$provided = $_GET['token'] ?? '';
if ($token !== '' && !hash_equals($token, $provided)) {
    json_error('UNAUTHORIZED', 'Invalid or missing token.', 401);
}

$pdo = getDBConnection();
if (!$pdo) {
    json_error('DB_ERROR', 'Cannot connect to database.', 500);
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS knd_agent_logs (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            timestamp  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            action     VARCHAR(255) NOT NULL DEFAULT '',
            tool       VARCHAR(100) NOT NULL DEFAULT '',
            result     MEDIUMTEXT,
            status     VARCHAR(50) NOT NULL DEFAULT 'ok',
            INDEX idx_tool      (tool),
            INDEX idx_status    (status),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Verify it was created
    $stmt = $pdo->query("SHOW TABLES LIKE 'knd_agent_logs'");
    $exists = $stmt->fetch() !== false;

    json_success([
        'table'    => 'knd_agent_logs',
        'created'  => $exists,
        'message'  => $exists ? 'Table knd_agent_logs is ready.' : 'Table creation may have failed.',
    ]);
} catch (Throwable $e) {
    json_error('DB_SETUP_FAILED', $e->getMessage(), 500);
}
