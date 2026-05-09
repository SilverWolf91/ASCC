<?php
/**
 * Script temporal para limpiar productos y mensajes de prueba de la BD.
 * Se eliminarán: Productos, Imágenes de Productos, Mensajes, Conversaciones, Reseñas de Productos.
 */
require_once __DIR__ . "/config/database.php";

try {
    // Desactivar restricciones de llaves foráneas para poder limpiar las tablas
    $conexion->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Limpiar tablas dependientes
    $tables = [
        'imagenes_productos',
        'mensajes',
        'conversaciones',
        'notificaciones',
        'resenas_productos'
    ];

    foreach ($tables as $table) {
        $conexion->exec("TRUNCATE TABLE $table");
    }

    // Limpiar registros de visitas relacionados con productos
    $conexion->exec("DELETE FROM visitas_detalle WHERE tipo_entidad = 'producto'");
    
    // Limpiar productos
    $conexion->exec("TRUNCATE TABLE productos");

    // Volver a activar restricciones
    $conexion->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "<h1>¡Limpieza Exitosa!</h1>";
    echo "<p>Todos los productos antiguos, imágenes rotas, mensajes y conversaciones han sido eliminados permanentemente de la base de datos.</p>";
    echo "<p>Ya puedes volver al catálogo y crear productos nuevos con imágenes en la nube (ImgBB).</p>";
    echo "<br><a href='/ascc/dashboard.php'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "<h1>Error durante la limpieza</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
