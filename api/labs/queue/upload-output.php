<?php
/**
 * POST /api/labs/queue/upload-output.php (multipart)
 * Auth: X-KND-WORKER-TOKEN
 * Body: job_id, tool, image (file)
 * Saves output image to storage so image.php and recent jobs can serve it.
 * Returns { ok: true, output_path: "uploads/labs/job_123_text2img.png" }
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/worker_auth.php';
require_once __DIR__ . '/../../../includes/storage.php';

$workerToken = get_worker_token();
$headerToken = trim($_SERVER['HTTP_X_KND_WORKER_TOKEN'] ?? '');
if ($workerToken === '' || $headerToken === '' || !hash_equals($workerToken, $headerToken)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST only']);
        exit;
    }

    $jobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
    $tool = trim((string) ($_POST['tool'] ?? ''));
    if ($jobId <= 0 || $tool === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'job_id and tool required']);
        exit;
    }

    $file = $_FILES['image'] ?? null;
    if (!$file) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'image file required (check server upload_max_filesize and post_max_size)']);
        exit;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errMsg = [
            UPLOAD_ERR_INI_SIZE => 'file too large (upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'file too large',
            UPLOAD_ERR_PARTIAL => 'upload partial',
            UPLOAD_ERR_NO_FILE => 'no file',
        ];
        $msg = $errMsg[$file['error']] ?? 'upload error ' . $file['error'];
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $msg]);
        exit;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'image file required']);
        exit;
    }

    $size = filesize($file['tmp_name']);
    // Hard safety cap for worker uploads (must also be <= PHP post_max_size / upload_max_filesize)
    $maxBytes = 64 * 1024 * 1024; // 64MB
    if ($size <= 0 || $size > $maxBytes) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'image size invalid (max 64MB)']);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        $ext = 'png';
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id FROM knd_labs_jobs WHERE id = ? AND status = 'processing' LIMIT 1");
    $stmt->execute([$jobId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Job not found or not processing']);
        exit;
    }

    $labsDir = defined('LABS_UPLOAD_DIR') ? LABS_UPLOAD_DIR : 'uploads/labs';
    $outputPathRel = $labsDir . '/job_' . $jobId . '_' . $tool . '.' . $ext;
    $fullPath = storage_path($outputPathRel);
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!is_dir($dir) || !move_uploaded_file($file['tmp_name'], $fullPath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not save image']);
        exit;
    }

    echo json_encode(['ok' => true, 'output_path' => $outputPathRel]);
} catch (\Throwable $e) {
    error_log('api/labs/queue/upload-output: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
