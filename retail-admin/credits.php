<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';

$search  = trim($_GET['q'] ?? '');
$custId  = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null;

// Clientes con crédito
$cWhere  = 'cr.business_id = ? AND cr.balance > 0';
$cParams = [$RETAIL_BIZ_ID];
if ($search) {
    $cWhere  .= ' AND (c.name LIKE ? OR c.document_id LIKE ?)';
    $cParams[] = "%$search%";
    $cParams[] = "%$search%";
}

$credStmt = $pdo->prepare(
    "SELECT cr.id, cr.customer_id, cr.balance, cr.updated_at, c.name, c.document_id
     FROM retail_credits cr
     INNER JOIN retail_customers c ON c.id = cr.customer_id
     WHERE $cWhere
     ORDER BY cr.balance DESC, c.name ASC
     LIMIT 100"
);
$credStmt->execute($cParams);
$credits = $credStmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas globales
$totDebt = $pdo->prepare('SELECT COALESCE(SUM(balance),0), COUNT(*) FROM retail_credits WHERE business_id = ? AND balance > 0');
$totDebt->execute([$RETAIL_BIZ_ID]);
[$totalDebt, $debtorCount] = $totDebt->fetch(PDO::FETCH_NUM);

// Detalle de transacciones si se selecciona un cliente
$transactions = [];
$selectedCustomer = null;
if ($custId) {
    $custStmt = $pdo->prepare('SELECT * FROM retail_customers WHERE id = ? AND business_id = ? LIMIT 1');
    $custStmt->execute([$custId, $RETAIL_BIZ_ID]);
    $selectedCustomer = $custStmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedCustomer) {
        $txStmt = $pdo->prepare(
            'SELECT ct.*, s.invoice_number
             FROM retail_credit_transactions ct
             INNER JOIN retail_credits cr ON cr.id = ct.credit_id
             LEFT JOIN retail_sales s ON s.id = ct.reference_sale_id
             WHERE cr.business_id = ? AND cr.customer_id = ?
             ORDER BY ct.created_at DESC LIMIT 50'
        );
        $txStmt->execute([$RETAIL_BIZ_ID, $custId]);
        $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$currency = $RETAIL_BIZ['base_currency'];

retail_header('Créditos de Clientes', 'credits');
?>

<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
  <div class="stat"><div class="stat-label">Deuda Total</div><div class="stat-value" style="color:var(--yellow)"><?= retail_fmt((float)$totalDebt, $currency) ?></div></div>
  <div class="stat"><div class="stat-label">Clientes con Deuda</div><div class="stat-value"><?= (int)$debtorCount ?></div></div>
  <div class="stat"><div class="stat-label">Deuda Promedio</div><div class="stat-value"><?= $debtorCount > 0 ? retail_fmt((float)$totalDebt / (int)$debtorCount, $currency) : '0.00 '.$currency ?></div></div>
</div>

<div style="display:grid;grid-template-columns:<?= $custId ? '1fr 1fr' : '1fr' ?>;gap:20px;">

  <div class="card">
    <div class="card-title">Clientes con Saldo Deudor</div>
    <form method="get" style="margin-bottom:14px;">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por nombre o documento..."
             style="width:100%;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-size:13px;">
    </form>
    <table>
      <thead><tr><th>Cliente</th><th>Doc.</th><th>Saldo</th><th>Actualizado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($credits as $cr): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($cr['name']) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($cr['document_id'] ?? '—') ?></td>
          <td style="color:var(--yellow);font-weight:700"><?= retail_fmt((float)$cr['balance'], $currency) ?></td>
          <td style="font-size:11px;color:var(--muted)"><?= $cr['updated_at'] ?></td>
          <td><a href="?customer_id=<?= $cr['customer_id'] ?>" class="btn btn-ghost" style="padding:4px 8px;font-size:11px;">Ver</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($credits)): ?>
        <tr><td colspan="5" style="color:var(--muted);text-align:center;padding:20px;">Sin clientes con deuda pendiente. ✅</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($custId && $selectedCustomer): ?>
  <div class="card">
    <div class="card-title">Historial — <?= htmlspecialchars($selectedCustomer['name']) ?></div>
    <table>
      <thead><tr><th>Tipo</th><th>Monto</th><th>Factura</th><th>Fecha</th></tr></thead>
      <tbody>
      <?php foreach ($transactions as $tx): ?>
        <tr>
          <td><span class="badge badge-<?= $tx['type']==='payment'?'green':'yellow' ?>"><?= strtoupper($tx['type']) ?></span></td>
          <td style="font-weight:700;color:<?= $tx['type']==='payment'?'var(--green)':'var(--yellow)' ?>">
            <?= ($tx['type']==='debit'?'+':'−') . retail_fmt((float)$tx['amount'], $currency) ?>
          </td>
          <td style="font-family:monospace;font-size:11px"><?= htmlspecialchars($tx['invoice_number'] ?? '—') ?></td>
          <td style="font-size:11px;color:var(--muted)"><?= $tx['created_at'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($transactions)): ?>
        <tr><td colspan="4" style="color:var(--muted);text-align:center;padding:20px;">Sin movimientos.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<?php retail_footer(); ?>
