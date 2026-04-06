<?php
/**
 * AI tools job submission (text2img, upscale, character_create, character_variation, texture_seamless).
 * POST /api/ai/submit.php
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/triposr_config.php';
require_once BASE_PATH . '/includes/ai_config.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/triposr.php';
require_once BASE_PATH . '/includes/ai.php';
require_once BASE_PATH . '/includes/support_credits.php';

define('AI_MAX_ACTIVE_JOBS', 1);
define('AI_MAX_PER_HOUR', 10);
define('AI_MAX_PER_DAY', 30);
define('AI_MAX_PROMPT_LEN', 500);
define('AI_MAX_IMAGE_SIZE', 10 * 1024 * 1024);
define('AI_MAX_IMAGE_DIM', 4096);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = current_user_id();
    if (!$userId) {
        json_error('AUTH_REQUIRED', 'You must be logged in.', 401);
    }

    $type = trim($_POST['type'] ?? '');
    $allowedTypes = ['text2img', 'upscale', 'character_create', 'character_variation', 'texture_seamless'];
    if (!in_array($type, $allowedTypes, true)) {
        json_error('INVALID_TYPE', 'type must be one of: ' . implode(', ', $allowedTypes));
    }

    $cost = ai_job_cost($type, ($type === 'text2img') ? ($_POST['mode'] ?? 'standard') : null);
    if ($cost <= 0) {
        json_error('INVALID_TYPE', 'Unknown cost for type.');
    }

    // Limits
    if (ai_count_active_jobs($pdo, $userId) >= AI_MAX_ACTIVE_JOBS) {
        json_error('ACTIVE_LIMIT', t('ai.error.active_limit', 'Ya tienes un trabajo en curso. Espera a que termine.'), 429);
    }
    if (ai_count_jobs_last_hour($pdo, $userId) >= AI_MAX_PER_HOUR) {
        json_error('RATE_LIMIT', t('ai.error.rate_limit', 'Límite por hora alcanzado. Máximo 10 por hora.'), 429);
    }
    if (ai_count_jobs_today($pdo, $userId) >= AI_MAX_PER_DAY) {
        json_error('DAILY_LIMIT', t('ai.error.daily_limit', 'Límite diario alcanzado. Máximo 30 por día.'), 429);
    }

    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);
    $available = get_available_points($pdo, $userId);
    if ($available < $cost) {
        json_error('INSUFFICIENT_POINTS', t('ai.error.insufficient_points', 'Puntos insuficientes. Tienes {available} KP, necesitas {cost} KP.', ['available' => $available, 'cost' => $cost]), 400);
    }

    $payload = [];
    $inputPath = '';
    $jobUuidForUpscale = null;

    if ($type === 'text2img') {
        $prompt = trim($_POST['prompt'] ?? '');
        if (strlen($prompt) < 1) {
            json_error('INVALID_INPUT', 'prompt is required.');
        }
        if (strlen($prompt) > AI_MAX_PROMPT_LEN) {
            json_error('INVALID_INPUT', 'Prompt max ' . AI_MAX_PROMPT_LEN . ' characters.');
        }
        $mode = ($_POST['mode'] ?? '') === 'high' ? 'high' : 'standard';
        $payload = [
            'prompt' => $prompt,
            'mode' => $mode,
            'width' => (int) ($_POST['width'] ?? 1024),
            'height' => (int) ($_POST['height'] ?? 1024),
            'seed' => !empty($_POST['seed']) ? (int) $_POST['seed'] : null,
        ];
    } elseif ($type === 'upscale') {
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            json_error('INVALID_IMAGE', 'Image upload required.');
        }
        $file = $_FILES['image'];
        if ($file['size'] > AI_MAX_IMAGE_SIZE) {
            json_error('FILE_TOO_LARGE', 'Image max 10MB.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            json_error('INVALID_IMAGE', 'Only JPG, PNG, WebP allowed.');
        }
        $imgInfo = @getimagesize($file['tmp_name']);
        if (!$imgInfo || ($imgInfo[0] ?? 0) < 1 || ($imgInfo[1] ?? 0) < 1) {
            json_error('INVALID_IMAGE', 'Invalid image dimensions.');
        }
        if (($imgInfo[0] ?? 0) > AI_MAX_IMAGE_DIM || ($imgInfo[1] ?? 0) > AI_MAX_IMAGE_DIM) {
            json_error('IMAGE_TOO_LARGE', 'Max dimensions 4096x4096.');
        }

        $jobUuid = ai_generate_uuid();
        $uploadDir = storage_path(AI_UPLOAD_DIR);
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0750, true);
        $ext = strpos($mime, 'png') !== false ? 'png' : (strpos($mime, 'webp') !== false ? 'webp' : 'jpg');
        $fileName = $jobUuid . '.' . $ext;
        $destPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            json_error('STORAGE_ERROR', 'Could not save image.');
        }
        $inputPath = AI_UPLOAD_DIR . '/' . $fileName;
        $scale = in_array((int) ($_POST['scale'] ?? 2), [2, 4], true) ? (int) $_POST['scale'] : 2;
        $siteUrl = rtrim(defined('SITE_URL') ? SITE_URL : ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
        $payload = [
            'image_url' => $siteUrl . '/api/ai/image.php?t=' . urlencode($jobUuid),
            'scale' => $scale,
        ];
        $jobUuidForUpscale = $jobUuid;
    } elseif ($type === 'character_create') {
        $prompt = trim($_POST['prompt'] ?? '');
        if (strlen($prompt) < 1) {
            json_error('INVALID_INPUT', 'prompt is required.');
        }
        if (strlen($prompt) > AI_MAX_PROMPT_LEN) {
            json_error('INVALID_INPUT', 'Prompt max ' . AI_MAX_PROMPT_LEN . ' characters.');
        }
        $style = in_array($_POST['style'] ?? '', ['game', 'anime', 'realistic'], true) ? $_POST['style'] : 'game';
        $payload = [
            'prompt' => $prompt,
            'style' => $style,
            'seed' => !empty($_POST['seed']) ? (int) $_POST['seed'] : null,
        ];
    } elseif ($type === 'character_variation') {
        $charId = trim($_POST['character_id'] ?? '');
        $varPrompt = trim($_POST['variation_prompt'] ?? '');
        if ($charId === '' || strlen($varPrompt) > AI_MAX_PROMPT_LEN) {
            json_error('INVALID_INPUT', 'character_id and variation_prompt required.');
        }
        $varType = in_array($_POST['variation_type'] ?? '', ['pose', 'outfit', 'expression'], true) ? $_POST['variation_type'] : 'pose';
        $payload = [
            'character_id' => $charId,
            'variation_prompt' => $varPrompt,
            'type' => $varType,
        ];
    } elseif ($type === 'texture_seamless') {
        $prompt = trim($_POST['prompt'] ?? '');
        if (strlen($prompt) < 1) {
            json_error('INVALID_INPUT', 'prompt is required.');
        }
        if (strlen($prompt) > AI_MAX_PROMPT_LEN) {
            json_error('INVALID_INPUT', 'Prompt max ' . AI_MAX_PROMPT_LEN . ' characters.');
        }
        $payload = [
            'prompt' => $prompt,
            'seed' => !empty($_POST['seed']) ? (int) $_POST['seed'] : null,
        ];
    }

    $job = ai_create_job($pdo, $userId, $type, $payload, $cost, $inputPath, null, $jobUuidForUpscale);
    if (!$job) {
        if ($type === 'upscale' && $inputPath) {
            @unlink(storage_path($inputPath));
        }
        json_error('JOB_CREATE_FAILED', 'Could not create job. Run sql/triposr_jobs_alter_ai.sql if needed.', 500);
    }

    ai_spend_points($pdo, $userId, (int) $job['id'], $cost);

    $siteUrl = rtrim(defined('SITE_URL') ? SITE_URL : ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
    $callbackUrl = $siteUrl . '/api/ai/callback.php';

    $gpuPayload = [
        'job_id' => $job['job_uuid'],
        'type' => $type,
        'payload' => $payload,
        'callback_url' => $callbackUrl,
        'secret' => AI_CALLBACK_SECRET,
        'timestamp' => time(),
    ];

    $now = gmdate('Y-m-d H:i:s');
    $gpuUrl = AI_GPU_API_URL;
    if (!empty($gpuUrl)) {
        if (strpos($gpuUrl, '/generate') === false) {
            $gpuUrl = rtrim($gpuUrl, '/') . '/generate';
        }
        $ch = curl_init($gpuUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($gpuPayload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            ai_update_job($pdo, $job['job_uuid'], [
                'status' => 'failed',
                'error_message' => 'GPU server unreachable: ' . $err,
                'completed_at' => $now,
            ]);
            ai_refund_points($pdo, $userId, (int) $job['id'], $cost);
        } else {
            ai_update_job($pdo, $job['job_uuid'], ['status' => 'processing']);
        }
    }

    if (isset($_SESSION['sc_badge_cache'])) unset($_SESSION['sc_badge_cache']);

    json_success([
        'job_id' => $job['job_uuid'],
        'job_type' => $type,
        'status' => 'pending',
        'cost' => $cost,
        'available_after' => get_available_points($pdo, $userId),
    ]);
} catch (\Throwable $e) {
    error_log('ai/submit: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
