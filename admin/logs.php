<?php
/**
 * Admin Audit Logs - view with filters (logs.view)
 */
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('logs.view');

$pdo = getDBConnection();
if (!$pdo) {
    header('Content-Type: text/html; charset=utf-8');
    echo 'Database connection failed.';
    exit;
}

$actionFilter = trim($_GET['action'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($actionFilter !== '') {
    $where[] = 'action = ?';
    $params[] = $actionFilter;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_logs $whereSQL");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT id, admin_id, admin_ip, admin_user, action, meta_json, created_at
     FROM admin_audit_logs $whereSQL
     ORDER BY id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$actions = $pdo->query("SELECT DISTINCT action FROM admin_audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs | KND Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        body { background: #0a0a0f; color: #e8ecf0; padding: 2rem 0; }
        .card { background: rgba(12,15,22,.85); border: 1px solid rgba(255,255,255,.08); }
        .table { color: #e8ecf0; }
        .table th { border-color: rgba(255,255,255,.1); color: #00d4ff; }
        .table td { border-color: rgba(255,255,255,.08); }
        .meta-json { font-size: 0.8rem; max-width: 300px; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body class="admin-page">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-list-alt me-2" style="color:#00d4ff"></i>Audit Logs</h2>
        <a href="/admin/" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Home</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-0">Action</label>
                    <select name="action" class="form-select form-select-sm" style="min-width:180px;">
                        <option value="">All</option>
                        <?php foreach ($actions as $a): ?>
                        <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $actionFilter === $a ? 'selected' : ''; ?>><?php echo htmlspecialchars($a); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>ID</th><th>Time</th><th>Admin</th><th>Action</th><th>IP</th><th>Meta</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo (int)$r['id']; ?></td>
                            <td class="small"><?php echo htmlspecialchars($r['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($r['admin_user'] ?? '—'); ?><?php if (!empty($r['admin_id'])) echo ' <span class="text-muted">#' . (int)$r['admin_id'] . '</span>'; ?></td>
                            <td><code><?php echo htmlspecialchars($r['action']); ?></code></td>
                            <td class="small"><?php echo htmlspecialchars($r['admin_ip'] ?? '—'); ?></td>
                            <td class="meta-json" title="<?php echo htmlspecialchars($r['meta_json'] ?? ''); ?>"><?php echo htmlspecialchars(mb_substr($r['meta_json'] ?? '', 0, 80)); ?><?php echo mb_strlen($r['meta_json'] ?? '') > 80 ? '…' : ''; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-muted text-center py-4">No logs found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center">
            <?php
            $prevParams = array_filter(['action' => $actionFilter ?: null, 'page' => $page > 1 ? $page - 1 : null]);
            $nextParams = array_filter(['action' => $actionFilter ?: null, 'page' => $page < $totalPages ? $page + 1 : null]);
            ?>
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="/admin/logs.php<?php echo $page > 1 ? '?' . http_build_query($prevParams) : '#'; ?>">Prev</a>
            </li>
            <li class="page-item disabled"><span class="page-link"><?php echo $page; ?> / <?php echo $totalPages; ?></span></li>
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="/admin/logs.php<?php echo $page < $totalPages ? '?' . http_build_query($nextParams) : '#'; ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
