<?php
session_start();
require_once __DIR__ . "/../../../backend/users/config/app.php";
require_once __DIR__ . "/../../../backend/users/config/database.php";

// Verificar que el usuario esté logueado
if (!isset($_SESSION["id_usuario"])) {
    header("Location: /ascc/frontend/users/views/auth/login.php");
    exit;
}

$referencia = $_GET['ref'] ?? '';
$id_producto = $_GET['producto'] ?? 0;
$cantidad = $_GET['cantidad'] ?? 0;
$total = $_GET['total'] ?? 0;

// Obtener información del producto
$stmt = $conexion->prepare("
    SELECT 
        p.*,
        usr.nombre as vendedor_nombre,
        usr.email as vendedor_email,
        usr.telefono as vendedor_telefono
    FROM productos p
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

// En un sistema real, aquí verificarías el estado del pago con PSE
// Por ahora simulamos que el pago está pendiente
$estado_pago = "PENDIENTE"; // APROBADO, RECHAZADO, PENDIENTE

// Simular aprobación automática después de 3 segundos (solo para desarrollo)
$auto_aprobar = true;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pago - <?= t('app_name') ?></title>

    <link rel="icon" type="image/png" href="/ascc/frontend/users/public/img/logo.png">
    <link rel="stylesheet" href="/ascc/frontend/users/public/css/ascc-theme.css">
    <link rel="stylesheet" href="/ascc/frontend/users/public/css/dashboard.css">
    <link rel="stylesheet" href="/ascc/frontend/users/public/css/pago-confirmacion.css">
</head>

<body class="theme-<?= $theme ?>" data-theme="<?= $theme ?>"
    data-auto-aprobar="<?= $auto_aprobar ? 'true' : 'false' ?>">
    <div class="header">
        <div class="logo" style="display: flex; align-items: center; gap: 12px;">
            <img src="/ascc/frontend/users/public/img/logo.png" alt="<?= t('app_name') ?> Logo" style="height: 45px;">
            <span style="font-size: 24px; font-weight: bold; color: #2e7d32;"><?= t('app_name') ?></span>
        </div>
        <div class="user-info">
            <span class="user-name">👤 <?= htmlspecialchars($_SESSION['nombre']) ?></span>
            <a href="/ascc/frontend/users/views/dashboard.php" class="btn-logout">Dashboard</a>
        </div>
    </div>

    <div class="confirmation-container">
        <div class="confirmation-card">
            <div id="statusContent">
                <!-- ESTADO PENDIENTE (inicial) -->
                <div class="status-icon status-pending">⏳</div>
                <h1 class="confirmation-title">Procesando Pago...</h1>
                <p class="confirmation-message">
                    Estamos verificando tu pago con el banco.<br>
                    Esto puede tardar unos segundos.
                </p>
                <div class="loading-spinner"></div>
            </div>

            <div class="transaction-details">
                <h3 style="margin-bottom: 15px; color: #333;">📋 Detalles de la Transacción</h3>

                <div class="detail-row">
                    <span class="detail-label">Referencia:</span>
                    <span class="detail-value"><strong><?= htmlspecialchars($referencia) ?></strong></span>
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

            <div id="actionButtons" style="display: none;">
                <div class="action-buttons">
                    <a href="/ascc/frontend/users/views/catalogo.php" class="btn btn-secondary">🛒 Ver más productos</a>
                    <a href="/ascc/frontend/users/views/dashboard.php" class="btn btn-primary">📊 Ir al Dashboard</a>
                </div>
            </div>

            <div id="vendorContact" style="display: none;">
                <div class="vendor-contact">
                    <h3>📞 Contactar al Vendedor</h3>
                    <p style="margin-bottom: 15px; color: #666;">
                        Coordina la entrega directamente con el vendedor:
                    </p>
                    <p>
                        <strong>📱 WhatsApp:</strong>
                        <a href="https://wa.me/57<?= preg_replace('/[^0-9]/', '', $producto['vendedor_telefono']) ?>?text=Hola, acabo de realizar el pago con referencia <?= urlencode($referencia) ?>"
                            target="_blank">
                            Enviar mensaje
                        </a>
                    </p>
                    <p>
                        <strong>📧 Email:</strong> <?= htmlspecialchars($producto['vendedor_email']) ?>
                    </p>
                    <p>
                        <strong>📞 Teléfono:</strong> <?= htmlspecialchars($producto['vendedor_telefono']) ?>
                    </p>
                </div>
            </div>

            <div class="info-box">
                <p><strong>📧 Confirmación por Email</strong></p>
                <p>Hemos enviado los detalles de tu compra a:
                    <strong><?= htmlspecialchars($comprador['email']) ?></strong>
                </p>
                <p>Guarda el número de referencia: <strong><?= htmlspecialchars($referencia) ?></strong></p>
            </div>
        </div>
    </div>

    <script src="/ascc/frontend/users/public/js/pago-confirmacion.js"></script>
</body>

</html>