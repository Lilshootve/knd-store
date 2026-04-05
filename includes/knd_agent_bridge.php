<?php
/**
 * Server-side forwarder to POST /api/agent/execute.php.
 * Uses KND_AGENTS_TOKEN from the environment (never sent to the browser; legacy: KND_WORKER_TOKEN).
 */

declare(strict_types=1);

if (!function_exists('knd_agent_bridge_allowed_tools')) {
    /**
     * Retail module tools plus db_query for read-only admin dashboards.
     *
     * @return list<string>
     */
    function knd_agent_bridge_allowed_tools(): array
    {
        return [
            'db_query',
            'create_sale',
            'create_credit_sale',
            'register_credit_payment',
            'get_product',
            'search_product',
            'get_inventory_low',
            'get_sales_today',
            'adjust_stock',
            'get_customer_by_document',
            'create_customer_if_not_exists',
            'update_exchange_rate',
            'get_top_products',
            'get_sales_summary',
            'list_customer_balances',
        ];
    }
}

if (!function_exists('knd_agent_execute_forward')) {
    /**
     * POST JSON to execute.php with Bearer token.
     *
     * @return array{http_code:int, raw:string, json:?array, curl_error?:string}
     */
    function knd_agent_execute_forward(array $body): array
    {
        if (!function_exists('knd_load_env')) {
            require_once __DIR__ . '/env.php';
        }
        knd_load_env();

        $token = knd_agents_token();
        if ($token === '') {
            return [
                'http_code'   => 503,
                'raw'         => '',
                'json'        => null,
                'curl_error'  => 'KND_AGENTS_TOKEN (or legacy KND_WORKER_TOKEN) is not configured.',
            ];
        }

        $base = trim((string) (knd_env('KND_PUBLIC_BASE_URL') ?? ''));
        if ($base === '') {
            $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $scheme = $https ? 'https' : 'http';
            $host   = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
            $base   = $scheme . '://' . $host;
        }
        $url = rtrim($base, '/') . '/api/agent/execute.php';

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return ['http_code' => 500, 'raw' => '', 'json' => null, 'curl_error' => 'json_encode failed'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['http_code' => $code ?: 0, 'raw' => '', 'json' => null, 'curl_error' => $err ?: 'curl_exec failed'];
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['http_code' => $code, 'raw' => $raw, 'json' => null, 'curl_error' => 'invalid JSON from execute.php'];
        }

        return ['http_code' => $code, 'raw' => $raw, 'json' => $json];
    }
}

if (!function_exists('knd_retail_admin_agent_call')) {
    /**
     * Call execute.php as the current logged-in user (retail admin panel SSR).
     *
     * @return array|null Agent JSON payload, or null on transport/parse failure
     */
    function knd_retail_admin_agent_call(
        string $tool,
        array $input,
        ?string $confirmId = null,
        bool $simulate = false,
        ?string $currency = null,
    ): ?array {
        $uid = function_exists('current_user_id') ? current_user_id() : null;
        if ($uid === null || $uid <= 0) {
            return null;
        }

        $body = [
            'tool'          => $tool,
            'input'         => $input,
            'business_type' => 'retail',
            'user_id'       => (string) $uid,
            'mode'          => 'admin',
        ];
        if ($confirmId !== null && $confirmId !== '') {
            $body['confirm_id'] = $confirmId;
        }
        if ($simulate) {
            $body['simulate'] = true;
        }
        if ($currency !== null && $currency !== '') {
            $body['currency'] = $currency;
        }

        $res = knd_agent_execute_forward($body);
        return $res['json'];
    }
}

if (!function_exists('knd_retail_admin_db_rows')) {
    /**
     * Run a read-only SELECT via execute.php db_query. Returns rows or [] on failure.
     *
     * @return list<array<string,mixed>>
     */
    function knd_retail_admin_db_rows(string $sql, array $params = []): array
    {
        $j = knd_retail_admin_agent_call('db_query', ['sql' => $sql, 'params' => $params]);
        if ($j === null || ($j['status'] ?? '') !== 'success') {
            return [];
        }
        $data = $j['data'] ?? null;
        if (!is_array($data)) {
            return [];
        }
        $rows = $data['rows'] ?? [];
        return is_array($rows) ? $rows : [];
    }
}
