<?php
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('system.storage_diag');
require_once __DIR__ . '/../includes/storage.php';

header('Content-Type: text/html; charset=utf-8');
ensure_storage_ready();

$ordersFile = storage_path('orders.json');
$logFile    = storage_path('logs/payments.log');

$ordersExist  = file_exists($ordersFile);
$ordersSize   = $ordersExist ? filesize($ordersFile) : 0;
$ordersCount  = 0;
$lastOrders   = [];

if ($ordersExist) {
    $all = read_json_array($ordersFile);
    $ordersCount = count($all);
    $lastOrders = array_slice($all, -5);
}

$logLines = [];
if (file_exists($logFile)) {
    $fp = @fopen($logFile, 'r');
    if ($fp) {
        $allLines = [];
        while (($line = fgets($fp)) !== false) {
            $allLines[] = $line;
        }
        fclose($fp);
        $logLines = array_slice($allLines, -5);
    }
}

$bankFile  = storage_path('bank_transfer_requests.json');
$otherFile = storage_path('other_payment_requests.json');
$bankCount = file_exists($bankFile) ? count(read_json_array($bankFile)) : 0;
$otherCount = file_exists($otherFile) ? count(read_json_array($otherFile)) : 0;
?>
<!DOCTYPE html>
<html>
<head><title>Storage Debug — KND Admin</title>
<style>
    body { font-family: 'Courier New', monospace; background: #0a0a0a; color: #e0e0e0; padding: 2rem; }
    h1 { color: #00d4ff; margin-bottom: 1.5rem; }
    .section { background: #111; border: 1px solid #222; padding: 1rem; margin-bottom: 1rem; border-radius: 6px; }
    .label { color: #888; }
    .ok { color: #4ade80; }
    .warn { color: #facc15; }
    pre { background: #050505; padding: 0.8rem; overflow-x: auto; font-size: 0.85rem; border-radius: 4px; }
    a { color: #00d4ff; }
</style>
</head>
<body>
<h1>Storage Debug</h1>
<p><a href="/admin/orders.php">&larr; Back to Admin</a></p>

<div class="section">
    <h3>orders.json</h3>
    <p><span class="label">Exists:</span> <span class="<?php echo $ordersExist ? 'ok' : 'warn'; ?>"><?php echo $ordersExist ? 'YES' : 'NO'; ?></span></p>
    <p><span class="label">File size:</span> <?php echo number_format($ordersSize); ?> bytes</p>
    <p><span class="label">Record count:</span> <?php echo $ordersCount; ?></p>
</div>

<div class="section">
    <h3>Other stores</h3>
    <p><span class="label">Bank Transfer requests:</span> <?php echo $bankCount; ?></p>
    <p><span class="label">WhatsApp Other requests:</span> <?php echo $otherCount; ?></p>
</div>

<div class="section">
    <h3>Last 5 Orders (orders.json)</h3>
    <?php if (empty($lastOrders)): ?>
    <p class="warn">No orders found.</p>
    <?php else: ?>
    <pre><?php
    foreach ($lastOrders as $o) {
        $safe = $o;
        unset($safe['payer_email']);
        if (isset($safe['customer']['email'])) $safe['customer']['email'] = '***';
        echo htmlspecialchars(json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "\n---\n";
    }
    ?></pre>
    <?php endif; ?>
</div>

<div class="section">
    <h3>Last 5 Log Lines (payments.log)</h3>
    <?php if (empty($logLines)): ?>
    <p class="warn">No log entries.</p>
    <?php else: ?>
    <pre><?php
    foreach ($logLines as $line) {
        echo htmlspecialchars(trim($line)) . "\n";
    }
    ?></pre>
    <?php endif; ?>
</div>

<div class="section">
    <h3>Filesystem</h3>
    <p><span class="label">storage_path():</span> <?php echo htmlspecialchars(storage_path()); ?></p>
    <p><span class="label">logs dir exists:</span> <span class="<?php echo is_dir(storage_path('logs')) ? 'ok' : 'warn'; ?>"><?php echo is_dir(storage_path('logs')) ? 'YES' : 'NO'; ?></span></p>
    <p><span class="label">orders.json writable:</span> <span class="<?php echo is_writable($ordersFile) ? 'ok' : 'warn'; ?>"><?php echo is_writable($ordersFile) ? 'YES' : 'NO'; ?></span></p>
    <p><span class="label">payments.log writable:</span> <span class="<?php echo is_writable($logFile) || is_writable(dirname($logFile)) ? 'ok' : 'warn'; ?>"><?php echo is_writable($logFile) || is_writable(dirname($logFile)) ? 'YES' : 'NO'; ?></span></p>
</div>
</body>
</html>
