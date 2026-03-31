<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('rewards.edit');

$pdo = getDBConnection();
if (!$pdo) {
    echo 'Database connection failed.';
    exit;
}

$flashMsg = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reward_action'])) {
    $rewardAction = $_POST['reward_action'];
    $now = gmdate('Y-m-d H:i:s');

    try {
        if ($rewardAction === 'create') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '') ?: null;
            $category = trim($_POST['category'] ?? 'knd_service');
            $pointsCost = max(1, (int) ($_POST['points_cost'] ?? 0));
            $stock = trim($_POST['stock'] ?? '');
            $stockVal = $stock === '' ? null : max(0, (int) $stock);

            if ($title === '' || $pointsCost <= 0) {
                $flashMsg = 'Title and points cost are required.';
                $flashType = 'danger';
            } else {
                $pdo->prepare(
                    "INSERT INTO rewards_catalog (title, description, category, points_cost, is_active, stock, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 1, ?, ?, ?)"
                )->execute([$title, $description, $category, $pointsCost, $stockVal, $now, $now]);
                require_once __DIR__ . '/_audit.php';
                admin_log_action('reward_create', ['title' => $title, 'points_cost' => $pointsCost]);
                $flashMsg = "Reward '$title' created.";
                $flashType = 'success';
            }
        } elseif ($rewardAction === 'update') {
            $id = (int) ($_POST['reward_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '') ?: null;
            $category = trim($_POST['category'] ?? 'knd_service');
            $pointsCost = max(1, (int) ($_POST['points_cost'] ?? 0));
            $stock = trim($_POST['stock'] ?? '');
            $stockVal = $stock === '' ? null : max(0, (int) $stock);
            $isActive = (int) ($_POST['is_active'] ?? 1);

            $pdo->prepare(
                "UPDATE rewards_catalog SET title=?, description=?, category=?, points_cost=?, is_active=?, stock=?, updated_at=? WHERE id=?"
            )->execute([$title, $description, $category, $pointsCost, $isActive, $stockVal, $now, $id]);
            require_once __DIR__ . '/_audit.php';
            admin_log_action('reward_update', ['reward_id' => $id, 'title' => $title]);
            $flashMsg = "Reward #$id updated.";
            $flashType = 'success';
        } elseif ($rewardAction === 'toggle') {
            $id = (int) ($_POST['reward_id'] ?? 0);
            $pdo->prepare(
                "UPDATE rewards_catalog SET is_active = IF(is_active=1,0,1), updated_at=? WHERE id=?"
            )->execute([$now, $id]);
            require_once __DIR__ . '/_audit.php';
            admin_log_action('reward_toggle', ['reward_id' => $id]);
            $flashMsg = "Reward #$id toggled.";
            $flashType = 'success';
        }
    } catch (\Throwable $e) {
        $flashMsg = 'Error: ' . $e->getMessage();
        $flashType = 'danger';
    }
}

$rewards = $pdo->query('SELECT * FROM rewards_catalog ORDER BY is_active DESC, points_cost ASC')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$editReward = null;
if ($editId > 0) {
    foreach ($rewards as $r) {
        if ((int) $r['id'] === $editId) {
            $editReward = $r;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Rewards Catalog</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        body { background: #0a0f1e; color: #e0e0e0; }
        .card { background: rgba(15,20,40,0.95); border: 1px solid rgba(37,156,174,0.2); }
        .table { color: #e0e0e0; }
    </style>
</head>
<body class="admin-page">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-gift me-2"></i>Rewards Catalog</h1>
        <div>
            <a href="/admin/" class="btn btn-outline-secondary btn-sm me-2"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
            <a href="/admin/support-credits.php" class="btn btn-outline-info btn-sm me-2"><i class="fas fa-coins me-1"></i>Points</a>
            <a href="?logout" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
        </div>
    </div>

    <?php if ($flashMsg): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show">
            <?= htmlspecialchars($flashMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h5 class="mb-3"><?= $editReward ? 'Edit Reward #' . $editReward['id'] : 'Create Reward' ?></h5>
                <form method="post">
                    <input type="hidden" name="reward_action" value="<?= $editReward ? 'update' : 'create' ?>">
                    <?php if ($editReward): ?><input type="hidden" name="reward_id" value="<?= $editReward['id'] ?>"><?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control form-control-sm bg-dark text-light border-secondary" value="<?= htmlspecialchars($editReward['title'] ?? '') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control form-control-sm bg-dark text-light border-secondary" rows="2"><?= htmlspecialchars($editReward['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select form-select-sm bg-dark text-light border-secondary">
                            <option value="knd_service" <?= ($editReward['category'] ?? '') === 'knd_service' ? 'selected' : '' ?>>KND Service</option>
                            <option value="beauty_selfcare" <?= ($editReward['category'] ?? '') === 'beauty_selfcare' ? 'selected' : '' ?>>Beauty & Self-care</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Points Cost</label>
                        <input type="number" name="points_cost" class="form-control form-control-sm bg-dark text-light border-secondary" min="1" value="<?= (int) ($editReward['points_cost'] ?? 100) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Stock (blank = unlimited)</label>
                        <input type="number" name="stock" class="form-control form-control-sm bg-dark text-light border-secondary" min="0" value="<?= $editReward && $editReward['stock'] !== null ? (int) $editReward['stock'] : '' ?>">
                    </div>
                    <?php if ($editReward): ?>
                    <div class="mb-2">
                        <label class="form-label">Active</label>
                        <select name="is_active" class="form-select form-select-sm bg-dark text-light border-secondary">
                            <option value="1" <?= (int) $editReward['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= (int) $editReward['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm w-100 mt-2"><?= $editReward ? 'Update' : 'Create' ?></button>
                    <?php if ($editReward): ?>
                        <a href="/admin/rewards.php" class="btn btn-outline-secondary btn-sm w-100 mt-1">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card p-3">
                <h5 class="mb-3">All Rewards</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr><th>#</th><th>Title</th><th>Category</th><th>Cost</th><th>Stock</th><th>Active</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rewards as $r): ?>
                            <tr class="<?= (int) $r['is_active'] === 0 ? 'opacity-50' : '' ?>">
                                <td><?= $r['id'] ?></td>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><?= htmlspecialchars($r['category']) ?></td>
                                <td><?= number_format($r['points_cost']) ?></td>
                                <td><?= $r['stock'] === null ? '∞' : $r['stock'] ?></td>
                                <td><?= (int) $r['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                                <td>
                                    <a href="?edit=<?= $r['id'] ?>" class="btn btn-outline-info btn-sm"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="reward_action" value="toggle">
                                        <input type="hidden" name="reward_id" value="<?= $r['id'] ?>">
                                        <button class="btn btn-outline-warning btn-sm"><i class="fas fa-power-off"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
