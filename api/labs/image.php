<?php
/**
 * GET /api/labs/image.php?job_id=XXX[&download=1]
 * Serves job output via proxy (image/model). download=1 forces attachment.
 */
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/storage.php';
if (file_exists(__DIR__ . '/../../config/labs.php')) {
    require_once __DIR__ . '/../../config/labs.php';
}
require_once __DIR__ . '/../../includes/comfyui.php';
require_once __DIR__ . '/../../includes/comfyui_provider.php';

try {
    api_require_login();

    $jobId = trim($_GET['job_id'] ?? '');
    if ($jobId === '') json_error('INVALID_INPUT', 'job_id is required.', 400);

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $currentUserId = current_user_id();
    $stmt = $pdo->prepare(
        "SELECT j.* FROM knd_labs_jobs j
         LEFT JOIN users u ON j.user_id = u.id
         WHERE j.id = ? AND (
           j.user_id = ? OR
           (j.status = 'done' AND COALESCE(u.labs_recent_private, 0) = 0)
         ) LIMIT 1"
    );
    $stmt->execute([$jobId, $currentUserId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) json_error('JOB_NOT_FOUND', 'Job not found.', 404);

    if (($job['status'] ?? '') !== 'done') {
        json_error('NO_OUTPUT', 'Output not ready.', 409);
    }

    $outputPath = $job['output_path'] ?? '';
    $tool = $job['tool'] ?? '';
    if ($outputPath !== '') {
        $fullPath = storage_path($outputPath);
        $base = realpath(storage_path());
        $resolved = is_file($fullPath) ? realpath($fullPath) : false;
        if ($resolved && $base && strpos($resolved, $base) === 0 && is_readable($fullPath)) {
            $size = filesize($fullPath);
            if ($size > 0) {
                $download = isset($_GET['download']) && $_GET['download'] !== '0' && $_GET['download'] !== '';
                $ext = strtolower((string) pathinfo($outputPath, PATHINFO_EXTENSION));
                $ct = 'application/octet-stream';
                if ($ext === 'png') $ct = 'image/png';
                elseif ($ext === 'jpg' || $ext === 'jpeg') $ct = 'image/jpeg';
                elseif ($ext === 'webp') $ct = 'image/webp';
                elseif ($ext === 'glb') $ct = 'model/gltf-binary';
                elseif ($ext === 'obj') $ct = 'text/plain';
                header('Content-Type: ' . $ct);
                header('Content-Length: ' . $size);
                header('X-Content-Type-Options: nosniff');
                header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="knd_labs_' . $jobId . ($ext ? ('.' . $ext) : '') . '"');
                readfile($fullPath);
                exit;
            }
            error_log('api/labs/image: job_id=' . $jobId . ' output_path empty or invalid file: ' . $fullPath);
        } else {
            error_log('api/labs/image: job_id=' . $jobId . ' output_path not readable or outside storage: ' . $fullPath);
        }
    }

    // Fallback: serve from KND_FINAL_IMAGE_DIR when worker saves there (e.g. F:\KND\output) and server can read it
    if ($tool !== '' && defined('KND_FINAL_IMAGE_DIR') && KND_FINAL_IMAGE_DIR !== '') {
        $fallbackDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, KND_FINAL_IMAGE_DIR), DIRECTORY_SEPARATOR);
        $baseName = 'job_' . $jobId . '_' . $tool . '.';
        foreach (['png', 'jpg', 'jpeg', 'webp', 'glb'] as $ext) {
            $path = $fallbackDir . DIRECTORY_SEPARATOR . $baseName . $ext;
            if (is_file($path) && is_readable($path) && filesize($path) > 0) {
                $size = filesize($path);
                $ct = $ext === 'webp' ? 'image/webp' : ($ext === 'png' ? 'image/png' : ($ext === 'glb' ? 'model/gltf-binary' : 'image/jpeg'));
                $download = isset($_GET['download']) && $_GET['download'] !== '0' && $_GET['download'] !== '';
                header('Content-Type: ' . $ct);
                header('Content-Length: ' . (string) $size);
                header('X-Content-Type-Options: nosniff');
                header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="knd_labs_' . $jobId . '.' . $ext . '"');
                readfile($path);
                exit;
            }
        }
    }

    $promptId = $job['comfy_prompt_id'] ?? '';
    if (!$promptId) json_error('NO_OUTPUT', 'No ComfyUI prompt for this job.', 409);

    $baseUrl = null;
    $provider = $job['provider'] ?? null;
    if ($provider === 'runpod') {
        $baseUrl = comfyui_get_base_url_runpod($pdo);
    } else {
        $baseUrl = comfyui_get_base_url_local($pdo);
    }
    if (!$baseUrl) $baseUrl = comfyui_get_base_url($pdo, null);
    $token = comfyui_get_token($pdo);

    $historyRaw = comfyui_get_history($promptId, $baseUrl, $token);
    $history = $historyRaw;
    // Some ComfyUI deployments wrap history as: { "<promptId>": { ... } }
    if (is_array($historyRaw) && isset($historyRaw[$promptId]) && is_array($historyRaw[$promptId])) {
        $history = $historyRaw[$promptId];
    }
    $filename = null;
    $subfolder = '';
    $imgType = 'output';
    if (is_array($history['outputs'] ?? null)) {
        foreach ($history['outputs'] as $nodeOutputs) {
            if (!is_array($nodeOutputs)) continue;
            foreach (['images', 'gifs', 'meshes', 'mesh', 'files', 'glbs'] as $bucket) {
                if (!isset($nodeOutputs[$bucket]) || !is_array($nodeOutputs[$bucket])) continue;
                foreach ($nodeOutputs[$bucket] as $img) {
                    if (!empty($img['filename'])) {
                        $filename = $img['filename'];
                        $subfolder = $img['subfolder'] ?? '';
                        $imgType = $img['type'] ?? 'output';
                        break 3;
                    }
                }
            }
        }
    }
    if (!$filename) json_error('FETCH_FAILED', 'Could not resolve output from ComfyUI history.', 502);

    $params = ['filename' => $filename, 'type' => $imgType];
    if ($subfolder !== '') $params['subfolder'] = $subfolder;
    $imageUrl = rtrim($baseUrl, '/') . '/view?' . http_build_query($params);

    $headers = ['Accept: */*'];
    if ($token !== '') $headers[] = 'X-KND-TOKEN: ' . $token;

    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $bin = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($bin === false || $code < 200 || $code >= 300) {
        json_error('FETCH_FAILED', 'Could not fetch output from ComfyUI.', 502);
    }

    $download = isset($_GET['download']) && $_GET['download'] !== '0' && $_GET['download'] !== '';
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $downloadName = 'knd_labs_' . $jobId . ($ext ? ('.' . $ext) : '');

    header('Content-Type: ' . ($ct ?: 'application/octet-stream'));
    header('Content-Length: ' . strlen($bin));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $downloadName . '"');

    echo $bin;
    exit;
} catch (\Throwable $e) {
    error_log('api/labs/image: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}
