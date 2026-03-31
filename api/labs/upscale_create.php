<?php
/**
 * POST /api/labs/upscale_create.php
 * Create upscale job. source_type: upload | recent
 */
header('Cache-Control: no-store, no-cache');
header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/support_credits.php';
require_once __DIR__ . '/../../includes/ai.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/comfyui.php';
require_once __DIR__ . '/../../includes/comfyui_provider.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/labs_image_helper.php';

const MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5MB
const MAX_DIMENSION = 2048;

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

    $scale = (int) ($_POST['scale'] ?? 4);
    if (!in_array($scale, [2, 4], true)) $scale = 4;

    $mode = trim($_POST['mode'] ?? 'standard');
    $outputFormat = trim($_POST['output_format'] ?? 'png');
    if (!in_array($outputFormat, ['png', 'jpg'], true)) $outputFormat = 'png';

    $costKp = $scale === 4 ? 8 : 5;

    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);
    $available = get_available_points($pdo, $userId);
    if ($available < $costKp) {
        json_error('INSUFFICIENT_POINTS', 'Insufficient KP. Need ' . $costKp . '.', 400);
    }

    $active = labs_count_active_jobs($pdo, $userId);
    if ($active >= 2) {
        json_error('RATE_LIMIT', 'Too many active jobs. Wait for current ones to finish.', 429);
    }

    $tmpPath = null;
    if ($sourceType === 'upload') {
        if (empty($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            json_error('INVALID_IMAGE', 'image_file required for upload.', 400);
        }
        $tmpPath = $_FILES['image_file']['tmp_name'];
        $size = filesize($tmpPath);
        if ($size > MAX_UPLOAD_BYTES) {
            json_error('INVALID_IMAGE', 'Image max 5MB.', 400);
        }
        $img = @getimagesize($tmpPath);
        if (!$img || !in_array($img[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            json_error('INVALID_IMAGE', 'Image must be PNG, JPG or WebP.', 400);
        }
        if (($img[0] ?? 0) > MAX_DIMENSION || ($img[1] ?? 0) > MAX_DIMENSION) {
            json_error('INVALID_IMAGE', 'Image max 2048px per side.', 400);
        }
    } else {
        $sourceJobId = (int) ($_POST['source_job_id'] ?? 0);
        if ($sourceJobId <= 0) json_error('INVALID_INPUT', 'source_job_id required for recent.', 400);
        $bytes = comfyui_fetch_job_image_bytes($pdo, $sourceJobId, $userId);
        if (!$bytes) json_error('INVALID_SOURCE', 'Could not fetch source image.', 400);
        $tmpPath = tempnam(sys_get_temp_dir(), 'knd_up');
        if (!$tmpPath || file_put_contents($tmpPath, $bytes) === false) {
            if ($tmpPath) @unlink($tmpPath);
            json_error('INTERNAL_ERROR', 'Temp file error.', 500);
        }
    }

    $baseUrl = comfyui_get_base_url($pdo, null);
    $token = comfyui_get_token($pdo);
    $runpodUrl = comfyui_get_base_url_runpod($pdo);
    $providerUsed = ($runpodUrl !== '' && rtrim($baseUrl, '/') === rtrim($runpodUrl, '/')) ? 'runpod' : 'local';

    try {
        $imageFilename = comfyui_upload_image($tmpPath, $baseUrl, $token);
    } finally {
        if ($tmpPath && $sourceType === 'recent') @unlink($tmpPath);
    }

    $payload = [
        'tool' => 'upscale',
        'image_filename' => $imageFilename,
        'scale' => $scale,
        'mode' => $mode,
        'output_format' => $outputFormat,
    ];

    $qualityCol = (string) $scale . 'x';
    $stmt = $pdo->prepare(
        "INSERT INTO knd_labs_jobs (user_id, tool, prompt, negative_prompt, status, cost_kp, quality, provider, priority, payload_json)
         VALUES (?, 'upscale', '', '', 'queued', ?, ?, ?, 100, ?)"
    );
    if (!$stmt || !$stmt->execute([$userId, $costKp, $qualityCol, $providerUsed, json_encode($payload)])) {
        json_error('DB_ERROR', 'Could not create job.', 500);
    }
    $jobId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("UPDATE knd_labs_jobs SET payload_json = ? WHERE id = ?");
    $payload['job_id'] = $jobId;
    $stmt->execute([json_encode($payload), $jobId]);

    ai_spend_points($pdo, $userId, $jobId, $costKp);

    json_success(['job_id' => (string) $jobId]);
} catch (\Throwable $e) {
    error_log('api/labs/upscale_create: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', $e->getMessage(), 500);
}
