<?php
/**
 * Script temporal para limpiar las fotos de perfil rotas.
 * Se auto-eliminará por seguridad después de ejecutarse.
 */
require_once __DIR__ . '/backend/users/config/database.php';

try {
    // 1. Borrar todas las referencias a fotos de perfil en la base de datos
    $stmt = $conexion->prepare("UPDATE usuarios SET foto_perfil = NULL");
    $stmt->execute();

    echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
    echo "<h1 style='color:green;'>✅ Limpieza completada exitosamente</h1>";
    echo "<p>Las fotos de perfil de todos los usuarios han sido reseteadas en la base de datos.</p>";
    echo "<p>Ya puedes volver a la página principal.</p>";
    echo "</div>";

    // 2. Por seguridad, el script se autoelimina después de correrse con éxito
    @unlink(__FILE__);

} catch (Exception $e) {
    echo "<h1>Error al limpiar la base de datos:</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
