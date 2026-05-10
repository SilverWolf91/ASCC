<?php
require_once __DIR__ . '/config/database.php';

try {
    // Verificar si las columnas ya existen
    $stmt = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'mp_access_token'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Las columnas ya existen en la base de datos.<br>";
    } else {
        // Agregar columnas
        $sql = "ALTER TABLE usuarios 
                ADD COLUMN mp_access_token VARCHAR(255) NULL AFTER password,
                ADD COLUMN mp_public_key VARCHAR(255) NULL AFTER mp_access_token";
        
        $conexion->exec($sql);
        echo "🚀 Columnas 'mp_access_token' y 'mp_public_key' agregadas exitosamente a la tabla 'usuarios'.<br>";
    }
    
    echo "<br><a href='index.php'>Volver al inicio</a>";
    
} catch (PDOException $e) {
    echo "❌ Error al modificar la base de datos: " . $e->getMessage();
}
?>
