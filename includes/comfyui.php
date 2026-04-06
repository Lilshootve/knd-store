<?php
/**
 * KND Labs - ComfyUI workflow injection and API helpers
 */

if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config/bootstrap.php';
}

if (!function_exists('array_is_list')) {
    /** @param array $arr */
    function array_is_list($arr): bool {
        if (!is_array($arr)) return false;
        if ($arr === []) return true;
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

/**
 * Upload image to ComfyUI and return filename.
 * @param string $filePath Path to image file
 * @param string|null $baseUrl Override base URL (from provider)
 * @param string $token Optional X-KND-TOKEN for auth
 */
function comfyui_upload_image(string $filePath, ?string $baseUrl = null, string $token = ''): string {
    if ($baseUrl === null && file_exists(BASE_PATH . '/config/comfyui.php')) {
        require_once BASE_PATH . '/config/comfyui.php';
        $baseUrl = defined('COMFYUI_BASE_URL') ? rtrim(COMFYUI_BASE_URL, '/') : '';
    }
    $base = $baseUrl ? rtrim($baseUrl, '/') : '';
    if ($base === '') throw new \RuntimeException('ComfyUI config not found');

    $mime = mime_content_type($filePath) ?: 'image/png';
    $headers = ['Content-Type: multipart/form-data'];
    if ($token !== '') $headers[] = 'X-KND-TOKEN: ' . $token;

    $tryPaths = ['/api/upload/image', '/upload/image', '/api/upload'];
    $body = null;
    $lastErr = '';
    foreach ($tryPaths as $path) {
        $url = $base . $path;
        $cfile = new \CURLFile($filePath, $mime, basename($filePath));
        $postFields = ['image' => $cfile, 'type' => 'input'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) {
            $lastErr = $err;
            continue;
        }
        if ($body !== false && $code >= 200 && $code < 300) break;
        $lastErr = 'HTTP ' . $code;
    }
    if ($body === false || $body === '') {
        error_log('ComfyUI upload failed: base=' . $base . ' lastErr=' . $lastErr);
        throw new \RuntimeException('ComfyUI upload failed: ' . ($lastErr ?: 'no response'));
    }
    $data = is_string($body) ? json_decode($body, true) : $body;
    if (!is_array($data)) {
        $preview = is_string($body) ? substr($body, 0, 200) : '';
        error_log('ComfyUI upload invalid JSON: base=' . $base . ' preview=' . $preview);
        throw new \RuntimeException('ComfyUI upload: invalid JSON response. ' . $preview);
    }
    $name = $data['name'] ?? $data['filename'] ?? null;
    if (empty($name) || !is_string($name)) {
        $preview = json_encode($data);
        if (strlen($preview) > 250) $preview = substr($preview, 0, 250) . '...';
        error_log('ComfyUI upload no filename: base=' . $base . ' response=' . $preview);
        throw new \RuntimeException('ComfyUI upload: no filename returned. Response: ' . $preview);
    }
    return $name;
}

/** Tool → workflow filename (single ComfyUI instance). */
const COMFYUI_TOOL_WORKFLOW_MAP = [
    'text2img'         => 'knd-workflow-api.json',
    'img2img'          => 'knd-workflow-api2.json',
    'remove-bg'        => 'remove_bg.json',
    'texture'          => 'texture_generate_pro.json',
    'texture_seamless' => 'texture_generate_pro.json',
    'texture_image'    => 'texture_from_image_pro.json',
    'texture_ultra'    => 'texture_ultra_pro.json',
    'upscale'          => 'upscale_api.json',
    'consistency'      => 'consistency_api.json',
    '3d_fast'          => '3d_fast.json',
    '3d_premium'       => '3d_premium.json',
    '3d_vertex'        => 'KND Character Lab.json',
    'character'        => 'knd-workflow-api.json',
];

/**
 * Get workflow filename for a tool (for worker dispatcher).
 */
function comfyui_workflow_filename_for_tool(string $tool, array $params = []): string {
    if ($tool === 'img2img') {
        return COMFYUI_TOOL_WORKFLOW_MAP['img2img'] ?? 'knd-workflow-api2.json';
    }
    return COMFYUI_TOOL_WORKFLOW_MAP[$tool] ?? COMFYUI_TOOL_WORKFLOW_MAP['text2img'];
}

/**
 * Load workflow file path by tool.
 */
function comfyui_workflow_path(string $tool, array $params = []): string {
    $baseDir = defined('WORKFLOWS_DIR') ? rtrim(WORKFLOWS_DIR, '/\\') : (BASE_PATH . '/workflows');
    $filename = comfyui_workflow_filename_for_tool($tool, $params);
    $path = $baseDir . '/' . $filename;
    if (!is_readable($path)) {
        if ($filename === '3d_fast.json') $path = $baseDir . '/generate fast 3d.json';
        if ($filename === '3d_premium.json') $path = $baseDir . '/3d premium.json';
        if ($filename === 'KND Character Lab.json') $path = BASE_PATH . '/comfy-router/workflows/KND Character Lab.json';
        if ($filename === 'knd-workflow-api2.json') $path = $baseDir . '/knd-workflow-api.json';
    }
    if (!is_readable($path)) {
        $path = $baseDir . '/knd-workflow-api.json';
    }
    return $path;
}

/**
 * Inject parameters into an already-loaded workflow (for worker dispatcher).
 * Modifies $wf by reference. Sets prompt, negative_prompt, seed, steps, cfg, width, height, image_filename, filename_prefix.
 */
function comfyui_inject_workflow_params(array &$wf, array $params, string $tool = 'text2img'): void {
    $prompt = $params['prompt'] ?? '';
    if ($tool === 'consistency') {
        $base = trim($params['base_prompt'] ?? '');
        $scene = trim($params['scene_prompt'] ?? '');
        $prompt = $base !== '' && $scene !== '' ? $base . ', ' . $scene : ($base ?: $scene ?: 'high quality image');
    }
    $textureTools = ['texture', 'texture_image', 'texture_ultra'];
    if (in_array($tool, $textureTools, true)) {
        $seamless = !empty($params['seamless']);
        $prompt = trim($prompt) ?: 'game texture, detailed surface';
        if ($seamless) {
            $prompt = 'seamless, tileable, ' . $prompt;
        }
    }
    $negative = $params['negative_prompt'] ?? 'ugly, blurry, low quality';
    $seed = isset($params['seed']) ? (int) $params['seed'] : random_int(0, 2147483647);
    $steps = (int) ($params['steps'] ?? 30);
    $steps = max(1, min(100, $steps));
    $cfg = (float) ($params['cfg'] ?? 6);
    $cfg = max(1, min(30, $cfg));
    $width = (int) ($params['width'] ?? 512);
    $height = (int) ($params['height'] ?? 512);
    $width = max(256, min(2048, $width - ($width % 8)));
    $height = max(256, min(2048, $height - ($height % 8)));
    $batchSize = 1;
    $samplerName = $params['sampler_name'] ?? 'dpmpp_2m';
    $scheduler = $params['scheduler'] ?? 'karras';
    $denoise = isset($params['denoise']) ? (float) $params['denoise'] : 1.0;
    $denoise = max(0.01, min(1.0, $denoise));

    $allowedSamplers = ['euler', 'euler_ancestral', 'heun', 'dpm_2', 'dpm_2_ancestral', 'lms', 'dpm_fast', 'dpm_adaptive', 'dpmpp_2s_ancestral', 'dpmpp_sde', 'dpmpp_2m', 'dpmpp_2m_sde', 'dpmpp_3m_sde', 'ddpm', 'lcm', 'ddim', 'uni_pc', 'uni_pc_bh2'];
    if (!in_array($samplerName, $allowedSamplers, true)) $samplerName = 'euler';
    $allowedSchedulers = ['normal', 'karras', 'exponential', 'sgm_uniform', 'simple'];
    if (!in_array($scheduler, $allowedSchedulers, true)) $scheduler = 'normal';

    if ($tool === '3d_vertex') {
        $meshSteps = max(10, min(120, (int) ($params['steps'] ?? 50)));
        $meshGuidance = max(1.0, min(20.0, (float) ($params['cfg'] ?? 7.5)));
        $textureSize = (int) ($params['texture_size'] ?? 2048);
        if (!in_array($textureSize, [1024, 2048], true)) $textureSize = 2048;
        $maxFaces = max(50000, min(500000, (int) ($params['max_faces'] ?? 200000)));
        $quality = strtolower(trim((string) ($params['quality'] ?? 'standard')));
        $texSteps = $quality === 'high' ? 40 : 30;
        $texGuidance = $quality === 'high' ? 4.5 : 3.0;
        $prefix = isset($params['job_id']) ? ('knd_3d_vertex/job_' . (int) $params['job_id']) : 'knd_3d_vertex/model';
        $vertexImage = (string) ($params['image_filename'] ?? '');

        foreach ($wf as $nid => $node) {
            if (!is_array($node) || empty($node['class_type'])) continue;
            $ctype = $node['class_type'];
            $inputs = &$wf[$nid]['inputs'];
            if (!is_array($inputs)) $inputs = [];

            if (($ctype === 'LoadImage' || $ctype === 'AILab_LoadImage' || $ctype === 'Hy3D21LoadImageWithTransparency') && $vertexImage !== '') {
                if (isset($inputs['image'])) $inputs['image'] = $vertexImage;
            }
            if ($ctype === 'Hy3DMeshGenerator') {
                $inputs['steps'] = $meshSteps;
                $inputs['guidance_scale'] = $meshGuidance;
                $inputs['seed'] = $seed;
            }
            if ($ctype === 'Hy3DMultiViewsGenerator') {
                $inputs['steps'] = $texSteps;
                $inputs['guidance_scale'] = $texGuidance;
                $inputs['texture_size'] = $textureSize;
                $inputs['seed'] = $seed;
            }
            if ($ctype === 'INTConstant' && isset($inputs['value'])) {
                $inputs['value'] = $maxFaces;
            }
            if ($ctype === 'StringConstant' && isset($inputs['string'])) {
                $inputs['string'] = $prefix;
            }
            if ($ctype === 'Hy3D21ExportMesh' && isset($inputs['filename_prefix'])) {
                $inputs['filename_prefix'] = $prefix;
                if (isset($inputs['file_format'])) $inputs['file_format'] = 'glb';
            }
            if ($ctype === 'Preview3D' && isset($inputs['image']) && $vertexImage !== '') {
                $inputs['image'] = $vertexImage;
            }
            if ($ctype === 'SaveImage' && isset($params['job_id'])) {
                $inputs['filename_prefix'] = $prefix . '_preview';
            }
        }
        return;
    }

    $injectedPositive = false;
    foreach ($wf as $nid => $node) {
        if (!is_array($node) || empty($node['class_type'])) continue;
        $ctype = $node['class_type'];
        $inputs = &$wf[$nid]['inputs'];
        if (!is_array($inputs)) $inputs = [];

        if ($ctype === 'CLIPTextEncode' && isset($inputs['text'])) {
            if (!$injectedPositive) {
                $inputs['text'] = $prompt;
                $injectedPositive = true;
            } else {
                $inputs['text'] = $negative;
            }
        }
        if (in_array($ctype, ['KSampler', 'KSamplerAdvanced'], true)) {
            $inputs['seed'] = $seed;
            $inputs['steps'] = $steps;
            $inputs['cfg'] = $cfg;
            $inputs['denoise'] = $denoise;
            if (isset($inputs['sampler_name'])) $inputs['sampler_name'] = $samplerName;
            if (isset($inputs['scheduler'])) $inputs['scheduler'] = $scheduler;
        }
        if ($ctype === 'EmptyLatentImage') {
            $inputs['width'] = $width;
            $inputs['height'] = $height;
            $inputs['batch_size'] = 1;
        }
        if (array_key_exists('batch_size', $inputs)) {
            $inputs['batch_size'] = 1;
        }
        if ($ctype === 'LoadImage' || $ctype === 'AILab_LoadImage') {
            if (!empty($params['image_filename'])) {
                $inputs['image'] = $params['image_filename'];
                if (isset($inputs['image_path_or_URL'])) {
                    $inputs['image_path_or_URL'] = '';
                }
            } elseif ($tool === 'consistency' && !empty($params['reference_image_filename'])) {
                $inputs['image'] = $params['reference_image_filename'];
            }
        }
        if (in_array($ctype, ['KSampler', 'KSamplerAdvanced'], true) && in_array($tool, $textureTools, true) && isset($params['denoise'])) {
            $d = (float) $params['denoise'];
            if (!empty($params['image_filename'])) {
                $d = max(0.4, min(0.95, $d));
            }
            $inputs['denoise'] = $d;
        }
        if ($ctype === 'CheckpointLoaderSimple' && $tool === 'consistency' && !empty($params['model_ckpt'])) {
            $inputs['ckpt_name'] = $params['model_ckpt'];
        }
        if ($ctype === 'UpscaleModelLoader' && $tool === 'upscale') {
            $model = $params['upscale_model'] ?? '4x-UltraSharp.pth';
            $inputs['model_name'] = comfyui_normalize_upscale_model((string) $model);
        }
        if (($ctype === 'SaveImage' || $ctype === 'Image Save') && isset($params['job_id'])) {
            $prefix = 'knd_' . preg_replace('/[^a-z0-9_]/', '_', $tool) . '/job_' . (int) $params['job_id'];
            $inputs['filename_prefix'] = $prefix;
            if (isset($inputs['output_path']) && is_string($inputs['output_path']) && $inputs['output_path'] !== '') {
                $inputs['output_path'] = '';
            }
        }
    }
}

/**
 * Load master workflow and inject parameters.
 * @param array $params prompt, negative_prompt, seed, steps, cfg, width, height, image_filename, job_id, etc.
 * @param string $tool text2img|img2img|texture|texture_image|texture_ultra|upscale|consistency|3d_fast|3d_premium|character
 * @return array workflow for ComfyUI API
 */
function comfyui_inject_workflow(array $params, string $tool = 'text2img'): array {
    $path = comfyui_workflow_path($tool, $params);
    if (!is_readable($path)) {
        throw new \RuntimeException('Workflow file not found: ' . basename($path));
    }
    $wf = json_decode(file_get_contents($path), true);
    if (!is_array($wf)) {
        throw new \RuntimeException('Invalid workflow JSON');
    }
    comfyui_inject_workflow_params($wf, $params, $tool);
    return $wf;
}

/**
 * Apply IPAdapter and ControlNet params to workflow. Only modifies node.inputs.
 * Node mapping: 31=LoadImage (control), 32=LoadImage (ref), 15=ControlNetApplyAdvanced, 23=IPAdapterAdvanced.
 * @param array $workflow Workflow by reference
 * @param array $params Payload with ipadapter/controlnet objects when enabled
 * @param string|null $controlImageFilename Filename in ComfyUI input (from upload). Required for node 31.
 * @param string|null $refImageFilename Filename in ComfyUI input. Required for node 32.
 */
function comfyui_apply_ipadapter_controlnet(array &$workflow, array $params, ?string $controlImageFilename, ?string $refImageFilename): void {
    $ip = $params['ipadapter'] ?? null;
    $cn = $params['controlnet'] ?? null;
    $ipEnabled = is_array($ip) && !empty($ip['enabled']);
    $cnEnabled = is_array($cn) && !empty($cn['enabled']);

    if (isset($workflow['31']) && is_array($workflow['31']['inputs'] ?? null)) {
        $workflow['31']['inputs']['image'] = $controlImageFilename ?? 'placeholder_control.png';
    }
    if (isset($workflow['32']) && is_array($workflow['32']['inputs'] ?? null)) {
        $workflow['32']['inputs']['image'] = $refImageFilename ?? 'placeholder_ref.png';
    }

    if (isset($workflow['15']) && is_array($workflow['15']['inputs'] ?? null)) {
        $s = $cnEnabled ? (float) ($cn['strength'] ?? 0.75) : 0;
        $s = max(0, min(1.20, $s));
        $start = $cnEnabled ? (float) ($cn['start_at'] ?? 0) : 0;
        $end = $cnEnabled ? (float) ($cn['end_at'] ?? 0.80) : 0;
        $workflow['15']['inputs']['strength'] = $s;
        $workflow['15']['inputs']['start_percent'] = max(0, min(1, $start));
        $workflow['15']['inputs']['end_percent'] = max(0, min(1, $end));
    }

    if (isset($workflow['23']) && is_array($workflow['23']['inputs'] ?? null)) {
        $w = $ipEnabled ? (float) ($ip['weight'] ?? 0.70) : 0;
        $w = max(0, min(1.20, $w));
        $start = $ipEnabled ? (float) ($ip['start_at'] ?? 0) : 0;
        $end = $ipEnabled ? (float) ($ip['end_at'] ?? 1) : 0;
        $workflow['23']['inputs']['weight'] = $w;
        $workflow['23']['inputs']['start_at'] = max(0, min(1, $start));
        $workflow['23']['inputs']['end_at'] = max(0, min(1, $end));
    }
}

/**
 * Fetch image bytes from ComfyUI by prompt_id (no DB).
 * Used by worker when output file is not on disk. Returns first image from history outputs.
 * @return string|null raw image bytes or null on failure
 */
function comfyui_fetch_output_image_bytes(string $promptId, string $baseUrl, string $token = ''): ?string {
    $hist = comfyui_get_history($promptId, $baseUrl, $token);
    if (!$hist || !isset($hist['outputs'])) return null;
    $filename = null;
    $subfolder = '';
    $imgType = 'output';
    foreach ($hist['outputs'] as $nodeOut) {
        if (!is_array($nodeOut)) continue;
        foreach (['images', 'gifs'] as $key) {
            if (!isset($nodeOut[$key]) || !is_array($nodeOut[$key])) continue;
            foreach ($nodeOut[$key] as $img) {
                if (!empty($img['filename'])) {
                    $filename = $img['filename'];
                    $subfolder = $img['subfolder'] ?? '';
                    $imgType = $img['type'] ?? 'output';
                    break 3;
                }
            }
        }
    }
    if (!$filename) return null;
    $params = ['filename' => $filename, 'type' => $imgType];
    if ($subfolder !== '') $params['subfolder'] = $subfolder;
    $base = rtrim($baseUrl, '/');
    $headers = ['Accept: image/*'];
    if ($token !== '') $headers[] = 'X-KND-TOKEN: ' . $token;
    foreach (['/view', '/api/view'] as $path) {
        $url = $base . $path . '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $bin = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($bin !== false && $code >= 200 && $code < 300) return $bin;
        error_log('ComfyUI fetch output image: ' . $url . ' HTTP ' . $code);
    }
    return null;
}

/**
 * Fetch image bytes from a completed labs job (for upscale source_type=recent).
 * @return string|null raw image bytes or null on failure
 */
function comfyui_fetch_job_image_bytes(PDO $pdo, int $jobId, int $userId): ?string {
    $stmt = $pdo->prepare("SELECT comfy_prompt_id, provider FROM knd_labs_jobs WHERE id = ? AND user_id = ? AND status = 'done' LIMIT 1");
    if (!$stmt || !$stmt->execute([$jobId, $userId])) return null;
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job || empty($job['comfy_prompt_id'])) return null;
    require_once __DIR__ . '/comfyui_provider.php';
    $baseUrl = ($job['provider'] ?? '') === 'runpod'
        ? comfyui_get_base_url_runpod($pdo) : comfyui_get_base_url_local($pdo);
    if (!$baseUrl) $baseUrl = comfyui_get_base_url($pdo, null);
    $token = comfyui_get_token($pdo);
    return comfyui_fetch_output_image_bytes($job['comfy_prompt_id'], $baseUrl, $token);
}

/** SDXL-only checkpoint models (KND Labs). SD 1.5 and FLUX excluded. */
const COMFYUI_CHECKPOINT_MAP = [
    'juggernaut_ragnarok' => 'juggernautXL_ragnarokBy.safetensors',
    'juggernaut_v8' => 'juggernautXL_v8Rundiffusion.safetensors',
    'pornmaster_asian' => 'pornmaster_asianSdxlV1VAE.safetensors',
    'sd_xl_base' => 'sd_xl_base_1.0.safetensors',
    'sd_xl_refiner' => 'sd_xl_refiner_1.0.safetensors',
    'waiANINSFWPONY' => 'waiANINSFWPONYXL_v130.safetensors',
    'waiNSFW_v120' => 'waiNSFWIllustrious_v120.safetensors',
    'waiNSFW_v150' => 'waiNSFWIllustrious_v150.safetensors',
];
const COMFYUI_REFINER = 'sd_xl_refiner_1.0.safetensors';
const COMFYUI_DEFAULT_MODEL = 'juggernaut_v8';

/** Allowed SDXL checkpoint filenames (guard). Must match COMFYUI_CHECKPOINT_MAP + refiner. */
const COMFYUI_SDXL_ALLOWED = [
    'juggernautXL_ragnarokBy.safetensors',
    'juggernautXL_v8Rundiffusion.safetensors',
    'pornmaster_asianSdxlV1VAE.safetensors',
    'sd_xl_base_1.0.safetensors',
    'sd_xl_refiner_1.0.safetensors',
    'waiANINSFWPONYXL_v130.safetensors',
    'waiNSFWIllustrious_v120.safetensors',
    'waiNSFWIllustrious_v150.safetensors',
];

/** Throw if checkpoint is not in SDXL allowlist. */
function comfyui_validate_sdxl_checkpoint(string $filename): void {
    $f = trim($filename);
    if ($f === '') return;
    if (!in_array($f, COMFYUI_SDXL_ALLOWED, true)) {
        throw new \InvalidArgumentException('Checkpoint "' . $f . '" is not allowed. KND Labs supports SDXL-only models.');
    }
}

/** Resolve model key or filename to checkpoint. Validates SDXL-only. */
function comfyui_resolve_checkpoint(string $model, ?string $overrideCkpt): string {
    $ckpt = null;
    if ($overrideCkpt !== null && trim($overrideCkpt) !== '') {
        $ckpt = trim($overrideCkpt);
    } elseif (isset(COMFYUI_CHECKPOINT_MAP[$model])) {
        $ckpt = COMFYUI_CHECKPOINT_MAP[$model];
    } elseif (substr(strtolower($model), -13) === '.safetensors') {
        $ckpt = $model;
    } else {
        $ckpt = COMFYUI_CHECKPOINT_MAP[COMFYUI_DEFAULT_MODEL];
    }
    comfyui_validate_sdxl_checkpoint($ckpt);
    return $ckpt;
}

/**
 * Override CheckpointLoaderSimple nodes with valid model.
 * @param array $workflow Workflow by reference
 * @param string $model Key from map or full .safetensors filename
 * @param bool $refinerEnabled Use refiner for second checkpoint node
 * @param string|null $overrideCkpt If set (from settings), use this checkpoint for base model
 */
function comfyui_apply_checkpoint(array &$workflow, string $model = 'juggernaut_v8', bool $refinerEnabled = false, ?string $overrideCkpt = null): void {
    $baseCkpt = comfyui_resolve_checkpoint($model, $overrideCkpt);
    $checkpointNodes = 0;
    foreach ($workflow as $nid => &$node) {
        if (!is_array($node) || ($node['class_type'] ?? '') !== 'CheckpointLoaderSimple') continue;
        $inputs = &$node['inputs'];
        if (!isset($inputs['ckpt_name'])) continue;
        $checkpointNodes++;
        if ($checkpointNodes === 1) {
            $inputs['ckpt_name'] = $baseCkpt;
        } elseif ($refinerEnabled) {
            $inputs['ckpt_name'] = COMFYUI_REFINER;
        } else {
            $inputs['ckpt_name'] = $baseCkpt;
        }
    }
}

/** Map legacy RealESRGAN models to 4x-UltraSharp (only model installed). */
const COMFYUI_UPSCALE_MODEL_MAP = [
    'RealESRGAN_x4plus.pth' => '4x-UltraSharp.pth',
    'RealESRGAN_x2plus.pth' => '4x-UltraSharp.pth',
];

function comfyui_normalize_upscale_model(string $modelName): string {
    $m = trim($modelName);
    if (isset(COMFYUI_UPSCALE_MODEL_MAP[$m])) return COMFYUI_UPSCALE_MODEL_MAP[$m];
    if (stripos($m, 'RealESRGAN') !== false || stripos($m, 'x4plus') !== false || stripos($m, 'x2plus') !== false) {
        return '4x-UltraSharp.pth';
    }
    return $m;
}

/**
 * Validate workflow: every node must have inputs as object/dict, never a list.
 * Throws on invalid structure (non-empty list inputs); empty list is normalized in strip_meta.
 */
function comfyui_validate_workflow_inputs(array $workflow): void {
    foreach ($workflow as $nid => $node) {
        if (!is_array($node)) continue;
        $inputs = $node['inputs'] ?? [];
        if (!is_array($inputs)) continue;
        if (array_is_list($inputs) && !empty($inputs)) {
            throw new \InvalidArgumentException('Workflow validation failed: node ' . $nid . ' has inputs as list (ComfyUI requires object).');
        }
    }
}

/**
 * Strip _meta, normalize UpscaleModelLoader model_name (RealESRGAN -> 4x-UltraSharp).
 * Ensures every node.inputs is an associative object (never list) for ComfyUI compatibility.
 */
function comfyui_strip_meta(array $workflow): array {
    $out = [];
    foreach ($workflow as $nid => $node) {
        if (!is_array($node)) {
            $out[$nid] = $node;
            continue;
        }
        $ctype = $node['class_type'] ?? '';
        $inputs = $node['inputs'] ?? [];
        if (!is_array($inputs)) {
            $inputs = [];
        }
        if (array_is_list($inputs)) {
            if (!empty($inputs)) {
                throw new \InvalidArgumentException('Workflow validation failed: node ' . $nid . ' has inputs as list (ComfyUI requires object).');
            }
            $inputs = (object) [];
        }
        if ($ctype === 'UpscaleModelLoader' && is_array($inputs) && isset($inputs['model_name'])) {
            $inputs['model_name'] = comfyui_normalize_upscale_model((string) $inputs['model_name']);
        }
        $out[$nid] = ['class_type' => $ctype, 'inputs' => $inputs];
    }
    return $out;
}

/**
 * Send prompt to ComfyUI.
 * POST {COMFY_BASE}/prompt with { "prompt": workflow_json, "client_id": "knd-labs" }
 * @param array $workflow Workflow JSON
 * @param string|null $baseUrl Override base URL (from provider)
 * @param string $token Optional X-KND-TOKEN for auth
 * @return array ['prompt_id' => string] or throw
 */
function comfyui_run_prompt(array $workflow, ?string $baseUrl = null, string $token = ''): array {
    if (!defined('COMFYUI_CLIENT_ID') && file_exists(BASE_PATH . '/config/comfyui.php')) {
        require_once BASE_PATH . '/config/comfyui.php';
    }
    if ($baseUrl === null) {
        $baseUrl = defined('COMFYUI_BASE_URL') ? COMFYUI_BASE_URL : '';
    }
    $base = $baseUrl ? rtrim($baseUrl, '/') : '';
    if ($base === '') throw new \RuntimeException('ComfyUI config not found');

    $url = $base . '/prompt';
    comfyui_validate_workflow_inputs($workflow);
    $workflowClean = comfyui_strip_meta($workflow);
    $payload = [
        'prompt' => $workflowClean,
        'client_id' => defined('COMFYUI_CLIENT_ID') ? COMFYUI_CLIENT_ID : 'knd-labs',
    ];
    $jsonBody = json_encode($payload);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token !== '') $headers[] = 'X-KND-TOKEN: ' . $token;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, (int) (defined('COMFYUI_TIMEOUT') ? COMFYUI_TIMEOUT : 120));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $bodyPreview = is_string($body) ? substr($body, 0, 500) : '';
    error_log('ComfyUI /prompt: HTTP ' . $code . ' | body: ' . $bodyPreview);

    if ($err) {
        throw new \RuntimeException('ComfyUI unreachable: ' . $err);
    }
    if ($code < 200 || $code >= 300) {
        $errMsg = 'ComfyUI HTTP ' . $code;
        $data = json_decode($body, true);
        if (is_array($data)) {
            if (!empty($data['message'])) {
                $errMsg = $data['message'];
                if (!empty($data['details'])) $errMsg .= ' | ' . (is_string($data['details']) ? $data['details'] : json_encode($data['details']));
            } elseif (!empty($data['error'])) $errMsg = is_string($data['error']) ? $data['error'] : json_encode($data['error']);
            elseif (!empty($data['node_errors'])) $errMsg = is_string($data['node_errors']) ? $data['node_errors'] : json_encode($data['node_errors']);
            else $errMsg .= ': ' . $bodyPreview;
        } else {
            $errMsg .= ': ' . $bodyPreview;
        }
        error_log('ComfyUI /prompt error: ' . $errMsg);
        throw new \RuntimeException($errMsg);
    }
    if (preg_match('/^\s*</', trim($body ?? ''))) {
        throw new \RuntimeException('ComfyUI returned HTML instead of JSON. Check COMFYUI_BASE_URL - wrong endpoint or base URL.');
    }
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['prompt_id'])) {
        $msg = 'Invalid response';
        if (is_array($data)) {
            if (isset($data['error'])) {
                $err = $data['error'];
                $msg = is_string($err) ? $err : (isset($err['message']) ? $err['message'] : json_encode($err));
            } elseif (isset($data['node_errors'])) {
                $msg = is_string($data['node_errors']) ? $data['node_errors'] : json_encode($data['node_errors']);
            } elseif (!empty($data['message'])) {
                $msg = $data['message'];
                if (!empty($data['details']) && (is_string($data['details']) ? $data['details'] !== '' : true)) {
                    $msg .= ' | ' . (is_string($data['details']) ? $data['details'] : json_encode($data['details']));
                }
                if (!empty($data['extra_info']) && is_array($data['extra_info'])) {
                    $msg .= ' | ' . json_encode($data['extra_info']);
                }
            }
            if (isset($data['type']) && $data['type'] === 'prompt_outputs_failed_validation') {
                $msg = 'Workflow validation failed: ' . $msg . '. Typical causes: (1) Upscale model missing – put 4x-UltraSharp.pth or RealESRGAN_x4plus.pth in ComfyUI/models/upscale_models/ ; (2) LoadImage file not in ComfyUI/input/ (worker must upload first).';
            }
        } elseif ($body) {
            $msg = substr($body, 0, 300);
        }
        error_log('ComfyUI validation error: ' . $msg);
        throw new \RuntimeException($msg);
    }
    return ['prompt_id' => $data['prompt_id']];
}

/**
 * Get ComfyUI history for a prompt.
 * GET {COMFY_BASE}/history/{prompt_id}
 * @param string $promptId ComfyUI prompt ID
 * @param string|null $baseUrl Override base URL (from provider)
 * @param string $token Optional X-KND-TOKEN for auth
 */
function comfyui_get_history(string $promptId, ?string $baseUrl = null, string $token = ''): ?array {
    if ($baseUrl === null && file_exists(BASE_PATH . '/config/comfyui.php')) {
        require_once BASE_PATH . '/config/comfyui.php';
        $baseUrl = defined('COMFYUI_BASE_URL') ? COMFYUI_BASE_URL : '';
    }
    $base = $baseUrl ? rtrim($baseUrl, '/') : '';
    if ($base === '') return null;

    $url = $base . '/history/' . urlencode($promptId);
    $headers = ['Accept: application/json'];
    if ($token !== '') $headers[] = 'X-KND-TOKEN: ' . $token;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $bodyPreview = is_string($body) ? substr($body, 0, 500) : '';
    error_log('ComfyUI /history/' . $promptId . ': HTTP ' . $code . ' | body: ' . $bodyPreview);
    if (!$body) return null;
    if (preg_match('/^\s*</', trim($body))) return null;
    $data = json_decode($body, true);
    return is_array($data) && isset($data[$promptId]) ? $data[$promptId] : null;
}

/**
 * Get recent ComfyUI jobs for user.
 */
function comfyui_get_user_jobs(PDO $pdo, int $userId, int $limit = 20): array {
    $limit = max(1, min(50, (int) $limit));
    $stmt = $pdo->prepare(
        "SELECT id, tool, prompt, status, image_url, cost_kp, provider, created_at FROM knd_labs_jobs WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}"
    );
    if (!$stmt || !$stmt->execute([$userId])) return [];
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user's labs_recent_private preference (0=public, 1=private).
 * Returns false (public) if column missing or error.
 */
function comfyui_user_prefers_private_recent(PDO $pdo, int $userId): bool {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(labs_recent_private, 0) FROM users WHERE id = ? LIMIT 1");
        if (!$stmt || !$stmt->execute([$userId])) return false;
        return (int) $stmt->fetchColumn() === 1;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Get recent ComfyUI jobs from all users (public catalog).
 * Only includes done jobs from users with labs_recent_private = 0.
 */
function comfyui_get_recent_jobs_public(PDO $pdo, int $limit = 24): array {
    try {
        $limit = max(1, min(50, (int) $limit));
        $stmt = $pdo->prepare(
            "SELECT j.id, j.tool, j.prompt, j.status, j.image_url, j.cost_kp, j.created_at, j.user_id
             FROM knd_labs_jobs j
             INNER JOIN users u ON j.user_id = u.id
             WHERE j.status = 'done' AND COALESCE(u.labs_recent_private, 0) = 0
             ORDER BY j.created_at DESC LIMIT {$limit}"
        );
        if (!$stmt || !$stmt->execute()) return [];
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}
