<?php

/**
 * Cerrar sesión
 */

session_start();
session_destroy();

header("Location: /ascc/frontend/users/views/auth/login.php");
exit;