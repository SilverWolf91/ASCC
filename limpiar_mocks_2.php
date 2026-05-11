<?php
require_once __DIR__ . '/config/database.php';

echo "<h1>Limpiando productos generados...</h1>";

try {
    $conexion->beginTransaction();
    
    $stmt = $conexion->query("SELECT id_producto FROM productos WHERE codigo_producto LIKE 'AGR-2026-%'");
    $productos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($productos) > 0) {
        $in = str_repeat('?,', count($productos) - 1) . '?';
        
        // 1. Borrar mensajes de las conversaciones de estos productos
        $stmt_msg = $conexion->prepare("
            DELETE FROM mensajes 
            WHERE id_conversacion IN (SELECT id_conversacion FROM conversaciones WHERE id_producto IN ($in))
        ");
        $stmt_msg->execute($productos);
        
        // 2. Borrar las conversaciones
        $stmt_conv = $conexion->prepare("DELETE FROM conversaciones WHERE id_producto IN ($in)");
        $stmt_conv->execute($productos);
        
        // 3. Borrar visitas
        $stmt_visitas = $conexion->prepare("DELETE FROM visitas_productos WHERE id_producto IN ($in)");
        $stmt_visitas->execute($productos);
        
        // 4. Borrar imágenes
        $stmt_img = $conexion->prepare("DELETE FROM imagenes_productos WHERE id_producto IN ($in)");
        $stmt_img->execute($productos);
        
        // 5. Borrar productos
        $stmt_prod = $conexion->prepare("DELETE FROM productos WHERE id_producto IN ($in)");
        $stmt_prod->execute($productos);
        
        $conexion->commit();
        echo "<h2>✅ Se han eliminado correctamente " . count($productos) . " productos y sus datos asociados.</h2>";
    } else {
        $conexion->rollBack();
        echo "<h2>No se encontraron productos generados por el script para borrar.</h2>";
    }
    
} catch (Exception $e) {
    $conexion->rollBack();
    echo "<h2>❌ Error al limpiar: " . $e->getMessage() . "</h2>";
}
?>
