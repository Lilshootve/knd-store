<?php
/**
 * Download generated 3D model (InstantMesh).
 * Endpoint: GET /api/triposr/download.php (kept for backward compatibility)
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/triposr_config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/triposr.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Location: /labs-3d-lab.php');
        exit;
    }

    require_login();

    $jobId = trim($_GET['job_id'] ?? '');
    if ($jobId === '') {
        header('Location: /labs-3d-lab.php?error=missing');
        exit;
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        header('Location: /labs-3d-lab.php?error=db');
        exit;
    }

    $job = get_triposr_job($pdo, $jobId);
    if (!$job) {
        header('Location: /labs-3d-lab.php?error=not_found');
        exit;
    }

    if ((int) $job['user_id'] !== (int) current_user_id()) {
        header('Location: /labs-3d-lab.php?error=forbidden');
        exit;
    }

    if ($job['status'] !== 'completed' || empty($job['output_path'])) {
        header('Location: /labs-3d-lab.php?error=not_ready');
        exit;
    }

    $fullPath = storage_path($job['output_path']);
    if (!is_file($fullPath) || !is_readable($fullPath)) {
        header('Location: /labs-3d-lab.php?error=file_missing');
        exit;
    }

    $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
    $mime = ($ext === 'glb') ? 'model/gltf-binary' : 'text/plain';
    $name = 'model-' . $jobId . '.' . $ext;

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
} catch (\Throwable $e) {
    error_log('instantmesh/download: ' . $e->getMessage());
    header('Location: /labs-3d-lab.php?error=server');
    exit;
}
