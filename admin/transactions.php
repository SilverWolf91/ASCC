<?php

/**
 * ASCC - Admin Transacciones
 * Ruta: admin/transactions.php
 * Descripción: Listado, búsqueda, filtro y detalle de transacciones.
 *
 * Tabla transacciones — Columnas reales:
 *   id_transaccion, referencia, id_producto, id_comprador, id_vendedor,
 *   cantidad, precio_unitario, total, estado, metodo_pago, banco,
 *   fecha_creacion, fecha_actualizacion, datos_pago
 *
 * Enum estado: PENDIENTE | APROBADO | RECHAZADO | CANCELADO
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
// MAPEO DE ESTADOS
// =============================================================================
$estado_map = [
    'PENDIENTE'  => ['label' => $lang['txn_status_pendiente'], 'class' => 'at-badge--pendiente',  'icon' => 'fa-clock'],
    'APROBADO'   => ['label' => $lang['txn_status_aprobado'],  'class' => 'at-badge--aprobado',   'icon' => 'fa-check-circle'],
    'RECHAZADO'  => ['label' => $lang['txn_status_rechazado'], 'class' => 'at-badge--rechazado',  'icon' => 'fa-times-circle'],
    'CANCELADO'  => ['label' => $lang['txn_status_cancelado'], 'class' => 'at-badge--cancelado',  'icon' => 'fa-ban'],
];

// =============================================================================
// PARÁMETROS DE FILTRO Y BÚSQUEDA
// =============================================================================
$search        = trim($_GET['search']  ?? '');
$estado_filter = strtoupper(trim($_GET['estado'] ?? ''));
$method_filter = trim($_GET['metodo']  ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;
$offset        = ($page - 1) * $per_page;

if (!array_key_exists($estado_filter, $estado_map)) $estado_filter = '';

// =============================================================================
// QUERY PRINCIPAL CON FILTROS
// =============================================================================
$where_parts = ['1=1'];
$params      = [];

if ($search !== '') {
    $where_parts[] = "(t.referencia LIKE :s1
                       OR uc.nombre LIKE :s2
                       OR uv.nombre LIKE :s3
                       OR CONCAT(p.tipo_producto, ' ', p.producto_especifico) LIKE :s4)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
    $params[':s4'] = "%{$search}%";
}
if ($estado_filter !== '') {
    $where_parts[] = "t.estado = :estado";
    $params[':estado'] = $estado_filter;
}
if ($method_filter !== '') {
    $where_parts[] = "t.metodo_pago = :metodo";
    $params[':metodo'] = $method_filter;
}

$where_sql = implode(' AND ', $where_parts);

// Total para paginación
$count_stmt = $conexion->prepare(
    "SELECT COUNT(*)
     FROM   transacciones t
     LEFT JOIN usuarios uc ON t.id_comprador = uc.id_usuario
     LEFT JOIN usuarios uv ON t.id_vendedor  = uv.id_usuario
     LEFT JOIN productos p  ON t.id_producto  = p.id_producto
     WHERE  {$where_sql}"
);
$count_stmt->execute($params);
$total_txn  = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total_txn / $per_page);

// Página actual
$params[':limit']  = $per_page;
$params[':offset'] = $offset;

$stmt = $conexion->prepare(
    "SELECT
         t.id_transaccion,
         t.referencia,
         t.cantidad,
         t.precio_unitario,
         t.total,
         t.estado,
         t.metodo_pago,
         t.banco,
         t.fecha_creacion,
         t.fecha_actualizacion,
         t.datos_pago,
         uc.nombre AS comprador_nombre,
         uv.nombre AS vendedor_nombre,
         CONCAT(p.tipo_producto,
                IFNULL(CONCAT(' — ', p.producto_especifico), '')) AS producto_nombre
     FROM   transacciones t
     LEFT JOIN usuarios uc ON t.id_comprador = uc.id_usuario
     LEFT JOIN usuarios uv ON t.id_vendedor  = uv.id_usuario
     LEFT JOIN productos p  ON t.id_producto  = p.id_producto
     WHERE  {$where_sql}
     ORDER  BY t.fecha_creacion DESC
     LIMIT  :limit OFFSET :offset"
);
foreach ($params as $key => $val) {
    $type = ($key === ':limit' || $key === ':offset') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $val, $type);
}
$stmt->execute();
$transacciones = $stmt->fetchAll();

// =============================================================================
// KPIs
// =============================================================================
$kpi_raw = $conexion->query(
    "SELECT
        COUNT(*)                                      AS total,
        SUM(estado = 'APROBADO')                      AS aprobadas,
        SUM(estado = 'PENDIENTE')                     AS pendientes,
        SUM(estado IN ('RECHAZADO','CANCELADO'))       AS fallidas,
        COALESCE(SUM(CASE WHEN estado = 'APROBADO'
                     THEN total ELSE 0 END), 0)       AS monto_total
     FROM transacciones"
)->fetch();

// Métodos de pago únicos para filtro
$metodos = $conexion->query(
    "SELECT DISTINCT metodo_pago FROM transacciones
     WHERE metodo_pago IS NOT NULL AND metodo_pago != ''
     ORDER BY metodo_pago ASC"
)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['txn_page_title'] ?> — ASCC Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/admin-transactions.css">
</head>

<body>

    <!-- SIDEBAR -->
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
            <a href="transactions.php" class="ag-sidebar__link ag-sidebar__link--active"><i
                    class="fas fa-credit-card"></i><span><?= $lang['nav_transactions'] ?></span></a>
            <p class="ag-sidebar__nav-label"><?= $lang['nav_content'] ?></p>
            <a href="categories.php" class="ag-sidebar__link"><i
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

    <!-- MAIN -->
    <main class="ag-main" id="agMain">

        <!-- TOPBAR -->
        <header class="ag-topbar">
            <div class="ag-topbar__left">
                <button class="ag-topbar__menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
                <div class="ag-topbar__breadcrumb">
                    <span><?= $lang['admin'] ?></span>
                    <i class="fas fa-chevron-right"></i>
                    <span class="ag-topbar__breadcrumb--current"><?= $lang['nav_transactions'] ?></span>
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
                    <div class="ag-avatar"><span><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></span>
                    </div>
                    <div class="ag-topbar__profile-info">
                        <span
                            class="ag-topbar__profile-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                        <span class="ag-topbar__profile-role"><?= $lang['admin'] ?></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="ag-dashboard-body">

            <!-- CABECERA -->
            <div class="ag-page-header">
                <div>
                    <h1 class="ag-page-header__title">💳 <?= $lang['txn_page_title'] ?></h1>
                    <p class="ag-page-header__subtitle">
                        <?= number_format($total_txn) ?> <?= $lang['txn_registered'] ?>
                    </p>
                </div>
            </div>

            <!-- KPIs -->
            <div class="at-kpi-row">
                <div class="at-kpi">
                    <div class="at-kpi__icon at-kpi__icon--total"><i class="fas fa-credit-card"></i></div>
                    <div class="at-kpi__info">
                        <span class="at-kpi__num"><?= number_format((int)$kpi_raw['total']) ?></span>
                        <span class="at-kpi__label"><?= $lang['txn_kpi_total'] ?></span>
                    </div>
                </div>
                <div class="at-kpi">
                    <div class="at-kpi__icon at-kpi__icon--aprobado"><i class="fas fa-check-circle"></i></div>
                    <div class="at-kpi__info">
                        <span class="at-kpi__num"><?= number_format((int)$kpi_raw['aprobadas']) ?></span>
                        <span class="at-kpi__label"><?= $lang['txn_kpi_aprobadas'] ?></span>
                    </div>
                </div>
                <div class="at-kpi">
                    <div class="at-kpi__icon at-kpi__icon--pendiente"><i class="fas fa-clock"></i></div>
                    <div class="at-kpi__info">
                        <span class="at-kpi__num"><?= number_format((int)$kpi_raw['pendientes']) ?></span>
                        <span class="at-kpi__label"><?= $lang['txn_kpi_pendientes'] ?></span>
                    </div>
                </div>
                <div class="at-kpi">
                    <div class="at-kpi__icon at-kpi__icon--fallida"><i class="fas fa-times-circle"></i></div>
                    <div class="at-kpi__info">
                        <span class="at-kpi__num"><?= number_format((int)$kpi_raw['fallidas']) ?></span>
                        <span class="at-kpi__label"><?= $lang['txn_kpi_rechazadas'] ?></span>
                    </div>
                </div>
                <div class="at-kpi">
                    <div class="at-kpi__icon at-kpi__icon--monto"><i class="fas fa-dollar-sign"></i></div>
                    <div class="at-kpi__info">
                        <span
                            class="at-kpi__num at-kpi__num--sm">$<?= number_format((float)$kpi_raw['monto_total'], 0, ',', '.') ?></span>
                        <span class="at-kpi__label"><?= $lang['txn_kpi_monto'] ?></span>
                    </div>
                </div>
            </div>

            <!-- FILTROS -->
            <div class="ag-dashboard-body" style="padding:0; gap:0;">
                <div class="au-toolbar au-toolbar--multi">
                    <form class="au-search-form" method="GET" action="transactions.php">

                        <div class="au-search-input-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="<?= $lang['txn_search_placeholder'] ?>"
                                value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                            <?php if ($search): ?>
                            <a href="transactions.php<?= ($estado_filter || $method_filter) ? '?' . http_build_query(['estado' => $estado_filter, 'metodo' => $method_filter]) : '' ?>"
                                class="au-search-clear">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                        </div>

                        <select name="estado" class="ap-filter-select" onchange="this.form.submit()">
                            <option value=""><?= $lang['txn_filter_all_states'] ?></option>
                            <?php foreach ($estado_map as $key => $e): ?>
                            <option value="<?= $key ?>" <?= $estado_filter === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['label']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="metodo" class="ap-filter-select" onchange="this.form.submit()">
                            <option value=""><?= $lang['txn_filter_all_methods'] ?></option>
                            <?php foreach ($metodos as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $method_filter === $m ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="au-search-btn">
                            <i class="fas fa-search" style="margin-right:6px;font-size:.8rem;"></i>
                            <?= $lang['search'] ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- TABLA -->
            <div class="ag-table-card">
                <div class="ag-table-card__body">
                    <table class="ag-table at-table">
                        <thead>
                            <tr>
                                <th><?= $lang['txn_col_ref'] ?></th>
                                <th><?= $lang['txn_col_product'] ?></th>
                                <th><?= $lang['txn_col_buyer'] ?></th>
                                <th><?= $lang['txn_col_seller'] ?></th>
                                <th><?= $lang['txn_col_amount'] ?></th>
                                <th><?= $lang['txn_col_method'] ?></th>
                                <th><?= $lang['txn_col_status'] ?></th>
                                <th><?= $lang['txn_col_date'] ?></th>
                                <th><?= $lang['txn_col_actions'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transacciones)): ?>
                            <tr>
                                <td colspan="9" class="au-empty">
                                    <i class="fas fa-credit-card"></i>
                                    <span><?= $lang['txn_empty'] ?><?= $search ? ' "' . htmlspecialchars($search) . '"' : '' ?></span>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($transacciones as $t):
                                    $estado_data = $estado_map[strtoupper($t['estado'])] ?? $estado_map['PENDIENTE'];
                                ?>
                            <tr>
                                <!-- Referencia -->
                                <td>
                                    <span class="at-referencia"><?= htmlspecialchars($t['referencia']) ?></span>
                                </td>

                                <!-- Producto -->
                                <td>
                                    <span
                                        class="at-producto"><?= htmlspecialchars($t['producto_nombre'] ?? '—') ?></span>
                                </td>

                                <!-- Comprador -->
                                <td>
                                    <div class="ag-table__user">
                                        <div class="ag-avatar ag-avatar--sm">
                                            <?= strtoupper(substr($t['comprador_nombre'] ?? 'C', 0, 1)) ?>
                                        </div>
                                        <span><?= htmlspecialchars($t['comprador_nombre'] ?? '—') ?></span>
                                    </div>
                                </td>

                                <!-- Vendedor -->
                                <td>
                                    <div class="ag-table__user">
                                        <div class="ag-avatar ag-avatar--sm at-avatar--vendedor">
                                            <?= strtoupper(substr($t['vendedor_nombre'] ?? 'V', 0, 1)) ?>
                                        </div>
                                        <span><?= htmlspecialchars($t['vendedor_nombre'] ?? '—') ?></span>
                                    </div>
                                </td>

                                <!-- Total -->
                                <td class="at-monto">
                                    $<?= number_format((float)$t['total'], 0, ',', '.') ?>
                                </td>

                                <!-- Método de pago -->
                                <td>
                                    <span class="at-metodo"><?= htmlspecialchars($t['metodo_pago'] ?? '—') ?></span>
                                </td>

                                <!-- Estado -->
                                <td>
                                    <span class="at-estado-badge <?= $estado_data['class'] ?>">
                                        <i class="fas <?= $estado_data['icon'] ?>"></i>
                                        <?= $estado_data['label'] ?>
                                    </span>
                                </td>

                                <!-- Fecha -->
                                <td class="au-table__date">
                                    <?= date('d/m/Y H:i', strtotime($t['fecha_creacion'])) ?>
                                </td>

                                <!-- Acciones -->
                                <td>
                                    <div class="au-actions">
                                        <button class="au-action-btn au-action-btn--view"
                                            title="<?= $lang['txn_action_view'] ?>" onclick="verDetalle(<?= htmlspecialchars(json_encode([
                                                                            'ref'         => $t['referencia'],
                                                                            'producto'    => $t['producto_nombre'] ?? '—',
                                                                            'comprador'   => $t['comprador_nombre'] ?? '—',
                                                                            'vendedor'    => $t['vendedor_nombre'] ?? '—',
                                                                            'cantidad'    => (int)$t['cantidad'],
                                                                            'precio_unit' => number_format((float)$t['precio_unitario'], 0, ',', '.'),
                                                                            'total'       => number_format((float)$t['total'], 0, ',', '.'),
                                                                            'metodo'      => $t['metodo_pago'] ?? '—',
                                                                            'banco'       => $t['banco'] ?? '—',
                                                                            'estado'      => $estado_data['label'],
                                                                            'estado_class' => $estado_data['class'],
                                                                            'estado_icon' => $estado_data['icon'],
                                                                            'fecha'       => date('d/m/Y H:i', strtotime($t['fecha_creacion'])),
                                                                            'actualizado' => $t['fecha_actualizacion'] ? date('d/m/Y H:i', strtotime($t['fecha_actualizacion'])) : '—',
                                                                            'datos_pago'  => $t['datos_pago'] ?? '',
                                                                        ])) ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINACIÓN -->
                <?php if ($total_pages > 1): ?>
                <div class="au-pagination">
                    <span class="au-pagination__info">
                        <?= $lang['users_showing'] ?>
                        <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_txn) ?>
                        <?= $lang['users_of'] ?>
                        <?= $total_txn ?>
                    </span>
                    <div class="au-pagination__pages">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&<?= http_build_query(['search' => $search, 'estado' => $estado_filter, 'metodo' => $method_filter]) ?>"
                            class="au-page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        <?php for ($pg = max(1, $page - 2); $pg <= min($total_pages, $page + 2); $pg++): ?>
                        <a href="?page=<?= $pg ?>&<?= http_build_query(['search' => $search, 'estado' => $estado_filter, 'metodo' => $method_filter]) ?>"
                            class="au-page-btn <?= $pg === $page ? 'au-page-btn--active' : '' ?>"><?= $pg ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&<?= http_build_query(['search' => $search, 'estado' => $estado_filter, 'metodo' => $method_filter]) ?>"
                            class="au-page-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /.ag-dashboard-body -->
    </main>

    <!-- =========================================================
         MODAL — DETALLE DE TRANSACCIÓN
    ========================================================== -->
    <div class="at-modal-backdrop" id="modalBackdrop" onclick="cerrarDetalle()"></div>

    <div class="at-modal" id="modalDetalle" role="dialog" aria-modal="true">
        <div class="at-modal__header" id="detalleHeader">
            <div class="at-modal__header-left">
                <div class="at-modal__icon"><i class="fas fa-credit-card"></i></div>
                <h2 class="at-modal__title"><?= $lang['txn_modal_title'] ?></h2>
            </div>
            <button class="at-modal__close" onclick="cerrarDetalle()"><i class="fas fa-times"></i></button>
        </div>

        <div class="at-modal__body">

            <!-- Estado + Referencia -->
            <div class="at-detail-hero">
                <span class="at-referencia-hero" id="det_ref">—</span>
                <span class="at-estado-badge" id="det_estado_badge">—</span>
            </div>

            <!-- Grid de datos principales -->
            <div class="at-detail-grid">
                <div class="at-detail-field">
                    <span class="at-detail-label"><?= $lang['txn_detail_product'] ?></span>
                    <span class="at-detail-value" id="det_producto">—</span>
                </div>
                <div class="at-detail-field">
                    <span class="at-detail-label"><?= $lang['txn_detail_buyer'] ?></span>
                    <span class="at-detail-value" id="det_comprador">—</span>
                </div>
                <div class="at-detail-field">
                    <span class="at-detail-label"><?= $lang['txn_detail_seller'] ?></span>
                    <span class="at-detail-value" id="det_vendedor">—</span>
                </div>
                <div class="at-detail-field">
                    <span class="at-detail-label"><?= $lang['txn_detail_method'] ?></span>
                    <span class="at-detail-value" id="det_metodo">—</span>
                </div>
                <div class="at-detail-field">
                    <span class="at-detail-label"><?= $lang['txn_detail_bank'] ?></span>
                    <span class="at-detail-value" id="det_banco">—</span>
                </div>
                <div class="at-detail-field">
                    <span class="at-detail-label"><?= $lang['txn_detail_date'] ?></span>
                    <span class="at-detail-value" id="det_fecha">—</span>
                </div>
            </div>

            <!-- Desglose financiero -->
            <div class="at-detail-financial">
                <div class="at-fin-row">
                    <span><?= $lang['txn_detail_qty'] ?></span>
                    <span id="det_cantidad">—</span>
                </div>
                <div class="at-fin-row">
                    <span><?= $lang['txn_detail_unit_price'] ?></span>
                    <span id="det_precio_unit">—</span>
                </div>
                <div class="at-fin-row at-fin-row--total">
                    <span><?= $lang['txn_detail_total'] ?></span>
                    <span id="det_total">—</span>
                </div>
            </div>

            <!-- Datos adicionales -->
            <div class="at-detail-extra" id="det_extra_wrap">
                <span class="at-detail-label"><?= $lang['txn_detail_data'] ?></span>
                <pre class="at-detail-pre" id="det_datos_pago">—</pre>
            </div>

        </div>

        <div class="at-modal__footer">
            <button type="button" class="at-btn-secondary" onclick="cerrarDetalle()">
                <?= $lang['close'] ?>
            </button>
        </div>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
    <script>
    // Variables PHP → JS
    window.TXN_LANG = {
        noData: <?= json_encode($lang['txn_no_data']) ?>
    };
    </script>
    <script src="assets/js/admin-transactions.js"></script>

</body>

</html>