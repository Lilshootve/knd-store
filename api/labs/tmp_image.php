<?php
/**
 * GET /api/labs/tmp_image.php?job_id=XXX
 * Serves temp upscale input image. Auth: X-KND-WORKER-TOKEN.
 * Worker downloads from here before uploading to ComfyUI.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/worker_auth.php';

$workerToken = get_worker_token();
$headerToken = trim($_SERVER['HTTP_X_KND_WORKER_TOKEN'] ?? '');
if ($workerToken === '' || $headerToken === '' || !hash_equals($workerToken, $headerToken)) {
    http_response_code(401);
    exit('Unauthorized');
}

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
if ($jobId <= 0) {
    http_response_code(400);
    exit('job_id required');
}

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    exit('Database error');
}

$stmt = $pdo->prepare("SELECT id, tool FROM knd_labs_jobs WHERE id = ? LIMIT 1");
$stmt->execute([$jobId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$job) {
    http_response_code(404);
    exit('Job not found');
}

$slot = trim($_GET['slot'] ?? 'input');
if ($slot === 'ref') {
    $filename = 'job_' . $jobId . '_ref.png';
} elseif ($slot === 'control') {
    $filename = 'job_' . $jobId . '_control.png';
} else {
    $filename = 'job_' . $jobId . '_input.png';
}
$relPath = 'uploads/labs/tmp/' . $filename;
$fullPath = storage_path($relPath);

$base = realpath(storage_path());
$resolved = is_file($fullPath) ? realpath($fullPath) : false;
if (!$resolved || !$base || strpos($resolved, $base) !== 0 || !is_readable($fullPath)) {
    http_response_code(404);
    exit('Image not found');
}

header('Content-Type: image/png');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
