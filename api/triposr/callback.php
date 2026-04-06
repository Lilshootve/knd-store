<?php
/**
 * InstantMesh 3D job callback from GPU server.
 * Endpoint: POST /api/triposr/callback.php (kept for backward compatibility)
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/triposr_config.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/triposr.php';
require_once BASE_PATH . '/includes/support_credits.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'POST only']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $secret = $body['secret'] ?? '';
    if (!hash_equals(INSTANTMESH_CALLBACK_SECRET, $secret)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid secret']);
        exit;
    }

    $jobId = trim($body['job_id'] ?? '');
    $status = trim($body['status'] ?? '');
    $modelUrl = trim($body['model_url'] ?? '');

    if ($jobId === '') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'job_id required']);
        exit;
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'DB error']);
        exit;
    }

    $job = get_triposr_job($pdo, $jobId);
    if (!$job) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Job not found']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $updates = [];

    if ($status === 'completed' && $modelUrl !== '') {
        $outputDir = storage_path(TRIPOSR_OUTPUT_DIR);
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0750, true);
        }
        $ext = (strpos($modelUrl, '.glb') !== false) ? 'glb' : 'obj';
        $outFile = $jobId . '.' . $ext;
        $outPath = $outputDir . DIRECTORY_SEPARATOR . $outFile;

        $ch = curl_init($modelUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data !== false && $httpCode >= 200 && $httpCode < 300 && strlen($data) > 0) {
            if (file_put_contents($outPath, $data) !== false) {
                $updates['output_path'] = TRIPOSR_OUTPUT_DIR . '/' . $outFile;
                $updates['status'] = 'completed';
                $updates['completed_at'] = $now;
            } else {
                $updates['status'] = 'failed';
                $updates['error_message'] = 'Could not save model file';
                $updates['completed_at'] = $now;
            }
        } else {
            $updates['status'] = 'failed';
            $updates['error_message'] = 'Could not download model from GPU server';
            $updates['completed_at'] = $now;
        }
    } elseif ($status === 'failed') {
        $updates['status'] = 'failed';
        $updates['error_message'] = trim($body['error_message'] ?? 'GPU processing failed');
        $updates['completed_at'] = $now;
    }

    if (!empty($updates)) {
        update_triposr_job($pdo, $jobId, $updates);

        if (($updates['status'] ?? '') === 'failed') {
            $cost = triposr_quality_cost($job['quality'] ?? 'balanced');
            triposr_refund_points($pdo, (int) $job['id'], (int) $job['user_id'], $cost);
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('instantmesh/callback: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Internal error']);
}
