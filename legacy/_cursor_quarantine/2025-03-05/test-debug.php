<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "=== DEBUG TEST ===<br><br>";

echo "1. Antes de cargar config.php<br>";
echo "function_exists('t'): " . (function_exists('t') ? 'SÍ' : 'NO') . "<br><br>";

echo "2. Cargando config.php línea por línea...<br>";

// Leer el archivo y ejecutarlo manualmente
$config_file = __DIR__ . '/includes/config.php';
$lines = file($config_file);

$line_num = 0;
foreach ($lines as $line) {
    $line_num++;
    
    // Saltar hasta llegar a donde están las funciones
    if ($line_num < 27) continue;
    
    // Mostrar las primeras líneas relevantes
    if ($line_num <= 45) {
        echo "Línea $line_num: " . htmlspecialchars(trim($line)) . "<br>";
        
        // Si llegamos a la definición de función t, verificar
        if (strpos($line, 'function t(') !== false) {
            echo ">>> ENCONTRADA DEFINICIÓN DE FUNCIÓN t() EN LÍNEA $line_num<br>";
        }
    }
    
    // Ejecutar hasta la línea 40 (justo antes de la función)
    if ($line_num == 40) {
        echo "<br>3. Ejecutando código hasta línea 40...<br>";
        eval(substr(file_get_contents($config_file), 0, strpos(file_get_contents($config_file), 'function t(')));
        echo "Código ejecutado hasta línea 40<br>";
        echo "function_exists('t'): " . (function_exists('t') ? 'SÍ' : 'NO') . "<br>";
        break;
    }
}

echo "<br>4. Intentando cargar config.php completo...<br>";
ob_start();
$error_occurred = false;
try {
    require_once $config_file;
} catch (Throwable $e) {
    $error_occurred = true;
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
}
$output = ob_get_clean();

if ($output) {
    echo "Output capturado: " . htmlspecialchars($output) . "<br>";
}

echo "<br>5. Después de cargar config.php:<br>";
echo "function_exists('t'): " . (function_exists('t') ? 'SÍ' : 'NO') . "<br>";

if (!$error_occurred && !function_exists('t')) {
    echo "<br><strong>PROBLEMA: La función no se definió pero no hubo error.</strong><br>";
    echo "Esto sugiere que la ejecución se detuvo antes de llegar a la definición.<br>";
}
?>
