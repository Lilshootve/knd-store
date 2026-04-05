<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';

$dateFrom = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-01');
$dateTo   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');
$typeFilter = in_array($_GET['type'] ?? '', ['cash','credit','']) ? ($_GET['type'] ?? '') : '';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 50;
$offset   = ($page - 1) * $perPage;

// Construir WHERE
$where  = 's.business_id = ? AND DATE(s.created_at) BETWEEN ? AND ?';
$params = [$RETAIL_BIZ_ID, $dateFrom, $dateTo];
if ($typeFilter) {
    $where  .= ' AND s.type = ?';
    $params[] = $typeFilter;
}

// Total de registros
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM retail_sales s WHERE $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

// Ventas
$salesStmt = $pdo->prepare(
    "SELECT s.id, s.type, s.total_base, s.total_local, s.currency_used,
            s.exchange_rate_snapshot, s.invoice_number, s.created_at,
            c.name AS customer_name, c.document_id AS customer_doc
     FROM retail_sales s
     LEFT JOIN retail_customers c ON c.id = s.customer_id AND c.business_id = s.business_id
     WHERE $where
     ORDER BY s.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$salesStmt->execute($params);
$sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

// Totales del período
$totalsStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_base),0) AS sum_base,
            COUNT(*) AS cnt,
            COUNT(CASE WHEN type='cash'   THEN 1 END) AS cash_cnt,
            COUNT(CASE WHEN type='credit' THEN 1 END) AS credit_cnt
     FROM retail_sales s WHERE $where"
);
$totalsStmt->execute($params);
$totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);

$currency = $RETAIL_BIZ['base_currency'];

retail_header('Ventas', 'sales');
?>

<!-- Filtros -->
<form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px;">
  <div>
    <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px;">Desde</label>
    <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" style="background:var(--card);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px;">Hasta</label>
    <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" style="background:var(--card);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px;">Tipo</label>
    <select name="type" style="background:var(--card);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-size:13px;">
      <option value="">Todos</option>
      <option value="cash"   <?= $typeFilter==='cash'?'selected':'' ?>>Contado</option>
      <option value="credit" <?= $typeFilter==='credit'?'selected':'' ?>>Crédito</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Filtrar</button>
</form>

<!-- Stats del período -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat"><div class="stat-label">Total Período</div><div class="stat-value"><?= retail_fmt((float)$totals['sum_base'], $currency) ?></div></div>
  <div class="stat"><div class="stat-label">Transacciones</div><div class="stat-value"><?= (int)$totals['cnt'] ?></div></div>
  <div class="stat"><div class="stat-label">Contado</div><div class="stat-value" style="color:var(--green)"><?= (int)$totals['cash_cnt'] ?></div></div>
  <div class="stat"><div class="stat-label">Crédito</div><div class="stat-value" style="color:var(--yellow)"><?= (int)$totals['credit_cnt'] ?></div></div>
</div>

<div class="card">
  <div class="card-title">Historial de Ventas (<?= $total ?> registros)</div>
  <table>
    <thead>
      <tr><th>Factura</th><th>Tipo</th><th>Cliente</th><th>Total Base</th><th>Total Local</th><th>Moneda</th><th>Fecha</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($sales as $s): ?>
      <tr>
        <td style="font-family:monospace;font-size:11px;"><?= htmlspecialchars($s['invoice_number'] ?? '—') ?></td>
        <td><span class="badge badge-<?= $s['type']==='cash'?'green':'yellow' ?>"><?= strtoupper($s['type']) ?></span></td>
        <td>
          <?= htmlspecialchars($s['customer_name'] ?? 'Anónimo') ?>
          <?php if ($s['customer_doc']): ?><br><span style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($s['customer_doc']) ?></span><?php endif; ?>
        </td>
        <td><?= retail_fmt((float)$s['total_base'], $currency) ?></td>
        <td><?= number_format((float)$s['total_local'], 2) ?></td>
        <td><span class="badge badge-blue"><?= htmlspecialchars($s['currency_used']) ?></span></td>
        <td style="font-size:12px;color:var(--muted)"><?= $s['created_at'] ?></td>
        <td>
          <?php if ($s['invoice_number']): ?>
          <a href="/storage/retail-invoices/<?= $RETAIL_BIZ_ID ?>/<?= urlencode($s['invoice_number']) ?>.html" target="_blank" class="btn btn-ghost" style="padding:4px 10px;font-size:11px;">🧾 Ver</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($sales)): ?>
      <tr><td colspan="8" style="color:var(--muted);text-align:center;padding:24px;">Sin ventas en el período seleccionado.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- Paginación -->
  <?php if ($pages > 1): ?>
  <div style="display:flex;gap:8px;margin-top:16px;align-items:center;">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?from=<?= $dateFrom ?>&to=<?= $dateTo ?>&type=<?= $typeFilter ?>&page=<?= $p ?>"
         style="padding:5px 10px;border-radius:5px;font-size:12px;text-decoration:none;
                background:<?= $p===$page?'var(--accent)':'var(--card)' ?>;
                color:<?= $p===$page?'#0d1117':'var(--muted)' ?>;
                border:1px solid var(--border);"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php retail_footer(); ?>
