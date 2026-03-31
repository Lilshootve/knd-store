<?php
/**
 * Lightweight .env loader for plain PHP projects.
 * Loads root .env once and exposes knd_env() helper.
 */

if (!function_exists('knd_parse_env_value')) {
    function knd_parse_env_value(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        return str_replace(['\n', '\r', '\t'], ["\n", "\r", "\t"], $value);
    }
}

if (!function_exists('knd_load_env')) {
    function knd_load_env(?string $envPath = null): bool
    {
        static $loaded = false;
        if ($loaded) {
            return true;
        }

        $path = $envPath ?: dirname(__DIR__) . '/.env';
        if (!is_readable($path)) {
            $loaded = true;
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            $loaded = true;
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $name = trim(substr($line, 0, $eqPos));
            if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                continue;
            }

            $value = knd_parse_env_value(substr($line, $eqPos + 1));

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($name . '=' . $value);
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

knd_load_env();
