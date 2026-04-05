<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';
require_once KND_ROOT . '/includes/knd_agent_bridge.php';

$search = trim($_GET['q'] ?? '');
$filter = in_array($_GET['filter'] ?? '', ['all', 'low', 'out'], true) ? ($_GET['filter'] ?? 'all') : 'all';

$where  = 'business_id = ? AND active = 1';
$params = [$RETAIL_BIZ_ID];

if ($search !== '') {
    $where .= ' AND (name LIKE ? OR sku LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($filter === 'low') {
    $where .= ' AND stock <= min_stock AND stock > 0';
} elseif ($filter === 'out') {
    $where .= ' AND stock = 0';
}

$products = knd_retail_admin_db_rows(
    "SELECT id, sku, name, price_base, stock, min_stock FROM retail_products WHERE $where ORDER BY name ASC",
    $params
);

$statsRows = knd_retail_admin_db_rows(
    'SELECT
       COUNT(*) AS total,
       SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) AS out_of_stock,
       SUM(CASE WHEN stock > 0 AND stock <= min_stock THEN 1 ELSE 0 END) AS low_stock,
       SUM(CASE WHEN stock > min_stock THEN 1 ELSE 0 END) AS ok
     FROM retail_products WHERE business_id = ? AND active = 1',
    [$RETAIL_BIZ_ID]
);
$stats = $statsRows[0] ?? ['total' => 0, 'out_of_stock' => 0, 'low_stock' => 0, 'ok' => 0];

$currency = $RETAIL_BIZ['base_currency'];

retail_header('Inventario', 'inventory');
?>

<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat"><div class="stat-label">Total Productos</div><div class="stat-value"><?= (int)($stats['total'] ?? 0) ?></div></div>
  <div class="stat"><div class="stat-label">OK</div><div class="stat-value" style="color:var(--green)"><?= (int)($stats['ok'] ?? 0) ?></div></div>
  <div class="stat"><div class="stat-label">Stock Bajo</div><div class="stat-value" style="color:var(--yellow)"><?= (int)($stats['low_stock'] ?? 0) ?></div></div>
  <div class="stat"><div class="stat-label">Agotados</div><div class="stat-value" style="color:var(--red)"><?= (int)($stats['out_of_stock'] ?? 0) ?></div></div>
</div>

<!-- Filtros -->
<form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
  <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar producto o SKU..."
         style="background:var(--card);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:6px;font-size:13px;width:250px;">
  <select name="filter" style="background:var(--card);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-size:13px;">
    <option value="all"  <?= $filter === 'all' ? 'selected' : '' ?>>Todos</option>
    <option value="low"  <?= $filter === 'low' ? 'selected' : '' ?>>Stock bajo</option>
    <option value="out"  <?= $filter === 'out' ? 'selected' : '' ?>>Agotados</option>
  </select>
  <button type="submit" class="btn btn-primary">Filtrar</button>
</form>

<div class="card">
  <div class="card-title"><?= count($products) ?> producto(s)</div>
  <table>
    <thead>
      <tr><th>SKU</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Mín.</th><th>Estado</th><th>Ajuste</th></tr>
    </thead>
    <tbody>
    <?php foreach ($products as $p):
        $stock = (int) $p['stock'];
        $min   = (int) $p['min_stock'];
        if ($stock === 0) {
            $badge = '<span class="badge badge-red">AGOTADO</span>';
        } elseif ($stock <= $min) {
            $badge = '<span class="badge badge-yellow">BAJO</span>';
        } else {
            $badge = '<span class="badge badge-green">OK</span>';
        }
        $pid = (int) $p['id'];
    ?>
      <tr>
        <td style="font-family:monospace;font-size:11px;color:var(--muted)"><?= htmlspecialchars($p['sku'] ?? '—') ?></td>
        <td style="font-weight:500"><?= htmlspecialchars((string) $p['name']) ?></td>
        <td><?= retail_fmt((float) $p['price_base'], $currency) ?></td>
        <td style="font-weight:700;color:<?= $stock === 0 ? 'var(--red)' : ($stock <= $min ? 'var(--yellow)' : 'var(--green)') ?>"><?= $stock ?></td>
        <td style="color:var(--muted)"><?= $min ?></td>
        <td><?= $badge ?></td>
        <td>
          <form class="knd-adjust-stock-form" data-product-id="<?= $pid ?>" style="display:flex;gap:6px;align-items:center;" onsubmit="return kndSubmitAdjustStock(event, <?= $pid ?>)">
            <input type="number" name="delta" placeholder="±" style="width:60px;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:4px 6px;border-radius:4px;font-size:12px;">
            <input type="text" name="reason" placeholder="Motivo" style="width:120px;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:4px 6px;border-radius:4px;font-size:12px;">
            <button type="submit" class="btn btn-ghost" style="padding:4px 10px;font-size:11px;">Ajustar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($products)): ?>
      <tr><td colspan="7" style="color:var(--muted);text-align:center;padding:24px;">Sin productos que coincidan con el filtro.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
async function kndSubmitAdjustStock(ev, productId) {
  ev.preventDefault();
  const form = ev.target;
  const delta = parseInt(form.delta.value, 10);
  const reason = (form.reason.value || '').trim();
  if (!Number.isFinite(delta) || delta === 0) {
    alert('Indique un ajuste distinto de cero.');
    return false;
  }
  const input = { product_id: productId, delta: delta, reason: reason };
  try {
    let r = await kndRetailAdminAgent({ tool: 'adjust_stock', input: input });
    if (r.status === 'blocked' && r.error && String(r.error).indexOf('REQUIRES_CONFIRMATION') !== -1) {
      const hint = r.message || r.error || 'Esta operación requiere confirmación.';
      if (!confirm(hint + '\n\n¿Ejecutar ahora?')) return false;
      if (!r.confirm_id) {
        alert('No se recibió confirm_id. Intente de nuevo.');
        return false;
      }
      r = await kndRetailAdminAgent({ tool: 'adjust_stock', input: input, confirm_id: r.confirm_id });
    }
    if (r.status !== 'success') {
      alert(r.error || 'No se pudo completar el ajuste.');
      return false;
    }
    location.reload();
  } catch (e) {
    alert(e.message || String(e));
  }
  return false;
}
</script>

<?php retail_footer(); ?>
