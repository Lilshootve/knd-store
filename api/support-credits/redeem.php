<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../includes/support_credits.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST required.', 405);
    }

    api_require_verified_email();
    csrf_guard();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();

    rate_limit_guard($pdo, "redeem:{$userId}", 5, 60);

    if (has_risk_flag($pdo, $userId)) {
        json_error('ACCOUNT_FLAGGED', 'Your account is restricted. Contact support.', 403);
    }

    $rewardId = (int) ($_POST['reward_id'] ?? 0);
    if ($rewardId <= 0) {
        json_error('INVALID_REWARD', 'Invalid reward ID.', 400);
    }

    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM rewards_catalog WHERE id = ? AND is_active = 1 FOR UPDATE'
        );
        $stmt->execute([$rewardId]);
        $reward = $stmt->fetch();

        if (!$reward) {
            $pdo->rollBack();
            json_error('REWARD_NOT_FOUND', 'Reward not found or inactive.', 404);
        }

        if ($reward['stock'] !== null && (int) $reward['stock'] <= 0) {
            $pdo->rollBack();
            json_error('OUT_OF_STOCK', 'This reward is out of stock.', 400);
        }

        $available = get_available_points($pdo, $userId);
        $cost = (int) $reward['points_cost'];

        if ($available < $cost) {
            $pdo->rollBack();
            json_error('INSUFFICIENT_POINTS', 'Not enough available credits. You have ' . $available . ' but need ' . $cost . '.', 400);
        }

        $now = gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "INSERT INTO reward_redemptions (user_id, reward_id, points_spent, status, created_at, updated_at)
             VALUES (?, ?, ?, 'requested', ?, ?)"
        );
        $stmt->execute([$userId, $rewardId, $cost, $now, $now]);
        $redemptionId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
             VALUES (?, 'redemption', ?, 'spend', 'spent', ?, ?)"
        );
        $stmt->execute([$userId, $redemptionId, $cost, $now]);

        if ($reward['stock'] !== null) {
            $pdo->prepare('UPDATE rewards_catalog SET stock = stock - 1 WHERE id = ?')
                ->execute([$rewardId]);
        }

        $pdo->commit();

        unset($_SESSION['sc_badge_cache']);
        $newBalance = get_available_points($pdo, $userId);

        json_success([
            'redemption_id'   => $redemptionId,
            'reward_title'    => $reward['title'],
            'points_spent'    => $cost,
            'available_after' => $newBalance,
        ]);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (\Throwable $e) {
    error_log('support-credits/redeem error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
