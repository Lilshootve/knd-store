<?php
/**
 * Admin audit log - registra acciones administrativas.
 * Requiere: session, config (getDBConnection), _rbac (admin_id, admin_username).
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
if (!function_exists('admin_id')) require_once __DIR__ . '/_rbac.php';

function admin_log_action(string $action, array $meta = []): void {
    try {
        $pdo = getDBConnection();
        if (!$pdo) return;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $adminId = admin_id();
        $adminUserStr = function_exists('admin_username') ? admin_username() : ($_SESSION['admin_username'] ?? 'admin');

        $metaJson = empty($meta) ? null : json_encode($meta);

        $hasAdminId = false;
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM admin_audit_logs LIKE 'admin_id'")->rowCount();
            $hasAdminId = ($cols > 0);
        } catch (\Throwable $e) {}

        if ($hasAdminId) {
            $stmt = $pdo->prepare(
                'INSERT INTO admin_audit_logs (admin_id, admin_ip, admin_user, action, meta_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$adminId, $ip, $adminUserStr, $action, $metaJson]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO admin_audit_logs (admin_ip, admin_user, action, meta_json, created_at) VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$ip, $adminUserStr, $action, $metaJson]);
        }
    } catch (\Throwable $e) {
        error_log('admin_log_action: ' . $e->getMessage());
    }
}
