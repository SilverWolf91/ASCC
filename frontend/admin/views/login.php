<?php

/**
 * ASCC - Admin Login
 * Ruta: admin/login.php
 * Descripción: Acceso exclusivo al panel de administración.
 *              Conectado a la BD real: ascc / tabla: usuarios
 */

session_start();

// Si ya hay sesión admin activa, redirigir directo al dashboard
if (
    isset($_SESSION['role'], $_SESSION['admin_token']) &&
    $_SESSION['role'] === 'admin' &&
    $_SESSION['admin_token'] === hash('sha256', $_SESSION['user_id'] . 'ASCC_ADMIN_SECRET')
) {
    header('Location: dashboard.php');
    exit;
}

// =============================================================================
// CONFIGURACIÓN DE SEGURIDAD
// =============================================================================
define('ADMIN_SECRET', 'ASCC_ADMIN_SECRET');
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// =============================================================================
// INTERNACIONALIZACIÓN
// =============================================================================
$lang_code = $_COOKIE['ag_lang'] ?? 'es';
$lang_file = __DIR__ . "/../../backend/admin/lang/{$lang_code}.php";
if (!file_exists($lang_file)) {
    $lang_code = 'es';
    $lang_file = __DIR__ . '/../../backend/admin/lang/es.php';
}
$lang = require $lang_file;

// =============================================================================
// TEMA
// =============================================================================
$theme = $_COOKIE['ag_theme'] ?? 'light';
$theme = in_array($theme, ['light', 'dark']) ? $theme : 'light';

// =============================================================================
// CONTROL DE INTENTOS FALLIDOS
// =============================================================================
$error        = '';
$is_locked    = false;
$attempts_key = 'admin_login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$lockout_key  = 'admin_lockout_'        . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] > time()) {
    $is_locked      = true;
    $remaining_time = ceil(($_SESSION[$lockout_key] - time()) / 60);
    $error          = sprintf($lang['login_locked'], $remaining_time);
}

// =============================================================================
// PROCESAR LOGIN — Conectado a BD real
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {

    // Verificar CSRF
    if (
        !isset($_POST['csrf_token'], $_SESSION['admin_csrf']) ||
        !hash_equals($_SESSION['admin_csrf'], $_POST['csrf_token'])
    ) {
        $error = $lang['login_invalid_csrf'];
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = $lang['login_fields_required'];
        } else {

            // ----------------------------------------------------------------
            // CONEXIÓN REAL — config/database.php → variable $conexion (PDO)
            // Tabla: usuarios
            // Columnas usadas: id_usuario, nombre, email, password, rol
            // ----------------------------------------------------------------
            require_once __DIR__ . '/../../../backend/users/config/database.php';

            $stmt = $conexion->prepare(
                "SELECT id_usuario, nombre, email, password, rol
                 FROM   usuarios
                 WHERE  email = :email
                   AND  rol   = 'admin'
                 LIMIT  1"
            );
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch(); // PDO::FETCH_ASSOC por defecto (config de database.php)

            // ----------------------------------------------------------------
            // VERIFICAR CONTRASEÑA contra el hash almacenado en la BD
            // ----------------------------------------------------------------
            if ($admin && password_verify($password, $admin['password'])) {

                // ✅ LOGIN EXITOSO
                session_regenerate_id(true); // Prevenir session fixation

                $_SESSION['user_id']     = $admin['id_usuario'];  // Columna real de la BD
                $_SESSION['username']    = $admin['nombre'];       // Columna real de la BD
                $_SESSION['role']        = 'admin';
                $_SESSION['admin_token'] = hash('sha256', $admin['id_usuario'] . ADMIN_SECRET);
                $_SESSION['login_time']  = time();

                // Limpiar intentos fallidos
                unset($_SESSION[$attempts_key], $_SESSION[$lockout_key]);

                header('Location: dashboard.php');
                exit;
            } else {

                // ❌ LOGIN FALLIDO
                $_SESSION[$attempts_key] = ($_SESSION[$attempts_key] ?? 0) + 1;
                $remaining = MAX_ATTEMPTS - $_SESSION[$attempts_key];

                if ($_SESSION[$attempts_key] >= MAX_ATTEMPTS) {
                    $_SESSION[$lockout_key] = time() + LOCKOUT_TIME;
                    $is_locked = true;
                    $error     = sprintf($lang['login_locked'], ceil(LOCKOUT_TIME / 60));
                } else {
                    $error = sprintf($lang['login_invalid_credentials'], $remaining);
                }
            }
        }
    }
}

// Generar CSRF token para el formulario
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" data-theme="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['login_page_title'] ?> — ASCC Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-login.css">
</head>

<body>

    <div class="ag-login-wrapper">

        <!-- Formulario centrado — pantalla completa -->
        <div class="ag-login-form-panel">

            <button class="ag-login-theme-toggle" id="themeToggle">
                <i class="fas <?= $theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>" id="themeIcon"></i>
            </button>

            <div class="ag-login-form-wrapper">

                <div class="ag-login-header">
                    <div class="ag-login-header__icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h1><?= $lang['login_title'] ?></h1>
                    <p><?= $lang['login_subtitle'] ?></p>
                </div>

                <?php if ($error): ?>
                <div class="ag-alert ag-alert--error" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!$is_locked): ?>
                <form class="ag-login-form" method="POST" action="login.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_csrf'] ?>">

                    <div class="ag-form-group">
                        <label for="email" class="ag-form-label">
                            <i class="fas fa-envelope"></i>
                            <?= $lang['login_email_label'] ?>
                        </label>
                        <input type="email" id="email" name="email" class="ag-form-input"
                            placeholder="<?= $lang['login_email_placeholder'] ?>"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
                    </div>

                    <div class="ag-form-group">
                        <label for="password" class="ag-form-label">
                            <i class="fas fa-lock"></i>
                            <?= $lang['login_password_label'] ?>
                        </label>
                        <div class="ag-form-input-wrapper">
                            <input type="password" id="password" name="password" class="ag-form-input"
                                placeholder="<?= $lang['login_password_placeholder'] ?>" required
                                autocomplete="current-password">
                            <button type="button" class="ag-form-password-toggle" id="togglePassword"
                                aria-label="Mostrar/ocultar contraseña">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="ag-login-submit">
                        <i class="fas fa-sign-in-alt"></i>
                        <?= $lang['login_submit_btn'] ?>
                    </button>

                </form>
                <?php endif; ?>

                <div class="ag-login-footer">
                    <p><?= $lang['login_back_to_site'] ?> <a href="../index.php"><?= $lang['login_back_link'] ?></a></p>
                </div>

            </div>
        </div>
    </div>

    <script>
    document.getElementById('themeToggle')?.addEventListener('click', () => {
        const html = document.documentElement;
        const icon = document.getElementById('themeIcon');
        const theme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', theme);
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        document.cookie = `ag_theme=${theme};path=/;max-age=31536000;SameSite=Lax`;
    });

    document.getElementById('togglePassword')?.addEventListener('click', () => {
        const input = document.getElementById('password');
        const icon = document.getElementById('togglePasswordIcon');
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
    </script>

</body>

</html>