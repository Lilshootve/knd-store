<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('leaderboard.view');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/knd_xp.php';

$pdo = getDBConnection();
if (!$pdo) { echo 'DB connection failed.'; exit; }

$csrfToken = csrf_token();
$flashMsg = '';
$flashType = '';

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        csrf_guard();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        rate_limit_guard($pdo, "admin_lb:{$ip}", 10, 60);

        switch ($_POST['action']) {
            case 'new_season':
                admin_require_perm('leaderboard.reset_season');
                $pdo->prepare("UPDATE knd_seasons SET is_active = 0 WHERE is_active = 1")->execute();
                $stmt = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 AS n FROM knd_seasons");
                $n = (int) $stmt->fetchColumn();
                $code = 'GENESIS_S' . $n;
                $name = 'KND Genesis Season ' . $n;
                $pdo->prepare(
                    "INSERT INTO knd_seasons (code, name, starts_at, ends_at, is_active) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1)"
                )->execute([$code, $name]);
                @unlink(sys_get_temp_dir() . '/knd_lb/state_v2.json');
                $flashMsg = "New season created: {$name}. Season stats will start from zero.";
                $flashType = 'success';
                break;

            case 'reset_season_stats':
                admin_require_perm('leaderboard.reset_season');
                $pdo->prepare("DELETE FROM knd_season_stats")->execute();
                @unlink(sys_get_temp_dir() . '/knd_lb/state_v2.json');
                require_once __DIR__ . '/_audit.php';
                $reason = trim($_POST['reason'] ?? '') ?: null;
                admin_log_action('leaderboard_reset_season_stats', ['reason' => $reason]);
                $flashMsg = 'Season stats cleared. Tab "Season" will show no data until users play again.';
                $flashType = 'success';
                break;

            case 'reset_all_xp':
                admin_require_perm('leaderboard.reset_season');
                $pdo->beginTransaction();
                try {
                    $pdo->exec("DELETE FROM knd_season_stats");
                    if ($pdo->query("SHOW TABLES LIKE 'knd_user_xp'")->rowCount() > 0) {
                        $pdo->exec("DELETE FROM knd_user_xp");
                    }
                    $pdo->exec("DELETE FROM user_xp");
                    $pdo->commit();
                    @unlink(sys_get_temp_dir() . '/knd_lb/state_v2.json');
                    require_once __DIR__ . '/_audit.php';
                    $reason = trim($_POST['reason'] ?? '') ?: null;
                    admin_log_action('leaderboard_reset_all_xp', ['reason' => $reason]);
                    $flashMsg = 'All XP cleared. Hall of Fame and Season both start from zero.';
                    $flashType = 'success';
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;

            case 'reactivate_season':
                admin_require_perm('leaderboard.reset_season');
                $sid = (int) ($_POST['season_id'] ?? 0);
                if ($sid <= 0) throw new \Exception('Invalid season ID.');
                $pdo->prepare("UPDATE knd_seasons SET is_active = 0")->execute();
                $chk = $pdo->prepare("SELECT ends_at FROM knd_seasons WHERE id = ?");
                $chk->execute([$sid]);
                $row = $chk->fetch(PDO::FETCH_ASSOC);
                if ($row && strtotime($row['ends_at']) <= time()) {
                    $pdo->prepare("UPDATE knd_seasons SET is_active = 1, ends_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?")->execute([$sid]);
                    $flashMsg = 'Season reactivated and extended by 30 days (it had already ended).';
                } else {
                    $pdo->prepare("UPDATE knd_seasons SET is_active = 1 WHERE id = ?")->execute([$sid]);
                    $flashMsg = 'Season reactivated.';
                }
                @unlink(sys_get_temp_dir() . '/knd_lb/state_v2.json');
                require_once __DIR__ . '/_audit.php';
                $reason = trim($_POST['reason'] ?? '') ?: null;
                admin_log_action('leaderboard_reactivate_season', ['season_id' => $sid, 'reason' => $reason]);
                $flashType = 'success';
                break;

            default:
                throw new \Exception('Unknown action.');
        }
    } catch (\Throwable $e) {
        $flashMsg = 'Error: ' . htmlspecialchars($e->getMessage());
        $flashType = 'danger';
    }
}

$season = get_active_season($pdo);
$seasonName = $season ? $season['name'] : '—';
$seasonEnds = $season ? $season['ends_at'] : '—';

$allSeasons = [];
try {
    $stmt = $pdo->query("SELECT id, code, name, starts_at, ends_at, is_active FROM knd_seasons ORDER BY id DESC");
    $allSeasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard Admin | KND Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="bg-dark text-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-trophy me-2" style="color:#00d4ff;"></i>Leaderboard Admin</h2>
        <a href="/admin/" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <?php if ($flashMsg): ?>
    <div class="alert alert-<?php echo $flashType; ?>"><?php echo $flashMsg; ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card bg-secondary bg-opacity-25 border border-secondary">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-calendar-alt me-2 text-info"></i>Current Season</h5>
                    <p class="mb-1"><strong><?php echo htmlspecialchars($seasonName); ?></strong></p>
                    <p class="mb-0 small text-white-50">Ends: <?php echo htmlspecialchars($seasonEnds); ?></p>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="card bg-secondary bg-opacity-25 border border-secondary">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-forward me-2 text-warning"></i>Start New Season</h5>
                    <p class="card-text small text-white-50">End current season and create a new 30-day one. Season tab will start from zero.</p>
                    <form method="post" class="mt-2" onsubmit="return confirm('Create a new season? Current season will be marked inactive.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="new_season">
                        <button type="submit" class="btn btn-warning btn-sm">Start New Season</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="card bg-secondary bg-opacity-25 border border-secondary">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-eraser me-2 text-info"></i>Reset Season Stats</h5>
                    <p class="card-text small text-white-50">Clear all season stats. Tab "Season" goes empty. Hall of Fame unchanged.</p>
                    <form method="post" class="mt-2" onsubmit="return confirm('Clear all season stats?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="reset_season_stats">
                        <button type="submit" class="btn btn-outline-info btn-sm">Reset Season Stats</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="card bg-secondary bg-opacity-25 border border-danger">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-bomb me-2 text-danger"></i>Reset All XP</h5>
                    <p class="card-text small text-white-50">Clear user_xp, knd_user_xp and season stats. Hall of Fame and Season both start from zero.</p>
                    <form method="post" class="mt-2" onsubmit="return confirm('RESET ALL XP? This cannot be undone. Hall of Fame and Season will be empty.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="reset_all_xp">
                        <button type="submit" class="btn btn-danger btn-sm">Reset All XP</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card bg-secondary bg-opacity-25 border border-secondary">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-history me-2 text-info"></i>All Seasons</h5>
                    <p class="card-text small text-white-50 mb-3">Reactivate a previous season to use it again.</p>
                    <?php if (empty($allSeasons)): ?>
                    <p class="mb-0 text-white-50">No seasons found.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm mb-0">
                            <thead><tr><th>ID</th><th>Name</th><th>Code</th><th>Starts</th><th>Ends</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($allSeasons as $s): ?>
                            <tr>
                                <td><?php echo (int)$s['id']; ?></td>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><code><?php echo htmlspecialchars($s['code']); ?></code></td>
                                <td class="small"><?php echo htmlspecialchars($s['starts_at'] ?? '—'); ?></td>
                                <td class="small"><?php echo htmlspecialchars($s['ends_at'] ?? '—'); ?></td>
                                <td>
                                    <?php if (!empty($s['is_active'])): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (empty($s['is_active'])): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Reactivate <?php echo htmlspecialchars(addslashes($s['name'])); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="reactivate_season">
                                        <input type="hidden" name="season_id" value="<?php echo (int)$s['id']; ?>">
                                        <button type="submit" class="btn btn-outline-success btn-sm">Reactivate</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
