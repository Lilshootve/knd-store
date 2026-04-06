<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/support_credits.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST required.', 405);
    }

    api_require_verified_email();
    csrf_guard();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();

    // Rate limit: 5 payments per 60s per user
    rate_limit_guard($pdo, "support_create:{$userId}", 5, 60);
    // Rate limit: 10 per 60s per IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "support_create_ip:{$ip}", 10, 60);

    if (has_risk_flag($pdo, $userId)) {
        json_error('ACCOUNT_FLAGGED', 'Your account is restricted. Contact support.', 403);
    }

    $method = trim($_POST['method'] ?? '');
    $amountRaw = $_POST['amount_usd'] ?? '';
    $notes = trim($_POST['notes'] ?? '') ?: null;
    $currency = trim($_POST['currency'] ?? 'USD') ?: 'USD';

    if (!in_array($method, SUPPORT_ALLOWED_METHODS, true)) {
        json_error('INVALID_METHOD', 'Invalid payment method.', 400);
    }

    $amount = (float) $amountRaw;
    if ($amount < SUPPORT_MIN_AMOUNT_USD || $amount > SUPPORT_MAX_AMOUNT_USD) {
        json_error('INVALID_AMOUNT', 'Amount must be between $' . SUPPORT_MIN_AMOUNT_USD . ' and $' . SUPPORT_MAX_AMOUNT_USD . '.', 400);
    }

    $result = create_support_payment($pdo, $userId, $method, $amount, $currency, $notes);

    unset($_SESSION['sc_badge_cache']);
    json_success($result);
} catch (\Throwable $e) {
    error_log('support-credits/create_payment error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
