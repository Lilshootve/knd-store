<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/knd_badges.php';

$iconMap = [
    'MW_FIRST_WIN' => 'fa-trophy',
    'MW_VETERAN' => 'fa-medal',
    'MW_WINS_25' => 'fa-medal',
    'MW_WINS_50' => 'fa-medal',
    'MW_WINS_100' => 'fa-medal',
    'MW_WIN_STREAK_3' => 'fa-fire',
    'MW_WIN_STREAK_5' => 'fa-fire',
    'MW_WIN_STREAK_10' => 'fa-fire',
    'MW_SPECIAL_X5' => 'fa-bolt',
    'MW_SPECIAL_X25' => 'fa-bolt',
    'MW_SPECIAL_X50' => 'fa-bolt',
    'MW_GIANT_SLAYER' => 'fa-dragon',
    'MW_GIANT_SLAYER_3' => 'fa-dragon',
    'MW_GIANT_SLAYER_5' => 'fa-dragon',
    'MW_GIANT_SLAYER_10' => 'fa-dragon',
];

try {
    api_require_login();
    $userId = (int) current_user_id();
    $pdo = getDBConnection();
    if (!$pdo) {
        json_success(['achievements' => [], 'unlocked' => []]);
    }

    $progress = badges_get_user_progress($pdo, $userId);
    $achievements = [];
    $unlocked = [];

    foreach ($progress as $badge) {
        $ut = (string) ($badge['unlock_type'] ?? '');
        if (strpos($ut, 'mind_wars_') !== 0) {
            continue;
        }
        $code = (string) ($badge['code'] ?? '');
        $achievements[] = [
            'id' => strtolower($code),
            'name' => $badge['name'],
            'desc' => $badge['description'],
            'icon' => $iconMap[$code] ?? 'fa-award',
            'unlocked' => !empty($badge['unlocked']),
            'current' => (int) ($badge['current'] ?? 0),
            'threshold' => (int) ($badge['threshold'] ?? 0),
        ];
        if (!empty($badge['unlocked'])) {
            $unlocked[] = $code;
        }
    }

    json_success(['achievements' => $achievements, 'unlocked' => $unlocked]);
} catch (Throwable $e) {
    json_success(['achievements' => [], 'unlocked' => []]);
}
