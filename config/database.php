<?php

/**
 * ASCC â€” ConexiÃ³n a la Base de Datos
 * Ruta: config/database.php
 *
 * PatrÃ³n Singleton via static variable.
 * Compatible hacia atrÃ¡s: $conexion sigue disponible para mÃ³dulos existentes.
 */

require_once __DIR__ . '/env_loader.php';

// â”€â”€ ConfiguraciÃ³n â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'ASCC');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// â”€â”€ FunciÃ³n Singleton (mÃ³dulos nuevos) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retorna siempre la misma instancia PDO durante toda la ejecuciÃ³n.
 * No importa cuÃ¡ntas veces se llame â€” una sola conexiÃ³n abierta.
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
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

// â”€â”€ Compatibilidad hacia atrÃ¡s (mÃ³dulos existentes usan $conexion) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Los archivos que ya funcionan con $conexion->query() siguen igual.
// No toques esta lÃ­nea.
$conexion = getDBConnection();