<?php
require_once __DIR__ . '/config/database.php';

echo "<h1>Limpiando productos generados...</h1>";

try {
    $conexion->beginTransaction();
    
    // 1. Obtener los IDs de los productos que generamos (Código empieza con AGR-2026-)
    $stmt = $conexion->query("SELECT id_producto FROM productos WHERE codigo_producto LIKE 'AGR-2026-%'");
    $productos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($productos) > 0) {
        $in = str_repeat('?,', count($productos) - 1) . '?';
        
        // 2. Borrar las imágenes de esos productos
        $stmt_img = $conexion->prepare("DELETE FROM imagenes_productos WHERE id_producto IN ($in)");
        $stmt_img->execute($productos);
        
        // 3. Borrar los productos
        $stmt_prod = $conexion->prepare("DELETE FROM productos WHERE id_producto IN ($in)");
        $stmt_prod->execute($productos);
        
        $conexion->commit();
        echo "<h2>✅ Se han eliminado correctamente " . count($productos) . " productos y sus imágenes.</h2>";
    } else {
        $conexion->rollBack();
        echo "<h2>No se encontraron productos generados por el script para borrar.</h2>";
    }
    
} catch (Exception $e) {
    $conexion->rollBack();
    echo "<h2>❌ Error al limpiar: " . $e->getMessage() . "</h2>";
}
?>
