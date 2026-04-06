<?php
/**
 * 3D Lab - Recent creations (user's own only)
 * GET /api/labs/3d-lab/history.php?limit=12
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

    $limit = min(24, max(1, (int) ($_GET['limit'] ?? 12)));

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $stmt = $pdo->prepare(
        "SELECT id, public_id, mode, prompt, category, style, status, glb_path, preview_path, created_at
         FROM knd_labs_3d_jobs
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT {$limit}"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $jobs = [];
    foreach ($rows as $r) {
        $jobs[] = [
            'id' => (int) $r['id'],
            'public_id' => $r['public_id'],
            'mode' => $r['mode'],
            'title' => mb_substr(trim($r['prompt'] ?? ''), 0, 60) ?: ('3D Lab · ' . date('M j', strtotime($r['created_at']))),
            'category' => $r['category'],
            'style' => $r['style'],
            'status' => $r['status'],
            'created_at' => $r['created_at'],
            'preview_url' => !empty($r['preview_path'])
                ? '/api/labs/3d-lab/download.php?id=' . urlencode($r['public_id']) . '&format=preview&inline=1'
                : null,
            'glb_url' => !empty($r['glb_path'])
                ? '/api/labs/3d-lab/download.php?id=' . urlencode($r['public_id']) . '&format=glb'
                : null,
        ];
    }

    json_success(['jobs' => $jobs]);
} catch (\Throwable $e) {
    error_log('api/labs/3d-lab/history: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'Could not fetch history.', 500);
}
