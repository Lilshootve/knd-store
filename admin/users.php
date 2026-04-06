<?php
require_once __DIR__ . '/../config/bootstrap.php';
ini_set('display_errors', '0');
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('users.view');

$pdo = getDBConnection();
if (!$pdo) { echo 'DB connection failed.'; exit; }

// ── Pagination & Filters ──
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 30;
$offset   = ($page - 1) * $perPage;
$search   = trim($_GET['q'] ?? '');
$filter   = $_GET['filter'] ?? '';
$sort     = $_GET['sort'] ?? 'newest';

$whereClauses = [];
$params = [];

if ($search !== '') {
    if (ctype_digit($search)) {
        $whereClauses[] = 'u.id = ?';
        $params[] = (int) $search;
    } else {
        $whereClauses[] = 'u.username LIKE ?';
        $params[] = '%' . $search . '%';
    }
}
if ($filter === 'risk') {
    $whereClauses[] = 'u.risk_flag = 1';
} elseif ($filter === 'online') {
    $whereClauses[] = 'up.last_seen >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 SECOND)';
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$orderSQL = match ($sort) {
    'oldest'      => 'u.created_at ASC',
    'most_points' => 'kp_available DESC',
    'online'      => 'up.last_seen DESC',
    default       => 'u.created_at DESC',
};

// Count total
$countSQL = "SELECT COUNT(*) FROM users u
             LEFT JOIN user_presence up ON up.user_id = u.id
             {$whereSQL}";
$stmt = $pdo->prepare($countSQL);
$stmt->execute($params);
$totalUsers = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));

// Fetch page with aggregated KP
// Subquery approach: get user IDs for the page first, then aggregate KP
$listSQL = "SELECT u.id, u.username, u.email, u.risk_flag, u.created_at,
                   up.last_seen,
                   COALESCE(kp_earn.earned, 0) AS kp_earned,
                   COALESCE(kp_spend.spent, 0) AS kp_spent,
                   COALESCE(kp_pend.pending, 0) AS kp_pending,
                   GREATEST(0, COALESCE(kp_earn.earned, 0) - COALESCE(kp_spend.spent, 0)) AS kp_available,
                   COALESCE(ux.xp, 0) AS xp_total
            FROM users u
            LEFT JOIN user_presence up ON up.user_id = u.id
            LEFT JOIN (
                SELECT user_id, SUM(points) AS earned
                FROM points_ledger
                WHERE entry_type = 'earn' AND status = 'available'
                GROUP BY user_id
            ) kp_earn ON kp_earn.user_id = u.id
            LEFT JOIN (
                SELECT user_id, ABS(SUM(points)) AS spent
                FROM points_ledger
                WHERE entry_type = 'spend'
                GROUP BY user_id
            ) kp_spend ON kp_spend.user_id = u.id
            LEFT JOIN (
                SELECT user_id, SUM(points) AS pending
                FROM points_ledger
                WHERE entry_type = 'earn' AND status = 'pending'
                GROUP BY user_id
            ) kp_pend ON kp_pend.user_id = u.id
            LEFT JOIN user_xp ux ON ux.user_id = u.id
            {$whereSQL}
            ORDER BY {$orderSQL}
            LIMIT {$perPage} OFFSET {$offset}";

try {
    $stmt = $pdo->prepare($listSQL);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log('admin/users list: ' . $e->getMessage());
    $users = [];
    // Fallback without KP columns in case points_ledger doesn't exist
    try {
        $fallback = "SELECT u.id, u.username, u.email, u.risk_flag, u.created_at,
                            up.last_seen, 0 AS kp_earned, 0 AS kp_spent, 0 AS kp_pending,
                            0 AS kp_available, COALESCE(ux.xp, 0) AS xp_total
                     FROM users u
                     LEFT JOIN user_presence up ON up.user_id = u.id
                     LEFT JOIN user_xp ux ON ux.user_id = u.id
                     {$whereSQL}
                     ORDER BY u.created_at DESC
                     LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($fallback);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e2) {
        error_log('admin/users fallback: ' . $e2->getMessage());
    }
}

require_once BASE_PATH . '/includes/header.php';
echo generateHeader('User Management', 'Admin user management');
echo generateAdminBar();
?>
<style>
.au-dash { --cyan: var(--c, #00e8ff); min-height:100vh; background: var(--void, #010508); color:#e8ecf0; padding-top:24px; padding-bottom:60px; }
.au-card { background:rgba(12,15,22,.85); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:1.25rem; }
.au-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
.au-topbar h1 { font-size:1.5rem; font-weight:700; margin:0; }
.au-tbl { font-size:.8rem; }
.au-tbl th { color:rgba(255,255,255,.5); font-weight:600; text-transform:uppercase; letter-spacing:.5px; font-size:.7rem; white-space:nowrap; }
.au-tbl td { vertical-align:middle; }
.au-risk-on { color:#f87171; font-weight:700; }
.au-online { display:inline-block; width:8px; height:8px; background:#4ade80; border-radius:50%; }
.au-offline { display:inline-block; width:8px; height:8px; background:rgba(255,255,255,.2); border-radius:50%; }
.au-kpi-strip { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:.75rem; margin-bottom:1.5rem; }
.au-kpi { background:rgba(12,15,22,.85); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:1rem; text-align:center; }
.au-kpi-val { font-size:1.6rem; font-weight:700; color:var(--cyan); font-family:'Orbitron',monospace; }
.au-kpi-lbl { font-size:.7rem; text-transform:uppercase; color:rgba(255,255,255,.5); letter-spacing:.5px; }
.au-filters { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
.au-filters .btn { font-size:.75rem; padding:.25rem .6rem; }
.au-pg { display:flex; gap:.25rem; flex-wrap:wrap; }
.au-pg a, .au-pg span { display:inline-block; padding:.3rem .6rem; font-size:.75rem; border-radius:6px; text-decoration:none; border:1px solid rgba(255,255,255,.1); color:#e8ecf0; }
.au-pg a:hover { border-color:var(--cyan); color:var(--cyan); }
.au-pg .active { background:var(--cyan); color:#0a0a0f; border-color:var(--cyan); font-weight:700; }
</style>

<div class="au-dash">
<div class="container">
    <div class="au-topbar">
        <h1><i class="fas fa-users me-2" style="color:var(--cyan)"></i>User Management</h1>
        <div class="d-flex gap-2">
            <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
            <a href="/admin/users.php?logout=1" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>

    <!-- KPI Strip -->
    <div class="au-kpi-strip">
        <div class="au-kpi">
            <div class="au-kpi-val"><?php echo number_format($totalUsers); ?></div>
            <div class="au-kpi-lbl">Total Users<?php if ($search !== '' || $filter !== '') echo ' (filtered)'; ?></div>
        </div>
        <?php
        // Quick global counts
        try {
            $totalAll     = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $totalRisk    = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE risk_flag = 1')->fetchColumn();
            $totalOnline  = 0;
            try { $totalOnline = (int) $pdo->query("SELECT COUNT(*) FROM user_presence WHERE last_seen >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 SECOND)")->fetchColumn(); } catch (\Throwable $e) {}
        } catch (\Throwable $e) { $totalAll = $totalUsers; $totalRisk = 0; $totalOnline = 0; }
        ?>
        <div class="au-kpi">
            <div class="au-kpi-val" style="color:#4ade80;"><?php echo $totalOnline; ?></div>
            <div class="au-kpi-lbl">Online Now</div>
        </div>
        <div class="au-kpi">
            <div class="au-kpi-val" style="color:#f87171;"><?php echo $totalRisk; ?></div>
            <div class="au-kpi-lbl">Risk Flagged</div>
        </div>
        <div class="au-kpi">
            <div class="au-kpi-val"><?php echo number_format($totalAll); ?></div>
            <div class="au-kpi-lbl">All Registered</div>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="au-card mb-4">
        <form method="get" class="d-flex gap-2 mb-3 flex-wrap">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by username or user ID…" value="<?php echo htmlspecialchars($search); ?>" style="max-width:280px;">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
            <button class="btn btn-sm btn-primary"><i class="fas fa-search me-1"></i>Search</button>
            <?php if ($search !== '' || $filter !== ''): ?>
            <a href="/admin/users.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
        <div class="au-filters">
            <span class="text-white-50" style="font-size:.75rem;">Filter:</span>
            <a href="?q=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>" class="btn btn-outline-<?php echo $filter === '' ? 'info' : 'secondary'; ?>">All</a>
            <a href="?q=<?php echo urlencode($search); ?>&filter=risk&sort=<?php echo urlencode($sort); ?>" class="btn btn-outline-<?php echo $filter === 'risk' ? 'danger' : 'secondary'; ?>">Risk Flagged</a>
            <a href="?q=<?php echo urlencode($search); ?>&filter=online&sort=<?php echo urlencode($sort); ?>" class="btn btn-outline-<?php echo $filter === 'online' ? 'success' : 'secondary'; ?>">Online</a>
            <span class="text-white-50 ms-2" style="font-size:.75rem;">Sort:</span>
            <a href="?q=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&sort=newest" class="btn btn-outline-<?php echo $sort === 'newest' ? 'info' : 'secondary'; ?>">Newest</a>
            <a href="?q=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&sort=oldest" class="btn btn-outline-<?php echo $sort === 'oldest' ? 'info' : 'secondary'; ?>">Oldest</a>
            <a href="?q=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&sort=most_points" class="btn btn-outline-<?php echo $sort === 'most_points' ? 'info' : 'secondary'; ?>">Most KP</a>
        </div>
    </div>

    <!-- User Table -->
    <div class="au-card mb-4">
        <div class="table-responsive">
            <table class="table table-sm table-dark au-tbl mb-0" style="--bs-table-bg:transparent;">
                <thead><tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th class="text-end">KP Avail</th>
                    <th class="text-end">KP Pend</th>
                    <th class="text-end">XP</th>
                    <th>Last Seen</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="10" class="text-center text-white-50 py-4">No users found.</td></tr>
                <?php else: foreach ($users as $u):
                    $isOnline = !empty($u['last_seen']) && strtotime($u['last_seen']) >= (time() - 60);
                ?>
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td>
                        <a href="/admin/user.php?id=<?php echo $u['id']; ?>" style="color:var(--cyan); text-decoration:none;">
                            <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                        </a>
                    </td>
                    <td class="text-white-50"><?php echo htmlspecialchars($u['email'] ?? '—'); ?></td>
                    <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                    <td>
                        <?php if ((int)($u['risk_flag'] ?? 0) === 1): ?>
                        <span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> RISK</span>
                        <?php else: ?>
                        <span class="badge bg-success" style="font-size:.65rem;">OK</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end" style="color:#4ade80; font-weight:600;"><?php echo number_format((int)($u['kp_available'] ?? 0)); ?></td>
                    <td class="text-end" style="color:#facc15;"><?php echo number_format((int)($u['kp_pending'] ?? 0)); ?></td>
                    <td class="text-end"><?php echo number_format((int)($u['xp_total'] ?? 0)); ?></td>
                    <td>
                        <?php if ($isOnline): ?>
                        <span class="au-online" title="Online"></span> <small class="text-success">now</small>
                        <?php elseif (!empty($u['last_seen'])): ?>
                        <span class="au-offline"></span> <small class="text-white-50"><?php echo date('M j H:i', strtotime($u['last_seen'])); ?></small>
                        <?php else: ?>
                        <span class="au-offline"></span> <small class="text-white-50">never</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <a href="/admin/user.php?id=<?php echo $u['id']; ?>" class="btn btn-outline-info btn-sm" style="font-size:.7rem; padding:.15rem .4rem;" title="View Detail">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="/admin/knd-points.php?u=<?php echo $u['id']; ?>" class="btn btn-outline-warning btn-sm" style="font-size:.7rem; padding:.15rem .4rem;" title="Wallet Inspector">
                            <i class="fas fa-wallet"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-white-50">
            Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalUsers); ?> of <?php echo $totalUsers; ?>
        </small>
        <div class="au-pg">
            <?php if ($page > 1): ?>
            <a href="?q=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&sort=<?php echo urlencode($sort); ?>&page=<?php echo $page - 1; ?>">&laquo;</a>
            <?php endif; ?>
            <?php
            $startP = max(1, $page - 3);
            $endP   = min($totalPages, $page + 3);
            for ($p = $startP; $p <= $endP; $p++):
            ?>
            <?php if ($p === $page): ?>
            <span class="active"><?php echo $p; ?></span>
            <?php else: ?>
            <a href="?q=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&sort=<?php echo urlencode($sort); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
            <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?q=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&sort=<?php echo urlencode($sort); ?>&page=<?php echo $page + 1; ?>">&raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
