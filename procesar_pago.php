<?php
session_start();
require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/config/database.php";

// Verificar que el usuario esté logueado
if (!isset($_SESSION["id_usuario"])) {
    header("Location: /ascc/views/auth/login.php");
    exit;
}

$id_producto = $_GET['producto'] ?? 0;
$cantidad = $_GET['cantidad'] ?? 0;
$total = $_GET['total'] ?? 0;

// Obtener información del producto
$stmt = $conexion->prepare("
    SELECT 
        p.*,
        u.departamento,
        u.municipio,
        u.vereda,
        u.lat,
        u.lng,
        usr.nombre as vendedor_nombre,
        usr.email as vendedor_email,
        usr.mp_access_token,
        usr.mp_public_key
    FROM productos p
    INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
    INNER JOIN usuarios usr ON p.id_usuario = usr.id_usuario
    WHERE p.id_producto = :id AND p.estado = 'disponible'
");
$stmt->bindParam(":id", $id_producto);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header("Location: /ascc/catalogo.php");
    exit;
}

$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if ($producto['id_usuario'] == $_SESSION['id_usuario']) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h2>⚠️ Error: No puedes comprar tu propio producto.</h2><p>Mercado Pago bloquea automáticamente las transacciones donde el comprador y el vendedor son la misma persona.</p><p><a href='/ascc/catalogo.php' style='color:#009ee3;'>Volver al catálogo</a></p></div>");
}

// Obtener información del comprador
$stmt = $conexion->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
$stmt->bindParam(":id", $_SESSION["id_usuario"]);
$stmt->execute();
$comprador = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular distancia entre comprador y vendedor (si ambos tienen coordenadas)
$distancia_km = 0;
$costo_envio = 0;

// Generar referencia única de pago
$referencia_pago = 'ASCC-' . time() . '-' . $id_producto;

// Verificar si el vendedor tiene configurado Mercado Pago
if (empty($producto['mp_access_token']) || empty($producto['mp_public_key'])) {
    die("<h2>Error: El vendedor aún no ha configurado su cuenta para recibir pagos.</h2><p><a href='/ascc/catalogo.php'>Volver al catálogo</a></p>");
}

// Crear preferencia de Mercado Pago vía cURL
$url_preference = "https://api.mercadopago.com/checkout/preferences";
$access_token = $producto['mp_access_token'];

$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$base_url = ($is_https ? "https" : "http") . "://$_SERVER[HTTP_HOST]/ascc";

$data_preference = [
    "items" => [
        [
            "id" => $id_producto,
            "title" => $producto['tipo_producto'] . " - " . ($producto['producto_especifico'] ?: 'ASCC'),
            "description" => "Compra en ASCC",
            "quantity" => (int)$cantidad,
            "unit_price" => (int)round($producto['precio'] + ($costo_envio / $cantidad)),
            "currency_id" => "COP"
        ]
    ],
    "back_urls" => [
        "success" => $base_url . "/controllers/pago_confirmacion.php?estado=success&ref=" . $referencia_pago,
        "failure" => $base_url . "/controllers/pago_confirmacion.php?estado=failure&ref=" . $referencia_pago,
        "pending" => $base_url . "/controllers/pago_confirmacion.php?estado=pending&ref=" . $referencia_pago
    ],
    "auto_return" => "approved",
    "external_reference" => $referencia_pago
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_preference);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_preference));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $access_token,
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$mp_response = json_decode($response, true);
$init_point = "";

if ($http_code == 200 || $http_code == 201) {
    $preference_id = $mp_response['id'];
    $public_key = $producto['mp_public_key'];
    
    // Fallback URL just in case
    if (strpos($access_token, 'TEST-') === 0) {
        $init_point = $mp_response['sandbox_init_point'];
    } else {
        $init_point = $mp_response['init_point'];
    }
} else {
    // Si hay error al crear la preferencia
    $error_msg = $mp_response['message'] ?? 'Error desconocido en Mercado Pago';
    die("<h2>Error al inicializar el pago: " . htmlspecialchars($error_msg) . "</h2><p><a href='/ascc/catalogo.php'>Volver al catálogo</a></p>");
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesar Pago - <?= t('app_name') ?></title>

    <link rel="icon" type="image/png" href="/ascc/public/img/logo.png">
    <link rel="stylesheet" href="/ascc/public/css/ascc-theme.css">
    <link rel="stylesheet" href="/ascc/public/css/dashboard.css">
    <link rel="stylesheet" href="/ascc/public/css/procesar-pago.css">

    <script src="https://sdk.mercadopago.com/js/v2"></script>
</head>

<body class="theme-<?= $theme ?>" data-theme="<?= $theme ?>">

    <!-- WIDGET DE TEMA E IDIOMA -->
    <?php include __DIR__ . '/partials/header.php'; ?>

    <div class="payment-container">
        <a href="/ascc/producto_detalle.php?id=<?= $id_producto ?>" class="back-link">← Volver al producto</a>

        <div class="payment-header">
            <h1>💳 Procesar Pago</h1>
            <p>Pago seguro con Mercado Pago - PSE, Tarjetas y Efectivo</p>
        </div>

        <div class="payment-card">
            <h2 style="margin-bottom: 20px;">📋 Resumen de Compra</h2>

            <div class="order-summary">
                <div class="summary-row">
                    <span>Producto:</span>
                    <strong><?= htmlspecialchars($producto['tipo_producto']) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Vendedor:</span>
                    <span><?= htmlspecialchars($producto['vendedor_nombre']) ?></span>
                </div>
                <div class="summary-row">
                    <span>Ubicación:</span>
                    <span><?= htmlspecialchars($producto['vereda']) ?>, <?= htmlspecialchars($producto['municipio']) ?>,
                        <?= htmlspecialchars($producto['departamento']) ?></span>
                </div>
                <div class="summary-row">
                    <span>Precio Unitario:</span>
                    <span>$<?= number_format($producto['precio'], 0, ",", ".") ?> COP</span>
                </div>
                <div class="summary-row">
                    <span>Cantidad:</span>
                    <span><?= $cantidad ?> <?= htmlspecialchars($producto['unidad']) ?></span>
                </div>

                <?php if ($distancia_km > 0): ?>
                <div class="summary-row">
                    <span>📍 Distancia estimada:</span>
                    <span><?= number_format($distancia_km, 1) ?> km</span>
                </div>
                <div class="summary-row">
                    <span>🚚 Costo de envío:</span>
                    <span>$<?= number_format($costo_envio, 0, ",", ".") ?> COP</span>
                </div>
                <?php endif; ?>

                <div class="summary-row">
                    <span>TOTAL A PAGAR:</span>
                    <span>$<?= number_format($total + $costo_envio, 0, ",", ".") ?> COP</span>
                </div>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <img src="https://http2.mlstatic.com/frontend-assets/ui-navigation/5.19.5/mercadopago/logo__large.png" alt="Mercado Pago" style="height: 40px; object-fit: contain;">
                <p style="color: #666; font-size: 14px; margin-top: 10px;">
                    Paga con el método de tu preferencia
                </p>
            </div>

            <!-- Íconos de pago removidos a petición del usuario, Mercado Pago lo maneja todo -->

            <!-- Botón oficial de Mercado Pago usando el SDK -->
            <div class="cho-container" style="text-align: center;"></div>
            
            <script>
                // Inicializar Mercado Pago
                const mp = new MercadoPago('<?= $public_key ?>', {
                    locale: 'es-CO'
                });

                // Renderizar el botón de pago
                mp.checkout({
                    preference: {
                        id: '<?= $preference_id ?>'
                    },
                    render: {
                        container: '.cho-container', // Indica dónde se mostrará el botón
                        label: '💳 Pagar con Mercado Pago - $<?= number_format($total + $costo_envio, 0, ",", ".") ?> COP', // Texto del botón
                    }
                });
            </script>
            
            <div style="text-align: center; margin-top: 15px;">
                <a href="<?= $init_point ?>" target="_top" style="font-size: 12px; color: #666; text-decoration: underline;">
                    Si el botón no carga, haz clic aquí
                </a>
            </div>

            <div class="security-badge">
                <p>
                    🔐 <strong>Pago 100% Seguro</strong><br>
                    Tu información está protegida con encriptación SSL<br>
                    Procesado por Mercado Pago - Pagos seguros certificados en Colombia
                </p>
            </div>

            <div class="warning-box">
                <p>
                    <strong>⚠️ Importante:</strong><br>
                    • Serás redirigido a la pasarela de Mercado Pago<br>
                    • Puedes pagar con PSE, tarjetas de crédito/débito y efectivo<br>
                    • El vendedor recibirá el dinero directamente en su cuenta<br>
                    • Conserva el comprobante que te llegará por email
                </p>
            </div>
        </div>
    </div>


</body>

</html>