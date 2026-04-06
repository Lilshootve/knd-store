<?php
/**
 * POST /api/labs/queue/lease.php
 * Auth: X-KND-WORKER-TOKEN header
 * Leases 1 queued job (atomically) and returns full job data.
 */
require_once __DIR__ . '/../../../config/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/worker_auth.php';

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

    $pdo = getDBConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $workerId = trim($_POST['worker_id'] ?? $_SERVER['HTTP_X_WORKER_ID'] ?? 'http-worker');

    $pdo->beginTransaction();
    $stmt = $pdo->query(
        "SELECT id, user_id, tool, prompt, negative_prompt, payload_json, provider, attempts, cost_kp, quality
         FROM knd_labs_jobs
         WHERE status = 'queued'
         ORDER BY priority ASC, created_at ASC
         LIMIT 1
         FOR UPDATE SKIP LOCKED"
    );
    $job = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (!$job) {
        $pdo->rollBack();
        echo json_encode(['ok' => true, 'job' => null]);
        exit;
    }

    $jobId = (int) $job['id'];
    $attempts = (int) ($job['attempts'] ?? 0) + 1;

    $pdo->prepare(
        "UPDATE knd_labs_jobs SET
           status = 'processing',
           locked_at = NOW(),
           locked_by = ?,
           started_at = IFNULL(started_at, NOW()),
           attempts = ?,
           updated_at = NOW()
         WHERE id = ?"
    )->execute([$workerId, $attempts, $jobId]);

    $pdo->commit();

    $payload = json_decode($job['payload_json'] ?? '{}', true);
    $jobData = [
        'id' => $jobId,
        'user_id' => (int) $job['user_id'],
        'tool' => $job['tool'] ?? 'text2img',
        'prompt' => $job['prompt'] ?? '',
        'negative_prompt' => $job['negative_prompt'] ?? '',
        'quality' => $job['quality'] ?? '',
        'cost_kp' => (int) ($job['cost_kp'] ?? 0),
        'provider' => $job['provider'] ?? 'local',
        'attempts' => $attempts,
        'payload' => is_array($payload) ? $payload : [],
    ];

    echo json_encode(['ok' => true, 'job' => $jobData]);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('api/labs/queue/lease: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
