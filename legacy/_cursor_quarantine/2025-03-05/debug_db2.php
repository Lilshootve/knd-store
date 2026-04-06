<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once BASE_PATH . '/includes/config.php';

echo "PHP SAPI: " . php_sapi_name() . PHP_EOL;

$vars = [
  'DB_HOST','DB_NAME','DB_USER','DB_PASS','DB_PORT',
  'MYSQL_HOST','MYSQL_DATABASE','MYSQL_USER','MYSQL_PASSWORD','MYSQL_PORT'
];

foreach ($vars as $k) {
  $v = defined($k) ? constant($k) : (getenv($k) ?: null);
  echo $k . " = " . ($v === null ? "(null)" : $v) . PHP_EOL;
}

try {
  $pdo = getDBConnection();
  var_dump($pdo);
} catch (Throwable $e) {
  echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
}