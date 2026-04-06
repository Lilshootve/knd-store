<?php
/**
 * Preview AI job output (inline image for img src). GET /api/ai/preview.php?job_id=...
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/ai_config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/triposr.php';
require_once BASE_PATH . '/includes/ai.php';

try {
    $jobId = trim($_GET['job_id'] ?? '');
    if ($jobId === '') {
        http_response_code(400);
        exit;
    }

    require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        http_response_code(500);
        exit;
    }

    $job = null;
    try {
        $job = ai_get_job($pdo, $jobId);
    } catch (\Throwable $e) {
    }
    if (!$job) {
        $job = get_triposr_job($pdo, $jobId);
    }
    if (!$job || $job['status'] !== 'completed' || empty($job['output_path'])) {
        http_response_code(404);
        exit;
    }

    if ((int) $job['user_id'] !== (int) current_user_id()) {
        http_response_code(403);
        exit;
    }

    $fullPath = storage_path($job['output_path']);
    if (strpos(realpath($fullPath), realpath(storage_path())) !== 0 || !is_file($fullPath)) {
        http_response_code(404);
        exit;
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        http_response_code(400);
        exit('Preview only for images');
    }

    $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
} catch (\Throwable $e) {
    error_log('ai/preview: ' . $e->getMessage());
    http_response_code(500);
    exit;
}
