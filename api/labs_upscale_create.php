<?php
/**
 * POST /api/labs_upscale_create.php
 * Create upscale job. source_type: upload | recent
 * Stores image on Hostinger. Worker downloads and uploads to ComfyUI.
 * No Hostinger->ComfyUI HTTP calls.
 */
require_once __DIR__ . '/../config/bootstrap.php';
header('Cache-Control: no-store, no-cache');
header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/support_credits.php';
require_once BASE_PATH . '/includes/ai.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/comfyui.php';
require_once BASE_PATH . '/includes/comfyui_provider.php';
require_once BASE_PATH . '/includes/settings.php';
require_once BASE_PATH . '/includes/labs_image_helper.php';

const MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5MB
const MAX_DIMENSION = 2048;
const UPSCALE_COST_KP = 5;
const LABS_TMP_DIR = 'uploads/labs/tmp';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();

    $sourceType = trim($_POST['source_type'] ?? '');
    if (!in_array($sourceType, ['upload', 'recent'], true)) {
        json_error('INVALID_INPUT', 'source_type must be upload or recent.', 400);
    }

    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);
    $available = get_available_points($pdo, $userId);
    if ($available < UPSCALE_COST_KP) {
        json_error('INSUFFICIENT_POINTS', 'Insufficient KP. Need ' . UPSCALE_COST_KP . '.', 400);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM knd_labs_jobs WHERE user_id = ? AND status IN ('queued','processing')");
    if ($stmt && $stmt->execute([$userId])) {
        if ((int) $stmt->fetchColumn() >= 2) {
            json_error('RATE_LIMIT', 'Too many active jobs.', 429);
        }
    }

    $tmpPath = null;
    if ($sourceType === 'upload') {
        if (empty($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            json_error('INVALID_IMAGE', 'image_file required for upload.', 400);
        }
        $tmpPath = $_FILES['image_file']['tmp_name'];
        $valid = labs_validate_image($tmpPath, MAX_UPLOAD_BYTES, MAX_DIMENSION);
        if (!$valid['ok']) {
            json_error('INVALID_IMAGE', $valid['error'] ?? 'Invalid image.', 400);
        }
    } else {
        json_error('UNSUPPORTED', 'source_type=recent not supported (no Hostinger->ComfyUI). Use upload.', 400);
    }

    $providerUsed = 'local';
    $runpodUrl = comfyui_get_base_url_runpod($pdo);
    $baseUrl = comfyui_get_base_url($pdo, null);
    if ($runpodUrl !== '' && rtrim($baseUrl, '/') === rtrim($runpodUrl, '/')) {
        $providerUsed = 'runpod';
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "INSERT INTO knd_labs_jobs (user_id, tool, prompt, negative_prompt, status, cost_kp, quality, provider, priority, payload_json)
         VALUES (?, 'upscale', '', '', 'queued', ?, '4x', ?, 100, ?)"
    );
    if (!$stmt || !$stmt->execute([$userId, UPSCALE_COST_KP, $providerUsed, '{}'])) {
        $pdo->rollBack();
        json_error('DB_ERROR', 'Could not create job.', 500);
    }
    $jobId = (int) $pdo->lastInsertId();

    $tmpDir = storage_path(LABS_TMP_DIR);
    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);
    $filename = 'job_' . $jobId . '_input.png';
    $storedPath = LABS_TMP_DIR . '/' . $filename;
    $fullPath = storage_path($storedPath);

    if (!labs_image_to_png($tmpPath, $fullPath)) {
        $pdo->rollBack();
        if ($tmpPath && $sourceType === 'recent') @unlink($tmpPath);
        json_error('INTERNAL_ERROR', 'Could not store image.', 500);
    }
    if ($tmpPath && $sourceType === 'recent') @unlink($tmpPath);

    $imageUrl = rtrim(defined('SITE_URL') ? SITE_URL : 'https://kndstore.com', '/') . '/api/labs/tmp_image.php?job_id=' . $jobId;
    $payload = [
        'tool' => 'upscale',
        'job_id' => $jobId,
        'image_url' => $imageUrl,
    ];
    $stmt = $pdo->prepare("UPDATE knd_labs_jobs SET payload_json = ? WHERE id = ?");
    $stmt->execute([json_encode($payload), $jobId]);
    $pdo->commit();

    ai_spend_points($pdo, $userId, $jobId, UPSCALE_COST_KP);

    json_success(['job_id' => (string) $jobId]);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('api/labs/upscale_create: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', $e->getMessage(), 500);
}
