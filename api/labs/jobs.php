<?php
/**
 * KND Labs - Recent jobs for logged user
 * GET /api/labs/jobs.php
 */
header('Cache-Control: no-store, no-cache');
header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/json.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
    }

    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $limit = (int) ($_GET['limit'] ?? 20);
    $limit = max(1, min(50, $limit));
    $filterProvider = trim($_GET['provider'] ?? '');
    $filterTool = trim($_GET['tool'] ?? '');

    $where = "user_id = ?";
    $params = [current_user_id()];
    if ($filterTool !== '') {
        $where .= " AND tool = ?";
        $params[] = $filterTool;
    }
    if ($filterProvider === 'local') {
        $where .= " AND (provider = 'local' OR provider IS NULL)";
    } elseif ($filterProvider === 'runpod') {
        $where .= " AND provider = 'runpod'";
    } elseif ($filterProvider === 'failed') {
        $where .= " AND status = 'failed'";
    }

    $stmt = $pdo->prepare(
        "SELECT id, tool, prompt, status, image_url, cost_kp, provider, created_at
         FROM knd_labs_jobs
         WHERE {$where}
         ORDER BY created_at DESC
         LIMIT {$limit}"
    );
    if (!$stmt || !$stmt->execute($params)) {
        json_error('DB_ERROR', 'Could not fetch jobs.', 500);
    }
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($jobs as &$j) {
        $j['job_id'] = (string) $j['id'];
        unset($j['id']);
    }

    json_success(['jobs' => $jobs]);
} catch (\Throwable $e) {
    error_log('api/labs/jobs: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}
