<?php

/**
 * ASCC - Admin Reviews
 * Ruta: admin/reviews.php
 * Descripción: Gestión y moderación de todas las reseñas del marketplace.
 *              Cubre resenas_producto, resenas_vendedor, resenas_comprador.
 */

session_start();

// ── Seguridad triple capa ────────────────────────────────────
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

// ── i18n ─────────────────────────────────────────────────────
$lang_code = $_SESSION['lang'] ?? $_COOKIE['ag_lang'] ?? 'es';
$lang_file = __DIR__ . "/../../backend/admin/lang/{$lang_code}.php";
if (!file_exists($lang_file)) {
    $lang_code = 'es';
    $lang_file = __DIR__ . '/../../backend/admin/lang/es.php';
}
$lang = require $lang_file;

// ── Tema ─────────────────────────────────────────────────────
$theme = $_COOKIE['ag_theme'] ?? 'light';
$theme = in_array($theme, ['light', 'dark']) ? $theme : 'light';

// ── Conexión ─────────────────────────────────────────────────
require_once __DIR__ . '/../../../backend/users/config/database.php';

// ── Parámetros de filtrado y paginación ──────────────────────
$tipo_filtro   = $_GET['tipo']   ?? 'todos';
$busqueda      = trim($_GET['q'] ?? '');
$pagina_actual = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina    = 15;
$offset        = ($pagina_actual - 1) * $por_pagina;

$tipos_validos = ['todos', 'producto', 'vendedor', 'comprador'];
if (!in_array($tipo_filtro, $tipos_validos)) {
    $tipo_filtro = 'todos';
}

// ── KPIs — totales por tabla ──────────────────────────────────
$kpi_resenas = [
    'total'      => 0,
    'productos'  => 0,
    'vendedores' => 0,
    'compradores' => 0,
    'promedio'   => 0.0,
    'esta_semana' => 0,
];

$kpi_resenas['productos'] = (int)$conexion->query(
    "SELECT COUNT(*) FROM resenas_producto"
)->fetchColumn();

$kpi_resenas['vendedores'] = (int)$conexion->query(
    "SELECT COUNT(*) FROM resenas_vendedor"
)->fetchColumn();

$kpi_resenas['compradores'] = (int)$conexion->query(
    "SELECT COUNT(*) FROM resenas_comprador"
)->fetchColumn();

$kpi_resenas['total'] = $kpi_resenas['productos']
    + $kpi_resenas['vendedores']
    + $kpi_resenas['compradores'];

// Promedio global de calificaciones (union de las 3 tablas)
$row_prom = $conexion->query(
    "SELECT ROUND(AVG(cal), 1) AS promedio FROM (
        SELECT calificacion AS cal FROM resenas_producto
        UNION ALL
        SELECT calificacion FROM resenas_vendedor
        UNION ALL
        SELECT calificacion FROM resenas_comprador
     ) AS todas"
)->fetch();
$kpi_resenas['promedio'] = (float)($row_prom['promedio'] ?? 0);

// Reseñas esta semana
$kpi_resenas['esta_semana'] = (int)$conexion->query(
    "SELECT COUNT(*) FROM (
        SELECT fecha_resena FROM resenas_producto  WHERE fecha_resena >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT fecha_resena FROM resenas_vendedor  WHERE fecha_resena >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT fecha_resena FROM resenas_comprador WHERE fecha_resena >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ) AS semana"
)->fetchColumn();

// ── Query principal — union de las 3 tablas ───────────────────
// Construimos una UNION que normaliza las 3 tablas en columnas comunes

$where_busqueda = '';
$params_count   = [];
$params_data    = [];

if ($busqueda !== '') {
    $where_busqueda = "WHERE autor_nombre LIKE :busqueda OR comentario LIKE :busqueda2";
    $params_count[':busqueda']  = "%{$busqueda}%";
    $params_count[':busqueda2'] = "%{$busqueda}%";
    $params_data[':busqueda']   = "%{$busqueda}%";
    $params_data[':busqueda2']  = "%{$busqueda}%";
}

// SQL base por tipo de reseña
$sql_producto = "
    SELECT
        rp.id_resena,
        'producto'                          AS tipo,
        rp.calificacion,
        rp.titulo,
        rp.comentario,
        rp.fecha_resena,
        u.nombre                            AS autor_nombre,
        u.id_usuario                        AS autor_id,
        u.rol                               AS autor_rol,
        CONCAT(p.tipo_producto,
               IFNULL(CONCAT(' - ', p.producto_especifico), '')) AS entidad_nombre,
        p.id_producto                       AS entidad_id
    FROM resenas_producto rp
    INNER JOIN usuarios  u ON u.id_usuario  = rp.id_usuario
    INNER JOIN productos p ON p.id_producto = rp.id_producto
";

$sql_vendedor = "
    SELECT
        rv.id_resena,
        'vendedor'                          AS tipo,
        rv.calificacion,
        rv.titulo,
        rv.comentario,
        rv.fecha_resena,
        uc.nombre                           AS autor_nombre,
        uc.id_usuario                       AS autor_id,
        uc.rol                              AS autor_rol,
        uv.nombre                           AS entidad_nombre,
        uv.id_usuario                       AS entidad_id
    FROM resenas_vendedor rv
    INNER JOIN usuarios uc ON uc.id_usuario = rv.id_comprador
    INNER JOIN usuarios uv ON uv.id_usuario = rv.id_vendedor
";

$sql_comprador = "
    SELECT
        rc.id_resena,
        'comprador'                         AS tipo,
        rc.calificacion,
        rc.titulo,
        rc.comentario,
        rc.fecha_resena,
        uv.nombre                           AS autor_nombre,
        uv.id_usuario                       AS autor_id,
        uv.rol                              AS autor_rol,
        uc.nombre                           AS entidad_nombre,
        uc.id_usuario                       AS entidad_id
    FROM resenas_comprador rc
    INNER JOIN usuarios uv ON uv.id_usuario = rc.id_vendedor
    INNER JOIN usuarios uc ON uc.id_usuario = rc.id_comprador
";

// Seleccionar qué tablas incluir según filtro
if ($tipo_filtro === 'producto') {
    $sql_union = $sql_producto;
} elseif ($tipo_filtro === 'vendedor') {
    $sql_union = $sql_vendedor;
} elseif ($tipo_filtro === 'comprador') {
    $sql_union = $sql_comprador;
} else {
    $sql_union = "({$sql_producto}) UNION ALL ({$sql_vendedor}) UNION ALL ({$sql_comprador})";
}

// Total para paginación
$sql_count = "SELECT COUNT(*) FROM ({$sql_union}) AS todas {$where_busqueda}";
$stmt_count = $conexion->prepare($sql_count);
$stmt_count->execute($params_count);
$total_resenas  = (int)$stmt_count->fetchColumn();
$total_paginas  = (int)ceil($total_resenas / $por_pagina);

// Datos paginados
$sql_data = "
    SELECT * FROM ({$sql_union}) AS todas
    {$where_busqueda}
    ORDER BY fecha_resena DESC
    LIMIT :limit OFFSET :offset
";
$stmt_data = $conexion->prepare($sql_data);
foreach ($params_data as $key => $val) {
    $stmt_data->bindValue($key, $val);
}
$stmt_data->bindValue(':limit',  $por_pagina, PDO::PARAM_INT);
$stmt_data->bindValue(':offset', $offset,     PDO::PARAM_INT);
$stmt_data->execute();
$resenas = $stmt_data->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['reviews_admin_title'] ?> — ASCC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin-reviews.css">
</head>

<body>

    <!-- ── SIDEBAR ───────────────────────────────────────────── -->
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
            <!-- ✅ ENLACE ACTIVO -->
            <a href="reviews.php" class="ag-sidebar__link ag-sidebar__link--active">
                <i class="fas fa-star"></i>
                <span><?= $lang['nav_reviews'] ?></span>
                <?php if ($kpi_resenas['esta_semana'] > 0): ?>
                    <span class="ag-badge ag-badge--green"><?= $kpi_resenas['esta_semana'] ?></span>
                <?php endif; ?>
            </a>

            <p class="ag-sidebar__nav-label"><?= $lang['nav_system'] ?></p>

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

    <!-- ── MAIN ──────────────────────────────────────────────── -->
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
                    <span class="ag-topbar__breadcrumb--current"><?= $lang['nav_reviews'] ?></span>
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

            <!-- Cabecera de página -->
            <div class="ag-page-header">
                <div>
                    <h1 class="ag-page-header__title">
                        <?= $lang['reviews_admin_title'] ?>
                    </h1>
                    <p class="ag-page-header__subtitle">
                        <?= $total_resenas ?>
                        <?= $lang['reviews_admin_subtitle'] ?>
                    </p>
                </div>
            </div>

            <!-- KPI CARDS -->
            <section class="ag-kpi-grid ag-kpi-grid--4" aria-label="<?= $lang['reviews_kpi_label'] ?>">

                <div class="ag-kpi-card ag-kpi-card--reviews-total">
                    <div class="ag-kpi-card__icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="ag-kpi-card__content">
                        <span class="ag-kpi-card__label"><?= $lang['reviews_kpi_total'] ?></span>
                        <span class="ag-kpi-card__value"><?= number_format($kpi_resenas['total']) ?></span>
                        <span class="ag-kpi-card__trend ag-kpi-card__trend--up">
                            <i class="fas fa-arrow-up"></i>
                            +<?= $kpi_resenas['esta_semana'] ?> <?= $lang['reviews_kpi_this_week'] ?>
                        </span>
                    </div>
                </div>

                <div class="ag-kpi-card ag-kpi-card--reviews-producto">
                    <div class="ag-kpi-card__icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div class="ag-kpi-card__content">
                        <span class="ag-kpi-card__label"><?= $lang['reviews_kpi_productos'] ?></span>
                        <span class="ag-kpi-card__value"><?= number_format($kpi_resenas['productos']) ?></span>
                    </div>
                    <div class="ag-kpi-card__action">
                        <a href="reviews.php?tipo=producto"><?= $lang['reviews_kpi_ver'] ?> →</a>
                    </div>
                </div>

                <div class="ag-kpi-card ag-kpi-card--reviews-vendedor">
                    <div class="ag-kpi-card__icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="ag-kpi-card__content">
                        <span class="ag-kpi-card__label"><?= $lang['reviews_kpi_vendedores'] ?></span>
                        <span class="ag-kpi-card__value"><?= number_format($kpi_resenas['vendedores']) ?></span>
                    </div>
                    <div class="ag-kpi-card__action">
                        <a href="reviews.php?tipo=vendedor"><?= $lang['reviews_kpi_ver'] ?> →</a>
                    </div>
                </div>

                <div class="ag-kpi-card ag-kpi-card--reviews-promedio">
                    <div class="ag-kpi-card__icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="ag-kpi-card__content">
                        <span class="ag-kpi-card__label"><?= $lang['reviews_kpi_promedio'] ?></span>
                        <span class="ag-kpi-card__value">
                            <?= number_format($kpi_resenas['promedio'], 1) ?>
                            <small style="font-size:1rem;opacity:.6">/5</small>
                        </span>
                        <span class="ag-kpi-card__trend ag-kpi-card__trend--up">
                            <?php
                            $prom = $kpi_resenas['promedio'];
                            for ($i = 1; $i <= 5; $i++):
                            ?>
                                <i class="fas fa-star<?= $i <= round($prom) ? '' : '-o' ?>"
                                    style="color:<?= $i <= round($prom) ? '#f59e0b' : 'var(--ag-text-muted)' ?>;font-size:.65rem"></i>
                            <?php endfor; ?>
                        </span>
                    </div>
                </div>

            </section>

            <!-- FILTROS + BUSCADOR -->
            <div class="ag-reviews-filters">
                <form method="GET" action="reviews.php" class="ag-reviews-filters__form">

                    <!-- Filtro por tipo -->
                    <div class="ag-reviews-filters__tabs" role="tablist">
                        <?php
                        $tabs = [
                            'todos'     => ['icon' => 'fa-list',     'label' => $lang['reviews_filter_todos']],
                            'producto'  => ['icon' => 'fa-box-open', 'label' => $lang['reviews_filter_producto']],
                            'vendedor'  => ['icon' => 'fa-store',    'label' => $lang['reviews_filter_vendedor']],
                            'comprador' => ['icon' => 'fa-shopping-cart', 'label' => $lang['reviews_filter_comprador']],
                        ];
                        foreach ($tabs as $key => $tab):
                            $activo = $tipo_filtro === $key ? 'ag-reviews-tab--activo' : '';
                        ?>

                            <a href="reviews.php?tipo=<?= $key ?><?= $busqueda ? '&q=' . urlencode($busqueda) : '' ?>"
                                class="ag-reviews-tab <?= $activo ?>" role="tab"
                                aria-selected="<?= $tipo_filtro === $key ? 'true' : 'false' ?>">
                                <i class="fas <?= $tab['icon'] ?>"></i>
                                <?= $tab['label'] ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Buscador -->
                    <div class="ag-reviews-filters__search">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($busqueda) ?>"
                            placeholder="<?= $lang['reviews_search_placeholder'] ?>" autocomplete="off">
                        <?php if ($busqueda): ?>
                            <a href="reviews.php?tipo=<?= $tipo_filtro ?>" class="ag-reviews-filters__clear">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                        <input type="hidden" name="tipo" value="<?= $tipo_filtro ?>">
                    </div>

                </form>
            </div>

            <!-- TABLA DE RESEÑAS -->
            <div class="ag-table-card">
                <div class="ag-table-card__header">
                    <h3>
                        <?= $lang['reviews_table_title'] ?>
                        <span class="ag-reviews-count"><?= $total_resenas ?></span>
                    </h3>
                </div>
                <div class="ag-table-card__body">
                    <table class="ag-table">
                        <thead>
                            <tr>
                                <th><?= $lang['reviews_col_tipo'] ?></th>
                                <th><?= $lang['reviews_col_autor'] ?></th>
                                <th><?= $lang['reviews_col_entidad'] ?></th>
                                <th><?= $lang['reviews_col_calificacion'] ?></th>
                                <th><?= $lang['reviews_col_comentario'] ?></th>
                                <th><?= $lang['reviews_col_fecha'] ?></th>
                                <th><?= $lang['reviews_col_acciones'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($resenas)): ?>
                                <tr>
                                    <td colspan="7" class="ag-reviews-empty">
                                        <i class="fas fa-star-half-alt"></i>
                                        <p><?= $lang['reviews_empty'] ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($resenas as $r): ?>
                                    <tr>
                                        <!-- Tipo badge -->
                                        <td>
                                            <span class="ag-reviews-type-badge ag-reviews-type-badge--<?= $r['tipo'] ?>">
                                                <?php if ($r['tipo'] === 'producto'): ?>
                                                    <i class="fas fa-box-open"></i>
                                                <?php elseif ($r['tipo'] === 'vendedor'): ?>
                                                    <i class="fas fa-store"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-shopping-cart"></i>
                                                <?php endif; ?>
                                                <?= $lang['reviews_tipo_' . $r['tipo']] ?>
                                            </span>
                                        </td>
                                        <!-- Autor -->
                                        <td>
                                            <div class="ag-table__user">
                                                <div class="ag-avatar ag-avatar--sm">
                                                    <?= strtoupper(substr($r['autor_nombre'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight:600;color:var(--ag-text-primary)">
                                                        <?= htmlspecialchars($r['autor_nombre']) ?>
                                                    </div>
                                                    <div style="font-size:.7rem;color:var(--ag-text-muted)">
                                                        <?= $lang['role_' . $r['autor_rol']] ?? $r['autor_rol'] ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- Entidad reseñada -->
                                        <td style="max-width:160px">
                                            <span class="ag-reviews-entidad"
                                                title="<?= htmlspecialchars($r['entidad_nombre']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($r['entidad_nombre'], 0, 30, '…')) ?>
                                            </span>
                                        </td>
                                        <!-- Calificación estrellas -->
                                        <td>
                                            <div class="ag-reviews-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $r['calificacion']
                                                                                ? 'ag-reviews-stars__filled'
                                                                                : 'ag-reviews-stars__empty' ?>"></i>
                                                <?php endfor; ?>
                                                <span class="ag-reviews-stars__num"><?= $r['calificacion'] ?></span>
                                            </div>
                                        </td>
                                        <!-- Comentario truncado -->
                                        <td style="max-width:220px">
                                            <?php if ($r['titulo']): ?>
                                                <div class="ag-reviews-titulo">
                                                    <?= htmlspecialchars(mb_strimwidth($r['titulo'], 0, 40, '…')) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="ag-reviews-comentario"
                                                title="<?= htmlspecialchars($r['comentario']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($r['comentario'], 0, 80, '…')) ?>
                                            </div>
                                        </td>
                                        <!-- Fecha -->
                                        <td style="white-space:nowrap;color:var(--ag-text-muted);font-size:.78rem">
                                            <?= date('d/m/Y', strtotime($r['fecha_resena'])) ?>
                                            <div style="font-size:.7rem">
                                                <?= date('H:i', strtotime($r['fecha_resena'])) ?>
                                            </div>
                                        </td>
                                        <!-- Acciones -->
                                        <td>
                                            <div class="ag-reviews-actions">
                                                <button class="ag-reviews-btn ag-reviews-btn--view"
                                                    onclick="verDetalle(<?= htmlspecialchars(json_encode($r)) ?>)"
                                                    title="<?= $lang['reviews_action_ver'] ?>"
                                                    aria-label="<?= $lang['reviews_action_ver'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="ag-reviews-btn ag-reviews-btn--delete"
                                                    onclick="confirmarEliminar(<?= $r['id_resena'] ?>, '<?= $r['tipo'] ?>')"
                                                    title="<?= $lang['reviews_action_eliminar'] ?>"
                                                    aria-label="<?= $lang['reviews_action_eliminar'] ?>">
                                                    <i class="fas fa-trash-alt"></i>
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
                <?php if ($total_paginas > 1): ?>
                    <div class="ag-reviews-pagination">
                        <span class="ag-reviews-pagination__info">
                            <?= $lang['reviews_mostrando'] ?>
                            <?= (($pagina_actual - 1) * $por_pagina) + 1 ?>–<?= min($pagina_actual * $por_pagina, $total_resenas) ?>
                            <?= $lang['reviews_de'] ?> <?= $total_resenas ?>
                        </span>
                        <div class="ag-reviews-pagination__pages">
                            <?php if ($pagina_actual > 1): ?>
                                <a href="?tipo=<?= $tipo_filtro ?>&q=<?= urlencode($busqueda) ?>&pagina=<?= $pagina_actual - 1 ?>"
                                    class="ag-reviews-pagination__btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $rango_inicio = max(1, $pagina_actual - 2);
                            $rango_fin    = min($total_paginas, $pagina_actual + 2);
                            for ($p = $rango_inicio; $p <= $rango_fin; $p++):
                            ?>
                                <a href="?tipo=<?= $tipo_filtro ?>&q=<?= urlencode($busqueda) ?>&pagina=<?= $p ?>"
                                    class="ag-reviews-pagination__btn <?= $p === $pagina_actual ? 'ag-reviews-pagination__btn--active' : '' ?>">
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($pagina_actual < $total_paginas): ?>
                                <a href="?tipo=<?= $tipo_filtro ?>&q=<?= urlencode($busqueda) ?>&pagina=<?= $pagina_actual + 1 ?>"
                                    class="ag-reviews-pagination__btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div><!-- /ag-table-card -->

        </div><!-- /ag-dashboard-body -->
    </main>

    <!-- ── MODAL DETALLE ─────────────────────────────────────── -->
    <div class="ag-modal-overlay" id="reviewModal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="ag-modal">
            <div class="ag-modal__header">
                <h2 class="ag-modal__title">
                    <i class="fas fa-star"></i>
                    <?= $lang['reviews_modal_title'] ?>
                </h2>
                <button class="ag-modal__close" onclick="cerrarModal()" aria-label="<?= $lang['close'] ?>">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="ag-modal__body" id="reviewModalBody">
                <!-- Se rellena via JS -->
            </div>
            <div class="ag-modal__footer">
                <button class="ag-btn ag-btn--danger" id="btnEliminarModal">
                    <i class="fas fa-trash-alt"></i>
                    <?= $lang['reviews_action_eliminar'] ?>
                </button>
                <button class="ag-btn ag-btn--secondary" onclick="cerrarModal()">
                    <?= $lang['close'] ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ── MODAL CONFIRMACIÓN ELIMINAR ──────────────────────── -->
    <div class="ag-modal-overlay" id="deleteModal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="ag-modal ag-modal--sm">
            <div class="ag-modal__header">
                <h2 class="ag-modal__title ag-modal__title--danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= $lang['reviews_confirm_delete_title'] ?>
                </h2>
                <button class="ag-modal__close" onclick="cerrarDeleteModal()" aria-label="<?= $lang['close'] ?>">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="ag-modal__body">
                <p style="color:var(--ag-text-secondary);line-height:1.6">
                    <?= $lang['reviews_confirm_delete_msg'] ?>
                </p>
            </div>
            <div class="ag-modal__footer">
                <button class="ag-btn ag-btn--danger" id="btnConfirmarEliminar">
                    <i class="fas fa-trash-alt"></i>
                    <?= $lang['delete'] ?>
                </button>
                <button class="ag-btn ag-btn--secondary" onclick="cerrarDeleteModal()">
                    <?= $lang['cancel'] ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ── TOAST ─────────────────────────────────────────────── -->
    <div class="ag-toast" id="agToast" role="status" aria-live="polite"></div>

    <script>
        window.ASCC = {
            theme: '<?= $theme ?>',
            lang: {
                chartUsersLabel: '',
                reviews_deleted: '<?= addslashes($lang['reviews_deleted']) ?>',
                reviews_error: '<?= addslashes($lang['reviews_error']) ?>',
            }
        };
    </script>
    <script src="../assets/js/admin-dashboard.js"></script>
    <script src="../assets/js/admin-reviews.js"></script>

</body>

</html>