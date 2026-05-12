<?php

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * ASCC — Controlador: Verificar Token de Registro
 * Ruta: controllers/verificar_token_registro.php
 *
 * Recibe POST desde views/auth/verificar_registro.php
 * Valida el token OTP y, si es correcto, crea el usuario en BD.
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ascc/views/auth/verificar_registro.php');
    exit;
}

/* â”€â”€ Verificar que exista registro pendiente â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if (!isset($_SESSION['pending_registro'])) {
    header('Location: /ascc/views/auth/registro.php?error=sesion_expirada');
    exit;
}

$pending = $_SESSION['pending_registro'];

/* â”€â”€ Verificar expiración â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if (time() > ($pending['expiry'] ?? 0)) {
    unset($_SESSION['pending_registro']);
    header('Location: /ascc/views/auth/registro.php?error=token_expirado');
    exit;
}

/* â”€â”€ Verificar intentos máximos (3) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if (($pending['intentos'] ?? 0) >= 3) {
    unset($_SESSION['pending_registro']);
    header('Location: /ascc/views/auth/registro.php?error=demasiados_intentos');
    exit;
}

/* â”€â”€ Validar token enviado por el usuario â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$token_ingresado = trim($_POST['token'] ?? '');

if (!hash_equals($pending['token'], $token_ingresado)) {
    $_SESSION['pending_registro']['intentos'] = ($pending['intentos'] ?? 0) + 1;
    $intentos_restantes = 3 - $_SESSION['pending_registro']['intentos'];
    header('Location: /ascc/views/auth/verificar_registro.php?error=token_invalido&restantes=' . $intentos_restantes);
    exit;
}

/* â”€â”€ Token correcto: crear cuenta en BD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

    /* ── Registro de Políticas de Privacidad (Legal) ─────────────── */
    if (!empty($pending['acepta_politicas'])) {
        // Crear tabla si no existe
        $conexion->exec("
            CREATE TABLE IF NOT EXISTS aceptacion_politicas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                email VARCHAR(255) NOT NULL,
                fecha_aceptacion DATETIME NOT NULL,
                version_politica VARCHAR(50) NOT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $fecha = date('Y-m-d H:i:s');
        $version = $pending['version_politica'] ?? '1.0';

        $stmt_pol = $conexion->prepare("
            INSERT INTO aceptacion_politicas (id_usuario, email, fecha_aceptacion, version_politica, ip_address)
            VALUES (:id_usuario, :email, :fecha, :version, :ip)
        ");
        $stmt_pol->bindParam(':id_usuario', $id_usuario);
        $stmt_pol->bindParam(':email', $pending['email']);
        $stmt_pol->bindParam(':fecha', $fecha);
        $stmt_pol->bindParam(':version', $version);
        $stmt_pol->bindParam(':ip', $ip_address);
        $stmt_pol->execute();
    }
    /* ────────────────────────────────────────────────────────────── */

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
