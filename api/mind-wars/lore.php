<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/mind_wars_lore.php';

try {
    $slug = isset($_GET['slug']) ? trim(strtolower((string) $_GET['slug'])) : '';
    if ($slug === '') {
        json_success(['lore' => null]);
        exit;
    }
    $dataset = mw_avatar_lore_dataset();
    $key = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?: '';
    $lore = null;
    if (isset($dataset[$key])) {
        $lore = $dataset[$key];
    } else {
        foreach ($dataset as $k => $v) {
            if (strpos($k, $key) !== false || strpos($key, $k) !== false) {
                $lore = $v;
                break;
            }
        }
    }
    json_success(['lore' => $lore, 'slug' => $slug]);
} catch (Throwable $e) {
    json_success(['lore' => null]);
}
