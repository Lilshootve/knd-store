<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<br><strong style='color:red'>ERROR FATAL DETECTADO:</strong><br>";
        echo "Mensaje: " . $error['message'] . "<br>";
        echo "Archivo: " . $error['file'] . "<br>";
        echo "Línea: " . $error['line'] . "<br>";
    }
});

echo "=== TEST INDEX.PHP ===<br><br>";

echo "1. Cargando session.php...<br>";
require_once __DIR__ . '/includes/session.php';
echo "✓ session.php cargado<br><br>";

echo "2. Cargando config.php...<br>";
require_once __DIR__ . '/includes/config.php';
echo "✓ config.php cargado<br><br>";

echo "3. Verificando funciones:<br>";
echo "function_exists('t'): " . (function_exists('t') ? 'SÍ ✓' : 'NO ✗') . "<br><br>";

echo "4. Cargando header.php...<br>";
ob_start();
$error_occurred = false;
try {
    require_once __DIR__ . '/includes/header.php';
    echo "✓ header.php cargado<br>";
} catch (Throwable $e) {
    $error_occurred = true;
    echo "✗ ERROR en header.php: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
}
$output = ob_get_clean();
if ($output) {
    echo "Output: " . htmlspecialchars($output) . "<br>";
}

if (!$error_occurred) {
    echo "<br>5. Probando generateHeader()...<br>";
    try {
        $header = generateHeader('Test', 'Test description');
        echo "✓ generateHeader() funciona<br>";
        echo "Longitud del header: " . strlen($header) . " caracteres<br>";
    } catch (Throwable $e) {
        echo "✗ ERROR en generateHeader(): " . $e->getMessage() . "<br>";
    }
    
    echo "<br>6. Probando generateNavigation()...<br>";
    try {
        $nav = generateNavigation();
        echo "✓ generateNavigation() funciona<br>";
    } catch (Throwable $e) {
        echo "✗ ERROR en generateNavigation(): " . $e->getMessage() . "<br>";
    }
}

echo "<br>7. Cargando products-data.php...<br>";
try {
    require_once __DIR__ . '/includes/products-data.php';
    echo "✓ products-data.php cargado<br>";
} catch (Throwable $e) {
    echo "✗ ERROR en products-data.php: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
}

echo "<br>8. Intentando ejecutar inicio de index.php...<br>";
try {
    $startTime = startPerformanceTimer();
    echo "✓ startPerformanceTimer() funciona<br>";
    setCacheHeaders('html');
    echo "✓ setCacheHeaders() funciona<br>";
} catch (Throwable $e) {
    echo "✗ ERROR: " . $e->getMessage() . "<br>";
}
?>
