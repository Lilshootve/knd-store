<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/support_credits.php';
require_once __DIR__ . '/../../admin/_rbac.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST required.', 405);
    }

    if (empty($_SESSION['admin_logged_in'])) {
        json_error('ADMIN_REQUIRED', 'Admin access required.', 403);
    }
    if (!admin_has_perm('payments.confirm')) {
        json_error('FORBIDDEN', 'Insufficient permissions.', 403);
    }

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');
    $notes = trim($_POST['notes'] ?? '') ?: null;

    if ($paymentId <= 0) {
        json_error('INVALID_PAYMENT', 'Invalid payment ID.', 400);
    }

    $validActions = ['confirm', 'reject', 'dispute', 'refund'];
    if (!in_array($action, $validActions, true)) {
        json_error('INVALID_ACTION', 'Action must be one of: ' . implode(', ', $validActions), 400);
    }

    $result = admin_update_payment($pdo, $paymentId, $action, $notes);

    if (isset($result['error'])) {
        json_error('OPERATION_FAILED', $result['error'], 400);
    }

    json_success($result);
} catch (\Throwable $e) {
    error_log('support-credits/admin_confirm_payment error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
