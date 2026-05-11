<?php
require_once __DIR__ . '/config/database.php';

try {
    $conexion->beginTransaction();

    // Obtener un usuario válido (vendedor, mixto o admin)
    $stmt_user = $conexion->query("SELECT id_usuario FROM usuarios WHERE rol IN ('vendedor', 'mixto', 'admin') LIMIT 1");
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if (!$user) die("Error: No se encontró ningún usuario vendedor en la base de datos.");
    $id_usuario = $user['id_usuario'];

    // Obtener una ubicación válida
    $stmt_ubi = $conexion->query("SELECT id_ubicacion FROM ubicaciones LIMIT 1");
    $ubi = $stmt_ubi->fetch(PDO::FETCH_ASSOC);
    if (!$ubi) die("Error: No se encontró ninguna ubicación en la base de datos.");
    $id_ubicacion = $ubi['id_ubicacion'];

    $codigo = 'TOM-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    $sql_prod = "INSERT INTO productos (
        codigo_producto, id_usuario, id_ubicacion, tipo_producto,
        categoria_principal, subcategoria, producto_especifico,
        descripcion, cantidad, unidad, precio, estado
    ) VALUES (
        :codigo, :id_usuario, :id_ubicacion, 'Tomates Frescos de Cosecha',
        'Verduras y Hortalizas', 'Hortalizas', 'Tomate',
        'Hermosos tomates frescos, cultivados de manera natural y seleccionados a mano. Tienen un color rojo intenso, textura firme y un sabor delicioso, ideales para ensaladas, guisos o salsas. Producto 100% campesino.',
        50, 'kg', 3500.00, 'disponible'
    )";

    $stmt = $conexion->prepare($sql_prod);
    $stmt->execute([
        ':codigo' => $codigo,
        ':id_usuario' => $id_usuario,
        ':id_ubicacion' => $id_ubicacion
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
