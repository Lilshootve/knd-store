<?php
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('orders.view');

require_once __DIR__ . '/../includes/storage.php';

ensure_storage_ready();

$ordersFile = storage_path('orders.json');
$bankFile   = storage_path('bank_transfer_requests.json');
$otherFile  = storage_path('other_payment_requests.json');

$validTabs = ['paypal', 'bank', 'whatsapp', 'test'];
$activeTab = in_array($_GET['tab'] ?? '', $validTabs) ? $_GET['tab'] : 'paypal';
$flashMsg = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId   = trim($_POST['order_id'] ?? '');
    $source    = $_POST['source'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';
    $postTab   = in_array($_POST['tab'] ?? '', $validTabs) ? $_POST['tab'] : 'paypal';
    $allowed   = ['pending', 'awaiting_transfer', 'processing', 'paid', 'completed', 'delivered', 'cancelled'];

    $redirectParams = ['tab' => $postTab];
    foreach (['search_paypal','status_paypal','search_bank','status_bank','search_whatsapp','status_whatsapp','search_test','status_test'] as $k) {
        if (!empty($_POST[$k])) $redirectParams[$k] = $_POST[$k];
    }

    $result = 'not_found';

    if ($orderId && $source && in_array($newStatus, $allowed)) {
        $fileMap = [
            'paypal'   => $ordersFile,
            'test'     => $ordersFile,
            'bank'     => $bankFile,
            'whatsapp' => $otherFile,
        ];
        $targetFile = $fileMap[$source] ?? null;

        if ($targetFile) {
            clearstatcache(true, $targetFile);
            $data = read_json_array($targetFile);
            $found = false;
            foreach ($data as $i => $r) {
                $refMatch = isset($r['order_ref']) && strcasecmp($r['order_ref'], $orderId) === 0;
                $idMatch  = isset($r['order_id']) && strcasecmp($r['order_id'], $orderId) === 0;
                $ppMatch  = isset($r['paypal_order_id']) && strcasecmp($r['paypal_order_id'], $orderId) === 0;
                if ($refMatch || $idMatch || $ppMatch) {
                    $data[$i]['status'] = $newStatus;
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $writeOk = write_json_array($targetFile, $data);
                $result = $writeOk ? 'ok' : 'write_failed';
                storage_log('admin_status_update', [
                    'order_id' => $orderId,
                    'new_status' => $newStatus,
                    'file' => basename($targetFile),
                    'write_ok' => $writeOk,
                ]);
            } else {
                storage_log('admin_status_update: not_found', [
                    'order_id' => $orderId,
                    'source' => $source,
                    'file' => basename($targetFile),
                    'record_count' => count($data),
                ]);
            }
        }
    }

    $redirectParams['update_result'] = $result;
    header('Location: /admin/orders.php?' . http_build_query($redirectParams));
    exit;
}

if (isset($_GET['update_result'])) {
    $ur = $_GET['update_result'];
    if ($ur === 'ok')           { $flashMsg = 'Status updated.';                  $flashType = 'success'; }
    elseif ($ur === 'not_found') { $flashMsg = 'Order not found in file.';         $flashType = 'warning'; }
    elseif ($ur === 'write_failed') { $flashMsg = 'Write failed — check server logs.'; $flashType = 'danger'; }
}

if (isset($_GET['purged'])) {
    $purgeDetails = $_SESSION['purge_results'] ?? [];
    unset($_SESSION['purge_results']);
    $flashMsg = 'Cache purged. ' . implode(' | ', $purgeDetails);
    $flashType = 'info';
}

// --- Load ALL datasets unconditionally (regardless of active tab) ---
// These arrays + counters must never be overwritten or conditionally zeroed.
clearstatcache(true, $ordersFile);
clearstatcache(true, $bankFile);
clearstatcache(true, $otherFile);

$allOrders     = read_json_array($ordersFile);
$bankRequests  = read_json_array($bankFile);
$otherRequests = read_json_array($otherFile);

$paypalOrders = [];
$testOrders   = [];
foreach ($allOrders as $r) {
    if (empty($r['status'])) $r['status'] = 'paid';
    $src = $r['source'] ?? null;
    if ($src === 'test') {
        $testOrders[] = $r;
    } elseif ($src === 'paypal' || !empty($r['paypal_order_id'])) {
        $paypalOrders[] = $r;
    } else {
        $paypalOrders[] = $r;
    }
}
foreach ($bankRequests as $i => $r) {
    if (empty($r['status'])) $bankRequests[$i]['status'] = 'pending';
}
foreach ($otherRequests as $i => $r) {
    if (empty($r['status'])) $otherRequests[$i]['status'] = 'pending';
}

// --- Global counters — computed once, used in all tabs and diagnostics ---
$countPaypal = count($paypalOrders);
$countTest   = count($testOrders);
$countBank   = count($bankRequests);
$countOther  = count($otherRequests);
$countTotal  = count($allOrders);

$diagFiles = [
    'orders.json' => $ordersFile,
    'bank_transfer_requests.json' => $bankFile,
    'other_payment_requests.json' => $otherFile,
];
$diagData = [];
foreach ($diagFiles as $label => $dfp) {
    clearstatcache(true, $dfp);
    $exists = file_exists($dfp);
    $diagData[$label] = [
        'path'     => $dfp,
        'exists'   => $exists,
        'readable' => $exists && is_readable($dfp),
        'writable' => $exists && is_writable($dfp),
        'size'     => $exists ? filesize($dfp) : 0,
        'mtime'    => $exists ? date('Y-m-d H:i:s', filemtime($dfp)) : '-',
    ];
}
$diagData['orders.json']['count_paypal'] = $countPaypal;
$diagData['orders.json']['count_test']   = $countTest;
$diagData['orders.json']['count_total']  = $countTotal;
$diagData['bank_transfer_requests.json']['count'] = $countBank;
$diagData['other_payment_requests.json']['count'] = $countOther;

$allStatuses = ['pending', 'awaiting_transfer', 'processing', 'paid', 'completed', 'delivered', 'cancelled'];

function getStatusClass(string $status): string {
    $map = [
        'pending' => 'status-pending',
        'awaiting_transfer' => 'status-awaiting_transfer',
        'paid' => 'status-paid',
        'completed' => 'status-completed',
        'delivered' => 'status-delivered',
        'processing' => 'status-processing',
        'cancelled' => 'status-cancelled',
        'failed' => 'status-failed',
    ];
    return $map[$status] ?? 'status-unknown';
}

function getStatusLabel(string $status): string {
    $map = [
        'pending' => 'Pending',
        'awaiting_transfer' => 'Awaiting Transfer',
        'paid' => 'Paid',
        'completed' => 'Completed',
        'delivered' => 'Delivered',
        'processing' => 'Processing',
        'cancelled' => 'Cancelled',
        'failed' => 'Failed',
    ];
    return $map[$status] ?? ucfirst($status);
}

function renderOrderTable($rows, $source, $tab) {
    global $allStatuses;
    $orderIdKey = ($source === 'paypal' || $source === 'test') ? 'order_ref' : 'order_id';
    $search = trim($_GET['search_' . $tab] ?? '');
    $filterStatus = trim($_GET['status_' . $tab] ?? '');
    if ($search) {
        $rows = array_filter($rows, fn($r) => stripos($r[$orderIdKey] ?? '', $search) !== false);
    }
    if ($filterStatus) {
        $rows = array_filter($rows, fn($r) => ($r['status'] ?? '') === $filterStatus);
    }
    $rows = array_values($rows);

    $paymentLabels = ['paypal' => 'PayPal', 'bank' => 'Bank Transfer', 'whatsapp' => 'WhatsApp Other', 'test' => 'Test'];
    $paymentLabel = $paymentLabels[$source] ?? ucfirst($source);

    $filterHidden = '';
    if ($search) $filterHidden .= '<input type="hidden" name="search_' . $tab . '" value="' . htmlspecialchars($search) . '">';
    if ($filterStatus) $filterHidden .= '<input type="hidden" name="status_' . $tab . '" value="' . htmlspecialchars($filterStatus) . '">';

    ob_start();
    ?>
    <div class="admin-card p-3 mb-3">
        <form method="get" class="row g-2 mb-3">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
            <div class="col-md-4">
                <input type="text" name="search_<?php echo $tab; ?>" class="form-control bg-dark text-light border-secondary" placeholder="Search by Order ID" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="status_<?php echo $tab; ?>" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <?php foreach ($allStatuses as $sv): ?>
                    <option value="<?php echo $sv; ?>" <?php echo $filterStatus === $sv ? 'selected' : ''; ?>><?php echo getStatusLabel($sv); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-cyber">Apply</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-borderless">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Order ID</th>
                        <th>Payment</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_reverse($rows) as $idx => $r):
                    $oid = $r[$orderIdKey] ?? '-';
                    $rowUid = $source . '-' . $idx;
                    $date = isset($r['created_at']) ? date('M j, Y H:i', strtotime($r['created_at'])) : '-';
                    $total = $r['totals']['total'] ?? 0;
                    $currency = $r['totals']['currency'] ?? 'USD';
                    $items = $r['items'] ?? [];
                    $cust = $r['customer'] ?? [];
                    if ($source === 'whatsapp') {
                        $custName = $r['customer_name'] ?? $cust['name'] ?? '-';
                        $custWhatsapp = $r['whatsapp'] ?? $cust['whatsapp'] ?? '';
                    } else {
                        $custName = $cust['name'] ?? '-';
                        $custWhatsapp = $cust['whatsapp'] ?? '';
                    }
                    $status = $r['status'] ?? (($source === 'paypal' || $source === 'test') ? 'paid' : 'pending');
                    $statusCls = getStatusClass($status);
                    $statusLbl = getStatusLabel($status);
                ?>
                    <tr class="order-row" data-row-uid="<?php echo htmlspecialchars($rowUid); ?>">
                        <td><?php echo htmlspecialchars($date); ?></td>
                        <td><code><?php echo htmlspecialchars($oid); ?></code></td>
                        <td><?php echo htmlspecialchars($paymentLabel); ?></td>
                        <td><?php echo htmlspecialchars($custName); ?><br><small class="text-muted"><?php echo htmlspecialchars($custWhatsapp); ?></small></td>
                        <td>$<?php echo number_format($total, 2); ?> <?php echo htmlspecialchars($currency); ?></td>
                        <td><span class="status-badge <?php echo $statusCls; ?>" data-badge-for="<?php echo htmlspecialchars($rowUid); ?>"><?php echo htmlspecialchars($statusLbl); ?></span></td>
                        <td><?php echo count($items); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-cyber expand-btn" data-target="<?php echo htmlspecialchars($rowUid); ?>">Expand</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary copy-wa-btn" data-order-id="<?php echo htmlspecialchars($oid); ?>" data-status="<?php echo htmlspecialchars($status); ?>" data-customer="<?php echo htmlspecialchars($custName); ?>">Copy WA</button>
                        </td>
                    </tr>
                    <tr class="order-details-row d-none" data-row-uid="<?php echo htmlspecialchars($rowUid); ?>-detail">
                        <td colspan="8" class="order-row-expand p-4">
                            <strong>Items:</strong>
                            <ul class="mb-2">
                            <?php foreach ($items as $it): ?>
                                <li><?php echo htmlspecialchars($it['name'] ?? 'Item'); ?> x<?php echo (int)($it['qty'] ?? 1); ?> — $<?php echo number_format($it['line_total'] ?? 0, 2); ?></li>
                            <?php endforeach; ?>
                            </ul>
                            <form method="post" class="d-flex align-items-center gap-2 flex-wrap">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($oid); ?>">
                                <input type="hidden" name="source" value="<?php echo htmlspecialchars($source); ?>">
                                <?php echo $filterHidden; ?>
                                <select name="new_status" class="status-select" data-row-uid="<?php echo htmlspecialchars($rowUid); ?>">
                                    <?php foreach ($allStatuses as $sv): ?>
                                    <option value="<?php echo $sv; ?>" <?php echo $status === $sv ? 'selected' : ''; ?>><?php echo getStatusLabel($sv); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-cyber">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($rows)): ?>
        <p class="text-muted mb-0">No orders found.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';

echo generateHeader('Admin - Orders', 'KND Store order management');
echo generateAdminBar();
?>
<style>
.admin-lux { --knd-cyan: #00d4ff; --knd-magenta: #ff00ff; }
.admin-lux body { background: #0a0a0f; color: #e8ecf0; }
.admin-lux .admin-card { background: rgba(12,15,22,0.8); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; }
.admin-lux .nav-tabs .nav-link { border: none; color: rgba(255,255,255,0.6); }
.admin-lux .nav-tabs .nav-link.active { color: var(--knd-cyan); border-bottom: 2px solid var(--knd-cyan); }
.admin-lux .table { color: #e8ecf0; }
.admin-lux .table th { border-color: rgba(255,255,255,0.08); font-weight: 600; }
.admin-lux .table td { border-color: rgba(255,255,255,0.06); vertical-align: middle; }
.admin-lux .table tbody tr:hover { background: rgba(255,255,255,0.03); }
.admin-lux .order-row-expand { background: rgba(0,212,255,0.05); }
.admin-lux .btn-cyber { background: rgba(0,212,255,0.15); border: 1px solid rgba(0,212,255,0.4); color: #00d4ff; }
.admin-lux .btn-cyber:hover { background: rgba(0,212,255,0.25); color: #fff; }
.admin-lux select.form-select { background: #0c0f16; color: #f0f4f8; border-color: rgba(255,255,255,0.12); }
.status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; letter-spacing: .4px; display: inline-block; text-transform: capitalize; }
.status-pending { background: #2a2f36; color: #9aa4af; }
.status-awaiting_transfer { background: rgba(243,156,18,.15); color: #f39c12; }
.status-paid { background: rgba(46,204,113,.15); color: #2ecc71; }
.status-completed { background: rgba(39,174,96,.18); color: #27ae60; }
.status-delivered { background: rgba(39,174,96,.22); color: #27ae60; }
.status-processing { background: rgba(52,152,219,.15); color: #3498db; }
.status-cancelled { background: rgba(231,76,60,.18); color: #e74c3c; }
.status-failed { background: rgba(192,57,43,.20); color: #c0392b; }
.status-unknown { background: #1f242b; color: #7f8c8d; }
.status-select { appearance: none; -webkit-appearance: none; background: #0c0f16; color: #f0f4f8; border: 1px solid rgba(255,255,255,0.12); border-radius: 20px; padding: 4px 28px 4px 12px; font-size: 12px; font-weight: 600; letter-spacing: .4px; cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2300d4ff'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; transition: border-color .2s, box-shadow .2s; }
.status-select:focus { outline: none; border-color: rgba(0,212,255,.5); box-shadow: 0 0 0 2px rgba(0,212,255,.15); }
.diag-panel { background: rgba(0,212,255,0.04); border: 1px solid rgba(0,212,255,0.12); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.8rem; }
.diag-panel summary { cursor: pointer; color: #00d4ff; font-weight: 600; }
.diag-panel table { width: 100%; }
.diag-panel td, .diag-panel th { padding: .2rem .5rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
.diag-panel .ok { color: #4ade80; }
.diag-panel .fail { color: #f87171; }
</style>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<div class="admin-lux">
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Order Management</h1>
            <div>
                <a href="/admin/" class="btn btn-sm btn-cyber me-2"><i class="fas fa-home me-1"></i>Admin Home</a>
                <a href="/admin/test_order.php" class="btn btn-sm btn-cyber me-2">+ Test Order</a>
                <a href="/admin/purge_cache.php" class="btn btn-sm btn-cyber me-2" title="Purge cache for /admin"><i class="fas fa-sync-alt me-1"></i>Purge Cache</a>
                <a href="/admin/debug_storage.php" class="btn btn-sm btn-cyber me-2">Debug</a>
                <a href="/admin/orders.php?logout=1" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>

        <?php if ($flashMsg): ?>
        <div class="alert alert-<?php echo $flashType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flashMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <details class="diag-panel">
            <summary>Storage Diagnostics</summary>
            <table class="mt-2">
                <tr><th>File</th><th>Path</th><th>Exists</th><th>R</th><th>W</th><th>Size</th><th>Modified</th><th>Records</th></tr>
                <?php foreach ($diagData as $label => $d): ?>
                <tr>
                    <td><code><?php echo $label; ?></code></td>
                    <td style="word-break:break-all;max-width:300px;"><code><?php echo htmlspecialchars($d['path']); ?></code></td>
                    <td class="<?php echo $d['exists'] ? 'ok' : 'fail'; ?>"><?php echo $d['exists'] ? 'Y' : 'N'; ?></td>
                    <td class="<?php echo $d['readable'] ? 'ok' : 'fail'; ?>"><?php echo $d['readable'] ? 'Y' : 'N'; ?></td>
                    <td class="<?php echo $d['writable'] ? 'ok' : 'fail'; ?>"><?php echo $d['writable'] ? 'Y' : 'N'; ?></td>
                    <td><?php echo number_format($d['size']); ?>B</td>
                    <td><?php echo $d['mtime']; ?></td>
                    <td>
                        <?php if ($label === 'orders.json'): ?>
                            <?php echo $d['count_total']; ?> total (<?php echo $d['count_paypal']; ?> paypal, <?php echo $d['count_test']; ?> test)
                        <?php else: ?>
                            <?php echo $d['count'] ?? 0; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </details>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'paypal' ? 'active' : ''; ?>" href="?tab=paypal">PayPal (<?php echo $countPaypal; ?>)</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'bank' ? 'active' : ''; ?>" href="?tab=bank">Bank Transfer (<?php echo $countBank; ?>)</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'whatsapp' ? 'active' : ''; ?>" href="?tab=whatsapp">WhatsApp Other (<?php echo $countOther; ?>)</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'test' ? 'active' : ''; ?>" href="?tab=test">Test (<?php echo $countTest; ?>)</a></li>
        </ul>

        <?php if ($activeTab === 'paypal'): ?>
            <?php echo renderOrderTable($paypalOrders, 'paypal', 'paypal'); ?>
        <?php elseif ($activeTab === 'bank'): ?>
            <?php echo renderOrderTable($bankRequests, 'bank', 'bank'); ?>
        <?php elseif ($activeTab === 'whatsapp'): ?>
            <?php echo renderOrderTable($otherRequests, 'whatsapp', 'whatsapp'); ?>
        <?php elseif ($activeTab === 'test'): ?>
            <?php echo renderOrderTable($testOrders, 'test', 'test'); ?>
        <?php endif; ?>
    </div>
</section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var statusClassMap = {
        'pending': 'status-pending',
        'awaiting_transfer': 'status-awaiting_transfer',
        'paid': 'status-paid',
        'completed': 'status-completed',
        'delivered': 'status-delivered',
        'processing': 'status-processing',
        'cancelled': 'status-cancelled',
        'failed': 'status-failed'
    };
    var statusLabelMap = {
        'pending': 'Pending',
        'awaiting_transfer': 'Awaiting Transfer',
        'paid': 'Paid',
        'completed': 'Completed',
        'delivered': 'Delivered',
        'processing': 'Processing',
        'cancelled': 'Cancelled',
        'failed': 'Failed'
    };

    function applySelectColor(sel) {
        var val = sel.value;
        sel.className = 'status-select ' + (statusClassMap[val] || 'status-unknown');
    }

    document.querySelectorAll('.status-select').forEach(function(sel) {
        applySelectColor(sel);
        sel.addEventListener('change', function() {
            applySelectColor(this);
            var uid = this.dataset.rowUid;
            var badge = document.querySelector('.status-badge[data-badge-for="' + uid + '"]');
            if (badge) {
                var val = this.value;
                badge.className = 'status-badge ' + (statusClassMap[val] || 'status-unknown');
                badge.textContent = statusLabelMap[val] || val;
            }
        });
    });

    document.querySelectorAll('.expand-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var uid = this.dataset.target;
            var details = document.querySelector('.order-details-row[data-row-uid="' + uid + '-detail"]');
            if (details) {
                details.classList.toggle('d-none');
                this.textContent = details.classList.contains('d-none') ? 'Expand' : 'Collapse';
            }
        });
    });

    document.querySelectorAll('.copy-wa-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var oid = this.dataset.orderId;
            var status = this.dataset.status;
            var customer = this.dataset.customer;
            var templates = {
                pending: 'Hi ' + customer + ', your order ' + oid + ' is being processed. We will confirm payment options shortly.',
                awaiting_transfer: 'Hi ' + customer + ', please complete the transfer for order ' + oid + '. Banking details were sent separately.',
                paid: 'Hi ' + customer + ', we received payment for order ' + oid + '. We will ship/deliver soon.',
                delivered: 'Hi ' + customer + ', your order ' + oid + ' has been delivered. Thank you!',
                cancelled: 'Hi ' + customer + ', order ' + oid + ' was cancelled. If you have questions, contact us.'
            };
            var msg = templates[status] || 'Order ' + oid + ' - Status: ' + status;
            navigator.clipboard.writeText(msg).then(function() { alert('Copied to clipboard'); }).catch(function() { prompt('Copy:', msg); });
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/navigation-extend.js"></script>
</body>
</html>
