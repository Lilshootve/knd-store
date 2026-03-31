<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/support_credits.php';
require_once __DIR__ . '/../../includes/knd_daily.php';
require_once __DIR__ . '/../../includes/knd_xp.php';

define('AU_ENTRY_MIN', 10);
define('AU_ENTRY_MAX', 5000);
define('AU_PAYOUT_RATIO', 1.7);
define('AU_XP_WIN', 10);
define('AU_XP_LOSE', 2);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();
    api_require_verified_email();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    rate_limit_guard($pdo, "au_roll_user:{$userId}", 5, 30);
    rate_limit_guard($pdo, "au_roll_ip:{$ip}", 10, 60);

    if (has_risk_flag($pdo, $userId)) {
        json_error('ACCOUNT_FLAGGED', 'Your account has been flagged. Contact support.', 403);
    }

    $choice = strtolower(trim($_POST['choice'] ?? ''));
    if (!in_array($choice, ['under', 'above'], true)) {
        json_error('INVALID_CHOICE', 'Choice must be "under" or "above".');
    }

    $entryKp = isset($_POST['entry_kp']) ? (int) $_POST['entry_kp'] : 200;
    if ($entryKp < AU_ENTRY_MIN || $entryKp > AU_ENTRY_MAX) {
        json_error('INVALID_ENTRY', 'Entry must be between ' . AU_ENTRY_MIN . ' and ' . AU_ENTRY_MAX . ' KP.');
    }
    $payoutKp = (int) floor($entryKp * AU_PAYOUT_RATIO);

    $pdo->beginTransaction();
    try {
        $available = get_available_points($pdo, $userId);
        if ($available < $entryKp) {
            $pdo->rollBack();
            json_error('INSUFFICIENT_POINTS', 'Not enough KND Points. Need ' . $entryKp . ' KP.', 400);
        }

        $now = gmdate('Y-m-d H:i:s');

        $pdo->prepare(
            "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
             VALUES (?, 'adjustment', 0, 'spend', 'spent', ?, ?)"
        )->execute([$userId, -$entryKp, $now]);

        $rolled = random_int(1, 10);
        $win = ($choice === 'under' && $rolled <= 5) || ($choice === 'above' && $rolled >= 6);
        $payout = 0;
        $xp = $win ? AU_XP_WIN : AU_XP_LOSE;

        if ($win) {
            $payout = $payoutKp;
            $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+12 months'));
            $pdo->prepare(
                "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
                 VALUES (?, 'adjustment', 0, 'earn', 'available', ?, ?, ?, ?)"
            )->execute([$userId, $payout, $now, $expiresAt, $now]);
        }

        $pdo->prepare(
            "INSERT INTO above_under_rolls (user_id, choice, rolled_value, is_win, entry_points, payout_points, xp_awarded, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$userId, $choice, $rolled, $win ? 1 : 0, $entryKp, $payout, $xp, $now]);
        $rollId = (int) $pdo->lastInsertId();

        $xpResult = xp_add($pdo, $userId, $xp, $win ? 'insight_win' : 'insight_lose', 'above_under_roll', $rollId);

        $pdo->commit();

        unset($_SESSION['sc_badge_cache'], $_SESSION['xp_badge_cache']);

        try {
            mission_increment($pdo, $userId, 'play_insight_5');
        } catch (\Throwable $e) {
            error_log('mission_increment AU error: ' . $e->getMessage());
        }

        $newBalance = get_available_points($pdo, $userId);

        $resp = [
            'rolled'         => $rolled,
            'win'            => $win,
            'choice'         => $choice,
            'entry'          => $entryKp,
            'payout'         => $payout,
            'points_balance' => $newBalance,
            'xp_awarded'     => $xp,
            'xp_delta'       => $xp,
            'xp_total'       => $xpResult['new_xp'] ?? 0,
            'level'          => $xpResult['new_level'],
        ];
        if ($xpResult['level_up']) {
            $resp['level_up'] = true;
            $resp['old_level'] = $xpResult['old_level'];
            $resp['new_level'] = $xpResult['new_level'];
        } else {
            $resp['level_up'] = false;
        }
        json_success($resp);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (\Throwable $e) {
    error_log('above-under roll error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
