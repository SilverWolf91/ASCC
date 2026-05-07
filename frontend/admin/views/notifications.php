<?php

/**
 * ASCC - Admin Notificaciones
 * Ruta: admin/notifications.php
 * Descripción: Gestión de notificaciones del sistema.
 *              Crear, enviar, listar y eliminar notificaciones.
 *              Destinatarios: todos, por rol, o usuario específico.
 *
 * Tablas:
 *   notificaciones         — registro principal
 *   notificaciones_leidas  — tracking de lectura por usuario
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
// CATÁLOGOS — sin hardcodear en vistas
// =============================================================================
$tipos_notif = [
    'info'    => ['label' => $lang['notif_tipo_info']    ?? 'Información', 'icon' => 'fa-info-circle',         'class' => 'an-tipo--info'],
    'success' => ['label' => $lang['notif_tipo_success'] ?? 'Éxito',       'icon' => 'fa-check-circle',        'class' => 'an-tipo--success'],
    'warning' => ['label' => $lang['notif_tipo_warning'] ?? 'Advertencia', 'icon' => 'fa-exclamation-triangle', 'class' => 'an-tipo--warning'],
    'danger'  => ['label' => $lang['notif_tipo_danger']  ?? 'Alerta',      'icon' => 'fa-times-circle',        'class' => 'an-tipo--danger'],
];

$roles_destino = [
    'todos'     => $lang['notif_dest_todos']     ?? 'Todos los usuarios',
    'vendedor'  => $lang['notif_dest_vendedor']  ?? 'Solo vendedores',
    'comprador' => $lang['notif_dest_comprador'] ?? 'Solo compradores',
    'mixto'     => $lang['notif_dest_mixto']     ?? 'Solo mixtos',
];

// =============================================================================
// ACCIONES POST
// =============================================================================
$feedback = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ------------------------------------------------------------------
    // CREAR / ENVIAR NOTIFICACIÓN
    // ------------------------------------------------------------------
    if ($action === 'crear') {

        $titulo          = trim($_POST['titulo']   ?? '');
        $mensaje         = trim($_POST['mensaje']  ?? '');
        $tipo            = trim($_POST['tipo']     ?? 'info');
        $dest_tipo       = trim($_POST['dest_tipo'] ?? 'rol'); // 'rol' | 'individual'
        $dest_rol        = trim($_POST['dest_rol'] ?? 'todos');
        $dest_usuario_id = (int)($_POST['dest_usuario_id'] ?? 0);

        // Validaciones
        if ($titulo === '') {
            $feedback = ['type' => 'error', 'msg' => $lang['notif_error_titulo'] ?? 'El título es obligatorio.'];
        } elseif ($mensaje === '') {
            $feedback = ['type' => 'error', 'msg' => $lang['notif_error_mensaje'] ?? 'El mensaje es obligatorio.'];
        } elseif (!array_key_exists($tipo, $tipos_notif)) {
            $feedback = ['type' => 'error', 'msg' => $lang['notif_error_tipo'] ?? 'Tipo inválido.'];
        } elseif ($dest_tipo === 'individual' && $dest_usuario_id <= 0) {
            $feedback = ['type' => 'error', 'msg' => $lang['notif_error_usuario'] ?? 'Selecciona un usuario destinatario.'];
        } else {

            if ($dest_tipo === 'rol') {
                // Broadcast por rol
                if (!array_key_exists($dest_rol, $roles_destino)) {
                    $dest_rol = 'todos';
                }
                $conexion->prepare(
                    "INSERT INTO notificaciones
                        (titulo, mensaje, tipo, destinatario_rol, id_destinatario, id_remitente)
                     VALUES
                        (:titulo, :mensaje, :tipo, :rol, NULL, :remitente)"
                )->execute([
                    ':titulo'    => $titulo,
                    ':mensaje'   => $mensaje,
                    ':tipo'      => $tipo,
                    ':rol'       => $dest_rol,
                    ':remitente' => $_SESSION['user_id'],
                ]);
            } else {
                // Individual — verificar que el usuario existe
                $check = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = :id");
                $check->execute([':id' => $dest_usuario_id]);
                if (!$check->fetch()) {
                    $feedback = ['type' => 'error', 'msg' => $lang['notif_error_usuario_404'] ?? 'Usuario no encontrado.'];
                    goto render;
                }
                $conexion->prepare(
                    "INSERT INTO notificaciones
                        (titulo, mensaje, tipo, destinatario_rol, id_destinatario, id_remitente)
                     VALUES
                        (:titulo, :mensaje, :tipo, NULL, :dest, :remitente)"
                )->execute([
                    ':titulo'    => $titulo,
                    ':mensaje'   => $mensaje,
                    ':tipo'      => $tipo,
                    ':dest'      => $dest_usuario_id,
                    ':remitente' => $_SESSION['user_id'],
                ]);
            }

            $feedback = ['type' => 'success', 'msg' => $lang['notif_created'] ?? 'Notificación enviada correctamente.'];
        }

        // ------------------------------------------------------------------
        // ELIMINAR NOTIFICACIÓN (soft delete — activa = 0)
        // ------------------------------------------------------------------
    } elseif ($action === 'eliminar') {

        $id_notif = (int)($_POST['id_notificacion'] ?? 0);
        if ($id_notif > 0) {
            $conexion->prepare(
                "UPDATE notificaciones SET activa = 0 WHERE id_notificacion = :id"
            )->execute([':id' => $id_notif]);
            $feedback = ['type' => 'success', 'msg' => $lang['notif_deleted'] ?? 'Notificación eliminada.'];
        }

        // ------------------------------------------------------------------
        // ELIMINAR TODAS (las filtradas o todas de una vez)
        // ------------------------------------------------------------------
    } elseif ($action === 'eliminar_todas') {

        $conexion->prepare(
            "UPDATE notificaciones SET activa = 0 WHERE activa = 1"
        )->execute();
        $feedback = ['type' => 'success', 'msg' => $lang['notif_deleted_all'] ?? 'Todas las notificaciones fueron eliminadas.'];
    }
}

render:

// =============================================================================
// PARÁMETROS DE FILTRO Y PAGINACIÓN
// =============================================================================
$filter_tipo = trim($_GET['tipo']  ?? '');
$filter_dest = trim($_GET['dest']  ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 15;
$offset      = ($page - 1) * $per_page;

if (!array_key_exists($filter_tipo, $tipos_notif)) $filter_tipo = '';

$where_parts = ['n.activa = 1'];
$params      = [];

if ($filter_tipo !== '') {
    $where_parts[] = "n.tipo = :tipo";
    $params[':tipo'] = $filter_tipo;
}
if ($filter_dest !== '') {
    if ($filter_dest === 'individual') {
        $where_parts[] = "n.destinatario_rol IS NULL";
    } elseif (array_key_exists($filter_dest, $roles_destino)) {
        $where_parts[] = "n.destinatario_rol = :dest_rol";
        $params[':dest_rol'] = $filter_dest;
    }
}

$where_sql = implode(' AND ', $where_parts);

// Total
$count_stmt = $conexion->prepare("SELECT COUNT(*) FROM notificaciones n WHERE {$where_sql}");
$count_stmt->execute($params);
$total_notifs = (int)$count_stmt->fetchColumn();
$total_pages  = (int)ceil($total_notifs / $per_page);

// Página
$params[':limit']  = $per_page;
$params[':offset'] = $offset;

$stmt = $conexion->prepare(
    "SELECT
         n.id_notificacion,
         n.titulo,
         n.mensaje,
         n.tipo,
         n.destinatario_rol,
         n.id_destinatario,
         n.fecha_creacion,
         u_dest.nombre   AS nombre_destinatario,
         u_rem.nombre    AS nombre_remitente,
         -- Contar cuántos la leyeron
         (SELECT COUNT(*) FROM notificaciones_leidas nl WHERE nl.id_notificacion = n.id_notificacion) AS total_leidas
     FROM   notificaciones n
     LEFT JOIN usuarios u_dest ON n.id_destinatario = u_dest.id_usuario
     LEFT JOIN usuarios u_rem  ON n.id_remitente    = u_rem.id_usuario
     WHERE  {$where_sql}
     ORDER  BY n.fecha_creacion DESC
     LIMIT  :limit OFFSET :offset"
);
foreach ($params as $key => $val) {
    $type = ($key === ':limit' || $key === ':offset') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $val, $type);
}
$stmt->execute();
$notificaciones = $stmt->fetchAll();

// =============================================================================
// KPIs
// =============================================================================
$kpi_raw = $conexion->query(
    "SELECT
        SUM(activa = 1)                       AS total_activas,
        SUM(activa = 1 AND tipo = 'info')     AS total_info,
        SUM(activa = 1 AND tipo = 'warning')  AS total_warning,
        SUM(activa = 1 AND tipo = 'danger')   AS total_danger,
        SUM(activa = 1 AND destinatario_rol IS NULL) AS total_individual
     FROM notificaciones"
)->fetch();

$total_leidas_global = (int)$conexion->query(
    "SELECT COUNT(DISTINCT id_notificacion) FROM notificaciones_leidas"
)->fetchColumn();

// =============================================================================
// LISTA DE USUARIOS PARA SELECTOR (individual)
// =============================================================================
$usuarios_lista = $conexion->query(
    "SELECT id_usuario, nombre, rol
     FROM   usuarios
     WHERE  rol IN ('vendedor','comprador','mixto')
     ORDER  BY nombre ASC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['notif_page_title'] ?? 'Notificaciones' ?> — ASCC Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin-notifications.css">
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
            <a href="banners.php" class="ag-sidebar__link"><i
                    class="fas fa-image"></i><span><?= $lang['nav_banners'] ?></span></a>
            <a href="notifications.php" class="ag-sidebar__link ag-sidebar__link--active"><i
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

        <header class="ag-topbar">
            <div class="ag-topbar__left">
                <button class="ag-topbar__menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
                <div class="ag-topbar__breadcrumb">
                    <span><?= $lang['admin'] ?></span>
                    <i class="fas fa-chevron-right"></i>
                    <span class="ag-topbar__breadcrumb--current"><?= $lang['nav_notifications'] ?></span>
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
                    <h1 class="ag-page-header__title"><?= $lang['notif_page_title'] ?? 'Notificaciones' ?></h1>
                    <p class="ag-page-header__subtitle">
                        <?= number_format((int)$kpi_raw['total_activas']) ?>
                        <?= $lang['notif_registered'] ?? 'notificaciones activas' ?>
                    </p>
                </div>
                <div class="an-header-actions">
                    <button class="an-btn-crear" id="btnAbrirCrear">
                        <i class="fas fa-plus"></i>
                        <span><?= $lang['notif_btn_create'] ?? 'Nueva notificación' ?></span>
                    </button>
                    <?php if ((int)$kpi_raw['total_activas'] > 0): ?>
                        <form method="POST" style="display:inline"
                            onsubmit="return confirm('<?= addslashes($lang['notif_confirm_delete_all'] ?? '¿Eliminar todas las notificaciones?') ?>')">
                            <input type="hidden" name="action" value="eliminar_todas">
                            <button type="submit" class="an-btn-danger">
                                <i class="fas fa-trash-alt"></i>
                                <span><?= $lang['notif_btn_delete_all'] ?? 'Eliminar todas' ?></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
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
            <div class="an-kpi-row">
                <div class="an-kpi">
                    <div class="an-kpi__icon an-kpi__icon--total"><i class="fas fa-bell"></i></div>
                    <div class="an-kpi__info">
                        <span class="an-kpi__num"><?= number_format((int)$kpi_raw['total_activas']) ?></span>
                        <span class="an-kpi__label"><?= $lang['notif_kpi_total'] ?? 'Enviadas' ?></span>
                    </div>
                </div>
                <div class="an-kpi">
                    <div class="an-kpi__icon an-kpi__icon--leidas"><i class="fas fa-eye"></i></div>
                    <div class="an-kpi__info">
                        <span class="an-kpi__num"><?= number_format($total_leidas_global) ?></span>
                        <span class="an-kpi__label"><?= $lang['notif_kpi_read'] ?? 'Leídas' ?></span>
                    </div>
                </div>
                <div class="an-kpi">
                    <div class="an-kpi__icon an-kpi__icon--warning"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="an-kpi__info">
                        <span class="an-kpi__num"><?= number_format((int)$kpi_raw['total_warning']) ?></span>
                        <span class="an-kpi__label"><?= $lang['notif_kpi_warnings'] ?? 'Advertencias' ?></span>
                    </div>
                </div>
                <div class="an-kpi">
                    <div class="an-kpi__icon an-kpi__icon--danger"><i class="fas fa-times-circle"></i></div>
                    <div class="an-kpi__info">
                        <span class="an-kpi__num"><?= number_format((int)$kpi_raw['total_danger']) ?></span>
                        <span class="an-kpi__label"><?= $lang['notif_kpi_alerts'] ?? 'Alertas' ?></span>
                    </div>
                </div>
            </div>

            <!-- FILTROS -->
            <div class="au-toolbar au-toolbar--multi">
                <form class="au-search-form" method="GET" action="notifications.php">
                    <select name="tipo" class="ap-filter-select" onchange="this.form.submit()">
                        <option value=""><?= $lang['notif_filter_all_types'] ?? 'Todos los tipos' ?></option>
                        <?php foreach ($tipos_notif as $key => $t): ?>
                            <option value="<?= $key ?>" <?= $filter_tipo === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="dest" class="ap-filter-select" onchange="this.form.submit()">
                        <option value=""><?= $lang['notif_filter_all_dest'] ?? 'Todos los destinatarios' ?></option>
                        <?php foreach ($roles_destino as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $filter_dest === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="individual" <?= $filter_dest === 'individual' ? 'selected' : '' ?>>
                            <?= $lang['notif_dest_individual'] ?? 'Usuario específico' ?>
                        </option>
                    </select>
                    <?php if ($filter_tipo || $filter_dest): ?>
                        <a href="notifications.php" class="an-btn-clear">
                            <i class="fas fa-times"></i>
                            <?= $lang['notif_clear_filters'] ?? 'Limpiar' ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- LISTA DE NOTIFICACIONES -->
            <?php if (empty($notificaciones)): ?>
                <div class="an-empty">
                    <div class="an-empty__icon"><i class="fas fa-bell-slash"></i></div>
                    <h3><?= $lang['notif_empty_title'] ?? 'No hay notificaciones' ?></h3>
                    <p><?= $lang['notif_empty_desc'] ?? 'Crea la primera notificación para comunicarte con tus usuarios.' ?>
                    </p>
                    <button class="an-btn-crear" onclick="abrirModal()">
                        <i class="fas fa-plus"></i>
                        <?= $lang['notif_btn_create'] ?? 'Nueva notificación' ?>
                    </button>
                </div>

            <?php else: ?>
                <div class="ag-table-card">
                    <div class="ag-table-card__body">
                        <table class="ag-table an-table">
                            <thead>
                                <tr>
                                    <th><?= $lang['notif_col_type']      ?? 'Tipo' ?></th>
                                    <th><?= $lang['notif_col_title']     ?? 'Título' ?></th>
                                    <th><?= $lang['notif_col_message']   ?? 'Mensaje' ?></th>
                                    <th><?= $lang['notif_col_dest']      ?? 'Destinatario' ?></th>
                                    <th><?= $lang['notif_col_read']      ?? 'Leídas' ?></th>
                                    <th><?= $lang['notif_col_date']      ?? 'Fecha' ?></th>
                                    <th><?= $lang['notif_col_actions']   ?? 'Acciones' ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notificaciones as $n):
                                    $tipo_data = $tipos_notif[$n['tipo']] ?? $tipos_notif['info'];
                                    $es_individual = $n['destinatario_rol'] === null;
                                ?>
                                    <tr>
                                        <!-- Tipo -->
                                        <td>
                                            <span class="an-tipo-badge <?= $tipo_data['class'] ?>">
                                                <i class="fas <?= $tipo_data['icon'] ?>"></i>
                                                <?= htmlspecialchars($tipo_data['label']) ?>
                                            </span>
                                        </td>

                                        <!-- Título -->
                                        <td>
                                            <span class="an-titulo"><?= htmlspecialchars($n['titulo']) ?></span>
                                        </td>

                                        <!-- Mensaje (truncado) -->
                                        <td>
                                            <span class="an-mensaje" title="<?= htmlspecialchars($n['mensaje']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($n['mensaje'], 0, 80, '…')) ?>
                                            </span>
                                        </td>

                                        <!-- Destinatario -->
                                        <td>
                                            <?php if ($es_individual): ?>
                                                <div class="ag-table__user">
                                                    <div class="ag-avatar ag-avatar--sm">
                                                        <?= strtoupper(substr($n['nombre_destinatario'] ?? 'U', 0, 1)) ?>
                                                    </div>
                                                    <span><?= htmlspecialchars($n['nombre_destinatario'] ?? '—') ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="an-dest-badge an-dest-badge--<?= $n['destinatario_rol'] ?>">
                                                    <i
                                                        class="fas <?= $n['destinatario_rol'] === 'todos' ? 'fa-users' : 'fa-user-tag' ?>"></i>
                                                    <?= htmlspecialchars($roles_destino[$n['destinatario_rol']] ?? $n['destinatario_rol']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Lecturas -->
                                        <td>
                                            <span class="an-reads">
                                                <i class="fas fa-eye"></i>
                                                <?= number_format((int)$n['total_leidas']) ?>
                                            </span>
                                        </td>

                                        <!-- Fecha -->
                                        <td class="au-table__date">
                                            <?= date('d/m/Y H:i', strtotime($n['fecha_creacion'])) ?>
                                        </td>

                                        <!-- Acciones -->
                                        <td>
                                            <div class="au-actions">
                                                <!-- Ver mensaje completo -->
                                                <button class="au-action-btn au-action-btn--view"
                                                    title="<?= $lang['notif_action_view'] ?? 'Ver detalle' ?>" onclick="verDetalle(<?= htmlspecialchars(json_encode([
                                                                                                                                        'titulo'  => $n['titulo'],
                                                                                                                                        'mensaje' => $n['mensaje'],
                                                                                                                                        'tipo'    => $n['tipo'],
                                                                                                                                        'dest'    => $es_individual
                                                                                                                                            ? ($n['nombre_destinatario'] ?? '—')
                                                                                                                                            : ($roles_destino[$n['destinatario_rol']] ?? $n['destinatario_rol']),
                                                                                                                                        'fecha'   => date('d/m/Y H:i', strtotime($n['fecha_creacion'])),
                                                                                                                                        'leidas'  => (int)$n['total_leidas'],
                                                                                                                                    ])) ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <!-- Eliminar -->
                                                <form method="POST" style="display:inline">
                                                    <input type="hidden" name="action" value="eliminar">
                                                    <input type="hidden" name="id_notificacion"
                                                        value="<?= $n['id_notificacion'] ?>">
                                                    <button type="submit" class="au-action-btn au-action-btn--delete"
                                                        title="<?= $lang['notif_action_delete'] ?? 'Eliminar' ?>"
                                                        onclick="return confirm('<?= addslashes($lang['notif_confirm_delete'] ?? '¿Eliminar esta notificación?') ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINACIÓN -->
                    <?php if ($total_pages > 1): ?>
                        <div class="au-pagination">
                            <span class="au-pagination__info">
                                <?= $lang['users_showing'] ?> <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_notifs) ?>
                                <?= $lang['users_of'] ?> <?= $total_notifs ?>
                            </span>
                            <div class="au-pagination__pages">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&<?= http_build_query(['tipo' => $filter_tipo, 'dest' => $filter_dest]) ?>"
                                        class="au-page-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                <?php for ($pg = max(1, $page - 2); $pg <= min($total_pages, $page + 2); $pg++): ?>
                                    <a href="?page=<?= $pg ?>&<?= http_build_query(['tipo' => $filter_tipo, 'dest' => $filter_dest]) ?>"
                                        class="au-page-btn <?= $pg === $page ? 'au-page-btn--active' : '' ?>"><?= $pg ?></a>
                                <?php endfor; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page + 1 ?>&<?= http_build_query(['tipo' => $filter_tipo, 'dest' => $filter_dest]) ?>"
                                        class="au-page-btn">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div><!-- /.ag-dashboard-body -->
    </main>

    <!-- =========================================================
         MODAL — CREAR NOTIFICACIÓN
    ========================================================== -->
    <div class="an-modal-backdrop" id="modalBackdrop" onclick="cerrarModal()"></div>

    <div class="an-modal" id="modalCrear" role="dialog" aria-modal="true">

        <div class="an-modal__header">
            <div class="an-modal__header-left">
                <div class="an-modal__icon"><i class="fas fa-bell"></i></div>
                <h2 class="an-modal__title"><?= $lang['notif_modal_title'] ?? 'Nueva notificación' ?></h2>
            </div>
            <button class="an-modal__close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" class="an-modal__form" id="formCrearNotif">
            <input type="hidden" name="action" value="crear">

            <div class="an-modal__body">
                <div class="an-form-grid">

                    <!-- Título -->
                    <div class="an-form-field an-form-field--full">
                        <label class="an-form-label" for="n_titulo">
                            <?= $lang['notif_field_title'] ?? 'Título' ?>
                            <span class="an-required">*</span>
                        </label>
                        <input type="text" id="n_titulo" name="titulo" class="an-form-input"
                            placeholder="<?= $lang['notif_field_title_ph'] ?? 'Ej: Mantenimiento programado' ?>"
                            maxlength="160" required>
                    </div>

                    <!-- Tipo -->
                    <div class="an-form-field">
                        <label class="an-form-label" for="n_tipo">
                            <?= $lang['notif_field_type'] ?? 'Tipo' ?>
                            <span class="an-required">*</span>
                        </label>
                        <select id="n_tipo" name="tipo" class="an-form-select" required onchange="actualizarPreview()">
                            <?php foreach ($tipos_notif as $key => $t): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($t['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tipo de destinatario -->
                    <div class="an-form-field">
                        <label class="an-form-label" for="n_dest_tipo">
                            <?= $lang['notif_field_dest_type'] ?? 'Enviar a' ?>
                            <span class="an-required">*</span>
                        </label>
                        <select id="n_dest_tipo" name="dest_tipo" class="an-form-select"
                            onchange="toggleDestinatario()">
                            <option value="rol"><?= $lang['notif_dest_por_rol'] ?? 'Por rol de usuario' ?></option>
                            <option value="individual"><?= $lang['notif_dest_individual'] ?? 'Usuario específico' ?>
                            </option>
                        </select>
                    </div>

                    <!-- Selector de rol (visible por defecto) -->
                    <div class="an-form-field an-form-field--full" id="wrapDestRol">
                        <label class="an-form-label" for="n_dest_rol">
                            <?= $lang['notif_field_dest_role'] ?? 'Rol destinatario' ?>
                        </label>
                        <select id="n_dest_rol" name="dest_rol" class="an-form-select">
                            <?php foreach ($roles_destino as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selector de usuario individual (oculto por defecto) -->
                    <div class="an-form-field an-form-field--full" id="wrapDestUsuario" style="display:none">
                        <label class="an-form-label" for="n_dest_usuario">
                            <?= $lang['notif_field_dest_user'] ?? 'Usuario destinatario' ?>
                        </label>
                        <select id="n_dest_usuario" name="dest_usuario_id" class="an-form-select">
                            <option value=""><?= $lang['notif_select_user'] ?? '— Selecciona un usuario —' ?></option>
                            <?php foreach ($usuarios_lista as $u): ?>
                                <option value="<?= $u['id_usuario'] ?>">
                                    <?= htmlspecialchars($u['nombre']) ?> (<?= htmlspecialchars($u['rol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Mensaje -->
                    <div class="an-form-field an-form-field--full">
                        <label class="an-form-label" for="n_mensaje">
                            <?= $lang['notif_field_message'] ?? 'Mensaje' ?>
                            <span class="an-required">*</span>
                        </label>
                        <textarea id="n_mensaje" name="mensaje" class="an-form-textarea"
                            placeholder="<?= $lang['notif_field_message_ph'] ?? 'Escribe el contenido de la notificación...' ?>"
                            rows="4" maxlength="1000" required oninput="actualizarContador(this)"></textarea>
                        <div class="an-form-counter">
                            <span id="contadorMensaje">0</span> / 1000
                        </div>
                    </div>

                    <!-- Preview de la notificación -->
                    <div class="an-form-field an-form-field--full">
                        <label class="an-form-label"><?= $lang['notif_field_preview'] ?? 'Vista previa' ?></label>
                        <div class="an-preview" id="notifPreview">
                            <div class="an-preview__icon" id="previewIcon"><i class="fas fa-info-circle"></i></div>
                            <div class="an-preview__content">
                                <span class="an-preview__title" id="previewTitle">
                                    <?= $lang['notif_preview_placeholder'] ?? 'Escribe un título...' ?>
                                </span>
                                <span class="an-preview__msg" id="previewMsg">
                                    <?= $lang['notif_preview_msg_placeholder'] ?? 'El mensaje aparecerá aquí' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="an-modal__footer">
                <button type="button" class="an-btn-secondary" onclick="cerrarModal()">
                    <?= $lang['cancel'] ?? 'Cancelar' ?>
                </button>
                <button type="submit" class="an-btn-primary" id="btnSubmitNotif">
                    <i class="fas fa-paper-plane"></i>
                    <?= $lang['notif_btn_send'] ?? 'Enviar notificación' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- =========================================================
         MODAL — VER DETALLE DE NOTIFICACIÓN
    ========================================================== -->
    <div class="an-modal an-modal--detail" id="modalDetalle" role="dialog" aria-modal="true">
        <div class="an-modal__header" id="detalleHeader">
            <div class="an-modal__header-left">
                <div class="an-modal__icon" id="detalleIcono"><i class="fas fa-info-circle"></i></div>
                <h2 class="an-modal__title" id="detalleTitulo">—</h2>
            </div>
            <button class="an-modal__close" onclick="cerrarDetalle()"><i class="fas fa-times"></i></button>
        </div>
        <div class="an-modal__body">
            <div class="an-detalle-grid">
                <div class="an-detalle-field">
                    <span class="an-detalle-label"><?= $lang['notif_detail_dest'] ?? 'Destinatario' ?></span>
                    <span class="an-detalle-value" id="detalleDest">—</span>
                </div>
                <div class="an-detalle-field">
                    <span class="an-detalle-label"><?= $lang['notif_detail_date'] ?? 'Fecha de envío' ?></span>
                    <span class="an-detalle-value" id="detalleFecha">—</span>
                </div>
                <div class="an-detalle-field">
                    <span class="an-detalle-label"><?= $lang['notif_detail_reads'] ?? 'Veces leída' ?></span>
                    <span class="an-detalle-value" id="detalleLeidas">—</span>
                </div>
            </div>
            <div class="an-detalle-mensaje">
                <span class="an-detalle-label"><?= $lang['notif_detail_message'] ?? 'Mensaje completo' ?></span>
                <p id="detalleMensaje">—</p>
            </div>
        </div>
        <div class="an-modal__footer">
            <button type="button" class="an-btn-secondary" onclick="cerrarDetalle()">
                <?= $lang['close'] ?? 'Cerrar' ?>
            </button>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script src="../assets/js/admin-notifications.js"></script>

    <!-- Inyección de claves de idioma para JS -->
    <script>
        window.NOTIF_LANG = {
            tipoIconos: <?= json_encode(array_combine(
                            array_keys($tipos_notif),
                            array_column($tipos_notif, 'icon')
                        )) ?>,
            tipoClases: <?= json_encode(array_combine(
                            array_keys($tipos_notif),
                            array_column($tipos_notif, 'class')
                        )) ?>,
            previewPlaceholder: '<?= addslashes($lang['notif_preview_placeholder']     ?? 'Escribe un título...') ?>',
            previewMsgPlaceholder: '<?= addslashes($lang['notif_preview_msg_placeholder'] ?? 'El mensaje aparecerá aquí') ?>',
        };
    </script>

</body>

</html>