<?php
/**
 * 3D Lab worker upload token only (X-KND-3D-WORKER-TOKEN).
 * Used exclusively by api/labs/3d-lab/upload-output.php.
 * Does not affect Text2Img queue auth (worker_auth.php / WORKER_TOKEN).
 */
require_once __DIR__ . '/env.php';

function get_worker_3d_upload_token(): string {
    static $token = null;
    if ($token !== null) return $token;
    $token = trim(knd_env_required('KND_WORKER_3D_UPLOAD_TOKEN'));
    return $token;
}
