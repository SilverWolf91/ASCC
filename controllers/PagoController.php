<?php
/**
 * Controlador de Pagos (Webhook Mercado Pago)
 */

session_start();
require_once __DIR__ . "/../config/database.php";

// Permitir recibir JSON de Mercado Pago
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data && isset($data['type']) && $data['type'] === 'payment') {
    // Es una notificación de pago
    $payment_id = $data['data']['id'];
    
    // Aquí deberíamos consultar la API de Mercado Pago con el payment_id para obtener detalles
    // Pero como es un marketplace, necesitamos el access_token del vendedor correspondiente
    // Para simplificar, confiamos en el `pago_confirmacion.php` que maneja el redireccionamiento
    
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

// Si no es un webhook de MP
http_response_code(200);
echo "Webhook ASCC";
?>