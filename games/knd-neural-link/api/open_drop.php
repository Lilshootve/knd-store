<?php
/**
 * KND Neural Link — open_drop.php
 * POST JSON: { "drop_type": "standard"|"premium"|"legendary" }
 *
 * PRODUCCIÓN: dejar $SANDBOX_MODE = true; quitar o desactivar dry-run.
 */

declare(strict_types=1);

// ── Sandbox: true = KP real, pity real, inventario, log. false = dry-run (sin escrituras). ──
$SANDBOX_MODE = true;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/support_credits.php';
require_once __DIR__ . '/../includes/knl_packs.php';
require_once __DIR__ . '/../includes/knl_db.php';
require_once __DIR__ . '/../includes/knl_assets.php';
require_once __DIR__ . '/../includes/knl_mw_items.php';

$serverName = $_SERVER['SERVER_NAME'] ?? '';
$knlIsLocal = in_array($serverName, ['localhost', '127.0.0.1'], true);

function knl_ag_log(string $step, string $message): void
{
    error_log('[KNL AGUACATE] ' . $step . ' — ' . $message);
}

function knl_fail(int $http, string $error, string $aguacate, ?string $detail = null, bool $exposeDetail = false): void
{
    global $knlIsLocal;
    http_response_code($http);
    $out = [
        'success' => false,
        'error'   => $error,
        'aguacate' => $aguacate,
    ];
    if ($exposeDetail && $knlIsLocal && $detail !== null) {
        $out['debug_detail'] = $detail;
    }
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    knl_fail(405, 'method_not_allowed', 'method');
}

if (!is_logged_in()) {
    knl_ag_log('auth', 'no session dr_user_id');
    knl_fail(401, 'Unauthenticated', 'auth');
}

$userId = (int) current_user_id();
if ($userId <= 0) {
    knl_ag_log('auth', 'invalid user id');
    knl_fail(401, 'Unauthenticated', 'auth');
}

$input    = json_decode((string) file_get_contents('php://input'), true) ?? [];
$dropType = $input['drop_type'] ?? 'standard';

$packs = knl_drop_packs_raw();
if (!isset($packs[$dropType])) {
    knl_fail(400, 'Invalid drop type', 'input');
}

$pack = $packs[$dropType];
$costKp = (int) $pack['cost_kp'];

$pdo = getDBConnection();
if (!$pdo) {
    knl_ag_log('db', 'getDBConnection failed');
    knl_fail(500, 'server_error', 'db');
}

const KNL_KE_REWARD = [
    'common'    => 10,
    'rare'      => 25,
    'special'   => 50,
    'epic'      => 100,
    'legendary' => 300,
];

function knl_resolve_rarity(array $rates, bool $forceLegendary, bool $forceEpic): string
{
    if ($forceLegendary) {
        return 'legendary';
    }
    if ($forceEpic) {
        return 'epic';
    }
    $roll = mt_rand(1, 100);
    $cumulative = 0;
    foreach ($rates as $rarity => $weight) {
        if ($weight <= 0) {
            continue;
        }
        $cumulative += $weight;
        if ($roll <= $cumulative) {
            return $rarity;
        }
    }
    return 'common';
}

function knl_ledger_spend_kp(PDO $pdo, int $userId, int $kp): void
{
    $types = ['knl_neural_link', 'drop_entry', 'adjustment'];
    $last  = null;
    foreach ($types as $sourceType) {
        try {
            $pdo->prepare(
                "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
                 VALUES (?, ?, 0, 'spend', 'spent', ?, NOW())"
            )->execute([$userId, $sourceType, -$kp]);

            return;
        } catch (PDOException $e) {
            $last = $e;
        }
    }
    knl_ag_log('kp_debit', $last ? $last->getMessage() : 'unknown');
    throw $last ?? new RuntimeException('Could not record KP spend');
}

function knl_pick_reward_row(PDO $pdo, string $rarity): ?array
{
    // Tirada desde mw_avatars; item_id del inventario enlaza con knd_avatar_items por nombre/slug/basename(thumb).
    return knl_neural_pick_mw_with_item($pdo, $rarity);
}

/** Portrait URL: mw_avatars.image only (cache-busted). No knd_avatar_items.asset_path fallback. */
function knl_portrait_url(array $row): string
{
    $img = $row['image'] ?? null;
    if (!is_string($img) || trim($img) === '') {
        return '';
    }

    return knl_bust_avatar_image_url($img);
}

// ── Dry-run: no DB writes ─────────────────────────────────────────
if (!$SANDBOX_MODE) {
    knl_ag_log('sandbox_off', 'dry-run response');
    $mockRarity = knl_resolve_rarity($pack['rates'], false, false);
    echo json_encode([
        'success'      => true,
        'dry_run'      => true,
        'aguacate'     => 'sandbox_off',
        'avatar'       => [
            'id'     => 0,
            'name'   => 'Simulated entity',
            'rarity' => $mockRarity,
            'class'  => 'probe',
            'image'  => '',
            'stats'  => ['mind' => 0, 'focus' => 0, 'speed' => 0, 'luck' => 0],
        ],
        'is_duplicate' => true,
        'ke_gained'    => 0,
        'pity_counter' => ['legendary' => 0, 'epic' => 0],
        'cost_paid'    => ['kp' => $costKp],
        'new_balance'  => ['knd_points' => get_available_points($pdo, $userId)],
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT IGNORE INTO knl_neural_link_pity (user_id, pity_legendary, pity_epic) VALUES (?, 0, 0)'
    )->execute([$userId]);

    $stmtPity = $pdo->prepare(
        'SELECT pity_legendary, pity_epic FROM knl_neural_link_pity WHERE user_id = ? FOR UPDATE'
    );
    $stmtPity->execute([$userId]);
    $pityRow = $stmtPity->fetch(PDO::FETCH_ASSOC);
    if (!$pityRow) {
        throw new RuntimeException('Pity row missing');
    }

    $pityLegendary = (int) $pityRow['pity_legendary'] + 1;
    $pityEpic      = (int) $pityRow['pity_epic'] + 1;

    $forceLegendary = $pityLegendary >= (int) $pack['pity_legendary'];
    $forceEpic      = !$forceLegendary && $pityEpic >= (int) $pack['pity_epic'];

    $resolvedRarity = knl_resolve_rarity($pack['rates'], $forceLegendary, $forceEpic);

    $available = get_available_points($pdo, $userId);
    if ($available < $costKp) {
        $pdo->rollBack();
        knl_ag_log('kp_debit', "need {$costKp} have {$available}");
        echo json_encode([
            'success'  => false,
            'error'    => 'insufficient_kp',
            'aguacate' => 'kp_debit',
        ]);
        exit;
    }

    knl_ledger_spend_kp($pdo, $userId, $costKp);

    $picked = knl_pick_reward_row($pdo, $resolvedRarity);
    if (!$picked) {
        knl_ag_log('pick_item', 'empty pool');
        throw new RuntimeException('No avatar items for rarity');
    }

    $itemId    = (int) $picked['item_id'];
    $mwId      = (int) $picked['mw_id'];
    $stmtOwn   = $pdo->prepare('SELECT 1 FROM knd_user_avatar_inventory WHERE user_id = ? AND item_id = ? LIMIT 1');
    $stmtOwn->execute([$userId, $itemId]);
    $isDuplicate = (bool) $stmtOwn->fetchColumn();

    $keGained = 0;
    if ($isDuplicate) {
        $keGained = KNL_KE_REWARD[$resolvedRarity] ?? 10;
        try {
            $pdo->prepare(
                'UPDATE knd_user_avatar_inventory
                 SET knowledge_energy = knowledge_energy + ?
                 WHERE user_id = ? AND item_id = ?'
            )->execute([$keGained, $userId, $itemId]);
        } catch (PDOException $e) {
            knl_ag_log('inventory', 'KE update skipped: ' . $e->getMessage());
            $keGained = 0;
        }
    } else {
        try {
            $pdo->prepare(
                'INSERT INTO knd_user_avatar_inventory
                 (user_id, item_id, acquired_at, knowledge_energy, avatar_level)
                 VALUES (?, ?, NOW(), 0, 1)'
            )->execute([$userId, $itemId]);
        } catch (PDOException $e) {
            $pdo->prepare(
                'INSERT IGNORE INTO knd_user_avatar_inventory (user_id, item_id, acquired_at)
                 VALUES (?, ?, NOW())'
            )->execute([$userId, $itemId]);
        }
    }

    if ($forceLegendary) {
        $pityLegendary = 0;
        $pityEpic      = 0;
    } elseif ($forceEpic) {
        $pityEpic = 0;
    } elseif ($resolvedRarity === 'legendary') {
        $pityLegendary = 0;
        $pityEpic      = 0;
    } elseif ($resolvedRarity === 'epic') {
        $pityEpic = 0;
    }

    $pdo->prepare(
        'UPDATE knl_neural_link_pity SET pity_legendary = ?, pity_epic = ? WHERE user_id = ?'
    )->execute([$pityLegendary, $pityEpic, $userId]);

    $hasCostKp = knl_drop_log_has_column($pdo, 'cost_kp');
    $hasItemId = knl_drop_log_has_column($pdo, 'item_id');

    if ($hasCostKp && $hasItemId) {
        $pdo->prepare(
            'INSERT INTO knd_drop_log
             (user_id, drop_type, avatar_id, item_id, rarity_resolved, is_duplicate, ke_gained, cost_kp, cost_coins, cost_gems, opened_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())'
        )->execute([
            $userId,
            $dropType,
            $mwId,
            $itemId,
            $resolvedRarity,
            (int) $isDuplicate,
            $keGained,
            $costKp,
        ]);
    } elseif ($hasItemId) {
        $pdo->prepare(
            'INSERT INTO knd_drop_log
             (user_id, drop_type, avatar_id, item_id, rarity_resolved, is_duplicate, ke_gained, cost_coins, cost_gems, opened_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $userId,
            $dropType,
            $mwId,
            $itemId,
            $resolvedRarity,
            (int) $isDuplicate,
            $keGained,
            $costKp,
            0,
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO knd_drop_log
             (user_id, drop_type, avatar_id, rarity_resolved, is_duplicate, ke_gained, cost_coins, cost_gems, opened_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $userId,
            $dropType,
            $mwId,
            $resolvedRarity,
            (int) $isDuplicate,
            $keGained,
            $costKp,
            0,
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    knl_ag_log('commit', $e->getMessage());
    knl_fail(500, 'server_error', 'commit', $e->getMessage(), true);
}

$stmtStats = $pdo->prepare(
    'SELECT mind, focus, speed, luck FROM mw_avatar_stats WHERE avatar_id = ? LIMIT 1'
);
$stmtStats->execute([$mwId]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: ['mind' => 0, 'focus' => 0, 'speed' => 0, 'luck' => 0];

$portrait = knl_portrait_url($picked);

echo json_encode([
    'success'       => true,
    'avatar'        => [
        'id'     => $itemId,
        'name'   => $picked['mw_name'] ?: $picked['item_name'],
        'rarity' => $resolvedRarity,
        'class'  => $picked['class'],
        'image'  => $portrait,
        'stats'  => $stats,
    ],
    'is_duplicate'  => $isDuplicate,
    'ke_gained'     => $keGained,
    'pity_counter'  => [
        'legendary' => $pityLegendary,
        'epic'      => $pityEpic,
    ],
    'cost_paid'     => ['kp' => $costKp],
    'new_balance'   => ['knd_points' => get_available_points($pdo, $userId)],
]);
