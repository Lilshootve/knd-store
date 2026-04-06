<?php
/**
 * KND Labs InstantMesh - job status
 * GET /api/labs/instantmesh/status.php?job_id={public_id}
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

    $publicId = trim((string) ($_GET['job_id'] ?? ''));
    if ($publicId === '') {
        json_error('INVALID_INPUT', 'job_id is required.', 400);
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $stmt = $pdo->prepare(
        "SELECT id, user_id, public_id, status, preview_image_path, output_glb_path, output_obj_path,
                remove_bg, seed, output_format, error_message, processing_started_at, completed_at, created_at
         FROM knd_labs_instantmesh_jobs
         WHERE public_id = ? LIMIT 1"
    );
    $stmt->execute([$publicId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        json_error('JOB_NOT_FOUND', 'Job not found.', 404);
    }
    if ((int) $job['user_id'] !== $userId) {
        json_error('FORBIDDEN', 'You do not have access to this job.', 403);
    }

    $hasGlb = !empty($job['output_glb_path']);
    $hasObj = !empty($job['output_obj_path']);
    $hasPreview = !empty($job['preview_image_path']);

    $startedTs = !empty($job['processing_started_at']) ? strtotime((string) $job['processing_started_at']) : null;
    $endedTs = !empty($job['completed_at']) ? strtotime((string) $job['completed_at']) : null;
    $totalSeconds = null;
    if ($startedTs && $endedTs && $endedTs >= $startedTs) {
        $totalSeconds = $endedTs - $startedTs;
    }

    $response = [
        'id' => (int) $job['id'],
        'public_id' => $job['public_id'],
        'status' => $job['status'],
        'remove_bg' => (int) $job['remove_bg'],
        'seed' => (int) $job['seed'],
        'output_format' => $job['output_format'],
        'error_message' => $job['error_message'],
        'created_at' => $job['created_at'],
        'processing_started_at' => $job['processing_started_at'],
        'completed_at' => $job['completed_at'],
        'total_time_seconds' => $totalSeconds,
        'has_preview' => $hasPreview,
        'has_glb' => $hasGlb,
        'has_obj' => $hasObj,
    ];

    if ($hasPreview) {
        $response['preview_url'] = '/api/labs/instantmesh/download.php?job_id=' . urlencode($job['public_id']) . '&format=preview&inline=1';
    }

    json_success($response);
} catch (\Throwable $e) {
    error_log('api/labs/instantmesh/status: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'Could not fetch job status.', 500);
}
