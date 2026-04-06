<?php
/**
 * Model Viewer - Upload GLB for preview
 * POST /api/labs/model-viewer/upload.php
 * Auth: logged-in user
 * Body: multipart/form-data with field "glb_file" (.glb)
 * Returns: { ok: true, data: { url: "/storage/labs/model-viewer/uploads/{userId}/{file}" } }
 *
 * This is intentionally simple: it stores the GLB under storage/labs/model-viewer/uploads
 * so the viewer can load it via a normal HTTP URL (CSP-friendly).
 */
require_once __DIR__ . '/../../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/storage.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'POST only.']]);
        exit;
    }

    require_login();
    $userId = (int) current_user_id();
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Login required.']]);
        exit;
    }

    $file = $_FILES['glb_file'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => ['code' => 'NO_FILE', 'message' => 'GLB file is required.']]);
        exit;
    }

    $origName = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext !== 'glb') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => ['code' => 'INVALID_EXT', 'message' => 'Only .glb files are allowed.']]);
        exit;
    }

    $size = (int) ($file['size'] ?? 0);
    $maxBytes = 25 * 1024 * 1024; // 25 MB hard limit
    if ($size <= 0 || $size > $maxBytes) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => ['code' => 'SIZE_LIMIT', 'message' => 'File too large. Max 25MB.']]);
        exit;
    }

    $relDir = 'labs/model-viewer/uploads/' . $userId;
    $absDir = storage_path($relDir);
    if (!is_dir($absDir) && !@mkdir($absDir, 0750, true) && !is_dir($absDir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => ['code' => 'STORAGE_FAILED', 'message' => 'Could not prepare storage directory.']]);
        exit;
    }

    $safeBase = preg_replace('/[^a-z0-9_\-]/i', '_', pathinfo($origName, PATHINFO_FILENAME) ?: 'model');
    $token = bin2hex(random_bytes(8));
    $fileName = $safeBase . '_' . $token . '.glb';
    $absPath = $absDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $absPath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => ['code' => 'MOVE_FAILED', 'message' => 'Could not save file.']]);
        exit;
    }

    @chmod($absPath, 0640);

    // Serve via PHP so storage/ stays blocked; viewer and download use this URL
    $publicUrl = '/api/labs/model-viewer/serve.php?f=' . rawurlencode($fileName);

    echo json_encode([
        'ok' => true,
        'data' => [
            'url' => $publicUrl,
            'filename' => $fileName,
            'original_name' => $origName,
        ],
    ]);
} catch (\Throwable $e) {
    error_log('api/labs/model-viewer/upload: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Upload failed.']]);
}

