<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';
require_once KND_ROOT . '/retail/tools/update_exchange_rate.php';
require_once KND_ROOT . '/includes/csrf.php';

$message = null;
$msgType = 'success';

// Procesar actualización de tasa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();

    $currency = strtoupper(trim($_POST['currency'] ?? ''));
    $rate     = (float) ($_POST['rate'] ?? 0);
    $confirm  = !empty($_POST['confirmed']);

    if ($confirm && $currency && $rate > 0) {
        $result = retail_update_exchange_rate($pdo, ['currency' => $currency, 'rate' => $rate]);
        if (isset($result['error'])) {
            $message = 'Error: ' . $result['error'];
            $msgType = 'danger';
        } else {
            $message = "Tasa $currency actualizada a " . number_format($rate, 4) . ".";
        }
    }
}

// Tasas vigentes (última por moneda)
$ratesStmt = $pdo->prepare(
    'SELECT r1.currency, r1.rate_to_base, r1.created_at
     FROM retail_exchange_rates r1
     INNER JOIN (
       SELECT currency, MAX(created_at) AS max_at
       FROM retail_exchange_rates WHERE business_id = ? GROUP BY currency
     ) r2 ON r1.currency = r2.currency AND r1.created_at = r2.max_at
     WHERE r1.business_id = ?
     ORDER BY r1.currency ASC'
);
$ratesStmt->execute([$RETAIL_BIZ_ID, $RETAIL_BIZ_ID]);
$current = $ratesStmt->fetchAll(PDO::FETCH_ASSOC);

// Historial
$histStmt = $pdo->prepare(
    'SELECT currency, rate_to_base, created_at
     FROM retail_exchange_rates
     WHERE business_id = ?
     ORDER BY created_at DESC LIMIT 50'
);
$histStmt->execute([$RETAIL_BIZ_ID]);
$history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = generateCSRFToken();

retail_header('Tasas de Cambio', 'exchange-rates');
?>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?>" style="margin-bottom:20px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;">

  <!-- Formulario actualización -->
  <div class="card">
    <div class="card-title">Actualizar Tasa</div>
    <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">
      Moneda base: <strong><?= htmlspecialchars($RETAIL_BIZ['base_currency']) ?></strong><br>
      Las tasas son append-only. El histórico se mantiene completo.
    </p>
    <form method="post" onsubmit="return confirmRate(this)">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="confirmed" value="1">
      <div style="margin-bottom:14px;">
        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:5px;">Moneda (ej: VES, COP, EUR)</label>
        <input type="text" name="currency" maxlength="10" required placeholder="VES"
               style="width:100%;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:8px 10px;border-radius:6px;font-size:13px;text-transform:uppercase;">
      </div>
      <div style="margin-bottom:18px;">
        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:5px;">Tasa (cuántas unidades = 1 <?= htmlspecialchars($RETAIL_BIZ['base_currency']) ?>)</label>
        <input type="number" name="rate" step="0.000001" min="0.000001" required placeholder="36.50"
               style="width:100%;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:8px 10px;border-radius:6px;font-size:13px;">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Actualizar Tasa</button>
    </form>
    <script>
    function confirmRate(form) {
      const curr = form.currency.value.toUpperCase();
      const rate = form.rate.value;
      return confirm('¿Confirmar actualización de tasa ' + curr + ' a ' + rate + '?\nEsta acción quedará en el audit log.');
    }
    </script>
  </div>

  <!-- Tasas vigentes -->
  <div>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-title">Tasas Vigentes</div>
      <table>
        <thead><tr><th>Moneda</th><th>Tasa actual</th><th>Actualizada</th></tr></thead>
        <tbody>
        <?php foreach ($current as $r): ?>
          <tr>
            <td><span class="badge badge-blue"><?= htmlspecialchars($r['currency']) ?></span></td>
            <td style="font-weight:700;font-family:monospace"><?= number_format((float)$r['rate_to_base'], 6) ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= $r['created_at'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($current)): ?>
          <tr><td colspan="3" style="color:var(--muted);text-align:center;padding:20px;">Sin tasas registradas.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-title">Historial de Tasas</div>
      <table>
        <thead><tr><th>Moneda</th><th>Tasa</th><th>Registrada</th></tr></thead>
        <tbody>
        <?php foreach ($history as $r): ?>
          <tr>
            <td><span class="badge badge-purple"><?= htmlspecialchars($r['currency']) ?></span></td>
            <td style="font-family:monospace;font-size:12px"><?= number_format((float)$r['rate_to_base'], 6) ?></td>
            <td style="font-size:11px;color:var(--muted)"><?= $r['created_at'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php retail_footer(); ?>
