<?php
/**
 * POST /api/labs/consistency_create.php
 * Create consistency job. Reference from recent job or upload.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
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

const CONSISTENCY_COST_KP = 5;
const LABS_TMP_DIR = 'uploads/labs/tmp';
const MAX_REF_BYTES = 5 * 1024 * 1024;
const MAX_REF_DIM = 2048;

function build_consistency_prompt(string $base, string $scene): string {
    $b = trim($base);
    $s = trim($scene);
    if ($b !== '' && $s !== '') return $b . ', ' . $s;
    return $b ?: ($s ?: 'high quality image');
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();

    $mode = trim($_POST['mode'] ?? 'style');
    if (!in_array($mode, ['style', 'character', 'both'], true)) $mode = 'style';

    $refSource = trim($_POST['reference_source'] ?? 'recent');
    if (!in_array($refSource, ['recent', 'upload'], true)) $refSource = 'recent';

    if ($refSource === 'recent') {
        $refJobId = (int) ($_POST['reference_job_id'] ?? 0);
        if ($refJobId <= 0) json_error('INVALID_INPUT', 'reference_job_id required when selecting from recent.', 400);
    } else {
        if (empty($_FILES['reference_image']['tmp_name']) || $_FILES['reference_image']['error'] !== UPLOAD_ERR_OK) {
            json_error('INVALID_INPUT', 'Reference image file required when uploading.', 400);
        }
    }

    $basePrompt = trim($_POST['base_prompt'] ?? '');
    $scenePrompt = trim($_POST['scene_prompt'] ?? '');
    $finalPrompt = build_consistency_prompt($basePrompt, $scenePrompt);
    if (strlen($finalPrompt) < 3) json_error('INVALID_INPUT', 'Base prompt or scene prompt required.', 400);
    if (strlen($finalPrompt) > 1000) $finalPrompt = substr($finalPrompt, 0, 1000);

    $negativePrompt = trim($_POST['negative_prompt'] ?? 'ugly, blurry, low quality');
    if (strlen($negativePrompt) > 500) $negativePrompt = substr($negativePrompt, 0, 500);

    $lockSeed = !empty($_POST['lock_seed']);
    $inheritModel = !empty($_POST['inherit_model']);
    $inheritResolution = !empty($_POST['inherit_resolution']);
    $inheritSampling = !empty($_POST['inherit_sampling']);

    $width = (int) ($_POST['width'] ?? 1024);
    $height = (int) ($_POST['height'] ?? 1024);
    $width = max(256, min(2048, $width - ($width % 8)));
    $height = max(256, min(2048, $height - ($height % 8)));

    $steps = (int) ($_POST['steps'] ?? 28);
    $steps = max(1, min(100, $steps));
    $cfg = (float) ($_POST['cfg'] ?? 7);
    $cfg = max(1, min(30, $cfg));
    $sampler = trim($_POST['sampler'] ?? $_POST['sampler_name'] ?? 'dpmpp_2m');
    $allowedSamplers = ['euler', 'euler_ancestral', 'dpmpp_2m', 'dpmpp_2m_sde', 'dpmpp_sde', 'ddim', 'lcm'];
    if (!in_array($sampler, $allowedSamplers, true)) $sampler = 'dpmpp_2m';

    $seed = !empty($_POST['seed']) ? (int) $_POST['seed'] : random_int(0, 2147483647);
    $model = trim($_POST['model'] ?? COMFYUI_DEFAULT_MODEL);
    $modelCkpt = isset(COMFYUI_CHECKPOINT_MAP[$model]) ? COMFYUI_CHECKPOINT_MAP[$model] : COMFYUI_CHECKPOINT_MAP[COMFYUI_DEFAULT_MODEL];

    $refJobId = (int) ($_POST['reference_job_id'] ?? 0);
    if ($refSource === 'recent' && $refJobId > 0) {
        $stmt = $pdo->prepare("SELECT id, output_path, payload_json FROM knd_labs_jobs WHERE id = ? AND user_id = ? AND status = 'done' LIMIT 1");
        $stmt->execute([$refJobId, $userId]);
        $refJob = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$refJob) json_error('INVALID_INPUT', 'Reference job not found or not completed.', 400);

        if ($inheritModel) {
            $refPayload = json_decode($refJob['payload_json'] ?? '{}', true);
            if (!empty($refPayload['model']) && isset(COMFYUI_CHECKPOINT_MAP[$refPayload['model']])) {
                $modelCkpt = COMFYUI_CHECKPOINT_MAP[$refPayload['model']];
            }
        }
        if ($inheritResolution) {
            $refPayload = json_decode($refJob['payload_json'] ?? '{}', true);
            if (!empty($refPayload['width'])) $width = max(256, min(2048, (int) $refPayload['width'] - ((int) $refPayload['width'] % 8)));
            if (!empty($refPayload['height'])) $height = max(256, min(2048, (int) $refPayload['height'] - ((int) $refPayload['height'] % 8)));
        }
        if ($inheritSampling) {
            $refPayload = json_decode($refJob['payload_json'] ?? '{}', true);
            if (isset($refPayload['steps'])) $steps = max(1, min(100, (int) $refPayload['steps']));
            if (isset($refPayload['cfg'])) $cfg = max(1, min(30, (float) $refPayload['cfg']));
            if (!empty($refPayload['sampler_name'])) $sampler = $refPayload['sampler_name'];
        }
        if ($lockSeed) {
            $refPayload = json_decode($refJob['payload_json'] ?? '{}', true);
            if (!empty($refPayload['seed'])) $seed = (int) $refPayload['seed'];
        }
    }

    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);
    $available = get_available_points($pdo, $userId);
    if ($available < CONSISTENCY_COST_KP) {
        json_error('INSUFFICIENT_POINTS', 'Insufficient KP. Need ' . CONSISTENCY_COST_KP . '.', 400);
    }

    $active = labs_count_active_jobs($pdo, $userId);
    if ($active >= 2) {
        json_error('RATE_LIMIT', 'Too many active jobs. Wait for current ones to finish.', 429);
    }

    $providerUsed = 'local';
    $runpodUrl = comfyui_get_base_url_runpod($pdo);
    $baseUrl = comfyui_get_base_url($pdo, null);
    if ($runpodUrl !== '' && rtrim($baseUrl, '/') === rtrim($runpodUrl, '/')) $providerUsed = 'runpod';

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "INSERT INTO knd_labs_jobs (user_id, tool, prompt, negative_prompt, status, cost_kp, quality, provider, priority, payload_json)
         VALUES (?, 'consistency', ?, ?, 'queued', ?, 'base', ?, 100, ?)"
    );
    $initialPayload = [
        'tool' => 'consistency',
        'mode' => $mode,
        'reference_source' => $refSource,
        'reference_job_id' => $refJobId > 0 ? $refJobId : null,
        'base_prompt' => $basePrompt,
        'scene_prompt' => $scenePrompt,
        'negative_prompt' => $negativePrompt,
        'lock_seed' => $lockSeed,
        'inherit_model' => $inheritModel,
        'inherit_resolution' => $inheritResolution,
        'inherit_sampling' => $inheritSampling,
        'seed' => $seed,
        'steps' => $steps,
        'cfg' => $cfg,
        'sampler_name' => $sampler,
        'width' => $width,
        'height' => $height,
        'model_ckpt' => $modelCkpt,
        'denoise' => 0.75,
    ];
    if (!$stmt || !$stmt->execute([$userId, $finalPrompt, $negativePrompt, CONSISTENCY_COST_KP, $providerUsed, json_encode($initialPayload)])) {
        $pdo->rollBack();
        json_error('DB_ERROR', 'Could not create job.', 500);
    }
    $jobId = (int) $pdo->lastInsertId();
    $initialPayload['job_id'] = $jobId;

    $tmpDir = storage_path(LABS_TMP_DIR);
    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);
    $siteBase = rtrim(defined('SITE_URL') ? SITE_URL : 'https://kndstore.com', '/');

    if ($refSource === 'upload') {
        $valid = labs_validate_image($_FILES['reference_image']['tmp_name'], MAX_REF_BYTES, MAX_REF_DIM);
        if (!$valid['ok']) {
            $pdo->rollBack();
            json_error('INVALID_IMAGE', $valid['error'] ?? 'Invalid reference image.', 400);
        }
        $destPath = storage_path(LABS_TMP_DIR . '/job_' . $jobId . '_ref.png');
        if (!labs_image_to_png($_FILES['reference_image']['tmp_name'], $destPath)) {
            $pdo->rollBack();
            json_error('STORAGE_ERROR', 'Could not save reference image.', 500);
        }
        $initialPayload['ref_image_url'] = $siteBase . '/api/labs/tmp_image.php?job_id=' . $jobId . '&slot=ref';
    } else {
        $stmt = $pdo->prepare("SELECT output_path, comfy_prompt_id, provider FROM knd_labs_jobs WHERE id = ? AND user_id = ? AND status = 'done' LIMIT 1");
        $stmt->execute([$refJobId, $userId]);
        $refJob = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$refJob) {
            $pdo->rollBack();
            json_error('INVALID_INPUT', 'Reference job has no output.', 400);
        }
        $destPath = storage_path(LABS_TMP_DIR . '/job_' . $jobId . '_ref.png');
        $saved = false;
        if (!empty($refJob['output_path'])) {
            $full = storage_path($refJob['output_path']);
            if (is_file($full) && is_readable($full) && labs_image_to_png($full, $destPath)) {
                $saved = true;
            }
        }
        if (!$saved && !empty($refJob['comfy_prompt_id'])) {
            $imgBytes = comfyui_fetch_job_image_bytes($pdo, $refJobId, $userId);
            if ($imgBytes && @file_put_contents($destPath, $imgBytes) !== false) {
                $saved = true;
            }
        }
        if (!$saved) {
            $pdo->rollBack();
            json_error('INVALID_INPUT', 'Could not resolve reference image from job.', 400);
        }
        $initialPayload['ref_image_url'] = $siteBase . '/api/labs/tmp_image.php?job_id=' . $jobId . '&slot=ref';
    }

    $stmt = $pdo->prepare("UPDATE knd_labs_jobs SET payload_json = ? WHERE id = ?");
    $stmt->execute([json_encode($initialPayload), $jobId]);
    $pdo->commit();

    ai_spend_points($pdo, $userId, $jobId, CONSISTENCY_COST_KP);

    json_success(['job_id' => (string) $jobId, 'available_after' => get_available_points($pdo, $userId)]);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('api/labs/consistency_create: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', $e->getMessage(), 500);
}
