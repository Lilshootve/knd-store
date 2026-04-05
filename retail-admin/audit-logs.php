<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';

$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 50;
$offset    = ($page - 1) * $perPage;
$action    = trim($_GET['action'] ?? '');
$entity    = trim($_GET['entity'] ?? '');
$userId    = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
$dateFrom  = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-01');
$dateTo    = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');

$where  = 'business_id = ? AND DATE(created_at) BETWEEN ? AND ?';
$params = [$RETAIL_BIZ_ID, $dateFrom, $dateTo];

if ($action) {
    $where .= ' AND action LIKE ?';
    $params[] = "%$action%";
}
if ($entity) {
    $where .= ' AND entity_type = ?';
    $params[] = $entity;
}
if ($userId) {
    $where .= ' AND user_id = ?';
    $params[] = $userId;
}

$total = (int) $pdo->prepare("SELECT COUNT(*) FROM retail_audit_logs WHERE $where")->execute($params) ?
    $pdo->prepare("SELECT COUNT(*) FROM retail_audit_logs WHERE $where")->execute($params) : 0;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM retail_audit_logs WHERE $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$logStmt = $pdo->prepare(
    "SELECT al.*, u.username
     FROM retail_audit_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE $where
     ORDER BY al.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$logStmt->execute($params);
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// Acciones únicas para el filtro
$actionsStmt = $pdo->prepare(
    'SELECT DISTINCT action FROM retail_audit_logs WHERE business_id = ? ORDER BY action ASC LIMIT 50'
);
$actionsStmt->execute([$RETAIL_BIZ_ID]);
$actionsList = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

$actionBadgeColor = [
    'create_sale'             => 'green',
    'create_credit_sale'      => 'yellow',
    'register_credit_payment' => 'blue',
    'adjust_stock'            => 'purple',
    'update_exchange_rate'    => 'red',
    'create_customer'         => 'green',
];

retail_header('Audit Logs', 'audit-logs');
?>

<!-- Filtros -->
<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:20px;">
  <div>
    <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Desde</label>
    <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" style="background:var(--card);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Hasta</label>
    <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" style="background:var(--card);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Acción</label>
    <select name="action" style="background:var(--card);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:13px;">
      <option value="">Todas</option>
      <?php foreach ($actionsList as $a): ?>
        <option value="<?= htmlspecialchars($a) ?>" <?= $action===$a?'selected':'' ?>><?= htmlspecialchars($a) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Entidad</label>
    <select name="entity" style="background:var(--card);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:13px;">
      <option value="">Todas</option>
      <?php foreach (['sale','product','credit','rate','customer','gateway_call'] as $e): ?>
        <option value="<?= $e ?>" <?= $entity===$e?'selected':'' ?>><?= $e ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Filtrar</button>
</form>

<div class="card">
  <div class="card-title">Registros de Auditoría (<?= $total ?> total)</div>
  <table>
    <thead><tr><th>Timestamp</th><th>Acción</th><th>Entidad</th><th>ID</th><th>Usuario</th><th>IP</th><th>Detalle</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $log):
        $color = $actionBadgeColor[$log['action']] ?? 'blue';
    ?>
      <tr>
        <td style="font-size:11px;font-family:monospace;color:var(--muted);white-space:nowrap"><?= $log['created_at'] ?></td>
        <td><span class="badge badge-<?= $color ?>"><?= htmlspecialchars($log['action']) ?></span></td>
        <td style="font-size:12px"><?= htmlspecialchars($log['entity_type'] ?? '—') ?></td>
        <td style="font-family:monospace;font-size:12px"><?= $log['entity_id'] ? '#'.$log['entity_id'] : '—' ?></td>
        <td style="font-size:12px"><?= htmlspecialchars($log['username'] ?? 'system') ?></td>
        <td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
        <td>
          <?php if ($log['before_json'] || $log['after_json']): ?>
          <button onclick="showDiff(this)" data-before="<?= htmlspecialchars($log['before_json'] ?? 'null') ?>"
                  data-after="<?= htmlspecialchars($log['after_json'] ?? 'null') ?>"
                  class="btn btn-ghost" style="padding:3px 8px;font-size:11px;">Ver diff</button>
          <?php else: ?>
          <span style="color:var(--muted);font-size:11px">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($logs)): ?>
      <tr><td colspan="7" style="color:var(--muted);text-align:center;padding:24px;">Sin registros para los filtros seleccionados.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- Paginación -->
  <?php if ($pages > 1): ?>
  <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap;">
    <?php for ($p = 1; $p <= min($pages, 20); $p++): ?>
      <a href="?from=<?= $dateFrom ?>&to=<?= $dateTo ?>&action=<?= urlencode($action) ?>&entity=<?= urlencode($entity) ?>&page=<?= $p ?>"
         style="padding:4px 9px;border-radius:4px;font-size:12px;text-decoration:none;
                background:<?= $p===$page?'var(--accent)':'var(--card)' ?>;
                color:<?= $p===$page?'#0d1117':'var(--muted)' ?>;border:1px solid var(--border);"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Modal diff -->
<div id="diff-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;width:700px;max-width:95%;max-height:80vh;overflow:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <strong>Cambios registrados</strong>
      <button onclick="document.getElementById('diff-modal').style.display='none'" class="btn btn-ghost" style="padding:4px 10px;">✕</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:6px;">ANTES</div>
        <pre id="diff-before" style="background:var(--bg);padding:12px;border-radius:6px;font-size:12px;overflow:auto;color:var(--red);border:1px solid var(--border)"></pre>
      </div>
      <div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:6px;">DESPUÉS</div>
        <pre id="diff-after" style="background:var(--bg);padding:12px;border-radius:6px;font-size:12px;overflow:auto;color:var(--green);border:1px solid var(--border)"></pre>
      </div>
    </div>
  </div>
</div>
<script>
function showDiff(btn) {
  try {
    document.getElementById('diff-before').textContent = JSON.stringify(JSON.parse(btn.dataset.before), null, 2);
    document.getElementById('diff-after').textContent  = JSON.stringify(JSON.parse(btn.dataset.after),  null, 2);
  } catch(e) {
    document.getElementById('diff-before').textContent = btn.dataset.before;
    document.getElementById('diff-after').textContent  = btn.dataset.after;
  }
  const modal = document.getElementById('diff-modal');
  modal.style.display = 'flex';
}
document.getElementById('diff-modal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>

<?php retail_footer(); ?>
