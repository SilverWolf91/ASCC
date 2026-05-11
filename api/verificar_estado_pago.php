<?php
session_start();
require_once __DIR__ . "/../config/database.php";

header('Content-Type: application/json; charset=utf-8');

$referencia = $_GET['ref'] ?? '';

if (empty($referencia)) {
    echo json_encode(['error' => 'Referencia no proporcionada']);
    exit;
}

// Buscar transacción en base de datos
$stmt = $conexion->prepare("SELECT estado FROM transacciones WHERE referencia = :ref");
$stmt->bindParam(":ref", $referencia);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $transaccion = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['estado' => $transaccion['estado']]);
} else {
    // Si no existe en BD, verificar con API de Wompi

    // CONFIGURACIÓN WOMPI
    $WOMPI_PRIVATE_KEY = "prv_test_as45sd78hjk9lqw34ert56yui8op90"; // Cambiar en producción

    // Consultar estado en Wompi
    $url = "https://sandbox.wompi.co/v1/transactions?reference=" . urlencode($referencia);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $WOMPI_PRIVATE_KEY
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);

        if (isset($data['data']) && count($data['data']) > 0) {
            $transaction = $data['data'][0];
            echo json_encode(['estado' => $transaction['status']]);
        } else {
            echo json_encode(['estado' => 'PENDIENTE']);
        }
    } else {
        echo json_encode(['estado' => 'PENDIENTE']);
    }
}
