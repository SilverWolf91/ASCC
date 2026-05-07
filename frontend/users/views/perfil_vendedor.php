<?php

/**
 * ASCC — Perfil Público del Vendedor
 * Ruta: perfil_vendedor.php
 *
 * Muestra: datos del vendedor, sus productos activos y sus reseñas.
 * Acceso: ?id=X (id_usuario del vendedor)
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../../../backend/users/config/app.php';
require_once __DIR__ . '/../../../backend/users/config/database.php';

// ── Validar parámetro ────────────────────────────────────────
$id_vendedor = (int) ($_GET['id'] ?? 0);
if ($id_vendedor <= 0) {
    header('Location: /ascc/catalogo.php');
    exit;
}

// ── Obtener datos del vendedor ───────────────────────────────
$stmt = $conexion->prepare("
    SELECT
        id_usuario,
        nombre,
        email,
        foto_perfil,
        telefono,
        rol,
        estado,
        fecha_registro
    FROM usuarios
    WHERE id_usuario = :id
      AND estado     = 'activo'
    LIMIT 1
");
$stmt->execute([':id' => $id_vendedor]);

if ($stmt->rowCount() === 0) {
    header('Location: /ascc/catalogo.php');
    exit;
}

$vendedor = $stmt->fetch();

// Solo vendedores y mixtos tienen perfil público
if (!in_array($vendedor['rol'], ['vendedor', 'mixto', 'admin'], true)) {
    header('Location: /ascc/catalogo.php');
    exit;
}

// ── Verificar si es el propio perfil ────────────────────────
$esPropioPerfil = isset($_SESSION['id_usuario'])
    && (int)$_SESSION['id_usuario'] === $id_vendedor;

// ── TRACKING DE VISITAS AL PERFIL ───────────────────────────
// Solo registrar si no es el propio vendedor visitando su perfil
if (!$esPropioPerfil) {
    registrarVisitaPerfil(
        $conexion,
        $id_vendedor,
        isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null
    );
}

/**
 * Registra una visita al perfil del vendedor evitando duplicados por sesión.
 * Si el mismo session_id ya visitó este perfil en los últimos 30 min,
 * no se inserta un nuevo registro.
 */
function registrarVisitaPerfil(
    PDO  $pdo,
    int  $id_vendedor,
    ?int $id_visitante
): void {
    $sesion_id = session_id();
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '';

    try {
        // Evitar contar recargas — mismo session_id en menos de 5 minutos no cuenta
        $check = $pdo->prepare("
            SELECT id FROM visitas_perfil
            WHERE id_vendedor = :vendedor
              AND sesion_id   = :sesion
              AND fecha_visita > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            LIMIT 1
        ");
        $check->execute([
            ':vendedor' => $id_vendedor,
            ':sesion'   => $sesion_id,
        ]);

        if ($check->rowCount() > 0) {
            return; // Ya registrada en los últimos 30 min
        }

        // Insertar visita nueva
        $pdo->prepare("
            INSERT INTO visitas_perfil
                (id_vendedor, id_visitante, ip_visitante, sesion_id)
            VALUES
                (:vendedor, :visitante, :ip, :sesion)
        ")->execute([
            ':vendedor'  => $id_vendedor,
            ':visitante' => $id_visitante,
            ':ip'        => $ip,
            ':sesion'    => $sesion_id,
        ]);

    } catch (PDOException $e) {
        // No interrumpir la carga de la página por un error de tracking
        error_log('ASCC tracking visita perfil: ' . $e->getMessage());
    }
}
// ── FIN TRACKING ─────────────────────────────────────────────

// ── Productos activos del vendedor ───────────────────────────
$stmtProd = $conexion->prepare("
    SELECT
        p.id_producto,
        p.tipo_producto,
        p.precio,
        p.unidad,
        p.cantidad,
        p.fecha_publicacion,
        u.municipio,
        u.departamento,
        (SELECT ruta_imagen
         FROM imagenes_productos
         WHERE id_producto = p.id_producto
         LIMIT 1) AS imagen
    FROM productos p
    INNER JOIN ubicaciones u ON u.id_ubicacion = p.id_ubicacion
    WHERE p.id_usuario = :id
      AND p.estado     = 'disponible'
    ORDER BY p.fecha_publicacion DESC
    LIMIT 12
");
$stmtProd->execute([':id' => $id_vendedor]);
$productos = $stmtProd->fetchAll();

// ── Resumen de calificaciones del vendedor ───────────────────
$stmtCal = $conexion->prepare("
    SELECT
        ROUND(AVG(calificacion), 1) AS promedio,
        COUNT(*)                    AS total
    FROM resenas_vendedor
    WHERE id_vendedor = :id
");
$stmtCal->execute([':id' => $id_vendedor]);
$calVendedor = $stmtCal->fetch();

$promedioVendedor = (float) ($calVendedor['promedio'] ?? 0);
$totalResenas     = (int)   ($calVendedor['total']    ?? 0);

// ── Variables para el partial de reseñas ────────────────────
$review_tipo = 'vendedor';
$review_id   = $id_vendedor;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($vendedor['nombre']) ?> — <?= t('app_name') ?></title>

    <link rel="icon" type="image/png" href="/ascc/frontend/users/public/img/logo.png">

    <?= ascc_theme_css() ?>

    <link rel="stylesheet"
        href="/ascc/frontend/users/public/css/producto-detalle.css?v=<?= filemtime(__DIR__ . '/public/css/producto-detalle.css') ?>">
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
</head>

<body class="theme-<?= $theme ?>" data-theme="<?= $theme ?>">

    <?php include __DIR__ . '/../../partials/header.php'; ?>

    <div class="detail-container">

        <a href="/ascc/frontend/users/views/catalogo.php" class="back-link">
            ← <?= t('back_to_catalog') ?>
        </a>

        <!-- ══ TARJETA DEL VENDEDOR ══════════════════════════ -->
        <div class="vendor-card">
            <div class="vendor-info">
                <!-- Avatar -->
                <div class="vendor-avatar">
                    <?php if ($vendedor['foto_perfil']): ?>
                    <img src="/ascc/frontend/users/public/<?= htmlspecialchars($vendedor['foto_perfil']) ?>"
                        alt="<?= htmlspecialchars($vendedor['nombre']) ?>"
                        style="width:100%;height:100%;border-radius:50%;object-fit:cover">
                    <?php else: ?>
                    <?= strtoupper(substr($vendedor['nombre'], 0, 1)) ?>
                    <?php endif; ?>
                </div>

                <div class="vendor-details">
                    <div class="vendor-name">
                        <?= htmlspecialchars($vendedor['nombre']) ?>
                    </div>

                    <!-- Estrellas promedio -->
                    <?php if ($totalResenas > 0): ?>
                    <div class="vendor-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?= $i <= round($promedioVendedor) ? '⭐' : '☆' ?>
                        <?php endfor; ?>
                        <?= number_format($promedioVendedor, 1) ?>
                        <small>(<?= $totalResenas ?>
                            <?= $totalResenas === 1 ? t('reviews_una') : t('reviews_varias') ?>)
                        </small>
                    </div>
                    <?php endif; ?>

                    <p class="vendor-verified">✅ <?= t('verified_vendor') ?></p>

                    <p style="font-size:.85rem;margin:.3rem 0 0;opacity:.7">
                        📅 <?= t('reviews_miembro_desde') ?>
                        <?= date('Y', strtotime($vendedor['fecha_registro'])) ?>
                    </p>
                </div>
            </div>

            <!-- Botones de contacto -->
            <?php if (!$esPropioPerfil && isset($_SESSION['id_usuario'])): ?>
            <h3 class="contact-title">📞 <?= t('contact_vendor') ?></h3>
            <div class="contact-buttons">
                <a href="https://wa.me/57<?= preg_replace('/[^0-9]/', '', $vendedor['telefono']) ?>" target="_blank"
                    class="btn-contact btn-whatsapp">
                    📱 <?= t('whatsapp') ?>
                </a>
                <a href="mailto:<?= htmlspecialchars($vendedor['email']) ?>" class="btn-contact btn-email">
                    📧 <?= t('email') ?>
                </a>
                <a href="tel:<?= htmlspecialchars($vendedor['telefono']) ?>" class="btn-contact btn-call">
                    📞 <?= t('call') ?>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- ══ PRODUCTOS DEL VENDEDOR ════════════════════════ -->
        <?php if (count($productos) > 0): ?>
        <div class="vendor-card" style="margin-top:1.5rem">
            <h2 class="vendor-title">
                📦 <?= t('reviews_productos_de') ?>
                <?= htmlspecialchars($vendedor['nombre']) ?>
            </h2>
            <div style="
                    display:grid;
                    grid-template-columns: repeat(auto-fill, minmax(200px,1fr));
                    gap:1rem;
                    margin-top:1rem
                ">
                <?php foreach ($productos as $prod): ?>
                <a href="/ascc/frontend/users/views/producto_detalle.php?id=<?= $prod['id_producto'] ?>&origen=perfil" style="
                            display:block;
                            background:var(--card-bg, #1e293b);
                            border:1px solid var(--border-color, #334155);
                            border-radius:10px;
                            overflow:hidden;
                            text-decoration:none;
                            color:inherit;
                            transition:transform .15s, box-shadow .15s
                        "
                    onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.2)'"
                    onmouseout="this.style.transform='';this.style.boxShadow=''">
                    <img src="/ascc/frontend/users/public/<?= htmlspecialchars($prod['imagen'] ?? 'img/no-image.png') ?>"
                        alt="<?= htmlspecialchars($prod['tipo_producto']) ?>"
                        style="width:100%;height:130px;object-fit:cover"
                        onerror="this.src='/ascc/frontend/users/public/img/no-image.png'" loading="lazy">
                    <div style="padding:.75rem">
                        <div style="font-weight:700;font-size:.9rem;margin-bottom:.25rem">
                            <?= htmlspecialchars($prod['tipo_producto']) ?>
                        </div>
                        <div style="color:#10b981;font-weight:700;font-size:.95rem">
                            $<?= number_format($prod['precio'], 0, ',', '.') ?> COP
                            <span style="font-size:.75rem;opacity:.7">/ <?= htmlspecialchars($prod['unidad']) ?></span>
                        </div>
                        <div style="font-size:.75rem;opacity:.6;margin-top:.2rem">
                            📍 <?= htmlspecialchars($prod['municipio']) ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══ MÓDULO DE RESEÑAS ══════════════════════════════ -->
        <?php include __DIR__ . '/../../partials/reviews.php'; ?>

    </div>

    <script src="/ascc/frontend/users/public/js/sync-global.js"></script>
    <script src="/ascc/frontend/users/public/js/reviews.js?v=<?= filemtime(__DIR__ . '/public/js/reviews.js') ?>"></script>

</body>

</html>