<?php
/**
 * Retail module — read-only reporting tools (invoked via modules/retail/tools.php).
 */

declare(strict_types=1);

/**
 * Top products by revenue (total_base) in a date range.
 *
 * @return array{success: bool, ...}|array{error: string}
 */
function retail_report_get_top_products(PDO $pdo, array $input): array
{
    $bizId = retail_business_id();
    $limit = isset($input['limit']) ? max(1, min(100, (int) $input['limit'])) : 10;
    [$from, $to] = retail_report_resolve_date_range($input);

    $sql = '
        SELECT si.product_id,
               MAX(p.name) AS product_name,
               SUM(si.qty) AS units_sold,
               SUM(si.qty * si.price_snapshot) AS revenue_base
        FROM retail_sale_items si
        INNER JOIN retail_sales s ON s.id = si.sale_id AND s.business_id = ?
        INNER JOIN retail_products p ON p.id = si.product_id AND p.business_id = s.business_id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY si.product_id
        ORDER BY revenue_base DESC
        LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$bizId, $from, $to]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'success'        => true,
        'date_from'      => $from,
        'date_to'        => $to,
        'base_currency'  => retail_business()['base_currency'],
        'limit'          => $limit,
        'top_products'   => $rows,
        'count'          => count($rows),
    ];
}

/**
 * Aggregated sales for a date range (cash vs credit, by currency).
 */
function retail_report_get_sales_summary(PDO $pdo, array $input): array
{
    $bizId = retail_business_id();
    [$from, $to] = retail_report_resolve_date_range($input);

    $stmt = $pdo->prepare(
        'SELECT
            DATE(created_at) AS sale_date,
            type,
            currency_used,
            COUNT(*) AS transactions,
            COALESCE(SUM(total_base), 0) AS total_base,
            COALESCE(SUM(total_local), 0) AS total_local
         FROM retail_sales
         WHERE business_id = ? AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY DATE(created_at), type, currency_used
         ORDER BY sale_date ASC, type ASC'
    );
    $stmt->execute([$bizId, $from, $to]);
    $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt2 = $pdo->prepare(
        'SELECT
            COUNT(*) AS transactions,
            COALESCE(SUM(total_base), 0) AS total_base
         FROM retail_sales
         WHERE business_id = ? AND DATE(created_at) BETWEEN ? AND ?'
    );
    $stmt2->execute([$bizId, $from, $to]);
    $tot = $stmt2->fetch(PDO::FETCH_ASSOC) ?: ['transactions' => 0, 'total_base' => 0];

    return [
        'success'       => true,
        'date_from'     => $from,
        'date_to'       => $to,
        'base_currency' => retail_business()['base_currency'],
        'totals'        => [
            'transactions' => (int) ($tot['transactions'] ?? 0),
            'total_base'   => (float) ($tot['total_base'] ?? 0),
        ],
        'breakdown'     => $breakdown,
    ];
}

/**
 * Customers with credit balance > 0 (and optional minimum).
 */
function retail_report_list_customer_balances(PDO $pdo, array $input): array
{
    $bizId      = retail_business_id();
    $minBalance = isset($input['min_balance']) ? max(0.0, (float) $input['min_balance']) : 0.01;
    $limit      = isset($input['limit']) ? max(1, min(500, (int) $input['limit'])) : 100;

    $sql = '
        SELECT c.id AS customer_id,
               c.name,
               c.document_id,
               cr.balance,
               cr.id AS credit_id
        FROM retail_credits cr
        INNER JOIN retail_customers c ON c.id = cr.customer_id AND c.business_id = cr.business_id
        WHERE cr.business_id = ? AND cr.balance >= ?
        ORDER BY cr.balance DESC
        LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$bizId, $minBalance]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sumStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(balance), 0) FROM retail_credits WHERE business_id = ? AND balance >= ?'
    );
    $sumStmt->execute([$bizId, $minBalance]);
    $totalDebt = (float) $sumStmt->fetchColumn();

    return [
        'success'          => true,
        'base_currency'    => retail_business()['base_currency'],
        'min_balance'      => $minBalance,
        'total_debt_base'  => $totalDebt,
        'customers'        => $rows,
        'count'            => count($rows),
    ];
}

/**
 * @return array{0: string, 1: string} Y-m-d
 */
function retail_report_resolve_date_range(array $input): array
{
    $today = date('Y-m-d');
    $from  = isset($input['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['date_from'])
        ? (string) $input['date_from'] : date('Y-m-d', strtotime('-30 days'));
    $to    = isset($input['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['date_to'])
        ? (string) $input['date_to'] : $today;

    if ($from > $to) {
        return [$to, $from];
    }
    return [$from, $to];
}
