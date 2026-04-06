<?php
require_once __DIR__ . '/../config/bootstrap.php';
ini_set('display_errors', '0');
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('payments.view');
require_once BASE_PATH . '/includes/support_credits.php';

$pdo = getDBConnection();
if (!$pdo) {
    echo 'Database connection failed.';
    exit;
}

$flashMsg = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $action = trim($_POST['action_type'] ?? '');
    $notes = trim($_POST['admin_notes'] ?? '') ?: null;

    if ($paymentId > 0 && in_array($action, ['confirm', 'reject', 'dispute', 'refund'])) {
        admin_require_perm('payments.confirm');
        try {
            $result = admin_update_payment($pdo, $paymentId, $action, $notes);
            if (isset($result['error'])) {
                $flashMsg = 'Error: ' . $result['error'];
                $flashType = 'danger';
            } else {
                $pm = $pdo->prepare('SELECT user_id, amount_usd FROM support_payments WHERE id = ?');
                $pm->execute([$paymentId]);
                $pmRow = $pm->fetch();
                require_once __DIR__ . '/_audit.php';
                admin_log_action('support_payment_' . $action, [
                    'payment_id' => $paymentId,
                    'user_id' => $pmRow ? (int) $pmRow['user_id'] : null,
                    'amount_usd' => $pmRow ? $pmRow['amount_usd'] : null,
                    'reason' => $notes,
                ]);
                $flashMsg = "Payment #$paymentId: $action successful.";
                $flashType = 'success';
            }
        } catch (\Throwable $e) {
            $flashMsg = 'Error: ' . $e->getMessage();
            $flashType = 'danger';
        }
    }

    if (isset($_POST['redeem_action'])) {
        $redeemId = (int) ($_POST['redemption_id'] ?? 0);
        $redeemAction = trim($_POST['redeem_action'] ?? '');
        $redeemNotes = trim($_POST['redeem_notes'] ?? '') ?: null;
        $validRedeemActions = ['approved', 'fulfilled', 'rejected', 'cancelled'];

        if ($redeemId > 0 && in_array($redeemAction, $validRedeemActions)) {
            admin_require_perm('payments.confirm');
            try {
                $now = gmdate('Y-m-d H:i:s');
                $pdo->prepare(
                    "UPDATE reward_redemptions SET status = ?, notes = CONCAT(COALESCE(notes,''), ?), updated_at = ? WHERE id = ?"
                )->execute([$redeemAction, $redeemNotes ? " | Admin: $redeemNotes" : '', $now, $redeemId]);

                $rdm = $pdo->prepare('SELECT user_id, points_spent FROM reward_redemptions WHERE id = ?');
                $rdm->execute([$redeemId]);
                $rd = $rdm->fetch();
                if (in_array($redeemAction, ['rejected', 'cancelled']) && $rd) {
                    $pdo->prepare(
                        "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
                         VALUES (?, 'adjustment', ?, 'reversal', 'available', ?, ?)"
                    )->execute([$rd['user_id'], $redeemId, $rd['points_spent'], $now]);
                }
                require_once __DIR__ . '/_audit.php';
                admin_log_action('reward_redemption_' . $redeemAction, [
                    'redemption_id' => $redeemId,
                    'user_id' => $rd ? (int) $rd['user_id'] : null,
                    'points_spent' => $rd ? (int) $rd['points_spent'] : null,
                    'reason' => $redeemNotes,
                ]);
                $flashMsg = "Redemption #$redeemId: $redeemAction.";
                $flashType = 'success';
            } catch (\Throwable $e) {
                $flashMsg = 'Error: ' . $e->getMessage();
                $flashType = 'danger';
            }
        }
    }
}

$activeTab = $_GET['tab'] ?? 'payments';

$pendingPayments = $pdo->query(
    "SELECT sp.*, u.username FROM support_payments sp
     JOIN users u ON u.id = sp.user_id
     WHERE sp.status = 'pending'
     ORDER BY sp.created_at DESC LIMIT 100"
)->fetchAll();

$recentPayments = $pdo->query(
    "SELECT sp.*, u.username FROM support_payments sp
     JOIN users u ON u.id = sp.user_id
     WHERE sp.status != 'pending'
     ORDER BY sp.updated_at DESC LIMIT 50"
)->fetchAll();

$pendingRedemptions = $pdo->query(
    "SELECT rr.*, u.username, rc.title AS reward_title FROM reward_redemptions rr
     JOIN users u ON u.id = rr.user_id
     JOIN rewards_catalog rc ON rc.id = rr.reward_id
     WHERE rr.status IN ('requested','approved')
     ORDER BY rr.created_at DESC LIMIT 100"
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - KND Points</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        body { background: #0a0f1e; color: #e0e0e0; }
        .card { background: rgba(15,20,40,0.95); border: 1px solid rgba(37,156,174,0.2); }
        .table { color: #e0e0e0; }
        .badge-pending { background: #ffc107; color: #000; }
        .badge-confirmed { background: #28a745; }
        .badge-rejected { background: #dc3545; }
        .badge-disputed { background: #fd7e14; }
        .badge-refunded { background: #6c757d; }
    </style>
</head>
<body class="admin-page">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-coins me-2"></i>KND Points Admin</h1>
        <div>
            <a href="/admin/" class="btn btn-outline-secondary btn-sm me-2"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
            <a href="/admin/rewards.php" class="btn btn-outline-info btn-sm me-2"><i class="fas fa-gift me-1"></i>Rewards</a>
            <a href="?logout" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
        </div>
    </div>

    <?php if ($flashMsg): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show">
            <?= htmlspecialchars($flashMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a class="nav-link <?= $activeTab === 'payments' ? 'active' : '' ?>" href="?tab=payments">Pending Payments (<?= count($pendingPayments) ?>)</a></li>
        <li class="nav-item"><a class="nav-link <?= $activeTab === 'history' ? 'active' : '' ?>" href="?tab=history">Payment History</a></li>
        <li class="nav-item"><a class="nav-link <?= $activeTab === 'redemptions' ? 'active' : '' ?>" href="?tab=redemptions">Redemptions (<?= count($pendingRedemptions) ?>)</a></li>
    </ul>

    <?php if ($activeTab === 'payments'): ?>
    <div class="card p-3">
        <h4 class="mb-3">Pending Payments</h4>
        <?php if (empty($pendingPayments)): ?>
            <p class="text-muted">No pending payments.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr><th>#</th><th>User</th><th>Method</th><th>Amount</th><th>Currency</th><th>Notes</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($pendingPayments as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['username']) ?></td>
                        <td><?= htmlspecialchars($p['method']) ?></td>
                        <td>$<?= number_format($p['amount_usd'], 2) ?></td>
                        <td><?= htmlspecialchars($p['currency']) ?></td>
                        <td class="small"><?= htmlspecialchars($p['notes'] ?? '') ?></td>
                        <td class="small"><?= $p['created_at'] ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                <input type="text" name="admin_notes" placeholder="Notes" class="form-control form-control-sm d-inline-block" style="width:120px">
                                <button name="action_type" value="confirm" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button>
                                <button name="action_type" value="reject" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
                                <button name="action_type" value="dispute" class="btn btn-warning btn-sm"><i class="fas fa-exclamation-triangle"></i></button>
                                <button name="action_type" value="refund" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php elseif ($activeTab === 'history'): ?>
    <div class="card p-3">
        <h4 class="mb-3">Payment History</h4>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr><th>#</th><th>User</th><th>Method</th><th>Amount</th><th>Status</th><th>Confirmed</th><th>Notes</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentPayments as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['username']) ?></td>
                        <td><?= htmlspecialchars($p['method']) ?></td>
                        <td>$<?= number_format($p['amount_usd'], 2) ?></td>
                        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] ?></span></td>
                        <td class="small"><?= $p['confirmed_at'] ?? '—' ?></td>
                        <td class="small"><?= htmlspecialchars($p['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($activeTab === 'redemptions'): ?>
    <div class="card p-3">
        <h4 class="mb-3">Pending Redemptions</h4>
        <?php if (empty($pendingRedemptions)): ?>
            <p class="text-muted">No pending redemptions.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr><th>#</th><th>User</th><th>Reward</th><th>Points</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($pendingRedemptions as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['username']) ?></td>
                        <td><?= htmlspecialchars($r['reward_title']) ?></td>
                        <td><?= $r['points_spent'] ?></td>
                        <td><span class="badge bg-info"><?= $r['status'] ?></span></td>
                        <td class="small"><?= $r['created_at'] ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="redemption_id" value="<?= $r['id'] ?>">
                                <input type="text" name="redeem_notes" placeholder="Notes" class="form-control form-control-sm d-inline-block" style="width:100px">
                                <button name="redeem_action" value="approved" class="btn btn-info btn-sm">Approve</button>
                                <button name="redeem_action" value="fulfilled" class="btn btn-success btn-sm">Fulfill</button>
                                <button name="redeem_action" value="rejected" class="btn btn-danger btn-sm">Reject</button>
                                <button name="redeem_action" value="cancelled" class="btn btn-secondary btn-sm">Cancel</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
