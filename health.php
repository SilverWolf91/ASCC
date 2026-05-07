<?php

/**
 * ASCC — Healthcheck endpoint
 * Ruta: health.php
 *
 * Endpoint mínimo para Railway / monitoreo externo.
 * NO toca base de datos, sesión, ni archivos de configuración del proyecto.
 * Su único trabajo: confirmar que PHP responde con 200.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
http_response_code(200);

echo json_encode([
    'status'    => 'ok',
    'service'   => 'ascc',
    'php'       => PHP_VERSION,
    'timestamp' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
