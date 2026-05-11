<?php

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * ASCC — API: Solicitar código OTP (2FA)
 * Ruta: api/solicitar_otp.php
 *
 * POST: csrf_token, tipo (email|password), email_nuevo (solo si tipo=email)
 * Genera un código de 6 dígitos, lo guarda en sesión y lo envía por email.
 * Expira en 5 minutos.
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */

ini_set('display_errors', '0');
ini_set('log_errors',     '1');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['success' => false, 'message' => 'Método no permitido.']));
}

ob_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* â”€â”€ Sesión â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => t('session_expired')]));
}

/* â”€â”€ CSRF â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$csrf_token = trim($_POST['csrf_token'] ?? '');
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => t('invalid_token')]));
}

/* â”€â”€ Datos del usuario actual â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$id_usuario = (int) $_SESSION['id_usuario'];
$stmt = $conexion->prepare('SELECT nombre, email FROM usuarios WHERE id_usuario = :id');
$stmt->bindParam(':id', $id_usuario, PDO::PARAM_INT);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    exit(json_encode(['success' => false, 'message' => t('session_expired')]));
}

$tipo = trim($_POST['tipo'] ?? '');

/* â”€â”€ Generar OTP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiry   = time() + 300; // 5 minutos

/* â”€â”€ Procesar según tipo â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if ($tipo === 'email') {

    $email_nuevo = trim($_POST['email_nuevo'] ?? '');

    if (!filter_var($email_nuevo, FILTER_VALIDATE_EMAIL)) {
        exit(json_encode(['success' => false, 'message' => t('validation_email')]));
    }

    if ($email_nuevo === $usuario['email']) {
        exit(json_encode(['success' => false, 'message' => t('otp_same_email')]));
    }

    /* Verificar que el nuevo email no esté tomado */
    $stmt2 = $conexion->prepare(
        'SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :id'
    );
    $stmt2->bindParam(':email', $email_nuevo);
    $stmt2->bindParam(':id',    $id_usuario, PDO::PARAM_INT);
    $stmt2->execute();
    if ($stmt2->fetch()) {
        exit(json_encode(['success' => false, 'message' => 'Este correo ya está registrado por otro usuario.']));
    }

    $_SESSION['otp']['email'] = [
        'code'    => $otp_code,
        'destino' => $email_nuevo,
        'expiry'  => $expiry,
    ];

    $enviado = enviarEmailOtp($email_nuevo, $usuario['nombre'], $otp_code, 'email');

} elseif ($tipo === 'password') {

    $_SESSION['otp']['password'] = [
        'code'   => $otp_code,
        'expiry' => $expiry,
    ];

    $enviado = enviarEmailOtp($usuario['email'], $usuario['nombre'], $otp_code, 'password');

} else {
    exit(json_encode(['success' => false, 'message' => 'Tipo de verificación inválido.']));
}

/* â”€â”€ Respuesta â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if ($enviado) {
    exit(json_encode(['success' => true, 'message' => t('otp_sent')]));
} else {
    exit(json_encode(['success' => false, 'message' => t('otp_send_error')]));
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   Función: enviar email con código OTP
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
function enviarEmailOtp(string $email, string $nombre, string $codigo, string $tipo): bool
{
    $mail = new PHPMailer(true);

    $asunto = $tipo === 'email'
        ? 'ðŸ” Código para cambiar tu correo — ASCC'
        : 'ðŸ” Código para cambiar tu contraseña — ASCC';

    $accion = $tipo === 'email'
        ? 'cambiar tu <strong>correo electrónico</strong>'
        : 'cambiar tu <strong>contraseña</strong>';

    try {
        $mail->isSMTP();
        $mail->CharSet    = 'UTF-8';
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(GMAIL_USER, GMAIL_NAME);
        $mail->addAddress($email, $nombre);
        $mail->isHTML(true);
        $mail->Subject = $asunto;

        $mail->Body = "
        <!DOCTYPE html>
        <html lang='es'>
        <head><meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background:#f5f5f5; margin:0; padding:0; }
            .container { max-width:520px; margin:30px auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.1); }
            .header { background:linear-gradient(135deg,#2D5016 0%,#1A3009 100%); color:#fff; padding:28px 30px; text-align:center; }
            .header h1 { margin:0; font-size:24px; }
            .content { padding:36px 30px; }
            .content p { color:#333; line-height:1.8; font-size:15px; }
            .otp-box { background:#f0fdf4; border:2px dashed #22c55e; border-radius:12px; padding:24px; text-align:center; margin:24px 0; }
            .otp-code { font-size:42px; font-weight:700; letter-spacing:12px; color:#1d6d3b; font-family:monospace; }
            .warning { background:#fff3e0; padding:14px; border-radius:8px; border-left:4px solid #f59e0b; margin:20px 0; }
            .footer { background:#f9f9f9; padding:18px; text-align:center; color:#666; font-size:13px; }
        </style>
        </head>
        <body>
        <div class='container'>
            <div class='header'><h1>ðŸ” Verificación de seguridad</h1></div>
            <div class='content'>
                <p>Hola <strong>$nombre</strong>,</p>
                <p>Recibimos una solicitud para $accion en <strong>ASCC</strong>. Usa el siguiente código de verificación:</p>
                <div class='otp-box'>
                    <div class='otp-code'>$codigo</div>
                    <p style='color:#555;margin:8px 0 0;font-size:13px;'>Válido por <strong>5 minutos</strong></p>
                </div>
                <div class='warning'>
                    <p style='margin:0;color:#92400e;'>
                        <strong>âš ï¸ Seguridad:</strong><br>
                        • Nunca compartas este código con nadie<br>
                        • ASCC jamás te lo pedirá por teléfono o chat<br>
                        • Si no solicitaste este cambio, ignora este email
                    </p>
                </div>
            </div>
            <div class='footer'>
                <p>© 2025 ASCC — Marketplace Agropecuario de Colombia ðŸ‡¨ðŸ‡´</p>
                <p>Correo automático, no respondas.</p>
            </div>
        </div>
        </body></html>";

        $mail->AltBody = "Hola $nombre,\n\nTu código de verificación ASCC es: $codigo\n\nVálido por 5 minutos. No lo compartas con nadie.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[ASCC] OTP email error: ' . $mail->ErrorInfo);
        return false;
    }
}
