<?php
/**
 * KND Labs InstantMesh - validated download endpoint
 * GET /api/labs/instantmesh/download.php?job_id={public_id}&format=glb|obj|preview&inline=1
 */
require_once __DIR__ . '/../../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/storage.php';

function instantmesh_fail_download(): void {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not available.';
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        instantmesh_fail_download();
    }

    require_login();
    $userId = (int) current_user_id();

    $publicId = trim((string) ($_GET['job_id'] ?? ''));
    if ($publicId === '') {
        instantmesh_fail_download();
    }

    $format = strtolower(trim((string) ($_GET['format'] ?? 'glb')));
    if (!in_array($format, ['glb', 'obj', 'preview'], true)) {
        instantmesh_fail_download();
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        instantmesh_fail_download();
    }

    $stmt = $pdo->prepare(
        "SELECT user_id, public_id, status, preview_image_path, output_glb_path, output_obj_path
         FROM knd_labs_instantmesh_jobs
         WHERE public_id = ? LIMIT 1"
    );
    $stmt->execute([$publicId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job || (int) $job['user_id'] !== $userId) {
        instantmesh_fail_download();
    }

    $path = null;
    $mime = 'application/octet-stream';
    $downloadName = 'instantmesh-' . $publicId;

    if ($format === 'glb') {
        $path = $job['output_glb_path'] ?? null;
        $mime = 'model/gltf-binary';
        $downloadName .= '.glb';
    } elseif ($format === 'obj') {
        $path = $job['output_obj_path'] ?? null;
        $mime = 'text/plain; charset=utf-8';
        $downloadName .= '.obj';
    } else {
        $path = $job['preview_image_path'] ?? null;
        $downloadName .= '.webp';
    }

    if (!$path) {
        instantmesh_fail_download();
    }

    $abs = storage_path($path);
    if (!is_file($abs) || !is_readable($abs)) {
        instantmesh_fail_download();
    }

    if ($format === 'preview') {
        $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $mime = 'image/png';
            $downloadName = 'instantmesh-' . $publicId . '.png';
        } elseif ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
            $downloadName = 'instantmesh-' . $publicId . '.jpg';
        } else {
            $mime = 'image/webp';
            $downloadName = 'instantmesh-' . $publicId . '.webp';
        }
    }

    $inline = !empty($_GET['inline']);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($abs));
    if ($inline) {
        header('Content-Disposition: inline; filename="' . basename($downloadName) . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
    }

    readfile($abs);
    exit;
} catch (\Throwable $e) {
    error_log('api/labs/instantmesh/download: ' . $e->getMessage());
    instantmesh_fail_download();
}
