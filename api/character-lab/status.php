<?php
/**
 * Character Lab - Job status
 * GET /api/character-lab/status.php?id={job_id}
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
    $userId = (int) current_user_id();

    $jobId = trim((string) ($_GET['id'] ?? $_GET['job_id'] ?? ''));
    if ($jobId === '') {
        json_error('INVALID_INPUT', 'id or job_id is required.', 400);
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $stmt = $pdo->prepare(
        "SELECT id, user_id, public_id, mode, status, concept_image_path, mesh_glb_path,
                preview_thumb_path, error_message, kp_cost, created_at, processing_started_at, completed_at
         FROM knd_character_lab_jobs
         WHERE public_id = ? OR id = ? LIMIT 1"
    );
    $stmt->execute([$jobId, is_numeric($jobId) ? (int) $jobId : 0]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        json_error('JOB_NOT_FOUND', 'Job not found.', 404);
    }
    if ((int) $job['user_id'] !== $userId) {
        json_error('FORBIDDEN', 'You do not have access to this job.', 403);
    }

    $hasConcept = !empty($job['concept_image_path']);
    $hasGlb = !empty($job['mesh_glb_path']);
    $hasPreview = !empty($job['preview_thumb_path']);

    $response = [
        'id' => (int) $job['id'],
        'public_id' => $job['public_id'],
        'mode' => $job['mode'],
        'status' => $job['status'],
        'kp_cost' => (int) $job['kp_cost'],
        'error_message' => $job['error_message'],
        'created_at' => $job['created_at'],
        'processing_started_at' => $job['processing_started_at'],
        'completed_at' => $job['completed_at'],
        'has_concept' => $hasConcept,
        'has_glb' => $hasGlb,
        'has_preview' => $hasPreview,
    ];

    if ($hasConcept) {
        $response['concept_url'] = '/api/character-lab/download.php?id=' . urlencode($job['public_id']) . '&format=concept&inline=1';
    }
    if ($hasPreview) {
        $response['preview_url'] = '/api/character-lab/download.php?id=' . urlencode($job['public_id']) . '&format=preview&inline=1';
    }

    json_success($response);
} catch (\Throwable $e) {
    error_log('api/character-lab/status: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'Could not fetch job status.', 500);
}
