<?php
/**
 * 3D Lab - Job status
 * GET /api/labs/3d-lab/status.php?id={job_id}
 */
require_once __DIR__ . '/../../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache');
header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
    }

    api_require_login();
    $userId = (int) current_user_id();

    $jobId = trim((string) ($_GET['id'] ?? $_GET['job_id'] ?? ''));
    if ($jobId === '') {
        json_error('INVALID_INPUT', 'id required.', 400);
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $stmt = $pdo->prepare(
        "SELECT id, user_id, public_id, mode, status, glb_path, preview_path, error_message,
                category, style, quality, created_at, completed_at
         FROM knd_labs_3d_jobs
         WHERE public_id = ? OR id = ? LIMIT 1"
    );
    $stmt->execute([$jobId, is_numeric($jobId) ? (int) $jobId : 0]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job || (int) $job['user_id'] !== $userId) {
        json_error('JOB_NOT_FOUND', 'Job not found.', 404);
    }

    $hasGlb = !empty($job['glb_path']);
    $response = [
        'id' => (int) $job['id'],
        'public_id' => $job['public_id'],
        'mode' => $job['mode'],
        'status' => $job['status'],
        'category' => $job['category'],
        'style' => $job['style'],
        'quality' => $job['quality'],
        'error_message' => $job['error_message'],
        'created_at' => $job['created_at'],
        'completed_at' => $job['completed_at'],
        'has_glb' => $hasGlb,
    ];

    if ($hasGlb) {
        $response['glb_url'] = '/api/labs/3d-lab/download.php?id=' . urlencode($job['public_id']) . '&format=glb';
    }
    if (!empty($job['preview_path'])) {
        $response['preview_url'] = '/api/labs/3d-lab/download.php?id=' . urlencode($job['public_id']) . '&format=preview&inline=1';
    }

    json_success($response);
} catch (\Throwable $e) {
    error_log('api/labs/3d-lab/status: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'Could not fetch status.', 500);
}
