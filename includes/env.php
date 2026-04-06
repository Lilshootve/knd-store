<?php
require_once __DIR__ . '/../config/bootstrap.php';

/**
 * Production-grade .env loader for plain PHP projects.
 *
 * Critical protections:
 * - Strict key syntax validation.
 * - UTF-8 BOM/control character cleanup.
 * - Robust inline comment stripping (# outside quotes only).
 * - Deterministic singleton load and optional non-override behavior.
 * - Optional required key and length validation.
 * - Optional debug logs with secret masking.
 */

if (!function_exists('knd_env_mask_value')) {
    function knd_env_mask_value(string $key, string $value): string
    {
        $isSecretKey = preg_match('/(?:TOKEN|SECRET|PASS|PASSWORD|PWD|API_KEY|PRIVATE|AUTH)/i', $key) === 1;
        $len = strlen($value);

        if ($isSecretKey) {
            return '[masked len=' . $len . ']';
        }

        if ($len <= 8) {
            return '[len=' . $len . ']';
        }
        return '[len=' . $len . ' preview=' . substr($value, 0, 2) . '…' . substr($value, -2) . ']';
    }
}

if (!function_exists('knd_env_debug_log')) {
    function knd_env_debug_log(bool $enabled, string $message): void
    {
        if ($enabled) {
            error_log('[knd/env] ' . $message);
        }
    }
}

if (!function_exists('knd_env_strip_control_chars')) {
    /**
     * Remove control chars except \n, \r, \t.
     */
    function knd_env_strip_control_chars(string $value): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    }
}

if (!function_exists('knd_env_strip_inline_comment')) {
    /**
     * Remove comments introduced by # only when outside quotes.
     */
    function knd_env_strip_inline_comment(string $value): string
    {
        $inSingle = false;
        $inDouble = false;
        $escape = false;
        $len = strlen($value);

        for ($i = 0; $i < $len; $i++) {
            $ch = $value[$i];

            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }

            if (!$inDouble && $ch === "'") {
                $inSingle = !$inSingle;
                continue;
            }
            if (!$inSingle && $ch === '"') {
                $inDouble = !$inDouble;
                continue;
            }

            if (!$inSingle && !$inDouble && $ch === '#') {
                return rtrim(substr($value, 0, $i));
            }
        }

        return $value;
    }
}

if (!function_exists('knd_parse_env_value')) {
    function knd_parse_env_value(string $rawValue): string
    {
        // Normalize CR from CRLF and remove hidden/control chars first.
        $value = str_replace("\r", '', $rawValue);
        $value = knd_env_strip_control_chars($value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Remove inline comments outside quotes.
        $value = knd_env_strip_inline_comment($value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Remove matching wrapping quotes.
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Interpret common escaped sequences.
        $value = str_replace(['\n', '\r', '\t'], ["\n", "\r", "\t"], $value);

        // Final cleanup to ensure deterministic/no invisible trailing bytes.
        $value = knd_env_strip_control_chars($value);
        return trim($value);
    }
}

if (!function_exists('knd_load_env')) {
    /**
     * Load .env exactly once.
     *
     * Supported options:
     * - allow_override (bool): override existing env vars, default false.
     * - debug (bool): log duplicate/suspicious values, default false.
     * - required_keys (string[]): required non-empty keys.
     * - length_rules (array): key => int exact length OR ['min'=>x,'max'=>y,'exact'=>z].
     */
    function knd_load_env(?string $envPath = null, array $options = []): bool
    {
        static $loaded = false;
        static $loadedPath = null;

        if ($loaded) {
            if (!empty($options['debug']) && $loadedPath !== null && $envPath !== null && $envPath !== $loadedPath) {
                knd_env_debug_log(true, 'Loader already initialized; ignoring new path: ' . $envPath);
            }
            return true;
        }

        $allowOverride = !empty($options['allow_override']);
        $debug = !empty($options['debug']);
        $requiredKeys = isset($options['required_keys']) && is_array($options['required_keys'])
            ? $options['required_keys']
            : [];
        $lengthRules = isset($options['length_rules']) && is_array($options['length_rules'])
            ? $options['length_rules']
            : [];

        $path = $envPath ?: BASE_PATH . '/.env';
        $loadedPath = $path;
        if (!is_readable($path)) {
            $loaded = true;
            knd_env_debug_log($debug, '.env not readable: ' . $path);

            // Even without file, enforce required key checks against existing environment.
            foreach ($requiredKeys as $requiredKey) {
                $requiredKey = (string) $requiredKey;
                $v = knd_env($requiredKey, null);
                if ($v === null || trim($v) === '') {
                    throw new RuntimeException('Missing required environment variable: ' . $requiredKey);
                }
            }
            return false;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $loaded = true;
            return false;
        }

        // Remove UTF-8 BOM if present.
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }

        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        $lines = explode("\n", $content);

        $seen = [];
        foreach ($lines as $line) {
            $line = knd_env_strip_control_chars($line);
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                knd_env_debug_log($debug, 'Skipping malformed line without "=".');
                continue;
            }

            $name = trim(substr($line, 0, $eqPos));
            if ($name === '' || preg_match('/^[A-Z_][A-Z0-9_]*$/', $name) !== 1) {
                knd_env_debug_log($debug, 'Skipping invalid key name: "' . $name . '"');
                continue;
            }

            if (isset($seen[$name])) {
                knd_env_debug_log($debug, 'Duplicate key found (last one wins): ' . $name);
            }
            $seen[$name] = true;

            $value = knd_parse_env_value(substr($line, $eqPos + 1));
            $existing = knd_env($name, null);
            if ($existing !== null && !$allowOverride) {
                // Keep deterministic environment source while syncing all stores.
                $_ENV[$name] = $existing;
                $_SERVER[$name] = $existing;
                putenv($name . '=' . $existing);
                knd_env_debug_log($debug, 'Preserving existing env var (override disabled): ' . $name);
                continue;
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($name . '=' . $value);

            if ($debug) {
                // Suspicious value heuristics.
                if ($value !== '' && preg_match('/[^\PC\s]/u', $value) === 1) {
                    knd_env_debug_log(true, 'Non-printable unicode chars detected in ' . $name . ' ' . knd_env_mask_value($name, $value));
                }
                if (strlen($value) > 4096) {
                    knd_env_debug_log(true, 'Unusually long env value in ' . $name . ' ' . knd_env_mask_value($name, $value));
                }
            }
        }

        // Required keys validation.
        foreach ($requiredKeys as $requiredKey) {
            $requiredKey = (string) $requiredKey;
            $v = knd_env($requiredKey, null);
            if ($v === null || trim($v) === '') {
                throw new RuntimeException('Missing required environment variable: ' . $requiredKey);
            }
        }

        // Optional length validation (for tokens/secrets and similar).
        foreach ($lengthRules as $key => $rule) {
            $key = (string) $key;
            $v = knd_env($key, null);
            if ($v === null || trim($v) === '') {
                continue;
            }

            $len = strlen($v);
            $ok = true;
            $msg = '';

            if (is_int($rule)) {
                $ok = ($len === $rule);
                $msg = 'expected exact length ' . $rule . ', got ' . $len;
            } elseif (is_array($rule)) {
                if (isset($rule['exact'])) {
                    $exact = (int) $rule['exact'];
                    $ok = ($len === $exact);
                    $msg = 'expected exact length ' . $exact . ', got ' . $len;
                } else {
                    $min = isset($rule['min']) ? (int) $rule['min'] : null;
                    $max = isset($rule['max']) ? (int) $rule['max'] : null;
                    if ($min !== null && $len < $min) {
                        $ok = false;
                        $msg = 'expected min length ' . $min . ', got ' . $len;
                    }
                    if ($max !== null && $len > $max) {
                        $ok = false;
                        $msg = 'expected max length ' . $max . ', got ' . $len;
                    }
                }
            }

            if (!$ok) {
                throw new RuntimeException('Invalid environment variable length for ' . $key . ': ' . $msg);
            }
            if ($debug) {
                knd_env_debug_log(true, 'Validated length for ' . $key . ' ' . knd_env_mask_value($key, $v));
            }
        }

        $loaded = true;
        return true;
    }
}

if (!function_exists('knd_env')) {
    function knd_env(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return (string) $value;
        }

        return $default;
    }
}

if (!function_exists('knd_env_required')) {
    function knd_env_required(string $key): string
    {
        $value = knd_env($key, null);
        if ($value === null || trim($value) === '') {
            throw new RuntimeException('Missing required environment variable: ' . $key);
        }
        return (string) $value;
    }
}

if (!function_exists('knd_env_bool')) {
    function knd_env_bool(string $key, bool $default = false): bool
    {
        $value = knd_env($key, null);
        if ($value === null) {
            return $default;
        }
        $v = strtolower(trim($value));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('knd_env_int')) {
    function knd_env_int(string $key, int $default = 0): int
    {
        $value = knd_env($key, null);
        if ($value === null || trim($value) === '') {
            return $default;
        }
        if (!preg_match('/^-?\d+$/', trim($value))) {
            return $default;
        }
        return (int) $value;
    }
}

// Generic aliases requested by integrations that expect env* helpers.
if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        return knd_env($key, $default);
    }
}

if (!function_exists('env_required')) {
    function env_required(string $key): string
    {
        return knd_env_required($key);
    }
}

if (!function_exists('env_bool')) {
    function env_bool(string $key): bool
    {
        return knd_env_bool($key, false);
    }
}

if (!function_exists('env_int')) {
    function env_int(string $key): int
    {
        return knd_env_int($key, 0);
    }
}

if (!function_exists('knd_request_authorization_header')) {
    /**
     * Raw Authorization header as PHP sees it.
     *
     * On Apache + mod_rewrite, the client header is often copied to
     * REDIRECT_HTTP_AUTHORIZATION while HTTP_AUTHORIZATION stays empty — same token in
     * Postman, but hash_equals fails if only HTTP_AUTHORIZATION is read.
     */
    function knd_request_authorization_header(): string
    {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0) {
                        return (string) $value;
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('knd_agents_token')) {
    /**
     * Bearer/API token for KND Agents: execute.php, /api/agent/*, Iris retail catalog, panel bridge.
     * Prefer KND_AGENTS_TOKEN (Iris/Kael); falls back to KND_WORKER_TOKEN only for legacy .env.
     * ComfyUI/labs HTTP workers use KND_WORKER_TOKEN via worker_auth.php — keep tokens separate in production.
     */
    function knd_agents_token(): string
    {
        $primary = trim((string) (knd_env('KND_AGENTS_TOKEN') ?? ''));
        if ($primary !== '') {
            return $primary;
        }
        return trim((string) (knd_env('KND_WORKER_TOKEN') ?? ''));
    }
}

knd_load_env();
