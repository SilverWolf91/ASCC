<?php

// Charset UTF-8 forzado (header HTTP + interno)
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

/**
 * ASCC - Admin Productos
 * Ruta: admin/products.php
 * Descripción: Gestión de productos del marketplace.
 *              Listado, búsqueda, filtro, cambio de estado y eliminación.
 *
 * Tabla productos — Columnas:
 *   id_producto, codigo_producto, id_usuario, id_ubicacion,
 *   tipo_producto, categoria_principal, subcategoria, producto_especifico,
 *   descripcion, cantidad, unidad, precio,
 *   estado enum('disponible','vendido'), fecha_publicacion, fecha_venta
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
// ACCIONES POST — Cambiar estado / Eliminar
// =============================================================================
$feedback = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action     = $_POST['action']      ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);

    if ($product_id <= 0) {
        $feedback = ['type' => 'error', 'msg' => 'ID de producto inválido.'];
    } elseif ($action === 'toggle_estado') {
        // ---------------------------------------------------------------
        // CAMBIAR ESTADO: disponible ↔ vendido
        // ---------------------------------------------------------------
        $nuevo_estado    = trim($_POST['nuevo_estado'] ?? '');
        $estados_validos = ['disponible', 'vendido'];

        if (!in_array($nuevo_estado, $estados_validos)) {
            $feedback = ['type' => 'error', 'msg' => 'Estado inválido.'];
        } else {
            $stmt = $conexion->prepare(
                "UPDATE productos SET estado = :estado WHERE id_producto = :id"
            );
            $stmt->execute([':estado' => $nuevo_estado, ':id' => $product_id]);
            $feedback = ['type' => 'success', 'msg' => 'Estado del producto actualizado.'];
        }
    } elseif ($action === 'eliminar') {
        // ---------------------------------------------------------------
        // ELIMINAR — borra dependencias primero (FK constraints)
        // ---------------------------------------------------------------
        try {
            $conexion->beginTransaction();

            // 1. Imágenes del producto
            $conexion->prepare(
                "DELETE FROM imagenes_productos WHERE id_producto = :id"
            )->execute([':id' => $product_id]);

            // 2. Reseñas del producto
            $conexion->prepare(
                "DELETE FROM resenas_producto WHERE id_producto = :id"
            )->execute([':id' => $product_id]);

            // 3. Mensajes de conversaciones sobre este producto
            $conexion->prepare(
                "DELETE FROM mensajes WHERE id_conversacion IN (
                     SELECT id_conversacion FROM conversaciones WHERE id_producto = :id
                 )"
            )->execute([':id' => $product_id]);

            // 4. Conversaciones del producto
            $conexion->prepare(
                "DELETE FROM conversaciones WHERE id_producto = :id"
            )->execute([':id' => $product_id]);

            // 5. Transacciones del producto
            $conexion->prepare(
                "DELETE FROM transacciones WHERE id_producto = :id"
            )->execute([':id' => $product_id]);

            // 6. El producto
            $conexion->prepare(
                "DELETE FROM productos WHERE id_producto = :id"
            )->execute([':id' => $product_id]);

            $conexion->commit();
            $feedback = ['type' => 'success', 'msg' => 'Producto eliminado correctamente.'];
        } catch (Exception $e) {
            $conexion->rollBack();
            $feedback = ['type' => 'error', 'msg' => 'Error al eliminar: ' . $e->getMessage()];
        }
    }
}

// =============================================================================
// PARÁMETROS DE FILTRO Y BÚSQUEDA
// =============================================================================
$search         = trim($_GET['search']    ?? '');
$estado_filter  = $_GET['estado']         ?? '';
$cat_filter     = trim($_GET['categoria'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$per_page       = 15;
$offset         = ($page - 1) * $per_page;

$estados_validos = ['disponible', 'vendido'];
if (!in_array($estado_filter, $estados_validos)) $estado_filter = '';

// =============================================================================
// QUERY PRINCIPAL CON FILTROS
// =============================================================================
$where_parts = ['1=1'];
$params      = [];

if ($search !== '') {
    $where_parts[] = "(p.codigo_producto LIKE :s1
                       OR p.tipo_producto LIKE :s2
                       OR p.producto_especifico LIKE :s3
                       OR u.nombre LIKE :s4)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
    $params[':s4'] = "%{$search}%";
}
if ($estado_filter !== '') {
    $where_parts[] = "p.estado = :estado";
    $params[':estado'] = $estado_filter;
}
if ($cat_filter !== '') {
    $where_parts[] = "p.categoria_principal = :categoria";
    $params[':categoria'] = $cat_filter;
}

$where_sql = implode(' AND ', $where_parts);

// Total para paginación
$count_stmt = $conexion->prepare(
    "SELECT COUNT(*)
     FROM   productos p
     LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
     WHERE  {$where_sql}"
);
$count_stmt->execute($params);
$total_products = (int)$count_stmt->fetchColumn();
$total_pages    = (int)ceil($total_products / $per_page);

// Página actual
$params[':limit']  = $per_page;
$params[':offset'] = $offset;

$stmt = $conexion->prepare(
    "SELECT p.id_producto,
            p.codigo_producto,
            p.tipo_producto,
            p.categoria_principal,
            p.subcategoria,
            p.producto_especifico,
            p.descripcion,
            p.cantidad,
            p.unidad,
            p.precio,
            p.estado,
            p.fecha_publicacion,
            u.nombre     AS vendedor_nombre,
            u.id_usuario AS vendedor_id
     FROM   productos p
     LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
     WHERE  {$where_sql}
     ORDER  BY p.fecha_publicacion DESC
     LIMIT  :limit OFFSET :offset"
);
foreach ($params as $key => $val) {
    $type = ($key === ':limit' || $key === ':offset') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $val, $type);
}
$stmt->execute();
$products = $stmt->fetchAll();

// =============================================================================
// IMÁGENES — traer todas las imágenes de los productos en esta página
// =============================================================================
$imagenes_map = [];
if (!empty($products)) {
    $ids          = array_column($products, 'id_producto');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $img_stmt     = $conexion->prepare(
        "SELECT id_producto, ruta_imagen
         FROM   imagenes_productos
         WHERE  id_producto IN ({$placeholders})
         ORDER  BY id_imagen ASC"
    );
    $img_stmt->execute($ids);
    foreach ($img_stmt->fetchAll() as $img) {
        $imagenes_map[$img['id_producto']][] = $img['ruta_imagen'];
    }
}

// =============================================================================
// KPIs
// =============================================================================
$kpi_raw = $conexion->query(
    "SELECT
        COUNT(*)                                          AS total,
        SUM(estado = 'disponible')                        AS disponibles,
        SUM(estado = 'vendido')                           AS vendidos,
        COALESCE(SUM(precio * cantidad), 0)               AS valor_inventario
     FROM productos"
)->fetch();

// Categorías únicas para el filtro
$categorias = $conexion->query(
    "SELECT DISTINCT categoria_principal
     FROM productos
     WHERE categoria_principal IS NOT NULL
     ORDER BY categoria_principal ASC"
)->fetchAll(PDO::FETCH_COLUMN);

// =============================================================================
// HELPERS PHP
// =============================================================================
function formatPrecio(float $precio): string
{
    return '$' . number_format($precio, 0, ',', '.');
}

$categoria_iconos = [
    'Ganado Bovino'         => '🐄',
    'Caballos y Equinos'    => '🐎',
    'Ganado Menor'          => '🐖',
    'Aves de Corral'        => '🐔',
    'Peces y Acuicultura'   => '🐟',
    'Verduras y Hortalizas' => '🥦',
    'Frutas'                => '🍎',
    'Cereales y Granos'     => '🌾',
    'Lácteos'               => '🥛',
    'Huevos y Derivados'    => '🥚',
    'Cárnicos y Embutidos'  => '🥩',
    'Plantas y Semillas'    => '🌱',
    'Productos Procesados'  => '🏭',
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['products_page_title'] ?> — ASCC Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- admin-dashboard.css PRIMERO, admin-products.css DESPUÉS para que sus reglas tengan mayor especificidad -->
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/admin-products.css">
</head>

<body>

    <!-- =========================================================
         SIDEBAR
         CAMBIO #1: Agregado enlace change-password.php en sección Sistema
    ========================================================== -->
    <aside class="ag-sidebar" id="agSidebar">
        <div class="ag-sidebar__logo">
            <span class="ag-sidebar__logo-icon">🌾</span>
            <span class="ag-sidebar__logo-text">ASCC</span>
        </div>
        <nav class="ag-sidebar__nav">
            <p class="ag-sidebar__nav-label"><?= $lang['nav_main'] ?></p>
            <a href="dashboard.php" class="ag-sidebar__link">
                <i class="fas fa-chart-line"></i><span><?= $lang['nav_dashboard'] ?></span>
            </a>
            <a href="users.php" class="ag-sidebar__link">
                <i class="fas fa-users"></i><span><?= $lang['nav_users'] ?></span>
            </a>
            <a href="products.php" class="ag-sidebar__link ag-sidebar__link--active">
                <i class="fas fa-box-open"></i><span><?= $lang['nav_products'] ?></span>
            </a>
            <a href="transactions.php" class="ag-sidebar__link">
                <i class="fas fa-credit-card"></i><span><?= $lang['nav_transactions'] ?></span>
            </a>
            <p class="ag-sidebar__nav-label"><?= $lang['nav_content'] ?></p>
            <a href="categories.php" class="ag-sidebar__link">
                <i class="fas fa-tags"></i><span><?= $lang['nav_categories'] ?></span>
            </a>
            <a href="banners.php" class="ag-sidebar__link">
                <i class="fas fa-image"></i><span><?= $lang['nav_banners'] ?></span>
            </a>
            <a href="notifications.php" class="ag-sidebar__link">
                <i class="fas fa-bell"></i><span><?= $lang['nav_notifications'] ?></span>
            </a>
            <p class="ag-sidebar__nav-label"><?= $lang['nav_system'] ?></p>
            <a href="configuracion.php" class="ag-sidebar__link">
                <i class="fas fa-cog"></i><span><?= $lang['nav_settings'] ?></span>
            </a>
            <!-- CAMBIO #1 — Enlace cambiar contraseña -->
            <a href="change-password.php" class="ag-sidebar__link">
                <i class="fas fa-key"></i><span><?= $lang['cp_nav_label'] ?></span>
            </a>
            <a href="logout.php" class="ag-sidebar__link ag-sidebar__link--danger">
                <i class="fas fa-sign-out-alt"></i><span><?= $lang['nav_logout'] ?></span>
            </a>
        </nav>
        <button class="ag-sidebar__collapse-btn" id="sidebarToggle">
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
                <button class="ag-topbar__menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="ag-topbar__breadcrumb">
                    <span><?= $lang['admin'] ?></span>
                    <i class="fas fa-chevron-right"></i>
                    <span class="ag-topbar__breadcrumb--current"><?= $lang['nav_products'] ?></span>
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
                        <span class="ag-topbar__profile-name">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                        </span>
                        <span class="ag-topbar__profile-role"><?= $lang['admin'] ?></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="ag-dashboard-body">

            <!-- CABECERA DE PÁGINA -->
            <div class="ag-page-header">
                <div>
                    <h1 class="ag-page-header__title"><?= $lang['products_page_title'] ?></h1>
                    <p class="ag-page-header__subtitle">
                        <?= number_format((int)$kpi_raw['total']) ?>
                        <?= (int)$kpi_raw['total'] != 1
                            ? $lang['products_registered_pl']
                            : $lang['products_registered'] ?>
                    </p>
                </div>
            </div>

            <!-- FEEDBACK (éxito / error) -->
            <?php if ($feedback['type']): ?>
                <div class="au-feedback au-feedback--<?= $feedback['type'] ?>">
                    <i class="fas <?= $feedback['type'] === 'success'
                                        ? 'fa-check-circle'
                                        : 'fa-exclamation-triangle' ?>"></i>
                    <?= htmlspecialchars($feedback['msg']) ?>
                </div>
            <?php endif; ?>

            <!-- KPIs -->
            <div class="ap-kpi-row">
                <div class="ap-kpi">
                    <div class="ap-kpi__icon ap-kpi__icon--total">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div class="ap-kpi__info">
                        <span class="ap-kpi__num"><?= number_format((int)$kpi_raw['total']) ?></span>
                        <span class="ap-kpi__label"><?= $lang['products_kpi_total'] ?></span>
                    </div>
                </div>
                <div class="ap-kpi">
                    <div class="ap-kpi__icon ap-kpi__icon--disponible">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="ap-kpi__info">
                        <span class="ap-kpi__num"><?= number_format((int)$kpi_raw['disponibles']) ?></span>
                        <span class="ap-kpi__label"><?= $lang['products_kpi_available'] ?></span>
                    </div>
                </div>
                <div class="ap-kpi">
                    <div class="ap-kpi__icon ap-kpi__icon--vendido">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="ap-kpi__info">
                        <span class="ap-kpi__num"><?= number_format((int)$kpi_raw['vendidos']) ?></span>
                        <span class="ap-kpi__label"><?= $lang['products_kpi_sold'] ?></span>
                    </div>
                </div>
                <div class="ap-kpi">
                    <div class="ap-kpi__icon ap-kpi__icon--valor">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="ap-kpi__info">
                        <span class="ap-kpi__num ap-kpi__num--sm">
                            <?= formatPrecio((float)$kpi_raw['valor_inventario']) ?>
                        </span>
                        <span class="ap-kpi__label"><?= $lang['products_kpi_inventory'] ?></span>
                    </div>
                </div>
            </div>

            <!-- BARRA DE FILTROS Y BÚSQUEDA -->
            <div class="au-toolbar au-toolbar--multi">
                <form class="au-search-form" method="GET" action="products.php">

                    <!-- Búsqueda por texto -->
                    <div class="au-search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="<?= $lang['products_search_placeholder'] ?>"
                            value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                        <?php if ($search): ?>
                            <a href="products.php<?= ($estado_filter || $cat_filter)
                                                        ? '?' . http_build_query(['estado' => $estado_filter, 'categoria' => $cat_filter])
                                                        : '' ?>" class="au-search-clear">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Filtro por estado -->
                    <select name="estado" class="ap-filter-select" onchange="this.form.submit()">
                        <option value=""><?= $lang['products_filter_all_states'] ?></option>
                        <option value="disponible" <?= $estado_filter === 'disponible' ? 'selected' : '' ?>>
                            <?= $lang['products_status_available'] ?>
                        </option>
                        <option value="vendido" <?= $estado_filter === 'vendido' ? 'selected' : '' ?>>
                            <?= $lang['products_status_sold'] ?>
                        </option>
                    </select>

                    <!-- Filtro por categoría -->
                    <select name="categoria" class="ap-filter-select" onchange="this.form.submit()">
                        <option value=""><?= $lang['products_filter_all_cats'] ?></option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $cat_filter === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- CAMBIO #2 — Botón Buscar con color verde visible en dark/light -->
                    <button type="submit" class="au-search-btn">
                        <i class="fas fa-search" style="margin-right:6px;font-size:.8rem;"></i>
                        <?= $lang['search'] ?>
                    </button>
                </form>
            </div>

            <!-- TABLA DE PRODUCTOS -->
            <div class="ag-table-card">
                <div class="ag-table-card__body">
                    <table class="ag-table ap-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= $lang['products_col_code'] ?></th>
                                <th><?= $lang['products_col_product'] ?></th>
                                <th><?= $lang['products_col_category'] ?></th>
                                <th><?= $lang['products_col_seller'] ?></th>
                                <th><?= $lang['products_col_price'] ?></th>
                                <th><?= $lang['products_col_quantity'] ?></th>
                                <th><?= $lang['products_col_status'] ?></th>
                                <th><?= $lang['products_col_published'] ?></th>
                                <th><?= $lang['products_col_actions'] ?></th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="10" class="au-empty">
                                        <i class="fas fa-box-open"></i>
                                        <span>
                                            <?= $lang['products_empty'] ?>
                                            <?= $search ? ' "' . htmlspecialchars($search) . '"' : '' ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php else: ?>
                                <?php foreach ($products as $i => $p): ?>
                                    <?php
                                    $nombre_producto = trim(
                                        ($p['tipo_producto'] ?? '') .
                                            ($p['producto_especifico']
                                                ? ' — ' . $p['producto_especifico']
                                                : '')
                                    );
                                    $icono_cat = $categoria_iconos[$p['categoria_principal']] ?? '📦';
                                    $es_disp   = $p['estado'] === 'disponible';
                                    ?>
                                    <tr>

                                        <!-- # -->
                                        <td class="au-table__num"><?= $offset + $i + 1 ?></td>

                                        <!-- Código -->
                                        <td>
                                            <span class="ap-codigo">
                                                <?= htmlspecialchars($p['codigo_producto'] ?? '—') ?>
                                            </span>
                                        </td>

                                        <!-- Producto -->
                                        <td>
                                            <div class="ap-producto-cell">
                                                <span class="ap-producto-cell__nombre">
                                                    <?= htmlspecialchars($nombre_producto) ?>
                                                </span>
                                                <?php if ($p['subcategoria']): ?>
                                                    <span class="ap-producto-cell__sub">
                                                        <?= htmlspecialchars($p['subcategoria']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Categoría -->
                                        <td>
                                            <span class="ap-categoria-badge">
                                                <?= $icono_cat ?>
                                                <?= htmlspecialchars($p['categoria_principal'] ?? '—') ?>
                                            </span>
                                        </td>

                                        <!-- Vendedor -->
                                        <td>
                                            <div class="ag-table__user">
                                                <div class="ag-avatar ag-avatar--sm">
                                                    <?= strtoupper(substr($p['vendedor_nombre'] ?? 'V', 0, 1)) ?>
                                                </div>
                                                <span><?= htmlspecialchars($p['vendedor_nombre'] ?? '—') ?></span>
                                            </div>
                                        </td>

                                        <!-- Precio -->
                                        <td class="ap-precio">
                                            <?= formatPrecio((float)$p['precio']) ?>
                                        </td>

                                        <!-- Cantidad + unidad -->
                                        <td class="ap-cantidad">
                                            <?= number_format((int)$p['cantidad']) ?>
                                            <span class="ap-unidad"><?= htmlspecialchars($p['unidad']) ?></span>
                                        </td>

                                        <!-- Estado -->
                                        <td>
                                            <span class="ap-estado-badge ap-estado-badge--<?= $p['estado'] ?>">
                                                <i class="fas <?= $es_disp ? 'fa-circle-check' : 'fa-handshake' ?>"></i>
                                                <?= ucfirst($p['estado']) ?>
                                            </span>
                                        </td>

                                        <!-- Fecha publicación -->
                                        <td class="au-table__date">
                                            <?= date('d/m/Y', strtotime($p['fecha_publicacion'])) ?>
                                        </td>

                                        <!-- Acciones
                                     CAMBIO #3 — Clases au-action-btn con colores semánticos
                                     definidos en admin-products.css (azul/ámbar/rojo)
                                -->
                                        <td>
                                            <div class="au-actions">

                                                <!-- Ver detalle en drawer -->
                                                <button class="au-action-btn au-action-btn--view"
                                                    title="<?= $lang['products_action_view'] ?>"
                                                    onclick="openDetailModal(<?= htmlspecialchars(json_encode([
                                                                                    'id'           => $p['id_producto'],
                                                                                    'codigo'       => $p['codigo_producto'] ?? '',
                                                                                    'nombre'       => $nombre_producto,
                                                                                    'categoria'    => $p['categoria_principal'] ?? '',
                                                                                    'subcategoria' => $p['subcategoria'] ?? '',
                                                                                    'descripcion'  => $p['descripcion'] ?? '',
                                                                                    'precio'       => formatPrecio((float)$p['precio']),
                                                                                    'precio_num'   => (float)$p['precio'],
                                                                                    'cantidad'     => (int)$p['cantidad'],
                                                                                    'unidad'       => $p['unidad'] ?? '',
                                                                                    'estado'       => $p['estado'],
                                                                                    'vendedor'     => $p['vendedor_nombre'] ?? '',
                                                                                    'fecha'        => date('d/m/Y', strtotime($p['fecha_publicacion'])),
                                                                                    'imagenes'     => $imagenes_map[$p['id_producto']] ?? [],
                                                                                ])) ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                <!-- Cambiar estado disponible ↔ vendido -->
                                                <form method="POST" style="display:inline">
                                                    <input type="hidden" name="action" value="toggle_estado">
                                                    <input type="hidden" name="product_id" value="<?= $p['id_producto'] ?>">
                                                    <input type="hidden" name="nuevo_estado"
                                                        value="<?= $es_disp ? 'vendido' : 'disponible' ?>">
                                                    <button type="submit"
                                                        class="au-action-btn <?= $es_disp ? 'au-action-btn--block' : 'au-action-btn--unblock' ?>"
                                                        title="<?= $es_disp ? $lang['products_status_sold'] : $lang['products_status_available'] ?>"
                                                        onclick="return confirm('¿Cambiar estado a &quot;<?= $es_disp ? 'vendido' : 'disponible' ?>&quot;?')">
                                                        <i class="fas <?= $es_disp ? 'fa-handshake' : 'fa-undo' ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Eliminar producto -->
                                                <form method="POST" style="display:inline">
                                                    <input type="hidden" name="action" value="eliminar">
                                                    <input type="hidden" name="product_id" value="<?= $p['id_producto'] ?>">
                                                    <button type="submit" class="au-action-btn au-action-btn--delete"
                                                        title="<?= $lang['products_action_delete'] ?>"
                                                        onclick="return confirm('⚠️ ¿Eliminar &quot;<?= addslashes($nombre_producto) ?>&quot;?\n\nSe eliminarán también sus conversaciones y transacciones.\n\nNo se puede deshacer.')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>

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
                            <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_products) ?>
                            <?= $lang['users_of'] ?>
                            <?= $total_products ?>
                        </span>
                        <div class="au-pagination__pages">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&<?= http_build_query(['search' => $search, 'estado' => $estado_filter, 'categoria' => $cat_filter]) ?>"
                                    class="au-page-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($pg = max(1, $page - 2); $pg <= min($total_pages, $page + 2); $pg++): ?>
                                <a href="?page=<?= $pg ?>&<?= http_build_query(['search' => $search, 'estado' => $estado_filter, 'categoria' => $cat_filter]) ?>"
                                    class="au-page-btn <?= $pg === $page ? 'au-page-btn--active' : '' ?>">
                                    <?= $pg ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&<?= http_build_query(['search' => $search, 'estado' => $estado_filter, 'categoria' => $cat_filter]) ?>"
                                    class="au-page-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div><!-- /.ag-table-card -->

        </div><!-- /.ag-dashboard-body -->
    </main>

    <!-- =========================================================
         DRAWER — DETALLE DEL PRODUCTO
         Slide-in desde la derecha
         IMPORTANTE: colocado DESPUÉS de <main> para garantizar
         que su z-index sea efectivo sobre todo el contenido.
    ========================================================== -->

    <!-- Backdrop — oscurece el fondo sin heredar patrones del overlay global -->
    <div class="ap-drawer-backdrop" id="drawerBackdrop" onclick="closeDetailModal()"></div>

    <aside class="ap-drawer" id="detailDrawer">

        <!-- Header con gradiente de marca -->
        <div class="ap-drawer__header">
            <div class="ap-drawer__header-left">
                <div class="ap-drawer__header-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <div>
                    <h3 class="ap-drawer__title"><?= $lang['products_modal_detail_title'] ?></h3>
                    <span class="ap-drawer__subtitle" id="det_codigo_header"></span>
                </div>
            </div>
            <button class="ap-drawer__close" onclick="closeDetailModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body scrollable -->
        <div class="ap-drawer__body">

            <!-- Hero: nombre del producto + badge de estado -->
            <div class="ap-drawer__hero">
                <div class="ap-drawer__hero-name" id="det_nombre"></div>
                <span id="det_estado_drawer"></span>
            </div>

            <!-- Tarjeta de precio / cantidad / total
                 SIEMPRE visible: el CSS tiene display:flex !important -->
            <div class="ap-drawer__price-card" id="det_price_card">
                <div class="ap-drawer__price-card-left">
                    <span class="ap-drawer__price-label"><?= $lang['products_detail_price'] ?></span>
                    <span class="ap-drawer__price-value" id="det_precio_drawer"></span>
                </div>
                <div class="ap-drawer__price-card-right">
                    <span class="ap-drawer__price-label"><?= $lang['products_detail_quantity'] ?></span>
                    <span class="ap-drawer__price-qty" id="det_cantidad_drawer"></span>
                </div>
                <div class="ap-drawer__price-card-total">
                    <span class="ap-drawer__price-label"><?= $lang['products_detail_total'] ?></span>
                    <span class="ap-drawer__price-total" id="det_total_drawer"></span>
                </div>
            </div>

            <!-- Categoría y subcategoría -->
            <div class="ap-drawer__section">
                <div class="ap-drawer__row">
                    <div class="ap-drawer__field">
                        <span class="ap-drawer__field-label"><?= $lang['products_detail_category'] ?></span>
                        <span class="ap-drawer__field-value" id="det_categoria_drawer"></span>
                    </div>
                    <div class="ap-drawer__field">
                        <span class="ap-drawer__field-label"><?= $lang['products_detail_subcategory'] ?></span>
                        <span class="ap-drawer__field-value" id="det_subcategoria_drawer"></span>
                    </div>
                </div>
            </div>

            <div class="ap-drawer__divider"></div>

            <!-- Vendedor y fecha de publicación -->
            <div class="ap-drawer__section">
                <div class="ap-drawer__row">
                    <div class="ap-drawer__field">
                        <span class="ap-drawer__field-label"><?= $lang['products_detail_seller'] ?></span>
                        <div class="ap-drawer__seller">
                            <div class="ap-drawer__seller-avatar" id="det_vendedor_avatar"></div>
                            <span id="det_vendedor_nombre"></span>
                        </div>
                    </div>
                    <div class="ap-drawer__field">
                        <span class="ap-drawer__field-label"><?= $lang['products_detail_published'] ?></span>
                        <span class="ap-drawer__field-value" id="det_fecha_drawer"></span>
                    </div>
                </div>
            </div>

            <div class="ap-drawer__divider"></div>

            <!-- Descripción -->
            <div class="ap-drawer__section">
                <span class="ap-drawer__field-label"><?= $lang['products_detail_description'] ?></span>
                <p class="ap-drawer__desc" id="det_descripcion_drawer"></p>
            </div>

            <div class="ap-drawer__divider"></div>

            <!-- Galería de imágenes -->
            <div class="ap-drawer__section">
                <span class="ap-drawer__field-label"><?= $lang['products_detail_images'] ?></span>
                <div class="ap-gallery" id="det_galeria"></div>
                <p class="ap-gallery__empty" id="det_galeria_empty" style="display:none">
                    <?= $lang['products_detail_no_images'] ?>
                </p>
            </div>

        </div><!-- /.ap-drawer__body -->
    </aside>

    <!-- =========================================================
         SCRIPTS
         admin-dashboard.js PRIMERO (inicializa sidebar, tema, etc.)
         Lógica del drawer inline DESPUÉS para tener acceso al DOM
    ========================================================== -->
    <script src="assets/js/admin-dashboard.js"></script>
    <script>
        // ─────────────────────────────────────────────────────────────────
        // CLAVES DE IDIOMA — inyectadas desde PHP (sin hardcoded text en JS)
        // ─────────────────────────────────────────────────────────────────
        const LANG = {
            noImages: '<?= addslashes($lang["products_detail_no_images"]) ?>',
            noDescription: '<?= addslashes($lang["products_no_description"]) ?>',
            statusAvail: '<?= addslashes($lang["products_status_available"]) ?>',
            statusSold: '<?= addslashes($lang["products_status_sold"]) ?>',
        };

        // ─────────────────────────────────────────────────────────────────
        // pluralizarUnidad(unidad, cantidad)
        // Devuelve la unidad en singular (cantidad <= 1) o plural (> 1).
        // Cubre todas las unidades agropecuarias colombianas habituales.
        // ─────────────────────────────────────────────────────────────────
        function pluralizarUnidad(unidad, cantidad) {
            if (!unidad) return '';

            const u = unidad.trim().toLowerCase();
            const n = parseFloat(cantidad);

            // Mapa explícito singular → plural
            const mapaPlural = {
                // Peso
                'kilo': 'kilos',
                'kilogramo': 'kilogramos',
                'kg': 'kg',
                'gramo': 'gramos',
                'gr': 'gr',
                'g': 'g',
                'libra': 'libras',
                'lb': 'lb',
                'tonelada': 'toneladas',
                'arroba': 'arrobas',
                // Volumen
                'litro': 'litros',
                'lt': 'lt',
                'mililitro': 'mililitros',
                'ml': 'ml',
                // Empaque / contenedor
                'bulto': 'bultos',
                'saco': 'sacos',
                'bolsa': 'bolsas',
                'caja': 'cajas',
                'canasta': 'canastas',
                'canastilla': 'canastillas',
                'paquete': 'paquetes',
                'unidad': 'unidades',
                // Animales
                'cabeza': 'cabezas',
                'res': 'reses',
                'caballo': 'caballos',
                'yegua': 'yeguas',
                'pollo': 'pollos',
                'gallina': 'gallinas',
                'pavo': 'pavos',
                'cerdo': 'cerdos',
                'lechón': 'lechones',
                'lechon': 'lechones',
                'pez': 'peces',
                'trucha': 'truchas',
                'tilapia': 'tilapias',
                'docena': 'docenas',
                // Genérico
                'ejemplar': 'ejemplares',
                'lote': 'lotes',
            };

            // Singular: cantidad <= 1 → devolver el texto original sin tocar
            if (n <= 1) {
                return unidad.trim();
            }

            // Buscar en el mapa (clave en minúscula)
            if (mapaPlural[u]) {
                const plural = mapaPlural[u];
                // Preservar capitalización de la primera letra
                const first = unidad.trim()[0];
                if (first === first.toUpperCase() && first !== first.toLowerCase()) {
                    return plural.charAt(0).toUpperCase() + plural.slice(1);
                }
                return plural;
            }

            // Reglas genéricas de fallback para español
            if (u.endsWith('s') || u.endsWith('x')) return unidad.trim(); // ya plural
            if (/[aeiouáéíóúü]$/i.test(u)) return unidad.trim() + 's'; // vocal → +s
            return unidad.trim() + 'es'; // consonante → +es
        }

        // ─────────────────────────────────────────────────────────────────
        // formatCOP(num)
        // Formatea como precio COP. Devuelve '—' si el valor es inválido.
        // ─────────────────────────────────────────────────────────────────
        function formatCOP(num) {
            if (num === null || num === undefined || isNaN(Number(num))) return '—';
            return '$' + Number(num).toLocaleString('es-CO', {
                maximumFractionDigits: 0
            });
        }

        // ─────────────────────────────────────────────────────────────────
        // openDetailModal(p)
        // Rellena el drawer con los datos del producto y lo muestra.
        // ─────────────────────────────────────────────────────────────────
        function openDetailModal(p) {

            // ── Header
            document.getElementById('det_codigo_header').textContent = p.codigo || '';
            document.getElementById('det_nombre').textContent = p.nombre || '—';

            // ── Badge de estado
            const estadoEl = document.getElementById('det_estado_drawer');
            const isDisp = p.estado === 'disponible';
            estadoEl.className = 'ap-estado-badge ap-estado-badge--' + p.estado;
            estadoEl.innerHTML =
                '<i class="fas ' + (isDisp ? 'fa-circle-check' : 'fa-handshake') + '"></i> ' +
                (isDisp ? LANG.statusAvail : LANG.statusSold);

            // ── Precio unitario
            // Se muestra $0 si el producto no tiene precio definido (ej.: animales sin precio fijo)
            const precioNum = parseFloat(p.precio_num);
            document.getElementById('det_precio_drawer').textContent = !isNaN(precioNum) ? formatCOP(precioNum) : '—';

            // ── Cantidad con unidad en singular o plural
            const cantidad = parseInt(p.cantidad, 10) || 0;
            const unidadFmt = pluralizarUnidad(p.unidad, cantidad);
            document.getElementById('det_cantidad_drawer').textContent =
                cantidad + (unidadFmt ? ' ' + unidadFmt : '');

            // ── Valor total = precio × cantidad
            const total = (!isNaN(precioNum) ? precioNum : 0) * cantidad;
            document.getElementById('det_total_drawer').textContent = formatCOP(total);

            // ── Categorías
            document.getElementById('det_categoria_drawer').textContent = p.categoria || '—';
            document.getElementById('det_subcategoria_drawer').textContent = p.subcategoria || '—';

            // ── Vendedor — inicial en el avatar
            document.getElementById('det_vendedor_avatar').textContent =
                (p.vendedor || 'V').charAt(0).toUpperCase();
            document.getElementById('det_vendedor_nombre').textContent = p.vendedor || '—';

            // ── Fecha y descripción
            document.getElementById('det_fecha_drawer').textContent =
                p.fecha || '—';
            document.getElementById('det_descripcion_drawer').textContent =
                p.descripcion || LANG.noDescription;

            // ── Galería de imágenes
            const galeriaEl = document.getElementById('det_galeria');
            const emptyEl = document.getElementById('det_galeria_empty');
            galeriaEl.innerHTML = '';

            if (p.imagenes && p.imagenes.length > 0) {
                emptyEl.style.display = 'none';
                galeriaEl.style.display = 'grid';
                p.imagenes.forEach(function(ruta, idx) {
                    const img = document.createElement('img');
                    // BD guarda:  uploads/productos/archivo.jpg
                    // products.php en: ascc/admin/
                    // Imágenes en:     ascc/public/uploads/productos/
                    img.src = '../public/' + ruta;
                    img.alt = (p.nombre || 'Producto') + ' ' + (idx + 1);
                    img.className = 'ap-gallery__img';
                    img.loading = 'lazy';
                    img.onclick = function() {
                        openLightbox(img.src, p.nombre);
                    };
                    galeriaEl.appendChild(img);
                });
            } else {
                galeriaEl.style.display = 'none';
                emptyEl.style.display = 'flex';
            }

            // ── Mostrar drawer con animación slide-in
            document.getElementById('drawerBackdrop').classList.add('ap-drawer-backdrop--visible');
            document.getElementById('detailDrawer').classList.add('ap-drawer--open');
            document.body.style.overflow = 'hidden';
        }

        // ─────────────────────────────────────────────────────────────────
        // closeDetailModal()
        // Cierra el drawer con animación slide-out.
        // ─────────────────────────────────────────────────────────────────
        function closeDetailModal() {
            document.getElementById('drawerBackdrop').classList.remove('ap-drawer-backdrop--visible');
            document.getElementById('detailDrawer').classList.remove('ap-drawer--open');
            document.body.style.overflow = '';
        }

        // ─────────────────────────────────────────────────────────────────
        // openLightbox(src, alt)
        // Muestra una imagen en pantalla completa con backdrop difuminado.
        // ─────────────────────────────────────────────────────────────────
        function openLightbox(src, alt) {
            const lb = document.createElement('div');
            lb.className = 'ap-lightbox';
            lb.innerHTML =
                '<div class="ap-lightbox__backdrop" onclick="this.parentElement.remove()"></div>' +
                '<div class="ap-lightbox__content">' +
                '<img src="' + src + '" alt="' + (alt || '') + '">' +
                '<button class="ap-lightbox__close" onclick="this.closest(\'.ap-lightbox\').remove()">' +
                '<i class="fas fa-times"></i>' +
                '</button>' +
                '</div>';
            document.body.appendChild(lb);
            requestAnimationFrame(function() {
                lb.classList.add('ap-lightbox--visible');
            });
        }

        // ─────────────────────────────────────────────────────────────────
        // Tecla ESC — cierra drawer y lightbox
        // ─────────────────────────────────────────────────────────────────
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDetailModal();
                var lb = document.querySelector('.ap-lightbox');
                if (lb) lb.remove();
            }
        });
    </script>

</body>

</html>