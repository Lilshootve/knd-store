<?php
/**
 * POST — Create server-side pending holo orb (type + amount) in session.
 * Client must call claim.php on click; reward is never trusted from the browser.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/holo_orb.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }
    csrf_guard();
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_FAIL', 'Database error.', 500);
    }

    $userId = (int) current_user_id();
    rate_limit_guard($pdo, "holo_orb_offer:{$userId}", 20, 60);

    $rem = holo_orb_cooldown_remaining_seconds($pdo, $userId);
    if ($rem > 0) {
        json_error('COOLDOWN', "Wait {$rem}s before a new orb can appear.", 429);
    }

    $pending = $_SESSION[HOLO_ORB_SESSION_KEY] ?? null;
    if (is_array($pending) && isset($pending['exp']) && (int) $pending['exp'] > time()) {
        if (holo_orb_validate_pending_shape($pending)) {
            json_success([
                'success'       => true,
                'reward_type'   => (string) $pending['type'],
                'amount'        => (int) $pending['amount'],
                'expires_at'    => (int) $pending['exp'],
                'reuse_pending' => true,
            ]);
        }
        unset($_SESSION[HOLO_ORB_SESSION_KEY]);
    }

    $rolled = holo_orb_roll_reward();
    $ttl = random_int(120, 300);
    $_SESSION[HOLO_ORB_SESSION_KEY] = [
        'type'   => $rolled['type'],
        'amount' => $rolled['amount'],
        'exp'    => time() + $ttl,
    ];

    json_success([
        'success'     => true,
        'reward_type' => $rolled['type'],
        'amount'      => $rolled['amount'],
        'expires_at'  => time() + $ttl,
    ]);
} catch (\Throwable $e) {
    error_log('api/orb/offer error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}
