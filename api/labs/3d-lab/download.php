<?php
/**
 * 3D Lab - Download GLB or preview
 * GET /api/labs/3d-lab/download.php?id={job_id}&format=glb|preview&inline=1
 */
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/storage.php';
require_once __DIR__ . '/../../../includes/labs_3d_helpers.php';

function labs_3d_fail(): void {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not available.';
    exit;
}

/**
 * Normalize path from DB for storage_path().
 * For format=glb: only labs/3d-lab/output/*.glb
 * For format=preview: only labs/3d-lab/preview/*.(webp|png|jpg|jpeg)
 * Rejects mismatched directories and wrong extensions; returns canonical fallback if path untrusted.
 */
function labs_3d_normalize_storage_path(string $path, string $publicId, string $format): string {
    $path = trim(str_replace('\\', '/', $path));

    if ($format === 'glb') {
        $allowedDir = LABS_3D_STORAGE_OUTPUT;
        $fallback = $allowedDir . '/' . $publicId . '.glb';
        if ($path === '') {
            return $fallback;
        }
        // Exact relative path: labs/3d-lab/output/<name>.glb
        if (preg_match('#^labs/3d-lab/output/[^\s\\\\/]+\.glb$#i', $path)) {
            return $path;
        }
        // Absolute or prefixed: extract output/<name>.glb
        if (preg_match('#labs/3d-lab/output/([^\s\\\\/]+\.glb)$#i', $path, $m)) {
            return $allowedDir . '/' . $m[1];
        }
        return $fallback;
    }

    // format === 'preview'
    $allowedDir = LABS_3D_STORAGE_PREVIEW;
    $fallback = $allowedDir . '/' . $publicId . '.webp';
    if ($path === '') {
        return $fallback;
    }
    // Exact relative path: labs/3d-lab/preview/<name>.(webp|png|jpg|jpeg)
    if (preg_match('#^labs/3d-lab/preview/[^\s\\\\/]+\.(webp|png|jpe?g)$#i', $path)) {
        return $path;
    }
    // Absolute or prefixed: extract preview/<name>.(webp|png|jpg|jpeg)
    if (preg_match('#labs/3d-lab/preview/([^\s\\\\/]+\.(webp|png|jpe?g))$#i', $path, $m)) {
        return $allowedDir . '/' . $m[1];
    }
    return $fallback;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        labs_3d_fail();
    }

    require_login();
    $userId = (int) current_user_id();

    $jobId = trim((string) ($_GET['id'] ?? $_GET['job_id'] ?? ''));
    $format = strtolower(trim((string) ($_GET['format'] ?? 'glb')));
    if (!in_array($format, ['glb', 'preview'], true)) {
        labs_3d_fail();
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        labs_3d_fail();
    }

    $stmt = $pdo->prepare(
        "SELECT user_id, public_id, status, glb_path, preview_path
         FROM knd_labs_3d_jobs
         WHERE public_id = ? OR id = ? LIMIT 1"
    );
    $stmt->execute([$jobId, is_numeric($jobId) ? (int) $jobId : 0]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job || (int) $job['user_id'] !== $userId) {
        labs_3d_fail();
    }

    if ($format === 'glb' && $job['status'] !== 'completed') {
        labs_3d_fail();
    }

    $rawPath = $format === 'glb'
        ? ($job['glb_path'] ?? null)
        : ($job['preview_path'] ?? null);

    if ($format === 'preview' && empty($rawPath)) {
        labs_3d_fail();
    }

    $path = labs_3d_normalize_storage_path($rawPath ?? '', $job['public_id'], $format);
    $abs = storage_path($path);
    if (!is_file($abs) || !is_readable($abs)) {
        error_log(
            '3D download missing file: job=' . $jobId .
            ' format=' . $format .
            ' raw=' . ($rawPath ?? 'NULL') .
            ' normalized=' . $path .
            ' abs=' . $abs
        );
        labs_3d_fail();
    }

    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $mime = $format === 'glb' ? 'model/gltf-binary' : ($ext === 'png' ? 'image/png' : ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/webp'));
    $name = '3d-lab-' . ($job['public_id'] ?? $jobId) . ($format === 'glb' ? '.glb' : ('.' . ($ext ?: 'webp')));

    $inline = !empty($_GET['inline']);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($abs));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($name) . '"');

    readfile($abs);
    exit;
} catch (\Throwable $e) {
    error_log('api/labs/3d-lab/download: ' . $e->getMessage());
    labs_3d_fail();
}
