<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST REQUIRE ===<br><br>";

echo "1. Verificando si functions-i18n.php existe...<br>";
$file = __DIR__ . '/includes/functions-i18n.php';
if (file_exists($file)) {
    echo "✓ Archivo existe: $file<br>";
    echo "Tamaño: " . filesize($file) . " bytes<br>";
} else {
    echo "✗ Archivo NO existe: $file<br>";
    die();
}

echo "<br>2. Cargando functions-i18n.php directamente...<br>";
require_once $file;
echo "✓ functions-i18n.php cargado<br>";

echo "<br>3. Verificando funciones:<br>";
echo "function_exists('t'): " . (function_exists('t') ? 'SÍ ✓' : 'NO ✗') . "<br>";

echo "<br>4. Cargando config.php...<br>";
require_once __DIR__ . '/includes/config.php';
echo "✓ config.php cargado<br>";

echo "<br>5. Verificando funciones después de config.php:<br>";
echo "function_exists('t'): " . (function_exists('t') ? 'SÍ ✓' : 'NO ✗') . "<br>";

if (!function_exists('t')) {
    echo "<br><strong>PROBLEMA:</strong> Las funciones no se están definiendo cuando se carga config.php<br>";
    echo "Esto sugiere que el require_once en config.php no se está ejecutando o hay un error.<br>";
    
    echo "<br>6. Verificando línea 29 de config.php...<br>";
    $config_lines = file(__DIR__ . '/includes/config.php');
    if (isset($config_lines[28])) {
        echo "Línea 29: " . htmlspecialchars($config_lines[28]) . "<br>";
    }
}
?>
