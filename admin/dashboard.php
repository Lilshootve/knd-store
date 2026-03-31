<?php
/**
 * Admin Dashboard v1 - KPIs, KP metrics, Top XP, Alerts
 */
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('dashboard.view');

$pdo = getDBConnection();
if (!$pdo) {
    header('Content-Type: text/html; charset=utf-8');
    echo 'Database connection failed.';
    exit;
}

require_once __DIR__ . '/../includes/knd_xp.php';

$todayStart = gmdate('Y-m-d 00:00:00');
$weekStart = gmdate('Y-m-d 00:00:00', strtotime('-7 days'));
$prevWeekStart = gmdate('Y-m-d 00:00:00', strtotime('-14 days'));

// New users (today / 7d)
$newUsersToday = 0;
$newUsers7d = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ?");
    $stmt->execute([$todayStart]);
    $newUsersToday = (int) $stmt->fetchColumn();
    $stmt->execute([$weekStart]);
    $newUsers7d = (int) $stmt->fetchColumn();
} catch (\Throwable $e) {}

// Online users (last_seen >= NOW()-10min)
$onlineUsers = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_presence WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $onlineUsers = (int) $stmt->fetchColumn();
} catch (\Throwable $e) {}

// KP issued (entry_type='earn') today / 7d
$kpIssuedToday = 0;
$kpIssued7d = 0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points), 0) FROM points_ledger WHERE entry_type = 'earn' AND created_at >= ?");
    $stmt->execute([$todayStart]);
    $kpIssuedToday = (int) $stmt->fetchColumn();
    $stmt->execute([$weekStart]);
    $kpIssued7d = (int) $stmt->fetchColumn();
} catch (\Throwable $e) {}

// KP spent (entry_type='spend') today / 7d
$kpSpentToday = 0;
$kpSpent7d = 0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(ABS(points)), 0) FROM points_ledger WHERE entry_type = 'spend' AND created_at >= ?");
    $stmt->execute([$todayStart]);
    $kpSpentToday = (int) $stmt->fetchColumn();
    $stmt->execute([$weekStart]);
    $kpSpent7d = (int) $stmt->fetchColumn();
} catch (\Throwable $e) {}

// KP expired today / 7d (status='expired' - no updated_at in points_ledger, use created_at when status changed)
$kpExpiredToday = 0;
$kpExpired7d = 0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points), 0) FROM points_ledger WHERE status = 'expired' AND created_at >= ?");
    $stmt->execute([$todayStart]);
    $kpExpiredToday = (int) $stmt->fetchColumn();
    $stmt->execute([$weekStart]);
    $kpExpired7d = (int) $stmt->fetchColumn();
} catch (\Throwable $e) {}

// KP in circulation (issued - spent - expired, approximation)
$issuedTotal = 0;
$spentTotal = 0;
$expiredTotal = 0;
try {
    $issuedTotal = (int) $pdo->query("SELECT COALESCE(SUM(points), 0) FROM points_ledger WHERE entry_type = 'earn'")->fetchColumn();
    $spentTotal = (int) $pdo->query("SELECT COALESCE(SUM(ABS(points)), 0) FROM points_ledger WHERE entry_type = 'spend'")->fetchColumn();
    $expiredTotal = (int) $pdo->query("SELECT COALESCE(SUM(points), 0) FROM points_ledger WHERE status = 'expired'")->fetchColumn();
} catch (\Throwable $e) {}
$kpInCirculation = max(0, $issuedTotal - $spentTotal - $expiredTotal);

// Previous 7d for circulation delta
$kpIssuedPrev7d = 0;
$kpSpentPrev7d = 0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points), 0) FROM points_ledger WHERE entry_type = 'earn' AND created_at >= ? AND created_at < ?");
    $stmt->execute([$prevWeekStart, $weekStart]);
    $kpIssuedPrev7d = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(ABS(points)), 0) FROM points_ledger WHERE entry_type = 'spend' AND created_at >= ? AND created_at < ?");
    $stmt->execute([$prevWeekStart, $weekStart]);
    $kpSpentPrev7d = (int) $stmt->fetchColumn();
} catch (\Throwable $e) {}
$circPrev7d = max(0, $kpIssuedPrev7d - $kpSpentPrev7d);
$circThis7d = max(0, $kpIssued7d - $kpSpent7d);
$circDeltaPct = ($circPrev7d > 0) ? (($circThis7d - $circPrev7d) / $circPrev7d) * 100 : 0;

// Top XP Gainers (Season) - top 10
$topSeason = [];
$season = get_active_season($pdo);
if ($season) {
    try {
        $stmt = $pdo->prepare(
            "SELECT s.user_id, s.xp_earned, u.username,
                    COALESCE(kux.level, 1) AS lvl, COALESCE(kux.xp, 0) AS total_xp
             FROM knd_season_stats s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN knd_user_xp kux ON kux.user_id = s.user_id
             WHERE s.season_id = ?
             ORDER BY s.xp_earned DESC
             LIMIT 10"
        );
        $stmt->execute([$season['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $i => $r) {
            $xp = (int) ($r['total_xp'] ?? 0);
            $level = $xp > 0 ? xp_calc_level($xp) : 1;
            $topSeason[] = [
                'rank' => $i + 1,
                'username' => $r['username'] ?? '?',
                'level' => $level,
                'xp_earned' => (int) ($r['xp_earned'] ?? 0),
            ];
        }
    } catch (\Throwable $e) {
        // fallback without knd_user_xp
        try {
            $stmt = $pdo->prepare(
                "SELECT s.user_id, s.xp_earned, u.username
                 FROM knd_season_stats s
                 JOIN users u ON u.id = s.user_id
                 WHERE s.season_id = ?
                 ORDER BY s.xp_earned DESC
                 LIMIT 10"
            );
            $stmt->execute([$season['id']]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $i => $r) {
                $topSeason[] = [
                    'rank' => $i + 1,
                    'username' => $r['username'] ?? '?',
                    'level' => 1,
                    'xp_earned' => (int) ($r['xp_earned'] ?? 0),
                ];
            }
        } catch (\Throwable $e2) {}
    }
}

// Alert: KP circulation increased >15%
$alertKpCirc = ($circDeltaPct > 15);

// Alert: single user spent >5000 KP in 24h (potential whale/abuse)
$alertWhale = null;
try {
    $stmt = $pdo->prepare(
        "SELECT user_id, SUM(ABS(points)) AS spent
         FROM points_ledger
         WHERE entry_type = 'spend' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         GROUP BY user_id
         HAVING spent > 5000
         ORDER BY spent DESC
         LIMIT 1"
    );
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $u = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $u->execute([$row['user_id']]);
        $alertWhale = ['username' => $u->fetchColumn() ?: '?', 'spent' => (int) $row['spent']];
    }
} catch (\Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | KND Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .admin-dash { --cyan: #00d4ff; min-height: 100vh; background: #0a0a0f; color: #e8ecf0; padding-top: 2rem; padding-bottom: 2rem; }
        .admin-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .admin-topbar h1 { font-size: 1.75rem; font-weight: 700; margin: 0; }
        .kpi-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .kpi-card { background: rgba(12,15,22,.85); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 1.25rem 1.5rem; }
        .kpi-value { font-size: 1.75rem; font-weight: 700; color: var(--cyan); line-height: 1.1; }
        .kpi-sublabel { font-size: .7rem; color: rgba(255,255,255,.4); margin-top: .2rem; }
        .kpi-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .8px; color: rgba(255,255,255,.5); margin-top: .35rem; }
        .card-dark { background: rgba(12,15,22,.85); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; }
        .table-dark-custom { color: #e8ecf0; }
        .table-dark-custom th { border-color: rgba(255,255,255,.1); color: var(--cyan); }
        .table-dark-custom td { border-color: rgba(255,255,255,.08); }
        .alert-banner { border-radius: 10px; }
    </style>
</head>
<body class="admin-dash">
<div class="container">
    <div class="admin-topbar">
        <h1><i class="fas fa-chart-line me-2" style="color:var(--cyan)"></i>Dashboard</h1>
        <div>
            <a href="/admin/" class="btn btn-outline-secondary btn-sm me-2"><i class="fas fa-arrow-left me-1"></i>Home</a>
            <a href="/admin/?logout=1" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>

    <?php if ($alertKpCirc): ?>
    <div class="alert alert-warning alert-banner mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>
        KP in circulation increased <?php echo round($circDeltaPct, 1); ?>% vs previous 7 days. Review if unexpected.
    </div>
    <?php endif; ?>
    <?php if ($alertWhale): ?>
    <div class="alert alert-info alert-banner mb-4">
        <i class="fas fa-info-circle me-2"></i>
        User <strong><?php echo htmlspecialchars($alertWhale['username']); ?></strong> spent <?php echo number_format($alertWhale['spent']); ?> KP in the last 24h.
    </div>
    <?php endif; ?>

    <h5 class="mb-3" style="color:var(--cyan)">Today / Last 7 days</h5>
    <div class="kpi-strip">
        <div class="kpi-card">
            <div class="kpi-value"><?php echo $newUsersToday; ?> / <?php echo $newUsers7d; ?></div>
            <div class="kpi-sublabel">today / 7d</div>
            <div class="kpi-label">New Users</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?php echo $onlineUsers; ?></div>
            <div class="kpi-label">Online (10min)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?php echo number_format($kpIssuedToday); ?> / <?php echo number_format($kpIssued7d); ?></div>
            <div class="kpi-sublabel">today / 7d</div>
            <div class="kpi-label">KP Issued</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?php echo number_format($kpSpentToday); ?> / <?php echo number_format($kpSpent7d); ?></div>
            <div class="kpi-sublabel">today / 7d</div>
            <div class="kpi-label">KP Spent</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?php echo number_format($kpExpiredToday); ?> / <?php echo number_format($kpExpired7d); ?></div>
            <div class="kpi-sublabel">today / 7d</div>
            <div class="kpi-label">KP Expired</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?php echo number_format($kpInCirculation); ?></div>
            <div class="kpi-label">KP in Circulation</div>
        </div>
    </div>

    <h5 class="mb-3 mt-4" style="color:var(--cyan)">Top XP Gainers (Season)</h5>
    <div class="card card-dark overflow-hidden">
        <div class="card-body p-0">
            <?php if (empty($topSeason)): ?>
            <p class="text-muted p-4 mb-0">No season data yet.</p>
            <?php else: ?>
            <table class="table table-dark-custom table-hover mb-0">
                <thead>
                    <tr><th>#</th><th>User</th><th>Level</th><th>Season XP</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($topSeason as $r): ?>
                    <tr>
                        <td><?php echo $r['rank']; ?></td>
                        <td><?php echo htmlspecialchars($r['username']); ?></td>
                        <td><?php echo $r['level']; ?></td>
                        <td><?php echo number_format($r['xp_earned']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
