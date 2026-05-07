<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();
require_once __DIR__ . '/../config/database.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$departamento = trim($_GET['departamento'] ?? '');
$municipio    = trim($_GET['municipio']    ?? '');
$vereda       = trim($_GET['vereda']       ?? '');

if (empty($departamento)) {
    echo json_encode(['lat' => null, 'lng' => null]);
    exit;
}

try {
    /* Buscar coordenadas del nivel más específico disponible */
    if (!empty($vereda) && $vereda !== 'Otro (No está en la lista)' && !empty($municipio)) {
        $stmt = $conexion->prepare("
            SELECT lat, lng FROM ubicaciones
            WHERE departamento = :depto AND municipio = :muni AND vereda = :vereda
              AND lat IS NOT NULL AND lng IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([':depto' => $departamento, ':muni' => $municipio, ':vereda' => $vereda]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { echo json_encode($row); exit; }
    }

    if (!empty($municipio) && $municipio !== 'Otro (No aparece en la lista)') {
        $stmt = $conexion->prepare("
            SELECT lat, lng FROM ubicaciones
            WHERE departamento = :depto AND municipio = :muni
              AND lat IS NOT NULL AND lng IS NOT NULL
            ORDER BY id_ubicacion ASC LIMIT 1
        ");
        $stmt->execute([':depto' => $departamento, ':muni' => $municipio]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { echo json_encode($row); exit; }
    }

    $stmt = $conexion->prepare("
        SELECT lat, lng FROM ubicaciones
        WHERE departamento = :depto AND lat IS NOT NULL AND lng IS NOT NULL
        ORDER BY id_ubicacion ASC LIMIT 1
    ");
    $stmt->execute([':depto' => $departamento]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($row ?: ['lat' => null, 'lng' => null]);

} catch (Exception $e) {
    echo json_encode(['lat' => null, 'lng' => null]);
}
