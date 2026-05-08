import re

with open("c:/xampp/htdocs/ASCC/config/email_config.php", "r", encoding="utf-8") as f:
    content = f.read()

brevo_helper = """
// -----------------------------------------------------------
// FUNCIÓN HELPER: ENVIAR POR BREVO API (HTTP)
// -----------------------------------------------------------
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
"""

content = content.replace("// -----------------------------------------------------------\n// CONFIGURACIÓN DE GMAIL — variables de entorno", brevo_helper + "\n// -----------------------------------------------------------\n// CONFIGURACIÓN DE GMAIL — variables de entorno")

# Fix Recuperacion
content = re.sub(r'\$mail = new PHPMailer\(true\);\s*try \{.*?(?=\$url_recuperacion)', r'', content, flags=re.DOTALL)
content = re.sub(r'\$mail->isHTML\(true\);\s*\$mail->Subject = \'([^\']+)\';\s*\$mail->Body = "', r'$subject = \'\1\';\n        $htmlContent = "', content, flags=re.DOTALL)
content = re.sub(r'";\s*\$mail->AltBody = "[^"]*";\s*\$mail->send\(\);\s*return true;\s*\}\s*catch\s*\(Exception\s*\$e\)\s*\{\s*error_log\([^)]+\);\s*return\s*\$mail->ErrorInfo;\s*\}', r'";\n\n        return enviarPorBrevoAPI($email, $nombre, $subject, $htmlContent);', content, flags=re.DOTALL)

# Fix Bienvenida
content = re.sub(r'function enviarEmailBienvenida\(\$email, \$nombre\)\s*\{\s*\$mail = new PHPMailer\(true\);\s*try \{.*?(?=\$url_dashboard)', r'function enviarEmailBienvenida($email, $nombre)\n{\n        ', content, flags=re.DOTALL)
content = re.sub(r'";\s*\$mail->AltBody = "[^"]*";\s*\$mail->send\(\);\s*return true;\s*\}\s*catch\s*\(Exception\s*\$e\)\s*\{\s*error_log\([^)]+\);\s*return false;\s*\}', r'";\n\n        return enviarPorBrevoAPI($email, $nombre, $subject, $htmlContent);', content, flags=re.DOTALL)

# Fix VerificacionRegistro
content = re.sub(r'function enviarEmailVerificacionRegistro\(string \$email, string \$nombre, string \$token\): bool\s*\{\s*\$mail = new PHPMailer\(true\);\s*try \{.*?(?=\$mail->isHTML\(true\);)', r'function enviarEmailVerificacionRegistro(string $email, string $nombre, string $token): bool\n{\n        ', content, flags=re.DOTALL)
content = re.sub(r'\$mail->isHTML\(true\);\s*\$mail->Subject = \'([^\']+)\';\s*\$mail->Body = "', r'$subject = \'\1\';\n        $htmlContent = "', content, flags=re.DOTALL)

with open("c:/xampp/htdocs/ASCC/config/email_config.php", "w", encoding="utf-8") as f:
    f.write(content)

print("Rewrite done")
