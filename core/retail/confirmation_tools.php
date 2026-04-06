<?php
/**
 * Retail tools that require a two-step confirm_id for admin callers on execute.php.
 *
 * Source of truth: config/retail_confirmation_tools.json (also imported by Next Iris).
 */

declare(strict_types=1);

if (!function_exists('knd_retail_confirmation_tools')) {
    /**
     * @return list<string>
     */
    function knd_retail_confirmation_tools(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $path = BASE_PATH . '/config/retail_confirmation_tools.json';
        if (is_readable($path)) {
            $raw = file_get_contents($path);
            if ($raw !== false) {
                $j = json_decode($raw, true);
                if (is_array($j) && isset($j['tools']) && is_array($j['tools'])) {
                    $tools = [];
                    foreach ($j['tools'] as $t) {
                        if (is_string($t) && $t !== '') {
                            $tools[] = $t;
                        }
                    }
                    if ($tools !== []) {
                        $cached = array_values(array_unique($tools));
                        return $cached;
                    }
                }
            }
        }

        $cached = [
            'create_sale',
            'create_credit_sale',
            'register_credit_payment',
            'create_customer_if_not_exists',
            'adjust_stock',
            'update_exchange_rate',
        ];
        return $cached;
    }
}
