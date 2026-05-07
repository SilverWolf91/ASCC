<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();
require_once __DIR__ . '/../config/database.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$departamento = isset($_GET['departamento']) ? trim($_GET['departamento']) : '';

if (empty($departamento)) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conexion->prepare("
        SELECT DISTINCT municipio
        FROM ubicaciones
        WHERE departamento = :depto
        ORDER BY municipio ASC
    ");
    $stmt->bindParam(':depto', $departamento);
    $stmt->execute();
    $municipios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $municipios[] = 'Otro (No aparece en la lista)';
    echo json_encode($municipios, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['Otro (No aparece en la lista)']);
}
