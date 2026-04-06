<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';
require_once KND_ROOT . '/includes/knd_agent_bridge.php';

$search = trim($_GET['q'] ?? '');
$custId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null;

$cWhere  = 'cr.business_id = ? AND cr.balance > 0';
$cParams = [$RETAIL_BIZ_ID];
if ($search !== '') {
    $cWhere .= ' AND (c.name LIKE ? OR c.document_id LIKE ?)';
    $cParams[] = '%' . $search . '%';
    $cParams[] = '%' . $search . '%';
}

$credits = knd_retail_admin_db_rows(
    "SELECT cr.id, cr.customer_id, cr.balance, cr.updated_at, c.name, c.document_id
     FROM retail_credits cr
     INNER JOIN retail_customers c ON c.id = cr.customer_id
     WHERE $cWhere
     ORDER BY cr.balance DESC, c.name ASC
     LIMIT 100",
    $cParams
);

$totRows = knd_retail_admin_db_rows(
    'SELECT COALESCE(SUM(balance),0) AS t, COUNT(*) AS c FROM retail_credits WHERE business_id = ? AND balance > 0',
    [$RETAIL_BIZ_ID]
);
$totalDebt   = (float) ($totRows[0]['t'] ?? 0);
$debtorCount = (int) ($totRows[0]['c'] ?? 0);

$transactions = [];
$selectedCustomer = null;
$selectedCreditBalance = null;
if ($custId) {
    $custRows = knd_retail_admin_db_rows(
        'SELECT * FROM retail_customers WHERE id = ? AND business_id = ? LIMIT 1',
        [$custId, $RETAIL_BIZ_ID]
    );
    $selectedCustomer = $custRows[0] ?? null;

    if ($selectedCustomer) {
        $balRows = knd_retail_admin_db_rows(
            'SELECT balance FROM retail_credits WHERE business_id = ? AND customer_id = ? LIMIT 1',
            [$RETAIL_BIZ_ID, $custId]
        );
        $selectedCreditBalance = isset($balRows[0]['balance']) ? (float) $balRows[0]['balance'] : null;

        $transactions = knd_retail_admin_db_rows(
            'SELECT ct.*, s.invoice_number
             FROM retail_credit_transactions ct
             INNER JOIN retail_credits cr ON cr.id = ct.credit_id
             LEFT JOIN retail_sales s ON s.id = ct.reference_sale_id
             WHERE cr.business_id = ? AND cr.customer_id = ?
             ORDER BY ct.created_at DESC LIMIT 50',
            [$RETAIL_BIZ_ID, $custId]
        );
    }
}

$currency = $RETAIL_BIZ['base_currency'];

retail_header('Créditos de Clientes', 'credits');
?>

<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
  <div class="stat"><div class="stat-label">Deuda Total</div><div class="stat-value" style="color:var(--yellow)"><?= retail_fmt($totalDebt, $currency) ?></div></div>
  <div class="stat"><div class="stat-label">Clientes con Deuda</div><div class="stat-value"><?= $debtorCount ?></div></div>
  <div class="stat"><div class="stat-label">Deuda Promedio</div><div class="stat-value"><?= $debtorCount > 0 ? retail_fmt($totalDebt / $debtorCount, $currency) : '0.00 ' . htmlspecialchars($currency) ?></div></div>
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
          <td style="font-weight:500"><?= htmlspecialchars((string) $cr['name']) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($cr['document_id'] ?? '—') ?></td>
          <td style="color:var(--yellow);font-weight:700"><?= retail_fmt((float) $cr['balance'], $currency) ?></td>
          <td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars((string) ($cr['updated_at'] ?? '')) ?></td>
          <td><a href="?customer_id=<?= (int) $cr['customer_id'] ?>" class="btn btn-ghost" style="padding:4px 8px;font-size:11px;">Ver</a></td>
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
    <div class="card-title">Historial — <?= htmlspecialchars((string) $selectedCustomer['name']) ?></div>
    <?php
    $canPay = $selectedCreditBalance !== null && $selectedCreditBalance > 0;
    ?>
    <?php if ($canPay): ?>
    <p style="font-size:13px;color:var(--muted);margin-bottom:12px;">
      Saldo deudor: <strong style="color:var(--yellow)"><?= retail_fmt($selectedCreditBalance, $currency) ?></strong>
      · Los pagos se registran vía <code>api/agent/execute.php</code> (herramienta <code>register_credit_payment</code>).
    </p>
    <form class="knd-credit-pay-form" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:18px;padding:14px;background:rgba(255,255,255,.03);border-radius:8px;border:1px solid var(--border);" onsubmit="return kndSubmitCreditPayment(event, <?= (int) $custId ?>)">
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Monto pagado</label>
        <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00"
               style="width:120px;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:6px 8px;border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Moneda cobrada</label>
        <input type="text" name="currency" maxlength="10" value="<?= htmlspecialchars(strtoupper((string) ($RETAIL_BIZ['base_currency'] ?? 'USD'))) ?>"
               style="width:72px;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:6px 8px;border-radius:6px;font-size:13px;text-transform:uppercase;">
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:18px;">Registrar pago</button>
    </form>
    <?php elseif ($selectedCreditBalance !== null && $selectedCreditBalance <= 0): ?>
    <p style="font-size:13px;color:var(--green);margin-bottom:14px;">Sin saldo deudor pendiente.</p>
    <?php endif; ?>
    <table>
      <thead><tr><th>Tipo</th><th>Monto</th><th>Factura</th><th>Fecha</th></tr></thead>
      <tbody>
      <?php foreach ($transactions as $tx): ?>
        <tr>
          <td><span class="badge badge-<?= ($tx['type'] ?? '') === 'payment' ? 'green' : 'yellow' ?>"><?= strtoupper(htmlspecialchars((string) ($tx['type'] ?? ''))) ?></span></td>
          <td style="font-weight:700;color:<?= ($tx['type'] ?? '') === 'payment' ? 'var(--green)' : 'var(--yellow)' ?>">
            <?= (($tx['type'] ?? '') === 'debit' ? '+' : '−') . retail_fmt((float) $tx['amount'], $currency) ?>
          </td>
          <td style="font-family:monospace;font-size:11px"><?= htmlspecialchars($tx['invoice_number'] ?? '—') ?></td>
          <td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars((string) ($tx['created_at'] ?? '')) ?></td>
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

<script>
async function kndSubmitCreditPayment(ev, customerId) {
  ev.preventDefault();
  const form = ev.target;
  const amount = parseFloat(form.amount.value, 10);
  const currency = (form.currency.value || '').trim().toUpperCase();
  if (!Number.isFinite(amount) || amount <= 0) {
    alert('Indique un monto válido mayor que cero.');
    return false;
  }
  if (!currency || currency.length < 2) {
    alert('Indique una moneda válida (ej. USD, VES).');
    return false;
  }
  if (!confirm('¿Registrar este pago en el sistema?')) return false;
  const input = { customer_id: customerId, amount: amount, currency: currency };
  try {
    let r = await kndRetailAdminAgent({ tool: 'register_credit_payment', input: input });
    if (r.status === 'blocked' && r.error && String(r.error).indexOf('REQUIRES_CONFIRMATION') !== -1) {
      const hint = r.message || r.error || 'Esta operación requiere confirmación del servidor.';
      if (!confirm(hint + '\n\n¿Ejecutar ahora?')) return false;
      if (!r.confirm_id) {
        alert('No se recibió confirm_id. Intente de nuevo.');
        return false;
      }
      r = await kndRetailAdminAgent({ tool: 'register_credit_payment', input: input, confirm_id: r.confirm_id });
    }
    if (r.status !== 'success') {
      alert(r.error || 'No se pudo registrar el pago.');
      return false;
    }
    const msg = (r.data && r.data.message) ? r.data.message : 'Pago registrado.';
    alert(msg);
    location.reload();
  } catch (e) {
    alert(e.message || String(e));
  }
  return false;
}
</script>

<?php retail_footer(); ?>
