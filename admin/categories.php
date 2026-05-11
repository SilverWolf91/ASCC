<?php

// Charset UTF-8 forzado (header HTTP + interno)
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

/**
 * ASCC - Gestión de Categorías
 * Ruta: admin/categories.php
 * Descripción: Vista de categorías del panel admin.
 *              Lee dinámicamente desde productos.categoria_principal.
 *              Sin tabla separada — refleja la BD en tiempo real.
 */

session_start();

// =============================================================================
// SEGURIDAD
// =============================================================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
if (
    !isset($_SESSION['admin_token']) ||
    $_SESSION['admin_token'] !== hash('sha256', $_SESSION['user_id'] . 'ASCC_ADMIN_SECRET')
) {
    session_destroy();
    header('Location: login.php?error=invalid_session');
    exit;
}

// =============================================================================
// INTERNACIONALIZACIÓN Y TEMA
// =============================================================================
$lang_code = $_SESSION['lang'] ?? $_COOKIE['ag_lang'] ?? 'es';
$lang_file = __DIR__ . "/lang/{$lang_code}.php";
if (!file_exists($lang_file)) {
    $lang_code = 'es';
    $lang_file = __DIR__ . '/lang/es.php';
}
$lang  = require $lang_file;
$theme = $_COOKIE['ag_theme'] ?? 'light';
$theme = in_array($theme, ['light', 'dark']) ? $theme : 'light';

// =============================================================================
// CONEXIÓN
// =============================================================================
require_once __DIR__ . '/../config/database.php';

// =============================================================================
// DATOS — Categorías dinámicas desde productos.categoria_principal
// =============================================================================

// KPIs globales
$total_productos  = (int)$conexion->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$total_categorias = (int)$conexion->query(
    "SELECT COUNT(DISTINCT categoria_principal) FROM productos WHERE categoria_principal IS NOT NULL"
)->fetchColumn();
$valor_inventario = (float)$conexion->query(
    "SELECT COALESCE(SUM(precio * cantidad), 0) FROM productos"
)->fetchColumn();
$productos_disponibles = (int)$conexion->query(
    "SELECT COUNT(*) FROM productos WHERE estado = 'disponible'"
)->fetchColumn();

// Categorías con estadísticas
$stmt = $conexion->query(
    "SELECT
        p.categoria_principal                          AS categoria,
        COUNT(*)                                       AS total_productos,
        SUM(CASE WHEN p.estado = 'disponible' THEN 1 ELSE 0 END) AS disponibles,
        SUM(CASE WHEN p.estado = 'vendido'    THEN 1 ELSE 0 END) AS vendidos,
        COALESCE(SUM(p.precio * p.cantidad), 0)        AS valor_total,
        COALESCE(AVG(p.precio), 0)                     AS precio_promedio,
        COUNT(DISTINCT p.id_usuario)                   AS vendedores_activos
     FROM productos p
     WHERE p.categoria_principal IS NOT NULL
     GROUP BY p.categoria_principal
     ORDER BY total_productos DESC"
);
$categorias = $stmt->fetchAll();

// Categoría más activa (para el highlight)
$cat_top = !empty($categorias) ? $categorias[0]['categoria'] : '';

// Mapa de íconos por categoría
$iconos = [
    'Huevos y Derivados'    => '🥚',
    'Aves de Corral'        => '🐔',
    'Ganado Bovino'         => '🐄',
    'Caballos y Equinos'    => '🐴',
    'Ganado Menor'          => '🐖',
    'Cárnicos y Embutidos'  => '🥩',
    'Lácteos'               => '🥛',
    'Verduras y Hortalizas' => '🥦',
    'Frutas'                => '🍎',
    'Cereales y Granos'     => '🌾',
    'Plantas y Semillas'    => '🌱',
    'Productos Procesados'  => '📦',
    'Peces y Acuicultura'   => '🐟',
];

// Mapa de colores por categoría
$colores = [
    'Huevos y Derivados'    => '#f4a261',
    'Aves de Corral'        => '#e9c46a',
    'Ganado Bovino'         => '#2d6a4f',
    'Caballos y Equinos'    => '#40916c',
    'Ganado Menor'          => '#52b788',
    'Cárnicos y Embutidos'  => '#e76f51',
    'Lácteos'               => '#74c69d',
    'Verduras y Hortalizas' => '#95d5b2',
    'Frutas'                => '#d62828',
    'Cereales y Granos'     => '#f77f00',
    'Plantas y Semillas'    => '#06654a',
    'Productos Procesados'  => '#457b9d',
    'Peces y Acuicultura'   => '#1d3557',
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['nav_categories'] ?> — ASCC Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/admin-categories.css">
</head>

<body>

    <!-- ============================================================
     SIDEBAR
============================================================ -->
    <aside class="ag-sidebar" id="agSidebar">
        <div class="ag-sidebar__logo">
            <span class="ag-sidebar__logo-icon">🌾</span>
            <span class="ag-sidebar__logo-text">ASCC</span>
        </div>
        <nav class="ag-sidebar__nav">
            <p class="ag-sidebar__nav-label"><?= $lang['nav_main'] ?></p>
            <a href="dashboard.php" class="ag-sidebar__link"><i
                    class="fas fa-chart-line"></i><span><?= $lang['nav_dashboard'] ?></span></a>
            <a href="users.php" class="ag-sidebar__link"><i
                    class="fas fa-users"></i><span><?= $lang['nav_users'] ?></span></a>
            <a href="products.php" class="ag-sidebar__link"><i
                    class="fas fa-box-open"></i><span><?= $lang['nav_products'] ?></span></a>
            <a href="transactions.php" class="ag-sidebar__link"><i
                    class="fas fa-credit-card"></i><span><?= $lang['nav_transactions'] ?></span></a>
            <p class="ag-sidebar__nav-label"><?= $lang['nav_content'] ?></p>
            <a href="categories.php" class="ag-sidebar__link ag-sidebar__link--active"><i
                    class="fas fa-tags"></i><span><?= $lang['nav_categories'] ?></span></a>
            <a href="banners.php" class="ag-sidebar__link"><i
                    class="fas fa-image"></i><span><?= $lang['nav_banners'] ?></span></a>
            <a href="notifications.php" class="ag-sidebar__link"><i
                    class="fas fa-bell"></i><span><?= $lang['nav_notifications'] ?></span></a>
            <p class="ag-sidebar__nav-label"><?= $lang['nav_system'] ?></p>
            <a href="configuracion.php" class="ag-sidebar__link"><i
                    class="fas fa-cog"></i><span><?= $lang['nav_settings'] ?></span></a>
            <a href="change-password.php" class="ag-sidebar__link"><i
                    class="fas fa-key"></i><span><?= $lang['cp_nav_label'] ?></span></a>
            <a href="logout.php" class="ag-sidebar__link ag-sidebar__link--danger"><i
                    class="fas fa-sign-out-alt"></i><span><?= $lang['nav_logout'] ?></span></a>
        </nav>
        <button class="ag-sidebar__collapse-btn" id="sidebarToggle">
            <i class="fas fa-chevron-left" id="sidebarToggleIcon"></i>
        </button>
    </aside>

    <!-- ============================================================
     MAIN
============================================================ -->
    <main class="ag-main" id="agMain">

        <!-- TOPBAR -->
        <header class="ag-topbar">
            <div class="ag-topbar__left">
                <button class="ag-topbar__menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
                <div class="ag-topbar__breadcrumb">
                    <span><?= $lang['admin'] ?></span>
                    <i class="fas fa-chevron-right"></i>
                    <span><?= $lang['nav_content'] ?></span>
                    <i class="fas fa-chevron-right"></i>
                    <span class="ag-topbar__breadcrumb--current"><?= $lang['nav_categories'] ?></span>
                </div>
            </div>
            <div class="ag-topbar__right">
                <div class="ag-topbar__lang-toggle">
                    <button class="ag-lang-btn <?= $lang_code === 'es' ? 'ag-lang-btn--active' : '' ?>"
                        onclick="switchLang('es')">ES</button>
                    <span>|</span>
                    <button class="ag-lang-btn <?= $lang_code === 'en' ? 'ag-lang-btn--active' : '' ?>"
                        onclick="switchLang('en')">EN</button>
                </div>
                <button class="ag-theme-toggle" id="themeToggle">
                    <i class="fas <?= $theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>" id="themeIcon"></i>
                </button>
                <div class="ag-topbar__profile">
                    <div class="ag-avatar">
                        <span><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></span>
                    </div>
                    <div class="ag-topbar__profile-info">
                        <span
                            class="ag-topbar__profile-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                        <span class="ag-topbar__profile-role"><?= $lang['admin'] ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- CUERPO -->
        <div class="ag-dashboard-body">

            <!-- CABECERA -->
            <div class="ag-page-header">
                <div>
                    <h1 class="ag-page-header__title"><?= $lang['cat_page_title'] ?></h1>
                    <p class="ag-page-header__subtitle"><?= $lang['cat_page_subtitle'] ?></p>
                </div>
                <div class="ag-page-header__date">
                    <i class="fas fa-calendar-alt"></i>
                    <span id="currentDate"></span>
                </div>
            </div>

            <!-- KPIs -->
            <section class="ac-kpi-row">
                <div class="ac-kpi">
                    <div class="ac-kpi__icon ac-kpi__icon--cat"><i class="fas fa-tags"></i></div>
                    <div class="ac-kpi__info">
                        <span class="ac-kpi__num"><?= $total_categorias ?></span>
                        <span class="ac-kpi__label"><?= $lang['cat_kpi_total_cats'] ?></span>
                    </div>
                </div>
                <div class="ac-kpi">
                    <div class="ac-kpi__icon ac-kpi__icon--prod"><i class="fas fa-box-open"></i></div>
                    <div class="ac-kpi__info">
                        <span class="ac-kpi__num"><?= $total_productos ?></span>
                        <span class="ac-kpi__label"><?= $lang['cat_kpi_total_prods'] ?></span>
                    </div>
                </div>
                <div class="ac-kpi">
                    <div class="ac-kpi__icon ac-kpi__icon--avail"><i class="fas fa-check-circle"></i></div>
                    <div class="ac-kpi__info">
                        <span class="ac-kpi__num"><?= $productos_disponibles ?></span>
                        <span class="ac-kpi__label"><?= $lang['cat_kpi_available'] ?></span>
                    </div>
                </div>
                <div class="ac-kpi">
                    <div class="ac-kpi__icon ac-kpi__icon--val"><i class="fas fa-dollar-sign"></i></div>
                    <div class="ac-kpi__info">
                        <span
                            class="ac-kpi__num ac-kpi__num--sm">$<?= number_format($valor_inventario, 0, ',', '.') ?></span>
                        <span class="ac-kpi__label"><?= $lang['cat_kpi_inventory'] ?></span>
                    </div>
                </div>
            </section>

            <?php if (empty($categorias)): ?>
                <!-- ESTADO VACÍO -->
                <div class="ac-empty">
                    <div class="ac-empty__icon">🌾</div>
                    <h3 class="ac-empty__title"><?= $lang['cat_empty_title'] ?></h3>
                    <p class="ac-empty__text"><?= $lang['cat_empty_text'] ?></p>
                    <a href="products.php" class="ac-empty__btn">
                        <i class="fas fa-box-open"></i> <?= $lang['cat_empty_btn'] ?>
                    </a>
                </div>

            <?php else: ?>

                <!-- GRID DE CATEGORÍAS -->
                <section class="ac-grid">
                    <?php foreach ($categorias as $i => $cat):
                        $nombre  = $cat['categoria'];
                        $icono   = $iconos[$nombre]  ?? '📦';
                        $color   = $colores[$nombre] ?? '#94a3b8';
                        $pct_disp = $cat['total_productos'] > 0
                            ? round(($cat['disponibles'] / $cat['total_productos']) * 100)
                            : 0;
                        $es_top  = ($nombre === $cat_top);
                    ?>
                        <div class="ac-card <?= $es_top ? 'ac-card--top' : '' ?>" style="--ac-color: <?= $color ?>;">
                            <?php if ($es_top): ?>
                                <span class="ac-card__top-badge"><?= $lang['cat_top_badge'] ?></span>
                            <?php endif; ?>

                            <!-- Header de la card -->
                            <div class="ac-card__header">
                                <div class="ac-card__icon"><?= $icono ?></div>
                                <div class="ac-card__title-block">
                                    <h3 class="ac-card__name"><?= htmlspecialchars($nombre) ?></h3>
                                    <span class="ac-card__count">
                                        <?= $cat['total_productos'] ?>
                                        <?= $cat['total_productos'] === 1 ? $lang['cat_product_sing'] : $lang['cat_product_pl'] ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Barra de disponibilidad -->
                            <div class="ac-card__bar-wrap">
                                <div class="ac-card__bar-track">
                                    <div class="ac-card__bar-fill"
                                        style="width: <?= $pct_disp ?>%; background-color: <?= $color ?>;"></div>
                                </div>
                                <span class="ac-card__bar-label"><?= $pct_disp ?>% <?= $lang['cat_available_label'] ?></span>
                            </div>

                            <!-- Stats -->
                            <div class="ac-card__stats">
                                <div class="ac-card__stat">
                                    <span class="ac-card__stat-val ac-card__stat-val--green"><?= $cat['disponibles'] ?></span>
                                    <span class="ac-card__stat-label"><?= $lang['cat_stat_available'] ?></span>
                                </div>
                                <div class="ac-card__stat">
                                    <span class="ac-card__stat-val ac-card__stat-val--amber"><?= $cat['vendidos'] ?></span>
                                    <span class="ac-card__stat-label"><?= $lang['cat_stat_sold'] ?></span>
                                </div>
                                <div class="ac-card__stat">
                                    <span class="ac-card__stat-val"><?= $cat['vendedores_activos'] ?></span>
                                    <span class="ac-card__stat-label"><?= $lang['cat_stat_sellers'] ?></span>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="ac-card__footer">
                                <div class="ac-card__footer-price">
                                    <span class="ac-card__footer-label"><?= $lang['cat_avg_price'] ?></span>
                                    <span
                                        class="ac-card__footer-val">$<?= number_format($cat['precio_promedio'], 0, ',', '.') ?></span>
                                </div>
                                <a href="products.php?categoria=<?= urlencode($nombre) ?>" class="ac-card__footer-link">
                                    <?= $lang['cat_see_products'] ?> <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>

                <!-- GRÁFICA DE BARRAS HORIZONTAL -->
                <section class="ac-chart-section">
                    <div class="ac-chart-card">
                        <div class="ac-chart-card__header">
                            <h3><?= $lang['cat_chart_title'] ?></h3>
                            <span class="ac-chart-card__sub"><?= $lang['cat_chart_sub'] ?></span>
                        </div>
                        <div class="ac-chart-card__body">
                            <canvas id="catChart"></canvas>
                        </div>
                    </div>
                </section>

            <?php endif; ?>

        </div><!-- /.ag-dashboard-body -->
    </main>

    <!-- Overlay móvil -->
    <div class="ag-overlay" id="agOverlay"></div>

    <!-- Datos para JS -->
    <script>
        window.ASCC_CATS = {
            labels: <?= json_encode(array_column($categorias, 'categoria')) ?>,
            totales: <?= json_encode(array_map('intval', array_column($categorias, 'total_productos'))) ?>,
            dispon: <?= json_encode(array_map('intval', array_column($categorias, 'disponibles'))) ?>,
            colors: <?= json_encode(array_values(array_map(fn($c) => $colores[$c['categoria']] ?? '#94a3b8', $categorias))) ?>,
            theme: '<?= $theme ?>',
            lang: {
                disponibles: '<?= addslashes($lang["cat_stat_available"]) ?>',
                vendidos: '<?= addslashes($lang["cat_stat_sold"]) ?>',
            }
        };
    </script>
    <script src="assets/js/admin-dashboard.js"></script>
    <script src="assets/js/admin-categories.js"></script>

</body>

</html>