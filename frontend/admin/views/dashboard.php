<?php

/**
 * ASCC - Admin Dashboard
 * Ruta: admin/dashboard.php
 * Descripción: Panel principal del administrador.
 *              Conectado a la BD real: ascc
 *
 * Tabla transacciones — Columnas reales confirmadas:
 *   id_transaccion, referencia, id_producto, id_comprador, id_vendedor,
 *   cantidad, precio_unitario, total, estado, metodo_pago, banco,
 *   fecha_creacion, fecha_actualizacion, datos_pago
 *
 * Enum estado: PENDIENTE | APROBADO | RECHAZADO | CANCELADO
 */

session_start();

// =============================================================================
// SEGURIDAD — Triple capa
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
// INTERNACIONALIZACIÓN
// =============================================================================
$lang_code = $_SESSION['lang'] ?? $_COOKIE['ag_lang'] ?? 'es';
$lang_file = __DIR__ . "/../../backend/admin/lang/{$lang_code}.php";
if (!file_exists($lang_file)) {
    $lang_code = 'es';
    $lang_file = __DIR__ . '/../../backend/admin/lang/es.php';
}
$lang = require $lang_file;

// =============================================================================
// TEMA VISUAL
// =============================================================================
$theme = $_COOKIE['ag_theme'] ?? 'light';
$theme = in_array($theme, ['light', 'dark']) ? $theme : 'light';

// =============================================================================
// CONEXIÓN — config/database.php → $conexion (PDO, FETCH_ASSOC por defecto)
// =============================================================================
require_once __DIR__ . '/../../../backend/users/config/database.php';

// =============================================================================
// KPIs — Queries reales
// =============================================================================
$kpi = [
    'total_users'        => 0,
    'sellers'            => 0,
    'buyers'             => 0,
    'mixed'              => 0,
    'active_products'    => 0,
    'pending_products'   => 0,
    'monthly_sales'      => 0,
    'daily_transactions' => 0,
    'reported_products'  => 0,
    'new_users_today'    => 0,
];

// Usuarios agrupados por rol
$stmt = $conexion->query(
    "SELECT rol, COUNT(*) AS total
     FROM   usuarios
     WHERE  rol IN ('vendedor','comprador','mixto','admin')
     GROUP  BY rol"
);
foreach ($stmt->fetchAll() as $row) {
    $kpi['total_users'] += $row['total'];
    if ($row['rol'] === 'vendedor')  $kpi['sellers'] = (int)$row['total'];
    if ($row['rol'] === 'comprador') $kpi['buyers']  = (int)$row['total'];
    if ($row['rol'] === 'mixto')     $kpi['mixed']   = (int)$row['total'];
}

// Nuevos usuarios hoy
$kpi['new_users_today'] = (int)$conexion->query(
    "SELECT COUNT(*) FROM usuarios WHERE DATE(fecha_registro) = CURDATE()"
)->fetchColumn();

// Total productos
$kpi['active_products'] = (int)$conexion->query(
    "SELECT COUNT(*) FROM productos"
)->fetchColumn();

// Ventas del mes — columna: total | fecha: fecha_creacion
$kpi['monthly_sales'] = (float)$conexion->query(
    "SELECT COALESCE(SUM(total), 0)
     FROM   transacciones
     WHERE  MONTH(fecha_creacion) = MONTH(NOW())
       AND  YEAR(fecha_creacion)  = YEAR(NOW())"
)->fetchColumn();

// Transacciones de hoy — columna fecha: fecha_creacion
$kpi['daily_transactions'] = (int)$conexion->query(
    "SELECT COUNT(*)
     FROM   transacciones
     WHERE  DATE(fecha_creacion) = CURDATE()"
)->fetchColumn();

// =============================================================================
// DENUNCIAS URGENTES — para badge en sidebar
// =============================================================================
$den_urg = 0;
try {
    $den_urg = (int)$conexion->query(
        "SELECT COUNT(*) FROM reportes_denuncias
         WHERE prioridad = 'alta' AND estado NOT IN ('resuelta','cerrada')"
    )->fetchColumn();
} catch (Exception $e) {
}

// =============================================================================
// ÚLTIMAS 5 TRANSACCIONES
// =============================================================================
$stmt = $conexion->query(
    "SELECT
         t.id_transaccion,
         u.nombre                                               AS comprador,
         CONCAT(p.tipo_producto, ' - ', p.producto_especifico) AS producto,
         t.total                                                AS monto,
         t.estado                                               AS estado
     FROM   transacciones t
     LEFT   JOIN usuarios  u ON t.id_comprador = u.id_usuario
     LEFT   JOIN productos p ON t.id_producto  = p.id_producto
     ORDER  BY t.fecha_creacion DESC
     LIMIT  5"
);
$recent_transactions_raw = $stmt->fetchAll();

$estado_map = [
    'aprobado'   => 'completed',
    'pendiente'  => 'pending',
    'rechazado'  => 'failed',
    'cancelado'  => 'failed',
];

$recent_transactions = [];
foreach ($recent_transactions_raw as $t) {
    $estado_key = strtolower($t['estado'] ?? '');
    $recent_transactions[] = [
        'id'      => '#TXN-' . str_pad($t['id_transaccion'], 4, '0', STR_PAD_LEFT),
        'buyer'   => $t['comprador'] ?? 'Usuario eliminado',
        'product' => $t['producto']  ?? 'Producto eliminado',
        'amount'  => (float)$t['monto'],
        'status'  => $estado_map[$estado_key] ?? 'pending',
    ];
}

// =============================================================================
// ÚLTIMOS 5 USUARIOS REGISTRADOS
// =============================================================================
$stmt = $conexion->query(
    "SELECT nombre, rol, DATE_FORMAT(fecha_registro, '%Y-%m-%d') AS fecha
     FROM   usuarios
     ORDER  BY fecha_registro DESC
     LIMIT  5"
);

$recent_users = [];
foreach ($stmt->fetchAll() as $u) {
    $recent_users[] = [
        'name' => $u['nombre'],
        'role' => $u['rol'],
        'date' => $u['fecha'],
    ];
}

// =============================================================================
// GRÁFICA — Registros de usuarios últimos 7 días
// =============================================================================
$stmt = $conexion->query(
    "SELECT
         DATE_FORMAT(fecha_registro, '%Y-%m-%d') AS dia,
         COUNT(*) AS total
     FROM   usuarios
     WHERE  fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP  BY dia
     ORDER  BY dia ASC"
);

$user_registrations = [];
foreach ($stmt->fetchAll() as $r) {
    $key = strtolower(date('D', strtotime($r['dia'])));
    $user_registrations[] = [
        'day'   => $lang[$key] ?? $r['dia'],
        'count' => (int)$r['total'],
    ];
}

if (empty($user_registrations)) {
    foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) {
        $user_registrations[] = ['day' => $lang[$d], 'count' => 0];
    }
}

// =============================================================================
// GRÁFICA — Productos por categoría_principal
// =============================================================================
$categoria_colores = [
    'Huevos y Derivados'    => '#f4a261',
    'Aves de Corral'        => '#e9c46a',
    'Ganado Bovino'         => '#2d6a4f',
    'Caballos y Equinos'    => '#40916c',
    'Ganado Menor'          => '#52b788',
    'Cárnicos y Embutidos'  => '#e76f51',
    'Lácteos'               => '#74c69d',
    'Verduras y Hortalizas' => '#95d5b2',
    'Frutas'                => '#b7e4c7',
    'Cereales y Granos'     => '#d8f3dc',
    'Plantas y Semillas'    => '#a8dadc',
    'Productos Procesados'  => '#457b9d',
    'Peces y Acuicultura'   => '#1d3557',
];

$stmt = $conexion->query(
    "SELECT categoria_principal, COUNT(*) AS total
     FROM   productos
     WHERE  categoria_principal IS NOT NULL
     GROUP  BY categoria_principal
     ORDER  BY total DESC"
);
$categorias_raw      = $stmt->fetchAll();
$total_productos_cat = array_sum(array_column($categorias_raw, 'total'));

$sales_by_category = [];
foreach ($categorias_raw as $cat) {
    $nombre = $cat['categoria_principal'];
    $pct    = $total_productos_cat > 0
        ? round(($cat['total'] / $total_productos_cat) * 100)
        : 0;
    $sales_by_category[] = [
        'label' => $nombre,
        'value' => $pct,
        'color' => $categoria_colores[$nombre] ?? '#94a3b8',
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['admin_dashboard_title'] ?> — ASCC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>

<body>

    <!-- =========================================================
         SIDEBAR
    ========================================================== -->
    <aside class="ag-sidebar" id="agSidebar">

        <div class="ag-sidebar__logo">
            <span class="ag-sidebar__logo-icon">🌾</span>
            <span class="ag-sidebar__logo-text">ASCC</span>
        </div>

        <nav class="ag-sidebar__nav">
            <p class="ag-sidebar__nav-label"><?= $lang['nav_main'] ?></p>

            <a href="dashboard.php" class="ag-sidebar__link ag-sidebar__link--active">
                <i class="fas fa-chart-line"></i>
                <span><?= $lang['nav_dashboard'] ?></span>
            </a>
            <a href="users.php" class="ag-sidebar__link">
                <i class="fas fa-users"></i>
                <span><?= $lang['nav_users'] ?></span>
                <?php if ($kpi['new_users_today'] > 0): ?>
                <span class="ag-badge ag-badge--green"><?= $kpi['new_users_today'] ?></span>
                <?php endif; ?>
            </a>
            <a href="products.php" class="ag-sidebar__link">
                <i class="fas fa-box-open"></i>
                <span><?= $lang['nav_products'] ?></span>
                <?php if ($kpi['pending_products'] > 0): ?>
                <span class="ag-badge ag-badge--amber"><?= $kpi['pending_products'] ?></span>
                <?php endif; ?>
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
            <a href="reviews.php" class="ag-sidebar__link">
                <i class="fas fa-star"></i>
                <span><?= $lang['nav_reviews'] ?></span>
            </a>

            <p class="ag-sidebar__nav-label"><?= $lang['nav_system'] ?></p>

            <a href="reportes.php" class="ag-sidebar__link">
                <i class="fas fa-chart-bar"></i>
                <span><?= $lang['nav_reports'] ?? 'Reportes' ?></span>
                <?php if ($den_urg > 0): ?>
                <span class="ag-badge ag-badge--red"><?= $den_urg ?></span>
                <?php endif; ?>
            </a>
            <!-- ✅ FIX: era settings.php, ahora apunta a configuracion.php -->
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

        <button class="ag-sidebar__collapse-btn" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-chevron-left" id="sidebarToggleIcon"></i>
        </button>
    </aside>

    <!-- =========================================================
         CONTENIDO PRINCIPAL
    ========================================================== -->
    <main class="ag-main" id="agMain">

        <!-- TOPBAR -->
        <header class="ag-topbar">
            <div class="ag-topbar__left">
                <button class="ag-topbar__menu-btn" id="mobileMenuBtn" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="ag-topbar__breadcrumb">
                    <span><?= $lang['admin'] ?></span>
                    <i class="fas fa-chevron-right"></i>
                    <span class="ag-topbar__breadcrumb--current"><?= $lang['nav_dashboard'] ?></span>
                </div>
            </div>

            <div class="ag-topbar__right">
                <div class="ag-topbar__search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="<?= $lang['topbar_search_placeholder'] ?>">
                </div>

                <div class="ag-topbar__lang-toggle">
                    <button class="ag-lang-btn <?= $lang_code === 'es' ? 'ag-lang-btn--active' : '' ?>"
                        onclick="switchLang('es')">ES</button>
                    <span>|</span>
                    <button class="ag-lang-btn <?= $lang_code === 'en' ? 'ag-lang-btn--active' : '' ?>"
                        onclick="switchLang('en')">EN</button>
                </div>

                <button class="ag-theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <i class="fas <?= $theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>" id="themeIcon"></i>
                </button>

                <button class="ag-alerts-btn" id="alertsBtn">
                    <i class="fas fa-bell"></i>
                    <?php if ($kpi['reported_products'] > 0): ?>
                    <span class="ag-alerts-dot"><?= $kpi['reported_products'] ?></span>
                    <?php endif; ?>
                </button>

                <div class="ag-topbar__profile">
                    <div class="ag-avatar">
                        <span><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></span>
                    </div>
                    <div class="ag-topbar__profile-info">
                        <span class="ag-topbar__profile-name">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                        </span>
                        <span class="ag-topbar__profile-role"><?= $lang['admin'] ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- CUERPO -->
        <div class="ag-dashboard-body">

            <div class="ag-page-header">
                <div>
                    <h1 class="ag-page-header__title">
                        <?= $lang['dashboard_welcome'] ?>,
                        <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?> 👋
                    </h1>
                    <p class="ag-page-header__subtitle"><?= $lang['dashboard_subtitle'] ?></p>
                </div>
                <div class="ag-page-header__date">
                    <i class="fas fa-calendar-alt"></i>
                    <span id="currentDate"></span>
                </div>
            </div>

            <!-- KPI CARDS -->
            <section class="ag-kpi-grid" aria-label="<?= $lang['kpi_section_label'] ?>">

                <div class="ag-kpi-card ag-kpi-card--users">
                    <div class="ag-kpi-card__icon"><i class="fas fa-users"></i></div>
                    <div class="ag-kpi-card__content">
                        <span class="ag-kpi-card__label"><?= $lang['kpi_total_users'] ?></span>
                        <span class="ag-kpi-card__value" data-count="<?= $kpi['total_users'] ?>">0</span>
                        <span class="ag-kpi-card__trend ag-kpi-card__trend--up">
                            <i class="fas fa-arrow-up"></i>
                            +<?= $kpi['new_users_today'] ?> <?= $lang['kpi_today'] ?>
                        </span>
                    </div>
                    <div class="ag-kpi-card__breakdown">
                        <span>
                            <i class="fas fa-store"></i>
                            <?= $kpi['sellers'] ?> <?= $lang['role_vendedores'] ?>
                        </span>
                        <span>
                            <i class="fas fa-shopping-cart"></i>
                            <?= $kpi['buyers'] ?> <?= $lang['role_compradores'] ?>
                        </span>
                        <span>
                            <i class="fas fa-exchange-alt"></i>
                            <?= $kpi['mixed'] ?> <?= $lang['role_mixto'] ?>
                        </span>
                    </div>
                </div>

                <div class="ag-kpi-card ag-kpi-card--products">
                    <div class="ag-kpi-card__icon"><i class="fas fa-box-open"></i></div>
                    <div class="ag-kpi-card__content">
                        <span class="ag-kpi-card__label"><?= $lang['kpi_active_products'] ?></span>
                        <span class="ag-kpi-card__value" data-count="<?= $kpi['active_products'] ?>">0</span>
                        <span class="ag-kpi-card__trend ag-kpi-card__trend--warning">
                            <i class="fas fa-clock"></i>
                            <?= $kpi['pending_products'] ?> <?= $lang['kpi_pending'] ?>
                        </span>
                    </div>
                    <div class="ag-kpi-card__action">
                        <a href="products.php?filter=pending"><?= $lang['kpi_review_pending'] ?> →</a>
                    </div>
                </div>

                <div class="ag-kpi-card ag-kpi-card--sales">
                    <div class="ag-kpi-card__icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="ag-kpi-card__content">
                        <span class="ag-kpi-card__label"><?= $lang['kpi_monthly_sales'] ?></span>
                        <span class="ag-kpi-card__value ag-kpi-card__value--currency">
                            $<?= number_format($kpi['monthly_sales'], 0, ',', '.') ?>
                        </span>
                        <span class="ag-kpi-card__trend ag-kpi-card__trend--up">
                            <i class="fas fa-chart-line"></i> <?= $lang['kpi_vs_last_month'] ?>
                        </span>
                    </div>
                    <div class="ag-kpi-card__action">
                        <a href="transactions.php"><?= $lang['kpi_see_transactions'] ?> →</a>
                    </div>
                </div>

                <div class="ag-kpi-card ag-kpi-card--transactions">
                    <div class="ag-kpi-card__icon"><i class="fas fa-credit-card"></i></div>
                    <div class="ag-kpi-card__content">
                        <span class="ag-kpi-card__label"><?= $lang['kpi_daily_transactions'] ?></span>
                        <span class="ag-kpi-card__value" data-count="<?= $kpi['daily_transactions'] ?>">0</span>
                        <span class="ag-kpi-card__trend ag-kpi-card__trend--up">
                            <i class="fas fa-arrow-up"></i> <?= $lang['kpi_vs_yesterday'] ?>
                        </span>
                    </div>
                </div>

                <div
                    class="ag-kpi-card ag-kpi-card--alerts <?= $kpi['reported_products'] > 0 ? 'ag-kpi-card--has-alerts' : '' ?>">
                    <div class="ag-kpi-card__icon"><i class="fas fa-flag"></i></div>
                    <div class="ag-kpi-card__content">
                        <span class="ag-kpi-card__label"><?= $lang['kpi_reported_products'] ?></span>
                        <span class="ag-kpi-card__value" data-count="<?= $kpi['reported_products'] ?>">0</span>
                        <span class="ag-kpi-card__trend ag-kpi-card__trend--danger">
                            <i class="fas fa-exclamation-circle"></i> <?= $lang['kpi_requires_attention'] ?>
                        </span>
                    </div>
                    <div class="ag-kpi-card__action">
                        <a href="products.php?filter=reported"><?= $lang['kpi_review_reports'] ?> →</a>
                    </div>
                </div>

            </section>

            <!-- GRÁFICAS -->
            <section class="ag-charts-grid">

                <div class="ag-chart-card ag-chart-card--donut">
                    <div class="ag-chart-card__header">
                        <h3><?= $lang['chart_sales_by_category'] ?></h3>
                        <span class="ag-chart-card__period"><?= $lang['chart_last_7_days'] ?></span>
                    </div>
                    <div class="ag-chart-card__body">
                        <canvas id="categoryChart" height="240"></canvas>
                    </div>
                    <div class="ag-chart-legend" id="categoryLegend">
                        <?php foreach ($sales_by_category as $item): ?>
                        <div class="ag-chart-legend__item">
                            <span class="ag-chart-legend__dot" style="background-color:<?= $item['color'] ?>"></span>
                            <span><?= htmlspecialchars($item['label']) ?></span>
                            <strong><?= $item['value'] ?>%</strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ag-chart-card ag-chart-card--bar">
                    <div class="ag-chart-card__header">
                        <h3><?= $lang['chart_new_users'] ?></h3>
                        <span class="ag-chart-card__period"><?= $lang['chart_last_7_days'] ?></span>
                    </div>
                    <div class="ag-chart-card__body">
                        <canvas id="usersChart" height="240"></canvas>
                    </div>
                </div>

            </section>

            <!-- TABLAS -->
            <section class="ag-tables-grid">

                <!-- Últimas transacciones -->
                <div class="ag-table-card">
                    <div class="ag-table-card__header">
                        <h3><?= $lang['table_recent_transactions'] ?></h3>
                        <a href="transactions.php" class="ag-table-card__link"><?= $lang['see_all'] ?> →</a>
                    </div>
                    <div class="ag-table-card__body">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th><?= $lang['col_id'] ?></th>
                                    <th><?= $lang['col_buyer'] ?></th>
                                    <th><?= $lang['col_product'] ?></th>
                                    <th><?= $lang['col_amount'] ?></th>
                                    <th><?= $lang['col_status'] ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_transactions)): ?>
                                <tr>
                                    <td colspan="5"
                                        style="text-align:center; color:var(--ag-text-muted); padding:24px;">
                                        <?= $lang['no_results'] ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recent_transactions as $txn): ?>
                                <tr>
                                    <td class="ag-table__id"><?= htmlspecialchars($txn['id']) ?></td>
                                    <td><?= htmlspecialchars($txn['buyer']) ?></td>
                                    <td><?= htmlspecialchars($txn['product']) ?></td>
                                    <td class="ag-table__amount">
                                        $<?= number_format($txn['amount'], 0, ',', '.') ?>
                                    </td>
                                    <td>
                                        <span class="ag-status-badge ag-status-badge--<?= $txn['status'] ?>">
                                            <?= $lang['status_' . $txn['status']] ?? $txn['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Usuarios recientes -->
                <div class="ag-table-card">
                    <div class="ag-table-card__header">
                        <h3><?= $lang['table_recent_users'] ?></h3>
                        <a href="users.php" class="ag-table-card__link"><?= $lang['see_all'] ?> →</a>
                    </div>
                    <div class="ag-table-card__body">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th><?= $lang['col_name'] ?></th>
                                    <th><?= $lang['col_role'] ?></th>
                                    <th><?= $lang['col_date'] ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_users)): ?>
                                <tr>
                                    <td colspan="3"
                                        style="text-align:center; color:var(--ag-text-muted); padding:24px;">
                                        <?= $lang['no_results'] ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="ag-table__user">
                                            <div class="ag-avatar ag-avatar--sm">
                                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                            </div>
                                            <?= htmlspecialchars($user['name']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ag-role-badge ag-role-badge--<?= $user['role'] ?>">
                                            <?= $lang['role_' . $user['role']] ?? $user['role'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($user['date']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>

        </div><!-- /.ag-dashboard-body -->
    </main>

    <!-- Datos para JS -->
    <script>
    window.ASCC = {
        categoryChartData: <?= json_encode($sales_by_category) ?>,
        userRegistrationsData: <?= json_encode($user_registrations) ?>,
        kpi: <?= json_encode($kpi) ?>,
        theme: '<?= $theme ?>',
        lang: {
            chartUsersLabel: '<?= $lang['chart_users_label'] ?>',
        }
    };
    </script>
    <script src="../assets/js/admin-dashboard.js"></script>

</body>

</html>