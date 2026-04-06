<?php
/**
 * Poll InstantMesh 3D job status.
 * Endpoint: GET /api/triposr/status.php (kept for backward compatibility)
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/triposr.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
    }

    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $jobId = trim($_GET['job_id'] ?? '');
    if ($jobId === '') {
        json_error('INVALID_INPUT', 'job_id is required.');
    }

    $job = get_triposr_job($pdo, $jobId);
    if (!$job) {
        json_error('JOB_NOT_FOUND', 'Job not found.');
    }

    if ((int) $job['user_id'] !== (int) current_user_id()) {
        json_error('FORBIDDEN', 'You do not have access to this job.', 403);
    }

    $data = [
        'status' => $job['status'],
        'output_path' => $job['output_path'],
        'quality' => $job['quality'] ?? 'balanced',
        'error_message' => $job['error_message'],
        'created_at' => $job['created_at'],
        'completed_at' => $job['completed_at'],
    ];

    json_success($data);
} catch (\Throwable $e) {
    error_log('instantmesh/status: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
