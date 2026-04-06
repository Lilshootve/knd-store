<?php
require_once __DIR__ . '/config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';
require_once BASE_PATH . '/includes/support_credits.php';

require_login();
require_verified_email();

$csrfToken = csrf_token();
$userId = current_user_id();
$ptsRate = defined('SUPPORT_POINTS_PER_USD') ? SUPPORT_POINTS_PER_USD : 100;

$pdo = getDBConnection();
$balance = ['pending' => 0, 'available' => 0, 'locked' => 0, 'spent_total' => 0, 'expiring_soon' => []];
$availableNet = 0;
try {
    if ($pdo) {
        release_available_points_if_due($pdo, $userId);
        expire_points_if_due($pdo, $userId);
        $balance = get_points_balance($pdo, $userId);
        $availableNet = get_available_points($pdo, $userId);
    }
} catch (\Throwable $e) {
    error_log('support-credits page balance error: ' . $e->getMessage());
}

$payments = [];
try {
    if ($pdo) {
        $stmt = $pdo->prepare(
            "SELECT sp.*, COALESCE(pl.points, 0) AS points FROM support_payments sp
             LEFT JOIN points_ledger pl ON pl.source_type='support_payment' AND pl.source_id=sp.id AND pl.entry_type='earn'
             WHERE sp.user_id = ? ORDER BY sp.created_at DESC LIMIT 20"
        );
        $stmt->execute([$userId]);
        $payments = $stmt->fetchAll();
    }
} catch (\Throwable $e) {
    $payments = [];
}

$kscCss = __DIR__ . '/assets/css/knd-support-credits.css';
$kscJs = __DIR__ . '/assets/js/knd-support-credits.js';
$v = file_exists($kscCss) ? filemtime($kscCss) : 0;
$vjs = file_exists($kscJs) ? filemtime($kscJs) : 0;

$extraHead = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap">' . "\n";
$extraHead .= '    <link rel="stylesheet" href="/assets/css/knd-support-credits.css?v=' . $v . '">' . "\n";
$extraHead .= '    <script src="/assets/js/knd-support-credits.js?v=' . $vjs . '" defer></script>' . "\n";

$seoTitle = t('sc.page_title', 'KND Points') . ' | KND Store';
$seoDesc = t('sc.page_desc', 'Get KND Points (KP) and redeem them for services and rewards.');

echo generateHeader($seoTitle, $seoDesc, $extraHead, true);
echo generateNavigation();
include __DIR__ . '/sections/knd_support_credits.php';
echo generateFooter();
echo generateScripts();
