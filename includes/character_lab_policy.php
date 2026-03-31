<?php
/**
 * Character Lab - Policy, validation, prompt normalization
 * Safe mode only for this implementation. NSFW can be added via policy_mode later.
 */

/** Allowed character categories for reliable 3D reconstruction */
const CHARACTER_LAB_CATEGORIES = [
    'human' => 'Human',
    'humanoid' => 'Humanoid',
    'fantasy' => 'Fantasy Character',
    'mascot' => 'Mascot',
    'creature' => 'Light Creature',
    'mecha' => 'Light Mecha',
];

/** Negative prompt elements for concept image */
const CHARACTER_LAB_NEGATIVE_PROMPT = 'multiple people, duplicate limbs, extra fingers, cut-off, cropped head, cropped hands, cropped feet, oversized weapons, giant wings, extreme hair occlusion, particles, smoke, clutter, text, watermark, logo, background scenery, photorealistic, realistic photo, celebrity, trademark, copyrighted character, gore, body horror, malformed anatomy, crowded scene';

/** Keywords that trigger rejection */
const CHARACTER_LAB_REJECT_PATTERNS = [
    'celebrity', 'celebrity likeness', 'famous person', 'real person',
    'copyright', 'trademark', 'franchise', 'marvel', 'dc comics', 'disney', 'nintendo', 'pokemon',
    'gore', 'blood', 'horror', 'body horror', 'extreme violence',
    'nsfw', 'nude', 'naked', 'explicit', 'adult only',
    'child', 'minor', 'underage', 'kid', 'baby',
    'multiple characters', 'crowd', 'group of people', 'two people', 'three people',
    'vehicle', 'car', 'motorcycle', 'tank',
    'weapon focus', 'holding gun', 'holding sword as main',
];

/**
 * Check if policy mode is allowed. Safe only for now.
 */
function character_lab_policy_allowed(string $policyMode): bool {
    return $policyMode === 'safe';
}

/**
 * Validate and normalize category. Returns allowed category or 'human'.
 */
function character_lab_validate_category(string $category): string {
    $cat = strtolower(trim($category));
    return array_key_exists($cat, CHARACTER_LAB_CATEGORIES) ? $cat : 'human';
}

/**
 * Check if raw prompt should be rejected.
 * @return array ['allowed' => bool, 'reason' => string|null]
 */
function character_lab_check_prompt(string $promptRaw): array {
    $lower = strtolower(trim($promptRaw));
    if (strlen($lower) > 2000) {
        return ['allowed' => false, 'reason' => 'Prompt too long.'];
    }
    foreach (CHARACTER_LAB_REJECT_PATTERNS as $pattern) {
        if (strpos($lower, $pattern) !== false) {
            return ['allowed' => false, 'reason' => 'Prompt contains disallowed content.'];
        }
    }
    return ['allowed' => true, 'reason' => null];
}

/**
 * Build normalized concept-image prompt from user input.
 * Enforces: single character, full body, centered, stylized game-ready, clean silhouette.
 */
function character_lab_build_prompt(string $userPrompt, string $category): string {
    $base = 'stylized game-ready character concept, single character, full body visible, centered composition';
    $base .= ', front or three-quarter view, clean silhouette, readable outfit and accessories';
    $base .= ', neutral to mild action pose, plain transparent background';
    $base .= ', anatomically coherent hands and feet, no cropped limbs';
    $base .= ', no extra characters, no vehicles, no environment clutter';

    $catLabel = CHARACTER_LAB_CATEGORIES[$category] ?? 'Human';
    $user = trim($userPrompt);
    if ($user !== '') {
        $user = preg_replace('/\s+/', ' ', $user);
        $user = mb_substr($user, 0, 500);
        return $catLabel . ', ' . $base . ', ' . $user;
    }
    return $catLabel . ', ' . $base;
}

/**
 * Get negative prompt for concept image.
 */
function character_lab_negative_prompt(): string {
    return CHARACTER_LAB_NEGATIVE_PROMPT;
}
