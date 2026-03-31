<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: *');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/mind_wars.php';

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $catalog = mw_get_avatar_collection_catalog($pdo);

    $avatars = [];
    foreach ($catalog as $item) {
        $name = (string) ($item['name'] ?? 'Avatar');
        $rarity = (string) ($item['rarity'] ?? 'common');
        $slug = (string) ($item['slug'] ?? '');
        if ($slug === '') {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '_', $name), '_')) ?: 'avatar';
        } else {
            $slug = str_replace('-', '_', $slug);
        }

        $modelPath = (string) ($item['model_path'] ?? '');
        if ($modelPath === '') {
            $modelPath = '/assets/avatars/models/' . $rarity . '/' . $slug . '.glb';
        }
        $thumbnailPath = (string) ($item['thumbnail_path'] ?? '');
        if ($thumbnailPath === '') {
            $thumbnailPath = '/assets/avatars/thumbnails/' . $slug . '.png';
        }
        $abilities = [];
        $abilities[] = [
            'type' => 'passive',
            'name' => (string) ($item['passive_name'] ?? ''),
            'description' => (string) ($item['passive_description'] ?? ''),
        ];
        $abilities[] = [
            'type' => 'ability',
            'name' => (string) ($item['ability_name'] ?? ''),
            'description' => (string) ($item['ability_description'] ?? ''),
        ];
        $abilities[] = [
            'type' => 'special',
            'name' => (string) ($item['special_name'] ?? ''),
            'description' => (string) ($item['special_description'] ?? ''),
        ];

        $avatars[] = [
            'id' => (int) ($item['item_id'] ?? 0),
            'slug' => $slug,
            'name' => $name,
            'rarity' => $rarity,
            'role' => (string) ($item['role_description'] ?? ''),
            'model_path' => $modelPath,
            'thumbnail_path' => $thumbnailPath,
            'lore' => [
                'short' => (string) ($item['short_lore'] ?? ''),
                'cultural' => (string) ($item['cultural_description'] ?? ''),
                'historical' => (string) ($item['historical_description'] ?? ''),
            ],
            'stats' => [
                'mind' => (int) ($item['stats']['mind'] ?? 0),
                'focus' => (int) ($item['stats']['focus'] ?? 0),
                'speed' => (int) ($item['stats']['speed'] ?? 0),
                'luck' => (int) ($item['stats']['luck'] ?? 0),
            ],
            'abilities' => $abilities,
        ];
    }

    json_success(['avatars' => $avatars]);
} catch (\Throwable $e) {
    error_log('avatars/full-collection error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
