<?php
require_once __DIR__ . '/config/database.php';

try {
    $conexion->beginTransaction();

    // Crear el producto en la tabla 'productos'
    // id_usuario = 43, id_ubicacion = 6
    $codigo = 'TOM-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    $sql_prod = "INSERT INTO productos (
        codigo_producto, id_usuario, id_ubicacion, tipo_producto,
        categoria_principal, subcategoria, producto_especifico,
        descripcion, cantidad, unidad, precio, estado
    ) VALUES (
        :codigo, 43, 6, 'Tomates Frescos de Cosecha',
        'Verduras y Hortalizas', 'Hortalizas', 'Tomate',
        'Hermosos tomates frescos, cultivados de manera natural y seleccionados a mano. Tienen un color rojo intenso, textura firme y un sabor delicioso, ideales para ensaladas, guisos o salsas. Producto 100% campesino.',
        50, 'kg', 3500.00, 'disponible'
    )";

    $stmt = $conexion->prepare($sql_prod);
    $stmt->execute([
        ':codigo' => $codigo
    ]);

    $id_producto = $conexion->lastInsertId();

    $urls_imagenes = [
        "https://i.ibb.co/Y78wczmj/8c2b3292cf43.jpg",
        "https://i.ibb.co/67LY9D23/e1249dd92af9.jpg",
        "https://i.ibb.co/qYy2v14d/e5443487a254.jpg",
        "https://i.ibb.co/0pbX62j2/2cb0a32f61e6.jpg",
        "https://i.ibb.co/RpxLvhWd/d3d05241c8fe.jpg"
    ];

    // Insertar las imágenes
    $sql_img = "INSERT INTO imagenes_productos (id_producto, ruta_imagen) VALUES (:id_prod, :ruta)";
    $stmt_img = $conexion->prepare($sql_img);

    foreach ($urls_imagenes as $url) {
        $stmt_img->execute([
            ':id_prod' => $id_producto,
            ':ruta' => $url
        ]);
    }

    $conexion->commit();
    echo "<h1>¡Éxito!</h1>";
    echo "<p>Producto de Tomates insertado correctamente en Producción con ID: $id_producto y " . count($urls_imagenes) . " imágenes vinculadas.</p>";
    echo "<a href='dashboard.php'>Volver al Dashboard</a>";

} catch (Exception $e) {
    $conexion->rollBack();
    die("Error en base de datos: " . $e->getMessage() . "\n");
}
