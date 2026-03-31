<?php
/**
 * InstantMesh 3D configuration loader.
 * Endpoints remain /api/triposr/* for backward compatibility.
 *
 * Reads strict values from .env:
 * INSTANTMESH_API_URL and INSTANTMESH_CALLBACK_SECRET.
 *
 * Add to includes/config.php:
 *   require_once __DIR__ . '/triposr_config.php';
 */
require_once __DIR__ . '/env.php';

if (!defined('INSTANTMESH_API_URL')) {
    define('INSTANTMESH_API_URL', knd_env_required('INSTANTMESH_API_URL'));
}
if (!defined('INSTANTMESH_CALLBACK_SECRET')) {
    define('INSTANTMESH_CALLBACK_SECRET', knd_env_required('INSTANTMESH_CALLBACK_SECRET'));
}
if (!defined('TRIPOSR_API_URL')) {
    define('TRIPOSR_API_URL', INSTANTMESH_API_URL);
}
if (!defined('TRIPOSR_CALLBACK_SECRET')) {
    define('TRIPOSR_CALLBACK_SECRET', INSTANTMESH_CALLBACK_SECRET);
}
if (!defined('TRIPOSR_UPLOAD_DIR')) {
    define('TRIPOSR_UPLOAD_DIR', 'triposr/uploads');
}
if (!defined('TRIPOSR_OUTPUT_DIR')) {
    define('TRIPOSR_OUTPUT_DIR', 'triposr/outputs');
}
