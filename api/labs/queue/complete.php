<?php
/**
 * POST /api/labs/queue/complete.php
 * Auth: X-KND-WORKER-TOKEN header
 * Marks job as done with image_url.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/worker_auth.php';
require_once __DIR__ . '/../../../includes/knd_badges.php';

$workerToken = get_worker_token();
$headerToken = trim($_SERVER['HTTP_X_KND_WORKER_TOKEN'] ?? '');
if ($workerToken === '' || $headerToken === '' || !hash_equals($workerToken, $headerToken)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST only']);
        exit;
    }

    $jobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
    if ($jobId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'job_id required']);
        exit;
    }

    $imageUrl = trim($_POST['image_url'] ?? '');
    if ($imageUrl === '') {
        $imageUrl = '/api/labs/image.php?job_id=' . $jobId;
    }

    $comfyPromptId = trim($_POST['comfy_prompt_id'] ?? '');
    $outputPath = trim($_POST['output_path'] ?? '');

    $pdo = getDBConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $updates = [
        "status = 'done'",
        "image_url = ?",
        "comfy_prompt_id = COALESCE(NULLIF(?, ''), comfy_prompt_id)",
        "finished_at = NOW()",
        "locked_at = NULL",
        "locked_by = NULL",
        "updated_at = NOW()",
    ];
    $params = [$imageUrl, $comfyPromptId];
    $hasOutputPath = $pdo->query("SHOW COLUMNS FROM knd_labs_jobs LIKE 'output_path'")->rowCount() > 0;
    if ($hasOutputPath && $outputPath !== '') {
        $updates[] = "output_path = ?";
        $params[] = $outputPath;
    }
    $params[] = $jobId;
    $sql = "UPDATE knd_labs_jobs SET " . implode(', ', $updates) . " WHERE id = ? AND status = 'processing'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Job not found or not processing']);
        exit;
    }

    // Check and grant generator badges after successful job completion
    try {
        // Get user_id from the job
        $stmt = $pdo->prepare("SELECT user_id FROM knd_labs_jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($job && isset($job['user_id'])) {
            $userId = (int)$job['user_id'];
            badges_check_and_grant($pdo, $userId, 'generator');
        }
    } catch (\Throwable $e) {
        // Log badge error but don't fail the completion
        error_log('Badge check failed in labs/queue/complete: ' . $e->getMessage());
    }

    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('api/labs/queue/complete: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
