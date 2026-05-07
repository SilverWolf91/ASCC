<?php

/**
 * Script de un solo uso para importar la base de datos ASCC.sql a Railway.
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Leer el archivo SQL
    $sql = file_get_contents(__DIR__ . '/ASCC.sql');
    
    if (!$sql) {
        die("No se pudo leer el archivo ASCC.sql.");
    }
    
    // Ejecutar todas las sentencias
    $pdo->exec($sql);
    
    echo "<h1>¡Éxito! 🎉</h1>";
    echo "<p>La base de datos se importó correctamente. Ya puedes ir a tu catálogo.</p>";
    echo '<a href="catalogo.php">Ir al Catálogo</a>';
    
} catch (PDOException $e) {
    echo "<h1>Error al importar la base de datos 😔</h1>";
    echo "<p>Si dice 'Table already exists', significa que ya se había importado antes.</p>";
    echo "<p>Detalle técnico: " . htmlspecialchars($e->getMessage()) . "</p>";
}
