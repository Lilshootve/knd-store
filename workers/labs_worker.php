#!/usr/bin/env php
<?php
/**
 * KND Labs HTTP Worker - No MySQL needed. Uses API lease/complete/fail.
 * Usage:
 *   php workers/labs_worker.php              (single run: process 1 job)
 *   php workers/labs_worker.php --loop --sleep=2 --worker-id=PC1
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
$projectRoot = BASE_PATH;
chdir($projectRoot);

require_once BASE_PATH . '/includes/env.php';
require_once BASE_PATH . '/includes/comfyui.php';
require_once BASE_PATH . '/includes/storage.php';
if (file_exists(BASE_PATH . '/config/labs.php')) {
    require_once BASE_PATH . '/config/labs.php';
}
if (!defined('LABS_UPLOAD_DIR')) define('LABS_UPLOAD_DIR', 'uploads/labs');

$opts = getopt('', ['loop', 'sleep:', 'worker-id:']);
$loop = isset($opts['loop']);
$sleepSec = isset($opts['sleep']) ? max(1, (int) $opts['sleep']) : 2;
$workerId = $opts['worker-id'] ?? 'http-' . gethostname();

$cfg = load_worker_config();

function load_worker_config(): array {
    return [
        'API_BASE'             => knd_env_required('KND_API_BASE'),
        'WORKER_TOKEN'         => knd_env_required('KND_WORKER_TOKEN'),
        'COMFYUI_BASE'         => knd_env_required('COMFYUI_URL'),
        'COMFYUI_3D_URL'       => (string) knd_env('COMFYUI_3D_URL', ''),
        'COMFYUI_TOKEN'        => (string) knd_env('COMFYUI_TOKEN', ''),
        'COMFY_INPUT_DIR'      => (string) knd_env('COMFY_INPUT_DIR', ''),
        'COMFY_OUTPUT_DIR'     => (string) knd_env('COMFY_OUTPUT_DIR', ''),
        'KND_FINAL_IMAGE_DIR'  => (string) knd_env('KND_FINAL_IMAGE_DIR', ''),
    ];
}

function logWorker(string $msg): void {
    $ts = date('Y-m-d H:i:s');
    echo "[$ts] $msg\n";
}

function httpPost(string $url, array $headers, array $post = []): array {
    $ch = curl_init($url);
    $allHeaders = ['Content-Type: application/x-www-form-urlencoded'];
    foreach ($headers as $h) $allHeaders[] = $h;
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post),
        CURLOPT_HTTPHEADER => $allHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : ['ok' => false, 'error' => 'Invalid JSON'];
}

/**
 * Upload output image to API so the server can serve it (recent jobs + download).
 * Returns output_path from response or null on failure. Retries once after 2s on failure.
 */
function workerUploadOutputImage(string $apiBase, array $headers, int $jobId, string $tool, string $imageBytes, string $ext): ?string {
    $tmpFile = tempnam(sys_get_temp_dir(), 'knd_out_');
    if ($tmpFile === false || @file_put_contents($tmpFile, $imageBytes) === false) {
        logWorker("Upload output: failed to write temp file");
        return null;
    }
    $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : ($ext === 'webp' ? 'image/webp' : 'image/png');
    $filename = 'job_' . $jobId . '_' . $tool . '.' . $ext;
    $cfile = new \CURLFile($tmpFile, $mime, $filename);
    $post = ['job_id' => $jobId, 'tool' => $tool, 'image' => $cfile];
    $url = rtrim($apiBase, '/') . '/api/labs/queue/upload-output.php';
    $allHeaders = [];
    foreach ($headers as $h) $allHeaders[] = $h;

    $doUpload = function () use ($url, $allHeaders, $post, $tmpFile) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$body, $code, $err];
    };

    foreach ([0, 1] as $attempt) {
        if ($attempt > 0) {
            logWorker("Upload output retry in 2s...");
            sleep(2);
        }
        list($body, $code, $err) = $doUpload();
        if ($body === false || $code < 200 || $code >= 300) {
            $preview = is_string($body) && $body !== '' ? substr($body, 0, 200) : ($err ?: 'no body');
            logWorker("Upload output failed (attempt " . ($attempt + 1) . "): HTTP $code " . ($err ? "curl=$err " : '') . "body=$preview");
            continue;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['ok']) || empty($data['output_path'])) {
            logWorker("Upload output bad response: " . substr(is_string($body) ? $body : json_encode($body), 0, 150));
            continue;
        }
        @unlink($tmpFile);
        return $data['output_path'];
    }
    @unlink($tmpFile);
    logWorker("Upload output: image will NOT show on website until upload succeeds. Check API_BASE and server upload_max_filesize/post_max_size.");
    return null;
}

function workerDownloadFile(string $url, array $headers): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($body !== false && $code >= 200 && $code < 300) ? $body : null;
}

function workerFetchComfyOutputBytes(string $baseUrl, string $filename, string $subfolder = '', string $type = 'output', string $token = ''): ?string {
    $base = rtrim($baseUrl, '/');
    $params = ['filename' => $filename, 'type' => $type ?: 'output'];
    if ($subfolder !== '') $params['subfolder'] = $subfolder;
    $headers = ['Accept: */*'];
    if ($token !== '') $headers[] = 'X-KND-TOKEN: ' . $token;
    foreach (['/view', '/api/view'] as $path) {
        $url = $base . $path . '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code >= 200 && $code < 300) return $body;
    }
    return null;
}

function workerCreatePlaceholderImage(): string {
    $tmp = tempnam(sys_get_temp_dir(), 'knd_ph');
    if (!$tmp) throw new \RuntimeException('Could not create temp file');
    $img = @imagecreatetruecolor(512, 512);
    if (!$img) {
        @unlink($tmp);
        throw new \RuntimeException('Could not create placeholder image');
    }
    $gray = imagecolorallocate($img, 128, 128, 128);
    imagefill($img, 0, 0, $gray);
    imagepng($img, $tmp);
    imagedestroy($img);
    return $tmp;
}

function workerUploadToComfyui(string $filePath, string $baseUrl, string $token = ''): string {
    $base = rtrim($baseUrl, '/');
    $mime = mime_content_type($filePath) ?: 'image/png';
    $headers = ['Content-Type: multipart/form-data'];
    if ($token !== '') $headers[] = 'X-KND-TOKEN: ' . $token;

    $tryPaths = ['/api/upload/image', '/upload/image', '/api/upload'];
    $lastErr = '';
    foreach ($tryPaths as $path) {
        $url = $base . $path;
        $cfile = new \CURLFile($filePath, $mime, basename($filePath));
        $postFields = ['image' => $cfile, 'type' => 'input'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        logWorker("ComfyUI upload: $url HTTP $code" . ($err ? " err=$err" : ''));
        if ($err) {
            $lastErr = $err;
            continue;
        }
        if ($body !== false && $code >= 200 && $code < 300) {
            $data = json_decode($body, true);
            $name = (is_array($data) && !empty($data['name'])) ? $data['name'] : ($data['filename'] ?? null);
            if ($name !== null && $name !== '') return $name;
            $lastErr = 'no filename in response';
            continue;
        }
        $lastErr = 'HTTP ' . $code;
    }
    throw new \RuntimeException('ComfyUI upload failed: ' . ($lastErr ?: 'no response'));
}

function comfyui_get_history_standalone(string $promptId, string $baseUrl, string $token = ''): ?array {
    $base = rtrim($baseUrl, '/');
    $url = $base . '/history/' . urlencode($promptId);
    $headers = ['Accept: application/json'];
    if ($token !== '') $headers[] = 'X-KND-TOKEN: ' . $token;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) {
        logWorker("ComfyUI history curl error: $err (HTTP $code)");
        return null;
    }
    if (!$body) return null;
    $data = json_decode($body, true);
    $hist = is_array($data) && isset($data[$promptId]) ? $data[$promptId] : null;
    return $hist;
}

if (empty($cfg['WORKER_TOKEN']) || empty($cfg['API_BASE'])) {
    logWorker('ERROR: Missing required .env values KND_API_BASE or KND_WORKER_TOKEN');
    exit(1);
}

$apiBase = rtrim($cfg['API_BASE'], '/');
$workerToken = $cfg['WORKER_TOKEN'];
// Use strict .env values (no hardcoded fallback URLs).
$comfyBase = rtrim((string) ($cfg['COMFYUI_BASE'] ?? ''), '/');
$comfy3dRaw = $cfg['COMFYUI_3D_URL'] ?? $cfg['COMFYUI_BASE'] ?? '';
$comfyBase3d = rtrim((string) $comfy3dRaw, '/');
$comfyToken = $cfg['COMFYUI_TOKEN'] ?? '';
$comfyOutputDir = $cfg['COMFY_OUTPUT_DIR'] ?? '';
$kndFinalImageDir = $cfg['KND_FINAL_IMAGE_DIR'] ?? '';
if ($kndFinalImageDir !== '') $kndFinalImageDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $kndFinalImageDir), DIRECTORY_SEPARATOR);
$headers = ['X-KND-WORKER-TOKEN: ' . $workerToken];

do {
    $resp = httpPost($apiBase . '/api/labs/queue/lease.php', $headers, ['worker_id' => $workerId]);
    if (!$resp['ok']) {
        logWorker('lease failed: ' . ($resp['error'] ?? 'unknown'));
        if ($loop) sleep($sleepSec);
        continue;
    }
    $job = $resp['job'] ?? null;
    if (!$job) {
        if ($loop) {
            logWorker('No jobs in queue');
            sleep($sleepSec);
            continue;
        }
        exit(0);
    }

    $jobId = (int) $job['id'];
    $payload = $job['payload'] ?? [];
    $tool = $payload['tool'] ?? 'text2img';
    $attempts = (int) ($job['attempts'] ?? 1);

    $allowedTools = ['text2img', 'img2img', 'remove-bg', 'texture', 'texture_seamless', 'texture_image', 'texture_ultra', 'upscale', 'consistency', '3d_fast', '3d_premium', '3d_vertex', 'character'];
    if (!in_array($tool, $allowedTools, true)) {
        httpPost($apiBase . '/api/labs/queue/fail.php', $headers, [
            'job_id' => $jobId,
            'error_message' => 'Unknown tool: ' . $tool,
            'no_retry' => '1',
        ]);
        logWorker("Job $jobId: unknown tool $tool");
        if (!$loop) break;
        continue;
    }

    if (empty($payload)) {
        httpPost($apiBase . '/api/labs/queue/fail.php', $headers, [
            'job_id' => $jobId,
            'error_message' => 'Invalid payload',
        ]);
        logWorker("Job $jobId: invalid payload");
        if (!$loop) break;
        continue;
    }

    $payload['job_id'] = $jobId;

    $model = $payload['model'] ?? 'juggernaut_v8';
    $refinerEnabled = !empty($payload['refiner_enabled']);
    $overrideCkpt = $payload['override_ckpt'] ?? null;

    $jobComfyBase = ($tool === '3d_vertex') ? $comfyBase3d : $comfyBase;
    if (($tool === 'upscale' || $tool === 'remove-bg' || $tool === '3d_vertex') && !empty($payload['image_url'])) {
        $imgData = workerDownloadFile($payload['image_url'], $headers);
        if (!$imgData) {
            httpPost($apiBase . '/api/labs/queue/fail.php', $headers, [
                'job_id' => $jobId,
            'error_message' => 'Could not download source image',
                'no_retry' => '1',
            ]);
            logWorker("Job $jobId: download failed");
            if (!$loop) break;
            sleep($sleepSec);
            continue;
        }
        $tmpFile = tempnam(sys_get_temp_dir(), 'knd_up');
        if (!$tmpFile || file_put_contents($tmpFile, $imgData) === false) {
            if ($tmpFile) @unlink($tmpFile);
            httpPost($apiBase . '/api/labs/queue/fail.php', $headers, [
                'job_id' => $jobId,
            'error_message' => 'Could not save temp image',
                'no_retry' => '1',
            ]);
            logWorker("Job $jobId: temp save failed");
            if (!$loop) break;
            sleep($sleepSec);
            continue;
        }
        try {
            $payload['image_filename'] = workerUploadToComfyui($tmpFile, $jobComfyBase, $comfyToken);
        } finally {
            @unlink($tmpFile);
        }
    }

    if (($tool === 'img2img' || $tool === 'texture_image') && !empty($payload['image_url'])) {
        $imgData = workerDownloadFile($payload['image_url'], $headers);
        if (!$imgData) {
            httpPost($apiBase . '/api/labs/queue/fail.php', $headers, [
                'job_id' => $jobId,
                'error_message' => 'Could not download source image',
                'no_retry' => '1',
            ]);
            logWorker("Job $jobId: image download failed");
            if (!$loop) break;
            sleep($sleepSec);
            continue;
        }
        $tmpFile = tempnam(sys_get_temp_dir(), 'knd_img');
        if ($tmpFile && file_put_contents($tmpFile, $imgData) !== false) {
            try {
                $payload['image_filename'] = workerUploadToComfyui($tmpFile, $jobComfyBase, $comfyToken);
            } finally {
                @unlink($tmpFile);
            }
        }
    }

    $refImageUrl = $payload['ref_image_url'] ?? $payload['reference_image_url'] ?? '';
    if ($tool === 'consistency') {
        if ($refImageUrl === '') {
            $refImageUrl = $apiBase . '/api/labs/tmp_image.php?job_id=' . $jobId . '&slot=ref';
            logWorker("Job $jobId: consistency ref_image_url missing in payload, using default: $refImageUrl");
        }
        if ($refImageUrl !== '') {
            $imgData = workerDownloadFile($refImageUrl, $headers);
            if (!$imgData) {
                httpPost($apiBase . '/api/labs/queue/fail.php', $headers, [
                    'job_id' => $jobId,
                    'error_message' => 'Could not download reference image from ' . parse_url($refImageUrl, PHP_URL_HOST) . ' (check worker can reach the site)',
                    'no_retry' => '1',
                ]);
                logWorker("Job $jobId: reference download failed (url=" . substr($refImageUrl, 0, 80) . '...)');
                if (!$loop) break;
                sleep($sleepSec);
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'knd_ref');
            if ($tmp && file_put_contents($tmp, $imgData) !== false) {
                try {
                    $payload['reference_image_filename'] = workerUploadToComfyui($tmp, $jobComfyBase, $comfyToken);
                } finally {
                    @unlink($tmp);
                }
            }
            if (empty($payload['reference_image_filename'])) {
                httpPost($apiBase . '/api/labs/queue/fail.php', $headers, [
                    'job_id' => $jobId,
                    'error_message' => 'Could not upload reference image to ComfyUI (upload returned 404 or error)',
                    'no_retry' => '1',
                ]);
                logWorker("Job $jobId: reference upload to ComfyUI failed");
                if (!$loop) break;
                sleep($sleepSec);
                continue;
            }
        }
    }

    if ($tool === 'consistency' && empty($payload['reference_image_filename'])) {
        logWorker("Job $jobId: consistency payload keys: " . implode(',', array_keys($payload)));
        httpPost($apiBase . '/api/labs/queue/fail.php', $headers, [
            'job_id' => $jobId,
            'error_message' => 'Consistency job requires a reference image. Create the job from the Consistency page (upload or select from recent).',
            'no_retry' => '1',
        ]);
        logWorker("Job $jobId: consistency job has no reference_image_filename and no ref_image_url");
        if (!$loop) break;
        sleep($sleepSec);
        continue;
    }

    try {
        switch ($tool) {
            case 'text2img':
            case 'character':
                $workflowFile = 'knd-workflow-api.json';
                break;
            case 'img2img':
                $workflowFile = 'knd-workflow-api2.json';
                break;
            case 'remove-bg':
                $workflowFile = 'remove_bg.json';
                break;
            case 'texture':
            case 'texture_seamless':
                $workflowFile = 'texture_generate_pro.json';
                break;
            case 'texture_image':
                $workflowFile = 'texture_from_image_pro.json';
                break;
            case 'texture_ultra':
                $workflowFile = 'texture_ultra_pro.json';
                break;
            case 'upscale':
                $workflowFile = 'upscale_api.json';
                break;
            case 'consistency':
                $workflowFile = 'consistency_api.json';
                break;
            case '3d_fast':
                $workflowFile = '3d_fast.json';
                break;
            case '3d_premium':
                $workflowFile = '3d_premium.json';
                break;
            case '3d_vertex':
                $workflowFile = 'KND Character Lab.json';
                break;
            default:
                throw new \Exception('Unknown tool: ' . $tool);
        }

        $workflowPath = comfyui_workflow_path($tool, $payload);
        if (!is_readable($workflowPath)) {
            throw new \RuntimeException('Workflow file not found: ' . $workflowFile);
        }
        $workflow = json_decode(file_get_contents($workflowPath), true);
        if (!is_array($workflow)) {
            throw new \RuntimeException('Invalid workflow JSON: ' . $workflowFile);
        }
        comfyui_inject_workflow_params($workflow, $payload, $tool);

        if ($tool === 'upscale') {
            // upscale: no checkpoint/ipadapter
        } elseif ($tool === 'remove-bg') {
            // remove-bg: dedicated workflow, no checkpoint/ipadapter
        } elseif ($tool === 'consistency') {
            // consistency: workflow already has checkpoint in params
        } elseif (in_array($tool, ['texture', 'texture_image', 'texture_ultra'], true)) {
            // texture: uses own checkpoint in workflow JSON, no IPAdapter/ControlNet
        } elseif (in_array($tool, ['3d_fast', '3d_premium', '3d_vertex'], true)) {
            // 3D: workflow has its own models
        } else {
            comfyui_apply_checkpoint($workflow, $model, $refinerEnabled, $overrideCkpt);
            $controlFilename = null;
            $refFilename = null;
            $ipEnabled = !empty($payload['ipadapter']['enabled']);
            $cnEnabled = !empty($payload['controlnet']['enabled']);
            if ($cnEnabled && !empty($payload['controlnet']['control_image_url'])) {
                $imgData = workerDownloadFile($payload['controlnet']['control_image_url'], $headers);
                if (!$imgData) throw new \RuntimeException('Could not download ControlNet control image.');
                $tmp = tempnam(sys_get_temp_dir(), 'knd_ctrl');
                if (!$tmp || file_put_contents($tmp, $imgData) === false) throw new \RuntimeException('Could not save control image.');
                try {
                    $controlFilename = workerUploadToComfyui($tmp, $jobComfyBase, $comfyToken);
                } finally {
                    @unlink($tmp);
                }
            }
            if ($ipEnabled && !empty($payload['ipadapter']['ref_image_url'])) {
                $imgData = workerDownloadFile($payload['ipadapter']['ref_image_url'], $headers);
                if (!$imgData) throw new \RuntimeException('Could not download IPAdapter reference image.');
                $tmp = tempnam(sys_get_temp_dir(), 'knd_ref');
                if (!$tmp || file_put_contents($tmp, $imgData) === false) throw new \RuntimeException('Could not save reference image.');
                try {
                    $refFilename = workerUploadToComfyui($tmp, $jobComfyBase, $comfyToken);
                } finally {
                    @unlink($tmp);
                }
            }
            if (!$controlFilename) {
                $ph = workerCreatePlaceholderImage();
                try {
                    $controlFilename = workerUploadToComfyui($ph, $jobComfyBase, $comfyToken);
                } finally {
                    @unlink($ph);
                }
            }
            if (!$refFilename) {
                $ph = workerCreatePlaceholderImage();
                try {
                    $refFilename = workerUploadToComfyui($ph, $jobComfyBase, $comfyToken);
                } finally {
                    @unlink($ph);
                }
            }
            comfyui_apply_ipadapter_controlnet($workflow, $payload, $controlFilename, $refFilename);
        }
        $debugDir = $projectRoot . '/workers/_debug';
        if (is_dir($debugDir) || @mkdir($debugDir, 0755, true)) {
            @file_put_contents($debugDir . '/last_workflow.json', json_encode($workflow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        $result = comfyui_run_prompt($workflow, $jobComfyBase, $comfyToken);
        $promptId = $result['prompt_id'];
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        logWorker("Job $jobId ComfyUI send failed: " . $msg);
        $failData = ['job_id' => $jobId, 'error_message' => $msg];
        $isValidation = (stripos($msg, 'prompt_outputs_failed_validation') !== false || stripos($msg, '400') !== false || stripos($msg, 'Value not in list') !== false || stripos($msg, 'Workflow validation failed') !== false || stripos($msg, 'is not allowed') !== false || stripos($msg, 'SDXL-only') !== false);
        if ($isValidation) {
            $failData['no_retry'] = '1';
        }
        httpPost($apiBase . '/api/labs/queue/fail.php', $headers, $failData);
        if (!$loop) break;
        sleep($sleepSec);
        continue;
    }

    logWorker("Job $jobId: ComfyUI prompt_id=$promptId, polling $jobComfyBase/history/...");
    $maxPoll = 120;
    $pollInterval = 1;
    $imageUrl = null;
    $outputFilename = null;
    $outputSubfolder = '';
    $outputType = 'output';
    $pollError = null;
    $lastHistDebug = null;
    for ($i = 0; $i < $maxPoll; $i++) {
        sleep($pollInterval);
        $hist = comfyui_get_history_standalone($promptId, $jobComfyBase, $comfyToken);
        if (!$hist) continue;
        if (isset($hist['status']['status_str']) && $hist['status']['status_str'] === 'error') {
            $err = $hist['status']['messages'] ?? 'ComfyUI error';
            $pollError = is_string($err) ? $err : json_encode($err);
            break;
        }
        $outputs = $hist['outputs'] ?? [];
        if (!is_array($outputs)) $outputs = [];
        $bucketOrder = ($tool === '3d_vertex')
            ? ['meshes', 'mesh', 'files', 'glbs', 'images', 'gifs']
            : ['images', 'gifs', 'meshes', 'mesh', 'files', 'glbs'];
        foreach ($outputs as $nodeOut) {
            if (!is_array($nodeOut)) continue;
            foreach ($bucketOrder as $key) {
                if (!isset($nodeOut[$key]) || !is_array($nodeOut[$key])) continue;
                foreach ($nodeOut[$key] as $img) {
                    if (is_array($img) && !empty($img['filename'])) {
                        $candidate = (string) $img['filename'];
                        if ($tool === '3d_vertex' && strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION)) !== 'glb' && in_array($key, ['images', 'gifs'], true)) {
                            continue;
                        }
                        $outputFilename = $img['filename'];
                        $outputSubfolder = $img['subfolder'] ?? '';
                        $outputType = $img['type'] ?? 'output';
                        $imageUrl = '/api/labs/image.php?job_id=' . $jobId;
                        break 4;
                    }
                }
            }
        }
        if ($imageUrl !== null) {
            logWorker("Job $jobId: outputs detected filename=$outputFilename subfolder=$outputSubfolder");
            break;
        }
        $lastHistDebug = isset($hist['outputs']) ? 'outputs=' . json_encode($hist['outputs'], JSON_UNESCAPED_UNICODE) : 'no outputs';
    }
    if ($imageUrl === null && $lastHistDebug !== null && !$pollError) {
        $preview = strlen($lastHistDebug) > 600 ? substr($lastHistDebug, 0, 600) . '...' : $lastHistDebug;
        logWorker("Job $jobId: history debug: $preview");
    }

    $outputPathRel = null;
    $imageBytes = null;
    $imageSource = '';

    if ($outputFilename !== null) {
        if ($comfyOutputDir !== '') {
            $comfyOut = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $comfyOutputDir), DIRECTORY_SEPARATOR);
            $srcPath = $outputSubfolder !== ''
                ? $comfyOut . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $outputSubfolder) . DIRECTORY_SEPARATOR . $outputFilename
                : $comfyOut . DIRECTORY_SEPARATOR . $outputFilename;
            if (is_file($srcPath) && is_readable($srcPath)) {
                $imageBytes = file_get_contents($srcPath);
                $imageSource = 'disk:' . $srcPath;
            } else {
                logWorker("Job $jobId: output not on disk at $srcPath, trying job_ID_tool name");
                // Fallback: ComfyUI or another process may have written job_131_upscale.png directly
                $altBase = $comfyOut . DIRECTORY_SEPARATOR . 'job_' . $jobId . '_' . $tool . '.';
                foreach (['png', 'jpg', 'jpeg', 'webp'] as $e) {
                    $altPath = $altBase . $e;
                    if (is_file($altPath) && is_readable($altPath)) {
                        $imageBytes = file_get_contents($altPath);
                        $imageSource = 'disk:' . $altPath;
                        logWorker("Job $jobId: found output at $altPath");
                        break;
                    }
                }
                if ($imageBytes === null) {
                    logWorker("Job $jobId: output not on disk, fetching from ComfyUI /view");
                }
            }
        }
        if ($imageBytes === null) {
            $imageBytes = workerFetchComfyOutputBytes($jobComfyBase, (string) $outputFilename, (string) $outputSubfolder, (string) $outputType, $comfyToken);
            if ($imageBytes === null) {
                $imageBytes = comfyui_fetch_output_image_bytes($promptId, $jobComfyBase, $comfyToken);
            }
            $imageSource = $imageSource ?: 'view:' . $jobComfyBase . '/view';
        }
    }

    if ($imageBytes !== null && $imageBytes !== '') {
        $ext = strtolower((string) pathinfo((string) $outputFilename, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'png';
            if (preg_match('/^\x89PNG/', $imageBytes)) $ext = 'png';
            elseif (preg_match('/^\xff\xd8\xff/', $imageBytes)) $ext = 'jpg';
            elseif (substr($imageBytes, 0, 4) === 'RIFF' && substr($imageBytes, 8, 4) === 'WEBP') $ext = 'webp';
            elseif (substr($imageBytes, 0, 4) === 'glTF') $ext = 'glb';
        }
        $size = strlen($imageBytes);
        if ($size === 0) {
            logWorker("Job $jobId: image bytes empty, cannot save");
        } else {
            $labsDir = defined('LABS_UPLOAD_DIR') ? LABS_UPLOAD_DIR : 'uploads/labs';
            $destRel = $labsDir . '/job_' . $jobId . '_' . $tool . '.' . $ext;
            $destFull = storage_path($destRel);
            $destDir = dirname($destFull);
            if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
            if (is_dir($destDir) && @file_put_contents($destFull, $imageBytes) !== false) {
                $outputPathRel = $destRel;
                logWorker("Job $jobId: saved output to $destRel (source: $imageSource, size: $size bytes)");

                if ($kndFinalImageDir !== '' && is_dir($kndFinalImageDir) && is_writable($kndFinalImageDir)) {
                    $finalName = 'job_' . $jobId . '_' . $tool . '.' . $ext;
                    $finalPath = $kndFinalImageDir . DIRECTORY_SEPARATOR . $finalName;
                    if (@file_put_contents($finalPath, $imageBytes) !== false) {
                        logWorker("Job $jobId: copied to KND_FINAL_IMAGE_DIR: $finalPath");
                    } else {
                        logWorker("Job $jobId: failed to copy to KND_FINAL_IMAGE_DIR: $finalPath");
                    }
                } elseif ($kndFinalImageDir !== '' && !is_dir($kndFinalImageDir)) {
                    logWorker("Job $jobId: KND_FINAL_IMAGE_DIR not a directory or missing: $kndFinalImageDir");
                }
            } else {
                logWorker("Job $jobId: failed to write storage output: $destFull");
            }
        }
    } elseif ($outputFilename !== null) {
        logWorker("Job $jobId: could not obtain image bytes (output_filename=$outputFilename, subfolder=$outputSubfolder)");
    }

    if ($pollError) {
        httpPost($apiBase . '/api/labs/queue/fail.php', $headers, [
            'job_id' => $jobId,
            'error_message' => $pollError,
        ]);
        logWorker("Job $jobId failed: $pollError");
    } elseif ($imageUrl) {
        $pathForComplete = null;
        if ($imageBytes !== null && $imageBytes !== '' && isset($ext) && in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $uploaded = workerUploadOutputImage($apiBase, $headers, $jobId, $tool, $imageBytes, $ext);
            if ($uploaded !== null) {
                $pathForComplete = $uploaded;
                logWorker("Job $jobId: uploaded output to server (recent/download will work)");
            } else {
                logWorker("Job $jobId: upload output to server failed; server will use fallback (KND_FINAL_IMAGE_DIR) if configured");
            }
        }
        $postData = [
            'job_id' => $jobId,
            'comfy_prompt_id' => $promptId,
            'image_url' => $imageUrl,
        ];
        if ($pathForComplete !== null) {
            $postData['output_path'] = str_replace('\\', '/', $pathForComplete);
        } elseif ($outputPathRel !== null) {
            $postData['output_path'] = str_replace('\\', '/', $outputPathRel);
        }
        $r = httpPost($apiBase . '/api/labs/queue/complete.php', $headers, $postData);
        if ($r['ok']) {
            logWorker("Job $jobId: done");
        } else {
            logWorker("Job $jobId complete failed: " . ($r['error'] ?? ''));
        }
    } elseif (!$pollError) {
        $errMsg = 'ComfyUI timeout: no image after ' . ($maxPoll * $pollInterval) . 's';
        httpPost($apiBase . '/api/labs/queue/fail.php', $headers, [
            'job_id' => $jobId,
            'error_message' => $errMsg,
        ]);
        logWorker("Job $jobId: $errMsg");
    }

    if (!$loop) break;
    sleep($sleepSec);

} while ($loop);
