<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "Test Simple: Verificando funciones<br><br>";

// Definir funciones directamente aquí para probar
function t($key = '', $vars = array(), $fallback = '') {
    $key = (string)$key;
    if (!is_array($vars)) {
        $vars = array();
    }
    $fallback = (string)$fallback;
    
    if ($fallback !== '') {
        $value = $fallback;
    } elseif ($key !== '') {
        $parts = explode('.', $key);
        $value = end($parts);
        $value = ucfirst(str_replace('_', ' ', $value));
    } else {
        $value = '';
    }
    
    if (!empty($vars)) {
        foreach ($vars as $name => $rawVal) {
            $safeVal = htmlspecialchars((string) $rawVal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = str_replace('{' . $name . '}', $safeVal, $value);
        }
    }
    
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

echo "Función t() definida directamente<br>";
echo "function_exists('t'): " . (function_exists('t') ? 'SÍ' : 'NO') . "<br>";
echo "Probando t('test.key', array(), 'Test funciona'): " . t('test.key', array(), 'Test funciona') . "<br><br>";

// Ahora probar cargar config.php
echo "Ahora cargando config.php...<br>";
require_once __DIR__ . '/includes/config.php';

echo "Después de cargar config.php:<br>";
echo "function_exists('t'): " . (function_exists('t') ? 'SÍ' : 'NO') . "<br>";

// Verificar si hay conflicto
$all_functions = get_defined_functions();
$user_functions = array_filter($all_functions['user'], function($func) {
    return $func === 't';
});
echo "Número de funciones 't' definidas: " . count($user_functions) . "<br>";
?>
