<?php
/**
 * Worker token for HTTP worker auth (X-KND-WORKER-TOKEN).
 * Strictly loaded from .env
 */
require_once __DIR__ . '/env.php';

function get_worker_token(): string {
    static $token = null;
    if ($token !== null) return $token;
    $token = trim(knd_env_required('KND_WORKER_TOKEN'));
    return $token;
}
