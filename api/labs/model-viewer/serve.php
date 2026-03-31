<?php
/**
 * Model Viewer - Serve uploaded GLB (stream file to viewer).
 * GET /api/labs/model-viewer/serve.php?f=filename.glb
 * Auth: logged-in user. Serves only files from storage/labs/model-viewer/uploads/{userId}/
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/storage.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Content-Type: text/plain');
        echo 'Method not allowed';
        exit;
    }

    require_login();
    $userId = (int) current_user_id();
    if ($userId <= 0) {
        http_response_code(401);
        header('Content-Type: text/plain');
        echo 'Unauthorized';
        exit;
    }

    $raw = isset($_GET['f']) ? trim((string) $_GET['f']) : '';
    // Only allow safe filename: alphanumeric, dash, underscore, single .glb extension
    if ($raw === '' || !preg_match('/^[a-zA-Z0-9_\-]+\.glb$/i', $raw)) {
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Invalid file';
        exit;
    }

    $relDir = 'labs/model-viewer/uploads/' . $userId;
    $absPath = storage_path($relDir . '/' . $raw);

    if (!is_file($absPath) || !is_readable($absPath)) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Not found';
        exit;
    }

    header('Content-Type: model/gltf-binary');
    header('Content-Length: ' . (string) filesize($absPath));
    header('X-Content-Type-Options: nosniff');

    $fp = fopen($absPath, 'rb');
    if ($fp) {
        while (!feof($fp)) {
            echo fread($fp, 65536);
        }
        fclose($fp);
    }
    exit;
} catch (\Throwable $e) {
    error_log('api/labs/model-viewer/serve: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Error';
    exit;
}
