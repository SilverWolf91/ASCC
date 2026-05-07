<?php

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * ASCC â€” API: Reenviar Token de VerificaciÃ³n de Registro
 * Ruta: api/reenviar_token_registro.php
 *
 * Genera un nuevo token, actualiza la sesiÃ³n y reenvÃ­a el email.
 * LÃ­mite: mÃ­nimo 60 segundos entre reenvÃ­os.
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/email_config.php';

/* â”€â”€ Verificar que exista registro pendiente â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if (!isset($_SESSION['pending_registro'])) {
    exit(json_encode([
        'success' => false,
        'message' => 'SesiÃ³n expirada. Por favor regÃ­strate nuevamente.',
    ]));
}

/* â”€â”€ LÃ­mite de velocidad: 60 segundos entre reenvÃ­os â”€â”€â”€â”€â”€â”€â”€ */
$last_sent = $_SESSION['pending_registro']['last_sent'] ?? 0;
$wait      = 60 - (time() - $last_sent);

if ($wait > 0) {
    exit(json_encode([
        'success' => false,
        'message' => "Espera {$wait} segundos antes de solicitar otro cÃ³digo.",
    ]));
}

/* â”€â”€ Generar nuevo token â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$token = generarTokenRegistro();

$_SESSION['pending_registro']['token']     = $token;
$_SESSION['pending_registro']['expiry']    = time() + 300;
$_SESSION['pending_registro']['intentos']  = 0;
$_SESSION['pending_registro']['last_sent'] = time();

$email  = $_SESSION['pending_registro']['email'];
$nombre = $_SESSION['pending_registro']['nombre'];

/* â”€â”€ Enviar email â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$enviado = enviarEmailVerificacionRegistro($email, $nombre, $token);

if ($enviado) {
    exit(json_encode([
        'success' => true,
        'message' => 'Nuevo cÃ³digo enviado. Revisa tu bandeja de entrada.',
    ]));
} else {
    exit(json_encode([
        'success' => false,
        'message' => 'No se pudo enviar el email. Intenta de nuevo en unos momentos.',
    ]));
}
