<?php

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * ASCC â€” VerificaciÃ³n de Cuenta (Registro)
 * Ruta: views/auth/verificar_registro.php
 *
 * Muestra el formulario para ingresar el token enviado por email.
 * Requiere $_SESSION['pending_registro'] activo.
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
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
    $error_msg = "âŒ CÃ³digo incorrecto. Te quedan <strong>{$restantes}</strong> intento(s).";
} elseif ($error_param === 'error_servidor') {
    $error_msg = 'âŒ Error al crear tu cuenta. Por favor intenta de nuevo.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Cuenta â€” ASCC</title>
    <link rel="icon" type="image/png" href="/ascc/public/img/logo.png">
    <link rel="stylesheet" href="/ascc/public/css/verificar-registro.css">
</head>
<body>

    <div class="verify-card">

        <!-- â”€â”€ HEADER â”€â”€ -->
        <div class="vc-header">
            <span class="vc-logo">ðŸŒ¾</span>
            <div class="vc-brand">Aromas y Sabores de mi Campo Colombiano</div>
            <div class="vc-title">Verifica tu cuenta</div>
            <div class="vc-sub">Ya casi terminas â€” solo un paso mÃ¡s</div>
        </div>

        <!-- â”€â”€ CUERPO â”€â”€ -->
        <div class="vc-body">

            <!-- Email al que se enviÃ³ el cÃ³digo -->
            <div class="email-info">
                <span class="email-icon">ðŸ“¬</span>
                <div class="email-text">
                    Enviamos un cÃ³digo de 8 caracteres a<br>
                    <strong><?= htmlspecialchars($email_masked) ?></strong><br>
                    Revisa tambiÃ©n tu carpeta de spam.
                </div>
            </div>

            <!-- Error -->
            <?php if ($error_msg): ?>
            <div class="alert-error">
                <span>âš ï¸</span>
                <span><?= $error_msg ?></span>
            </div>
            <?php endif; ?>

            <!-- Formulario de verificaciÃ³n -->
            <form id="formVerificar" action="/ascc/controllers/verificar_token_registro.php" method="POST">

                <label class="field-label" for="tokenInput">
                    ðŸ” Ingresa el cÃ³digo de verificaciÃ³n
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
                        autofocus
                        required
                    >
                </div>
                <span class="field-hint">
                    8 caracteres Â· distingue mayÃºsculas y minÃºsculas
                </span>

                <!-- Contador regresivo -->
                <div class="countdown-wrap">
                    <div class="countdown-label">El cÃ³digo expira en:</div>
                    <div class="countdown-timer" id="countdownTimer">05:00</div>
                    <div class="countdown-expired" id="countdownExpired">
                        â± El cÃ³digo expirÃ³. Solicita uno nuevo.
                    </div>
                </div>

                <!-- Timestamp de expiraciÃ³n para el JS -->
                <input type="hidden" id="expiryTs" value="<?= (int)$expiry ?>">

                <!-- BotÃ³n verificar -->
                <button type="submit" class="btn-verify" id="btnVerify">
                    <span class="btn-icon">âœ…</span>
                    Verificar mi cuenta
                </button>

            </form>

            <!-- Reenviar cÃ³digo -->
            <button type="button" class="btn-resend" id="btnResend">
                <span class="resend-text">ðŸ“¨ Reenviar cÃ³digo</span>
                <span class="resend-loading">Enviando...</span>
            </button>

            <!-- Volver al registro -->
            <a href="/ascc/views/auth/registro.php" class="back-link">
                â† Volver al formulario de registro
            </a>

        </div><!-- /.vc-body -->

    </div><!-- /.verify-card -->

    <p class="vc-footer">Â© 2025 ASCC Â· Marketplace Agropecuario de Colombia ðŸ‡¨ðŸ‡´</p>

    <script src="/ascc/public/js/verificar-registro.js"></script>

</body>
</html>
