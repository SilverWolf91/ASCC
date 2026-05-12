<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - CONFIGURACIÓN DE EMAIL
 * Sistema de envío de correos con PHPMailer
 * ═══════════════════════════════════════════════════════════
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Rutas CON carpeta src
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

require_once __DIR__ . '/env_loader.php';

// ═══════════════════════════════════════════════════════════
// FUNCIÓN HELPER: ENVIAR POR BREVO API (HTTP)
// ═══════════════════════════════════════════════════════════

function enviarPorBrevoAPI($toEmail, $toName, $subject, $htmlContent) {
    $apiKey = getenv('BREVO_API_KEY') ?: ($_ENV['BREVO_API_KEY'] ?? '');
    
    if (empty($apiKey)) {
        error_log("Error: BREVO_API_KEY no está configurada.");
        return "Error: BREVO_API_KEY no configurada en Railway.";
    }

    $senderEmail = getenv('GMAIL_USER') ?: 'lopeztorresjosesamuel@gmail.com';
    $senderName = getenv('GMAIL_NAME') ?: 'ASCC Colombia';

    $data = [
        'sender' => [
            'name' => $senderName,
            'email' => $senderEmail
        ],
        'to' => [
            [
                'email' => $toEmail,
                'name' => $toName
            ]
        ],
        'subject' => $subject,
        'htmlContent' => $htmlContent
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    } else {
        error_log("Brevo API Error ($httpCode): " . $response);
        return "Error de API ($httpCode)";
    }
}

// ═══════════════════════════════════════════════════════════
// CONFIGURACIÓN DE GMAIL — variables de entorno
// ═══════════════════════════════════════════════════════════

define('GMAIL_USER',     getenv('GMAIL_USER')     ?: '');
define('GMAIL_PASSWORD', getenv('GMAIL_PASSWORD') ?: '');
define('GMAIL_NAME',     getenv('GMAIL_NAME')     ?: 'ASCC Colombia');
// URL base del sitio — en Railway se configura como variable de entorno
define('APP_URL',        rtrim(getenv('APP_URL') ?: 'http://localhost/ascc', '/'));

// ═══════════════════════════════════════════════════════════
// FUNCIÓN: ENVIAR EMAIL DE RECUPERACIÓN DE CONTRASEÑA
// ═══════════════════════════════════════════════════════════

function enviarEmailRecuperacion($email, $nombre, $token)
{
    $url_recuperacion = APP_URL . "/views/auth/restablecer.php?token=" . urlencode($token);
    $subject = '🔑 Recupera tu contraseña - ASCC';

    $htmlContent = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 30px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #2D5016 0%, #1A3009 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 40px 30px; }
                .content p { color: #333; line-height: 1.8; font-size: 16px; }
                .btn { display: inline-block; background: linear-gradient(135deg, #F2A71B 0%, #D68910 100%); color: white; padding: 15px 35px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 14px; }
                .warning { background: #fff3e0; padding: 15px; border-radius: 8px; border-left: 4px solid #ff9800; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔑 Recuperación de Contraseña</h1>
                </div>
                <div class='content'>
                    <p>Hola <strong>$nombre</strong>,</p>
                    <p>Recibimos una solicitud para recuperar tu contraseña en <strong>ASCC</strong>.</p>
                    <p>Haz clic en el siguiente botón para crear una nueva contraseña:</p>
                    <p style='text-align: center;'>
                        <a href='$url_recuperacion' class='btn'>Restablecer Contraseña</a>
                    </p>
                    <div class='warning'>
                        <p style='margin: 0; color: #e65100;'>
                            <strong>⚠️ Importante:</strong><br>
                            • Este enlace expira en 24 horas<br>
                            • Si no solicitaste este cambio, ignora este email<br>
                            • Nunca compartas tu contraseña con nadie
                        </p>
                    </div>
                    <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                    <p style='word-break: break-all; color: #2D5016;'><small>$url_recuperacion</small></p>
                </div>
                <div class='footer'>
                    <p>© 2025 ASCC - Marketplace Agropecuario de Colombia 🇨🇴</p>
                    <p>Este es un correo automático, por favor no respondas.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return enviarPorBrevoAPI($email, $nombre, $subject, $htmlContent);
}

// ═══════════════════════════════════════════════════════════
// FUNCIÓN: ENVIAR EMAIL DE BIENVENIDA (REGISTRO)
// ═══════════════════════════════════════════════════════════

function enviarEmailBienvenida($email, $nombre)
{
    $url_dashboard      = APP_URL . "/dashboard.php";
    $url_crear_producto = APP_URL . "/crear_producto.php";
    $subject = '🌾 ¡Bienvenido a ASCC! - Cuenta creada exitosamente';

    $htmlContent = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 30px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #2D5016 0%, #1A3009 100%); color: white; padding: 40px 30px; text-align: center; }
                .header h1 { margin: 0 0 10px 0; font-size: 32px; }
                .content { padding: 40px 30px; }
                .content p { color: #333; line-height: 1.8; font-size: 16px; }
                .welcome-banner { background: linear-gradient(135deg, #F5F1E8 0%, #E8DFCF 100%); padding: 25px; border-radius: 10px; margin: 25px 0; text-align: center; border: 3px solid #F2A71B; }
                .btn { display: inline-block; background: linear-gradient(135deg, #F2A71B 0%, #D68910 100%); color: white; padding: 15px 35px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 10px 5px; }
                .features { background: #f9f9f9; padding: 25px; border-radius: 10px; margin: 25px 0; }
                .features h3 { color: #2D5016; margin-top: 0; }
                .features ul { margin: 0; padding-left: 20px; }
                .features li { margin: 10px 0; color: #555; line-height: 1.6; }
                .footer { background: #2D5016; color: white; padding: 25px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🌾 ¡Bienvenido a ASCC!</h1>
                    <p>Tu cuenta ha sido creada exitosamente</p>
                </div>
                <div class='content'>
                    <div class='welcome-banner'>
                        <h2 style='color: #2D5016; margin: 0 0 10px 0;'>🎉 ¡Hola $nombre!</h2>
                        <p style='color: #555; margin: 0;'>Nos alegra tenerte en nuestra comunidad agropecuaria</p>
                    </div>

                    <p><strong>¡Felicidades!</strong> Tu cuenta en <strong>ASCC</strong> está lista para usar.</p>

                    <div class='features'>
                        <h3>🚀 Ya puedes comenzar a vender:</h3>
                        <ul>
                            <li><strong>📦 Publicar productos</strong> con fotos y precios</li>
                            <li><strong>📍 Marcar ubicación GPS</strong> para que te encuentren</li>
                            <li><strong>💰 Gestionar tus ventas</strong> desde el dashboard</li>
                            <li><strong>📱 Contacto directo</strong> con compradores (WhatsApp, email, teléfono)</li>
                            <li><strong>⭐ Recibir calificaciones</strong> de clientes satisfechos</li>
                        </ul>
                    </div>

                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='$url_dashboard' class='btn'>🏠 Ir al Dashboard</a>
                        <a href='$url_crear_producto' class='btn'>➕ Publicar Producto</a>
                    </p>

                    <div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #1976d2;'>
                        <p style='margin: 0; color: #0d47a1;'>
                            <strong>💡 Consejo de experto:</strong> Sube fotos de buena calidad y describe detalladamente tus productos. ¡Esto atrae más compradores!
                        </p>
                    </div>

                    <p><strong>📋 Datos de tu cuenta:</strong></p>
                    <ul style='color: #555; line-height: 2;'>
                        <li><strong>Email:</strong> $email</li>
                        <li><strong>Nombre:</strong> $nombre</li>
                        <li><strong>Rol:</strong> Vendedor</li>
                        <li><strong>Estado:</strong> ✅ Cuenta activa</li>
                    </ul>

                    <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>

                    <p style='font-size: 18px; color: #2D5016;'><strong>¡Bienvenido a la familia ASCC! 🌾🇨🇴</strong></p>
                </div>
                <div class='footer'>
                    <p style='font-size: 18px; margin-bottom: 15px;'><strong>ASCC</strong></p>
                    <p>Marketplace Agropecuario de Colombia</p>
                    <p>Conectando productores con compradores</p>
                    <p style='font-size: 13px; opacity: 0.8; margin-top: 15px;'>Este es un correo automático, por favor no respondas.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return enviarPorBrevoAPI($email, $nombre, $subject, $htmlContent);
}

// ═══════════════════════════════════════════════════════════
// FUNCIÓN: GENERAR TOKEN ALFANUMÉRICO PARA REGISTRO
// Excluye caracteres ambiguos: 0, O, 1, l, I
// ═══════════════════════════════════════════════════════════

function generarTokenRegistro(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $len   = strlen($chars);
    $token = '';
    $bytes = random_bytes(8);
    for ($i = 0; $i < 8; $i++) {
        $token .= $chars[ord($bytes[$i]) % $len];
    }
    return $token;
}

// ═══════════════════════════════════════════════════════════
// FUNCIÓN: ENVIAR EMAIL DE VERIFICACIÓN DE CUENTA (REGISTRO)
// ═══════════════════════════════════════════════════════════

function enviarEmailVerificacionRegistro(string $email, string $nombre, string $token): bool
{
    $subject = '🌾 Verifica tu cuenta en ASCC — Código de seguridad';

    $htmlContent = "
<!DOCTYPE html>
<html lang='es'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1.0'>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Segoe UI',Arial,sans-serif;background:#eef4ee}
  .wrapper{max-width:600px;margin:30px auto;border-radius:18px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.15)}
  .header{background:linear-gradient(135deg,#1A3009 0%,#2D5016 55%,#3d6b1e 100%);padding:44px 30px 36px;text-align:center;position:relative;overflow:hidden}
  .header::after{content:'🍃';position:absolute;font-size:120px;opacity:.06;bottom:-20px;right:-10px;line-height:1}
  .header::before{content:'🌿';position:absolute;font-size:90px;opacity:.06;top:-10px;left:-10px;line-height:1}
  .h-icon{font-size:52px;margin-bottom:10px;display:block}
  .h-brand{color:#F2A71B;font-size:11px;font-weight:700;letter-spacing:3px;text-transform:uppercase;margin-bottom:10px}
  .h-title{color:#fff;font-size:28px;font-weight:800;line-height:1.25;margin-bottom:8px}
  .h-sub{color:rgba(255,255,255,.72);font-size:14px}
  .body{background:#fff;padding:42px 38px}
  .greeting{font-size:16px;color:#1f2937;line-height:1.7;margin-bottom:28px}
  .greeting strong{color:#2D5016}
  .token-label{font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:#9ca3af;text-align:center;margin-bottom:18px}
  .token-wrap{text-align:center;margin:0 0 28px}
  .token-card{display:inline-block;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:2.5px solid #4ade80;border-radius:18px;padding:20px 24px;box-shadow:0 6px 28px rgba(74,222,128,.22);max-width:100%;overflow:hidden;}
  .token-emoji{font-size:30px;margin-bottom:10px;display:block}
  .token-code{font-family:'Courier New',Courier,monospace;font-size:34px;font-weight:800;letter-spacing:6px;color:#1d6d3b;display:block;word-wrap:break-word;}
  .token-valid{margin-top:14px;font-size:13px;color:#059669;font-weight:600}
  .alert-time{background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1.5px solid #F2A71B;border-radius:12px;padding:18px 22px;margin:0 0 28px;display:flex;align-items:center;gap:14px}
  .at-icon{font-size:28px;flex-shrink:0}
  .at-text{font-size:14px;color:#92400e;line-height:1.6}
  .at-text strong{color:#7c2d12}
  .steps-title{font-size:13px;font-weight:700;color:#374151;margin-bottom:14px}
  .step{display:flex;align-items:flex-start;gap:14px;margin-bottom:14px}
  .step-n{width:28px;height:28px;background:#2D5016;color:#F2A71B;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0}
  .step-t{font-size:14px;color:#4b5563;line-height:1.6;padding-top:4px}
  .step-t strong{color:#1f2937}
  .security{background:#f9fafb;border-radius:12px;padding:20px 22px;margin-top:26px;border-left:4px solid #2D5016}
  .security-title{font-size:13px;font-weight:700;color:#374151;margin-bottom:10px}
  .security ul{padding-left:18px}
  .security li{font-size:13px;color:#6b7280;margin-bottom:7px;line-height:1.55}
  .footer{background:linear-gradient(135deg,#1A3009,#2D5016);padding:30px;text-align:center}
  .f-brand{color:#F2A71B;font-size:20px;font-weight:800;margin-bottom:6px}
  .f-text{color:rgba(255,255,255,.72);font-size:13px;line-height:1.6}
  .f-flag{font-size:22px;margin-top:10px}
  .divider{height:1px;background:#f3f4f6;margin:24px 0}
</style>
</head>
<body>
<div class='wrapper'>

  <div class='header'>
    <span class='h-icon'>🌾</span>
    <div class='h-brand'>Aromas y Sabores de mi Campo Colombiano</div>
    <div class='h-title'>¡Estás a un paso de unirte!</div>
    <div class='h-sub'>Verifica tu cuenta para empezar a vender y comprar</div>
  </div>

  <div class='body'>
    <p class='greeting'>
      ¡Hola <strong>$nombre</strong>! 👋<br><br>
      Nos alegra que quieras ser parte de <strong>ASCC</strong> — el marketplace que conecta
      a los productores agropecuarios de Colombia con compradores de todo el país.
      Para proteger tu cuenta, necesitamos confirmar que este correo es tuyo.
    </p>

    <p class='token-label'>🔐 Tu código de verificación personal</p>
    <div class='token-wrap'>
      <div class='token-card'>
        <span class='token-emoji'>🌿</span>
        <span class='token-code'>$token</span>
        <div class='token-valid'>⏱&nbsp; Válido por <strong>5 minutos</strong></div>
      </div>
    </div>

    <div class='alert-time'>
      <span class='at-icon'>⚡</span>
      <div class='at-text'>
        <strong>¡Actúa rápido!</strong> Este código expira en exactamente
        <strong>5 minutos</strong> por razones de seguridad. Si expira, podrás
        solicitar uno nuevo desde la página de verificación.
      </div>
    </div>

    <p class='steps-title'>📋 ¿Cómo usar el código?</p>
    <div class='step'>
      <div class='step-n'>1</div>
      <div class='step-t'>Vuelve a la <strong>página de verificación</strong> que se abrió en tu navegador.</div>
    </div>
    <div class='step'>
      <div class='step-n'>2</div>
      <div class='step-t'>Escribe el código <strong>exactamente como aparece</strong> — distingue entre mayúsculas y minúsculas.</div>
    </div>
    <div class='step'>
      <div class='step-n'>3</div>
      <div class='step-t'>Haz clic en <strong>«Verificar mi cuenta»</strong> y ¡listo! Tu cuenta quedará activa de inmediato.</div>
    </div>

    <div class='divider'></div>

    <div class='security'>
      <p class='security-title'>🔒 Tu seguridad es nuestra prioridad</p>
      <ul>
        <li>Nunca compartas este código — <strong>ASCC jamás te lo pedirá</strong> por teléfono, WhatsApp o chat</li>
        <li>Si no intentaste crear una cuenta, ignora este correo con tranquilidad</li>
        <li>Este código es de <strong>un solo uso</strong> y se invalida al ser utilizado</li>
      </ul>
    </div>
  </div>

  <div class='footer'>
    <div class='f-brand'>🌾 ASCC</div>
    <div class='f-text'>
      Marketplace Agropecuario de Colombia<br>
      Conectando el campo con el país 🇨🇴
    </div>
    <div class='f-flag'>🌱 Hecho con orgullo colombiano</div>
  </div>

</div>
</body>
</html>";

        return enviarPorBrevoAPI($email, $nombre, $subject, $htmlContent);
}