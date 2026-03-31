<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "=== TEST FINAL ===<br><br>";

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<br><strong>ERROR FATAL DETECTADO:</strong><br>";
        echo "Mensaje: " . $error['message'] . "<br>";
        echo "Archivo: " . $error['file'] . "<br>";
        echo "Línea: " . $error['line'] . "<br>";
    }
});

echo "1. Cargando session.php...<br>";
require_once __DIR__ . '/includes/session.php';
echo "✓ session.php cargado<br><br>";

echo "2. Cargando config.php...<br>";
ob_start();
$error_occurred = false;
$error_message = '';
try {
    require_once __DIR__ . '/includes/config.php';
} catch (Throwable $e) {
    $error_occurred = true;
    $error_message = $e->getMessage();
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
}
$output = ob_get_clean();

if ($output) {
    echo "Output capturado: " . htmlspecialchars($output) . "<br>";
}

if (!$error_occurred) {
    echo "✓ config.php cargado sin errores aparentes<br><br>";
}

echo "3. Verificando funciones:<br>";
echo "function_exists('t'): " . (function_exists('t') ? '<strong style="color:green">SÍ ✓</strong>' : '<strong style="color:red">NO ✗</strong>') . "<br>";
echo "function_exists('t_html'): " . (function_exists('t_html') ? '<strong style="color:green">SÍ ✓</strong>' : '<strong style="color:red">NO ✗</strong>') . "<br>";
echo "function_exists('current_lang'): " . (function_exists('current_lang') ? '<strong style="color:green">SÍ ✓</strong>' : '<strong style="color:red">NO ✗</strong>') . "<br><br>";

if (function_exists('t')) {
    echo "4. Probando función t():<br>";
    $result = t('test.key', array(), 'Test funciona');
    echo "Resultado: " . htmlspecialchars($result) . "<br>";
} else {
    echo "4. NO se puede probar t() porque no existe<br>";
    echo "<br><strong>DIAGNÓSTICO:</strong><br>";
    echo "Las funciones están en el archivo pero no se están definiendo.<br>";
    echo "Esto sugiere que hay un error fatal que está deteniendo la ejecución antes de llegar a las funciones.<br>";
    echo "Revisa los logs de PHP o el handler de errores fatales arriba.<br>";
}
?>
