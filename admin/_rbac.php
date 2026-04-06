<?php
/**
 * RBAC - Role-Based Access Control for admin panel.
 * Requires: session (admin_id, admin_role), config (getDBConnection).
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';

// Role -> permissions map. Owner gets all (checked via role name).
$GLOBALS['_admin_role_permissions'] = [
    'owner'   => ['*'], // all (incl. admin_users.view, .create, .edit, .reset_password)
    'manager' => [
        'dashboard.view', 'users.view', 'orders.view', 'payments.view', 'payments.confirm',
        'rewards.edit', 'leaderboard.view', 'leaderboard.reset_season', 'logs.view',
        'economy.adjust_kp', 'system.create_test_order', 'system.storage_diag', 'system.purge_cache',
    ],
    'support' => [
        'dashboard.view', 'users.view', 'orders.view', 'payments.view', 'payments.confirm',
        'rewards.edit', 'logs.view',
    ],
    'viewer'  => [
        'dashboard.view', 'users.view', 'orders.view', 'payments.view',
        'leaderboard.view', 'logs.view',
    ],
];

function admin_role(): string {
    return $_SESSION['admin_role'] ?? '';
}

function admin_id(): ?int {
    $id = $_SESSION['admin_id'] ?? null;
    return $id !== null ? (int) $id : null;
}

function admin_username(): string {
    return $_SESSION['admin_username'] ?? 'admin';
}

function admin_has_perm(string $perm): bool {
    $role = admin_role();
    if ($role === '') return false;
    $perms = $GLOBALS['_admin_role_permissions'][$role] ?? [];
    if (in_array('*', $perms, true)) return true;
    return in_array($perm, $perms, true);
}

function admin_require_perm(string $perm): void {
    if (!admin_has_perm($perm)) {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>Forbidden</title></head><body style="font-family:sans-serif;padding:2rem;background:#0a0a0a;color:#fff;">';
        echo '<h1>403 Forbidden</h1><p>You do not have permission to access this resource.</p>';
        echo '<p><a href="/admin/">Back to Admin</a></p></body></html>';
        exit;
    }
}

function admin_can_any(array $perms): bool {
    foreach ($perms as $p) {
        if (admin_has_perm($p)) return true;
    }
    return false;
}

/**
 * Check manager limits for economy.adjust_kp: abs(delta) <= 10000, max 5/day.
 * Call after admin_require_perm('economy.adjust_kp').
 * @throws \Exception if limit exceeded
 */
function admin_check_economy_limits(PDO $pdo, int $delta): void {
    if (admin_has_perm('*')) return; // owner
    if (!admin_has_perm('economy.adjust_kp')) return;

    if (abs($delta) > 10000) {
        throw new \Exception('Manager limit: adjustment cannot exceed 10,000 KP in absolute value.');
    }
    $aid = admin_id();
    $uname = admin_username();
    if ($aid === null && $uname === '') return;
    $today = gmdate('Y-m-d 00:00:00');
    $hasAdminId = false;
    try {
        $hasAdminId = $pdo->query("SHOW COLUMNS FROM admin_audit_logs LIKE 'admin_id'")->rowCount() > 0;
    } catch (\Throwable $e) {}
    if ($hasAdminId && $aid !== null) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM admin_audit_logs
             WHERE admin_id = ? AND created_at >= ?
             AND action IN ('kp_grant_available','kp_grant_pending','kp_remove_points','kp_force_release')"
        );
        $stmt->execute([$aid, $today]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM admin_audit_logs
             WHERE admin_user = ? AND created_at >= ?
             AND action IN ('kp_grant_available','kp_grant_pending','kp_remove_points','kp_force_release')"
        );
        $stmt->execute([$uname, $today]);
    }
    $count = (int) $stmt->fetchColumn();
    if ($count >= 5) {
        throw new \Exception('Manager limit: maximum 5 KP adjustments per day reached. Try tomorrow or contact owner.');
    }
}
