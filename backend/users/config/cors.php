<?php
/**
 * Configuración CORS para la comunicación con el Frontend de React (Vercel)
 * Este archivo debe ser incluido al inicio de todos los endpoints consumidos por React.
 */

// Permitir solicitudes desde el origen de Vercel (o localhost para desarrollo)
$allowed_origins = [
    'http://localhost:5173', // Vite default port
    'http://localhost:3000',
    // Aquí agregarás la URL de Vercel cuando esté desplegado, ej: 'https://ascc-frontend.vercel.app'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Si necesitas permitir todo temporalmente en desarrollo, descomenta la siguiente línea:
    // header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Credentials: true"); // Necesario si se envían cookies/sesiones
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Manejar preflight request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
