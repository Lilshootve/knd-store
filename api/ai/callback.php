<?php
/**
 * AI job callback from GPU server. POST /api/ai/callback.php
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/ai_config.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/storage.php';
require_once BASE_PATH . '/includes/triposr.php';
require_once BASE_PATH . '/includes/ai.php';

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
    $signature = $body['signature'] ?? '';
    $timestamp = $body['timestamp'] ?? '';

    if ($signature !== '' && $timestamp !== '' && AI_CALLBACK_SECRET !== '') {
        $payloadForSign = $body;
        unset($payloadForSign['signature']);
        $bodyToSign = json_encode($payloadForSign, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha256', $timestamp . $bodyToSign, AI_CALLBACK_SECRET);
        if (!hash_equals($expected, $signature)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
            exit;
        }
    } elseif (!hash_equals(AI_CALLBACK_SECRET, $secret)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid secret']);
        exit;
    }

    $jobId = trim($body['job_id'] ?? '');
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

    $job = ai_get_job($pdo, $jobId);
    if (!$job) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Job not found']);
        exit;
    }

    $status = trim($body['status'] ?? '');
    $resultUrl = trim($body['result_url'] ?? $body['model_url'] ?? '');
    $resultJson = [];
    $now = date('Y-m-d H:i:s');
    $updates = [];

    if ($status === 'completed' && $resultUrl !== '') {
        $outputDir = storage_path(AI_OUTPUT_DIR);
        if (!is_dir($outputDir)) @mkdir($outputDir, 0750, true);

        $ext = 'png';
        if (strpos($resultUrl, '.jpg') !== false || strpos($resultUrl, '.jpeg') !== false) $ext = 'jpg';
        elseif (strpos($resultUrl, '.webp') !== false) $ext = 'webp';
        elseif (strpos($resultUrl, '.glb') !== false) $ext = 'glb';
        elseif (strpos($resultUrl, '.obj') !== false) $ext = 'obj';

        $outFile = $jobId . '.' . $ext;
        $outPath = $outputDir . DIRECTORY_SEPARATOR . $outFile;

        $ch = curl_init($resultUrl);
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
                $updates['output_path'] = AI_OUTPUT_DIR . '/' . $outFile;
                $updates['status'] = 'completed';
                $updates['completed_at'] = $now;
            } else {
                $updates['status'] = 'failed';
                $updates['error_message'] = 'Could not save result file';
                $updates['completed_at'] = $now;
            }
        } else {
            $updates['status'] = 'failed';
            $updates['error_message'] = 'Could not download result from GPU';
            $updates['completed_at'] = $now;
        }

        if (!empty($body['character_id'])) {
            $resultJson['character_id'] = $body['character_id'];
        }
        if (!empty($body['preview_image_url'])) {
            $resultJson['preview_image_url'] = $body['preview_image_url'];
        }
        if (!empty($resultJson)) {
            $updates['result_json'] = json_encode($resultJson);
        }
    } elseif ($status === 'failed') {
        $updates['status'] = 'failed';
        $updates['error_message'] = trim($body['error_message'] ?? 'GPU processing failed');
        $updates['completed_at'] = $now;
    }

    if (!empty($updates)) {
        ai_update_job($pdo, $jobId, $updates);

        if (($updates['status'] ?? '') === 'failed') {
            $costKp = (int) ($job['cost_kp'] ?? 0);
            if ($costKp > 0) {
                ai_refund_points($pdo, (int) $job['user_id'], (int) $job['id'], $costKp);
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('ai/callback: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Internal error']);
}
