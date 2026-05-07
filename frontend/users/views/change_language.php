<?php

/**
 * Endpoint para cambiar el idioma
 * ASCC - Change Language
 */

session_start();

// Incluir el sistema de idiomas
require_once __DIR__ . '/language.php';

// Verificar que sea una peticion POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Obtener el idioma solicitado
$lang = isset($_POST['language']) ? $_POST['language'] : '';

// Validar y cambiar el idioma
if (setLanguage($lang)) {
    echo json_encode([
        'success' => true,
        'language' => $lang,
        'message' => 'Language changed successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid language code'
    ]);
}
