<?php
/**
 * KND Labs - ComfyUI Provider (local / runpod / auto)
 * Resolves which ComfyUI base URL to use.
 */

require_once __DIR__ . '/settings.php';

const PROVIDER_MODE_LOCAL = 'local';
const PROVIDER_MODE_RUNPOD = 'runpod';
const PROVIDER_MODE_AUTO = 'auto';

function comfyui_get_provider_mode(PDO $pdo): string {
    $mode = settings_get($pdo, 'labs_provider_mode', PROVIDER_MODE_AUTO);
    return in_array($mode, [PROVIDER_MODE_LOCAL, PROVIDER_MODE_RUNPOD, PROVIDER_MODE_AUTO], true)
        ? $mode : PROVIDER_MODE_AUTO;
}

function comfyui_get_base_url_local(PDO $pdo): string {
    $url = settings_get($pdo, 'comfyui_base_url_local', 'http://127.0.0.1:8190');
    return rtrim((string) $url, '/');
}

function comfyui_get_base_url_runpod(PDO $pdo): string {
    $url = settings_get($pdo, 'comfyui_base_url_runpod', '');
    return rtrim((string) $url, '/');
}

function comfyui_get_auto_timeout_ms(PDO $pdo): int {
    $ms = (int) settings_get($pdo, 'labs_auto_timeout_ms', '3000');
    return max(500, min(30000, $ms));
}

function comfyui_get_token(PDO $pdo): string {
    return (string) settings_get($pdo, 'comfyui_token', '');
}

/**
 * Get base URL for a preferred provider (local|runpod) or null for auto.
 */
function comfyui_get_base_url(PDO $pdo, ?string $preferred = null): string {
    $mode = $preferred ?: comfyui_get_provider_mode($pdo);
    if ($mode === PROVIDER_MODE_LOCAL) {
        return comfyui_get_base_url_local($pdo);
    }
    if ($mode === PROVIDER_MODE_RUNPOD) {
        $url = comfyui_get_base_url_runpod($pdo);
        if ($url === '') {
            $url = comfyui_get_base_url_local($pdo);
        }
        return $url;
    }
    return comfyui_pick_provider_auto($pdo);
}

/**
 * Try local first with timeout; on failure use runpod.
 */
function comfyui_pick_provider_auto(PDO $pdo): string {
    $local = comfyui_get_base_url_local($pdo);
    $runpod = comfyui_get_base_url_runpod($pdo);
    $timeoutSec = comfyui_get_auto_timeout_ms($pdo) / 1000;

    if ($local !== '') {
        $ok = comfyui_healthcheck($local, $pdo, $timeoutSec);
        if ($ok) return $local;
    }

    if ($runpod !== '') return $runpod;
    return $local ?: $runpod ?: 'https://comfy.kndstore.com';
}

/**
 * Quick healthcheck: GET {base}/prompt expects 200 + JSON.
 */
function comfyui_healthcheck(string $baseUrl, PDO $pdo, float $timeoutSec = 3): bool {
    $url = rtrim($baseUrl, '/') . '/prompt';
    $token = comfyui_get_token($pdo);

    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($token !== '') {
        $headers[] = 'X-KND-TOKEN: ' . $token;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => max(1, (int) ceil($timeoutSec * 0.5)),
        CURLOPT_TIMEOUT => max(2, (int) ceil($timeoutSec)),
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) return false;
    if ($code < 200 || $code >= 300) return false;
    if (preg_match('/^\s*</', trim($body ?? ''))) return false;
    return true;
}
