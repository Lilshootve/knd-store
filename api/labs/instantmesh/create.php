<?php
/**
 * KND Labs InstantMesh - create job
 * POST /api/labs/instantmesh/create.php
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

const INSTANTMESH_MAX_SIZE = 10 * 1024 * 1024; // 10MB
const INSTANTMESH_COST = 15;
const INSTANTMESH_INPUT_DIR = 'labs/instantmesh/input';

function instantmesh_uuid_v4(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0xffff), random_int(0, 0x4fff) | 0x4000,
        random_int(0, 0xffff) | 0x8000, random_int(0, 0xffff),
        random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

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
        "SELECT COUNT(*) FROM knd_labs_instantmesh_jobs
         WHERE user_id = ? AND status IN ('queued','processing')"
    );
    $stmtActive->execute([$userId]);
    $activeJobs = (int) $stmtActive->fetchColumn();
    if ($activeJobs > 0) {
        json_error('ACTIVE_JOB_EXISTS', 'You already have an active InstantMesh job.', 429);
    }

    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);
    $available = get_available_points($pdo, $userId);
    if ($available < INSTANTMESH_COST) {
        json_error('INSUFFICIENT_POINTS', 'Insufficient credits for this generation.', 400);
    }

    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        json_error('INVALID_IMAGE', 'Image upload is required.', 400);
    }

    $file = $_FILES['image'];
    if ((int) $file['size'] > INSTANTMESH_MAX_SIZE) {
        json_error('FILE_TOO_LARGE', 'Image must be <= 10MB.', 400);
    }

    $tmpPath = (string) $file['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
        json_error('INVALID_IMAGE', 'Invalid upload source.', 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmpPath) : '';
    if ($finfo) finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        json_error('INVALID_IMAGE_TYPE', 'Allowed formats: jpg, jpeg, png, webp.', 400);
    }

    $seed = isset($_POST['seed']) && $_POST['seed'] !== '' ? (int) $_POST['seed'] : 42;
    if ($seed < 0) $seed = 42;
    $removeBg = !empty($_POST['remove_bg']) ? 1 : 0;

    $outputFormat = strtolower(trim((string) ($_POST['output_format'] ?? 'glb')));
    if (!in_array($outputFormat, ['glb', 'obj', 'both'], true)) {
        json_error('INVALID_OUTPUT_FORMAT', 'output_format must be glb, obj or both.', 400);
    }

    $publicId = instantmesh_uuid_v4();
    $ext = $allowed[$mime];
    $filename = $publicId . '.' . $ext;

    $inputDir = storage_path(INSTANTMESH_INPUT_DIR);
    if (!is_dir($inputDir) && !@mkdir($inputDir, 0755, true)) {
        json_error('STORAGE_ERROR', 'Could not prepare storage directory.', 500);
    }

    $inputRel = INSTANTMESH_INPUT_DIR . '/' . $filename;
    $inputAbs = storage_path($inputRel);
    if (!move_uploaded_file($tmpPath, $inputAbs)) {
        json_error('STORAGE_ERROR', 'Could not save uploaded image.', 500);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO knd_labs_instantmesh_jobs
        (user_id, public_id, status, source_image_path, remove_bg, seed, output_format, credits_cost)
        VALUES (?, ?, 'queued', ?, ?, ?, ?, ?)"
    );

    $ok = $stmt->execute([
        $userId,
        $publicId,
        $inputRel,
        $removeBg,
        $seed,
        $outputFormat,
        INSTANTMESH_COST,
    ]);

    if (!$ok) {
        @unlink($inputAbs);
        json_error('DB_ERROR', 'Could not create job.', 500);
    }

    $jobId = (int) $pdo->lastInsertId();

    $now = gmdate('Y-m-d H:i:s');
    $ledger = $pdo->prepare(
        "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
         VALUES (?, '3d_generation', ?, 'spend', 'spent', ?, ?)"
    );
    $ledger->execute([$userId, $jobId, -INSTANTMESH_COST, $now]);

    if (isset($_SESSION['sc_badge_cache'])) {
        unset($_SESSION['sc_badge_cache']);
    }

    json_success([
        'id' => $jobId,
        'public_id' => $publicId,
        'status' => 'queued',
        'seed' => $seed,
        'remove_bg' => $removeBg,
        'output_format' => $outputFormat,
        'credits_cost' => INSTANTMESH_COST,
        'available_after' => get_available_points($pdo, $userId),
        'created_at' => gmdate('Y-m-d H:i:s'),
    ]);
} catch (\Throwable $e) {
    error_log('api/labs/instantmesh/create: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred while creating the job.', 500);
}
