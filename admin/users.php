<?php

// Charset UTF-8 forzado (header HTTP + interno)
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

/**
 * ASCC - Admin Usuarios
 * Ruta: admin/users.php
 * Descripción: Gestión de usuarios — Listar, Buscar, Bloquear, Editar, Eliminar.
 *
 * Tabla usuarios — Columnas usadas:
 *   id_usuario, nombre, email, telefono, cedula, rol, estado, fecha_registro
 *   estado: enum('activo', 'bloqueado') DEFAULT 'activo'
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
// ACCIONES POST — Bloquear / Desbloquear / Editar / Eliminar
// =============================================================================
$feedback = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action  = $_POST['action']     ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // Protección: nunca operar sobre el propio admin
    if ($user_id === (int)$_SESSION['user_id'] || $user_id <= 0) {
        $feedback = ['type' => 'error', 'msg' => 'Operación no permitida.'];
    } elseif ($action === 'toggle_estado') {
        // ---------------------------------------------------------------
        // BLOQUEAR / DESBLOQUEAR
        // ---------------------------------------------------------------
        $stmt = $conexion->prepare(
            "UPDATE usuarios
             SET    estado = IF(estado = 'activo', 'bloqueado', 'activo')
             WHERE  id_usuario = :id AND rol != 'admin'"
        );
        $stmt->execute([':id' => $user_id]);
        $feedback = ['type' => 'success', 'msg' => 'Estado del usuario actualizado.'];
    } elseif ($action === 'editar') {
        // ---------------------------------------------------------------
        // EDITAR — nombre, teléfono, rol
        // ---------------------------------------------------------------
        $nombre   = trim($_POST['nombre']   ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $rol      = trim($_POST['rol']      ?? '');

        $roles_validos = ['vendedor', 'comprador', 'mixto'];

        if (empty($nombre) || empty($email) || !in_array($rol, $roles_validos)) {
            $feedback = ['type' => 'error', 'msg' => 'Datos inválidos. Verifica el formulario.'];
        } else {
            // Verificar que el email no lo use otro usuario
            $check = $conexion->prepare(
                "SELECT COUNT(*) FROM usuarios WHERE email = :email AND id_usuario != :id"
            );
            $check->execute([':email' => $email, ':id' => $user_id]);

            if ((int)$check->fetchColumn() > 0) {
                $feedback = ['type' => 'error', 'msg' => 'Ese correo electrónico ya está en uso por otro usuario.'];
            } else {
                $stmt = $conexion->prepare(
                    "UPDATE usuarios
                     SET    nombre   = :nombre,
                            telefono = :telefono,
                            email    = :email,
                            rol      = :rol
                     WHERE  id_usuario = :id AND rol != 'admin'"
                );
                $stmt->execute([
                    ':nombre'   => $nombre,
                    ':telefono' => $telefono,
                    ':email'    => $email,
                    ':rol'      => $rol,
                    ':id'       => $user_id,
                ]);
                $feedback = ['type' => 'success', 'msg' => "Usuario \"{$nombre}\" actualizado correctamente."];
            }
        }
    } elseif ($action === 'eliminar') {
        // ---------------------------------------------------------------
        // ELIMINAR — borra primero las dependencias para evitar FK errors
        // Tablas que referencian id_usuario: conversaciones, mensajes,
        // transacciones, resenas_producto, resenas_vendedor, password_resets
        // ---------------------------------------------------------------
        try {
            $conexion->beginTransaction();

            // 1. Mensajes de conversaciones donde participa el usuario
            $conexion->prepare(
                "DELETE FROM mensajes WHERE id_conversacion IN (
                     SELECT id_conversacion FROM conversaciones
                     WHERE  id_comprador = :id OR id_vendedor = :id2
                 )"
            )->execute([':id' => $user_id, ':id2' => $user_id]);

            // 2. Conversaciones
            $conexion->prepare(
                "DELETE FROM conversaciones WHERE id_comprador = :id OR id_vendedor = :id2"
            )->execute([':id' => $user_id, ':id2' => $user_id]);

            // 3. Reseñas de producto
            $conexion->prepare(
                "DELETE FROM resenas_producto WHERE id_usuario = :id"
            )->execute([':id' => $user_id]);

            // 4. Reseñas de vendedor
            $conexion->prepare(
                "DELETE FROM resenas_vendedor WHERE id_comprador = :id OR id_vendedor = :id2"
            )->execute([':id' => $user_id, ':id2' => $user_id]);

            // 5. Restablecimiento de contraseña (se intenta, se ignora si la tabla tiene otra estructura)
            try {
                $conexion->prepare(
                    "DELETE FROM password_resets WHERE id_usuario = :id"
                )->execute([':id' => $user_id]);
            } catch (Exception $e) {
                // La tabla password_resets puede tener estructura diferente — se continúa sin error
            }

            // 6. Imágenes de productos del usuario (si tiene productos)
            $conexion->prepare(
                "DELETE FROM imagenes_productos WHERE id_producto IN (
                     SELECT id_producto FROM productos WHERE id_usuario = :id
                 )"
            )->execute([':id' => $user_id]);

            // 7. Productos del usuario
            $conexion->prepare(
                "DELETE FROM productos WHERE id_usuario = :id"
            )->execute([':id' => $user_id]);

            // 8. Transacciones (como comprador o vendedor)
            $conexion->prepare(
                "DELETE FROM transacciones WHERE id_comprador = :id OR id_vendedor = :id2"
            )->execute([':id' => $user_id, ':id2' => $user_id]);

            // 9. Finalmente el usuario
            $conexion->prepare(
                "DELETE FROM usuarios WHERE id_usuario = :id AND rol != 'admin'"
            )->execute([':id' => $user_id]);

            $conexion->commit();
            $feedback = ['type' => 'success', 'msg' => 'Usuario eliminado correctamente.'];
        } catch (Exception $e) {
            $conexion->rollBack();
            $feedback = ['type' => 'error', 'msg' => 'Error al eliminar: ' . $e->getMessage()];
        }
    }
}

// =============================================================================
// PARÁMETROS DE FILTRO Y BÚSQUEDA
// =============================================================================
$search     = trim($_GET['search'] ?? '');
$rol_filter = $_GET['rol']         ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 15;
$offset     = ($page - 1) * $per_page;

$allowed_roles = ['vendedor', 'comprador', 'mixto'];
if (!in_array($rol_filter, $allowed_roles)) $rol_filter = '';

// =============================================================================
// QUERY CON FILTROS
// =============================================================================
$where_parts = ["rol != 'admin'"];
$params      = [];

if ($search !== '') {
    $where_parts[] = "(nombre LIKE :search1 OR email LIKE :search2 OR cedula LIKE :search3)";
    $params[':search1'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
}
if ($rol_filter !== '') {
    $where_parts[] = "rol = :rol";
    $params[':rol'] = $rol_filter;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

// Total para paginación
$count_stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios {$where_sql}");
$count_stmt->execute($params);
$total_users = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total_users / $per_page);

// Página actual
$params[':limit']  = $per_page;
$params[':offset'] = $offset;

$stmt = $conexion->prepare(
    "SELECT id_usuario, nombre, email, telefono, cedula, rol, estado, fecha_registro
     FROM   usuarios
     {$where_sql}
     ORDER  BY fecha_registro DESC
     LIMIT  :limit OFFSET :offset"
);
foreach ($params as $key => $val) {
    $type = ($key === ':limit' || $key === ':offset') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $val, $type);
}
$stmt->execute();
$users = $stmt->fetchAll();

// =============================================================================
// KPIs rápidos
// =============================================================================
$kpi_roles = $conexion->query(
    "SELECT rol, COUNT(*) AS total FROM usuarios WHERE rol != 'admin' GROUP BY rol"
)->fetchAll();
$kpi = ['total' => 0, 'vendedor' => 0, 'comprador' => 0, 'mixto' => 0];
foreach ($kpi_roles as $r) {
    $kpi['total']    += $r['total'];
    $kpi[$r['rol']]   = (int)$r['total'];
}
$total_bloqueados = (int)$conexion->query(
    "SELECT COUNT(*) FROM usuarios WHERE estado = 'bloqueado' AND rol != 'admin'"
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['users_page_title'] ?> — ASCC Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/admin-users.css">
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
            <a href="users.php" class="ag-sidebar__link ag-sidebar__link--active"><i
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
            <a href="notifications.php" class="ag-sidebar__link"><i
                    class="fas fa-bell"></i><span><?= $lang['nav_notifications'] ?></span></a>
            <p class="ag-sidebar__nav-label"><?= $lang['nav_system'] ?></p>
            <a href="configuracion.php" class="ag-sidebar__link"><i
                    class="fas fa-cog"></i><span><?= $lang['nav_settings'] ?></span></a>
            <a href="logout.php" class="ag-sidebar__link ag-sidebar__link--danger"><i
                    class="fas fa-sign-out-alt"></i><span><?= $lang['nav_logout'] ?></span></a>
        </nav>
        <button class="ag-sidebar__collapse-btn" id="sidebarToggle"><i class="fas fa-chevron-left"
                id="sidebarToggleIcon"></i></button>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="ag-main" id="agMain">

        <header class="ag-topbar">
            <div class="ag-topbar__left">
                <button class="ag-topbar__menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
                <div class="ag-topbar__breadcrumb">
                    <span><?= $lang['admin'] ?></span>
                    <i class="fas fa-chevron-right"></i>
                    <span class="ag-topbar__breadcrumb--current"><?= $lang['nav_users'] ?></span>
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
                    <h1 class="ag-page-header__title"><?= $lang['users_page_title'] ?></h1>
                    <p class="ag-page-header__subtitle">
                        <?= $total_users ?> <?= $lang['users_subtitle'] ?>
                        <?php if ($total_bloqueados > 0): ?>
                            — <span style="color:var(--ag-color-danger)"><?= $total_bloqueados ?>
                                <?= $total_bloqueados !== 1 ? $lang['users_blocked_count_pl'] : $lang['users_blocked_count'] ?></span>
                        <?php endif; ?>
                    </p>
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

            <!-- KPIs DE ROL -->
            <div class="au-kpi-row">
                <div class="au-kpi <?= $rol_filter === '' ? 'au-kpi--active' : '' ?>">
                    <a href="users.php<?= $search ? '?search=' . urlencode($search) : '' ?>">
                        <span class="au-kpi__num"><?= $kpi['total'] ?></span>
                        <span class="au-kpi__label"><?= $lang['users_kpi_total'] ?></span>
                    </a>
                </div>
                <div class="au-kpi au-kpi--vendedor <?= $rol_filter === 'vendedor' ? 'au-kpi--active' : '' ?>">
                    <a href="users.php?rol=vendedor<?= $search ? '&search=' . urlencode($search) : '' ?>">
                        <span class="au-kpi__num"><?= $kpi['vendedor'] ?></span>
                        <span class="au-kpi__label"><i class="fas fa-store"></i>
                            <?= $lang['users_kpi_sellers'] ?></span>
                    </a>
                </div>
                <div class="au-kpi au-kpi--comprador <?= $rol_filter === 'comprador' ? 'au-kpi--active' : '' ?>">
                    <a href="users.php?rol=comprador<?= $search ? '&search=' . urlencode($search) : '' ?>">
                        <span class="au-kpi__num"><?= $kpi['comprador'] ?></span>
                        <span class="au-kpi__label"><i class="fas fa-shopping-cart"></i>
                            <?= $lang['users_kpi_buyers'] ?></span>
                    </a>
                </div>
                <div class="au-kpi au-kpi--mixto <?= $rol_filter === 'mixto' ? 'au-kpi--active' : '' ?>">
                    <a href="users.php?rol=mixto<?= $search ? '&search=' . urlencode($search) : '' ?>">
                        <span class="au-kpi__num"><?= $kpi['mixto'] ?></span>
                        <span class="au-kpi__label"><i class="fas fa-exchange-alt"></i>
                            <?= $lang['users_kpi_mixed'] ?></span>
                    </a>
                </div>
            </div>

            <!-- BARRA DE BÚSQUEDA -->
            <div class="au-toolbar">
                <form class="au-search-form" method="GET" action="users.php">
                    <?php if ($rol_filter): ?>
                        <input type="hidden" name="rol" value="<?= htmlspecialchars($rol_filter) ?>">
                    <?php endif; ?>
                    <div class="au-search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="<?= $lang['users_search_placeholder'] ?>"
                            value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                        <?php if ($search): ?>
                            <a href="users.php<?= $rol_filter ? '?rol=' . $rol_filter : '' ?>" class="au-search-clear">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="au-search-btn"><?= $lang['search'] ?></button>
                </form>
            </div>

            <!-- TABLA -->
            <div class="ag-table-card">
                <div class="ag-table-card__body">
                    <table class="ag-table au-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= $lang['users_col_user'] ?></th>
                                <th><?= $lang['users_col_email'] ?></th>
                                <th><?= $lang['users_col_cedula'] ?></th>
                                <th><?= $lang['users_col_phone'] ?></th>
                                <th><?= $lang['users_col_role'] ?></th>
                                <th><?= $lang['users_col_status'] ?></th>
                                <th><?= $lang['users_col_registered'] ?></th>
                                <th><?= $lang['users_col_actions'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="9" class="au-empty">
                                        <i class="fas fa-users-slash"></i>
                                        <span><?= $lang['users_empty'] ?><?= $search ? ' "' . htmlspecialchars($search) . '"' : '' ?></span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $i => $u): ?>
                                    <tr class="<?= $u['estado'] === 'bloqueado' ? 'au-row--blocked' : '' ?>">
                                        <td class="au-table__num"><?= $offset + $i + 1 ?></td>
                                        <td>
                                            <div class="ag-table__user">
                                                <div
                                                    class="ag-avatar ag-avatar--sm <?= $u['estado'] === 'bloqueado' ? 'ag-avatar--blocked' : '' ?>">
                                                    <?= strtoupper(substr($u['nombre'], 0, 1)) ?>
                                                </div>
                                                <span><?= htmlspecialchars($u['nombre']) ?></span>
                                            </div>
                                        </td>
                                        <td class="au-table__email"><?= htmlspecialchars($u['email']) ?></td>
                                        <td class="au-table__cedula"><?= htmlspecialchars($u['cedula']) ?></td>
                                        <td><?= htmlspecialchars($u['telefono']) ?></td>
                                        <td>
                                            <span class="ag-role-badge ag-role-badge--<?= $u['rol'] ?>">
                                                <?= $lang['role_' . $u['rol']] ?? $u['rol'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="au-estado-badge au-estado-badge--<?= $u['estado'] ?>">
                                                <i
                                                    class="fas <?= $u['estado'] === 'activo' ? 'fa-check-circle' : 'fa-ban' ?>"></i>
                                                <?= $u['estado'] === 'activo' ? $lang['users_status_active'] : $lang['users_status_blocked'] ?>
                                            </span>
                                        </td>
                                        <td class="au-table__date"><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></td>
                                        <td>
                                            <div class="au-actions">
                                                <!-- Editar -->
                                                <button class="au-action-btn au-action-btn--edit"
                                                    title="<?= $lang['users_action_edit'] ?>" onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                                                                                                            'id'       => $u['id_usuario'],
                                                                                                                            'nombre'   => $u['nombre'],
                                                                                                                            'email'    => $u['email'],
                                                                                                                            'telefono' => $u['telefono'],
                                                                                                                            'cedula'   => $u['cedula'],
                                                                                                                            'rol'      => $u['rol'],
                                                                                                                        ])) ?>)">
                                                    <i class="fas fa-pen"></i>
                                                </button>

                                                <!-- Bloquear / Desbloquear -->
                                                <form method="POST" style="display:inline">
                                                    <input type="hidden" name="action" value="toggle_estado">
                                                    <input type="hidden" name="user_id" value="<?= $u['id_usuario'] ?>">
                                                    <button type="submit"
                                                        class="au-action-btn <?= $u['estado'] === 'activo' ? 'au-action-btn--block' : 'au-action-btn--unblock' ?>"
                                                        title="<?= $u['estado'] === 'activo' ? $lang['users_action_block'] : $lang['users_action_unblock'] ?>"
                                                        onclick="return confirm('<?= $u['estado'] === 'activo' ? $lang['users_confirm_block'] : $lang['users_confirm_unblock'] ?> <?= addslashes($u['nombre']) ?>?')">
                                                        <i
                                                            class="fas <?= $u['estado'] === 'activo' ? 'fa-ban' : 'fa-unlock' ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Eliminar -->
                                                <form method="POST" style="display:inline">
                                                    <input type="hidden" name="action" value="eliminar">
                                                    <input type="hidden" name="user_id" value="<?= $u['id_usuario'] ?>">
                                                    <button type="submit" class="au-action-btn au-action-btn--delete"
                                                        title="<?= $lang['users_action_delete'] ?>"
                                                        onclick="return confirm('⚠️ <?= $lang['users_confirm_delete'] ?> <?= addslashes($u['nombre']) ?>?\n\n<?= $lang['users_delete_warning'] ?>')">
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
                            <?= $lang['users_showing'] ?> <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_users) ?>
                            <?= $lang['users_of'] ?> <?= $total_users ?>
                        </span>
                        <div class="au-pagination__pages">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $rol_filter ? '&rol=' . $rol_filter : '' ?>"
                                    class="au-page-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                                <a href="?page=<?= $p ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $rol_filter ? '&rol=' . $rol_filter : '' ?>"
                                    class="au-page-btn <?= $p === $page ? 'au-page-btn--active' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $rol_filter ? '&rol=' . $rol_filter : '' ?>"
                                    class="au-page-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- =====================================================================
     MODAL — EDITAR USUARIO
====================================================================== -->
    <div class="au-modal-overlay" id="editModal">
        <div class="au-modal">
            <div class="au-modal__header">
                <h3><i class="fas fa-user-edit"></i> <?= $lang['users_modal_edit_title'] ?></h3>
                <button class="au-modal__close" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="au-modal__form">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div class="au-modal__field">
                    <label><?= $lang['users_modal_name'] ?></label>
                    <input type="text" name="nombre" id="edit_nombre" required maxlength="100">
                </div>

                <div class="au-modal__field">
                    <label><?= $lang['users_modal_email'] ?></label>
                    <input type="email" name="email" id="edit_email" required maxlength="100">
                </div>

                <div class="au-modal__field">
                    <label><?= $lang['users_modal_phone'] ?></label>
                    <input type="text" name="telefono" id="edit_telefono" maxlength="20">
                </div>

                <div class="au-modal__field">
                    <label><?= $lang['users_modal_cedula'] ?> <span
                            class="au-modal__field-note"><?= $lang['users_modal_cedula_note'] ?></span></label>
                    <input type="text" id="edit_cedula" disabled>
                </div>

                <div class="au-modal__field">
                    <label><?= $lang['users_modal_role'] ?></label>
                    <select name="rol" id="edit_rol">
                        <option value="vendedor"><?= $lang['role_vendedor'] ?></option>
                        <option value="comprador"><?= $lang['role_comprador'] ?></option>
                        <option value="mixto"><?= $lang['role_mixto'] ?></option>
                    </select>
                </div>

                <div class="au-modal__actions">
                    <button type="button" class="au-modal__btn au-modal__btn--cancel" onclick="closeEditModal()">
                        <?= $lang['cancel'] ?>
                    </button>
                    <button type="submit" class="au-modal__btn au-modal__btn--save">
                        <i class="fas fa-save"></i> <?= $lang['save'] ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
    <script>
        // Modal editar
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_nombre').value = user.nombre;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_telefono').value = user.telefono;
            document.getElementById('edit_cedula').value = user.cedula;
            document.getElementById('edit_rol').value = user.rol;
            document.getElementById('editModal').classList.add('au-modal-overlay--visible');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('au-modal-overlay--visible');
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });

        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEditModal();
        });
    </script>
</body>

</html>