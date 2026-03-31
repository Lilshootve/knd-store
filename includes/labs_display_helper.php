<?php
/**
 * KND Labs - Display helpers for Image Details and URLs
 */

/**
 * Normalize job data for Image Details panel display.
 * @param array $job Raw job row from DB (with payload_json)
 * @return array Normalized display-ready details
 */
function labs_get_job_display_details(array $job): array {
    $payload = is_string($job['payload_json'] ?? '') ? json_decode($job['payload_json'], true) : ($job['payload_json'] ?? []);
    $payload = is_array($payload) ? $payload : [];
    $tool = $job['tool'] ?? '';
    $d = [
        'tool' => $tool,
        'status' => $job['status'] ?? '—',
        'model' => $payload['model'] ?? $payload['model_ckpt'] ?? '—',
        'seed' => $payload['seed'] ?? null,
        'sampler' => $payload['sampler_name'] ?? $payload['sampler'] ?? '—',
        'steps' => $payload['steps'] ?? null,
        'cfg' => $payload['cfg'] ?? null,
        'width' => $payload['width'] ?? null,
        'height' => $payload['height'] ?? null,
        'created_at' => $job['created_at'] ?? '—',
        'job_id' => $job['id'] ?? '—',
        'cost_kp' => (int) ($job['cost_kp'] ?? 0),
        'prompt' => $job['prompt'] ?? ($payload['base_prompt'] ?? '') . ($payload['scene_prompt'] ?? '') ?: '—',
        'negative_prompt' => $job['negative_prompt'] ?? ($payload['negative_prompt'] ?? '—'),
    ];
    if ($tool === 'consistency') {
        $d['mode'] = $payload['mode'] ?? '—';
        $d['reference_source'] = $payload['reference_source'] ?? '—';
        $d['reference_job_id'] = $payload['reference_job_id'] ?? null;
        $d['base_prompt'] = $payload['base_prompt'] ?? '—';
        $d['scene_prompt'] = $payload['scene_prompt'] ?? '—';
    }
    return $d;
}

/**
 * Build URL for Generate Variations (redirect to consistency with reference).
 * @param array $job Job with id, tool, payload_json
 * @return string|null URL or null if job not suitable for variations
 */
function labs_build_consistency_variation_url(array $job): ?string {
    $status = $job['status'] ?? '';
    if ($status !== 'done') return null;
    $tool = $job['tool'] ?? '';
    if (!in_array($tool, ['text2img', 'consistency'], true)) return null;
    $jid = (int) ($job['id'] ?? 0);
    if ($jid <= 0) return null;
    $payload = is_string($job['payload_json'] ?? '') ? json_decode($job['payload_json'], true) : ($job['payload_json'] ?? []);
    $payload = is_array($payload) ? $payload : [];
    $mode = 'style';
    if ($tool === 'consistency' && !empty($payload['mode']) && in_array($payload['mode'], ['style', 'character', 'both'], true)) {
        $mode = $payload['mode'];
    }
    return '/labs-consistency.php?reference_job_id=' . $jid . '&mode=' . urlencode($mode);
}
