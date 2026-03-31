<?php
/**
 * Cancel InstantMesh 3D job (refunds KP).
 * Endpoint: POST /api/triposr/cancel.php (kept for backward compatibility)
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/triposr.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $jobId = trim($_POST['job_id'] ?? $_GET['job_id'] ?? '');
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

    if ($job['status'] !== 'pending') {
        json_error('CANNOT_CANCEL', 'Only queued jobs can be cancelled.', 400);
    }

    $ok = update_triposr_job($pdo, $jobId, [
        'status' => 'failed',
        'error_message' => 'Cancelled by user',
        'completed_at' => date('Y-m-d H:i:s'),
    ]);

    if (!$ok) {
        json_error('UPDATE_FAILED', 'Could not cancel job.', 500);
    }

    $cost = triposr_quality_cost($job['quality'] ?? 'balanced');
    triposr_refund_points($pdo, (int) $job['id'], (int) $job['user_id'], $cost);

    if (isset($_SESSION['sc_badge_cache'])) {
        unset($_SESSION['sc_badge_cache']);
    }

    json_success(['cancelled' => true, 'status' => 'failed']);
} catch (\Throwable $e) {
    error_log('instantmesh/cancel: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
