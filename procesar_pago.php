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
        usr.email as vendedor_email
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

// CONFIGURACIÓN WOMPI
// IMPORTANTE: Cambia estas keys cuando te registres en Wompi
$WOMPI_PUBLIC_KEY = "pub_test_Q5jHHMYFUOMJUtYWGCTyKqNO7cMd4RCp"; // Llave de prueba
$WOMPI_PRIVATE_KEY = "prv_test_as45sd78hjk9lqw34ert56yui8op90"; // Llave de prueba

// En producción usa:
// $WOMPI_PUBLIC_KEY = "pub_prod_TU_LLAVE_PUBLICA";
// $WOMPI_PRIVATE_KEY = "prv_prod_TU_LLAVE_PRIVADA";
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

    <!-- WOMPI SDK -->
    <script src="https://checkout.wompi.co/widget.js"></script>
</head>

<body data-wompi-public-key="<?= htmlspecialchars($WOMPI_PUBLIC_KEY) ?>"
    data-comprador-email="<?= htmlspecialchars($comprador['email']) ?>"
    data-comprador-nombre="<?= htmlspecialchars($comprador['nombre']) ?>"
    data-comprador-telefono="<?= preg_replace('/[^0-9]/', '', $comprador['telefono']) ?>"
    data-comprador-cedula="<?= htmlspecialchars($comprador['cedula']) ?>"
    data-producto-municipio="<?= htmlspecialchars($producto['municipio']) ?>"
    data-producto-departamento="<?= htmlspecialchars($producto['departamento']) ?>">

    <div class="header">
        <div class="logo" style="display: flex; align-items: center; gap: 12px;">
            <img src="/ascc/public/img/logo.png" alt="<?= t('app_name') ?> Logo" style="height: 45px;">
            <span style="font-size: 24px; font-weight: bold; color: #2e7d32;"><?= t('app_name') ?></span>
        </div>
        <div class="user-info">
            <span class="user-name">👤 <?= htmlspecialchars($_SESSION['nombre']) ?></span>
            <a href="/ascc/dashboard.php" class="btn-logout">Dashboard</a>
        </div>
    </div>

    <div class="payment-container">
        <a href="/ascc/producto_detalle.php?id=<?= $id_producto ?>" class="back-link">← Volver al producto</a>

        <div class="payment-header">
            <h1>💳 Procesar Pago</h1>
            <p>Pago seguro con Wompi - PSE, Tarjetas y más</p>
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
                <img src="https://wompi.co/wp-content/uploads/2021/03/logo-wompi.svg" alt="Wompi" class="wompi-logo">
                <p style="color: #666; font-size: 14px; margin-top: 10px;">
                    Paga con el método de tu preferencia
                </p>
            </div>

            <div class="payment-methods">
                <div class="payment-icon">💳 PSE</div>
                <div class="payment-icon">💳 Tarjetas</div>
                <div class="payment-icon">💰 Nequi</div>
                <div class="payment-icon">💰 Daviplata</div>
                <div class="payment-icon">🏪 Corresponsales</div>
            </div>

            <form id="wompiForm">
                <input type="hidden" id="referencia" value="<?= $referencia_pago ?>">
                <input type="hidden" id="total" value="<?= ($total + $costo_envio) * 100 ?>">
                <input type="hidden" id="producto_id" value="<?= $id_producto ?>">
                <input type="hidden" id="cantidad" value="<?= $cantidad ?>">

                <button type="button" class="btn-pay-wompi" onclick="pagarConWompi()">
                    💳 Pagar con Wompi - $<?= number_format($total + $costo_envio, 0, ",", ".") ?> COP
                </button>
            </form>

            <div class="security-badge">
                <p>
                    🔐 <strong>Pago 100% Seguro</strong><br>
                    Tu información está protegida con encriptación SSL<br>
                    Procesado por Wompi - Pagos seguros certificados en Colombia
                </p>
            </div>

            <div class="warning-box">
                <p>
                    <strong>⚠️ Importante:</strong><br>
                    • Serás redirigido a la pasarela de pago de Wompi<br>
                    • Puedes pagar con PSE, tarjetas de crédito/débito, Nequi, Daviplata<br>
                    • El vendedor será notificado cuando se confirme el pago<br>
                    • Conserva el comprobante que te llegará por email
                </p>
            </div>
        </div>
    </div>

    <script src="/ascc/public/js/procesar-pago.js"></script>
</body>

</html>