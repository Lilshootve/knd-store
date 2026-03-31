<?php
/**
 * 3D Lab - Helpers, policy, categories, presets
 * Safe mode only. Separate from ComfyUI used by text2img/upscale/consistency.
 */

const LABS_3D_KP_COST = 30;
const LABS_3D_MAX_IMAGE_SIZE = 10 * 1024 * 1024;
const LABS_3D_STORAGE_INPUT = 'labs/3d-lab/input';
const LABS_3D_STORAGE_OUTPUT = 'labs/3d-lab/output';
const LABS_3D_STORAGE_PREVIEW = 'labs/3d-lab/preview';

const LABS_3D_ALLOWED_MIMES = [
    'image/jpeg' => 'jpg', 'image/jpg' => 'jpg',
    'image/png' => 'png', 'image/webp' => 'webp',
];

const LABS_3D_CATEGORIES = [
    'Character' => 'Character',
    'Creature' => 'Creature',
    'Vehicle' => 'Vehicle',
    'Weapon' => 'Weapon',
    'Object / Product' => 'Object / Product',
    'Environment Prop' => 'Environment Prop',
    'Furniture' => 'Furniture',
    'Architecture' => 'Architecture',
    'Stylized Asset' => 'Stylized Asset',
    'Realistic Asset' => 'Realistic Asset',
    'Game Asset' => 'Game Asset',
    '3D Print Object' => '3D Print Object',
];

const LABS_3D_STYLES = [
    'Realistic' => 'Realistic',
    'Stylized' => 'Stylized',
    'Low Poly' => 'Low Poly',
    'Hard Surface' => 'Hard Surface',
    'Organic' => 'Organic',
    'Cartoon' => 'Cartoon',
];

const LABS_3D_QUALITY = [
    'Standard' => 'Standard',
    'High' => 'High',
];

/** Smart preset hints by category (for worker / future ComfyUI) */
const LABS_3D_CATEGORY_PRESETS = [
    'Character' => ['topology' => 'clean', 'pose_lock' => true, 'symmetry' => 'suggested', 'texture' => 'high'],
    'Creature' => ['topology' => 'organic', 'pose_lock' => false, 'symmetry' => 'optional'],
    'Vehicle' => ['topology' => 'hard_surface', 'symmetry' => 'suggested', 'optimization' => 'aggressive'],
    'Weapon' => ['topology' => 'hard_surface', 'symmetry' => 'suggested'],
    'Game Asset' => ['polycount' => 'controlled', 'optimization' => 'aggressive', 'uv' => 'optimized'],
    '3D Print Object' => ['geometry' => 'closed_solid', 'mesh' => 'watertight'],
    'Stylized Asset' => ['detail' => 'medium', 'texture' => 'stylized'],
];

function labs_3d_uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0xffff), random_int(0, 0x4fff) | 0x4000,
        random_int(0, 0xffff) | 0x8000, random_int(0, 0xffff),
        random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

function labs_3d_kp_cost(): int {
    return defined('LABS_3D_KP_COST') ? (int) LABS_3D_KP_COST : 30;
}

function labs_3d_validate_category(string $cat): string {
    return array_key_exists($cat, LABS_3D_CATEGORIES) ? $cat : 'Stylized Asset';
}

function labs_3d_validate_style(string $s): string {
    return array_key_exists($s, LABS_3D_STYLES) ? $s : 'Stylized';
}

function labs_3d_validate_quality(string $q): string {
    return array_key_exists($q, LABS_3D_QUALITY) ? $q : 'Standard';
}

/**
 * Mark stale 3D lab jobs (processing/queued too long) as failed.
 * Call before counting active jobs to avoid blocking users with orphaned jobs.
 *
 * @param \PDO $pdo
 * @param int $userId
 * @param int $processingMinutes Jobs in 'processing' longer than this = abandoned (default 15)
 * @param int $queuedMinutes Jobs in 'queued' longer than this = abandoned (default 15)
 * @return int Number of jobs marked as failed
 */
function labs_3d_cleanup_stale_jobs(\PDO $pdo, int $userId, int $processingMinutes = 15, int $queuedMinutes = 15): int {
    $stmt = $pdo->prepare(
        "UPDATE knd_labs_3d_jobs SET
            status = 'failed',
            error_message = COALESCE(error_message, 'Job abandoned (timeout)'),
            completed_at = NOW(),
            updated_at = NOW()
         WHERE user_id = ?
         AND (
             (status = 'processing' AND processing_started_at < DATE_SUB(NOW(), INTERVAL ? MINUTE))
             OR (status = 'queued' AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE))
         )"
    );
    if (!$stmt || !$stmt->execute([$userId, $processingMinutes, $queuedMinutes])) {
        return 0;
    }
    $n = $stmt->rowCount();
    if ($n > 0) {
        error_log("labs_3d_cleanup_stale_jobs: user_id={$userId} marked {$n} stale job(s) as failed");
    }
    return $n;
}
