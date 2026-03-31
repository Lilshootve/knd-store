<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "=== TEST DIRECTO ===<br><br>";

echo "1. Leyendo config.php directamente...<br>";
$config_content = file_get_contents(__DIR__ . '/includes/config.php');

// Buscar la línea donde está function t(
$lines = explode("\n", $config_content);
$found_t = false;
$found_t_html = false;
$found_current_lang = false;

foreach ($lines as $num => $line) {
    $line_num = $num + 1;
    if (strpos($line, 'function t(') !== false && !$found_t) {
        echo "ENCONTRADA función t() en línea $line_num: " . htmlspecialchars(trim($line)) . "<br>";
        $found_t = true;
    }
    if (strpos($line, 'function t_html(') !== false && !$found_t_html) {
        echo "ENCONTRADA función t_html() en línea $line_num: " . htmlspecialchars(trim($line)) . "<br>";
        $found_t_html = true;
    }
    if (strpos($line, 'function current_lang(') !== false && !$found_current_lang) {
        echo "ENCONTRADA función current_lang() en línea $line_num: " . htmlspecialchars(trim($line)) . "<br>";
        $found_current_lang = true;
    }
}

echo "<br>2. Mostrando líneas 20-90 del archivo:<br>";
for ($i = 19; $i < 90 && $i < count($lines); $i++) {
    echo "Línea " . ($i + 1) . ": " . htmlspecialchars($lines[$i]) . "<br>";
}

echo "<br>3. Intentando ejecutar config.php con output buffering...<br>";
ob_start();
$error = null;
try {
    require __DIR__ . '/includes/config.php';
} catch (Throwable $e) {
    $error = $e;
}
$output = ob_get_clean();

if ($error) {
    echo "ERROR CAPTURADO: " . $error->getMessage() . "<br>";
    echo "Archivo: " . $error->getFile() . "<br>";
    echo "Línea: " . $error->getLine() . "<br>";
}

echo "<br>4. Verificando funciones después de cargar:<br>";
echo "function_exists('t'): " . (function_exists('t') ? 'SÍ' : 'NO') . "<br>";
echo "function_exists('t_html'): " . (function_exists('t_html') ? 'SÍ' : 'NO') . "<br>";
echo "function_exists('current_lang'): " . (function_exists('current_lang') ? 'SÍ' : 'NO') . "<br>";

if ($output) {
    echo "<br>5. Output capturado: " . htmlspecialchars($output) . "<br>";
}
?>
