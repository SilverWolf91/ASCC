<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - Aromas y Sabores de mi Campo Colombiano - DETALLE DE PRODUCTO MEJORADO
 * Con soporte de tema oscuro/claro, idioma ES/EN y mensajería
 * ═══════════════════════════════════════════════════════════
 */

// Prevenir caché del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Cargar configuración global (tema, idioma, traducciones)
require_once __DIR__ . "/../../../backend/users/config/app.php";
require_once __DIR__ . "/../../../backend/users/config/database.php";

$id_producto = $_GET['id'] ?? 0;

// Obtener detalles completos del producto
$stmt = $conexion->prepare("
    SELECT
        p.*,
        u.departamento,
        u.municipio,
        u.vereda,
        u.lat,
        u.lng,
        usr.nombre as vendedor_nombre,
        usr.telefono as vendedor_telefono,
        usr.email as vendedor_email,
        usr.id_usuario as vendedor_id
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

// Verificar si el producto es del usuario actual
$es_mi_producto   = false;
$usuario_logueado = false;
if (isset($_SESSION['id_usuario'])) {
    $usuario_logueado = true;
    if ($_SESSION['id_usuario'] == $producto['vendedor_id']) {
        $es_mi_producto = true;
    }
}

// ── TRACKING DE VISITAS ───────────────────────────────────────
// Solo registrar si:
//   1. No es el dueño del producto
//   2. El producto existe (ya lo verificamos arriba)
if (!$es_mi_producto) {
    registrarVisitaProducto(
        $conexion,
        (int)$producto['id_producto'],
        isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null,
        $_GET['origen'] ?? 'directo'
    );
}

/**
 * Registra una visita al producto evitando duplicados por sesión.
 * Si el mismo session_id ya visitó este producto en los últimos 30 min,
 * no se inserta un nuevo registro.
 */
function registrarVisitaProducto(
    PDO    $pdo,
    int    $id_producto,
    ?int   $id_visitante,
    string $origen = 'directo'
): void {
    $sesion_id   = session_id();
    $ip          = $_SERVER['REMOTE_ADDR'] ?? '';

    // Orígenes válidos — whitelist
    $origenes_validos = ['catalogo', 'busqueda', 'perfil', 'directo'];
    if (!in_array($origen, $origenes_validos, true)) {
        $origen = 'directo';
    }

    // Evitar contar recargas — mismo session_id en menos de 5 minutos no cuenta
    try {
        $check = $pdo->prepare("
            SELECT id FROM visitas_producto
            WHERE id_producto = :prod
              AND sesion_id   = :sesion
              AND fecha_visita > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            LIMIT 1
        ");
        $check->execute([
            ':prod'   => $id_producto,
            ':sesion' => $sesion_id,
        ]);

        if ($check->rowCount() > 0) {
            return; // Ya registrada en los últimos 30 min
        }

        // Insertar visita nueva
        $pdo->prepare("
            INSERT INTO visitas_producto
                (id_producto, id_visitante, ip_visitante, sesion_id, origen)
            VALUES
                (:prod, :visitante, :ip, :sesion, :origen)
        ")->execute([
            ':prod'      => $id_producto,
            ':visitante' => $id_visitante,
            ':ip'        => $ip,
            ':sesion'    => $sesion_id,
            ':origen'    => $origen,
        ]);

    } catch (PDOException $e) {
        // No interrumpir la carga de la página por un error de tracking
        error_log('ASCC tracking visita producto: ' . $e->getMessage());
    }
}
// ── FIN TRACKING ──────────────────────────────────────────────

// Obtener todas las imágenes del producto
$stmt = $conexion->prepare("SELECT ruta_imagen FROM imagenes_productos WHERE id_producto = :id");
$stmt->bindParam(":id", $id_producto);
$stmt->execute();
$imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calificación promedio del vendedor
$calificacion_vendedor = 5.0;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($producto['tipo_producto']) ?> - <?= t('app_name') ?></title>

    <link rel="icon" type="image/png" href="/ascc/frontend/users/public/img/logo.png">

    <!-- CSS dinámico según tema -->
    <?= ascc_theme_css() ?>

    <link rel="stylesheet" href="/ascc/frontend/users/public/css/producto-detalle.css?v=<?= time() ?>">

    <!-- CSS Módulo de Reseñas -->
    <link rel="stylesheet"
        href="/ascc/frontend/users/public/css/reviews.css?v=<?= filemtime(__DIR__ . '/public/css/reviews.css') ?>">

    <!-- i18n para JS del módulo de reseñas -->
    <script>
    window.RV_I18N = <?= json_encode([
        'reviews_hint_1'             => t('reviews_hint_1'),
        'reviews_hint_2'             => t('reviews_hint_2'),
        'reviews_hint_3'             => t('reviews_hint_3'),
        'reviews_hint_4'             => t('reviews_hint_4'),
        'reviews_hint_5'             => t('reviews_hint_5'),
        'reviews_sin_resenas'        => t('reviews_sin_resenas'),
        'reviews_una'                => t('reviews_una'),
        'reviews_varias'             => t('reviews_varias'),
        'reviews_de_5'               => t('reviews_de_5'),
        'reviews_distribucion'       => t('reviews_distribucion'),
        'reviews_vacio'              => t('reviews_vacio'),
        'reviews_ya_resenado'        => t('reviews_ya_resenado'),
        'reviews_exito'              => t('reviews_exito'),
        'reviews_estrellas'          => t('reviews_estrellas'),
        'reviews_eliminar'           => t('reviews_eliminar'),
        'reviews_confirmar_eliminar' => t('reviews_confirmar_eliminar'),
        'reviews_err_sin_estrella'   => t('reviews_err_sin_estrella'),
        'reviews_err_sin_comentario' => t('reviews_err_sin_comentario'),
        'reviews_err_ya_resenado'    => t('reviews_err_ya_resenado'),
        'reviews_err_auto_resena'    => t('reviews_err_auto_resena'),
        'reviews_err_generico'       => t('reviews_err_generico'),
        'reviews_err_red'            => t('reviews_err_red'),
        'reviews_error_cargar'       => t('reviews_error_cargar'),
        'reviews_cargar_mas'         => t('reviews_cargar_mas'),
    ], JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDfQiFq34PJh6XvksXGxvkpMi3badLWEQc"></script>

    <style>
    /* Botón ver perfil vendedor en producto_detalle */
    .btn-ver-perfil-vendedor {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 14px 0 4px;
        padding: 10px 18px;
        background: rgba(16, 185, 129, 0.08);
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-radius: 10px;
        color: #10b981;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
        width: fit-content;
    }

    .btn-ver-perfil-vendedor:hover {
        background: rgba(16, 185, 129, 0.15);
        border-color: #10b981;
        transform: translateY(-1px);
    }

    [data-theme="light"] .btn-ver-perfil-vendedor {
        background: #ecfdf5;
        border-color: #6ee7b7;
        color: #065f46;
    }

    [data-theme="light"] .btn-ver-perfil-vendedor:hover {
        background: #d1fae5;
        border-color: #059669;
    }
    </style>
</head>

<body class="theme-<?= $theme ?>" data-theme="<?= $theme ?>" data-precio-unitario="<?= $producto['precio'] ?>"
    data-cantidad-maxima="<?= $producto['cantidad'] ?>" data-id-producto="<?= $producto['id_producto'] ?>"
    data-producto-lat="<?= $producto['lat'] ?? 0 ?>" data-producto-lng="<?= $producto['lng'] ?? 0 ?>"
    data-producto-tipo="<?= htmlspecialchars($producto['tipo_producto']) ?>"
    data-producto-vereda="<?= htmlspecialchars($producto['vereda']) ?>"
    data-producto-municipio="<?= htmlspecialchars($producto['municipio']) ?>">

    <!-- WIDGET DE TEMA E IDIOMA -->
    <?php include __DIR__ . '/../../partials/header.php'; ?>

    <div class="detail-container">

        <a href="/ascc/frontend/users/views/catalogo.php" class="back-link">← <?= t('back_to_catalog') ?></a>

        <!-- ══ DETALLES DEL PRODUCTO ══════════════════════════ -->
        <div class="product-detail">

            <!-- CARRUSEL DE IMÁGENES -->
            <div class="image-gallery">
                <div class="carousel-container" id="carouselContainer">
                    <?php if (count($imagenes) > 0): ?>
                    <?php foreach ($imagenes as $index => $img): ?>
                    <img src="/ascc/frontend/users/public/<?= $img['ruta_imagen'] ?>"
                        alt="<?= htmlspecialchars($producto['tipo_producto']) ?> - Imagen <?= $index + 1 ?>"
                        class="carousel-image <?= $index === 0 ? 'active' : '' ?>"
                        onerror="this.src='/ascc/frontend/users/public/img/no-image.png'">
                    <?php endforeach; ?>

                    <?php if (count($imagenes) > 1): ?>
                    <button class="carousel-arrow left" onclick="changeSlide(-1)">‹</button>
                    <button class="carousel-arrow right" onclick="changeSlide(1)">›</button>

                    <div class="carousel-counter">
                        <span id="currentSlide">1</span> / <?= count($imagenes) ?>
                    </div>

                    <div class="carousel-indicators">
                        <?php for ($i = 0; $i < count($imagenes); $i++): ?>
                        <div class="indicator <?= $i === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $i ?>)"></div>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <img src="/ascc/frontend/users/public/img/no-image.png" alt="Sin imagen" class="carousel-image active">
                    <?php endif; ?>
                </div>
            </div>

            <!-- INFORMACIÓN DEL PRODUCTO -->
            <div class="product-info-detail">
                <h1 class="product-title-detail">🌾 <?= htmlspecialchars($producto['tipo_producto']) ?></h1>

                <div class="product-price-detail">
                    $<?= number_format($producto['precio'], 0, ",", ".") ?> COP
                    <span class="price-unit">/ <?= htmlspecialchars($producto['unidad']) ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">📦 <?= t('available') ?>:</span>
                    <span class="info-value">
                        <?= htmlspecialchars($producto['cantidad']) ?>
                        <?= htmlspecialchars($producto['unidad']) ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">📍 <?= t('product_location') ?>:</span>
                    <span class="info-value">
                        <?= htmlspecialchars($producto['vereda']) ?>,
                        <?= htmlspecialchars($producto['municipio']) ?>,
                        <?= htmlspecialchars($producto['departamento']) ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">📅 <?= t('published') ?>:</span>
                    <span class="info-value">
                        <?= date("d/m/Y", strtotime($producto['fecha_publicacion'])) ?>
                    </span>
                </div>

                <div class="description-box">
                    <h3>📝 <?= t('product_description') ?></h3>
                    <p class="description-text">
                        <?= nl2br(htmlspecialchars($producto['descripcion'])) ?>
                    </p>
                </div>

                <?php if (!$es_mi_producto && $usuario_logueado): ?>
                <!-- SISTEMA DE COMPRA -->
                <div class="purchase-section">
                    <h3 class="purchase-title">🛒 <?= t('purchase_section') ?></h3>

                    <div class="quantity-selector">
                        <span class="quantity-label"><?= t('quantity_label') ?>:</span>
                        <button type="button" class="quantity-btn" onclick="decreaseQuantity()">-</button>
                        <input type="number" id="quantity" class="quantity-input" value="1" min="1"
                            max="<?= $producto['cantidad'] ?>" onchange="updateTotal()">
                        <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                    </div>

                    <div id="shippingCost" class="shipping-cost-box" style="display: none;">
                        <strong>🚚 <?= t('shipping_cost') ?>:</strong>
                        <span class="shipping-price">$<span id="shippingPrice">0</span> COP</span>
                    </div>

                    <div class="total-price">
                        💰 <?= t('total_price') ?>: $<span
                            id="totalPrice"><?= number_format($producto['precio'], 0, ",", ".") ?></span> COP
                    </div>

                    <button type="button" class="btn-buy" onclick="procesarCompra()">
                        💳 <?= t('buy_now') ?>
                    </button>

                    <div class="payment-methods">
                        <div class="payment-icon">PSE</div>
                        <div class="payment-icon">VISA</div>
                        <div class="payment-icon">MASTERCARD</div>
                        <div class="payment-icon">NEQUI</div>
                        <div class="payment-icon">DAVIPLATA</div>
                    </div>

                    <p class="secure-payment-text">
                        🔒 <?= t('secure_payment') ?>
                    </p>
                </div>
                <?php elseif ($es_mi_producto): ?>
                <div class="owner-badge">
                    ⚠️ <?= t('your_product_warning') ?>
                </div>
                <?php endif; ?>

            </div><!-- /product-info-detail -->
        </div><!-- /product-detail -->

        <!-- ══ MAPA Y UBICACIÓN ═══════════════════════════════ -->
        <?php if ($producto['lat'] && $producto['lng']): ?>
        <div class="location-section">
            <h2>📍 <?= t('product_location') ?></h2>

            <?php if ($usuario_logueado && !$es_mi_producto): ?>
            <div class="distance-card">
                <h3>📏 <?= t('distance_info') ?></h3>
                <button type="button" class="btn-directions btn-calculate" onclick="calcularDistanciaYCosto()">
                    📍 <?= t('calculate_distance') ?>
                </button>

                <div id="distanceInfo" class="distance-info-container" style="display: none;">
                    <div class="distance-info">
                        <div class="distance-item">
                            <div class="distance-value" id="distanceValue">0</div>
                            <div class="distance-label"><?= t('kilometers') ?></div>
                        </div>
                        <div class="distance-item">
                            <div class="distance-value" id="timeValue">0</div>
                            <div class="distance-label"><?= t('minutes_approx') ?></div>
                        </div>
                        <div class="distance-item">
                            <div class="distance-value" id="costValue">$0</div>
                            <div class="distance-label"><?= t('shipping_cost') ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div id="productMap" class="product-map"></div>

            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $producto['lat'] ?>,<?= $producto['lng'] ?>"
                target="_blank" class="btn-directions">
                🗺️ <?= t('open_google_maps') ?>
            </a>
        </div><!-- /location-section -->
        <?php endif; ?>

        <!-- ══ INFORMACIÓN DEL VENDEDOR ══════════════════════ -->
        <div class="vendor-card">
            <h2 class="vendor-title">👤 <?= t('vendor_info') ?></h2>

            <div class="vendor-info">
                <div class="vendor-avatar">
                    <?= strtoupper(substr($producto['vendedor_nombre'], 0, 1)) ?>
                </div>
                <div class="vendor-details">
                    <div class="vendor-name">
                        <?= htmlspecialchars($producto['vendedor_nombre']) ?>
                    </div>
                    <div class="vendor-rating">⭐⭐⭐⭐⭐ <?= number_format($calificacion_vendedor, 1) ?></div>
                    <p class="vendor-verified">✅ <?= t('verified_vendor') ?></p>
                </div>
            </div>

            <?php if (!$es_mi_producto): ?>
            <!-- Botón ver perfil completo del vendedor -->
            <a href="/ascc/frontend/users/views/perfil_vendedor.php?id=<?= $producto['vendedor_id'] ?>" class="btn-ver-perfil-vendedor">
                👤 <?= t('vendor_info') ?> — <?= htmlspecialchars($producto['vendedor_nombre']) ?>
            </a>
            <?php endif; ?>

            <?php if (!$es_mi_producto && $usuario_logueado): ?>
            <h3 class="contact-title">📞 <?= t('contact_vendor') ?></h3>

            <div class="contact-buttons">
                <button type="button" class="btn-contact btn-message" onclick="abrirModalMensaje()"
                    data-vendedor-id="<?= $producto['vendedor_id'] ?>"
                    data-vendedor-nombre="<?= htmlspecialchars($producto['vendedor_nombre']) ?>"
                    data-producto-id="<?= $producto['id_producto'] ?>"
                    data-producto-nombre="<?= htmlspecialchars($producto['tipo_producto']) ?>">
                    💬 <?= t('internal_message') ?>
                </button>

                <a href="https://wa.me/57<?= preg_replace('/[^0-9]/', '', $producto['vendedor_telefono']) ?>?text=Hola, me interesa tu producto: <?= urlencode($producto['tipo_producto']) ?>"
                    target="_blank" class="btn-contact btn-whatsapp">
                    📱 <?= t('whatsapp') ?>
                </a>

                <a href="mailto:<?= htmlspecialchars($producto['vendedor_email']) ?>?subject=Consulta sobre <?= urlencode($producto['tipo_producto']) ?>"
                    class="btn-contact btn-email">
                    📧 <?= t('email') ?>
                </a>

                <a href="tel:<?= htmlspecialchars($producto['vendedor_telefono']) ?>" class="btn-contact btn-call">
                    📞 <?= t('call') ?>
                </a>
            </div>
            <?php endif; ?>

            <div class="info-box">
                <p class="advice-text">
                    <strong>💡 Consejo:</strong> <?= t('advice_verify') ?>
                </p>
            </div>
        </div><!-- /vendor-card -->

        <!-- ══ MÓDULO DE RESEÑAS (tabs: Producto / Vendedor) ══ -->
        <?php
        $review_tipo      = 'producto';
        $review_id        = $producto['id_producto'];
        $review_tipo_alt  = 'vendedor';
        $review_id_alt    = $producto['vendedor_id'];
        $review_modo      = 'tabs';
        $review_es_propio = $es_mi_producto;
        include __DIR__ . '/../../partials/reviews.php';
        ?>

    </div><!-- /detail-container -->

    <!-- ══ MODAL DE MENSAJE ══════════════════════════════════ -->
    <div id="modalMensaje" class="modal-mensaje" style="display: none;">
        <div class="modal-mensaje-content">
            <div class="modal-mensaje-header">
                <h3>💬 <?= t('send_message_to') ?></h3>
                <button type="button" class="btn-close-modal" onclick="cerrarModalMensaje()">✖</button>
            </div>
            <div class="modal-mensaje-body">
                <p class="modal-info">
                    <strong><?= t('vendor') ?>:</strong> <span id="modalVendedorNombre"></span><br>
                    <strong><?= t('product') ?>:</strong> <span id="modalProductoNombre"></span>
                </p>
                <textarea id="mensajeTexto" class="mensaje-textarea" placeholder="<?= t('write_message') ?>"
                    rows="5"></textarea>
                <div id="mensajeError" class="mensaje-error" style="display: none;"></div>
                <div id="mensajeExito" class="mensaje-exito" style="display: none;"></div>
            </div>
            <div class="modal-mensaje-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModalMensaje()"><?= t('cancel') ?></button>
                <button type="button" class="btn-send-message" onclick="enviarMensaje()">
                    ✅ <?= t('send_message') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ══ SCRIPTS ════════════════════════════════════════════ -->
    <script src="/ascc/frontend/users/public/js/sync-global.js"></script>
    <script src="/ascc/frontend/users/public/js/producto-detalle.js?v=<?= time() ?>"></script>
    <script src="/ascc/frontend/users/public/js/reviews.js?v=<?= filemtime(__DIR__ . '/public/js/reviews.js') ?>"></script>

</body>

</html>