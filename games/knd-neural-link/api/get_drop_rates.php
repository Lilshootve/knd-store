<?php
declare(strict_types=1);
/**
 * KND Neural Link — get_drop_rates.php
 * GET optional ?type=standard|premium|legendary
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/support_credits.php';
require_once __DIR__ . '/../includes/knl_packs.php';
require_once __DIR__ . '/../includes/knl_mw_items.php';

$serverName = $_SERVER['SERVER_NAME'] ?? '';
$knlIsLocal = in_array($serverName, ['localhost', '127.0.0.1'], true);

function knl_rates_ag_log(string $step, string $message): void
{
    error_log('[KNL AGUACATE] ' . $step . ' — ' . $message);
}

if (!is_logged_in()) {
    knl_rates_ag_log('auth', 'no session');
    http_response_code(401);
    echo json_encode([
        'success'  => false,
        'error'    => 'Unauthenticated',
        'aguacate' => 'auth',
    ]);
    exit;
}

$userId = (int) current_user_id();
if ($userId <= 0) {
    knl_rates_ag_log('auth', 'invalid user');
    http_response_code(401);
    echo json_encode([
        'success'  => false,
        'error'    => 'Unauthenticated',
        'aguacate' => 'auth',
    ]);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    knl_rates_ag_log('db', 'getDBConnection failed');
    http_response_code(500);
    echo json_encode([
        'success'  => false,
        'error'    => 'server_error',
        'aguacate' => 'db',
    ]);
    exit;
}

$raw = knl_drop_packs_raw();

$packs = [
    'standard' => [
        'id'          => 'standard',
        'label'       => 'Standard link',
        'description' => 'Baseline neural scan. Full rarity spread.',
        'cost_kp'     => $raw['standard']['cost_kp'],
        'cost_coins'  => 0,
        'cost_gems'   => 0,
        'rates'       => knl_pack_rates_ui_rows('standard'),
        'pity_legendary' => $raw['standard']['pity_legendary'],
        'pity_epic'      => $raw['standard']['pity_epic'],
        'color'          => '#00e8ff',
        'icon'           => 'link_standard',
    ],
    'premium' => [
        'id'          => 'premium',
        'label'       => 'Amplified link',
        'description' => 'Boosted epic and legendary coherence.',
        'cost_kp'     => $raw['premium']['cost_kp'],
        'cost_coins'  => 0,
        'cost_gems'   => 0,
        'rates'       => knl_pack_rates_ui_rows('premium'),
        'pity_legendary' => $raw['premium']['pity_legendary'],
        'pity_epic'      => $raw['premium']['pity_epic'],
        'color'          => '#d6cfc7',
        'icon'           => 'link_premium',
    ],
    'legendary' => [
        'id'          => 'legendary',
        'label'       => 'Deep sync',
        'description' => 'High-bandwidth channel. Epic+ weighted.',
        'cost_kp'     => $raw['legendary']['cost_kp'],
        'cost_coins'  => 0,
        'cost_gems'   => 0,
        'rates'       => knl_pack_rates_ui_rows('legendary'),
        'pity_legendary' => $raw['legendary']['pity_legendary'],
        'pity_epic'      => $raw['legendary']['pity_epic'],
        'color'          => '#ffcc00',
        'icon'           => 'link_deep',
    ],
];

$filterType = isset($_GET['type']) ? (string) $_GET['type'] : null;
if ($filterType !== null && $filterType !== '' && isset($packs[$filterType])) {
    $packs = [$filterType => $packs[$filterType]];
}

try {
    $pdo->prepare(
        'INSERT IGNORE INTO knl_neural_link_pity (user_id, pity_legendary, pity_epic) VALUES (?, 0, 0)'
    )->execute([$userId]);

    $stmt = $pdo->prepare(
        'SELECT pity_legendary, pity_epic FROM knl_neural_link_pity WHERE user_id = ?'
    );
    $stmt->execute([$userId]);
    $pityRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['pity_legendary' => 0, 'pity_epic' => 0];

    $pityLeg  = (int) $pityRow['pity_legendary'];
    $pityEpic = (int) $pityRow['pity_epic'];

    $pool = knl_neural_pool_counts_by_rarity($pdo);

    foreach ($packs as &$pack) {
        $pack['pity_legendary_current'] = $pityLeg;
        $den = max(1, (int) $pack['pity_legendary']);
        $pack['pity_legendary_pct']     = (int) round($pityLeg / $den * 100);
        $pack['pity_epic_current']      = $pityEpic;
        $denE = max(1, (int) $pack['pity_epic']);
        $pack['pity_epic_pct']          = (int) round($pityEpic / $denE * 100);
        foreach ($pack['rates'] as &$rate) {
            $rate['pool_count'] = $pool[$rate['rarity']] ?? 0;
        }
        unset($rate);
    }
    unset($pack);

    $kp = get_available_points($pdo, $userId);

    echo json_encode([
        'success' => true,
        'packs'   => array_values($packs),
        'balance' => [
            'knd_points' => $kp,
            'coins'      => $kp,
            'gems'       => 0,
        ],
        'pity' => [
            'legendary' => $pityLeg,
            'epic'      => $pityEpic,
        ],
    ]);
} catch (Throwable $e) {
    knl_rates_ag_log('query', $e->getMessage());
    http_response_code(500);
    $out = [
        'success'  => false,
        'error'    => 'server_error',
        'aguacate' => 'query',
    ];
    if ($knlIsLocal) {
        $out['debug_detail'] = $e->getMessage();
    }
    echo json_encode($out);
}
