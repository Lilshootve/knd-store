<?php
/**
 * Serves input image for AI jobs (e.g. upscale). Token in ?t= (job_uuid).
 * GPU server fetches from this URL.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/ai_config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/triposr.php';
require_once __DIR__ . '/../../includes/ai.php';

try {
    $token = trim($_GET['t'] ?? '');
    if ($token === '' || strlen($token) < 30) {
        http_response_code(400);
        exit('Invalid token');
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        http_response_code(500);
        exit('Service unavailable');
    }

    $job = null;
    try {
        $job = ai_get_job($pdo, $token);
    } catch (\Throwable $e) {
    }
    if (!$job) {
        $job = get_triposr_job($pdo, $token);
    }
    if (!$job || empty($job['input_path'])) {
        http_response_code(404);
        exit('Not found');
    }

    $fullPath = storage_path($job['input_path']);
    if (strpos($fullPath, storage_path()) !== 0) {
        http_response_code(403);
        exit('Forbidden');
    }
    if (!is_file($fullPath) || !is_readable($fullPath)) {
        http_response_code(404);
        exit('Image not found');
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
} catch (\Throwable $e) {
    error_log('ai/image: ' . $e->getMessage());
    http_response_code(500);
    exit('Error');
}
