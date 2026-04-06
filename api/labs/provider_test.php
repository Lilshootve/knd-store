<?php
/**
 * GET /api/labs/provider_test.php
 * Admin only. Returns healthcheck for local and runpod providers.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/json.php';
require_once __DIR__ . '/../../admin/_rbac.php';

if (empty($_SESSION['admin_logged_in'])) {
    json_error('UNAUTHORIZED', 'Admin required.', 403);
}

require_once BASE_PATH . '/includes/settings.php';
require_once BASE_PATH . '/includes/comfyui_provider.php';

try {
    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $localUrl = comfyui_get_base_url_local($pdo);
    $runpodUrl = comfyui_get_base_url_runpod($pdo);
    $timeoutSec = comfyui_get_auto_timeout_ms($pdo) / 1000;

    $localOk = false;
    $localMs = null;
    if ($localUrl !== '') {
        $start = microtime(true);
        $localOk = comfyui_healthcheck($localUrl, $pdo, $timeoutSec);
        $localMs = (int) round((microtime(true) - $start) * 1000);
    }

    $runpodOk = false;
    $runpodMs = null;
    if ($runpodUrl !== '') {
        $start = microtime(true);
        $runpodOk = comfyui_healthcheck($runpodUrl, $pdo, $timeoutSec);
        $runpodMs = (int) round((microtime(true) - $start) * 1000);
    }

    $chosenProvider = comfyui_get_provider_mode($pdo);
    if ($chosenProvider === PROVIDER_MODE_AUTO) {
        $chosenProvider = $localOk ? PROVIDER_MODE_LOCAL : (($runpodUrl !== '') ? PROVIDER_MODE_RUNPOD : 'none');
    }

    json_success([
        'local' => ['ok' => $localOk, 'latency_ms' => $localMs],
        'runpod' => ['ok' => $runpodOk, 'latency_ms' => $runpodMs],
        'chosen_provider' => $chosenProvider,
    ]);
} catch (\Throwable $e) {
    error_log('api/labs/provider_test: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', $e->getMessage(), 500);
}
