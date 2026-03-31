<?php
// KND Store - i18n fallback (rescate)
// Esto evita fatal errors si faltan archivos de idioma.
// Luego lo mejoramos bien.

// Función auxiliar para cargar el diccionario de idioma
if (!function_exists('_load_i18n_dict')) {
    function _load_i18n_dict(): array {
        static $dict = null;
        
        if ($dict !== null) {
            return $dict;
        }
        
        $lang = current_lang();
        $langFile = __DIR__ . '/lang/' . $lang . '.php';
        
        if (file_exists($langFile) && is_readable($langFile)) {
            $dict = require $langFile;
            if (is_array($dict)) {
                return $dict;
            }
        }
        
        // Fallback: diccionario vacío si no se puede cargar
        $dict = [];
        return $dict;
    }
}

if (!function_exists('t')) {
    function t(string $key, $fallback = null, array $vars = []): string {
        // Detectar si $fallback es un array (compatibilidad con llamadas antiguas)
        if (is_array($fallback)) {
            // Si tiene claves 'es' o 'en', es un diccionario por idioma
            if (isset($fallback['es']) || isset($fallback['en'])) {
                $lang = current_lang();
                $text = $fallback[$lang] ?? $fallback['es'] ?? $fallback['en'] ?? $key;
            } else {
                // Si no tiene 'es'/'en, entonces es $vars y el fallback es null
                $vars = $fallback;
                // Intentar buscar en el diccionario primero
                $dict = _load_i18n_dict();
                $text = $dict[$key] ?? $key;
            }
        } else {
            // $fallback es string/null normal
            // Intentar buscar en el diccionario primero
            $dict = _load_i18n_dict();
            if (isset($dict[$key])) {
                $text = $dict[$key];
            } else {
                $text = $fallback ?? $key;
            }
        }

        // Reemplazo simple: {name} => valor
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', (string)$v, $text);
        }
        
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('t_html')) {
    function t_html(string $key, $fallback = null, array $vars = []): string {
        // Detectar si $fallback es un array (compatibilidad con llamadas antiguas)
        if (is_array($fallback)) {
            // Si tiene claves 'es' o 'en', es un diccionario por idioma
            if (isset($fallback['es']) || isset($fallback['en'])) {
                $lang = current_lang();
                $text = $fallback[$lang] ?? $fallback['es'] ?? $fallback['en'] ?? $key;
            } else {
                // Si no tiene 'es'/'en, entonces es $vars y el fallback es null
                $vars = $fallback;
                // Intentar buscar en el diccionario primero
                $dict = _load_i18n_dict();
                $text = $dict[$key] ?? $key;
            }
        } else {
            // $fallback es string/null normal
            // Intentar buscar en el diccionario primero
            $dict = _load_i18n_dict();
            if (isset($dict[$key])) {
                $text = $dict[$key];
            } else {
                $text = $fallback ?? $key;
            }
        }

        // Reemplazo simple: {name} => valor
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $text);
        }
        
        return $text; // Sin htmlspecialchars final para permitir HTML
    }
}

if (!function_exists('current_lang')) {
    function current_lang(): string {
        return 'en';
    }
}

if (!function_exists('__')) {
    function __(string $key, $fallback = null, array $vars = []): string {
        return t($key, $fallback, $vars);
    }
}
