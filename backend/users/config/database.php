<?php

/**
 * ASCC — Conexión a la Base de Datos
 * Ruta: config/database.php
 *
 * Patrón Singleton via static variable.
 * Compatible hacia atrás: $conexion sigue disponible para módulos existentes.
 */

// ── Configuración ─────────────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'ASCC');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── Función Singleton (módulos nuevos) ────────────────────────────────────────

/**
 * Retorna siempre la misma instancia PDO durante toda la ejecución.
 * No importa cuántas veces se llame — una sola conexión abierta.
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    return $pdo;
}

// ── Compatibilidad hacia atrás (módulos existentes usan $conexion) ────────────
// Los archivos que ya funcionan con $conexion->query() siguen igual.
// No toques esta línea.
$conexion = getDBConnection();