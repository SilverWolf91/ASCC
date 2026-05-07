<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - Aromas y Sabores de mi Campo Colombiano - DASHBOARD PRINCIPAL
 * Ruta: C:\xampp\htdocs\ascc\dashboard.php
 *
 * Responsabilidad: Vista principal del productor
 *   - Carga config/app.php (sesión, idioma, tema, traducciones)
 *   - Consulta BD y construye el HTML
 *   - Incluye los JS al final del body (NO redefine ASCCGlobal)
 *
 * Orden de JS al final del body:
 *   1. sync-global.js   → define window.ASCCGlobal
 *   2. dashboard-sync.js → actualiza estado visual del sidebar
 *   3. dashboard.js     → lógica específica del dashboard
 *   4. modal-perfil.js  → lógica del modal actualizar datos
 * ═══════════════════════════════════════════════════════════
 */

// Prevenir caché
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Configuración global (sesión, idioma, tema, t(), ascc_theme_css())
require_once __DIR__ . '/config/app.php';

// Verificar autenticación
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ascc/views/auth/login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
$id_usuario = $_SESSION['id_usuario'];

// ── CONSULTAS BD ─────────────────────────────────────────────

// Datos del usuario
$stmt = $conexion->prepare('SELECT * FROM usuarios WHERE id_usuario = :id');
$stmt->bindParam(':id', $id_usuario);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Rol del usuario para controlar el menú
$rol_usuario = $usuario['rol'] ?? 'vendedor';
$_SESSION['rol'] = $rol_usuario;

// Permisos por rol
$puede_vender  = in_array($rol_usuario, ['vendedor', 'mixto']);
$puede_comprar = in_array($rol_usuario, ['comprador', 'mixto']);

// Productos disponibles
$stmt = $conexion->prepare('
    SELECT
        p.*,
        u.departamento, u.municipio, u.vereda,
        GROUP_CONCAT(ip.ruta_imagen) AS imagenes
    FROM productos p
    INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
    LEFT  JOIN imagenes_productos ip ON p.id_producto = ip.id_producto
    WHERE p.id_usuario = :id AND p.estado = \'disponible\'
    GROUP BY p.id_producto
    ORDER BY p.fecha_publicacion DESC
');
$stmt->bindParam(':id', $id_usuario);
$stmt->execute();
$productos_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Productos vendidos (últimos 20)
$stmt = $conexion->prepare('
    SELECT
        p.*,
        u.departamento, u.municipio, u.vereda,
        GROUP_CONCAT(ip.ruta_imagen) AS imagenes
    FROM productos p
    INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
    LEFT  JOIN imagenes_productos ip ON p.id_producto = ip.id_producto
    WHERE p.id_usuario = :id AND p.estado = \'vendido\'
    GROUP BY p.id_producto
    ORDER BY p.fecha_venta DESC
    LIMIT 20
');
$stmt->bindParam(':id', $id_usuario);
$stmt->execute();
$productos_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── ESTADÍSTICAS ─────────────────────────────────────────────
$total_disponibles = count($productos_disponibles);
$total_vendidos    = count($productos_vendidos);
$valor_inventario  = 0;
foreach ($productos_disponibles as $p) {
    $valor_inventario += $p['precio'] * $p['cantidad'];
}

// Obtener conversaciones reales del usuario
$stmt = $conexion->prepare("
    SELECT
        c.id_conversacion,
        c.id_producto,
        c.ultima_actualizacion,
        p.tipo_producto,
        CASE
            WHEN c.id_comprador = :id_usuario1 THEN u_vendedor.nombre
            ELSE u_comprador.nombre
        END as nombre_otro_usuario,
        (SELECT mensaje FROM mensajes WHERE id_conversacion = c.id_conversacion ORDER BY fecha_envio DESC LIMIT 1) as ultimo_mensaje,
        (SELECT fecha_envio FROM mensajes WHERE id_conversacion = c.id_conversacion ORDER BY fecha_envio DESC LIMIT 1) as fecha_ultimo_mensaje,
        (SELECT COUNT(*) FROM mensajes WHERE id_conversacion = c.id_conversacion AND id_remitente != :id_usuario2 AND leido = 0) as mensajes_no_leidos
    FROM conversaciones c
    INNER JOIN productos p ON c.id_producto = p.id_producto
    LEFT JOIN usuarios u_vendedor ON c.id_vendedor = u_vendedor.id_usuario
    LEFT JOIN usuarios u_comprador ON c.id_comprador = u_comprador.id_usuario
    WHERE c.id_comprador = :id_usuario3 OR c.id_vendedor = :id_usuario4
    ORDER BY c.ultima_actualizacion DESC
");
$stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':id_usuario3', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':id_usuario4', $id_usuario, PDO::PARAM_INT);
$stmt->execute();
$conversaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar mensajes no leídos total
$mensajes_no_leidos = 0;
foreach ($conversaciones as $conv) {
    $mensajes_no_leidos += $conv['mensajes_no_leidos'];
}

// ── TENDENCIAS ────────────────────────────────────────────────
// Más vendidos: productos con más unidades en transacciones APROBADAS
$stmt = $conexion->prepare('
    SELECT p.id_producto, p.tipo_producto, p.producto_especifico,
           p.categoria_principal,
           SUM(t.cantidad) AS total_cantidad,
           COUNT(DISTINCT t.id_transaccion) AS num_ventas
    FROM   transacciones t
    INNER JOIN productos p ON t.id_producto = p.id_producto
    WHERE  t.estado = \'APROBADO\'
    GROUP  BY t.id_producto
    ORDER  BY total_cantidad DESC
    LIMIT  4
');
$stmt->execute();
$mas_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback: si no hay transacciones aprobadas, usar productos marcados como vendidos
if (empty($mas_vendidos)) {
    $stmt = $conexion->prepare('
        SELECT id_producto, tipo_producto, producto_especifico,
               categoria_principal,
               1 AS total_cantidad, 1 AS num_ventas
        FROM   productos
        WHERE  estado = \'vendido\'
        ORDER  BY fecha_venta DESC
        LIMIT  4
    ');
    $stmt->execute();
    $mas_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Más vistos: productos con más registros en vistas_productos
$stmt = $conexion->prepare('
    SELECT p.id_producto, p.tipo_producto, p.producto_especifico,
           p.categoria_principal,
           COUNT(v.id_vista) AS total_vistas
    FROM   vistas_productos v
    INNER JOIN productos p ON v.id_producto = p.id_producto
    WHERE  p.estado = \'disponible\'
    GROUP  BY v.id_producto
    ORDER  BY total_vistas DESC
    LIMIT  4
');
$stmt->execute();
$mas_vistos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback: si no hay datos de vistas, usar los más recientes
if (empty($mas_vistos)) {
    $stmt = $conexion->prepare('
        SELECT id_producto, tipo_producto, producto_especifico,
               categoria_principal, 0 AS total_vistas
        FROM   productos
        WHERE  estado = \'disponible\'
        ORDER  BY fecha_publicacion DESC
        LIMIT  4
    ');
    $stmt->execute();
    $mas_vistos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función: emoji por categoría
function tendenciaEmoji(string $cat, string $tipo): string {
    $map = [
        'verduras' => '🥦', 'frutas'   => '🍎', 'aves'    => '🐔',
        'peces'    => '🐟', 'mayor'    => '🐄', 'menor'   => '🐑',
        'cereales' => '🌾', 'lacteos'  => '🥛', 'huevos'  => '🥚',
        'tuberculos' => '🥔', 'tubérculos' => '🥔',
        'legumbres'=> '🫘', 'cafe'     => '☕', 'café'    => '☕',
        'cacao'    => '🍫', 'platano'  => '🍌', 'plátano' => '🍌',
        'panela'   => '🍯', 'miel'     => '🍯',
    ];
    $key = strtolower(trim($cat));
    if (isset($map[$key])) return $map[$key];
    // Fallback por tipo de producto
    $tipo_l = strtolower($tipo);
    foreach ($map as $k => $emoji) {
        if (str_contains($tipo_l, $k)) return $emoji;
    }
    return '🌱';
}

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= t('app_name') ?></title>
    <link rel="icon" type="image/png" href="/ascc/public/img/logo.png">

    <!-- CSS dinámico según tema (light.css o dark.css) -->
    <?= ascc_theme_css() ?>

    <!-- CSS específico del dashboard -->
    <link rel="stylesheet" href="/ascc/public/css/dashboard.css">

    <!-- CSS modal actualizar datos -->
    <link rel="stylesheet" href="/ascc/public/css/modal-perfil.css">
</head>

<body class="theme-<?= $theme ?>" data-theme="<?= $theme ?>">

    <div class="dashboard-layout">

        <!-- ── SIDEBAR ─────────────────────────────────────────── -->
        <aside class="sidebar collapsed">

            <div class="sidebar-header">
                <div class="logo">
                    <img src="/ascc/public/img/logo.png" alt="<?= t('app_name') ?>">
                    <span>A S C C</span>
                </div>
                <button class="sidebar-toggle" onclick="toggleSidebar()" title="<?= t('expand_menu') ?>">
                    ☰
                </button>
            </div>

            <!-- Toggle tema -->
            <div class="theme-toggle-container">
                <div class="theme-toggle" onclick="ASCCGlobal.toggleTema()">
                    <div class="theme-label">
                        <span class="theme-icon"><?= $theme === 'light' ? '🌙' : '☀️' ?></span>
                        <span><?= $theme === 'light' ? t('dark_mode') : t('light_mode') ?></span>
                    </div>
                    <div class="toggle-switch <?= $theme === 'dark' ? 'activo' : '' ?>">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
            </div>

            <!-- Selector de idioma -->
            <div class="language-selector-container">
                <div class="language-selector">
                    <button class="language-btn <?= $lang === 'es' ? 'active' : '' ?>" data-lang="es"
                        onclick="ASCCGlobal.cambiarIdioma('es')" title="Cambiar a Español">
                        <span>🇨🇴</span>
                        <span>ES</span>
                    </button>
                    <button class="language-btn <?= $lang === 'en' ? 'active' : '' ?>" data-lang="en"
                        onclick="ASCCGlobal.cambiarIdioma('en')" title="Switch to English">
                        <span>🇺🇸</span>
                        <span>EN</span>
                    </button>
                </div>
            </div>

            <!-- ── NAVEGACIÓN DINÁMICA POR ROL ─────────────────── -->
            <nav class="sidebar-nav">

                <!-- SECCIÓN PRINCIPAL — visible para todos los roles -->
                <div class="nav-section">
                    <div class="nav-section-title"><?= t('main_section') ?></div>

                    <a class="nav-item active" onclick="openTab(event,'inicio')">
                        <span class="nav-icon">🏠</span>
                        <span><?= t('menu_home') ?></span>
                    </a>

                    <?php if ($puede_vender): ?>
                    <a class="nav-item" onclick="openTab(event,'misProductos')">
                        <span class="nav-icon">📦</span>
                        <span><?= t('menu_products') ?></span>
                    </a>
                    <a class="nav-item" onclick="openTab(event,'publicar')">
                        <span class="nav-icon">➕</span>
                        <span><?= t('menu_publish') ?></span>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- SECCIÓN COMUNICACIÓN — visible para todos los roles -->
                <div class="nav-section">
                    <div class="nav-section-title"><?= t('communication_section') ?></div>
                    <a class="nav-item" onclick="openTab(event,'mensajes')">
                        <span class="nav-icon">💬</span>
                        <span><?= t('menu_messages') ?></span>
                        <?php if ($mensajes_no_leidos > 0): ?>
                        <span class="nav-badge"><?= $mensajes_no_leidos ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <?php if ($puede_comprar): ?>
                <!-- SECCIÓN EXPLORAR — solo comprador y mixto -->
                <div class="nav-section">
                    <div class="nav-section-title"><?= t('explore_section') ?></div>
                    <a class="nav-item" onclick="openTab(event,'catalogo')">
                        <span class="nav-icon">🛒</span>
                        <span><?= t('menu_catalog') ?></span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- ══ NUEVO: SECCIÓN REPORTES — visible para todos los roles ══ -->
                <div class="nav-section">
                    <div class="nav-section-title">📊 Análisis</div>
                    <a class="nav-item" href="/ascc/reportes.php" target="_self">
                        <span class="nav-icon">📈</span>
                        <span><?= t('menu_reportes') ?></span>
                    </a>
                </div>
                <!-- ══ FIN NUEVO ══ -->

            </nav>
            <!-- ── FIN NAVEGACIÓN ───────────────────────────────── -->

            <!-- Footer sidebar -->
            <div class="sidebar-footer">
                <div class="user-profile">
                    <?php if (!empty($usuario['foto_perfil'])): ?>
                    <img src="/ascc/public/<?= htmlspecialchars($usuario['foto_perfil']) ?>"
                        alt="<?= htmlspecialchars($usuario['nombre']) ?>" class="user-avatar-img"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="user-avatar" style="display:none;">
                        <?= strtoupper(substr($usuario['nombre'], 0, 2)) ?>
                    </div>
                    <?php else: ?>
                    <div class="user-avatar">
                        <?= strtoupper(substr($usuario['nombre'], 0, 2)) ?>
                    </div>
                    <?php endif; ?>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                        <div class="user-role"><?php
                            if ($rol_usuario === 'vendedor')       echo '🚜 Vendedor';
                            elseif ($rol_usuario === 'comprador')  echo '🛒 Comprador';
                            elseif ($rol_usuario === 'mixto')      echo '🤝 Comprador y Vendedor';
                            elseif ($rol_usuario === 'admin')      echo '⚙️ Administrador';
                            else echo ucfirst($rol_usuario);
                        ?></div>
                    </div>
                </div>
                <a href="/ascc/controllers/logout.php" class="btn-logout">
                    <?= t('logout') ?>
                </a>
            </div>

        </aside>

        <!-- Overlay para móvil -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <!-- ── CONTENIDO PRINCIPAL ─────────────────────────────── -->
        <main class="main-content">

            <!-- Botón flotante abrir sidebar (móvil) -->
            <button class="sidebar-float-toggle" onclick="toggleSidebar()" title="<?= t('open_menu') ?>">
                ☰
            </button>

            <!-- Topbar -->
            <div class="topbar">
                <div class="topbar-welcome">
                    <h1><?= t('welcome') ?>, <?= htmlspecialchars(explode(' ', $usuario['nombre'])[0]) ?>!</h1>
                    <p><?= t('manage_business') ?></p>
                </div>
                <div class="topbar-actions">
                    <button class="btn-refresh" onclick="refreshAllData()" title="<?= t('refresh') ?>">
                        🔄
                    </button>
                </div>
            </div>

            <!-- Alerta bienvenida (solo tras registro) -->
            <?php if (isset($_GET['registro_exitoso']) && $_GET['registro_exitoso'] == 1): ?>
            <div class="container">
                <div class="alert-registro-exitoso">
                    <h3><?= t('welcome_title') ?></h3>
                    <p>
                        <?= t('account_created') ?>
                        <strong>
                            <?= isset($_GET['email_enviado']) && $_GET['email_enviado'] == 1
                                ? t('email_sent')
                                : t('email_not_sent') ?>
                        </strong>
                    </p>
                    <div class="alert-next-step">
                        <?= t('next_step') ?>
                        <?php if ($puede_vender): ?>
                        <a href="#" onclick="openTab(event,'publicar');return false;">
                            <?= t('create_first_product') ?>
                        </a>
                        <?php else: ?>
                        <a href="#" onclick="openTab(event,'catalogo');return false;">
                            <?= t('menu_catalog') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="container">

                <!-- Stats cards -->
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-header">
                            <div class="stat-icon green">📦</div>
                        </div>
                        <div class="stat-value"><?= $total_disponibles ?></div>
                        <div class="stat-label"><?= t('stat_active_products') ?></div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-header">
                            <div class="stat-icon orange">💰</div>
                        </div>
                        <div class="stat-value">$<?= number_format($valor_inventario, 0, ',', '.') ?></div>
                        <div class="stat-label"><?= t('stat_inventory_value') ?></div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-header">
                            <div class="stat-icon blue">✅</div>
                        </div>
                        <div class="stat-value"><?= $total_vendidos ?></div>
                        <div class="stat-label"><?= t('stat_products_sold') ?></div>
                    </div>
                </div>

                <!-- ── TAB: INICIO ─────────────────────────────── -->
                <div id="inicio" class="tab-content active">
                    <div class="inicio-grid">

                        <!-- Perfil -->
                        <div class="profile-card">
                            <div class="profile-photo-container">
                                <?php if (!empty($usuario['foto_perfil'])): ?>
                                <img src="/ascc/public/<?= htmlspecialchars($usuario['foto_perfil']) ?>"
                                    alt="<?= htmlspecialchars($usuario['nombre']) ?>" class="profile-photo-img"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="profile-photo" style="display:none;">
                                    <?= strtoupper(substr($usuario['nombre'], 0, 2)) ?>
                                </div>
                                <?php else: ?>
                                <div class="profile-photo">
                                    <?= strtoupper(substr($usuario['nombre'], 0, 2)) ?>
                                </div>
                                <?php endif; ?>
                                <div class="profile-photo-edit" onclick="openPhotoModal()"
                                    title="<?= t('change_photo') ?>">
                                    📷
                                </div>
                            </div>

                            <div class="profile-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                            <div class="profile-email"><?= htmlspecialchars($usuario['email']) ?></div>

                            <div class="profile-badges">
                                <span class="badge badge-verified"><?= t('verified') ?></span>
                                <?php if ($total_vendidos >= 5): ?>
                                <span class="badge badge-premium"><?= t('featured') ?></span>
                                <?php endif; ?>
                            </div>

                            <button class="btn-update-profile" id="btnActualizarDatos" onclick="updateProfile()">
                                <?= t('update_profile') ?>
                            </button>

                            <!-- ══ NUEVO: Acceso rápido a reportes desde el perfil ══ -->
                            <a href="/ascc/reportes.php" class="btn-update-profile"
                                style="display:block;text-align:center;text-decoration:none;margin-top:8px;">
                                📊 <?= t('menu_reportes') ?>
                            </a>
                            <!-- ══ FIN NUEVO ══ -->
                        </div>

                        <!-- Área derecha -->
                        <div class="main-content-area">

                            <!-- Tendencia -->
                            <div class="trending-section">
                                <div class="section-header">
                                    <h3 class="section-title">📈 <?= t('trending_products') ?></h3>
                                    <div class="trending-tabs">
                                        <button class="trending-tab is-active"
                                            onclick="switchTrending(this,'tpVendidos')">
                                            🏆 <?= t('trend_sold') ?>
                                        </button>
                                        <button class="trending-tab"
                                            onclick="switchTrending(this,'tpVistos')">
                                            👁 <?= t('trend_viewed') ?>
                                        </button>
                                    </div>
                                </div>

                                <!-- Panel: Más vendidos -->
                                <div class="trending-panel" id="tpVendidos">
                                <?php if (empty($mas_vendidos)): ?>
                                    <p class="trending-no-data">📭 <?= t('trend_no_data') ?></p>
                                <?php else: ?>
                                    <div class="trending-grid">
                                    <?php foreach ($mas_vendidos as $i => $prod): ?>
                                        <div class="trending-item">
                                            <span class="trending-rank">#<?= $i + 1 ?></span>
                                            <div class="trending-icon">
                                                <?= tendenciaEmoji($prod['categoria_principal'] ?? '', $prod['tipo_producto'] ?? '') ?>
                                            </div>
                                            <div class="trending-name">
                                                <?= htmlspecialchars($prod['producto_especifico'] ?: $prod['tipo_producto']) ?>
                                            </div>
                                            <div class="trending-views">
                                                🛒 <?= number_format((float)$prod['total_cantidad']) ?>
                                                <?= t('trend_units_sold') ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                </div>

                                <!-- Panel: Más vistos -->
                                <div class="trending-panel" id="tpVistos" style="display:none">
                                <?php if (empty($mas_vistos)): ?>
                                    <p class="trending-no-data">📭 <?= t('trend_no_data') ?></p>
                                <?php else: ?>
                                    <div class="trending-grid">
                                    <?php foreach ($mas_vistos as $i => $prod): ?>
                                        <div class="trending-item">
                                            <span class="trending-rank">#<?= $i + 1 ?></span>
                                            <div class="trending-icon">
                                                <?= tendenciaEmoji($prod['categoria_principal'] ?? '', $prod['tipo_producto'] ?? '') ?>
                                            </div>
                                            <div class="trending-name">
                                                <?= htmlspecialchars($prod['producto_especifico'] ?: $prod['tipo_producto']) ?>
                                            </div>
                                            <div class="trending-views">
                                                👁 <?= number_format((int)($prod['total_vistas'] ?? 0)) ?>
                                                <?= t('views') ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>

                            <!-- Notificaciones en tiempo real -->
                            <div class="notif-section" id="seccionNotificaciones">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        🔔 <?= t('notif_title') ?>
                                        <span class="notif-badge" id="notifBadge" style="display:none"></span>
                                    </h3>
                                    <button class="notif-mark-all" id="btnMarcarTodas">
                                        <?= t('notif_mark_all') ?>
                                    </button>
                                </div>
                                <div class="notif-list" id="notifLista">
                                    <div class="notif-empty" id="notifEmpty">
                                        <span>✅</span>
                                        <p><?= t('notif_empty') ?></p>
                                        <small><?= t('notif_empty_sub') ?></small>
                                    </div>
                                </div>
                            </div>

                            <!-- Descuentos -->
                            <div class="discounts-section">
                                <div class="section-header">
                                    <h3 class="section-title"><?= t('active_discounts') ?></h3>
                                </div>
                                <div class="discount-item">
                                    <div class="discount-info">
                                        <h4><?= t('premium_publication') ?></h4>
                                        <p><?= t('highlight_products_30days') ?></p>
                                    </div>
                                    <div class="discount-badge">-50%</div>
                                </div>
                                <div class="discount-item">
                                    <div class="discount-info">
                                        <h4><?= t('annual_plan') ?></h4>
                                        <p><?= t('save_commissions') ?></p>
                                    </div>
                                    <div class="discount-badge">-30%</div>
                                </div>
                            </div>

                            <!-- Actividad reciente -->
                            <div class="activity-section">
                                <div class="section-header">
                                    <h3 class="section-title"><?= t('recent_activity') ?></h3>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-icon green">✅</div>
                                    <div class="activity-details">
                                        <h4><?= t('product_sold') ?></h4>
                                        <p><?= t('hours_ago') ?></p>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-icon blue">👁️</div>
                                    <div class="activity-details">
                                        <h4>56 <?= t('new_visits') ?></h4>
                                        <p><?= t('today') ?></p>
                                    </div>
                                </div>
                                <div class="activity-icon orange">💬</div>
                                <div class="activity-details">
                                    <h4>3 <?= t('new_messages') ?></h4>
                                    <p><?= t('hour_ago') ?></p>
                                </div>
                            </div>

                        </div><!-- /main-content-area -->
                    </div><!-- /inicio-grid -->
                </div><!-- /inicio -->

                <!-- ── TAB: MIS PRODUCTOS — solo vendedor y mixto ──── -->
                <?php if ($puede_vender): ?>
                <div id="misProductos" class="tab-content">
                    <div class="section-header">
                        <h2><?= t('my_available_products') ?></h2>
                        <span class="badge badge-verified"><?= $total_disponibles ?> <?= t('products') ?></span>
                    </div>

                    <?php if (count($productos_disponibles) > 0): ?>
                    <div class="products-grid">
                        <?php foreach ($productos_disponibles as $producto):
                            $imgs  = !empty($producto['imagenes']) ? explode(',', $producto['imagenes']) : [];
                            $thumb = !empty($imgs) ? $imgs[0] : 'img/no-image.png';
                        ?>
                        <div class="product-card">
                            <img src="/ascc/public/<?= $thumb ?>"
                                alt="<?= htmlspecialchars($producto['tipo_producto']) ?>" class="product-image"
                                onerror="this.src='/ascc/public/img/no-image.png'">
                            <div class="product-card-body">
                                <h3 class="product-title"><?= htmlspecialchars($producto['tipo_producto']) ?></h3>
                                <div class="product-price">$<?= number_format($producto['precio'], 0, ',', '.') ?></div>
                                <div class="product-quantity">
                                    <strong><?= t('quantity') ?>:</strong>
                                    <?= $producto['cantidad'] ?> <?= $producto['unidad'] ?>
                                </div>
                                <div class="product-location">
                                    📍 <?= htmlspecialchars($producto['vereda']) ?>,
                                    <?= htmlspecialchars($producto['municipio']) ?>
                                </div>
                                <div class="product-actions">
                                    <button class="btn-action-small btn-action-success"
                                        onclick="marcarVendido(<?= $producto['id_producto'] ?>)">
                                        <?= t('mark_sold') ?>
                                    </button>
                                    <button class="btn-action-small btn-action-danger"
                                        onclick="eliminarProducto(<?= $producto['id_producto'] ?>)">
                                        🗑️ <?= t('delete') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <h3><?= t('no_products_published') ?></h3>
                        <p><?= t('start_publishing') ?></p>
                        <button class="btn-primary" onclick="openTab(event,'publicar')">
                            📤 <?= t('publish_product') ?>
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Historial de ventas -->
                    <?php if (count($productos_vendidos) > 0): ?>
                    <div class="section-header" style="margin-top:60px;">
                        <h2><?= t('sales_history') ?></h2>
                        <span class="badge badge-verified"><?= $total_vendidos ?> <?= t('sold') ?></span>
                    </div>
                    <div class="products-grid">
                        <?php foreach ($productos_vendidos as $producto):
                            $imgs  = !empty($producto['imagenes']) ? explode(',', $producto['imagenes']) : [];
                            $thumb = !empty($imgs) ? $imgs[0] : 'img/no-image.png';
                        ?>
                        <div class="product-card sold-card">
                            <div class="sold-badge"><?= t('sold_badge') ?></div>
                            <img src="/ascc/public/<?= $thumb ?>"
                                alt="<?= htmlspecialchars($producto['tipo_producto']) ?>" class="product-image"
                                onerror="this.src='/ascc/public/img/no-image.png'">
                            <div class="product-card-body">
                                <h3 class="product-title"><?= htmlspecialchars($producto['tipo_producto']) ?></h3>
                                <div class="product-price">$<?= number_format($producto['precio'], 0, ',', '.') ?></div>
                                <div class="product-location">
                                    📍 <?= htmlspecialchars($producto['vereda']) ?>,
                                    <?= htmlspecialchars($producto['municipio']) ?>
                                </div>
                                <div class="product-sold-date">
                                    📅 <?= t('sold_on') ?>:
                                    <?= date('d/m/Y', strtotime($producto['fecha_venta'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ── TAB: MENSAJES — visible para todos los roles ─── -->
                <div id="mensajes" class="tab-content">
                    <iframe src="/ascc/mensajes.php" style="width:100%; height:100vh; border:none;"></iframe>
                </div>

                <!-- ── TAB: PUBLICAR — solo vendedor y mixto ──────── -->
                <?php if ($puede_vender): ?>
                <div id="publicar" class="tab-content">
                    <iframe src="/ascc/crear_producto.php?embed=1"
                        style="width:100%; height:100vh; border:none;"></iframe>
                </div>
                <?php endif; ?>

                <!-- ── TAB: CATÁLOGO — solo comprador y mixto ──────── -->
                <?php if ($puede_comprar): ?>
                <div id="catalogo" class="tab-content">
                    <iframe src="/ascc/catalogo.php?embed=1" style="width:100%; height:100vh; border:none;"></iframe>
                </div>
                <?php endif; ?>

            </div><!-- /container -->
        </main>
    </div><!-- /dashboard-layout -->

    <!-- ── MODAL: CAMBIO DE FOTO ──────────────────────────────── -->
    <div class="photo-modal" id="photoModal">
        <div class="photo-modal-content">
            <div class="photo-modal-header">
                <h3>📷 <?= t('change_photo') ?></h3>
                <button class="photo-modal-close" onclick="closePhotoModal()">✕</button>
            </div>
            <div class="photo-modal-body">
                <div class="photo-preview-container">
                    <div class="photo-preview" id="photoPreview">
                        <?php if (!empty($usuario['foto_perfil'])): ?>
                        <img src="/ascc/public/<?= htmlspecialchars($usuario['foto_perfil']) ?>" alt="Preview"
                            id="previewImage">
                        <?php else: ?>
                        <div class="photo-preview-placeholder">
                            <span style="font-size:64px;">📷</span>
                            <p><?= t('select_image') ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <input type="file" id="photoInput" accept="image/jpeg,image/jpg,image/png,image/webp"
                    style="display:none;" onchange="previewPhoto(event)">
                <button class="btn-select-photo" onclick="document.getElementById('photoInput').click()">
                    📁 <?= t('select_image') ?>
                </button>
                <p class="photo-help-text"><?= t('photo_requirements') ?></p>
            </div>
            <div class="photo-modal-footer">
                <button class="btn-cancel" onclick="closePhotoModal()">
                    <?= t('cancel') ?>
                </button>
                <button class="btn-upload" id="btnUpload" onclick="uploadPhoto()" disabled>
                    <?= t('save') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal actualizar datos -->
    <?php require_once __DIR__ . '/modal-perfil.php'; ?>

    <!--
    ── SCRIPTS ──────────────────────────────────────────────
    ORDEN OBLIGATORIO:
    1. sync-global.js    → define window.ASCCGlobal (PRIMERO)
    2. dashboard-sync.js → actualiza estado visual del sidebar
    3. dashboard.js      → usa window.ASCCGlobal
    4. modal-perfil.js   → lógica modal actualizar datos (ÚLTIMO)
    -->
    <script>
    var notifLang = {
        nueva_notif: '<?= t('notif_nueva') ?>',
        ahora:       '<?= t('notif_ahora') ?>',
        hace_min:    '<?= t('notif_hace_min') ?>',
        hace_h:      '<?= t('notif_hace_h') ?>',
        hace_d:      '<?= t('notif_hace_d') ?>',
    };
    </script>
    <script src="/ascc/public/js/sync-global.js" defer></script>
    <script src="/ascc/public/js/dashboard-sync.js" defer></script>
    <script src="/ascc/public/js/dashboard.js" defer></script>
    <script src="/ascc/public/js/modal-perfil.js" defer></script>

</body>

</html>