<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';
require_once KND_ROOT . '/includes/knd_agent_bridge.php';

$current = knd_retail_admin_db_rows(
    'SELECT r1.currency, r1.rate_to_base, r1.created_at
     FROM retail_exchange_rates r1
     INNER JOIN (
       SELECT currency, MAX(created_at) AS max_at
       FROM retail_exchange_rates WHERE business_id = ? GROUP BY currency
     ) r2 ON r1.currency = r2.currency AND r1.created_at = r2.max_at
     WHERE r1.business_id = ?
     ORDER BY r1.currency ASC',
    [$RETAIL_BIZ_ID, $RETAIL_BIZ_ID]
);

$history = knd_retail_admin_db_rows(
    'SELECT currency, rate_to_base, created_at
     FROM retail_exchange_rates
     WHERE business_id = ?
     ORDER BY created_at DESC LIMIT 50',
    [$RETAIL_BIZ_ID]
);

$csrfToken = generateCSRFToken();

retail_header('Tasas de Cambio', 'exchange-rates');
?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;">

  <!-- Formulario actualización -->
  <div class="card">
    <div class="card-title">Actualizar Tasa</div>
    <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">
      Moneda base: <strong><?= htmlspecialchars($RETAIL_BIZ['base_currency']) ?></strong><br>
      Las tasas son append-only. El histórico se mantiene completo.<br>
      La operación pasa por <code>execute.php</code> (confirmación en dos pasos).
    </p>
    <form id="knd-rate-form">
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
            <td><span class="badge badge-blue"><?= htmlspecialchars((string) $r['currency']) ?></span></td>
            <td style="font-weight:700;font-family:monospace"><?= number_format((float) $r['rate_to_base'], 6) ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars((string) $r['created_at']) ?></td>
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
            <td><span class="badge badge-purple"><?= htmlspecialchars((string) $r['currency']) ?></span></td>
            <td style="font-family:monospace;font-size:12px"><?= number_format((float) $r['rate_to_base'], 6) ?></td>
            <td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars((string) $r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
document.getElementById('knd-rate-form').addEventListener('submit', async function(ev) {
  ev.preventDefault();
  const f = ev.target;
  const currency = (f.currency.value || '').trim().toUpperCase();
  const rate = parseFloat(f.rate.value);
  if (!currency || !(rate > 0)) {
    alert('Moneda y tasa válidas requeridas.');
    return;
  }
  if (!confirm('¿Confirmar actualización de tasa ' + currency + ' a ' + rate + '?\nEsta acción quedará en el audit log.')) return;
  const input = { currency: currency, rate: rate };
  try {
    let r = await kndRetailAdminAgent({ tool: 'update_exchange_rate', input: input });
    if (r.status === 'blocked' && r.error && String(r.error).indexOf('REQUIRES_CONFIRMATION') !== -1) {
      const hint = r.message || r.error || 'Confirmar actualización de tasa.';
      if (!confirm(hint + '\n\n¿Ejecutar ahora?')) return;
      if (!r.confirm_id) {
        alert('No se recibió confirm_id.');
        return;
      }
      r = await kndRetailAdminAgent({ tool: 'update_exchange_rate', input: input, confirm_id: r.confirm_id });
    }
    if (r.status !== 'success') {
      alert(r.error || 'Error al actualizar tasa.');
      return;
    }
    location.reload();
  } catch (e) {
    alert(e.message || String(e));
  }
});
</script>

<?php retail_footer(); ?>
