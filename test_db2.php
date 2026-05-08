<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Starting DB test...\n";
$start = microtime(true);
require_once __DIR__ . '/backend/users/config/database.php';
$conexion = getDBConnection();
$end = microtime(true);

echo "Connected successfully.\n";
echo "Time taken: " . ($end - $start) . " seconds\n";
