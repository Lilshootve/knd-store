<?php
/**
 * 3D Lab - Create job
 * POST /api/labs/3d-lab/create.php
 * Dedicated pipeline, separate ComfyUI. Safe mode only.
 */
require_once __DIR__ . '/../../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache');
header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/support_credits.php';
require_once BASE_PATH . '/includes/labs_3d_helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    api_require_login();
    $userId = (int) current_user_id();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $cancelPrevious = !empty($_POST['cancel_previous']);
    if ($cancelPrevious) {
        $stmtCancel = $pdo->prepare(
            "UPDATE knd_labs_3d_jobs SET status = 'failed', error_message = 'Cancelled by user', completed_at = NOW() WHERE user_id = ? AND status IN ('queued','processing')"
        );
        if ($stmtCancel) {
            $stmtCancel->execute([$userId]);
        }
    } else {
        labs_3d_cleanup_stale_jobs($pdo, $userId);
    }

    $stmtActive = $pdo->prepare(
        "SELECT COUNT(*) FROM knd_labs_3d_jobs WHERE user_id = ? AND status IN ('queued','processing')"
    );
    $stmtActive->execute([$userId]);
    if ((int) $stmtActive->fetchColumn() > 0) {
        json_error('ACTIVE_JOB_EXISTS', 'You already have an active 3D Lab job.', 429);
    }

    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);
    $kpCost = labs_3d_kp_cost();
    $available = get_available_points($pdo, $userId);
    if ($available < $kpCost) {
        json_error('INSUFFICIENT_POINTS', 'Insufficient KP. Need ' . $kpCost . ' KP.', 400);
    }

    $mode = strtolower(trim((string) ($_POST['mode'] ?? 'text')));
    if (!in_array($mode, ['text', 'image', 'text_image', 'recent'], true)) {
        json_error('INVALID_MODE', 'mode must be text, image, text_image, or recent.', 400);
    }
    // Text-only mode not supported yet (no workflow). Image, text_image, recent use image→3D.
    if ($mode === 'text') {
        json_error('MODE_NOT_AVAILABLE', 'Text-only mode is coming soon. Use Image or Text+Image.', 400);
    }

    $category = labs_3d_validate_category(trim((string) ($_POST['category'] ?? 'Stylized Asset')));
    $style = labs_3d_validate_style(trim((string) ($_POST['style'] ?? 'Stylized')));
    $quality = labs_3d_validate_quality(trim((string) ($_POST['quality'] ?? 'Standard')));

    $prompt = trim((string) ($_POST['prompt'] ?? ''));
    $negativePrompt = trim((string) ($_POST['negative_prompt'] ?? ''));
    if (strlen($prompt) > 2000) $prompt = substr($prompt, 0, 2000);
    if (strlen($negativePrompt) > 1000) $negativePrompt = substr($negativePrompt, 0, 1000);

    $inputImagePath = null;
    $sourceRecentJobId = null;
    $sourceRecentType = null;

    $publicId = labs_3d_uuid();

    if ($mode === 'image' || $mode === 'text_image') {
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            json_error('INVALID_IMAGE', 'Image upload required.', 400);
        }
        $file = $_FILES['image'];
        if ((int) $file['size'] > LABS_3D_MAX_IMAGE_SIZE) {
            json_error('FILE_TOO_LARGE', 'Image must be <= 10MB.', 400);
        }
        $tmpPath = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            json_error('INVALID_IMAGE', 'Invalid upload.', 400);
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpPath) : '';
        if ($finfo) finfo_close($finfo);
        if (!isset(LABS_3D_ALLOWED_MIMES[$mime])) {
            json_error('INVALID_IMAGE_TYPE', 'Allowed: jpg, png, webp.', 400);
        }
        $ext = LABS_3D_ALLOWED_MIMES[$mime];
        $filename = $publicId . '_input.' . $ext;
        $inputDir = storage_path(LABS_3D_STORAGE_INPUT);
        if (!is_dir($inputDir) && !@mkdir($inputDir, 0755, true)) {
            json_error('STORAGE_ERROR', 'Could not prepare storage.', 500);
        }
        $inputRel = LABS_3D_STORAGE_INPUT . '/' . $filename;
        $inputAbs = storage_path($inputRel);
        if (!move_uploaded_file($tmpPath, $inputAbs)) {
            json_error('STORAGE_ERROR', 'Could not save image.', 500);
        }
        $inputImagePath = $inputRel;
        if ($mode === 'text_image' && $prompt === '') {
            json_error('INVALID_INPUT', 'Prompt required for text+image mode.', 400);
        }
    } elseif ($mode === 'recent') {
        $recentId = (int) ($_POST['source_recent_job_id'] ?? 0);
        $recentType = trim((string) ($_POST['source_recent_type'] ?? '3d_lab'));
        if ($recentId <= 0) {
            json_error('INVALID_INPUT', 'source_recent_job_id required.', 400);
        }
        $stmt = $pdo->prepare(
            "SELECT id, glb_path, input_image_path FROM knd_labs_3d_jobs
             WHERE id = ? AND user_id = ? AND status = 'completed' LIMIT 1"
        );
        $stmt->execute([$recentId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['input_image_path'])) {
            json_error('RECENT_NOT_FOUND', 'Recent creation not found or has no source image.', 404);
        }
        $srcPath = $row['input_image_path'];
        $abs = storage_path($srcPath);
        if (!is_file($abs) || !is_readable($abs)) {
            json_error('NO_IMAGE', 'Source image not available.', 400);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) ?: 'png';
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $ext = 'png';
        $filename = $publicId . '_recent.' . $ext;
        $inputDir = storage_path(LABS_3D_STORAGE_INPUT);
        if (!is_dir($inputDir) && !@mkdir($inputDir, 0755, true)) {
            json_error('STORAGE_ERROR', 'Could not prepare storage.', 500);
        }
        $inputRel = LABS_3D_STORAGE_INPUT . '/' . $filename;
        $inputAbs = storage_path($inputRel);
        if (!copy($abs, $inputAbs)) {
            json_error('STORAGE_ERROR', 'Could not copy image.', 500);
        }
        $inputImagePath = $inputRel;
        $sourceRecentJobId = $recentId;
        $sourceRecentType = $recentType;
    } else {
        if ($prompt === '') {
            json_error('INVALID_INPUT', 'Prompt required for text-only mode.', 400);
        }
    }

    $advancedJson = null;
    if (!empty($_POST['advanced_params'])) {
        $adv = json_decode((string) $_POST['advanced_params'], true);
        if (is_array($adv)) {
            $advancedJson = json_encode($adv);
        }
    }

    $stmt = $pdo->prepare(
        "INSERT INTO knd_labs_3d_jobs
        (user_id, public_id, mode, prompt, negative_prompt, category, style, quality, policy_mode,
         input_image_path, source_recent_job_id, source_recent_type, advanced_params_json, kp_cost, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'safe', ?, ?, ?, ?, ?, 'queued')"
    );

    $ok = $stmt->execute([
        $userId, $publicId, $mode, $prompt, $negativePrompt, $category, $style, $quality,
        $inputImagePath, $sourceRecentJobId ?: null, $sourceRecentType ?: null, $advancedJson, $kpCost,
    ]);

    if (!$ok) {
        if ($inputImagePath && file_exists(storage_path($inputImagePath))) {
            @unlink(storage_path($inputImagePath));
        }
        json_error('DB_ERROR', 'Could not create job.', 500);
    }

    $jobId = (int) $pdo->lastInsertId();

    $now = gmdate('Y-m-d H:i:s');
    $ledger = $pdo->prepare(
        "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
         VALUES (?, '3d_lab_spend', ?, 'spend', 'spent', ?, ?)"
    );
    $ledger->execute([$userId, $jobId, -$kpCost, $now]);

    if (isset($_SESSION['sc_badge_cache'])) {
        unset($_SESSION['sc_badge_cache']);
    }

    json_success([
        'id' => $jobId,
        'public_id' => $publicId,
        'status' => 'queued',
        'kp_cost' => $kpCost,
        'available_after' => get_available_points($pdo, $userId),
        'created_at' => $now,
    ]);
} catch (\Throwable $e) {
    error_log('api/labs/3d-lab/create: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}
