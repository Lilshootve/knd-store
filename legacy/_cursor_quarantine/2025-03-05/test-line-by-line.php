<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "=== TEST LÍNEA POR LÍNEA ===<br><br>";

$config_file = __DIR__ . '/includes/config.php';
$lines = file($config_file);

echo "Ejecutando config.php línea por línea hasta encontrar las funciones...<br><br>";

$code_so_far = '';
for ($i = 0; $i < count($lines); $i++) {
    $line_num = $i + 1;
    $line = $lines[$i];
    $code_so_far .= $line;
    
    // Mostrar cada línea importante
    if ($line_num <= 30 || strpos($line, 'function') !== false) {
        echo "Línea $line_num: " . htmlspecialchars(rtrim($line)) . "<br>";
    }
    
    // Intentar ejecutar hasta esta línea cada 5 líneas
    if ($line_num % 5 == 0 || strpos($line, 'function t(') !== false) {
        ob_start();
        $error = null;
        try {
            eval('?>' . $code_so_far);
        } catch (Throwable $e) {
            $error = $e;
        }
        $output = ob_get_clean();
        
        if ($error) {
            echo "<strong style='color:red'>ERROR en línea $line_num:</strong> " . $error->getMessage() . "<br>";
            echo "Tipo: " . get_class($error) . "<br>";
            break;
        }
        
        if (function_exists('t')) {
            echo "<strong style='color:green'>✓ Función t() definida después de línea $line_num</strong><br>";
            break;
        }
        
        if ($line_num >= 35) {
            echo "<strong style='color:orange'>Llegamos a línea $line_num y t() aún no existe</strong><br>";
            break;
        }
    }
}

echo "<br>Resultado final:<br>";
echo "function_exists('t'): " . (function_exists('t') ? '<strong style="color:green">SÍ</strong>' : '<strong style="color:red">NO</strong>') . "<br>";
?>
