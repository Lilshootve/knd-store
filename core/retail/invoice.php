<?php
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../../config/bootstrap.php';
}

/**
 * KND Retail Module — Invoice Generator
 *
 * retail_generate_invoice(PDO $pdo, int $saleId): string
 * Retorna HTML de la factura. Guardado en storage/retail-invoices/.
 *
 * Número único: INV-{business_id}-{YYYY}-{seq_padded}
 * Formato: HTML imprimible (compatible print CSS).
 */

/**
 * Genera y guarda la factura HTML para una venta.
 * @return string Ruta relativa al archivo generado.
 */
function retail_generate_invoice(PDO $pdo, int $saleId): string
{
    $bizId = retail_business_id();

    // Obtener datos de la venta
    $saleStmt = $pdo->prepare(
        'SELECT s.*, c.name AS customer_name, c.document_id AS customer_doc,
                b.name AS business_name, b.base_currency
         FROM retail_sales s
         INNER JOIN businesses b ON b.id = s.business_id
         LEFT JOIN retail_customers c ON c.id = s.customer_id
         WHERE s.id = ? AND s.business_id = ?
         LIMIT 1'
    );
    $saleStmt->execute([$saleId, $bizId]);
    $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        return '';
    }

    // Obtener items
    $itemsStmt = $pdo->prepare(
        'SELECT si.qty, si.price_snapshot, p.name AS product_name, p.sku
         FROM retail_sale_items si
         INNER JOIN retail_products p ON p.id = si.product_id
         WHERE si.sale_id = ?
         ORDER BY si.id ASC'
    );
    $itemsStmt->execute([$saleId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $invoiceNumber = $sale['invoice_number'];
    $html          = _retail_invoice_html($sale, $items, $invoiceNumber);

    // Guardar en disco
    $dir  = defined('KND_ROOT') ? KND_ROOT : BASE_PATH;
    $dir .= "/storage/retail-invoices/{$bizId}";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = $dir . '/' . $invoiceNumber . '.html';
    file_put_contents($filename, $html);

    return "storage/retail-invoices/{$bizId}/{$invoiceNumber}.html";
}

/**
 * Construye el HTML de la factura.
 */
function _retail_invoice_html(array $sale, array $items, string $invoiceNumber): string
{
    $businessName = htmlspecialchars($sale['business_name']);
    $baseCurrency = htmlspecialchars($sale['base_currency']);
    $localCurrency = htmlspecialchars($sale['currency_used']);
    $rate          = (float) $sale['exchange_rate_snapshot'];
    $totalBase     = (float) $sale['total_base'];
    $totalLocal    = (float) $sale['total_local'];
    $saleType      = $sale['type'] === 'credit' ? 'CRÉDITO' : 'CONTADO';
    $createdAt     = $sale['created_at'];
    $invoiceNum    = htmlspecialchars($invoiceNumber);
    $customerName  = $sale['customer_name'] ? htmlspecialchars($sale['customer_name']) : 'Cliente Anónimo';
    $customerDoc   = $sale['customer_doc']  ? htmlspecialchars($sale['customer_doc'])  : '—';

    // Construir filas de items
    $itemRows = '';
    foreach ($items as $item) {
        $name       = htmlspecialchars($item['product_name']);
        $sku        = $item['sku'] ? htmlspecialchars($item['sku']) : '—';
        $qty        = (int) $item['qty'];
        $unitBase   = (float) $item['price_snapshot'];
        $unitLocal  = $unitBase * $rate;
        $subtBase   = $unitBase * $qty;
        $subtLocal  = $unitLocal * $qty;

        $itemRows .= "<tr>
            <td>$sku</td>
            <td>$name</td>
            <td class='right'>$qty</td>
            <td class='right'>" . number_format($unitBase, 2) . " $baseCurrency</td>
            <td class='right'>" . number_format($unitLocal, 2) . " $localCurrency</td>
            <td class='right'>" . number_format($subtBase, 2) . " $baseCurrency</td>
        </tr>\n";
    }

    $rateDisplay = $rate !== 1.0
        ? "1 $baseCurrency = " . number_format($rate, 4) . " $localCurrency"
        : "Sin conversión (misma moneda)";

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Factura $invoiceNum</title>
<style>
  @media print { body { margin: 0; } .no-print { display: none; } }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1a1a2e; background: #fff; margin: 20px; }
  .invoice-box { max-width: 760px; margin: auto; border: 1px solid #e0e0e0; padding: 30px; border-radius: 8px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #0f3460; padding-bottom: 16px; }
  .business-name { font-size: 22px; font-weight: 700; color: #0f3460; }
  .invoice-meta { text-align: right; }
  .invoice-meta .invoice-num { font-size: 16px; font-weight: 700; color: #e94560; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-top: 4px; }
  .badge-cash   { background: #d4edda; color: #155724; }
  .badge-credit { background: #fff3cd; color: #856404; }
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
  .info-block { background: #f8f9fa; padding: 12px; border-radius: 6px; }
  .info-block h4 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; color: #6c757d; letter-spacing: .5px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  th { background: #0f3460; color: #fff; padding: 8px 10px; text-align: left; font-size: 12px; }
  td { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; font-size: 12px; }
  tr:hover td { background: #f8f9fa; }
  .right { text-align: right; }
  .totals { margin-left: auto; width: 300px; }
  .totals table { margin: 0; }
  .totals td { border: none; padding: 4px 10px; }
  .total-row td { font-weight: 700; font-size: 14px; color: #0f3460; border-top: 2px solid #0f3460; }
  .rate-note { font-size: 11px; color: #6c757d; margin-top: 12px; border-top: 1px solid #eee; padding-top: 10px; }
  .footer { text-align: center; font-size: 11px; color: #aaa; margin-top: 24px; border-top: 1px solid #eee; padding-top: 14px; }
</style>
</head>
<body>
<div class="invoice-box">

  <div class="header">
    <div>
      <div class="business-name">$businessName</div>
      <div style="margin-top:6px;font-size:12px;color:#6c757d;">Sistema KND Retail</div>
    </div>
    <div class="invoice-meta">
      <div class="invoice-num">$invoiceNum</div>
      <div style="font-size:12px;margin-top:4px;">$createdAt</div>
      <span class="badge badge-$sale[type]">$saleType</span>
    </div>
  </div>

  <div class="info-grid">
    <div class="info-block">
      <h4>Cliente</h4>
      <strong>$customerName</strong><br>
      <span style="color:#6c757d;">Doc: $customerDoc</span>
    </div>
    <div class="info-block">
      <h4>Moneda</h4>
      <strong>$localCurrency</strong><br>
      <span style="color:#6c757d;">$rateDisplay</span>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>SKU</th>
        <th>Producto</th>
        <th class="right">Qty</th>
        <th class="right">Precio ($baseCurrency)</th>
        <th class="right">Precio ($localCurrency)</th>
        <th class="right">Subtotal ($baseCurrency)</th>
      </tr>
    </thead>
    <tbody>
      $itemRows
    </tbody>
  </table>

  <div class="totals">
    <table>
      <tr>
        <td>Total $baseCurrency:</td>
        <td class="right">{$baseCurrency} " . number_format($totalBase, 2) . "</td>
      </tr>
      <tr>
        <td>Total $localCurrency:</td>
        <td class="right">{$localCurrency} " . number_format($totalLocal, 2) . "</td>
      </tr>
      <tr class="total-row">
        <td>TOTAL:</td>
        <td class="right">{$localCurrency} " . number_format($totalLocal, 2) . "</td>
      </tr>
    </table>
  </div>

  <div class="rate-note">
    Tasa de cambio al momento de la venta: $rateDisplay &nbsp;|&nbsp; ID Venta: #{$sale['id']}
  </div>

  <div class="footer">
    Generado por KND Retail SaaS &nbsp;·&nbsp; $createdAt<br>
    Este documento es válido como comprobante de la transacción registrada.
  </div>
</div>
</body>
</html>
HTML;
}
