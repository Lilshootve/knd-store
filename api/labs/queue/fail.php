<?php
/**
 * POST /api/labs/queue/fail.php
 * Auth: X-KND-WORKER-TOKEN header
 * Marks job as failed (if attempts>=3) or requeues.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/worker_auth.php';

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

    $errorMessage = trim($_POST['error_message'] ?? 'Unknown error');
    $noRetry = isset($_POST['no_retry']) && ($_POST['no_retry'] === '1' || $_POST['no_retry'] === 'true');
    $retryInSeconds = isset($_POST['retry_in_seconds']) ? (int) $_POST['retry_in_seconds'] : null;

    $pdo = getDBConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT attempts FROM knd_labs_jobs WHERE id = ? AND status = 'processing' LIMIT 1");
    $stmt->execute([$jobId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Job not found or not processing']);
        exit;
    }

    $attempts = (int) ($row['attempts'] ?? 0);
    $finalFail = $noRetry || $retryInSeconds === 0 || $attempts >= 3;

    if ($finalFail) {
        $stmt = $pdo->prepare(
            "UPDATE knd_labs_jobs SET
               status = 'failed',
               error_message = ?,
               finished_at = NOW(),
               locked_at = NULL,
               locked_by = NULL,
               updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$errorMessage, $jobId]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE knd_labs_jobs SET
               status = 'queued',
               locked_at = NULL,
               locked_by = NULL,
               updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$jobId]);
    }

    echo json_encode(['ok' => true, 'requeued' => !$finalFail]);
} catch (\Throwable $e) {
    error_log('api/labs/queue/fail: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
