<?php
/**
 * ASCC — Admin Reportes
 * Ruta: admin/reportes.php
 *
 * Panel de métricas globales de la plataforma para el administrador.
 * Incluye: KPIs en tiempo real, gráficas Chart.js, gestión de usuarios,
 *          inventario global, denuncias, ranking y exportación.
 */

session_start();

// ── Seguridad triple capa ──────────────────────────────────────────────────
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

// ── i18n ───────────────────────────────────────────────────────────────────
$lang_code = $_SESSION['lang'] ?? $_COOKIE['ag_lang'] ?? 'es';
$lang_file = __DIR__ . "/lang/{$lang_code}.php";
if (!file_exists($lang_file)) { $lang_code = 'es'; $lang_file = __DIR__ . '/lang/es.php'; }
$lang = require $lang_file;

// ── Tema ───────────────────────────────────────────────────────────────────
$theme = $_COOKIE['ag_theme'] ?? 'light';
$theme = in_array($theme, ['light','dark']) ? $theme : 'light';

// ── Base de datos ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';

// ── CSRF ───────────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];

// ── KPIs iniciales (SSR para primera carga rápida) ─────────────────────────
$kpi = [
    'usuarios_total'    => 0,
    'usuarios_hoy'      => 0,
    'productos_activos' => 0,
    'productos_hoy'     => 0,
    'ventas_mes'        => 0,
    'denuncias_abiertas'=> 0,
    'visitas_hoy'       => 0,
    'conversion'        => 0,
];

try {
    $kpi['usuarios_total']    = (int)$conexion->query("SELECT COUNT(*) FROM usuarios WHERE rol != 'admin'")->fetchColumn();
    $kpi['usuarios_hoy']      = (int)$conexion->query("SELECT COUNT(*) FROM usuarios WHERE DATE(fecha_registro) = CURDATE() AND rol != 'admin'")->fetchColumn();
    $kpi['productos_activos'] = (int)$conexion->query("SELECT COUNT(*) FROM productos WHERE estado = 'disponible'")->fetchColumn();
    $kpi['productos_hoy']     = (int)$conexion->query("SELECT COUNT(*) FROM productos WHERE DATE(fecha_publicacion) = CURDATE()")->fetchColumn();
    $kpi['ventas_mes']        = (float)($conexion->query("SELECT COALESCE(SUM(total),0) FROM transacciones WHERE estado='APROBADO' AND MONTH(fecha_creacion)=MONTH(NOW()) AND YEAR(fecha_creacion)=YEAR(NOW())")->fetchColumn() ?? 0);
    $kpi['denuncias_abiertas']= (int)$conexion->query("SELECT COUNT(*) FROM reportes_denuncias WHERE estado NOT IN ('resuelta','cerrada')")->fetchColumn();

    $vp = (int)$conexion->query("SELECT COUNT(*) FROM visitas_producto WHERE DATE(fecha_visita) = CURDATE()")->fetchColumn();
    $vf = (int)$conexion->query("SELECT COUNT(*) FROM visitas_perfil   WHERE DATE(fecha_visita) = CURDATE()")->fetchColumn();
    $kpi['visitas_hoy'] = $vp + $vf;

    $vis30 = (int)$conexion->query("SELECT COUNT(*) FROM visitas_producto WHERE fecha_visita >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $ven30 = (int)$conexion->query("SELECT COUNT(*) FROM transacciones WHERE estado='APROBADO' AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $kpi['conversion'] = $vis30 > 0 ? round(($ven30 / $vis30) * 100, 1) : 0;
} catch (PDOException $e) {
    error_log('ASCC Admin Reportes KPI: ' . $e->getMessage());
}

// ── Conteo de denuncias urgentes para badge ────────────────────────────────
$denuncias_urgentes = 0;
try {
    $denuncias_urgentes = (int)$conexion->query(
        "SELECT COUNT(*) FROM reportes_denuncias
         WHERE prioridad = 'alta' AND estado NOT IN ('resuelta','cerrada')"
    )->fetchColumn();
} catch (PDOException $e) {}

// ── Helpers de formato ─────────────────────────────────────────────────────
function fmt_cop(float $n): string {
    return '$' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['rep_admin_title'] ?? 'Reportes' ?> — ASCC Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/reportes-admin.css">
</head>

<body>

    <!-- =====================================================================
         SIDEBAR
    ====================================================================== -->
    <aside class="ag-sidebar" id="agSidebar">

        <div class="ag-sidebar__logo">
            <span class="ag-sidebar__logo-icon">🌾</span>
            <span class="ag-sidebar__logo-text">ASCC</span>
        </div>

        <nav class="ag-sidebar__nav">
            <p class="ag-sidebar__nav-label"><?= $lang['nav_main'] ?></p>

            <a href="dashboard.php" class="ag-sidebar__link">
                <i class="fas fa-chart-line"></i>
                <span><?= $lang['nav_dashboard'] ?></span>
            </a>
            <a href="users.php" class="ag-sidebar__link">
                <i class="fas fa-users"></i>
                <span><?= $lang['nav_users'] ?></span>
            </a>
            <a href="products.php" class="ag-sidebar__link">
                <i class="fas fa-box-open"></i>
                <span><?= $lang['nav_products'] ?></span>
            </a>
            <a href="transactions.php" class="ag-sidebar__link">
                <i class="fas fa-credit-card"></i>
                <span><?= $lang['nav_transactions'] ?></span>
            </a>

            <p class="ag-sidebar__nav-label"><?= $lang['nav_content'] ?></p>

            <a href="categories.php" class="ag-sidebar__link">
                <i class="fas fa-tags"></i>
                <span><?= $lang['nav_categories'] ?></span>
            </a>
            <a href="banners.php" class="ag-sidebar__link">
                <i class="fas fa-image"></i>
                <span><?= $lang['nav_banners'] ?></span>
            </a>
            <a href="notifications.php" class="ag-sidebar__link">
                <i class="fas fa-bell"></i>
                <span><?= $lang['nav_notifications'] ?></span>
            </a>

            <p class="ag-sidebar__nav-label"><?= $lang['nav_system'] ?></p>

            <a href="reportes.php" class="ag-sidebar__link ag-sidebar__link--active">
                <i class="fas fa-chart-bar"></i>
                <span><?= $lang['nav_reports'] ?? 'Reportes' ?></span>
                <?php if ($denuncias_urgentes > 0): ?>
                <span class="ag-badge ag-badge--red"><?= $denuncias_urgentes ?></span>
                <?php endif; ?>
            </a>
            <a href="configuracion.php" class="ag-sidebar__link">
                <i class="fas fa-cog"></i>
                <span><?= $lang['nav_settings'] ?></span>
            </a>
            <a href="change-password.php" class="ag-sidebar__link">
                <i class="fas fa-key"></i>
                <span><?= $lang['cp_nav_label'] ?></span>
            </a>
            <a href="logout.php" class="ag-sidebar__link ag-sidebar__link--danger">
                <i class="fas fa-sign-out-alt"></i>
                <span><?= $lang['nav_logout'] ?></span>
            </a>
        </nav>

    </aside>

    <!-- =====================================================================
         CONTENIDO PRINCIPAL
    ====================================================================== -->
    <main class="ag-main">

        <!-- TOPBAR -->
        <header class="ag-topbar">
            <button class="ag-topbar__toggle" id="sidebarToggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="ag-topbar__right">
                <div class="ag-theme-toggle" id="themeToggle" onclick="switchTheme()">
                    <span class="ag-theme-toggle__label"><?= $lang['theme_light'] ?? 'Modo Claro' ?></span>
                    <div class="ag-theme-toggle__btn <?= $theme === 'light' ? 'active' : '' ?>"></div>
                </div>
                <div class="ag-lang-switcher">
                    <button class="ag-lang-btn <?= $lang_code === 'es' ? 'active' : '' ?>"
                        onclick="switchLang('es')">ES</button>
                    <button class="ag-lang-btn <?= $lang_code === 'en' ? 'active' : '' ?>"
                        onclick="switchLang('en')">EN</button>
                </div>
            </div>
        </header>

        <!-- BODY -->
        <div class="rep-body">

            <!-- ── CABECERA ─────────────────────────────────────────── -->
            <div class="rep-header">
                <div>
                    <h1 class="rep-header__title">
                        <i class="fas fa-chart-bar" style="color:var(--ag-brand-primary)"></i>
                        <?= $lang['rep_admin_title'] ?? 'Reportes de Plataforma' ?>
                    </h1>
                    <p class="rep-header__subtitle">
                        <?= $lang['rep_admin_subtitle'] ?? 'Métricas globales de ASCC' ?>
                    </p>
                </div>
                <div class="rep-header__actions">
                    <span class="rep-last-update" id="lastUpdate">
                        <?= date('d/m/Y H:i') ?>
                    </span>
                    <button class="rep-refresh-btn" onclick="RepAdmin.actualizarKpis()">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- ── TABS ─────────────────────────────────────────────── -->
            <div class="rep-tabs-wrapper">
                <button class="rep-tab-btn active" data-tab="metricas">
                    <i class="fas fa-chart-line"></i> <?= $lang['rep_tab_metricas'] ?? 'Métricas' ?>
                </button>
                <button class="rep-tab-btn" data-tab="usuarios">
                    <i class="fas fa-users"></i> <?= $lang['rep_tab_usuarios'] ?? 'Usuarios' ?>
                </button>
                <button class="rep-tab-btn" data-tab="productos">
                    <i class="fas fa-box-open"></i> <?= $lang['rep_tab_productos'] ?? 'Productos' ?>
                </button>
                <button class="rep-tab-btn" data-tab="denuncias">
                    <i class="fas fa-flag"></i> <?= $lang['rep_tab_denuncias'] ?? 'Denuncias' ?>
                    <?php if ($denuncias_urgentes > 0): ?>
                    <span class="rep-badge rep-badge--danger"><?= $denuncias_urgentes ?></span>
                    <?php endif; ?>
                </button>
                <button class="rep-tab-btn" data-tab="ranking">
                    <i class="fas fa-trophy"></i> <?= $lang['rep_tab_ranking'] ?? 'Ranking' ?>
                </button>
                <button class="rep-tab-btn" data-tab="exportar">
                    <i class="fas fa-download"></i> <?= $lang['rep_tab_exportar'] ?? 'Exportar' ?>
                </button>
            </div>

            <!-- ══════════════════════════════════════════════════════
                 TAB 1 — MÉTRICAS
            ═══════════════════════════════════════════════════════ -->
            <div id="tab-metricas" class="rep-tab-panel active">

                <!-- KPIs -->
                <div class="rep-kpi-grid">
                    <div class="rep-kpi-card rep-kpi-card--success">
                        <div class="rep-kpi-icon"><i class="fas fa-users"></i></div>
                        <div class="rep-kpi-value" id="kpiUsuariosTotal"><?= number_format($kpi['usuarios_total']) ?>
                        </div>
                        <div class="rep-kpi-label"><?= $lang['rep_kpi_usuarios_total'] ?? 'Usuarios totales' ?></div>
                        <div class="rep-kpi-trend">
                            <i class="fas fa-arrow-up"></i>
                            <span id="kpiUsuariosHoy">+<?= $kpi['usuarios_hoy'] ?></span> hoy
                        </div>
                    </div>

                    <div class="rep-kpi-card rep-kpi-card--info">
                        <div class="rep-kpi-icon"><i class="fas fa-box-open"></i></div>
                        <div class="rep-kpi-value" id="kpiProductosActivos">
                            <?= number_format($kpi['productos_activos']) ?></div>
                        <div class="rep-kpi-label"><?= $lang['rep_kpi_productos_activos'] ?? 'Productos activos' ?>
                        </div>
                        <div class="rep-kpi-trend">
                            <i class="fas fa-plus"></i>
                            <span id="kpiProductosHoy">+<?= $kpi['productos_hoy'] ?></span> hoy
                        </div>
                    </div>

                    <div class="rep-kpi-card rep-kpi-card--success">
                        <div class="rep-kpi-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="rep-kpi-value" id="kpiVentasMes"><?= fmt_cop($kpi['ventas_mes']) ?></div>
                        <div class="rep-kpi-label"><?= $lang['rep_kpi_ventas_mes'] ?? 'Ventas del mes' ?></div>
                        <div class="rep-kpi-trend rep-kpi-trend--neutral">
                            <span><?= $lang['rep_kpi_ventas_vs'] ?? 'vs mes anterior' ?></span>
                        </div>
                    </div>

                    <div
                        class="rep-kpi-card <?= $kpi['denuncias_abiertas'] > 0 ? 'rep-kpi-card--danger' : 'rep-kpi-card--success' ?>">
                        <div class="rep-kpi-icon"><i class="fas fa-flag"></i></div>
                        <div class="rep-kpi-value" id="kpiDenuncias"><?= $kpi['denuncias_abiertas'] ?></div>
                        <div class="rep-kpi-label"><?= $lang['rep_kpi_denuncias_abiertas'] ?? 'Denuncias abiertas' ?>
                        </div>
                        <div class="rep-kpi-trend <?= $denuncias_urgentes > 0 ? 'rep-kpi-trend--down' : '' ?>">
                            <?php if ($denuncias_urgentes > 0): ?>
                            <i class="fas fa-exclamation-triangle"></i> <?= $denuncias_urgentes ?>
                            <?= $lang['rep_kpi_denuncias_urgentes'] ?? 'urgentes' ?>
                            <?php else: ?>
                            Sin urgentes
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="rep-kpi-card rep-kpi-card--info">
                        <div class="rep-kpi-icon"><i class="fas fa-eye"></i></div>
                        <div class="rep-kpi-value" id="kpiVisitasHoy"><?= number_format($kpi['visitas_hoy']) ?></div>
                        <div class="rep-kpi-label"><?= $lang['rep_kpi_visitas_hoy'] ?? 'Visitas hoy' ?></div>
                        <div class="rep-kpi-trend rep-kpi-trend--neutral">
                            Productos + perfiles
                        </div>
                    </div>

                    <div class="rep-kpi-card rep-kpi-card--warning">
                        <div class="rep-kpi-icon"><i class="fas fa-percentage"></i></div>
                        <div class="rep-kpi-value" id="kpiConversion"><?= $kpi['conversion'] ?>%</div>
                        <div class="rep-kpi-label"><?= $lang['rep_kpi_conversion'] ?? 'Conversión global' ?></div>
                        <div class="rep-kpi-trend rep-kpi-trend--neutral">
                            Últimos 30 días
                        </div>
                    </div>
                </div>

                <!-- Gráficas fila 1 -->
                <div class="rep-charts-grid rep-charts-grid--3">
                    <div class="rep-card">
                        <div class="rep-card__header">
                            <span class="rep-card__title">
                                <i class="fas fa-chart-line" style="color:#10b981"></i>
                                <?= $lang['rep_graf_ventas_diarias'] ?? 'Ventas diarias' ?>
                            </span>
                        </div>
                        <div class="rep-card__body">
                            <div class="rep-chart-wrap">
                                <canvas id="chartVentasGlobal"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="rep-card">
                        <div class="rep-card__header">
                            <span class="rep-card__title">
                                <i class="fas fa-users" style="color:#3b82f6"></i>
                                <?= $lang['rep_graf_usuarios_rol'] ?? 'Usuarios por rol' ?>
                            </span>
                        </div>
                        <div class="rep-card__body">
                            <div class="rep-chart-wrap rep-chart-wrap--sm">
                                <canvas id="chartRoles"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficas fila 2 -->
                <div class="rep-charts-grid">
                    <div class="rep-card">
                        <div class="rep-card__header">
                            <span class="rep-card__title">
                                <i class="fas fa-tags" style="color:#f59e0b"></i>
                                <?= $lang['rep_graf_categorias'] ?? 'Categorías' ?>
                            </span>
                        </div>
                        <div class="rep-card__body">
                            <div class="rep-chart-wrap">
                                <canvas id="chartCategoriasGlobal"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="rep-card">
                        <div class="rep-card__header">
                            <span class="rep-card__title">
                                <i class="fas fa-clock" style="color:#8b5cf6"></i>
                                <?= $lang['rep_graf_actividad_hora'] ?? 'Actividad por hora' ?>
                            </span>
                        </div>
                        <div class="rep-card__body">
                            <div class="rep-chart-wrap">
                                <canvas id="chartActividad"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /tab-metricas -->


            <!-- ══════════════════════════════════════════════════════
                 TAB 2 — USUARIOS
            ═══════════════════════════════════════════════════════ -->
            <div id="tab-usuarios" class="rep-tab-panel">

                <div class="rep-card">
                    <div class="rep-card__header">
                        <span class="rep-card__title">
                            <i class="fas fa-users" style="color:#10b981"></i>
                            <?= $lang['rep_usr_titulo'] ?? 'Gestión de usuarios' ?>
                        </span>
                    </div>
                    <div class="rep-card__body">

                        <div class="rep-filter-row">
                            <button class="rep-filter-btn rep-filter-usuarios active" data-filtro="todos">
                                <?= $lang['rep_usr_todos'] ?? 'Todos' ?>
                            </button>
                            <button class="rep-filter-btn rep-filter-usuarios" data-filtro="vendedor">
                                <?= $lang['rep_usr_vendedores'] ?? 'Vendedores' ?>
                            </button>
                            <button class="rep-filter-btn rep-filter-usuarios" data-filtro="comprador">
                                <?= $lang['rep_usr_compradores'] ?? 'Compradores' ?>
                            </button>
                            <button class="rep-filter-btn rep-filter-usuarios" data-filtro="mixto">
                                <?= $lang['rep_usr_mixtos'] ?? 'Mixtos' ?>
                            </button>
                            <input type="text" id="searchUsuarios" class="rep-search-input"
                                placeholder="🔍 Buscar por nombre o email...">
                        </div>

                    </div>

                    <div class="rep-card__body rep-card__body--flush">
                        <div class="rep-table-wrap">
                            <table class="rep-table">
                                <thead>
                                    <tr>
                                        <th><?= $lang['rep_usr_col_nombre'] ?? 'Nombre' ?></th>
                                        <th><?= $lang['rep_usr_col_rol'] ?? 'Rol' ?></th>
                                        <th style="text-align:center">
                                            <?= $lang['rep_usr_col_productos'] ?? 'Productos' ?></th>
                                        <th style="text-align:center"><?= $lang['rep_usr_col_ventas'] ?? 'Ventas' ?>
                                        </th>
                                        <th><?= $lang['rep_usr_col_calificacion'] ?? 'Calificación' ?></th>
                                        <th><?= $lang['rep_usr_col_registro'] ?? 'Registro' ?></th>
                                        <th><?= $lang['rep_usr_col_estado'] ?? 'Estado' ?></th>
                                        <th><?= $lang['rep_usr_col_acciones'] ?? 'Acciones' ?></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyUsuarios">
                                    <tr>
                                        <td colspan="8"
                                            style="text-align:center;padding:30px;color:var(--ag-text-muted)">
                                            <i class="fas fa-spinner fa-spin"></i> Cargando...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div><!-- /tab-usuarios -->


            <!-- ══════════════════════════════════════════════════════
                 TAB 3 — PRODUCTOS
            ═══════════════════════════════════════════════════════ -->
            <div id="tab-productos" class="rep-tab-panel">

                <div class="rep-card">
                    <div class="rep-card__header">
                        <span class="rep-card__title">
                            <i class="fas fa-box-open" style="color:#3b82f6"></i>
                            <?= $lang['rep_prod_titulo'] ?? 'Inventario global' ?>
                        </span>
                    </div>
                    <div class="rep-card__body">
                        <div class="rep-filter-row">
                            <input type="text" id="searchProductos" class="rep-search-input"
                                placeholder="🔍 Buscar por producto, código o vendedor...">
                        </div>
                    </div>

                    <div class="rep-card__body rep-card__body--flush">
                        <div class="rep-table-wrap">
                            <table class="rep-table">
                                <thead>
                                    <tr>
                                        <th><?= $lang['rep_prod_col_codigo'] ?? 'Código' ?></th>
                                        <th><?= $lang['rep_prod_col_producto'] ?? 'Producto' ?></th>
                                        <th><?= $lang['rep_prod_col_vendedor'] ?? 'Vendedor' ?></th>
                                        <th><?= $lang['rep_prod_col_categoria'] ?? 'Categoría' ?></th>
                                        <th style="text-align:right"><?= $lang['rep_prod_col_precio'] ?? 'Precio' ?>
                                        </th>
                                        <th style="text-align:center"><?= $lang['rep_prod_col_stock'] ?? 'Stock' ?></th>
                                        <th style="text-align:center"><?= $lang['rep_prod_col_visitas'] ?? 'Visitas' ?>
                                        </th>
                                        <th><?= $lang['rep_prod_col_estado'] ?? 'Estado' ?></th>
                                        <th><?= $lang['rep_prod_col_fecha'] ?? 'Publicado' ?></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyProductos">
                                    <tr>
                                        <td colspan="9"
                                            style="text-align:center;padding:30px;color:var(--ag-text-muted)">
                                            <i class="fas fa-spinner fa-spin"></i> Cargando...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div><!-- /tab-productos -->


            <!-- ══════════════════════════════════════════════════════
                 TAB 4 — DENUNCIAS
            ═══════════════════════════════════════════════════════ -->
            <div id="tab-denuncias" class="rep-tab-panel">

                <?php if ($denuncias_urgentes > 0): ?>
                <div class="rep-alert-banner rep-alert-banner--danger">
                    <i class="fas fa-exclamation-triangle" style="flex-shrink:0;margin-top:2px"></i>
                    <span>Hay <strong><?= $denuncias_urgentes ?></strong> denuncia(s) de alta prioridad sin resolver que
                        requieren atención inmediata.</span>
                </div>
                <?php endif; ?>

                <div class="rep-card">
                    <div class="rep-card__header">
                        <span class="rep-card__title">
                            <i class="fas fa-flag" style="color:#ef4444"></i>
                            <?= $lang['rep_den_titulo'] ?? 'Gestión de denuncias' ?>
                        </span>
                    </div>
                    <div class="rep-card__body">
                        <div class="rep-filter-row">
                            <button class="rep-filter-btn rep-filter-denuncias active" data-filtro="todas">
                                <?= $lang['rep_den_todas'] ?? 'Todas' ?>
                            </button>
                            <button class="rep-filter-btn rep-filter-denuncias" data-filtro="recibidas">
                                <?= $lang['rep_den_recibidas'] ?? 'Recibidas' ?>
                            </button>
                            <button class="rep-filter-btn rep-filter-denuncias" data-filtro="en_revision">
                                <?= $lang['rep_den_en_revision'] ?? 'En revisión' ?>
                            </button>
                            <button class="rep-filter-btn rep-filter-denuncias" data-filtro="resueltas">
                                <?= $lang['rep_den_resueltas'] ?? 'Resueltas' ?>
                            </button>
                        </div>
                    </div>

                    <div class="rep-card__body rep-card__body--flush">
                        <div class="rep-table-wrap">
                            <table class="rep-table">
                                <thead>
                                    <tr>
                                        <th><?= $lang['rep_den_col_id'] ?? 'ID' ?></th>
                                        <th><?= $lang['rep_den_col_denunciante'] ?? 'Denunciante' ?></th>
                                        <th><?= $lang['rep_den_col_denunciado'] ?? 'Denunciado' ?></th>
                                        <th><?= $lang['rep_den_col_categoria'] ?? 'Categoría' ?></th>
                                        <th><?= $lang['rep_den_col_prioridad'] ?? 'Prioridad' ?></th>
                                        <th><?= $lang['rep_den_col_estado'] ?? 'Estado' ?></th>
                                        <th><?= $lang['rep_den_col_fecha'] ?? 'Fecha' ?></th>
                                        <th><?= $lang['rep_den_col_acciones'] ?? 'Acciones' ?></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyDenuncias">
                                    <tr>
                                        <td colspan="8"
                                            style="text-align:center;padding:30px;color:var(--ag-text-muted)">
                                            <i class="fas fa-spinner fa-spin"></i> Cargando...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div><!-- /tab-denuncias -->


            <!-- ══════════════════════════════════════════════════════
                 TAB 5 — RANKING
            ═══════════════════════════════════════════════════════ -->
            <div id="tab-ranking" class="rep-tab-panel">

                <div class="rep-charts-grid">

                    <div class="rep-card">
                        <div class="rep-card__header">
                            <span class="rep-card__title">
                                <i class="fas fa-star" style="color:#f59e0b"></i>
                                <?= $lang['rep_rank_top_vendedores'] ?? 'Top vendedores mejor valorados' ?>
                            </span>
                        </div>
                        <div class="rep-card__body">
                            <div class="rep-chart-wrap">
                                <canvas id="chartRankVendedores"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="rep-card">
                        <div class="rep-card__header">
                            <span class="rep-card__title">
                                <i class="fas fa-shopping-cart" style="color:#3b82f6"></i>
                                <?= $lang['rep_rank_top_compradores'] ?? 'Compradores más activos' ?>
                            </span>
                        </div>
                        <div class="rep-card__body rep-card__body--flush">
                            <div class="rep-table-wrap">
                                <table class="rep-table">
                                    <thead>
                                        <tr>
                                            <th style="width:40px"><?= $lang['rep_rank_col_pos'] ?? '#' ?></th>
                                            <th><?= $lang['rep_rank_col_nombre'] ?? 'Nombre' ?></th>
                                            <th style="text-align:center">
                                                <?= $lang['rep_rank_col_compras'] ?? 'Compras' ?></th>
                                            <th style="text-align:right">Total gastado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyRankCompradores">
                                        <tr>
                                            <td colspan="4"
                                                style="text-align:center;padding:20px;color:var(--ag-text-muted)">
                                                <i class="fas fa-spinner fa-spin"></i>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

            </div><!-- /tab-ranking -->


            <!-- ══════════════════════════════════════════════════════
                 TAB 6 — EXPORTAR
            ═══════════════════════════════════════════════════════ -->
            <div id="tab-exportar" class="rep-tab-panel">

                <div class="rep-card">
                    <div class="rep-card__header">
                        <span class="rep-card__title">
                            <i class="fas fa-check-square" style="color:#10b981"></i>
                            Selecciona qué exportar
                        </span>
                    </div>
                    <div class="rep-card__body">
                        <div class="rep-export-grid">
                            <label class="rep-check-item">
                                <input type="checkbox" class="rep-check-export" value="usuarios" checked>
                                <div>
                                    <div class="rep-check-title">👥 <?= $lang['rep_exp_usuarios'] ?? 'Usuarios' ?></div>
                                    <div class="rep-check-sub">Nombre, rol, estado, calificación, registro</div>
                                </div>
                            </label>
                            <label class="rep-check-item">
                                <input type="checkbox" class="rep-check-export" value="productos" checked>
                                <div>
                                    <div class="rep-check-title">📦 <?= $lang['rep_exp_productos'] ?? 'Productos' ?>
                                    </div>
                                    <div class="rep-check-sub">Código, precio, stock, estado, vendedor</div>
                                </div>
                            </label>
                            <label class="rep-check-item">
                                <input type="checkbox" class="rep-check-export" value="ventas" checked>
                                <div>
                                    <div class="rep-check-title">💰 <?= $lang['rep_exp_ventas'] ?? 'Ventas' ?></div>
                                    <div class="rep-check-sub">Todas las transacciones de la plataforma</div>
                                </div>
                            </label>
                            <label class="rep-check-item">
                                <input type="checkbox" class="rep-check-export" value="denuncias" checked>
                                <div>
                                    <div class="rep-check-title">🚨 <?= $lang['rep_exp_denuncias'] ?? 'Denuncias' ?>
                                    </div>
                                    <div class="rep-check-sub">Tickets, estado, prioridad, fechas</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="rep-export-btns">
                    <button class="rep-export-btn" id="btnExcelAdmin">
                        📊 <?= $lang['rep_exp_excel'] ?? 'Excel completo' ?>
                        <small>Abre en Excel con formato</small>
                    </button>
                    <button class="rep-export-btn" id="btnCsvAdmin">
                        📄 <?= $lang['rep_exp_csv'] ?? 'CSV para Power BI' ?>
                        <small>Optimizado para Power BI Desktop</small>
                    </button>
                </div>

                <!-- Info Power BI -->
                <div class="rep-card">
                    <div class="rep-card__header">
                        <span class="rep-card__title">
                            <i class="fas fa-chart-bar" style="color:#f59e0b"></i>
                            Conexión con Power BI Desktop
                        </span>
                    </div>
                    <div class="rep-card__body">
                        <p style="font-size:0.875rem;color:var(--ag-text-secondary);margin-bottom:16px;line-height:1.6">
                            Descarga el CSV y ábrelo en Power BI Desktop para crear dashboards ejecutivos avanzados.
                            Cuando subas ASCC a producción (Hostinger), podrás conectar Power BI directamente via API
                            con actualización automática.
                        </p>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                            <div style="padding:12px;background:var(--ag-bg-hover);border-radius:8px">
                                <div
                                    style="font-size:0.75rem;font-weight:600;color:var(--ag-brand-primary);margin-bottom:4px">
                                    PASO 1</div>
                                <div style="font-size:0.8125rem;color:var(--ag-text-primary)">Descarga Power BI Desktop
                                    gratis</div>
                                <a href="https://www.microsoft.com/es-es/power-platform/products/power-bi/downloads"
                                    target="_blank"
                                    style="font-size:0.75rem;color:var(--ag-brand-primary);text-decoration:none">
                                    microsoft.com/power-bi →
                                </a>
                            </div>
                            <div style="padding:12px;background:var(--ag-bg-hover);border-radius:8px">
                                <div
                                    style="font-size:0.75rem;font-weight:600;color:var(--ag-brand-primary);margin-bottom:4px">
                                    PASO 2</div>
                                <div style="font-size:0.8125rem;color:var(--ag-text-primary)">Descarga el CSV de esta
                                    página</div>
                            </div>
                            <div style="padding:12px;background:var(--ag-bg-hover);border-radius:8px">
                                <div
                                    style="font-size:0.75rem;font-weight:600;color:var(--ag-brand-primary);margin-bottom:4px">
                                    PASO 3</div>
                                <div style="font-size:0.8125rem;color:var(--ag-text-primary)">Abre Power BI → Obtener
                                    datos → CSV → Selecciona el archivo</div>
                            </div>
                            <div style="padding:12px;background:var(--ag-bg-hover);border-radius:8px">
                                <div
                                    style="font-size:0.75rem;font-weight:600;color:var(--ag-brand-primary);margin-bottom:4px">
                                    PASO 4</div>
                                <div style="font-size:0.8125rem;color:var(--ag-text-primary)">Arrastra columnas y crea
                                    gráficas automáticamente</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /tab-exportar -->

        </div><!-- /rep-body -->

    </main><!-- /ag-main -->

    <!-- =====================================================================
         MODAL — GESTIONAR DENUNCIA
    ====================================================================== -->
    <div class="rep-modal-backdrop" id="modalDenBackdrop">
        <div class="rep-modal">
            <div class="rep-modal__header">
                <span class="rep-modal__title">
                    <i class="fas fa-flag" style="color:#ef4444;margin-right:8px"></i>
                    Gestionar denuncia <span id="modalDenId"></span>
                </span>
                <button class="rep-modal__close" onclick="RepAdmin.cerrarModalDenuncia()">✕</button>
            </div>
            <div class="rep-modal__body">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:12px;
                            background:var(--ag-bg-hover);border-radius:8px;font-size:0.8125rem">
                    <div>
                        <span style="color:var(--ag-text-secondary)">Denunciante:</span>
                        <strong id="modalDenDenunciante" style="color:var(--ag-text-primary);margin-left:6px">—</strong>
                    </div>
                    <div>
                        <span style="color:var(--ag-text-secondary)">Denunciado:</span>
                        <strong id="modalDenDenunciado" style="color:var(--ag-text-primary);margin-left:6px">—</strong>
                    </div>
                    <div>
                        <span style="color:var(--ag-text-secondary)">Fecha:</span>
                        <span id="modalDenFecha" style="margin-left:6px">—</span>
                    </div>
                </div>

                <div style="padding:12px;background:var(--ag-bg-hover);border-radius:8px;font-size:0.8125rem">
                    <div
                        style="color:var(--ag-text-secondary);margin-bottom:6px;font-weight:600;text-transform:uppercase;font-size:0.75rem">
                        Descripción</div>
                    <p id="modalDenDescripcion" style="color:var(--ag-text-primary);line-height:1.5">—</p>
                </div>

                <div class="rep-modal__field">
                    <label><?= $lang['rep_den_cambiar_estado'] ?? 'Cambiar estado' ?></label>
                    <select id="modalDenEstado" class="rep-modal__select">
                        <option value="recibida"><?= $lang['rep_estado_recibida'] ?? 'Recibida' ?></option>
                        <option value="en_revision"><?= $lang['rep_estado_en_revision'] ?? 'En revisión' ?></option>
                        <option value="pendiente_vendedor">
                            <?= $lang['rep_estado_pendiente_vendedor'] ?? 'Pendiente vendedor' ?></option>
                        <option value="resuelta"><?= $lang['rep_estado_resuelta'] ?? 'Resuelta' ?></option>
                        <option value="cerrada"><?= $lang['rep_estado_cerrada'] ?? 'Cerrada' ?></option>
                    </select>
                </div>

                <div class="rep-modal__field">
                    <label><?= $lang['rep_den_respuesta'] ?? 'Respuesta del administrador' ?></label>
                    <textarea id="modalDenRespuesta" class="rep-modal__textarea" rows="4"
                        placeholder="Escribe tu respuesta o resolución aquí..."></textarea>
                </div>

            </div>
            <div class="rep-modal__footer">
                <button class="rep-btn-cancel" onclick="RepAdmin.cerrarModalDenuncia()">Cancelar</button>
                <button class="rep-btn-save" onclick="RepAdmin.guardarDenuncia()">
                    <i class="fas fa-save"></i> <?= $lang['rep_den_guardar'] ?? 'Guardar respuesta' ?>
                </button>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         SCRIPTS
    ====================================================================== -->
    <script src="assets/js/admin-dashboard.js"></script>
    <script>
    window.REP_ADMIN_CONFIG = {
        csrf: '<?= $csrf ?>',
        apiUrl: 'ajax/reportes_admin.php',
    };
    </script>
    <script src="assets/js/reportes-admin.js"></script>

</body>

</html>