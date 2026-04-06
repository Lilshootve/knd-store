<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/_guard.php';
require_once BASE_PATH . '/includes/csrf.php';

admin_require_login();

$pdo = getDBConnection();
if (!$pdo) {
    header('Content-Type: text/html; charset=utf-8');
    echo 'Database connection failed.';
    exit;
}

$csrfToken = csrf_token();
$q = trim((string) ($_GET['q'] ?? ''));
$slot = trim((string) ($_GET['slot'] ?? ''));
$rarity = trim((string) ($_GET['rarity'] ?? ''));
$status = trim((string) ($_GET['status'] ?? 'active'));

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(name LIKE ? OR asset_path LIKE ? OR code LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($slot !== '') {
    $where[] = 'slot = ?';
    $params[] = $slot;
}
if ($rarity !== '') {
    $where[] = 'rarity = ?';
    $params[] = $rarity;
}
if ($status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'is_active = 0';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM knd_avatar_items {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$sql = "SELECT id, code, slot, rarity, name, asset_path, is_active, price_kp
        FROM knd_avatar_items
        {$whereSql}
        ORDER BY is_active DESC, FIELD(rarity, 'legendary', 'epic', 'rare', 'special', 'common'), id DESC
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avatar Items | KND Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .avadm { min-height: 100vh; background:#0a0a0f; color:#e8ecf0; padding:2rem 0; --cyan:#00d4ff; }
        .av-card { background:rgba(12,15,22,.9); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:1rem; }
        .av-topbar { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
        .av-title { margin:0; font-size:1.4rem; font-weight:700; }
        .av-muted { color:rgba(255,255,255,.62); font-size:.82rem; }
        .av-table th { font-size:.72rem; text-transform:uppercase; color:var(--cyan); letter-spacing:.06em; border-color:rgba(255,255,255,.08); }
        .av-table td { border-color:rgba(255,255,255,.08); vertical-align:middle; }
        .av-name-input { min-width:260px; }
        .av-path { max-width:330px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .av-status-ok { color:#4ade80; }
        .av-status-off { color:#f87171; }
        .av-pager a, .av-pager span { display:inline-block; margin:0 .15rem; padding:.22rem .56rem; border-radius:6px; text-decoration:none; border:1px solid rgba(255,255,255,.12); color:#e8ecf0; font-size:.8rem; }
        .av-pager .active { background:var(--cyan); color:#0a0a0f; border-color:var(--cyan); font-weight:700; }
        .avadm .form-control,
        .avadm .form-select {
            background-color: rgba(6, 10, 22, 0.95);
            border-color: rgba(255, 255, 255, 0.18);
            color: #e8ecf0;
        }
        .avadm .form-control::placeholder {
            color: rgba(232, 236, 240, 0.45);
        }
        .avadm .form-control:focus,
        .avadm .form-select:focus {
            background-color: rgba(8, 12, 26, 0.98);
            border-color: rgba(0, 212, 255, 0.7);
            color: #ffffff;
            box-shadow: 0 0 0 0.2rem rgba(0, 212, 255, 0.2);
        }
        .avadm .form-select option {
            background: #0e1528;
            color: #eef3ff;
        }
        .avadm .btn-outline-secondary {
            color: #c8d0e6;
            border-color: rgba(200, 208, 230, 0.45);
        }
        .avadm .btn-outline-secondary:hover {
            color: #0a0a0f;
            background: #c8d0e6;
            border-color: #c8d0e6;
        }
    </style>
</head>
<body class="avadm">
<div class="container">
    <div class="av-topbar">
        <h1 class="av-title"><i class="fas fa-id-card me-2" style="color:var(--cyan)"></i>Avatar Items</h1>
        <div class="d-flex gap-2">
            <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
            <a href="/admin/avatar_sync_items.php" class="btn btn-outline-info btn-sm" target="_blank">Sync</a>
            <a href="/admin/avatar_sync_items.php?force_names=1" class="btn btn-info btn-sm" target="_blank">Sync + Force Names</a>
        </div>
    </div>

    <div class="av-card mb-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-white-50 mb-1">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?php echo htmlspecialchars($q); ?>" placeholder="name, code, path...">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-white-50 mb-1">Slot</label>
                <select name="slot" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['frame','hair','top','bottom','shoes','accessory','bg'] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $slot === $opt ? 'selected' : ''; ?>><?php echo ucfirst($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-white-50 mb-1">Rarity</label>
                <select name="rarity" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['legendary','epic','rare','special','common'] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $rarity === $opt ? 'selected' : ''; ?>><?php echo ucfirst($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-white-50 mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="/admin/avatar_items.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
        <p class="av-muted mb-0 mt-2">Edit names here to keep profile/drop labels stable. Normal sync keeps manual names; force sync can regenerate from filename.</p>
    </div>

    <div class="av-card">
        <div class="table-responsive">
            <table class="table table-dark table-sm av-table mb-0" style="--bs-table-bg:transparent;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Preview</th>
                        <th>Name</th>
                        <th>Slot / Rarity</th>
                        <th>Path</th>
                        <th>Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-white-50">No avatar items found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $item): ?>
                        <tr>
                            <td><?php echo (int) $item['id']; ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($item['asset_path']); ?>" alt="" style="width:42px;height:42px;object-fit:contain;">
                            </td>
                            <td>
                                <form class="avatar-name-form d-flex gap-2 align-items-center mb-0" data-item-id="<?php echo (int) $item['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                                    <input class="form-control form-control-sm av-name-input" type="text" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" maxlength="120">
                                    <button class="btn btn-outline-info btn-sm" type="submit">Save</button>
                                </form>
                            </td>
                            <td>
                                <div><strong><?php echo htmlspecialchars($item['slot']); ?></strong></div>
                                <div class="text-white-50 small"><?php echo htmlspecialchars($item['rarity']); ?></div>
                            </td>
                            <td class="av-path" title="<?php echo htmlspecialchars($item['asset_path']); ?>">
                                <?php echo htmlspecialchars($item['asset_path']); ?>
                            </td>
                            <td><?php echo number_format((int) $item['price_kp']); ?> KP</td>
                            <td>
                                <?php if ((int) $item['is_active'] === 1): ?>
                                    <span class="av-status-ok"><i class="fas fa-circle-check me-1"></i>Active</span>
                                <?php else: ?>
                                    <span class="av-status-off"><i class="fas fa-circle-xmark me-1"></i>Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="av-pager mt-3">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php
                    $query = http_build_query([
                        'q' => $q,
                        'slot' => $slot,
                        'rarity' => $rarity,
                        'status' => $status,
                        'page' => $p,
                    ]);
                ?>
                <?php if ($p === $page): ?>
                    <span class="active"><?php echo $p; ?></span>
                <?php else: ?>
                    <a href="?<?php echo htmlspecialchars($query); ?>"><?php echo $p; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const forms = document.querySelectorAll('.avatar-name-form');
    forms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const oldText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const body = new FormData(form);
                const res = await fetch('/admin/api/avatar_update_name.php', {
                    method: 'POST',
                    body
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    throw new Error(data?.error?.message || 'Could not save name');
                }
                btn.textContent = 'Saved';
                setTimeout(() => {
                    btn.textContent = oldText;
                }, 900);
            } catch (err) {
                btn.textContent = 'Error';
                setTimeout(() => {
                    btn.textContent = oldText;
                }, 1200);
                alert(err.message || 'Could not update name');
            } finally {
                setTimeout(() => {
                    btn.disabled = false;
                }, 250);
            }
        });
    });
})();
</script>
</body>
</html>

