<?php
/**
 * Character Lab - Validated download endpoint
 * GET /api/character-lab/download.php?id={job_id}&format=glb|concept|preview&inline=1
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/storage.php';

function character_lab_fail_download(): void {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not available.';
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        character_lab_fail_download();
    }

    require_once BASE_PATH . '/includes/auth.php';
    if (!function_exists('require_login')) {
        require_once BASE_PATH . '/includes/config.php';
    }
    require_login();
    $userId = (int) current_user_id();

    $jobId = trim((string) ($_GET['id'] ?? $_GET['job_id'] ?? ''));
    if ($jobId === '') {
        character_lab_fail_download();
    }

    $format = strtolower(trim((string) ($_GET['format'] ?? 'glb')));
    if (!in_array($format, ['glb', 'concept', 'preview'], true)) {
        character_lab_fail_download();
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        character_lab_fail_download();
    }

    $stmt = $pdo->prepare(
        "SELECT user_id, public_id, status, concept_image_path, mesh_glb_path, preview_thumb_path
         FROM knd_character_lab_jobs
         WHERE public_id = ? OR id = ? LIMIT 1"
    );
    $stmt->execute([$jobId, is_numeric($jobId) ? (int) $jobId : 0]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job || (int) $job['user_id'] !== $userId) {
        character_lab_fail_download();
    }

    $allowedStatuses = ['mesh_ready', 'partial_success'];
    if ($format === 'glb' && !in_array($job['status'], $allowedStatuses, true)) {
        character_lab_fail_download();
    }

    $path = null;
    $mime = 'application/octet-stream';
    $downloadName = 'character-' . ($job['public_id'] ?? $jobId);

    if ($format === 'glb') {
        $path = $job['mesh_glb_path'] ?? null;
        $mime = 'model/gltf-binary';
        $downloadName .= '.glb';
    } elseif ($format === 'concept') {
        $path = $job['concept_image_path'] ?? null;
        $downloadName .= '_concept.png';
    } else {
        $path = $job['preview_thumb_path'] ?? $job['concept_image_path'] ?? null;
        $downloadName .= '_preview.webp';
    }

    if (!$path) {
        character_lab_fail_download();
    }

    $abs = storage_path($path);
    if (!is_file($abs) || !is_readable($abs)) {
        character_lab_fail_download();
    }

    $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
    if ($format === 'concept' || $format === 'preview') {
        if ($ext === 'png') {
            $mime = 'image/png';
            $downloadName = pathinfo($downloadName, PATHINFO_FILENAME) . '.png';
        } elseif ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
            $downloadName = pathinfo($downloadName, PATHINFO_FILENAME) . '.jpg';
        } else {
            $mime = 'image/webp';
            $downloadName = pathinfo($downloadName, PATHINFO_FILENAME) . '.webp';
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
    error_log('api/character-lab/download: ' . $e->getMessage());
    character_lab_fail_download();
}
