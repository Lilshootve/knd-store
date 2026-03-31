<?php
/**
 * Download AI job result (image or model). GET /api/ai/download.php?job_id=...
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/ai_config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/triposr.php';
require_once __DIR__ . '/../../includes/ai.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Location: /ai-tools.php');
        exit;
    }

    require_login();

    $jobId = trim($_GET['job_id'] ?? '');
    if ($jobId === '') {
        header('Location: /ai-tools.php?error=missing');
        exit;
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        header('Location: /ai-tools.php?error=db');
        exit;
    }

    $job = null;
    try {
        $job = ai_get_job($pdo, $jobId);
    } catch (\Throwable $e) {
    }
    if (!$job) {
        $job = get_triposr_job($pdo, $jobId);
    }
    if (!$job) {
        header('Location: /ai-tools.php?error=not_found');
        exit;
    }

    if ((int) $job['user_id'] !== (int) current_user_id()) {
        header('Location: /ai-tools.php?error=forbidden');
        exit;
    }

    if ($job['status'] !== 'completed' || empty($job['output_path'])) {
        header('Location: /ai-tools.php?error=not_ready');
        exit;
    }

    $fullPath = storage_path($job['output_path']);
    if (strpos(realpath($fullPath), realpath(storage_path())) !== 0) {
        header('Location: /ai-tools.php?error=forbidden');
        exit;
    }
    if (!is_file($fullPath) || !is_readable($fullPath)) {
        header('Location: /ai-tools.php?error=file_missing');
        exit;
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimes = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'glb' => 'model/gltf-binary',
        'obj' => 'text/plain',
    ];
    $mime = $mimes[$ext] ?? 'application/octet-stream';
    $name = 'ai-' . $jobId . '.' . $ext;

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
} catch (\Throwable $e) {
    error_log('ai/download: ' . $e->getMessage());
    header('Location: /ai-tools.php?error=server');
    exit;
}
