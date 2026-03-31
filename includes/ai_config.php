<?php
/**
 * AI tools configuration (text2img, upscale, character lab).
 * Reuses INSTANTMESH_* if AI_GPU_API_URL not set (unified GPU server).
 */
require_once __DIR__ . '/env.php';

if (!defined('AI_GPU_API_URL')) {
    define('AI_GPU_API_URL', knd_env_required('AI_GPU_API_URL'));
}
if (!defined('AI_CALLBACK_SECRET')) {
    define('AI_CALLBACK_SECRET', knd_env_required('AI_CALLBACK_SECRET'));
}
if (!defined('AI_UPLOAD_DIR')) {
    define('AI_UPLOAD_DIR', 'ai/uploads');
}
if (!defined('AI_OUTPUT_DIR')) {
    define('AI_OUTPUT_DIR', 'ai/outputs');
}
