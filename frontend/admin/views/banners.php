<?php

/**
 * ASCC - Admin Banners
 * Ruta: admin/banners.php
 * Descripción: Gestión de banners promocionales del marketplace.
 *              CRUD completo: listar, crear, activar/desactivar, eliminar.
 *              Las imágenes se guardan en /public/uploads/banners/ (ruta relativa en BD).
 *
 * Tabla: banners
 *   id_banner, titulo, subtitulo, url_destino, ruta_imagen, alt_imagen,
 *   posicion, orden, activo, fecha_inicio, fecha_fin, clicks,
 *   id_usuario, fecha_creacion, fecha_actualizacion
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
// INTERNACIONALIZACIÓN Y TEMA
// =============================================================================
$lang_code = $_SESSION['lang'] ?? $_COOKIE['ag_lang'] ?? 'es';
$lang_file = __DIR__ . "/../../backend/admin/lang/{$lang_code}.php";
if (!file_exists($lang_file)) {
    $lang_code = 'es';
    $lang_file = __DIR__ . '/../../backend/admin/lang/es.php';
}
$lang  = require $lang_file;
$theme = $_COOKIE['ag_theme'] ?? 'light';
$theme = in_array($theme, ['light', 'dark']) ? $theme : 'light';

// =============================================================================
// CONEXIÓN
// =============================================================================
require_once __DIR__ . '/../../../backend/users/config/database.php';

// =============================================================================
// CONFIGURACIÓN DE SUBIDA DE IMÁGENES
// =============================================================================
// Ruta relativa desde /public/ — compatible con migración a CDN/nube:
// solo cambia BASE_URL en config, no la BD.
define('BANNERS_UPLOAD_DIR', __DIR__ . '/../public/uploads/banners/');
define('BANNERS_UPLOAD_REL', 'uploads/banners/');
define('BANNERS_MAX_SIZE',   3 * 1024 * 1024); // 3 MB
define('BANNERS_MIN_WIDTH',  800);              // px mínimo
define('BANNERS_TIPOS_MIME', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('BANNERS_EXTENSIONES', ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// Crear directorio si no existe
if (!is_dir(BANNERS_UPLOAD_DIR)) {
    mkdir(BANNERS_UPLOAD_DIR, 0755, true);
}

// =============================================================================
// POSICIONES DISPONIBLES — sin hardcodear en vistas
// =============================================================================
$posiciones_disponibles = [
    'hero'       => $lang['banner_pos_hero']       ?? 'Hero / Slider principal',
    'secundario' => $lang['banner_pos_secundario']  ?? 'Banner secundario',
    'categorias' => $lang['banner_pos_categorias']  ?? 'Sección categorías',
    'sidebar'    => $lang['banner_pos_sidebar']     ?? 'Barra lateral',
];

// =============================================================================
// ACCIONES POST
// =============================================================================
$feedback = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ------------------------------------------------------------------
    // CREAR BANNER
    // ------------------------------------------------------------------
    if ($action === 'crear') {

        $titulo      = trim($_POST['titulo']      ?? '');
        $subtitulo   = trim($_POST['subtitulo']   ?? '');
        $url_destino = trim($_POST['url_destino'] ?? '');
        $alt_imagen  = trim($_POST['alt_imagen']  ?? '');
        $posicion    = trim($_POST['posicion']     ?? 'hero');
        $orden       = max(0, (int)($_POST['orden'] ?? 0));
        $fecha_ini   = $_POST['fecha_inicio'] ?? '';
        $fecha_fin   = $_POST['fecha_fin']    ?? '';
        $activo      = isset($_POST['activo']) ? 1 : 0;

        // Validación básica
        if ($titulo === '') {
            $feedback = ['type' => 'error', 'msg' => $lang['banner_error_titulo'] ?? 'El título es obligatorio.'];
        } elseif (!array_key_exists($posicion, $posiciones_disponibles)) {
            $feedback = ['type' => 'error', 'msg' => $lang['banner_error_posicion'] ?? 'Posición inválida.'];
        } elseif (empty($_FILES['imagen']['name'])) {
            $feedback = ['type' => 'error', 'msg' => $lang['banner_error_imagen'] ?? 'La imagen es obligatoria.'];
        } else {
            // ── Validar y subir imagen
            $resultado_subida = subirImagenBanner($_FILES['imagen']);

            if ($resultado_subida['error']) {
                $feedback = ['type' => 'error', 'msg' => $resultado_subida['msg']];
            } else {
                $ruta_imagen = $resultado_subida['ruta'];

                $stmt = $conexion->prepare(
                    "INSERT INTO banners
                        (titulo, subtitulo, url_destino, ruta_imagen, alt_imagen,
                         posicion, orden, activo, fecha_inicio, fecha_fin, id_usuario)
                     VALUES
                        (:titulo, :subtitulo, :url_destino, :ruta_imagen, :alt_imagen,
                         :posicion, :orden, :activo,
                         :fecha_inicio, :fecha_fin, :id_usuario)"
                );
                $stmt->execute([
                    ':titulo'       => $titulo,
                    ':subtitulo'    => $subtitulo ?: null,
                    ':url_destino'  => $url_destino ?: null,
                    ':ruta_imagen'  => $ruta_imagen,
                    ':alt_imagen'   => $alt_imagen ?: $titulo,
                    ':posicion'     => $posicion,
                    ':orden'        => $orden,
                    ':activo'       => $activo,
                    ':fecha_inicio' => $fecha_ini ?: null,
                    ':fecha_fin'    => $fecha_fin ?: null,
                    ':id_usuario'   => $_SESSION['user_id'],
                ]);

                $feedback = ['type' => 'success', 'msg' => $lang['banner_created'] ?? 'Banner creado correctamente.'];
            }
        }

        // ------------------------------------------------------------------
        // TOGGLE ACTIVO / INACTIVO
        // ------------------------------------------------------------------
    } elseif ($action === 'toggle_activo') {

        $id_banner   = (int)($_POST['id_banner']  ?? 0);
        $nuevo_estado = (int)($_POST['nuevo_estado'] ?? 0);

        if ($id_banner > 0 && in_array($nuevo_estado, [0, 1])) {
            $conexion->prepare(
                "UPDATE banners SET activo = :activo WHERE id_banner = :id"
            )->execute([':activo' => $nuevo_estado, ':id' => $id_banner]);
            $feedback = ['type' => 'success', 'msg' => $lang['banner_updated'] ?? 'Estado actualizado.'];
        }

        // ------------------------------------------------------------------
        // ACTUALIZAR ORDEN
        // ------------------------------------------------------------------
    } elseif ($action === 'actualizar_orden') {

        $id_banner = (int)($_POST['id_banner'] ?? 0);
        $nuevo_orden = max(0, (int)($_POST['orden'] ?? 0));

        if ($id_banner > 0) {
            $conexion->prepare(
                "UPDATE banners SET orden = :orden WHERE id_banner = :id"
            )->execute([':orden' => $nuevo_orden, ':id' => $id_banner]);
            $feedback = ['type' => 'success', 'msg' => $lang['banner_order_updated'] ?? 'Orden actualizado.'];
        }

        // ------------------------------------------------------------------
        // ELIMINAR BANNER
        // ------------------------------------------------------------------
    } elseif ($action === 'eliminar') {

        $id_banner = (int)($_POST['id_banner'] ?? 0);

        if ($id_banner > 0) {
            // Obtener ruta de imagen para borrar el archivo físico
            $row = $conexion->prepare(
                "SELECT ruta_imagen FROM banners WHERE id_banner = :id"
            );
            $row->execute([':id' => $id_banner]);
            $banner_a_borrar = $row->fetch();

            $conexion->prepare(
                "DELETE FROM banners WHERE id_banner = :id"
            )->execute([':id' => $id_banner]);

            // Borrar archivo físico del servidor (no crítico si falla)
            if ($banner_a_borrar) {
                $archivo_fisico = __DIR__ . '/../public/' . $banner_a_borrar['ruta_imagen'];
                if (is_file($archivo_fisico)) {
                    @unlink($archivo_fisico);
                }
            }

            $feedback = ['type' => 'success', 'msg' => $lang['banner_deleted'] ?? 'Banner eliminado.'];
        }
    }
}

// =============================================================================
// FUNCIÓN — Subida segura de imágenes
// =============================================================================
function subirImagenBanner(array $file): array
{
    // 1. Verificar errores de PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => true, 'msg' => 'Error al subir el archivo (código ' . $file['error'] . ').'];
    }

    // 2. Verificar tamaño
    if ($file['size'] > BANNERS_MAX_SIZE) {
        $mb = round(BANNERS_MAX_SIZE / 1024 / 1024, 1);
        return ['error' => true, 'msg' => "La imagen supera el tamaño máximo de {$mb} MB."];
    }

    // 3. Validar MIME real (no confiar en la extensión del cliente)
    $mime_real = mime_content_type($file['tmp_name']);
    if (!in_array($mime_real, BANNERS_TIPOS_MIME)) {
        return ['error' => true, 'msg' => 'Formato no permitido. Use JPG, PNG, WebP o GIF.'];
    }

    // 4. Verificar que sea una imagen válida con GD
    $info = @getimagesize($file['tmp_name']);
    if (!$info) {
        return ['error' => true, 'msg' => 'El archivo no es una imagen válida.'];
    }

    // 5. Validar dimensiones mínimas
    if ($info[0] < BANNERS_MIN_WIDTH) {
        return ['error' => true, 'msg' => 'La imagen debe tener al menos ' . BANNERS_MIN_WIDTH . 'px de ancho.'];
    }

    // 6. Generar nombre único seguro — sin path traversal posible
    $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, BANNERS_EXTENSIONES)) $ext = 'jpg';
    $nombre    = 'banner_' . uniqid('', true) . '_' . time() . '.' . $ext;
    $destino   = BANNERS_UPLOAD_DIR . $nombre;

    // 7. Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        return ['error' => true, 'msg' => 'No se pudo guardar la imagen en el servidor.'];
    }

    return [
        'error' => false,
        'ruta'  => BANNERS_UPLOAD_REL . $nombre,
    ];
}

// =============================================================================
// LEER BANNERS DE LA BD — con filtros opcionales
// =============================================================================
$filter_pos    = trim($_GET['posicion'] ?? '');
$filter_estado = $_GET['estado'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 12;
$offset        = ($page - 1) * $per_page;

$where_parts = ['1=1'];
$params      = [];

if ($filter_pos !== '' && array_key_exists($filter_pos, $posiciones_disponibles)) {
    $where_parts[] = "b.posicion = :posicion";
    $params[':posicion'] = $filter_pos;
}
if ($filter_estado === '1' || $filter_estado === '0') {
    $where_parts[] = "b.activo = :activo";
    $params[':activo'] = (int)$filter_estado;
}

$where_sql = implode(' AND ', $where_parts);

// Total
$count_stmt = $conexion->prepare("SELECT COUNT(*) FROM banners b WHERE {$where_sql}");
$count_stmt->execute($params);
$total_banners = (int)$count_stmt->fetchColumn();
$total_pages   = (int)ceil($total_banners / $per_page);

// Página actual
$params[':limit']  = $per_page;
$params[':offset'] = $offset;

$stmt = $conexion->prepare(
    "SELECT b.id_banner, b.titulo, b.subtitulo, b.url_destino,
            b.ruta_imagen, b.alt_imagen, b.posicion, b.orden,
            b.activo, b.fecha_inicio, b.fecha_fin, b.clicks,
            b.fecha_creacion, u.nombre AS creado_por
     FROM   banners b
     LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario
     WHERE  {$where_sql}
     ORDER  BY b.posicion ASC, b.orden ASC, b.fecha_creacion DESC
     LIMIT  :limit OFFSET :offset"
);
foreach ($params as $key => $val) {
    $type = ($key === ':limit' || $key === ':offset') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $val, $type);
}
$stmt->execute();
$banners = $stmt->fetchAll();

// =============================================================================
// KPIs
// =============================================================================
$kpi_raw = $conexion->query(
    "SELECT
        COUNT(*)                         AS total,
        SUM(activo = 1)                  AS activos,
        SUM(activo = 0)                  AS inactivos,
        COALESCE(SUM(clicks), 0)         AS total_clicks
     FROM banners"
)->fetch();

// Banners por posición
$por_posicion = $conexion->query(
    "SELECT posicion, COUNT(*) AS total
     FROM banners
     GROUP BY posicion"
)->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['banner_page_title'] ?? 'Banners' ?> — ASCC Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin-banners.css">
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
            <a href="dashboard.php" class="ag-sidebar__link"><i
                    class="fas fa-chart-line"></i><span><?= $lang['nav_dashboard'] ?></span></a>
            <a href="users.php" class="ag-sidebar__link"><i
                    class="fas fa-users"></i><span><?= $lang['nav_users'] ?></span></a>
            <a href="products.php" class="ag-sidebar__link"><i
                    class="fas fa-box-open"></i><span><?= $lang['nav_products'] ?></span></a>
            <a href="transactions.php" class="ag-sidebar__link"><i
                    class="fas fa-credit-card"></i><span><?= $lang['nav_transactions'] ?></span></a>
            <p class="ag-sidebar__nav-label"><?= $lang['nav_content'] ?></p>
            <a href="categories.php" class="ag-sidebar__link"><i
                    class="fas fa-tags"></i><span><?= $lang['nav_categories'] ?></span></a>
            <a href="banners.php" class="ag-sidebar__link ag-sidebar__link--active"><i
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
                    <span class="ag-topbar__breadcrumb--current"><?= $lang['nav_banners'] ?></span>
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

        <div class="ag-dashboard-body">

            <!-- CABECERA -->
            <div class="ag-page-header">
                <div>
                    <h1 class="ag-page-header__title"><?= $lang['banner_page_title'] ?? 'Banners' ?></h1>
                    <p class="ag-page-header__subtitle">
                        <?= number_format((int)$kpi_raw['total']) ?>
                        <?= $lang['banner_registered'] ?? 'banners registrados' ?>
                    </p>
                </div>
                <!-- Botón abrir modal crear -->
                <button class="ab-btn-crear" id="btnAbrirCrear">
                    <i class="fas fa-plus"></i>
                    <span><?= $lang['banner_btn_create'] ?? 'Nuevo banner' ?></span>
                </button>
            </div>

            <!-- FEEDBACK -->
            <?php if ($feedback['type']): ?>
                <div class="au-feedback au-feedback--<?= $feedback['type'] ?>">
                    <i
                        class="fas <?= $feedback['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                    <?= htmlspecialchars($feedback['msg']) ?>
                </div>
            <?php endif; ?>

            <!-- KPIs -->
            <div class="ab-kpi-row">
                <div class="ab-kpi">
                    <div class="ab-kpi__icon ab-kpi__icon--total"><i class="fas fa-images"></i></div>
                    <div class="ab-kpi__info">
                        <span class="ab-kpi__num"><?= number_format((int)$kpi_raw['total']) ?></span>
                        <span class="ab-kpi__label"><?= $lang['banner_kpi_total'] ?? 'Total banners' ?></span>
                    </div>
                </div>
                <div class="ab-kpi">
                    <div class="ab-kpi__icon ab-kpi__icon--activo"><i class="fas fa-eye"></i></div>
                    <div class="ab-kpi__info">
                        <span class="ab-kpi__num"><?= number_format((int)$kpi_raw['activos']) ?></span>
                        <span class="ab-kpi__label"><?= $lang['banner_kpi_active'] ?? 'Activos' ?></span>
                    </div>
                </div>
                <div class="ab-kpi">
                    <div class="ab-kpi__icon ab-kpi__icon--inactivo"><i class="fas fa-eye-slash"></i></div>
                    <div class="ab-kpi__info">
                        <span class="ab-kpi__num"><?= number_format((int)$kpi_raw['inactivos']) ?></span>
                        <span class="ab-kpi__label"><?= $lang['banner_kpi_inactive'] ?? 'Inactivos' ?></span>
                    </div>
                </div>
                <div class="ab-kpi">
                    <div class="ab-kpi__icon ab-kpi__icon--clicks"><i class="fas fa-mouse-pointer"></i></div>
                    <div class="ab-kpi__info">
                        <span class="ab-kpi__num"><?= number_format((int)$kpi_raw['total_clicks']) ?></span>
                        <span class="ab-kpi__label"><?= $lang['banner_kpi_clicks'] ?? 'Clics totales' ?></span>
                    </div>
                </div>
            </div>

            <!-- FILTROS -->
            <div class="au-toolbar au-toolbar--multi">
                <form class="au-search-form" method="GET" action="banners.php">
                    <select name="posicion" class="ap-filter-select" onchange="this.form.submit()">
                        <option value=""><?= $lang['banner_filter_all_positions'] ?? 'Todas las posiciones' ?></option>
                        <?php foreach ($posiciones_disponibles as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $filter_pos === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="estado" class="ap-filter-select" onchange="this.form.submit()">
                        <option value=""><?= $lang['banner_filter_all_states'] ?? 'Todos los estados' ?></option>
                        <option value="1" <?= $filter_estado === '1' ? 'selected' : '' ?>>
                            <?= $lang['banner_active'] ?? 'Activo' ?></option>
                        <option value="0" <?= $filter_estado === '0' ? 'selected' : '' ?>>
                            <?= $lang['banner_inactive'] ?? 'Inactivo' ?></option>
                    </select>
                    <?php if ($filter_pos || $filter_estado !== ''): ?>
                        <a href="banners.php" class="ab-btn-clear">
                            <i class="fas fa-times"></i>
                            <?= $lang['banner_clear_filters'] ?? 'Limpiar filtros' ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- GRID DE BANNERS -->
            <?php if (empty($banners)): ?>
                <div class="ab-empty">
                    <div class="ab-empty__icon"><i class="fas fa-images"></i></div>
                    <h3><?= $lang['banner_empty_title'] ?? 'No hay banners aún' ?></h3>
                    <p><?= $lang['banner_empty_desc'] ?? 'Crea tu primer banner para empezar a promocionar el marketplace.' ?>
                    </p>
                    <button class="ab-btn-crear"
                        onclick="document.getElementById('modalCrear').classList.add('ab-modal--open')">
                        <i class="fas fa-plus"></i>
                        <?= $lang['banner_btn_create'] ?? 'Crear banner' ?>
                    </button>
                </div>
            <?php else: ?>
                <div class="ab-grid" id="abGrid">
                    <?php foreach ($banners as $b):
                        $es_activo     = (int)$b['activo'] === 1;
                        $img_src       = '../public/' . htmlspecialchars($b['ruta_imagen']);
                        $tiene_vigencia = $b['fecha_inicio'] || $b['fecha_fin'];
                        $pos_label     = $posiciones_disponibles[$b['posicion']] ?? $b['posicion'];

                        // Determinar si está dentro de la vigencia
                        $hoy = date('Y-m-d');
                        $vigente = true;
                        if ($b['fecha_inicio'] && $hoy < $b['fecha_inicio']) $vigente = false;
                        if ($b['fecha_fin']    && $hoy > $b['fecha_fin'])    $vigente = false;
                    ?>
                        <div class="ab-card <?= !$es_activo ? 'ab-card--inactive' : '' ?>" data-id="<?= $b['id_banner'] ?>">

                            <!-- Imagen de previsualización -->
                            <div class="ab-card__img-wrap"
                                onclick="abrirPreview('<?= $img_src ?>', '<?= htmlspecialchars(addslashes($b['titulo'])) ?>')">
                                <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($b['alt_imagen']) ?>" class="ab-card__img"
                                    loading="lazy" onerror="this.src='assets/img/banner-placeholder.svg'">
                                <div class="ab-card__img-overlay">
                                    <i class="fas fa-expand"></i>
                                </div>
                            </div>

                            <!-- Info del banner -->
                            <div class="ab-card__body">

                                <div class="ab-card__header">
                                    <div class="ab-card__badges">
                                        <!-- Badge de posición -->
                                        <span class="ab-badge ab-badge--pos ab-badge--pos-<?= $b['posicion'] ?>">
                                            <?= htmlspecialchars($pos_label) ?>
                                        </span>
                                        <!-- Badge activo/inactivo -->
                                        <span class="ab-badge <?= $es_activo ? 'ab-badge--active' : 'ab-badge--inactive' ?>">
                                            <i class="fas <?= $es_activo ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                            <?= $es_activo ? ($lang['banner_active'] ?? 'Activo') : ($lang['banner_inactive'] ?? 'Inactivo') ?>
                                        </span>
                                        <?php if ($tiene_vigencia && !$vigente): ?>
                                            <span class="ab-badge ab-badge--expired">
                                                <i class="fas fa-clock"></i>
                                                <?= $lang['banner_expired'] ?? 'Expirado' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Orden -->
                                    <div class="ab-card__orden-wrap" title="<?= $lang['banner_order'] ?? 'Orden' ?>">
                                        <i class="fas fa-sort"></i>
                                        <input type="number" class="ab-orden-input" value="<?= (int)$b['orden'] ?>" min="0"
                                            max="99" data-id="<?= $b['id_banner'] ?>" onchange="actualizarOrden(this)">
                                    </div>
                                </div>

                                <h3 class="ab-card__titulo"><?= htmlspecialchars($b['titulo']) ?></h3>

                                <?php if ($b['subtitulo']): ?>
                                    <p class="ab-card__subtitulo"><?= htmlspecialchars($b['subtitulo']) ?></p>
                                <?php endif; ?>

                                <?php if ($b['url_destino']): ?>
                                    <a href="<?= htmlspecialchars($b['url_destino']) ?>" target="_blank" rel="noopener"
                                        class="ab-card__url">
                                        <i class="fas fa-external-link-alt"></i>
                                        <?= htmlspecialchars($b['url_destino']) ?>
                                    </a>
                                <?php endif; ?>

                                <!-- Métricas -->
                                <div class="ab-card__meta">
                                    <span>
                                        <i class="fas fa-mouse-pointer"></i>
                                        <?= number_format((int)$b['clicks']) ?> <?= $lang['banner_clicks'] ?? 'clics' ?>
                                    </span>
                                    <?php if ($tiene_vigencia): ?>
                                        <span>
                                            <i class="fas fa-calendar"></i>
                                            <?= $b['fecha_inicio'] ? date('d/m/Y', strtotime($b['fecha_inicio'])) : '∞' ?>
                                            →
                                            <?= $b['fecha_fin']    ? date('d/m/Y', strtotime($b['fecha_fin']))    : '∞' ?>
                                        </span>
                                    <?php endif; ?>
                                    <span>
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($b['creado_por'] ?? 'Admin') ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Acciones -->
                            <div class="ab-card__actions">

                                <!-- Toggle activo/inactivo -->
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle_activo">
                                    <input type="hidden" name="id_banner" value="<?= $b['id_banner'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?= $es_activo ? 0 : 1 ?>">
                                    <button type="submit"
                                        class="au-action-btn <?= $es_activo ? 'au-action-btn--block' : 'au-action-btn--unblock' ?>"
                                        title="<?= $es_activo ? ($lang['banner_deactivate'] ?? 'Desactivar') : ($lang['banner_activate'] ?? 'Activar') ?>">
                                        <i class="fas <?= $es_activo ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                    </button>
                                </form>

                                <!-- Eliminar -->
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="eliminar">
                                    <input type="hidden" name="id_banner" value="<?= $b['id_banner'] ?>">
                                    <button type="submit" class="au-action-btn au-action-btn--delete"
                                        title="<?= $lang['banner_delete'] ?? 'Eliminar' ?>"
                                        onclick="return confirm('<?= addslashes($lang['banner_confirm_delete'] ?? '¿Eliminar este banner? No se puede deshacer.') ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINACIÓN -->
                <?php if ($total_pages > 1): ?>
                    <div class="au-pagination">
                        <span class="au-pagination__info">
                            <?= $lang['users_showing'] ?>
                            <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_banners) ?>
                            <?= $lang['users_of'] ?>
                            <?= $total_banners ?>
                        </span>
                        <div class="au-pagination__pages">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&<?= http_build_query(['posicion' => $filter_pos, 'estado' => $filter_estado]) ?>"
                                    class="au-page-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            <?php for ($pg = max(1, $page - 2); $pg <= min($total_pages, $page + 2); $pg++): ?>
                                <a href="?page=<?= $pg ?>&<?= http_build_query(['posicion' => $filter_pos, 'estado' => $filter_estado]) ?>"
                                    class="au-page-btn <?= $pg === $page ? 'au-page-btn--active' : '' ?>">
                                    <?= $pg ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&<?= http_build_query(['posicion' => $filter_pos, 'estado' => $filter_estado]) ?>"
                                    class="au-page-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div><!-- /.ag-dashboard-body -->
    </main>

    <!-- =========================================================
         MODAL — CREAR BANNER
    ========================================================== -->
    <div class="ab-modal-backdrop" id="modalBackdrop" onclick="cerrarModal()"></div>

    <div class="ab-modal" id="modalCrear" role="dialog" aria-modal="true"
        aria-label="<?= $lang['banner_modal_title'] ?? 'Crear banner' ?>">

        <div class="ab-modal__header">
            <div class="ab-modal__header-left">
                <div class="ab-modal__icon"><i class="fas fa-plus"></i></div>
                <h2 class="ab-modal__title"><?= $lang['banner_modal_title'] ?? 'Nuevo banner' ?></h2>
            </div>
            <button class="ab-modal__close" onclick="cerrarModal()" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="ab-modal__form" id="formCrearBanner">
            <input type="hidden" name="action" value="crear">

            <div class="ab-modal__body">

                <!-- ZONA DE PREVISUALIZACIÓN + SUBIDA -->
                <div class="ab-upload-zone" id="uploadZone">
                    <input type="file" name="imagen" id="inputImagen" accept=".jpg,.jpeg,.png,.webp,.gif"
                        class="ab-upload-zone__input">
                    <div class="ab-upload-zone__placeholder" id="uploadPlaceholder">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <strong><?= $lang['banner_upload_prompt'] ?? 'Arrastra la imagen aquí o haz clic' ?></strong>
                        <span><?= $lang['banner_upload_hint'] ?? 'JPG, PNG, WebP · Máx. 3MB · Mín. 800px de ancho' ?></span>
                    </div>
                    <img src="" alt="" class="ab-upload-zone__preview" id="imgPreview" style="display:none">
                    <button type="button" class="ab-upload-zone__clear" id="btnClearImg" style="display:none"
                        onclick="limpiarImagen()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- CAMPOS DEL FORMULARIO -->
                <div class="ab-form-grid">

                    <!-- Título -->
                    <div class="ab-form-field ab-form-field--full">
                        <label class="ab-form-label" for="titulo">
                            <?= $lang['banner_field_title'] ?? 'Título' ?>
                            <span class="ab-required">*</span>
                        </label>
                        <input type="text" id="titulo" name="titulo" class="ab-form-input"
                            placeholder="<?= $lang['banner_field_title_ph'] ?? 'Ej: Temporada de papa criolla' ?>"
                            maxlength="120" required>
                    </div>

                    <!-- Subtítulo -->
                    <div class="ab-form-field ab-form-field--full">
                        <label class="ab-form-label" for="subtitulo">
                            <?= $lang['banner_field_subtitle'] ?? 'Subtítulo' ?>
                            <span class="ab-optional">(<?= $lang['optional'] ?? 'opcional' ?>)</span>
                        </label>
                        <input type="text" id="subtitulo" name="subtitulo" class="ab-form-input"
                            placeholder="<?= $lang['banner_field_subtitle_ph'] ?? 'Ej: Mejores precios directos del campo' ?>"
                            maxlength="255">
                    </div>

                    <!-- URL de destino -->
                    <div class="ab-form-field ab-form-field--full">
                        <label class="ab-form-label" for="url_destino">
                            <?= $lang['banner_field_url'] ?? 'URL de destino' ?>
                            <span class="ab-optional">(<?= $lang['optional'] ?? 'opcional' ?>)</span>
                        </label>
                        <input type="url" id="url_destino" name="url_destino" class="ab-form-input"
                            placeholder="<?= $lang['banner_field_url_ph'] ?? 'https://... o /ruta-interna' ?>">
                    </div>

                    <!-- Texto alternativo -->
                    <div class="ab-form-field ab-form-field--full">
                        <label class="ab-form-label" for="alt_imagen">
                            <?= $lang['banner_field_alt'] ?? 'Texto alternativo (accesibilidad)' ?>
                        </label>
                        <input type="text" id="alt_imagen" name="alt_imagen" class="ab-form-input"
                            placeholder="<?= $lang['banner_field_alt_ph'] ?? 'Describe brevemente la imagen' ?>"
                            maxlength="180">
                    </div>

                    <!-- Posición -->
                    <div class="ab-form-field">
                        <label class="ab-form-label" for="posicion">
                            <?= $lang['banner_field_position'] ?? 'Posición' ?>
                            <span class="ab-required">*</span>
                        </label>
                        <select id="posicion" name="posicion" class="ab-form-select" required>
                            <?php foreach ($posiciones_disponibles as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Orden -->
                    <div class="ab-form-field">
                        <label class="ab-form-label" for="orden">
                            <?= $lang['banner_field_order'] ?? 'Orden de aparición' ?>
                        </label>
                        <input type="number" id="orden" name="orden" class="ab-form-input" value="0" min="0" max="99">
                        <span class="ab-form-hint"><?= $lang['banner_field_order_hint'] ?? '0 = primero' ?></span>
                    </div>

                    <!-- Fecha inicio -->
                    <div class="ab-form-field">
                        <label class="ab-form-label" for="fecha_inicio">
                            <?= $lang['banner_field_start'] ?? 'Fecha de inicio' ?>
                            <span class="ab-optional">(<?= $lang['optional'] ?? 'opcional' ?>)</span>
                        </label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="ab-form-input">
                        <span
                            class="ab-form-hint"><?= $lang['banner_field_date_hint'] ?? 'Dejar vacío = sin límite' ?></span>
                    </div>

                    <!-- Fecha fin -->
                    <div class="ab-form-field">
                        <label class="ab-form-label" for="fecha_fin">
                            <?= $lang['banner_field_end'] ?? 'Fecha de fin' ?>
                            <span class="ab-optional">(<?= $lang['optional'] ?? 'opcional' ?>)</span>
                        </label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="ab-form-input">
                    </div>

                    <!-- Activo -->
                    <div class="ab-form-field ab-form-field--full">
                        <label class="ab-toggle-label">
                            <input type="checkbox" name="activo" value="1" checked class="ab-toggle-input"
                                id="chkActivo">
                            <span class="ab-toggle-switch"></span>
                            <span class="ab-toggle-text" id="toggleText">
                                <?= $lang['banner_active'] ?? 'Activo' ?> —
                                <?= $lang['banner_toggle_hint'] ?? 'Visible en el marketplace' ?>
                            </span>
                        </label>
                    </div>

                </div><!-- /.ab-form-grid -->
            </div><!-- /.ab-modal__body -->

            <div class="ab-modal__footer">
                <button type="button" class="ab-btn-secondary" onclick="cerrarModal()">
                    <?= $lang['cancel'] ?? 'Cancelar' ?>
                </button>
                <button type="submit" class="ab-btn-primary" id="btnSubmitBanner">
                    <i class="fas fa-save"></i>
                    <?= $lang['banner_btn_save'] ?? 'Guardar banner' ?>
                </button>
            </div>
        </form>
    </div><!-- /.ab-modal -->

    <!-- =========================================================
         LIGHTBOX — Previsualización de imagen
    ========================================================== -->
    <div class="ap-lightbox" id="lightboxBanner" style="display:none">
        <div class="ap-lightbox__backdrop" onclick="cerrarLightbox()"></div>
        <div class="ap-lightbox__content">
            <img src="" alt="" id="lightboxImg">
            <button class="ap-lightbox__close" onclick="cerrarLightbox()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script src="../assets/js/admin-banners.js"></script>

</body>

</html>