<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "=== TEST ARCHIVO DE FUNCIONES SEPARADO ===<br><br>";

echo "1. Cargando functions-i18n.php directamente...<br>";
require_once __DIR__ . '/includes/functions-i18n.php';
echo "✓ functions-i18n.php cargado<br><br>";

echo "2. Verificando funciones:<br>";
echo "function_exists('t'): " . (function_exists('t') ? '<strong style="color:green">SÍ ✓</strong>' : '<strong style="color:red">NO ✗</strong>') . "<br>";
echo "function_exists('t_html'): " . (function_exists('t_html') ? '<strong style="color:green">SÍ ✓</strong>' : '<strong style="color:red">NO ✗</strong>') . "<br>";
echo "function_exists('current_lang'): " . (function_exists('current_lang') ? '<strong style="color:green">SÍ ✓</strong>' : '<strong style="color:red">NO ✗</strong>') . "<br><br>";

if (function_exists('t')) {
    echo "3. Probando función t():<br>";
    $result = t('test.key', array(), 'Test funciona');
    echo "Resultado: " . htmlspecialchars($result) . "<br><br>";
}

echo "4. Ahora cargando config.php...<br>";
require_once __DIR__ . '/includes/config.php';
echo "✓ config.php cargado<br><br>";

echo "5. Verificando funciones después de cargar config.php:<br>";
echo "function_exists('t'): " . (function_exists('t') ? '<strong style="color:green">SÍ ✓</strong>' : '<strong style="color:red">NO ✗</strong>') . "<br>";
?>
