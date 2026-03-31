<?php
if (!function_exists('t')) {
    function t(string $key, $fallback = null, array $vars = []): string
    {
        // Caso: te pasaron un array como 2do parámetro (lo que está rompiendo el footer)
        if (is_array($fallback)) {
            $arr = $fallback;

            // Si trae 'es' o 'en', lo tomamos como diccionario simple
            if (isset($arr['es']) || isset($arr['en'])) {
                $lang = $_COOKIE['lang'] ?? 'es';
                $text = $arr[$lang] ?? ($arr['es'] ?? ($arr['en'] ?? $key));
            } else {
                // Si no, asumimos que son variables tipo {name}
                $vars = $arr;
                $text = $key;
            }
        } else {
            // Caso normal: fallback string o null
            $text = ($fallback !== null) ? (string)$fallback : $key;
        }

        // Reemplazo de variables {var}
        if (!empty($vars)) {
            foreach ($vars as $k => $v) {
                $text = str_replace('{' . $k . '}', (string)$v, $text);
            }
        }

        return $text;
    }
}

if (!function_exists('__')) {
    function __(string $key, $fallback = null, array $vars = []): string {
        return t($key, $fallback, $vars);
    }
}