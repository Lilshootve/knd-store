<?php
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config/bootstrap.php';
}

/**
 * Character Lab - Helper functions
 */

require_once __DIR__ . '/character_lab_policy.php';

$clConfig = BASE_PATH . '/config/character_lab.local.php';
if (file_exists($clConfig)) {
    require_once $clConfig;
}

/** KP cost for Character Lab (config-driven; fallback) */
function character_lab_kp_cost(): int {
    if (defined('CHARACTER_LAB_KP_COST')) {
        return max(5, (int) CHARACTER_LAB_KP_COST);
    }
    return 25;
}

/** Storage base for Character Lab */
const CHARACTER_LAB_STORAGE_INPUT = 'labs/character-lab/input';
const CHARACTER_LAB_STORAGE_CONCEPT = 'labs/character-lab/concept';
const CHARACTER_LAB_STORAGE_MESH = 'labs/character-lab/mesh';
const CHARACTER_LAB_STORAGE_THUMB = 'labs/character-lab/thumbs';

/** Max upload size bytes */
const CHARACTER_LAB_MAX_IMAGE_SIZE = 10 * 1024 * 1024;

/** Allowed image MIME types */
const CHARACTER_LAB_ALLOWED_MIMES = [
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

function character_lab_uuid(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0xffff), random_int(0, 0x4fff) | 0x4000,
        random_int(0, 0xffff) | 0x8000, random_int(0, 0xffff),
        random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

/**
 * Sanitize user prompt for storage (strip control chars, limit length).
 */
function character_lab_sanitize_prompt(string $raw): string {
    $s = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', '', $raw);
    return mb_substr(trim($s), 0, 2000);
}
