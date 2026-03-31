<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "=== TEST DE SINTAXIS ===<br><br>";

$config_file = __DIR__ . '/includes/config.php';

echo "1. Verificando sintaxis de config.php...<br>";
$output = [];
$return_var = 0;
exec("php -l " . escapeshellarg($config_file) . " 2>&1", $output, $return_var);

if ($return_var === 0) {
    echo "✓ Sintaxis correcta<br>";
    echo implode("<br>", $output) . "<br>";
} else {
    echo "✗ ERROR DE SINTAXIS:<br>";
    echo implode("<br>", $output) . "<br>";
    die();
}

echo "<br>2. Cargando config.php...<br>";
try {
    require_once $config_file;
    echo "✓ config.php cargado<br>";
} catch (Throwable $e) {
    echo "✗ ERROR al cargar: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    die();
}

echo "<br>3. Verificando funciones:<br>";
echo "function_exists('t'): " . (function_exists('t') ? 'SÍ ✓' : 'NO ✗') . "<br>";
echo "function_exists('t_html'): " . (function_exists('t_html') ? 'SÍ ✓' : 'NO ✗') . "<br>";
echo "function_exists('current_lang'): " . (function_exists('current_lang') ? 'SÍ ✓' : 'NO ✗') . "<br>";

if (function_exists('t')) {
    echo "<br>4. Probando función t():<br>";
    $result = t('test.key', array(), 'Test funciona');
    echo "Resultado: " . htmlspecialchars($result) . "<br>";
}
?>
