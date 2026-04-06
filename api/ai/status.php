<?php
/**
 * AI job status polling. GET /api/ai/status.php?job_id=...
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
require_once BASE_PATH . '/includes/ai.php';

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

    $job = null;
    try {
        $job = ai_get_job($pdo, $jobId);
    } catch (\Throwable $e) {
    }
    if (!$job) {
        $job = get_triposr_job($pdo, $jobId);
        if (!$job) {
            json_error('JOB_NOT_FOUND', 'Job not found.');
        }
        if (!isset($job['job_type'])) $job['job_type'] = 'img23d';
        if (!isset($job['result'])) $job['result'] = [];
    }

    if ((int) $job['user_id'] !== (int) current_user_id()) {
        json_error('FORBIDDEN', 'Access denied.', 403);
    }

    $data = [
        'status' => $job['status'],
        'job_type' => $job['job_type'] ?? 'img23d',
        'output_path' => $job['output_path'] ?? null,
        'error_message' => $job['error_message'] ?? null,
        'result' => $job['result'] ?? [],
        'created_at' => $job['created_at'] ?? null,
        'completed_at' => $job['completed_at'] ?? null,
    ];
    if (isset($job['quality'])) $data['quality'] = $job['quality'];
    if (isset($job['cost_kp'])) $data['cost_kp'] = (int) $job['cost_kp'];

    json_success($data);
} catch (\Throwable $e) {
    error_log('ai/status: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
