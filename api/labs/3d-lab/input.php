<?php
/**
 * 3D Lab - Serve input image for worker download (remote web + local worker).
 * GET /api/labs/3d-lab/input.php?id={public_id}
 * No auth - public_id acts as secret. Worker fetches from remote hosting.
 */
header('Cache-Control: public, max-age=3600');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/storage.php';

function fail(): void {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        fail();
    }

    $publicId = trim((string) ($_GET['id'] ?? $_GET['public_id'] ?? ''));
    if ($publicId === '') {
        fail();
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        fail();
    }

    $stmt = $pdo->prepare(
        "SELECT input_image_path FROM knd_labs_3d_jobs WHERE public_id = ? LIMIT 1"
    );
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['input_image_path'])) {
        fail();
    }

    $path = $row['input_image_path'];
    $abs = storage_path($path);
    if (!is_file($abs) || !is_readable($abs)) {
        fail();
    }

    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/webp');
    $name = '3d-lab-input-' . $publicId . '.' . ($ext ?: 'png');

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($abs));
    header('Content-Disposition: inline; filename="' . basename($name) . '"');

    readfile($abs);
    exit;
} catch (\Throwable $e) {
    error_log('api/labs/3d-lab/input: ' . $e->getMessage());
    fail();
}
