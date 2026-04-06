<?php
/**
 * POST — Consume session pending orb, apply reward, set last_orb_claim_at (cooldown).
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
    rate_limit_guard($pdo, "holo_orb_claim:{$userId}", 6, 60);

    $pending = $_SESSION[HOLO_ORB_SESSION_KEY] ?? null;
    if (!is_array($pending) || !isset($pending['exp']) || (int) $pending['exp'] < time()) {
        unset($_SESSION[HOLO_ORB_SESSION_KEY]);
        json_error('NO_PENDING_ORB', 'No orb to claim or it expired. Wait for a new one.', 400);
    }

    if (!holo_orb_validate_pending_shape($pending)) {
        unset($_SESSION[HOLO_ORB_SESSION_KEY]);
        json_error('INVALID_PENDING', 'Invalid orb state.', 400);
    }

    $type = (string) $pending['type'];
    $amount = (int) $pending['amount'];

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare('SELECT id, last_orb_claim_at FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_error('USER_NOT_FOUND', 'User not found.', 404);
        }

        $lastRaw = $row['last_orb_claim_at'] ?? null;
        if ($lastRaw !== null && $lastRaw !== '') {
            $lastTs = strtotime((string) $lastRaw . ' UTC');
            if ($lastTs !== false && (time() - $lastTs) < HOLO_ORB_COOLDOWN_SEC) {
                $wait = HOLO_ORB_COOLDOWN_SEC - (time() - $lastTs);
                unset($_SESSION[HOLO_ORB_SESSION_KEY]);
                if ($ownTx && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                json_error('COOLDOWN', "Wait {$wait}s before claiming again.", 429);
            }
        }

        $result = holo_orb_apply_reward($pdo, $userId, $type, $amount);

        $nowUtc = gmdate('Y-m-d H:i:s');
        $pdo->prepare('UPDATE users SET last_orb_claim_at = ? WHERE id = ?')->execute([$nowUtc, $userId]);

        unset($_SESSION[HOLO_ORB_SESSION_KEY]);

        if ($ownTx) {
            $pdo->commit();
        }

        $payload = array_merge([
            'success' => true,
        ], $result);

        json_success($payload);
    } catch (\Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
} catch (\Throwable $e) {
    error_log('api/orb/claim error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}
