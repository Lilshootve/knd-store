<?php
/**
 * Character Lab - Recent compatible images for user
 * GET /api/character-lab/recent-images.php?limit=12
 */
header('Cache-Control: no-store, no-cache');
header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/json.php';

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

    $items = [];

    $stmt = $pdo->prepare(
        "SELECT id, tool, prompt, created_at
         FROM knd_labs_jobs
         WHERE user_id = ? AND status = 'done' AND output_path IS NOT NULL AND output_path != ''
         ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->execute([$userId, $limit]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $items[] = [
            'id' => (int) $row['id'],
            'source' => 'labs_job',
            'label' => ($row['tool'] ?? 'image') . ' · ' . date('M j', strtotime($row['created_at'])),
            'image_url' => '/api/labs/image.php?job_id=' . (int) $row['id'],
        ];
    }

    $stmt2 = $pdo->prepare(
        "SELECT id, public_id, created_at
         FROM knd_labs_instantmesh_jobs
         WHERE user_id = ? AND status = 'completed' AND (source_image_path IS NOT NULL OR preview_image_path IS NOT NULL)
         ORDER BY created_at DESC LIMIT ?"
    );
    $stmt2->execute([$userId, $limit]);
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $pubId = $row['public_id'] ?? '';
        $items[] = [
            'id' => (int) $row['id'],
            'source' => 'instantmesh',
            'label' => 'InstantMesh · ' . date('M j', strtotime($row['created_at'])),
            'image_url' => $pubId ? '/api/labs/instantmesh/download.php?job_id=' . urlencode($pubId) . '&format=preview&inline=1' : null,
        ];
    }

    $stmt3 = $pdo->prepare(
        "SELECT id, public_id, created_at
         FROM knd_character_lab_jobs
         WHERE user_id = ? AND concept_image_path IS NOT NULL AND concept_image_path != ''
         ORDER BY created_at DESC LIMIT ?"
    );
    $stmt3->execute([$userId, $limit]);
    foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $jobId = (int) $row['id'];
        $items[] = [
            'id' => $jobId,
            'source' => 'character_lab',
            'label' => 'Character Lab · ' . date('M j', strtotime($row['created_at'])),
            'image_url' => '/api/character-lab/download.php?id=' . $jobId . '&format=concept&inline=1',
        ];
    }

    usort($items, function ($a, $b) {
        return 0;
    });

    $items = array_slice($items, 0, $limit);

    json_success(['images' => $items]);
} catch (\Throwable $e) {
    error_log('api/character-lab/recent-images: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'Could not fetch recent images.', 500);
}
