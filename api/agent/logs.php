<?php
/**
 * KND Agent — Logs Viewer
 * GET /api/agent/logs.php?token=<token>&limit=50&tool=db_query&status=ok
 *
 * Returns recent entries from knd_agent_logs.
 * Protected by KND_WORKER_TOKEN
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$token    = trim((string) (knd_env('KND_WORKER_TOKEN') ?? ''));
$provided = $_GET['token'] ?? '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($auth, 'Bearer ')) $provided = substr($auth, 7);

if ($token !== '' && !hash_equals($token, $provided)) {
    json_error('UNAUTHORIZED', 'Invalid or missing token.', 401);
}

$pdo = getDBConnection();
if (!$pdo) {
    json_error('DB_ERROR', 'Database connection failed.', 500);
}

$limit  = max(1, min(500, (int)($_GET['limit'] ?? 50)));
$tool   = $_GET['tool']   ?? '';
$status = $_GET['status'] ?? '';

$where  = [];
$params = [];

if ($tool !== '') {
    $where[]  = 'tool = ?';
    $params[] = $tool;
}
if ($status !== '') {
    $where[]  = 'status = ?';
    $params[] = $status;
}

$sql = 'SELECT id, timestamp, tool, action, result, status FROM knd_agent_logs';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC LIMIT ' . $limit;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON result fields for readability
    foreach ($logs as &$log) {
        $decoded = json_decode($log['result'] ?? '', true);
        if (is_array($decoded)) {
            $log['result'] = $decoded;
        }
    }
    unset($log);

    json_success([
        'logs'  => $logs,
        'count' => count($logs),
        'limit' => $limit,
    ]);
} catch (Throwable $e) {
    json_error('QUERY_FAILED', $e->getMessage(), 500);
}
