<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC — Verificación de Cuenta (Registro)
 * Ruta: views/auth/verificar_registro.php
 *
 * Muestra el formulario para ingresar el token enviado por email.
 * Requiere $_SESSION['pending_registro'] activo.
 * ═══════════════════════════════════════════════════════════
 */

session_start();

/* Si no hay registro pendiente, redirigir al formulario */
if (!isset($_SESSION['pending_registro'])) {
    header('Location: /ascc/views/auth/registro.php?error=sesion_expirada');
    exit;
}

$pending  = $_SESSION['pending_registro'];
$email    = $pending['email'] ?? '';
$nombre   = $pending['nombre'] ?? '';
$expiry   = $pending['expiry'] ?? (time() + 300);
$intentos = $pending['intentos'] ?? 0;

/* Enmascarar email: j****@gmail.com */
$partes   = explode('@', $email);
$usuario  = $partes[0] ?? '';
$dominio  = $partes[1] ?? '';
$mask_len = max(1, strlen($usuario) - 2);
$email_masked = substr($usuario, 0, 1)
    . str_repeat('*', $mask_len)
    . (strlen($usuario) > 1 ? substr($usuario, -1) : '')
    . '@' . $dominio;

/* Mensajes de error */
$error_msg = '';
$error_param = $_GET['error'] ?? '';
if ($error_param === 'token_invalido') {
    $restantes = (int)($_GET['restantes'] ?? (3 - $intentos));
    $error_msg = "Código incorrecto. Te quedan <strong>{$restantes}</strong> intento(s).";
} elseif ($error_param === 'error_servidor') {
    $error_msg = 'Error al crear tu cuenta. Por favor intenta de nuevo.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Cuenta — ASCC</title>
    <link rel="icon" type="image/png" href="/ascc/public/img/logo.png">
    <link rel="stylesheet" href="/ascc/public/css/verificar-registro.css">
</head>
<body>

    <div class="verify-card">

        <!-- ── HEADER ── -->
        <div class="vc-header">
            <div class="vc-logo-wrap">
                <img src="/ascc/public/img/logo.png" alt="ASCC" class="vc-logo-img" onerror="this.style.display='none'">
            </div>
            <div class="vc-brand">Aromas y Sabores de mi Campo Colombiano</div>
            <div class="vc-title">Verifica tu cuenta</div>
            <div class="vc-sub">Ya casi terminas, solo un paso más</div>
        </div>

        <!-- ── CUERPO ── -->
        <div class="vc-body">

            <!-- Email al que se envió el código -->
            <div class="email-info">
                <div class="email-text">
                    Enviamos un código de 8 caracteres a<br>
                    <strong><?= htmlspecialchars($email_masked) ?></strong><br>
                    <span class="email-hint">Revisa también tu carpeta de spam.</span>
                </div>
            </div>

            <!-- Error -->
            <?php if ($error_msg): ?>
            <div class="alert-error">
                <?= $error_msg ?>
            </div>
            <?php endif; ?>

            <!-- Formulario de verificación -->
            <form id="formVerificar" action="/ascc/controllers/verificar_token_registro.php" method="POST">

                <label class="field-label" for="tokenInput">
                    Ingresa el código de verificación
                </label>

                <div class="token-input-wrap">
                    <input
                        type="text"
                        id="tokenInput"
                        name="token"
                        class="token-input"
                        maxlength="8"
                        inputmode="text"
                        autocomplete="one-time-code"
                        placeholder="aBcD3fGh"
                        autocapitalize="off"
                        autocorrect="off"
                        spellcheck="false"
                        autofocus
                        required
                    >
                </div>
                <span class="field-hint">
                    8 caracteres · distingue mayúsculas y minúsculas
                </span>

                <!-- Contador regresivo -->
                <div class="countdown-wrap">
                    <div class="countdown-label">El código expira en:</div>
                    <div class="countdown-timer" id="countdownTimer">05:00</div>
                    <div class="countdown-expired" id="countdownExpired">
                        El código expiró. Solicita uno nuevo.
                    </div>
                </div>

                <!-- Timestamp de expiración para el JS -->
                <input type="hidden" id="expiryTs" value="<?= (int)$expiry ?>">

                <!-- Botón verificar -->
                <button type="submit" class="btn-verify" id="btnVerify">
                    Verificar mi cuenta
                </button>

            </form>

            <!-- Reenviar código -->
            <button type="button" class="btn-resend" id="btnResend">
                <span class="resend-text">Reenviar código</span>
                <span class="resend-loading">Enviando...</span>
            </button>

            <!-- Volver al registro -->
            <a href="/ascc/views/auth/registro.php" class="back-link">
                ← Volver al formulario de registro
            </a>

        </div><!-- /.vc-body -->

    </div><!-- /.verify-card -->

    <p class="vc-footer">© 2025 ASCC · Marketplace Agropecuario de Colombia</p>

    <script src="/ascc/public/js/verificar-registro.js"></script>

</body>
</html>
