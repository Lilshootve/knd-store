<?php
/**
 * KND Labs - ComfyUI configuration (single unified instance)
 * One ComfyUI for: Text2Img, Texture Lab, Upscale, Consistency, 3D Lab, Character Lab.
 * Default: http://127.0.0.1:8190 (local portable).
 */
if (!defined('COMFYUI_BASE_URL')) {
    define('COMFYUI_BASE_URL', getenv('COMFYUI_URL') ?: getenv('COMFYUI_BASE_URL') ?: 'http://127.0.0.1:8190');
}
if (!defined('COMFYUI_TIMEOUT')) {
    define('COMFYUI_TIMEOUT', 120);
}
if (!defined('COMFYUI_CLIENT_ID')) {
    define('COMFYUI_CLIENT_ID', 'knd-labs');
}
