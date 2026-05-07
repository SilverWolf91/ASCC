<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC — Controlador: Verificar Token de Registro
 * Ruta: controllers/verificar_token_registro.php
 *
 * Recibe POST desde views/auth/verificar_registro.php
 * Valida el token OTP y, si es correcto, crea el usuario en BD.
 * ═══════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ascc/views/auth/verificar_registro.php');
    exit;
}

/* ── Verificar que exista registro pendiente ─────────────── */
if (!isset($_SESSION['pending_registro'])) {
    header('Location: /ascc/views/auth/registro.php?error=sesion_expirada');
    exit;
}

$pending = $_SESSION['pending_registro'];

/* ── Verificar expiración ────────────────────────────────── */
if (time() > ($pending['expiry'] ?? 0)) {
    unset($_SESSION['pending_registro']);
    header('Location: /ascc/views/auth/registro.php?error=token_expirado');
    exit;
}

/* ── Verificar intentos máximos (3) ─────────────────────── */
if (($pending['intentos'] ?? 0) >= 3) {
    unset($_SESSION['pending_registro']);
    header('Location: /ascc/views/auth/registro.php?error=demasiados_intentos');
    exit;
}

/* ── Validar token enviado por el usuario ────────────────── */
$token_ingresado = trim($_POST['token'] ?? '');

if (!hash_equals($pending['token'], $token_ingresado)) {
    $_SESSION['pending_registro']['intentos'] = ($pending['intentos'] ?? 0) + 1;
    $intentos_restantes = 3 - $_SESSION['pending_registro']['intentos'];
    header('Location: /ascc/views/auth/verificar_registro.php?error=token_invalido&restantes=' . $intentos_restantes);
    exit;
}

/* ── Token correcto: crear cuenta en BD ──────────────────── */
try {
    $sql = "INSERT INTO usuarios (nombre, email, password, telefono, cedula, rol)
            VALUES (:nombre, :email, :password, :telefono, :cedula, :rol)";

    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':nombre',   $pending['nombre']);
    $stmt->bindParam(':email',    $pending['email']);
    $stmt->bindParam(':password', $pending['password']);
    $stmt->bindParam(':telefono', $pending['telefono']);
    $stmt->bindParam(':cedula',   $pending['cedula']);
    $stmt->bindParam(':rol',      $pending['rol']);
    $stmt->execute();

    $id_usuario = $conexion->lastInsertId();

    /* Limpiar datos pendientes */
    unset($_SESSION['pending_registro']);

    /* Enviar email de bienvenida */
    $email_bienvenida = enviarEmailBienvenida($pending['email'], $pending['nombre']);

    /* Iniciar sesión */
    $_SESSION['id_usuario'] = $id_usuario;
    $_SESSION['nombre']     = $pending['nombre'];
    $_SESSION['rol']        = $pending['rol'];

    $param = $email_bienvenida ? '&email_enviado=1' : '&email_enviado=0';
    header('Location: /ascc/dashboard.php?registro_exitoso=1' . $param);
    exit;

} catch (PDOException $e) {
    error_log('[ASCC] Error al crear usuario tras verificación: ' . $e->getMessage());
    header('Location: /ascc/views/auth/verificar_registro.php?error=error_servidor');
    exit;
}
