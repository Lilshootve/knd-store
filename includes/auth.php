<?php
// KND Store - Unified auth helpers (single user system: dr_user_id)

require_once __DIR__ . '/session.php';

function is_logged_in(): bool {
    return !empty($_SESSION['dr_user_id']);
}

function current_user_id(): ?int {
    return $_SESSION['dr_user_id'] ?? null;
}

function current_username(): ?string {
    return $_SESSION['dr_username'] ?? null;
}

// Note: config.php (not in git) also defines isLoggedIn() and getCurrentUser().
// Both must be updated to use $_SESSION['dr_user_id'] instead of $_SESSION['user_id'].
// All new code should use is_logged_in(), current_user_id(), require_login() from this file.

function auth_login(int $userId, string $username): void {
    session_regenerate_id(true);
    $_SESSION['dr_user_id'] = $userId;
    $_SESSION['dr_username'] = $username;
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Check if the logged-in user has a verified email.
 * Users without an email on file (legacy accounts) are treated as verified.
 */
function is_email_verified(): bool {
    if (!is_logged_in()) return false;
    try {
        require_once __DIR__ . '/config.php';
        $pdo = getDBConnection();
        if (!$pdo) return false;
        $stmt = $pdo->prepare('SELECT email, email_verified FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([current_user_id()]);
        $row = $stmt->fetch();
        if (!$row) return false;
        if (empty($row['email'])) return true;
        return (int) $row['email_verified'] === 1;
    } catch (\Throwable $e) {
        return true;
    }
}

/**
 * Redirect to login if not authenticated. Use at top of protected pages.
 * Preserves embed=1 when redirecting from embed context (e.g. arena iframe) so auth renders without header.
 */
function require_login(): void {
    if (!is_logged_in()) {
        $url = '/auth.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/');
        if (isset($_GET['embed']) && $_GET['embed'] === '1') {
            $url .= '&embed=1';
        }
        header('Location: ' . $url);
        exit;
    }
}

/**
 * Redirect to auth page if email not verified. Call after require_login().
 * Preserves embed=1 when redirecting from embed context.
 */
function require_verified_email(): void {
    if (!is_email_verified()) {
        $url = '/auth.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/');
        if (isset($_GET['embed']) && $_GET['embed'] === '1') {
            $url .= '&embed=1';
        }
        header('Location: ' . $url);
        exit;
    }
}

/**
 * API guard: return JSON error if not authenticated.
 */
function api_require_login(): void {
    if (!is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'You must be logged in.']
        ]);
        exit;
    }
}

/**
 * API guard: return JSON error if email not verified.
 */
function api_require_verified_email(): void {
    api_require_login();
    if (!is_email_verified()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'error' => ['code' => 'EMAIL_NOT_VERIFIED', 'message' => 'Please verify your email first.']
        ]);
        exit;
    }
}
