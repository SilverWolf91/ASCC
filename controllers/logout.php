<?php

/**
 * Cerrar sesión
 */

session_start();

// Borrar variables y cookie de sesión
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

// Reenviar el parámetro de error (p.ej. sesion_expirada) al login
$qs = '';
if (!empty($_GET['error'])) {
    $qs = '?error=' . urlencode($_GET['error']);
}

header('Location: /ascc/views/auth/login.php' . $qs);
exit;