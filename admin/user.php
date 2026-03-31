<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('users.view');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/support_credits.php';
require_once __DIR__ . '/../includes/knd_xp.php';

$pdo = getDBConnection();
if (!$pdo) { echo 'DB connection failed.'; exit; }

$userId = (int) ($_GET['id'] ?? 0);
if ($userId <= 0) { header('Location: /admin/users.php'); exit; }

$csrfToken = csrf_token();
$flashMsg = '';
$flashType = '';

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        csrf_guard();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        rate_limit_guard($pdo, "admin_ud:{$ip}", 30, 300);

        $now = gmdate('Y-m-d H:i:s');

        switch ($_POST['action']) {
            case 'grant_available':
                $amount = (int) ($_POST['amount'] ?? 0);
                if ($amount <= 0 || $amount > 1000000) throw new \Exception('Amount must be 1–1,000,000.');
                $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+12 months'));
                $pdo->beginTransaction();
                $pdo->prepare(
                    "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
                     VALUES (?, 'adjustment', 0, 'earn', 'available', ?, ?, ?, ?)"
                )->execute([$userId, $amount, $now, $expiresAt, $now]);
                $pdo->commit();
                $flashMsg = "Granted {$amount} KP (available now).";
                $flashType = 'success';
                break;

            case 'remove_points':
                $amount = (int) ($_POST['amount'] ?? 0);
                if ($amount <= 0 || $amount > 1000000) throw new \Exception('Amount must be 1–1,000,000.');
                $pdo->beginTransaction();
                $avail = get_available_points($pdo, $userId);
                if ($avail < $amount) {
                    $pdo->rollBack();
                    throw new \Exception("Insufficient available KP ({$avail}).");
                }
                $pdo->prepare(
                    "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
                     VALUES (?, 'adjustment', 0, 'spend', 'spent', ?, ?)"
                )->execute([$userId, -$amount, $now]);
                $pdo->commit();
                $flashMsg = "Removed {$amount} KP.";
                $flashType = 'success';
                break;

            case 'force_release':
                $mode = $_POST['release_mode'] ?? 'safe';
                $pdo->beginTransaction();
                if ($mode === 'override') {
                    $stmt = $pdo->prepare("UPDATE points_ledger SET status = 'available', available_at = ? WHERE user_id = ? AND status = 'pending' AND entry_type = 'earn'");
                    $stmt->execute([$now, $userId]);
                    $count = $stmt->rowCount();
                } else {
                    $count = release_available_points_if_due($pdo, $userId);
                }
                $pdo->commit();
                $flashMsg = "Force release ({$mode}): {$count} entries moved.";
                $flashType = 'success';
                break;

            case 'toggle_risk':
                $stmt = $pdo->prepare('SELECT risk_flag FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $row = $stmt->fetch();
                if (!$row) throw new \Exception('User not found.');
                $newFlag = (int) $row['risk_flag'] === 1 ? 0 : 1;
                $pdo->prepare('UPDATE users SET risk_flag = ? WHERE id = ?')->execute([$newFlag, $userId]);
                require_once __DIR__ . '/_audit.php';
                admin_log_action('user_risk_flag_toggle', ['user_id' => $userId, 'risk_flag' => $newFlag]);
                $flashMsg = "Risk flag set to {$newFlag}.";
                $flashType = $newFlag ? 'warning' : 'success';
                break;

            case 'add_note':
                $note = trim($_POST['note'] ?? '');
                if ($note === '') throw new \Exception('Note cannot be empty.');
                $pdo->prepare('INSERT INTO admin_user_notes (user_id, admin_session, note, created_at) VALUES (?, ?, ?, ?)')
                    ->execute([$userId, 'admin', $note, $now]);
                $flashMsg = 'Note added.';
                $flashType = 'success';
                break;

            case 'level_up':
                admin_require_perm('leaderboard.view');
                $stmt = $pdo->prepare('SELECT xp FROM knd_user_xp WHERE user_id = ?');
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $curXp = $row ? (int) $row['xp'] : 0;
                $curLvl = xp_calc_level($curXp);
                if ($curLvl >= 30) throw new \Exception('User already at max level.');
                $xpNeeded = 100 * ($curLvl ** 2) - $curXp;
                if ($xpNeeded < 1) $xpNeeded = 1;
                $res = xp_admin_adjust($pdo, $userId, $xpNeeded);
                require_once __DIR__ . '/_audit.php';
                admin_log_action('xp_admin_level_up', ['user_id' => $userId, 'old_level' => $curLvl, 'new_level' => $res['level']]);
                $flashMsg = "Level up: {$curLvl} → {$res['level']} (+{$xpNeeded} XP).";
                $flashType = 'success';
                break;

            case 'level_down':
                admin_require_perm('leaderboard.view');
                $stmt = $pdo->prepare('SELECT xp FROM knd_user_xp WHERE user_id = ?');
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $curXp = $row ? (int) $row['xp'] : 0;
                $curLvl = xp_calc_level($curXp);
                if ($curLvl <= 1) throw new \Exception('User already at level 1.');
                $targetXp = 100 * (max(0, $curLvl - 2) ** 2);
                $delta = $targetXp - $curXp;
                $res = xp_admin_adjust($pdo, $userId, $delta);
                require_once __DIR__ . '/_audit.php';
                admin_log_action('xp_admin_level_down', ['user_id' => $userId, 'old_level' => $curLvl, 'new_level' => $res['level']]);
                $flashMsg = "Level down: {$curLvl} → {$res['level']}.";
                $flashType = 'success';
                break;

            default:
                throw new \Exception('Unknown action.');
        }
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flashMsg = $e->getMessage();
        $flashType = 'danger';
    }

    $_SESSION['ud_flash'] = ['msg' => $flashMsg, 'type' => $flashType];
    header('Location: /admin/user.php?id=' . $userId);
    exit;
}

if (!empty($_SESSION['ud_flash'])) {
    $flashMsg  = $_SESSION['ud_flash']['msg'];
    $flashType = $_SESSION['ud_flash']['type'];
    unset($_SESSION['ud_flash']);
}

// ── Fetch user data ──
$stmt = $pdo->prepare('SELECT id, username, email, email_verified, risk_flag, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    header('Location: /admin/users.php');
    exit;
}

// KP balance
release_available_points_if_due($pdo, $userId);
expire_points_if_due($pdo, $userId);
$balance = get_points_balance($pdo, $userId);
$balance['available_now'] = get_available_points($pdo, $userId);

// Last seen
$lastSeen = null;
try {
    $stmt = $pdo->prepare('SELECT last_seen FROM user_presence WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $lastSeen = $row['last_seen'] ?? null;
} catch (\Throwable $e) {}

// XP & Level (knd_user_xp preferred)
$xpTotal = 0;
$userLevel = 1;
try {
    $stmt = $pdo->prepare('SELECT xp, level FROM knd_user_xp WHERE user_id = ?');
    $stmt->execute([$userId]);
    $xpRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($xpRow) {
        $xpTotal = (int) ($xpRow['xp'] ?? 0);
        $userLevel = min(30, max(1, (int) ($xpRow['level'] ?? 1)));
    } else {
        $stmt = $pdo->prepare('SELECT xp FROM user_xp WHERE user_id = ?');
        $stmt->execute([$userId]);
        $xpRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $xpTotal = (int) ($xpRow['xp'] ?? 0);
        $userLevel = xp_calc_level($xpTotal);
    }
} catch (\Throwable $e) {}

// LastRoll stats
$lrStats = ['total' => 0, 'wins' => 0, 'losses' => 0];
try {
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN winner_user_id = ? THEN 1 ELSE 0 END) AS wins,
            SUM(CASE WHEN loser_user_id = ? THEN 1 ELSE 0 END) AS losses
         FROM deathroll_games_1v1
         WHERE status = 'finished' AND (player1_user_id = ? OR player2_user_id = ?)"
    );
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $lrStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $lrStats;
} catch (\Throwable $e) {}

// Above/Under stats
$auStats = ['total' => 0, 'wins' => 0, 'losses' => 0, 'net_kp' => 0];
try {
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(is_win) AS wins,
            SUM(CASE WHEN is_win = 0 THEN 1 ELSE 0 END) AS losses,
            SUM(CASE WHEN is_win = 1 THEN payout_points ELSE 0 END) - SUM(entry_points) AS net_kp
         FROM above_under_rolls WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $auStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $auStats;
} catch (\Throwable $e) {}

// Ledger (last 100)
$activeTab = $_GET['tab'] ?? 'ledger';
$ledgerRows = [];
$payments = [];
$redemptions = [];
$notes = [];

try {
    $statusFilter = $_GET['ls'] ?? '';
    $sourceFilter = $_GET['lsrc'] ?? '';
    $sql = "SELECT * FROM points_ledger WHERE user_id = ?";
    $p = [$userId];
    if ($statusFilter !== '') { $sql .= " AND status = ?"; $p[] = $statusFilter; }
    if ($sourceFilter !== '') { $sql .= " AND source_type = ?"; $p[] = $sourceFilter; }
    $sql .= " ORDER BY id DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    $ledgerRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

try {
    $stmt = $pdo->prepare('SELECT * FROM support_payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
    $stmt->execute([$userId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

try {
    $stmt = $pdo->prepare('SELECT * FROM reward_redemptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
    $stmt->execute([$userId]);
    $redemptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

try {
    $stmt = $pdo->prepare('SELECT * FROM admin_user_notes WHERE user_id = ? ORDER BY created_at DESC LIMIT 30');
    $stmt->execute([$userId]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

require_once __DIR__ . '/../includes/header.php';
echo generateHeader('User #' . $userId, 'Admin user detail');
echo generateAdminBar();
?>
<style>
.ud-dash { --cyan: #00d4ff; min-height:100vh; background:#0a0a0f; color:#e8ecf0; padding-top:100px; padding-bottom:60px; }
.ud-card { background:rgba(12,15,22,.85); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:1.25rem; margin-bottom:1rem; }
.ud-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
.ud-topbar h1 { font-size:1.5rem; font-weight:700; margin:0; }
.ud-kpi { font-size:1.8rem; font-weight:700; color:var(--cyan); line-height:1.1; font-family:'Orbitron',monospace; }
.ud-kpi-sm { font-size:.7rem; text-transform:uppercase; letter-spacing:.8px; color:rgba(255,255,255,.5); margin-top:.2rem; }
.ud-stat-card { text-align:center; }
.ud-tbl { font-size:.78rem; }
.ud-tbl th { color:rgba(255,255,255,.5); font-weight:600; text-transform:uppercase; letter-spacing:.5px; font-size:.7rem; }
.ud-risk-on { color:#f87171; font-weight:700; }
.ud-risk-off { color:#4ade80; }
.ud-tab-nav .nav-link { color:rgba(255,255,255,.5); border:1px solid transparent; font-size:.8rem; }
.ud-tab-nav .nav-link.active { color:var(--cyan); border-color:rgba(0,212,255,.3); background:rgba(0,212,255,.08); }
.ud-actions .btn { font-size:.78rem; }
</style>

<div class="ud-dash">
<div class="container">
    <div class="ud-topbar">
        <h1><i class="fas fa-user me-2" style="color:var(--cyan)"></i>User Detail</h1>
        <div class="d-flex gap-2">
            <a href="/admin/users.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Users</a>
            <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="fas fa-home me-1"></i>Dashboard</a>
        </div>
    </div>

    <?php if ($flashMsg): ?>
    <div class="alert alert-<?php echo $flashType; ?> alert-dismissible fade show"><?php echo htmlspecialchars($flashMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- User Header -->
    <div class="ud-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h3 class="mb-1" style="color:var(--cyan);">
                    #<?php echo $userData['id']; ?> — <?php echo htmlspecialchars($userData['username']); ?>
                </h3>
                <p class="mb-0 text-white-50" style="font-size:.85rem;">
                    <?php echo htmlspecialchars($userData['email'] ?? '—'); ?>
                    <?php if (!empty($userData['email_verified'])): ?><span class="badge bg-success ms-1">verified</span><?php else: ?><span class="badge bg-secondary ms-1">unverified</span><?php endif; ?>
                    &middot; Joined: <?php echo date('M j, Y H:i', strtotime($userData['created_at'])); ?>
                    &middot; Last seen:
                    <?php if ($lastSeen):
                        $isOnline = strtotime($lastSeen) >= (time() - 60);
                        echo $isOnline ? '<span class="text-success">Online</span>' : date('M j H:i', strtotime($lastSeen));
                    else: echo '<span class="text-white-50">never</span>'; endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <?php if ((int) $userData['risk_flag'] === 1): ?>
                <span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>RISK FLAGGED</span>
                <?php else: ?>
                <span class="badge bg-success"><i class="fas fa-check me-1"></i>CLEAN</span>
                <?php endif; ?>
                <a href="/admin/knd-points.php?u=<?php echo $userId; ?>" class="btn btn-outline-warning btn-sm"><i class="fas fa-wallet me-1"></i>Wallet Inspector</a>
            </div>
        </div>
    </div>

    <!-- KP & Stats Cards -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="ud-card ud-stat-card">
                <div class="ud-kpi"><?php echo number_format($balance['available_now']); ?></div>
                <div class="ud-kpi-sm">KP Available</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ud-card ud-stat-card">
                <div class="ud-kpi" style="color:#facc15;"><?php echo number_format($balance['pending']); ?></div>
                <div class="ud-kpi-sm">KP Pending</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ud-card ud-stat-card">
                <div class="ud-kpi" style="color:#f87171;"><?php echo number_format($balance['spent_total']); ?></div>
                <div class="ud-kpi-sm">KP Spent</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ud-card ud-stat-card">
                <div class="ud-kpi" style="color:#fb923c;"><?php echo count($balance['expiring_soon']); ?></div>
                <div class="ud-kpi-sm">Expiring Soon</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="ud-card ud-stat-card">
                <div class="ud-kpi"><?php echo number_format($xpTotal); ?></div>
                <div class="ud-kpi-sm">Total XP</div>
                <div style="font-size:.7rem; margin-top:.3rem;">Level <?php echo $userLevel; ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ud-card ud-stat-card">
                <div class="ud-kpi"><?php echo (int)($lrStats['total'] ?? 0); ?></div>
                <div class="ud-kpi-sm">LastRoll Games</div>
                <div style="font-size:.7rem; margin-top:.3rem;">
                    <span class="text-success"><?php echo (int)($lrStats['wins'] ?? 0); ?>W</span> /
                    <span class="text-danger"><?php echo (int)($lrStats['losses'] ?? 0); ?>L</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ud-card ud-stat-card">
                <div class="ud-kpi"><?php echo (int)($auStats['total'] ?? 0); ?></div>
                <div class="ud-kpi-sm">Above/Under</div>
                <div style="font-size:.7rem; margin-top:.3rem;">
                    <span class="text-success"><?php echo (int)($auStats['wins'] ?? 0); ?>W</span> /
                    <span class="text-danger"><?php echo (int)($auStats['losses'] ?? 0); ?>L</span>
                    &middot; Net: <span style="color:<?php echo (int)($auStats['net_kp'] ?? 0) >= 0 ? '#4ade80' : '#f87171'; ?>;"><?php echo number_format((int)($auStats['net_kp'] ?? 0)); ?> KP</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ud-card ud-stat-card">
                <?php
                $winRate = 0;
                $totalGames = (int)($lrStats['total'] ?? 0) + (int)($auStats['total'] ?? 0);
                $totalWins  = (int)($lrStats['wins'] ?? 0)  + (int)($auStats['wins'] ?? 0);
                if ($totalGames > 0) $winRate = round(($totalWins / $totalGames) * 100, 1);
                ?>
                <div class="ud-kpi"><?php echo $winRate; ?>%</div>
                <div class="ud-kpi-sm">Win Rate (All)</div>
                <div style="font-size:.7rem; margin-top:.3rem;"><?php echo $totalGames; ?> games played</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="ud-card ud-actions">
        <h5 class="mb-3" style="color:var(--cyan); font-size:1rem;"><i class="fas fa-tools me-2"></i>Quick Actions</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="grant_available">
                    <label class="form-label small text-white-50">Grant Available KP</label>
                    <div class="input-group input-group-sm">
                        <input type="number" name="amount" class="form-control" min="1" max="1000000" required placeholder="KP">
                        <button class="btn btn-success btn-sm" onclick="return confirm('Grant available KP?')"><i class="fas fa-plus"></i></button>
                    </div>
                </form>
            </div>
            <div class="col-md-4">
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="remove_points">
                    <label class="form-label small text-white-50">Remove KP</label>
                    <div class="input-group input-group-sm">
                        <input type="number" name="amount" class="form-control" min="1" max="1000000" required placeholder="KP">
                        <button class="btn btn-danger btn-sm" onclick="return confirm('Remove KP?')"><i class="fas fa-minus"></i></button>
                    </div>
                </form>
            </div>
            <div class="col-md-4">
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="force_release">
                    <label class="form-label small text-white-50">Force Release</label>
                    <select name="release_mode" class="form-select form-select-sm mb-1">
                        <option value="safe">Safe</option>
                        <option value="override">Override All</option>
                    </select>
                    <button class="btn btn-info btn-sm w-100" onclick="return confirm('Force release?')"><i class="fas fa-unlock me-1"></i>Release</button>
                </form>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <?php if (admin_has_perm('leaderboard.view')): ?>
                <div class="p-2 rounded mb-2" style="border:1px solid rgba(255,255,255,.06);">
                    <label class="form-label small text-white-50">Level (<?php echo $userLevel; ?>/30)</label>
                    <div class="d-flex gap-1">
                        <form method="post" class="flex-grow-1" onsubmit="return confirm('Subir de nivel a este usuario?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="level_up">
                            <button class="btn btn-success btn-sm w-100" <?php echo $userLevel >= 30 ? 'disabled' : ''; ?>><i class="fas fa-arrow-up me-1"></i>+1</button>
                        </form>
                        <form method="post" class="flex-grow-1" onsubmit="return confirm('Bajar de nivel a este usuario?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="level_down">
                            <button class="btn btn-danger btn-sm w-100" <?php echo $userLevel <= 1 ? 'disabled' : ''; ?>><i class="fas fa-arrow-down me-1"></i>-1</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="toggle_risk">
                    <label class="form-label small text-white-50">Risk Flag</label>
                    <p class="mb-1 small">
                        Current: <?php echo (int) $userData['risk_flag'] === 1
                            ? '<span class="ud-risk-on">ON</span>'
                            : '<span class="ud-risk-off">OFF</span>'; ?>
                    </p>
                    <button class="btn btn-outline-warning btn-sm w-100" onclick="return confirm('Toggle risk flag?')"><i class="fas fa-flag me-1"></i>Toggle</button>
                </form>
            </div>
            <div class="col-md-8">
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add_note">
                    <label class="form-label small text-white-50">Admin Note</label>
                    <div class="input-group input-group-sm">
                        <textarea name="note" class="form-control" rows="1" required placeholder="Write a note…"></textarea>
                        <button class="btn btn-outline-light btn-sm"><i class="fas fa-sticky-note me-1"></i>Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs ud-tab-nav mb-0 mt-4" role="tablist">
        <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'ledger' ? 'active' : ''; ?>" href="?id=<?php echo $userId; ?>&tab=ledger">Ledger</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'payments' ? 'active' : ''; ?>" href="?id=<?php echo $userId; ?>&tab=payments">Payments</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'redemptions' ? 'active' : ''; ?>" href="?id=<?php echo $userId; ?>&tab=redemptions">Redemptions</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'notes' ? 'active' : ''; ?>" href="?id=<?php echo $userId; ?>&tab=notes">Notes (<?php echo count($notes); ?>)</a></li>
    </ul>

    <!-- Tab Content -->
    <?php if ($activeTab === 'ledger'): ?>
    <div class="ud-card" style="border-top-left-radius:0; border-top-right-radius:0;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="mb-0" style="color:var(--cyan); font-size:.9rem;">Points Ledger (last 100)</h6>
            <form method="get" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="id" value="<?php echo $userId; ?>">
                <input type="hidden" name="tab" value="ledger">
                <select name="ls" class="form-select form-select-sm" style="width:auto; font-size:.75rem;">
                    <option value="">All Status</option>
                    <?php foreach (['pending','available','locked','spent','expired'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo ($_GET['ls'] ?? '') === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="lsrc" class="form-select form-select-sm" style="width:auto; font-size:.75rem;">
                    <option value="">All Source</option>
                    <?php foreach (['support_payment','redemption','adjustment','lastroll_entry','lastroll_payout','above_under_entry','above_under_payout'] as $sr): ?>
                    <option value="<?php echo $sr; ?>" <?php echo ($_GET['lsrc'] ?? '') === $sr ? 'selected' : ''; ?>><?php echo $sr; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-info"><i class="fas fa-filter"></i></button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-dark ud-tbl mb-0" style="--bs-table-bg:transparent;">
                <thead><tr>
                    <th>#</th><th>Date</th><th>Type</th><th>Amount</th><th>Status</th><th>Source</th><th>Ref</th><th>Available</th><th>Expires</th>
                </tr></thead>
                <tbody>
                <?php foreach ($ledgerRows as $lr): ?>
                <tr>
                    <td><?php echo $lr['id']; ?></td>
                    <td><?php echo date('M j H:i', strtotime($lr['created_at'])); ?></td>
                    <td><?php echo $lr['entry_type']; ?></td>
                    <td style="font-weight:700; color:<?php echo (int) $lr['points'] >= 0 ? '#4ade80' : '#f87171'; ?>;">
                        <?php echo (int) $lr['points'] >= 0 ? '+' : ''; ?><?php echo number_format((int) $lr['points']); ?>
                    </td>
                    <td><span class="badge bg-<?php echo match($lr['status']) { 'available'=>'success','pending'=>'warning','spent'=>'secondary','expired'=>'dark','locked'=>'danger',default=>'secondary' }; ?>"><?php echo $lr['status']; ?></span></td>
                    <td><?php echo htmlspecialchars($lr['source_type']); ?></td>
                    <td><?php echo $lr['source_id'] ?: '—'; ?></td>
                    <td><?php echo !empty($lr['available_at']) ? date('M j', strtotime($lr['available_at'])) : '—'; ?></td>
                    <td><?php echo !empty($lr['expires_at']) ? date('M j, Y', strtotime($lr['expires_at'])) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($ledgerRows)): ?>
                <tr><td colspan="9" class="text-center text-white-50">No entries.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($activeTab === 'payments'): ?>
    <div class="ud-card" style="border-top-left-radius:0; border-top-right-radius:0;">
        <h6 class="mb-3" style="color:var(--cyan); font-size:.9rem;">Payment History</h6>
        <div class="table-responsive">
            <table class="table table-sm table-dark ud-tbl mb-0" style="--bs-table-bg:transparent;">
                <thead><tr>
                    <th>#</th><th>Date</th><th>Method</th><th>Amount</th><th>Status</th><th>Points</th><th>Ref</th>
                </tr></thead>
                <tbody>
                <?php foreach ($payments as $pay): ?>
                <tr>
                    <td><?php echo $pay['id']; ?></td>
                    <td><?php echo date('M j H:i', strtotime($pay['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($pay['payment_method'] ?? '—'); ?></td>
                    <td>$<?php echo number_format((float)($pay['amount_usd'] ?? 0), 2); ?></td>
                    <td><span class="badge bg-<?php echo match($pay['status'] ?? '') { 'confirmed'=>'success','pending'=>'warning','rejected'=>'danger',default=>'secondary' }; ?>"><?php echo htmlspecialchars($pay['status'] ?? '—'); ?></span></td>
                    <td><?php echo number_format((int)($pay['points_awarded'] ?? 0)); ?></td>
                    <td class="text-white-50" style="font-size:.7rem;"><?php echo htmlspecialchars($pay['reference'] ?? $pay['transaction_id'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                <tr><td colspan="7" class="text-center text-white-50">No payments.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($activeTab === 'redemptions'): ?>
    <div class="ud-card" style="border-top-left-radius:0; border-top-right-radius:0;">
        <h6 class="mb-3" style="color:var(--cyan); font-size:.9rem;">Redemption History</h6>
        <div class="table-responsive">
            <table class="table table-sm table-dark ud-tbl mb-0" style="--bs-table-bg:transparent;">
                <thead><tr>
                    <th>#</th><th>Date</th><th>Reward</th><th>Cost</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($redemptions as $rd): ?>
                <tr>
                    <td><?php echo $rd['id']; ?></td>
                    <td><?php echo date('M j H:i', strtotime($rd['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($rd['reward_name'] ?? 'Reward #' . ($rd['reward_id'] ?? '?')); ?></td>
                    <td><?php echo number_format((int)($rd['points_cost'] ?? 0)); ?> KP</td>
                    <td><span class="badge bg-<?php echo match($rd['status'] ?? '') { 'fulfilled'=>'success','requested'=>'warning','rejected'=>'danger',default=>'secondary' }; ?>"><?php echo htmlspecialchars($rd['status'] ?? '—'); ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($redemptions)): ?>
                <tr><td colspan="5" class="text-center text-white-50">No redemptions.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($activeTab === 'notes'): ?>
    <div class="ud-card" style="border-top-left-radius:0; border-top-right-radius:0;">
        <h6 class="mb-3" style="color:var(--cyan); font-size:.9rem;">Admin Notes</h6>
        <?php if (empty($notes)): ?>
        <p class="text-white-50 text-center">No notes yet.</p>
        <?php else: ?>
        <?php foreach ($notes as $n): ?>
        <div class="mb-2 p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
            <small class="text-white-50"><?php echo date('M j, Y H:i', strtotime($n['created_at'])); ?> — <?php echo htmlspecialchars($n['admin_session'] ?? 'admin'); ?></small>
            <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($n['note'])); ?></p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
