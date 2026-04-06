<?php
/**
 * Character Lab - Create job
 * POST /api/character-lab/create.php
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache');
header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/support_credits.php';
require_once BASE_PATH . '/includes/character_lab_helpers.php';

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

    $stmtActive = $pdo->prepare(
        "SELECT COUNT(*) FROM knd_character_lab_jobs
         WHERE user_id = ? AND status IN ('queued','image_generating','image_ready','mesh_generating')"
    );
    $stmtActive->execute([$userId]);
    if ((int) $stmtActive->fetchColumn() > 0) {
        json_error('ACTIVE_JOB_EXISTS', 'You already have an active Character Lab job.', 429);
    }

    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);
    $kpCost = character_lab_kp_cost();
    $available = get_available_points($pdo, $userId);
    if ($available < $kpCost) {
        json_error('INSUFFICIENT_POINTS', 'Insufficient KP for this generation.', 400);
    }

    $mode = strtolower(trim((string) ($_POST['mode'] ?? 'text')));
    if (!in_array($mode, ['text', 'image', 'text_image', 'recent_image'], true)) {
        json_error('INVALID_MODE', 'mode must be text, image, text_image, or recent_image.', 400);
    }

    $policyMode = strtolower(trim((string) ($_POST['policy_mode'] ?? 'safe')));
    if (!character_lab_policy_allowed($policyMode)) {
        json_error('POLICY_NOT_ALLOWED', 'Only safe mode is available.', 400);
    }

    $promptRaw = trim((string) ($_POST['prompt'] ?? ''));
    $check = character_lab_check_prompt($promptRaw);
    if (!$check['allowed']) {
        json_error('PROMPT_REJECTED', $check['reason'] ?? 'Invalid prompt.', 400);
    }

    $category = character_lab_validate_category((string) ($_POST['category'] ?? 'human'));
    $promptSanitized = character_lab_sanitize_prompt($promptRaw);
    $promptForImage = character_lab_build_prompt($promptSanitized, $category);

    $inputImagePath = null;
    $sourceRecentJobId = null;

    if ($mode === 'image' || $mode === 'text_image') {
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            json_error('INVALID_IMAGE', 'Image upload is required for this mode.', 400);
        }
        $file = $_FILES['image'];
        if ((int) $file['size'] > CHARACTER_LAB_MAX_IMAGE_SIZE) {
            json_error('FILE_TOO_LARGE', 'Image must be <= 10MB.', 400);
        }
        $tmpPath = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            json_error('INVALID_IMAGE', 'Invalid upload source.', 400);
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpPath) : '';
        if ($finfo) finfo_close($finfo);
        if (!isset(CHARACTER_LAB_ALLOWED_MIMES[$mime])) {
            json_error('INVALID_IMAGE_TYPE', 'Allowed formats: jpg, jpeg, png, webp.', 400);
        }
        $publicId = character_lab_uuid();
        $ext = CHARACTER_LAB_ALLOWED_MIMES[$mime];
        $filename = $publicId . '_input.' . $ext;
        $inputDir = storage_path(CHARACTER_LAB_STORAGE_INPUT);
        if (!is_dir($inputDir) && !@mkdir($inputDir, 0755, true)) {
            json_error('STORAGE_ERROR', 'Could not prepare storage directory.', 500);
        }
        $inputRel = CHARACTER_LAB_STORAGE_INPUT . '/' . $filename;
        $inputAbs = storage_path($inputRel);
        if (!move_uploaded_file($tmpPath, $inputAbs)) {
            json_error('STORAGE_ERROR', 'Could not save uploaded image.', 500);
        }
        $inputImagePath = $inputRel;
    } elseif ($mode === 'recent_image') {
        $recentId = (int) ($_POST['source_recent_job_id'] ?? 0);
        $recentSource = trim((string) ($_POST['source_recent_type'] ?? 'labs_job'));
        if ($recentId <= 0) {
            json_error('INVALID_INPUT', 'source_recent_job_id is required for recent_image mode.', 400);
        }
        if ($recentSource === 'labs_job') {
            $stmt = $pdo->prepare("SELECT id, output_path FROM knd_labs_jobs WHERE id = ? AND user_id = ? AND status = 'done' LIMIT 1");
            $stmt->execute([$recentId, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                json_error('RECENT_NOT_FOUND', 'Recent image not found or not yours.', 404);
            }
            $outputPath = $row['output_path'] ?? '';
            if ($outputPath === '') {
                json_error('NO_IMAGE', 'Selected job has no saved image.', 400);
            }
            $abs = storage_path($outputPath);
            if (!is_file($abs) || !is_readable($abs)) {
                json_error('NO_IMAGE', 'Image file not available.', 400);
            }
            $publicId = character_lab_uuid();
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) ?: 'png';
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $ext = 'png';
            $filename = $publicId . '_recent.' . $ext;
            $inputDir = storage_path(CHARACTER_LAB_STORAGE_INPUT);
            if (!is_dir($inputDir) && !@mkdir($inputDir, 0755, true)) {
                json_error('STORAGE_ERROR', 'Could not prepare storage directory.', 500);
            }
            $inputRel = CHARACTER_LAB_STORAGE_INPUT . '/' . $filename;
            $inputAbs = storage_path($inputRel);
            if (!copy($abs, $inputAbs)) {
                json_error('STORAGE_ERROR', 'Could not copy image.', 500);
            }
            $inputImagePath = $inputRel;
            $sourceRecentJobId = $recentId;
        } elseif ($recentSource === 'instantmesh') {
            $stmt = $pdo->prepare("SELECT id, source_image_path, preview_image_path FROM knd_labs_instantmesh_jobs WHERE id = ? AND user_id = ? AND status = 'completed' LIMIT 1");
            $stmt->execute([$recentId, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                json_error('RECENT_NOT_FOUND', 'Recent image not found or not yours.', 404);
            }
            $srcPath = $row['source_image_path'] ?? $row['preview_image_path'] ?? '';
            if ($srcPath === '') {
                json_error('NO_IMAGE', 'Selected job has no image.', 400);
            }
            $abs = storage_path($srcPath);
            if (!is_file($abs) || !is_readable($abs)) {
                json_error('NO_IMAGE', 'Image file not available.', 400);
            }
            $publicId = character_lab_uuid();
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) ?: 'png';
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $ext = 'png';
            $filename = $publicId . '_recent.' . $ext;
            $inputDir = storage_path(CHARACTER_LAB_STORAGE_INPUT);
            if (!is_dir($inputDir) && !@mkdir($inputDir, 0755, true)) {
                json_error('STORAGE_ERROR', 'Could not prepare storage directory.', 500);
            }
            $inputRel = CHARACTER_LAB_STORAGE_INPUT . '/' . $filename;
            $inputAbs = storage_path($inputRel);
            if (!copy($abs, $inputAbs)) {
                json_error('STORAGE_ERROR', 'Could not copy image.', 500);
            }
            $inputImagePath = $inputRel;
            $sourceRecentJobId = $recentId;
        } elseif ($recentSource === 'character_lab') {
            $stmt = $pdo->prepare("SELECT id, concept_image_path FROM knd_character_lab_jobs WHERE id = ? AND user_id = ? AND concept_image_path IS NOT NULL AND concept_image_path != '' LIMIT 1");
            $stmt->execute([$recentId, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                json_error('RECENT_NOT_FOUND', 'Recent image not found or not yours.', 404);
            }
            $srcPath = $row['concept_image_path'];
            $abs = storage_path($srcPath);
            if (!is_file($abs) || !is_readable($abs)) {
                json_error('NO_IMAGE', 'Image file not available.', 400);
            }
            $publicId = character_lab_uuid();
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) ?: 'png';
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $ext = 'png';
            $filename = $publicId . '_recent.' . $ext;
            $inputDir = storage_path(CHARACTER_LAB_STORAGE_INPUT);
            if (!is_dir($inputDir) && !@mkdir($inputDir, 0755, true)) {
                json_error('STORAGE_ERROR', 'Could not prepare storage directory.', 500);
            }
            $inputRel = CHARACTER_LAB_STORAGE_INPUT . '/' . $filename;
            $inputAbs = storage_path($inputRel);
            if (!copy($abs, $inputAbs)) {
                json_error('STORAGE_ERROR', 'Could not copy image.', 500);
            }
            $inputImagePath = $inputRel;
            $sourceRecentJobId = $recentId;
        } else {
            json_error('INVALID_SOURCE', 'source_recent_type must be labs_job, instantmesh, or character_lab.', 400);
        }
    } else {
        if ($promptRaw === '') {
            json_error('INVALID_INPUT', 'Prompt is required for text mode.', 400);
        }
        $publicId = character_lab_uuid();
    }

    if (!isset($publicId)) {
        $publicId = character_lab_uuid();
    }

    $engineImage = 'comfyui';
    $engine3d = 'hunyuan3d';

    $stmt = $pdo->prepare(
        "INSERT INTO knd_character_lab_jobs
        (user_id, public_id, mode, prompt_raw, prompt_sanitized, category, policy_mode,
         input_image_path, source_recent_job_id, engine_image, engine_3d, kp_cost, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued')"
    );

    $ok = $stmt->execute([
        $userId, $publicId, $mode, $promptRaw, $promptSanitized, $category, $policyMode,
        $inputImagePath, $sourceRecentJobId ?: null, $engineImage, $engine3d, $kpCost,
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
         VALUES (?, 'character_lab_spend', ?, 'spend', 'spent', ?, ?)"
    );
    $ledger->execute([$userId, $jobId, -$kpCost, $now]);

    if (isset($_SESSION['sc_badge_cache'])) {
        unset($_SESSION['sc_badge_cache']);
    }

    json_success([
        'id' => $jobId,
        'public_id' => $publicId,
        'status' => 'queued',
        'mode' => $mode,
        'category' => $category,
        'kp_cost' => $kpCost,
        'available_after' => get_available_points($pdo, $userId),
        'created_at' => $now,
    ]);
} catch (\Throwable $e) {
    error_log('api/character-lab/create: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred while creating the job.', 500);
}
