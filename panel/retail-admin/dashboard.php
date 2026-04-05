<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';
require_once KND_ROOT . '/includes/knd_agent_bridge.php';

$today = date('Y-m-d');

$todayRows = knd_retail_admin_db_rows(
    'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_base),0) AS total
     FROM retail_sales WHERE business_id = ? AND DATE(created_at) = ?',
    [$RETAIL_BIZ_ID, $today]
);
$todayData = $todayRows[0] ?? ['cnt' => 0, 'total' => 0];

$weekRows = knd_retail_admin_db_rows(
    'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_base),0) AS total
     FROM retail_sales WHERE business_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
    [$RETAIL_BIZ_ID]
);
$weekData = $weekRows[0] ?? ['cnt' => 0, 'total' => 0];

$lowRows = knd_retail_admin_db_rows(
    'SELECT COUNT(*) AS c FROM retail_products WHERE business_id = ? AND active = 1 AND stock <= min_stock',
    [$RETAIL_BIZ_ID]
);
$lowCount = (int) ($lowRows[0]['c'] ?? 0);

$debtorRows = knd_retail_admin_db_rows(
    'SELECT COUNT(*) AS c FROM retail_credits WHERE business_id = ? AND balance > 0',
    [$RETAIL_BIZ_ID]
);
$debtorCount = (int) ($debtorRows[0]['c'] ?? 0);

$totalDebtRows = knd_retail_admin_db_rows(
    'SELECT COALESCE(SUM(balance),0) AS t FROM retail_credits WHERE business_id = ?',
    [$RETAIL_BIZ_ID]
);
$debtAmount = (float) ($totalDebtRows[0]['t'] ?? 0);

$recent = knd_retail_admin_db_rows(
    'SELECT s.id, s.type, s.total_base, s.currency_used, s.invoice_number, s.created_at,
            c.name AS customer_name
     FROM retail_sales s
     LEFT JOIN retail_customers c ON c.id = s.customer_id AND c.business_id = s.business_id
     WHERE s.business_id = ?
     ORDER BY s.created_at DESC LIMIT 10',
    [$RETAIL_BIZ_ID]
);

$rateRows = knd_retail_admin_db_rows(
    'SELECT r1.currency, r1.rate_to_base, r1.created_at
     FROM retail_exchange_rates r1
     INNER JOIN (
       SELECT currency, MAX(created_at) AS max_at FROM retail_exchange_rates WHERE business_id = ? GROUP BY currency
     ) r2 ON r1.currency = r2.currency AND r1.created_at = r2.max_at
     WHERE r1.business_id = ?
     ORDER BY r1.currency ASC',
    [$RETAIL_BIZ_ID, $RETAIL_BIZ_ID]
);

$currency = $RETAIL_BIZ['base_currency'];

retail_header('Dashboard', 'dashboard');
?>

<?php if ($lowCount > 0): ?>
<div class="alert alert-warning">⚠️ <strong><?= $lowCount ?> producto(s)</strong> con stock bajo o agotado. <a href="inventory.php" style="color:inherit;text-decoration:underline">Ver inventario</a></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat">
    <div class="stat-label">Ventas Hoy</div>
    <div class="stat-value"><?= retail_fmt((float) $todayData['total'], $currency) ?></div>
    <div class="stat-sub"><?= (int) $todayData['cnt'] ?> transacciones</div>
  </div>
  <div class="stat">
    <div class="stat-label">Ventas Esta Semana</div>
    <div class="stat-value"><?= retail_fmt((float) $weekData['total'], $currency) ?></div>
    <div class="stat-sub"><?= (int) $weekData['cnt'] ?> transacciones</div>
  </div>
  <div class="stat">
    <div class="stat-label">Productos Bajo Stock</div>
    <div class="stat-value" style="color:<?= $lowCount > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= $lowCount ?></div>
    <div class="stat-sub"><a href="inventory.php" style="color:var(--accent)">Ver inventario</a></div>
  </div>
  <div class="stat">
    <div class="stat-label">Deuda Total Clientes</div>
    <div class="stat-value" style="color:<?= $debtAmount > 0 ? 'var(--yellow)' : 'var(--green)' ?>"><?= retail_fmt($debtAmount, $currency) ?></div>
    <div class="stat-sub"><?= $debtorCount ?> cliente(s) con saldo</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;">

  <div class="card">
    <div class="card-title">Últimas Ventas</div>
    <table>
      <thead><tr><th>Factura</th><th>Tipo</th><th>Cliente</th><th>Total</th><th>Fecha</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $s): ?>
        <tr>
          <td style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($s['invoice_number'] ?? '—') ?></td>
          <td><span class="badge badge-<?= ($s['type'] ?? '') === 'cash' ? 'green' : 'yellow' ?>"><?= strtoupper(htmlspecialchars((string) ($s['type'] ?? ''))) ?></span></td>
          <td><?= htmlspecialchars($s['customer_name'] ?? 'Anónimo') ?></td>
          <td><?= retail_fmt((float) $s['total_base'], (string) ($s['currency_used'] ?? $currency)) ?></td>
          <td style="color:var(--muted);font-size:12px;"><?= htmlspecialchars((string) ($s['created_at'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?>
        <tr><td colspan="5" style="color:var(--muted);text-align:center;padding:20px;">Sin ventas registradas aún.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div>
    <div class="card">
      <div class="card-title">Tasas de Cambio Vigentes</div>
      <table>
        <thead><tr><th>Moneda</th><th>Tasa</th></tr></thead>
        <tbody>
        <?php foreach ($rateRows as $r): ?>
          <tr>
            <td><span class="badge badge-blue"><?= htmlspecialchars((string) $r['currency']) ?></span></td>
            <td><?= number_format((float) $r['rate_to_base'], 4) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rateRows)): ?>
          <tr><td colspan="2" style="color:var(--muted);text-align:center;padding:16px;">Sin tasas registradas.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      <div style="margin-top:12px;">
        <a href="exchange-rates.php" class="btn btn-ghost" style="width:100%;justify-content:center;">Gestionar tasas</a>
      </div>
    </div>
  </div>

</div>

<?php retail_footer(); ?>
