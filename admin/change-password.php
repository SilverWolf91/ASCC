<?php

// Charset UTF-8 forzado (header HTTP + interno)
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

/**
 * ASCC - Cambiar Contraseña Admin
 * Ruta: admin/change-password.php
 * Descripción: Permite al administrador cambiar su contraseña de forma segura.
 *              Verifica la contraseña actual con bcrypt antes de guardar la nueva.
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
// PROCESO DEL FORMULARIO
// =============================================================================
$feedback = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $actual    = $_POST['password_actual']    ?? '';
    $nueva     = $_POST['password_nueva']     ?? '';
    $confirmar = $_POST['password_confirmar'] ?? '';

    // ── Validaciones
    if (empty($actual) || empty($nueva) || empty($confirmar)) {
        $feedback = ['type' => 'error', 'msg' => $lang['cp_error_empty']];
    } elseif (strlen($nueva) < 8) {
        $feedback = ['type' => 'error', 'msg' => $lang['cp_error_min_length']];
    } elseif ($nueva !== $confirmar) {
        $feedback = ['type' => 'error', 'msg' => $lang['cp_error_mismatch']];
    } else {
        // ── Obtener hash actual de la BD
        $stmt = $conexion->prepare(
            "SELECT password FROM usuarios WHERE id_usuario = :id"
        );
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $row = $stmt->fetch();

        if (!$row) {
            $feedback = ['type' => 'error', 'msg' => $lang['cp_error_user_not_found']];
        } elseif (!password_verify($actual, $row['password'])) {
            // Contraseña actual incorrecta
            $feedback = ['type' => 'error', 'msg' => $lang['cp_error_wrong_current']];
        } else {
            // ── Todo correcto: hashear y guardar la nueva contraseña
            $nuevo_hash = password_hash($nueva, PASSWORD_BCRYPT, ['cost' => 10]);

            $update = $conexion->prepare(
                "UPDATE usuarios SET password = :hash WHERE id_usuario = :id"
            );
            $update->execute([':hash' => $nuevo_hash, ':id' => $_SESSION['user_id']]);

            $feedback = ['type' => 'success', 'msg' => $lang['cp_success']];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['cp_page_title'] ?> — ASCC Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/change-password.css">
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
            <a href="dashboard.php" class="ag-sidebar__link">
                <i class="fas fa-chart-line"></i><span><?= $lang['nav_dashboard'] ?></span>
            </a>
            <a href="users.php" class="ag-sidebar__link">
                <i class="fas fa-users"></i><span><?= $lang['nav_users'] ?></span>
            </a>
            <a href="products.php" class="ag-sidebar__link">
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
            <a href="change-password.php" class="ag-sidebar__link ag-sidebar__link--active">
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
                    <span class="ag-topbar__breadcrumb--current"><?= $lang['cp_page_title'] ?></span>
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

            <!-- CABECERA -->
            <div class="ag-page-header">
                <div>
                    <h1 class="ag-page-header__title"><?= $lang['cp_page_title'] ?></h1>
                    <p class="ag-page-header__subtitle"><?= $lang['cp_page_subtitle'] ?></p>
                </div>
            </div>

            <!-- CARD DEL FORMULARIO -->
            <div class="cp-card">

                <!-- Ícono decorativo -->
                <div class="cp-card__icon">
                    <i class="fas fa-shield-halved"></i>
                </div>

                <!-- FEEDBACK -->
                <?php if ($feedback['type']): ?>
                    <div class="au-feedback au-feedback--<?= $feedback['type'] ?>">
                        <i
                            class="fas <?= $feedback['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                        <?= htmlspecialchars($feedback['msg']) ?>
                    </div>
                <?php endif; ?>

                <!-- FORMULARIO -->
                <form class="cp-form" method="POST" action="change-password.php" autocomplete="off">

                    <!-- Contraseña actual -->
                    <div class="cp-field">
                        <label class="cp-field__label" for="password_actual">
                            <i class="fas fa-lock"></i>
                            <?= $lang['cp_label_current'] ?>
                        </label>
                        <div class="cp-field__input-wrapper">
                            <input type="password" id="password_actual" name="password_actual" class="cp-field__input"
                                placeholder="<?= $lang['cp_placeholder_current'] ?>" required
                                autocomplete="current-password">
                            <button type="button" class="cp-field__toggle"
                                onclick="togglePassword('password_actual', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Nueva contraseña -->
                    <div class="cp-field">
                        <label class="cp-field__label" for="password_nueva">
                            <i class="fas fa-key"></i>
                            <?= $lang['cp_label_new'] ?>
                        </label>
                        <div class="cp-field__input-wrapper">
                            <input type="password" id="password_nueva" name="password_nueva" class="cp-field__input"
                                placeholder="<?= $lang['cp_placeholder_new'] ?>" required autocomplete="new-password"
                                oninput="checkStrength(this.value)">
                            <button type="button" class="cp-field__toggle"
                                onclick="togglePassword('password_nueva', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <!-- Barra de fortaleza -->
                        <div class="cp-strength" id="strengthBar">
                            <div class="cp-strength__track">
                                <div class="cp-strength__fill" id="strengthFill"></div>
                            </div>
                            <span class="cp-strength__label" id="strengthLabel"></span>
                        </div>
                        <p class="cp-field__hint"><?= $lang['cp_hint_min'] ?></p>
                    </div>

                    <!-- Confirmar nueva contraseña -->
                    <div class="cp-field">
                        <label class="cp-field__label" for="password_confirmar">
                            <i class="fas fa-check-double"></i>
                            <?= $lang['cp_label_confirm'] ?>
                        </label>
                        <div class="cp-field__input-wrapper">
                            <input type="password" id="password_confirmar" name="password_confirmar"
                                class="cp-field__input" placeholder="<?= $lang['cp_placeholder_confirm'] ?>" required
                                autocomplete="new-password" oninput="checkMatch()">
                            <button type="button" class="cp-field__toggle"
                                onclick="togglePassword('password_confirmar', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="cp-field__match" id="matchMsg"></p>
                    </div>

                    <!-- Botón submit -->
                    <button type="submit" class="cp-submit">
                        <i class="fas fa-floppy-disk"></i>
                        <?= $lang['cp_btn_save'] ?>
                    </button>

                </form>
            </div>

        </div><!-- /.ag-dashboard-body -->
    </main>

    <script src="assets/js/admin-dashboard.js"></script>
    <script>
        // Claves de idioma para JS
        const CP_LANG = {
            weak: '<?= addslashes($lang["cp_strength_weak"]) ?>',
            fair: '<?= addslashes($lang["cp_strength_fair"]) ?>',
            good: '<?= addslashes($lang["cp_strength_good"]) ?>',
            strong: '<?= addslashes($lang["cp_strength_strong"]) ?>',
            match: '<?= addslashes($lang["cp_match_ok"]) ?>',
            noMatch: '<?= addslashes($lang["cp_match_fail"]) ?>',
        };

        // ── Mostrar / ocultar contraseña
        function togglePassword(fieldId, btn) {
            const input = document.getElementById(fieldId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // ── Barra de fortaleza de contraseña
        function checkStrength(value) {
            const bar = document.getElementById('strengthBar');
            const fill = document.getElementById('strengthFill');
            const label = document.getElementById('strengthLabel');

            if (!value) {
                bar.style.display = 'none';
                return;
            }

            bar.style.display = 'flex';

            let score = 0;
            if (value.length >= 8) score++;
            if (value.length >= 12) score++;
            if (/[A-Z]/.test(value)) score++;
            if (/[0-9]/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value)) score++;

            const levels = [{
                    pct: '20%',
                    color: '#ef4444',
                    text: CP_LANG.weak
                },
                {
                    pct: '40%',
                    color: '#f97316',
                    text: CP_LANG.weak
                },
                {
                    pct: '60%',
                    color: '#eab308',
                    text: CP_LANG.fair
                },
                {
                    pct: '80%',
                    color: '#22c55e',
                    text: CP_LANG.good
                },
                {
                    pct: '100%',
                    color: '#06654a',
                    text: CP_LANG.strong
                },
            ];

            const lvl = levels[Math.min(score, 4)];
            fill.style.width = lvl.pct;
            fill.style.backgroundColor = lvl.color;
            label.textContent = lvl.text;
            label.style.color = lvl.color;
        }

        // ── Verificar que las contraseñas coinciden
        function checkMatch() {
            const nueva = document.getElementById('password_nueva').value;
            const confirmar = document.getElementById('password_confirmar').value;
            const msg = document.getElementById('matchMsg');

            if (!confirmar) {
                msg.textContent = '';
                return;
            }

            if (nueva === confirmar) {
                msg.textContent = '✓ ' + CP_LANG.match;
                msg.style.color = '#059669';
            } else {
                msg.textContent = '✗ ' + CP_LANG.noMatch;
                msg.style.color = '#ef4444';
            }
        }
    </script>

</body>

</html>