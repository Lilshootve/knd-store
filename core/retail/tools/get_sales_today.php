<?php
/**
 * Tool: get_sales_today
 * Resumen de ventas del día actual para el negocio activo.
 * Opcionalmente acepta 'date' (YYYY-MM-DD) para otros días.
 */

function retail_get_sales_today(PDO $pdo, array $input): array
{
    $bizId = retail_business_id();
    $date  = isset($input['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date'])
        ? $input['date']
        : date('Y-m-d');

    // Resumen agregado
    $summaryStmt = $pdo->prepare(
        'SELECT
           COUNT(*)                             AS total_transactions,
           COALESCE(SUM(total_base), 0)         AS total_base,
           COALESCE(SUM(total_local), 0)        AS total_local,
           COUNT(CASE WHEN type = "cash"   THEN 1 END) AS cash_count,
           COUNT(CASE WHEN type = "credit" THEN 1 END) AS credit_count,
           currency_used
         FROM retail_sales
         WHERE business_id = ? AND DATE(created_at) = ?
         GROUP BY currency_used
         ORDER BY total_base DESC'
    );
    $summaryStmt->execute([$bizId, $date]);
    $summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimas 20 ventas con detalle
    $salesStmt = $pdo->prepare(
        'SELECT s.id, s.type, s.total_base, s.total_local, s.currency_used,
                s.exchange_rate_snapshot, s.invoice_number, s.created_at,
                c.name AS customer_name
         FROM retail_sales s
         LEFT JOIN retail_customers c ON c.id = s.customer_id AND c.business_id = s.business_id
         WHERE s.business_id = ? AND DATE(s.created_at) = ?
         ORDER BY s.created_at DESC
         LIMIT 20'
    );
    $salesStmt->execute([$bizId, $date]);
    $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Total global (suma de all currencies en base)
    $totalBase = array_sum(array_column($summary, 'total_base'));

    return [
        'success'           => true,
        'date'              => $date,
        'total_base'        => (float) $totalBase,
        'base_currency'     => retail_business()['base_currency'],
        'by_currency'       => $summary,
        'recent_sales'      => $sales,
        'message'           => empty($summary)
            ? "Sin ventas el $date."
            : 'Total del día: ' . number_format($totalBase, 2) . ' ' . retail_business()['base_currency'],
    ];
}
