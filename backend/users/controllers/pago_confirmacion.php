<?php
session_start();
require_once __DIR__ . "/../config/database.php";

// Verificar que el usuario esté logueado
if (!isset($_SESSION["id_usuario"])) {
    header("Location: /ascc/frontend/users/views/auth/login.php");
    exit;
}

$referencia = $_GET['ref'] ?? '';
$id_producto = $_GET['producto'] ?? 0;
$cantidad = $_GET['cantidad'] ?? 0;

// Buscar la transacción en la base de datos
$stmt = $conexion->prepare("SELECT * FROM transacciones WHERE referencia = :ref");
$stmt->bindParam(":ref", $referencia);
$stmt->execute();
$transaccion = $stmt->fetch(PDO::FETCH_ASSOC);

$estado_pago = $transaccion['estado'] ?? 'PENDIENTE';
$total = $transaccion['total'] ?? 0;

// Obtener información del producto
$stmt = $conexion->prepare("
    SELECT 
        p.*,
        u.lat,
        u.lng,
        u.departamento,
        u.municipio,
        u.vereda,
        usr.nombre as vendedor_nombre,
        usr.email as vendedor_email,
        usr.telefono as vendedor_telefono
    FROM productos p
    INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
    INNER JOIN usuarios usr ON p.id_usuario = usr.id_usuario
    WHERE p.id_producto = :id
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
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pago - ASCC</title>

    <!-- FAVICON -->
    <link rel="icon" type="image/png" href="/ascc/frontend/users/public/img/logo.png">

    <link rel="stylesheet" href="/ascc/frontend/users/public/css/dashboard.css">
    <style>
    body {
        background: #f5f5f5;
    }

    .confirmation-container {
        max-width: 700px;
        margin: 50px auto;
        padding: 20px;
    }

    .confirmation-card {
        background: white;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .status-icon {
        font-size: 80px;
        margin-bottom: 20px;
    }

    .status-pending {
        color: #ff9800;
        animation: pulse 1.5s infinite;
    }

    .status-approved {
        color: #4caf50;
        animation: bounce 0.5s;
    }

    .status-rejected {
        color: #f44336;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    @keyframes bounce {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-20px);
        }
    }

    .confirmation-title {
        font-size: 28px;
        font-weight: bold;
        color: #333;
        margin-bottom: 15px;
    }

    .confirmation-message {
        font-size: 16px;
        color: #666;
        margin-bottom: 30px;
        line-height: 1.6;
    }

    .transaction-details {
        background: #f9f9f9;
        padding: 25px;
        border-radius: 8px;
        margin: 30px 0;
        text-align: left;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #e0e0e0;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-label {
        font-weight: 600;
        color: #666;
    }

    .detail-value {
        color: #333;
        text-align: right;
    }

    .action-buttons {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }

    .btn {
        flex: 1;
        padding: 15px 25px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }

    .btn-primary {
        background: #2e7d32;
        color: white;
    }

    .btn-primary:hover {
        background: #1b5e20;
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: #757575;
        color: white;
    }

    .btn-secondary:hover {
        background: #424242;
        transform: translateY(-2px);
    }

    .loading-spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #2e7d32;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
        margin: 20px auto;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .info-box {
        background: #e3f2fd;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #1976d2;
        margin-top: 25px;
        text-align: left;
    }

    .info-box p {
        margin: 8px 0;
        color: #1565c0;
        font-size: 14px;
    }

    .vendor-contact {
        background: #f0f8f0;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
        text-align: left;
    }

    .vendor-contact h3 {
        color: #2e7d32;
        margin-bottom: 15px;
    }

    .map-container {
        width: 100%;
        height: 300px;
        border-radius: 8px;
        margin: 20px 0;
        border: 2px solid #e0e0e0;
    }
    </style>
</head>

<body>
    <div class="header">
        <div class="logo" style="display: flex; align-items: center; gap: 12px;">
            <img src="/ascc/frontend/users/public/img/logo.png" alt="ASCC Logo" style="height: 45px;">
            <span style="font-size: 24px; font-weight: bold; color: #2e7d32;">ASCC</span>
        </div>
        <div class="user-info">
            <span class="user-name">👤 <?= htmlspecialchars($_SESSION['nombre']) ?></span>
            <a href="/ascc/frontend/users/views/dashboard.php" class="btn-logout">Dashboard</a>
        </div>
    </div>

    <div class="confirmation-container">
        <div class="confirmation-card">
            <?php if ($estado_pago === 'APPROVED'): ?>
            <!-- PAGO APROBADO -->
            <div class="status-icon status-approved">✅</div>
            <h1 class="confirmation-title">¡Pago Aprobado!</h1>
            <p class="confirmation-message">
                Tu pago ha sido procesado exitosamente.<br>
                El vendedor ha sido notificado y se pondrá en contacto contigo.
            </p>
            <?php elseif ($estado_pago === 'REJECTED'): ?>
            <!-- PAGO RECHAZADO -->
            <div class="status-icon status-rejected">❌</div>
            <h1 class="confirmation-title">Pago Rechazado</h1>
            <p class="confirmation-message">
                Lo sentimos, tu pago no pudo ser procesado.<br>
                Por favor intenta nuevamente con otro método de pago.
            </p>
            <?php else: ?>
            <!-- PAGO PENDIENTE -->
            <div class="status-icon status-pending">⏳</div>
            <h1 class="confirmation-title">Procesando Pago...</h1>
            <p class="confirmation-message">
                Estamos verificando tu pago con Wompi.<br>
                Esto puede tardar unos segundos.
            </p>
            <div class="loading-spinner"></div>
            <?php endif; ?>

            <div class="transaction-details">
                <h3 style="margin-bottom: 15px; color: #333;">📋 Detalles de la Transacción</h3>

                <div class="detail-row">
                    <span class="detail-label">Referencia:</span>
                    <span class="detail-value"><strong><?= htmlspecialchars($referencia) ?></strong></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Estado:</span>
                    <span class="detail-value">
                        <?php
                        if ($estado_pago === 'APPROVED') {
                            echo '<span style="color: #4caf50; font-weight: bold;">✅ APROBADO</span>';
                        } elseif ($estado_pago === 'REJECTED') {
                            echo '<span style="color: #f44336; font-weight: bold;">❌ RECHAZADO</span>';
                        } else {
                            echo '<span style="color: #ff9800; font-weight: bold;">⏳ PENDIENTE</span>';
                        }
                        ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Producto:</span>
                    <span class="detail-value"><?= htmlspecialchars($producto['tipo_producto']) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Cantidad:</span>
                    <span class="detail-value"><?= $cantidad ?> <?= htmlspecialchars($producto['unidad']) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Vendedor:</span>
                    <span class="detail-value"><?= htmlspecialchars($producto['vendedor_nombre']) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Total Pagado:</span>
                    <span class="detail-value" style="font-size: 20px; color: #2e7d32; font-weight: bold;">
                        $<?= number_format($total, 0, ",", ".") ?> COP
                    </span>
                </div>
            </div>

            <?php if ($estado_pago === 'APPROVED'): ?>
            <!-- INFORMACIÓN DE CONTACTO DEL VENDEDOR -->
            <div class="vendor-contact">
                <h3>📞 Contactar al Vendedor</h3>
                <p style="margin-bottom: 15px; color: #666;">
                    Coordina la entrega directamente con el vendedor:
                </p>
                <p style="margin: 8px 0;">
                    <strong>📱 WhatsApp:</strong>
                    <a href="https://wa.me/57<?= preg_replace('/[^0-9]/', '', $producto['vendedor_telefono']) ?>?text=Hola, acabo de realizar el pago con referencia <?= urlencode($referencia) ?>"
                        target="_blank" style="color: #25D366; text-decoration: none; font-weight: 600;">
                        Enviar mensaje
                    </a>
                </p>
                <p style="margin: 8px 0;">
                    <strong>📧 Email:</strong> <?= htmlspecialchars($producto['vendedor_email']) ?>
                </p>
                <p style="margin: 8px 0;">
                    <strong>📞 Teléfono:</strong> <?= htmlspecialchars($producto['vendedor_telefono']) ?>
                </p>
            </div>

            <!-- MAPA CON UBICACIÓN DEL VENDEDOR -->
            <?php if ($producto['lat'] && $producto['lng']): ?>
            <div style="margin-top: 20px;">
                <h3 style="color: #333; margin-bottom: 10px;">📍 Ubicación del Vendedor</h3>
                <div id="map" class="map-container"></div>
                <p style="margin-top: 10px; font-size: 13px; color: #666;">
                    <?= htmlspecialchars($producto['vereda']) ?>, <?= htmlspecialchars($producto['municipio']) ?>,
                    <?= htmlspecialchars($producto['departamento']) ?>
                </p>
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $producto['lat'] ?>,<?= $producto['lng'] ?>"
                    target="_blank"
                    style="display: inline-block; margin-top: 10px; padding: 10px 20px; background: #4285F4; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">
                    🗺️ Abrir en Google Maps
                </a>
            </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="/ascc/frontend/users/views/catalogo.php" class="btn btn-secondary">🛒 Ver más productos</a>
                <a href="/ascc/frontend/users/views/dashboard.php" class="btn btn-primary">📊 Ir al Dashboard</a>
            </div>
            <?php elseif ($estado_pago === 'REJECTED'): ?>
            <div class="action-buttons">
                <a href="/ascc/producto_detalle.php?id=<?= $id_producto ?>" class="btn btn-secondary">🔄 Intentar de
                    nuevo</a>
                <a href="/ascc/frontend/users/views/catalogo.php" class="btn btn-primary">🛒 Ver otros productos</a>
            </div>
            <?php endif; ?>

            <div class="info-box">
                <p><strong>📧 Confirmación por Email</strong></p>
                <p>Hemos enviado los detalles de tu compra a:
                    <strong><?= htmlspecialchars($comprador['email']) ?></strong>
                </p>
                <p>Guarda el número de referencia: <strong><?= htmlspecialchars($referencia) ?></strong></p>
            </div>
        </div>
    </div>

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDfQiFq34PJh6XvksXGxvkpMi3badLWEQc"></script>

    <script>
    <?php if ($estado_pago === 'APPROVED' && $producto['lat'] && $producto['lng']): ?>
    // Inicializar mapa con ubicación del vendedor
    function initMap() {
        const vendorLocation = {
            lat: <?= $producto['lat'] ?>,
            lng: <?= $producto['lng'] ?>
        };

        const map = new google.maps.Map(document.getElementById('map'), {
            zoom: 14,
            center: vendorLocation
        });

        const marker = new google.maps.Marker({
            position: vendorLocation,
            map: map,
            title: 'Ubicación del vendedor',
            icon: {
                url: 'http://maps.google.com/mapfiles/ms/icons/green-dot.png'
            }
        });

        const infoWindow = new google.maps.InfoWindow({
            content: '<div style="padding: 10px;"><strong><?= htmlspecialchars($producto['vendedor_nombre']) ?></strong><br><?= htmlspecialchars($producto['vereda']) ?>, <?= htmlspecialchars($producto['municipio']) ?></div>'
        });

        marker.addListener('click', () => {
            infoWindow.open(map, marker);
        });
    }

    initMap();
    <?php endif; ?>

    <?php if ($estado_pago === 'PENDIENTE'): ?>
    // Verificar estado del pago cada 3 segundos
    let intentos = 0;
    const maxIntentos = 20; // 1 minuto

    function verificarEstadoPago() {
        if (intentos >= maxIntentos) {
            location.reload();
            return;
        }

        fetch('/ascc/api/verificar_estado_pago.php?ref=<?= urlencode($referencia) ?>')
            .then(response => response.json())
            .then(data => {
                if (data.estado === 'APPROVED' || data.estado === 'REJECTED') {
                    location.reload();
                } else {
                    intentos++;
                    setTimeout(verificarEstadoPago, 3000);
                }
            })
            .catch(() => {
                intentos++;
                setTimeout(verificarEstadoPago, 3000);
            });
    }

    setTimeout(verificarEstadoPago, 3000);
    <?php endif; ?>
    </script>
</body>

</html>