<?php
/**
 * Retail module — tool registry for agent execute.php (get_module_tools contract).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config/bootstrap.php';
defined('KND_ROOT') or define('KND_ROOT', BASE_PATH);

require_once BASE_PATH . '/core/retail/auth.php';
require_once BASE_PATH . '/core/retail/tools/create_sale.php';
require_once BASE_PATH . '/core/retail/tools/create_credit_sale.php';
require_once BASE_PATH . '/core/retail/tools/register_credit_payment.php';
require_once BASE_PATH . '/core/retail/tools/get_product.php';
require_once BASE_PATH . '/core/retail/tools/search_product.php';
require_once BASE_PATH . '/core/retail/tools/get_inventory_low.php';
require_once BASE_PATH . '/core/retail/tools/get_sales_today.php';
require_once BASE_PATH . '/core/retail/tools/adjust_stock.php';
require_once BASE_PATH . '/core/retail/tools/get_customer_by_document.php';
require_once BASE_PATH . '/core/retail/tools/create_customer_if_not_exists.php';
require_once BASE_PATH . '/core/retail/tools/update_exchange_rate.php';
require_once __DIR__ . '/reporting_tools.php';

/**
 * Assert server-resolved tenant matches input (execute.php injects business_id).
 */
function _retail_module_assert_business(array $input): void
{
    $bid = (int) ($input['business_id'] ?? 0);
    if ($bid <= 0) {
        throw new RuntimeException('BUSINESS_ID_REQUIRED: business_id must be set for retail tools.');
    }
    if ($bid !== retail_business_id()) {
        throw new RuntimeException('BUSINESS_MISMATCH: business_id does not match resolved tenant.');
    }
}

/**
 * @return array<string, callable(array): array>
 */
function get_module_tools(): array
{
    return [
        'create_sale' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_create_sale($pdo, $input);
        },
        'create_credit_sale' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_create_credit_sale($pdo, $input);
        },
        'register_credit_payment' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_register_credit_payment($pdo, $input);
        },
        'get_product' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_get_product($pdo, $input);
        },
        'search_product' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_search_product($pdo, $input);
        },
        'get_inventory_low' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_get_inventory_low($pdo, $input);
        },
        'get_sales_today' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_get_sales_today($pdo, $input);
        },
        'adjust_stock' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_adjust_stock($pdo, $input);
        },
        'get_customer_by_document' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_get_customer_by_document($pdo, $input);
        },
        'create_customer_if_not_exists' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_create_customer_if_not_exists($pdo, $input);
        },
        'update_exchange_rate' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_update_exchange_rate($pdo, $input);
        },
        'get_top_products' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_report_get_top_products($pdo, $input);
        },
        'get_sales_summary' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_report_get_sales_summary($pdo, $input);
        },
        'list_customer_balances' => function (array $input): array {
            _retail_module_assert_business($input);
            $pdo = getDBConnection();
            if (!$pdo) {
                throw new RuntimeException('DB connection failed.');
            }
            return retail_report_list_customer_balances($pdo, $input);
        },
    ];
}
