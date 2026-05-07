<?php

/**
 * Cerrar sesión
 */

session_start();
session_destroy();

header("Location: /ascc/views/auth/login.php");
exit;