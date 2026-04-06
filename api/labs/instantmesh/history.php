<?php
/**
 * KND Labs InstantMesh - recent history for logged user
 * GET /api/labs/instantmesh/history.php?limit=8
 */
require_once __DIR__ . '/../../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache');
header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
    }

    api_require_login();
    $userId = (int) current_user_id();

    $limit = (int) ($_GET['limit'] ?? 8);
    $limit = max(1, min(24, $limit));

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $stmt = $pdo->prepare(
        "SELECT id, public_id, status, preview_image_path, output_glb_path, output_obj_path,
                seed, remove_bg, output_format, error_message, created_at, completed_at
         FROM knd_labs_instantmesh_jobs
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT {$limit}"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $jobs = [];
    foreach ($rows as $row) {
        $jobs[] = [
            'id' => (int) $row['id'],
            'public_id' => (string) $row['public_id'],
            'status' => (string) $row['status'],
            'seed' => (int) $row['seed'],
            'remove_bg' => (int) $row['remove_bg'],
            'output_format' => (string) $row['output_format'],
            'error_message' => $row['error_message'],
            'created_at' => $row['created_at'],
            'completed_at' => $row['completed_at'],
            'has_glb' => !empty($row['output_glb_path']),
            'has_obj' => !empty($row['output_obj_path']),
            'preview_url' => !empty($row['preview_image_path'])
                ? '/api/labs/instantmesh/download.php?job_id=' . urlencode((string) $row['public_id']) . '&format=preview&inline=1'
                : null,
        ];
    }

    json_success(['jobs' => $jobs]);
} catch (\Throwable $e) {
    error_log('api/labs/instantmesh/history: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'Could not fetch history.', 500);
}
