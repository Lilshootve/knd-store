<?php
/**
 * GET /api/drop/status.php
 * Returns user's drop rate limit status (used, remaining, resets_at)
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/rate_limit.php';

define('DROP_MAX_PER_HOUR', 10);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
    }

    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = current_user_id();
    $status = rate_limit_status($pdo, "drop_user:{$userId}", DROP_MAX_PER_HOUR, 3600);

    json_success($status);
} catch (\Throwable $e) {
    error_log('api/drop/status: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}
