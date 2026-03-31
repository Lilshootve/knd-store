<?php
/**
 * KND Labs - ComfyUI job status
 * GET /api/labs/status.php?job_id=XXX
 */
header('Cache-Control: no-store, no-cache');
header('Content-Type: application/json');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/support_credits.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/comfyui.php';
require_once __DIR__ . '/../../includes/comfyui_provider.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
    }

    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $jobId = trim($_GET['job_id'] ?? '');
    if ($jobId === '') {
        json_error('INVALID_INPUT', 'job_id is required.', 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM knd_labs_jobs WHERE id = ? AND user_id = ? LIMIT 1");
    if (!$stmt || !$stmt->execute([$jobId, current_user_id()])) {
        json_error('DB_ERROR', 'Could not fetch job.', 500);
    }

    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) {
        json_error('JOB_NOT_FOUND', 'Job not found.', 404);
    }

    $baseUrl = null;
    $token = comfyui_get_token($pdo);
    $jobProvider = $job['provider'] ?? null;
    if ($jobProvider) {
        $baseUrl = $jobProvider === 'runpod'
            ? comfyui_get_base_url_runpod($pdo)
            : comfyui_get_base_url_local($pdo);
        if ($baseUrl === '' && $jobProvider === 'runpod') {
            $baseUrl = comfyui_get_base_url($pdo, null);
        }
    }
    if ($baseUrl === null || $baseUrl === '') {
        $baseUrl = comfyui_get_base_url($pdo, null);
    }

    // Refresh status from ComfyUI if still running
    if ($job['status'] === 'pending' || $job['status'] === 'processing') {
        $jobTool = (string) ($job['tool'] ?? '');
        if ($jobTool === '3d_vertex') {
            $stmt2 = $pdo->prepare("UPDATE knd_labs_jobs SET status = 'processing', updated_at = NOW() WHERE id = ?");
            if ($stmt2) $stmt2->execute([$jobId]);
            $job['status'] = 'processing';
        } else {
        $promptId = $job['comfy_prompt_id'] ?? '';
        $historyRaw = $promptId ? comfyui_get_history($promptId, $baseUrl, $token) : null;

        // Some ComfyUI history responses come wrapped like: { "<promptId>": { ... } }
        $history = $historyRaw;
        if (is_array($historyRaw) && $promptId !== '' && isset($historyRaw[$promptId]) && is_array($historyRaw[$promptId])) {
            $history = $historyRaw[$promptId];
        }

        $filename = null;
        $subfolder = '';
        $imgType = 'output';

        if (is_array($history) && isset($history['outputs']) && is_array($history['outputs'])) {
            foreach ($history['outputs'] as $nodeOutputs) {
                if (isset($nodeOutputs['images']) && is_array($nodeOutputs['images'])) {
                    foreach ($nodeOutputs['images'] as $img) {
                        if (!empty($img['filename'])) {
                            $filename = $img['filename'];
                            $subfolder = $img['subfolder'] ?? '';
                            $imgType = $img['type'] ?? 'output';
                            break 2;
                        }
                    }
                }
            }
        }

        if ($filename) {
            $imageUrl = '/api/labs/image.php?job_id=' . $jobId;

            $stmt2 = $pdo->prepare("UPDATE knd_labs_jobs SET status = 'done', image_url = ?, finished_at = NOW(), updated_at = NOW() WHERE id = ?");
            if ($stmt2) $stmt2->execute([$imageUrl, $jobId]);

            $job['status'] = 'done';
            $job['image_url'] = $imageUrl;
        } else {
            // Check error status from history if available
            if (is_array($history) && isset($history['status']) && is_array($history['status'])) {
                $st = $history['status'];
                if (($st['status_str'] ?? '') === 'error') {
                    $errMsg = $st['messages'] ?? 'ComfyUI error';
                    $errStr = is_string($errMsg) ? $errMsg : json_encode($errMsg);

                    $stmt2 = $pdo->prepare("UPDATE knd_labs_jobs SET status = 'failed', error_message = ?, finished_at = NOW(), updated_at = NOW() WHERE id = ?");
                    if ($stmt2) $stmt2->execute([$errStr, $jobId]);

                    $job['status'] = 'failed';
                    $job['error_message'] = $errStr;
                } else {
                    $stmt2 = $pdo->prepare("UPDATE knd_labs_jobs SET status = 'processing', updated_at = NOW() WHERE id = ?");
                    if ($stmt2) $stmt2->execute([$jobId]);
                    $job['status'] = 'processing';
                }
            } else {
                $stmt2 = $pdo->prepare("UPDATE knd_labs_jobs SET status = 'processing', updated_at = NOW() WHERE id = ?");
                if ($stmt2) $stmt2->execute([$jobId]);
                $job['status'] = 'processing';
            }
        }
        }
    }

    $avgSeconds = ['text2img' => 60, 'upscale' => 40, 'remove-bg' => 35, 'character' => 45, 'consistency' => 45, '3d_vertex' => 180];
    $avgSec = $avgSeconds[$job['tool'] ?? 'text2img'] ?? 60;

    $queuePosition = null;
    $etaSeconds = null;
    if ($job['status'] === 'queued') {
        $stmtPos = $pdo->prepare(
            "SELECT COUNT(*) FROM knd_labs_jobs j2
             JOIN knd_labs_jobs j0 ON j0.id = ?
             WHERE j2.status = 'queued'
             AND (j2.priority < j0.priority OR (j2.priority = j0.priority AND j2.created_at <= j0.created_at))"
        );
        $stmtPos->execute([$jobId]);
        $queuePosition = (int) $stmtPos->fetchColumn();
        $etaSeconds = $queuePosition * $avgSec;
    }

    $stage = $job['status'];
    if ($job['status'] === 'processing') {
        $stage = !empty($job['comfy_prompt_id']) ? 'generating' : 'picked';
    }

    $data = [
        'status' => $job['status'],
        'stage' => $stage,
        'provider' => $job['provider'] ?? null,
        'queue_position' => $queuePosition,
        'eta_seconds' => $etaSeconds,
        'image_url' => $job['image_url'] ?? null,
        'error_message' => $job['error_message'] ?? null
    ];
    $uid = current_user_id();
    if ($uid) {
        release_available_points_if_due($pdo, $uid);
        $data['available_after'] = get_available_points($pdo, $uid);
    }
    json_success($data);
} catch (\Throwable $e) {
    error_log('api/labs/status: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}