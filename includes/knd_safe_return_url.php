<?php
/**
 * Sanitize ?return= for same-site navigation (path + query only). Mitigates open redirects.
 */
function knd_safe_return_url_from_get(string $param = 'return'): ?string
{
    if (!isset($_GET[$param])) {
        return null;
    }
    return knd_sanitize_internal_return_url((string) $_GET[$param]);
}

function knd_sanitize_internal_return_url(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (strlen($raw) > 512) {
        return null;
    }
    if (strpbrk($raw, "\r\n\0\t") !== false) {
        return null;
    }
    if (preg_match('#^(?i)(https?|javascript|data):#', $raw)) {
        return null;
    }
    if ($raw[0] !== '/') {
        return null;
    }
    if (str_starts_with($raw, '//')) {
        return null;
    }
    if (str_contains($raw, '\\')) {
        return null;
    }
    if (str_contains($raw, '@')) {
        return null;
    }
    return $raw;
}
