<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "=== TEST DE PARSE ===<br><br>";

$config_file = __DIR__ . '/includes/config.php';

echo "1. Verificando sintaxis de config.php...<br>";
$output = shell_exec("php -l " . escapeshellarg($config_file) . " 2>&1");
echo htmlspecialchars($output) . "<br><br>";

echo "2. Leyendo archivo y mostrando líneas 24-35 (donde deberían estar las funciones):<br>";
$lines = file($config_file);
for ($i = 23; $i < 35 && $i < count($lines); $i++) {
    $line_num = $i + 1;
    $line = $lines[$i];
    echo "Línea $line_num: " . htmlspecialchars($line) . "<br>";
    
    // Buscar función t
    if (strpos($line, 'function t(') !== false) {
        echo ">>> ¡ENCONTRADA función t() en línea $line_num!<br>";
    }
}

echo "<br>3. Intentando ejecutar solo las primeras 30 líneas...<br>";
$first_30_lines = implode('', array_slice($lines, 0, 30));
eval('?>' . $first_30_lines);
echo "✓ Primeras 30 líneas ejecutadas<br>";
echo "function_exists('t'): " . (function_exists('t') ? 'SÍ' : 'NO') . "<br>";

echo "<br>4. Intentando ejecutar líneas 24-35 (donde están las funciones)...<br>";
$func_lines = implode('', array_slice($lines, 23, 12));
try {
    eval($func_lines);
    echo "✓ Líneas de funciones ejecutadas<br>";
    echo "function_exists('t'): " . (function_exists('t') ? 'SÍ' : 'NO') . "<br>";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
}
?>
