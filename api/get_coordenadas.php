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

$VEREDA_OTRO    = 'Otro (No está en la lista)';
$MUNICIPIO_OTRO = 'Otro (No aparece en la lista)';

try {
    /* 1. NIVEL VEREDA — buscar coordenadas exactas de la vereda */
    if (!empty($vereda) && $vereda !== $VEREDA_OTRO && !empty($municipio) && $municipio !== $MUNICIPIO_OTRO) {
        $stmt = $conexion->prepare("
            SELECT lat, lng FROM ubicaciones
            WHERE departamento = :depto AND municipio = :muni AND vereda = :vereda
              AND lat IS NOT NULL AND lng IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([':depto' => $departamento, ':muni' => $municipio, ':vereda' => $vereda]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode($row);
            exit;
        }
        /* La vereda específica no tiene coords → devolver null para que
           el frontend caiga a Google Geocoder con el nombre real
           (más preciso que un fallback arbitrario de la BD). */
        echo json_encode(['lat' => null, 'lng' => null]);
        exit;
    }

    /* 2. NIVEL MUNICIPIO — buscar el centro del municipio, priorizando vereda='Centro' */
    if (!empty($municipio) && $municipio !== $MUNICIPIO_OTRO) {
        $stmt = $conexion->prepare("
            SELECT lat, lng FROM ubicaciones
            WHERE departamento = :depto AND municipio = :muni
              AND lat IS NOT NULL AND lng IS NOT NULL
            ORDER BY (vereda = 'Centro') DESC, id_ubicacion ASC
            LIMIT 1
        ");
        $stmt->execute([':depto' => $departamento, ':muni' => $municipio]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode($row);
            exit;
        }
        /* Sin coords para ese municipio → devolver null para que el frontend
           use Google Geocoder con el nombre del municipio. */
        echo json_encode(['lat' => null, 'lng' => null]);
        exit;
    }

    /* 3. SOLO DEPARTAMENTO — devolver null para que el frontend use
       _DEPT_COORDS (capital del departamento, dato curado y confiable). */
    echo json_encode(['lat' => null, 'lng' => null]);

} catch (Exception $e) {
    echo json_encode(['lat' => null, 'lng' => null]);
}
