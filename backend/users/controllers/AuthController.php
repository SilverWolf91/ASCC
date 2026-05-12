<?php

/**
 * Controlador de Autenticación
 * Maneja registro y login de usuarios con confirmación por email
 *
 * ASCC — Modificación: login ahora verifica estado = 'activo'
 * Un usuario bloqueado desde el panel admin no puede iniciar sesión.
 */

session_start();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/email_config.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? '';

    // ============================================
    // REGISTRO DE USUARIO
    // ============================================
    if ($accion === "registro") {

        $nombre      = trim($_POST["nombre"]);
        $email       = trim($_POST["email"]);
        $telefono    = trim($_POST["telefono"]);
        $cedula      = trim($_POST["cedula"]);
        $password    = $_POST["password"];
        $password2   = $_POST["password2"];
        $codigo_pais = $_POST["codigo_pais"] ?? 'CO';

        // Validar y sanitizar rol recibido del formulario
        $roles_validos = ['vendedor', 'comprador', 'mixto'];
        $rol = trim($_POST["rol"] ?? '');
        if (!in_array($rol, $roles_validos)) {
            header("Location: /ascc/frontend/users/views/auth/registro.php?error=rol_invalido");
            exit;
        }

        // Validar que las contraseñas coincidan
        if ($password !== $password2) {
            header("Location: /ascc/frontend/users/views/auth/registro.php?error=passwords_no_coinciden");
            exit;
        }

        // Validar políticas de privacidad
        $acepta_politicas = isset($_POST['acepta_politicas']) ? true : false;
        if (!$acepta_politicas) {
            header("Location: /ascc/frontend/users/views/auth/registro.php?error=politicas_requeridas");
            exit;
        }

        // Validar longitud de contraseña
        if (strlen($password) < 6) {
            header("Location: /ascc/frontend/users/views/auth/registro.php?error=password_corta");
            exit;
        }

        try {
            // Verificar si el email ya existe
            $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = :email");
            $stmt->bindParam(":email", $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                header("Location: /ascc/frontend/users/views/auth/registro.php?error=email_existe");
                exit;
            }

            // Verificar si la cédula ya existe
            $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE cedula = :cedula");
            $stmt->bindParam(":cedula", $cedula);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                header("Location: /ascc/frontend/users/views/auth/registro.php?error=cedula_existe");
                exit;
            }

            // Hashear contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Formatear teléfono con código de país
            $telefono_completo = $codigo_pais . '-' . $telefono;

            // Generar token de verificación (NO insertar en BD aún)
            $token = generarTokenRegistro();

            // Guardar datos pendientes en sesión
            $_SESSION['pending_registro'] = [
                'nombre'    => $nombre,
                'email'     => $email,
                'password'  => $password_hash,
                'telefono'  => $telefono_completo,
                'cedula'    => $cedula,
                'rol'       => $rol,
                'token'     => $token,
                'expiry'    => time() + 300,
                'intentos'  => 0,
                'last_sent' => time(),
                'acepta_politicas' => true,
                'version_politica' => '1.0'
            ];

            // Enviar email de verificación
            $email_enviado = enviarEmailVerificacionRegistro($email, $nombre, $token);

            if (!$email_enviado) {
                unset($_SESSION['pending_registro']);
                header("Location: /ascc/frontend/users/views/auth/registro.php?error=email_no_enviado");
                exit;
            }

            // Redirigir a pantalla de verificación
            header("Location: /ascc/frontend/users/views/auth/verificar_registro.php");
            exit;

        } catch (PDOException $e) {
            die("❌ Error en el registro: " . $e->getMessage());
        }
    }

    // ============================================
    // LOGIN DE USUARIO
    // ============================================
    if ($accion === "login") {

        $email    = trim($_POST["email"]);
        $password = $_POST["password"];

        try {
            // Buscar usuario por email — se trae también el campo estado
            $stmt = $conexion->prepare(
                "SELECT id_usuario, nombre, email, password, rol, estado
                 FROM   usuarios
                 WHERE  email = :email
                 LIMIT  1"
            );
            $stmt->bindParam(":email", $email);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                header("Location: /ascc/frontend/users/views/auth/login.php?error=credenciales_invalidas");
                exit;
            }

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar contraseña
            if (!password_verify($password, $usuario["password"])) {
                header("Location: /ascc/frontend/users/views/auth/login.php?error=credenciales_invalidas");
                exit;
            }

            // -------------------------------------------------------
            // VERIFICAR ESTADO — Si está bloqueado, no puede entrar
            // El admin bloquea desde admin/users.php
            // -------------------------------------------------------
            if ($usuario["estado"] === "bloqueado") {
                header("Location: /ascc/frontend/users/views/auth/login.php?error=cuenta_bloqueada");
                exit;
            }

            // Iniciar sesión
            $_SESSION["id_usuario"] = $usuario["id_usuario"];
            $_SESSION["nombre"]     = $usuario["nombre"];
            $_SESSION["rol"]        = $usuario["rol"];

            header("Location: /ascc/dashboard.php");
            exit;
        } catch (PDOException $e) {
            die("❌ Error al iniciar sesión: " . $e->getMessage());
        }
    }
}

// Si no es POST, redirigir al login
header("Location: /ascc/frontend/users/views/auth/login.php");
exit;
