<?php
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('system.purge_cache');

require_once __DIR__ . '/_audit.php';
admin_log_action('purge_cache', ['reason' => 'Manual purge']);

$results = [];

// 1. Send LiteSpeed purge header for /admin/*
if (isset($_SERVER['HTTP_X_LSCACHE']) || stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'litespeed') !== false) {
    header('X-LiteSpeed-Purge: /admin/*');
    $results[] = 'LiteSpeed: sent X-LiteSpeed-Purge: /admin/*';
} else {
    $results[] = 'LiteSpeed: not detected (header not sent)';
}

// 2. Clear OPcache for admin PHP files if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    $results[] = 'OPcache: full reset OK';
} elseif (function_exists('opcache_invalidate')) {
    $adminFiles = glob(__DIR__ . '/*.php');
    $count = 0;
    foreach ($adminFiles as $f) {
        if (opcache_invalidate($f, true)) $count++;
    }
    $results[] = "OPcache: invalidated $count admin files";
} else {
    $results[] = 'OPcache: not available';
}

// 3. Clear PHP stat cache
clearstatcache(true);
$results[] = 'PHP stat cache: cleared';

// 4. Touch .htaccess to bust any file-mtime-based caches
$htaccess = __DIR__ . '/.htaccess';
if (file_exists($htaccess)) {
    touch($htaccess);
    $results[] = '.htaccess: touched (mtime updated)';
}

// 5. Set a flash message and redirect back
$_SESSION['purge_results'] = $results;
header('Location: /admin/orders.php?purged=1&tab=' . urlencode($_GET['tab'] ?? 'paypal'));
exit;
