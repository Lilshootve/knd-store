<?php
/**
 * Serves the input image for an InstantMesh 3D job.
 * Called by the GPU server - job_uuid in ?t= is used (unguessable).
 * Endpoint kept at /api/triposr/image.php for backward compatibility.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/triposr_config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/triposr.php';

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

    $job = get_triposr_job($pdo, $token);
    if (!$job) {
        http_response_code(404);
        exit('Job not found');
    }

    $fullPath = storage_path($job['input_path']);
    if (!is_file($fullPath) || !is_readable($fullPath)) {
        http_response_code(404);
        exit('Image not found');
    }

    $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
    $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
} catch (\Throwable $e) {
    error_log('instantmesh/image: ' . $e->getMessage());
    http_response_code(500);
    exit('Error');
}
