<?php
require_once __DIR__ . "/../../../../backend/users/config/database.php";

$token = $_GET['token'] ?? '';

// Verificar que el token existe y no ha expirado
$tokenValido = false;
$tokenExpirado = false;

if (!empty($token)) {
    $stmt = $conexion->prepare("
        SELECT usuario_email, expiracion 
        FROM password_resets 
        WHERE token = :token
    ");
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (strtotime($reset['expiracion']) >= time()) {
            $tokenValido = true;
        } else {
            $tokenExpirado = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - ASCC</title>

    <link rel="icon" type="image/png" href="/ascc/frontend/users/public/img/logo.png">
    <link rel="stylesheet" href="/ascc/frontend/users/public/css/ascc-theme-CORRECTO.css">
    <link rel="stylesheet" href="/ascc/frontend/users/public/css/restablecer.css">
</head>

<body>

    <div class="reset-container">

        <!-- LOGO GRANDE Y DESTACADO -->
        <div class="logo-section">
            <div class="logo-wrapper">
                <img src="/ascc/frontend/users/public/img/logo.png" alt="ASCC Logo">
            </div>
            <h1>ASCC</h1>
            <p>Marketplace Agropecuario</p>
        </div>

        <?php if (!$tokenValido): ?>
        <!-- TOKEN INVÁLIDO O EXPIRADO -->
        <div class="error-container">
            <div class="error-icon"><?= $tokenExpirado ? '⏰' : '❌' ?></div>
            <h2 class="error-title">
                <?= $tokenExpirado ? 'Enlace Expirado' : 'Enlace Inválido' ?>
            </h2>
            <p class="error-message">
                <?php if ($tokenExpirado): ?>
                Este enlace de recuperación ha expirado. Los enlaces son válidos por 24 horas.<br>
                Por favor, solicita un nuevo enlace de recuperación.
                <?php else: ?>
                Este enlace de recuperación no es válido o ya fue utilizado.<br>
                Por favor, verifica que hayas copiado el enlace completo del correo.
                <?php endif; ?>
            </p>
            <a href="/ascc/frontend/users/views/auth/recuperar.php" class="btn-link">
                Solicitar Nuevo Enlace
            </a>
        </div>

        <?php else: ?>
        <!-- FORMULARIO DE RESTABLECIMIENTO -->
        <h2 class="reset-title">Nueva Contraseña</h2>
        <p class="reset-subtitle">
            Ingresa tu nueva contraseña. Debe tener al menos 6 caracteres.
        </p>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <?php
                    if ($_GET['error'] === 'passwords_no_coinciden') {
                        echo "❌ Las contraseñas no coinciden";
                    } elseif ($_GET['error'] === 'password_corta') {
                        echo "❌ La contraseña debe tener al menos 6 caracteres";
                    } elseif ($_GET['error'] === 'campos_vacios') {
                        echo "❌ Todos los campos son obligatorios";
                    } else {
                        echo "❌ Error al restablecer la contraseña. Intenta nuevamente";
                    }
                    ?>
        </div>
        <?php endif; ?>

        <form action="/ascc/backend/users/controllers/RecuperarController.php" method="POST" onsubmit="return validateForm()">
            <input type="hidden" name="accion" value="restablecer">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label for="pass1">🔒 Nueva Contraseña</label>
                <input type="password" name="password" id="pass1" placeholder="••••••••" required
                    onkeyup="checkPasswordStrength()" autofocus>
                <div id="strength-indicator" class="password-strength"></div>
                <div id="strength-text" class="strength-text"></div>
            </div>

            <div class="form-group">
                <label for="pass2">🔒 Confirmar Nueva Contraseña</label>
                <input type="password" name="password2" id="pass2" placeholder="••••••••" required>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="show-pass" onclick="togglePasswords()">
                <label for="show-pass">Mostrar contraseñas</label>
            </div>

            <button type="submit" class="btn-primary">
                ✅ Restablecer Contraseña
            </button>
        </form>

        <div class="alert alert-warning" style="margin-top: 20px;">
            <strong>💡 Consejo de Seguridad:</strong><br>
            Usa una contraseña segura que incluya letras, números y caracteres especiales.
            No compartas tu contraseña con nadie.
        </div>
        <?php endif; ?>

    </div>

    <script src="/ascc/frontend/users/public/js/restablecer.js"></script>

</body>

</html>