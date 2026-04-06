<?php
/**
 * 3D Lab - Worker uploads GLB and preview to hosting storage.
 * POST /api/labs/3d-lab/upload-output.php
 * Auth: X-KND-3D-WORKER-TOKEN (3D upload token only; separate from Text2Img queue token).
 * Body: multipart with public_id, glb (file), preview (file, optional)
 *
 * Worker runs locally, web on hosting. Files are created on worker,
 * this API receives and saves them to hosting storage so download.php can serve.
 */
require_once __DIR__ . '/../../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache');
header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/worker_3d_upload_auth.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/labs_3d_helpers.php';

function json_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$workerToken = get_worker_3d_upload_token();
$headerToken = trim((string) ($_SERVER['HTTP_X_KND_3D_WORKER_TOKEN'] ?? ''));
$postToken = trim((string) ($_POST['_worker_3d_token'] ?? ''));
$token = $headerToken !== '' ? $headerToken : $postToken;
if ($workerToken === '' || $token === '' || !hash_equals($workerToken, $token)) {
    json_fail('Unauthorized', 401);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_fail('POST only', 405);
    }

    $publicId = trim((string) ($_POST['public_id'] ?? ''));
    if ($publicId === '' || strlen($publicId) < 10) {
        json_fail('public_id required');
    }

    $glbFile = $_FILES['glb'] ?? null;
    if (!$glbFile || $glbFile['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($glbFile['tmp_name'])) {
        json_fail('glb file required');
    }

    $ext = strtolower(pathinfo($glbFile['name'], PATHINFO_EXTENSION));
    if ($ext !== 'glb') {
        json_fail('glb must be .glb');
    }

    $outputDir = storage_path(LABS_3D_STORAGE_OUTPUT);
    $previewDir = storage_path(LABS_3D_STORAGE_PREVIEW);
    if (!is_dir($outputDir)) {
        @mkdir($outputDir, 0750, true);
    }
    if (!is_dir($previewDir)) {
        @mkdir($previewDir, 0750, true);
    }

    $glbDest = $outputDir . DIRECTORY_SEPARATOR . $publicId . '.glb';
    if (!move_uploaded_file($glbFile['tmp_name'], $glbDest)) {
        json_fail('Could not save GLB');
    }

    $previewRel = null;
    $previewFile = $_FILES['preview'] ?? null;
    if ($previewFile && $previewFile['error'] === UPLOAD_ERR_OK && is_uploaded_file($previewFile['tmp_name'])) {
        $prevExt = strtolower(pathinfo($previewFile['name'], PATHINFO_EXTENSION)) ?: 'webp';
        if (!in_array($prevExt, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $prevExt = 'webp';
        }
        $previewDest = $previewDir . DIRECTORY_SEPARATOR . $publicId . '.' . $prevExt;
        if (move_uploaded_file($previewFile['tmp_name'], $previewDest)) {
            $previewRel = LABS_3D_STORAGE_PREVIEW . '/' . $publicId . '.' . $prevExt;
        }
    }

    $glbRel = LABS_3D_STORAGE_OUTPUT . '/' . $publicId . '.glb';

    // Persist paths in DB so download works even if worker fails after upload
    $pdo = getDBConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE knd_labs_3d_jobs SET glb_path = ?, preview_path = COALESCE(?, preview_path), updated_at = NOW() WHERE public_id = ?"
            );
            $stmt->execute([$glbRel, $previewRel, $publicId]);
        } catch (\Throwable $dbEx) {
            error_log('api/labs/3d-lab/upload-output: DB update paths: ' . $dbEx->getMessage());
        }
    }

    echo json_encode(['ok' => true, 'glb_path' => $glbRel, 'preview_path' => $previewRel]);
} catch (\Throwable $e) {
    error_log('api/labs/3d-lab/upload-output: ' . $e->getMessage());
    json_fail('Upload failed', 500);
}
