<?php
/**
 * ASCC - Admin Logout
 * Ruta: admin/logout.php
 * DescripciÃ³n: Cierra la sesiÃ³n del administrador de forma segura
 */

session_start();

// Verificar que era realmente admin antes de destruir
$was_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Destruir toda la sesiÃ³n
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Redirigir siempre al login de admin (nunca al pÃºblico)
header('Location: login.php?logout=1');
exit;