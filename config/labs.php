<?php
/**
 * KND Labs - Central path configuration.
 * Override in config/labs.local.php for your environment.
 *
 * COMFY_OUTPUT_DIR: Where ComfyUI writes outputs (when worker runs on same machine).
 *   Example: C:\AI\Comfyui3d\Comfyui3d\ComfyUI_windows_portable\ComfyUI\output
 * KND_FINAL_IMAGE_DIR: Final destination for generated images (F:\KND\images or F:\KND\output).
 *   Worker copies here after each successful job. Used for archive/backup; web serves from storage.
 */
$labsLocal = __DIR__ . '/labs.local.php';
if (file_exists($labsLocal)) {
    require_once $labsLocal;
}
if (!defined('COMFY_INPUT_DIR')) {
    define('COMFY_INPUT_DIR', getenv('COMFY_INPUT_DIR') ?: (dirname(__DIR__) . '/comfyui/ComfyUI/input'));
}
if (!defined('COMFY_OUTPUT_DIR')) {
    define('COMFY_OUTPUT_DIR', getenv('COMFY_OUTPUT_DIR') ?: '/path/to/ComfyUI/output');
}
if (!defined('KND_FINAL_IMAGE_DIR')) {
    define('KND_FINAL_IMAGE_DIR', getenv('KND_FINAL_IMAGE_DIR') ?: 'C:\\AI\\Comfyui3d\\Comfyui3d\\ComfyUI_windows_portable\\ComfyUI\\output');
}
if (!defined('WORKFLOWS_DIR')) {
    define('WORKFLOWS_DIR', getenv('WORKFLOWS_DIR') ?: (dirname(__DIR__) . '/workflows'));
}
if (!defined('LABS_UPLOAD_DIR')) {
    define('LABS_UPLOAD_DIR', 'uploads/labs');
}
