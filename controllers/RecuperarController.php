<?php
// Búfer de salida para prevenir cualquier error de 'headers already sent'
ob_start();
session_start();
require_once __DIR__ . "/../config/database.php";

/**
 * Controlador de Recuperación de Contraseña
 * Maneja solicitudes de recuperación y restablecimiento de contraseña
 */

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'solicitar_recuperacion':
        solicitarRecuperacion($conexion);
        break;

    case 'restablecer':
        restablecerContrasena($conexion);
        break;

    default:
        header("Location: /ascc/views/auth/recuperar.php");
        exit;
}

/**
 * Envía un enlace de recuperación al email del usuario
 */
function solicitarRecuperacion($conexion)
{
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        header("Location: /ascc/views/auth/recuperar.php?error=email_vacio");
        exit;
    }

    // Verificar que el email existe
    $stmt = $conexion->prepare("SELECT id_usuario, nombre, email FROM usuarios WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        // Por seguridad, no revelamos si el email existe o no
        // Pero mostramos un mensaje genérico de éxito
        header("Location: /ascc/views/auth/recuperar.php?success=1");
        exit;
    }

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Generar token único
    $token = bin2hex(random_bytes(32));
    $expiracion = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Verificar si la tabla password_resets existe
    try {
        $stmt = $conexion->query("SHOW TABLES LIKE 'password_resets'");
        if ($stmt->rowCount() === 0) {
            error_log("ERROR CRÍTICO: La tabla password_resets no existe. Por favor ejecuta el script SQL.");
            header("Location: /ascc/views/auth/recuperar.php?error=tabla_no_existe");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error al verificar tabla: " . $e->getMessage());
        header("Location: /ascc/views/auth/recuperar.php?error=sistema");
        exit;
    }

    // Guardar token en base de datos
    try {
        // Primero eliminar tokens antiguos del mismo email
        $stmt = $conexion->prepare("DELETE FROM password_resets WHERE usuario_email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        // Insertar nuevo token
        $stmt = $conexion->prepare("
            INSERT INTO password_resets (usuario_email, token, expiracion) 
            VALUES (:email, :token, :expiracion)
        ");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expiracion', $expiracion);
        $stmt->execute();

        error_log("Token generado exitosamente para: $email");
    } catch (PDOException $e) {
        error_log("Error al guardar token: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        error_log("Email intentado: $email");
        header("Location: /ascc/views/auth/recuperar.php?error=bd_error");
        exit;
    }

    // Cargar configuración de email con PHPMailer
    require_once __DIR__ . "/../config/email_config.php";

    // Enviar email usando PHPMailer
    $emailEnviado = enviarEmailRecuperacion($email, $usuario['nombre'], $token);

    if ($emailEnviado === true) {
        error_log("✅ Email de recuperación enviado exitosamente a: $email");
        header("Location: /ascc/views/auth/recuperar.php?success=1");
        exit;
    } else {
        error_log("❌ Error al enviar email a: $email - " . $emailEnviado);
        $errorUrl = urlencode(substr($emailEnviado, 0, 150));
        header("Location: /ascc/views/auth/recuperar.php?error=envio_fallido&detalle=" . $errorUrl);
        exit;
    }
}

/**
 * Restablece la contraseña del usuario usando el token
 */
function restablecerContrasena($conexion)
{
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validaciones
    if (empty($token) || empty($password) || empty($password2)) {
        header("Location: /ascc/views/auth/restablecer.php?token=$token&error=campos_vacios");
        exit;
    }

    if ($password !== $password2) {
        header("Location: /ascc/views/auth/restablecer.php?token=$token&error=passwords_no_coinciden");
        exit;
    }

    if (strlen($password) < 6) {
        header("Location: /ascc/views/auth/restablecer.php?token=$token&error=password_corta");
        exit;
    }

    // Verificar token
    $stmt = $conexion->prepare("
        SELECT usuario_email, expiracion 
        FROM password_resets 
        WHERE token = :token
    ");
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        header("Location: /ascc/views/auth/recuperar.php?error=token_invalido");
        exit;
    }

    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar que no haya expirado
    if (strtotime($reset['expiracion']) < time()) {
        header("Location: /ascc/views/auth/recuperar.php?error=token_expirado");
        exit;
    }

    // Actualizar contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $conexion->beginTransaction();

        // Actualizar contraseña del usuario
        $stmt = $conexion->prepare("UPDATE usuarios SET password = :password WHERE email = :email");
        $stmt->bindParam(':password', $passwordHash);
        $stmt->bindParam(':email', $reset['usuario_email']);
        $stmt->execute();

        // Eliminar el token usado
        $stmt = $conexion->prepare("DELETE FROM password_resets WHERE token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        $conexion->commit();

        // Redirigir al login con mensaje de éxito
        header("Location: /ascc/views/auth/login.php?password_changed=1");
        exit;
    } catch (PDOException $e) {
        $conexion->rollBack();
        error_log("Error al restablecer contraseña: " . $e->getMessage());
        header("Location: /ascc/views/auth/restablecer.php?token=$token&error=sistema");
        exit;
    }
}
