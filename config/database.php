<?php

/**
 * ASCC — Conexión a la Base de Datos
 * Ruta: config/database.php
 *
 * Patrón Singleton vía variable estática.
 * Compatible hacia atrás: $conexion sigue disponible para módulos existentes.
 */

require_once __DIR__ . '/env_loader.php';

// ── Configuración ────────────────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'ASCC');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// ── Función Singleton (módulos nuevos) ───────────────────────────────────────

/**
 * Retorna siempre la misma instancia PDO durante toda la ejecución.
 * No importa cuántas veces se llame: una sola conexión abierta.
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
                PDO::ATTR_TIMEOUT            => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"
            ]
        );
    }

    return $pdo;
}

// ── Compatibilidad hacia atrás (módulos existentes usan $conexion) ──────────
// Los archivos que ya funcionan con $conexion->query() siguen igual.
$conexion = getDBConnection();
