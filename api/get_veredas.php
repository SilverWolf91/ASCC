<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();
require_once __DIR__ . '/../config/database.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$departamento = isset($_GET['departamento']) ? trim($_GET['departamento']) : '';
$municipio    = isset($_GET['municipio'])    ? trim($_GET['municipio'])    : '';

if (empty($departamento) || empty($municipio)) {
    echo json_encode([]);
    exit;
}

if ($municipio === 'Otro (No aparece en la lista)') {
    echo json_encode(['Otro (No está en la lista)']);
    exit;
}

try {
    $stmt = $conexion->prepare("
        SELECT DISTINCT vereda
        FROM ubicaciones
        WHERE departamento = :depto
          AND municipio    = :muni
        ORDER BY vereda ASC
    ");
    $stmt->bindParam(':depto', $departamento);
    $stmt->bindParam(':muni',  $municipio);
    $stmt->execute();

    $veredas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $veredas = array_values(array_filter($veredas, function ($v) {
        return !empty(trim($v));
    }));
    $veredas[] = 'Otro (No está en la lista)';

    echo json_encode($veredas, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['Otro (No está en la lista)']);
}
