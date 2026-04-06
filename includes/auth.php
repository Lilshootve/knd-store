<?php
// KND Store - Unified auth helpers (single user system: dr_user_id)

require_once __DIR__ . '/session.php';

function is_logged_in(): bool {
    return !empty($_SESSION['dr_user_id']) || !empty($_SESSION['user_id']);
}

function current_user_id(): ?int {
    if (!empty($_SESSION['dr_user_id'])) {
        return (int) $_SESSION['dr_user_id'];
    }
    if (!empty($_SESSION['user_id'])) {
        return (int) $_SESSION['user_id'];
    }
    return null;
}

function current_username(): ?string {
    return $_SESSION['dr_username'] ?? null;
}

/**
 * Primary workspace business (first membership by business_users.id ASC).
 * Future: user-selected tenant; see current_business_ids().
 */
function current_business_id(): ?int {
    if (!isset($_SESSION['business_id']) || $_SESSION['business_id'] === '' || $_SESSION['business_id'] === false) {
        return null;
    }
    return (int) $_SESSION['business_id'];
}

/** All active business IDs for the user (same order as primary). */
function current_business_ids(): array {
    if (empty($_SESSION['business_ids']) || !is_array($_SESSION['business_ids'])) {
        return [];
    }
    return array_values(array_filter(array_map('intval', $_SESSION['business_ids']), static fn (int $id) => $id > 0));
}

// Note: config.php (not in git) also defines isLoggedIn() and getCurrentUser().
// Both must be updated to use $_SESSION['dr_user_id'] instead of $_SESSION['user_id'].
// All new code should use is_logged_in(), current_user_id(), require_login() from this file.

function auth_login(int $userId, string $username): void {
    session_regenerate_id(true);
    $_SESSION['dr_user_id'] = $userId;
    $_SESSION['dr_username'] = $username;
    $_SESSION['user_id'] = $userId;
}

/**
 * Load tenant memberships into session: business_id (primary) and business_ids (all).
 */
function auth_refresh_session_tenant(?PDO $pdo = null): void
{
    if (!is_logged_in()) {
        unset($_SESSION['business_id'], $_SESSION['business_ids']);
        return;
    }

    if ($pdo === null) {
        require_once __DIR__ . '/config.php';
        $pdo = getDBConnection();
    }
    if (!$pdo) {
        unset($_SESSION['business_id'], $_SESSION['business_ids']);
        return;
    }

    $uid = current_user_id();
    if (!$uid) {
        unset($_SESSION['business_id'], $_SESSION['business_ids']);
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT bu.business_id
         FROM business_users bu
         INNER JOIN businesses b ON b.id = bu.business_id
         WHERE bu.user_id = ? AND b.active = 1
         ORDER BY bu.id ASC'
    );
    $stmt->execute([$uid]);
    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bid = (int) ($row['business_id'] ?? 0);
        if ($bid > 0) {
            $ids[] = $bid;
        }
    }

    if ($ids === []) {
        unset($_SESSION['business_id'], $_SESSION['business_ids']);
        return;
    }

    $primaryBusinessId = $ids[0];
    $_SESSION['business_id'] = $primaryBusinessId;
    $_SESSION['business_ids'] = $ids;
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
