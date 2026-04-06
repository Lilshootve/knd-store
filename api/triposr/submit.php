<?php
/**
 * InstantMesh 3D job submission.
 * Endpoint: POST /api/triposr/submit.php (kept for backward compatibility)
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/triposr_config.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/triposr.php';
require_once BASE_PATH . '/includes/support_credits.php';

define('TRIPOSR_MAX_ACTIVE_JOBS', 1);
define('TRIPOSR_MAX_SUBMITS_PER_HOUR', 10);
define('TRIPOSR_MAX_SUBMITS_PER_DAY', 30);
define('TRIPOSR_MAX_IMAGE_DIMENSION', 4096);
define('TRIPOSR_MAX_IMAGE_SIZE', 10 * 1024 * 1024); // 10MB

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

    // Validate quality
    $quality = trim($_POST['quality'] ?? 'balanced');
    $allowedQuality = ['fast', 'balanced', 'high'];
    if (!in_array($quality, $allowedQuality, true)) {
        $quality = 'balanced';
    }

    $cost = triposr_quality_cost($quality);

    // Limit: active jobs per user (max 1)
    $activeCount = triposr_count_active_jobs($pdo, $userId);
    if ($activeCount >= TRIPOSR_MAX_ACTIVE_JOBS) {
        json_error('ACTIVE_LIMIT', t('triposr.error.active_limit', 'Ya tienes un modelo generándose. Espera a que termine.'), 429);
    }

    // Rate limit: submits per hour
    $hourCount = triposr_count_jobs_last_hour($pdo, $userId);
    if ($hourCount >= TRIPOSR_MAX_SUBMITS_PER_HOUR) {
        json_error('RATE_LIMIT', t('triposr.error.rate_limit', 'Límite por hora alcanzado. Máximo 10 generaciones por hora.'), 429);
    }

    // Daily limit
    $dayCount = triposr_count_jobs_today($pdo, $userId);
    if ($dayCount >= TRIPOSR_MAX_SUBMITS_PER_DAY) {
        json_error('DAILY_LIMIT', t('triposr.error.daily_limit', 'Límite diario alcanzado. Máximo 30 generaciones por día.'), 429);
    }

    // Check points before image validation (fail fast)
    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);
    $available = get_available_points($pdo, $userId);
    if ($available < $cost) {
        json_error('INSUFFICIENT_POINTS', t('triposr.error.insufficient_points', 'Puntos insuficientes. Tienes {available} KP, necesitas {cost} KP para calidad {quality}.', ['available' => $available, 'cost' => $cost, 'quality' => $quality]), 400);
    }

    // Validate uploaded image
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'No image uploaded or upload error.';
        if (!empty($_FILES['image']['error'])) {
            switch ($_FILES['image']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $msg = 'File too large.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $msg = 'File only partially uploaded.';
                    break;
                default:
                    $msg = 'Upload failed.';
            }
        }
        json_error('INVALID_IMAGE', $msg);
    }

    $file = $_FILES['image'];
    if ($file['size'] > TRIPOSR_MAX_IMAGE_SIZE) {
        json_error('FILE_TOO_LARGE', 'Image must be 10MB or smaller.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) {
        json_error('INVALID_IMAGE', 'Only JPG, PNG and WebP images are allowed. SVG and other formats are not supported.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'svg') {
        json_error('INVALID_IMAGE', 'SVG is not supported. Use JPG, PNG or WebP.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        json_error('INVALID_IMAGE', 'Could not read image dimensions. File may be corrupted.');
    }
    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1) {
        json_error('INVALID_IMAGE', 'Invalid image dimensions.');
    }
    if ($width > TRIPOSR_MAX_IMAGE_DIMENSION || $height > TRIPOSR_MAX_IMAGE_DIMENSION) {
        json_error('IMAGE_TOO_LARGE', 'Image dimensions must not exceed 4096x4096.');
    }

    // Ensure directories exist
    $uploadDir = storage_path(TRIPOSR_UPLOAD_DIR);
    $outputDir = storage_path(TRIPOSR_OUTPUT_DIR);
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0750, true);
    }
    if (!is_dir($outputDir)) {
        @mkdir($outputDir, 0750, true);
    }
    if (!is_writable($uploadDir)) {
        json_error('STORAGE_ERROR', 'Upload directory not writable.', 500);
    }

    $jobUuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0xffff), random_int(0, 0x4fff) | 0x4000,
        random_int(0, 0xffff) | 0x8000, random_int(0, 0xffff),
        random_int(0, 0xffff), random_int(0, 0xffff)
    );

    $ext = (strpos($mime, 'png') !== false) ? 'png' : ((strpos($mime, 'webp') !== false) ? 'webp' : 'jpg');
    $fileName = $jobUuid . '.' . $ext;
    $destPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        json_error('STORAGE_ERROR', 'Could not save uploaded image.', 500);
    }

    $inputRelPath = TRIPOSR_UPLOAD_DIR . '/' . $fileName;
    $job = create_triposr_job($pdo, $userId, $inputRelPath, $jobUuid, $quality);
    if (!$job) {
        @unlink($destPath);
        json_error('JOB_CREATE_FAILED', 'Could not create job.', 500);
    }

    // Deduct points and record in ledger (source_id = job id)
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
         VALUES (?, '3d_generation', ?, 'spend', 'spent', ?, ?)"
    );
    $stmt->execute([$userId, (int) $job['id'], -$cost, $now]);

    $siteUrl = rtrim(defined('SITE_URL') ? SITE_URL : ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
    $imageUrl = $siteUrl . '/api/triposr/image.php?t=' . urlencode($job['job_uuid']);
    $callbackUrl = $siteUrl . '/api/triposr/callback.php';

    if (!empty(INSTANTMESH_API_URL)) {
        $payload = json_encode([
            'job_id' => $job['job_uuid'],
            'image_url' => $imageUrl,
            'callback_url' => $callbackUrl,
            'secret' => INSTANTMESH_CALLBACK_SECRET,
            'quality' => $quality,
            'timestamp' => time(),
        ]);
        $ch = curl_init(INSTANTMESH_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            update_triposr_job($pdo, $job['job_uuid'], [
                'status' => 'failed',
                'error_message' => 'GPU server unreachable: ' . $err,
                'completed_at' => $now,
            ]);
            triposr_refund_points($pdo, (int) $job['id'], $userId, $cost);
        } else {
            update_triposr_job($pdo, $job['job_uuid'], ['status' => 'processing']);
        }
    }

    if (isset($_SESSION['sc_badge_cache'])) {
        unset($_SESSION['sc_badge_cache']);
    }

    json_success([
        'job_id' => $job['job_uuid'],
        'status' => $job['status'],
        'quality' => $quality,
        'cost' => $cost,
        'available_after' => get_available_points($pdo, $userId),
    ]);
} catch (\Throwable $e) {
    error_log('instantmesh/submit: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
