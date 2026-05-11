<?php
require_once __DIR__ . '/config/database.php';

// Limite de tiempo para que no se detenga
set_time_limit(300);

echo "<h1>Iniciando generación de productos...</h1>";

// 1. Obtener vendedores o mixtos
$stmt = $conexion->query("SELECT id_usuario FROM usuarios WHERE rol IN ('vendedor', 'mixto') AND estado = 'activo'");
$vendedores = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($vendedores)) {
    die("❌ Error: No hay usuarios con rol vendedor o mixto en la base de datos. Genera usuarios primero.");
}

// 2. Definir datos de prueba (Categorías y Productos)
$datos_productos = [
    [
        'categoria' => 'verduras', 'subcategoria' => 'Hortalizas', 'especifico' => 'Tomate',
        'tipo' => 'Tomate Chonto Primera Calidad', 'unidad' => 'kg', 'precio_min' => 2500, 'precio_max' => 4000,
        'imagenes' => ['https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800&q=80']
    ],
    [
        'categoria' => 'frutas', 'subcategoria' => 'Cítricos', 'especifico' => 'Limón',
        'tipo' => 'Limón Tahití de Exportación', 'unidad' => 'bulto', 'precio_min' => 50000, 'precio_max' => 80000,
        'imagenes' => ['https://images.unsplash.com/photo-1590502593747-4229879946b6?w=800&q=80']
    ],
    [
        'categoria' => 'frutas', 'subcategoria' => 'Tropicales', 'especifico' => 'Plátano',
        'tipo' => 'Plátano Hartón Verde', 'unidad' => 'kg', 'precio_min' => 1500, 'precio_max' => 3000,
        'imagenes' => ['https://images.unsplash.com/photo-1623065422900-05049b27aa0a?w=800&q=80']
    ],
    [
        'categoria' => 'huevos', 'subcategoria' => 'Gallina', 'especifico' => 'Huevos AA',
        'tipo' => 'Huevos Campesinos AA (Cubeta)', 'unidad' => 'unidad', 'precio_min' => 12000, 'precio_max' => 16000,
        'imagenes' => ['https://images.unsplash.com/photo-1506976785307-8732e854ad02?w=800&q=80']
    ],
    [
        'categoria' => 'lacteos', 'subcategoria' => 'Leche', 'especifico' => 'Leche Cruda',
        'tipo' => 'Leche Cruda de Vaca recién ordeñada', 'unidad' => 'litro', 'precio_min' => 2000, 'precio_max' => 3500,
        'imagenes' => ['https://images.unsplash.com/photo-1550583724-b2692b85b150?w=800&q=80']
    ],
    [
        'categoria' => 'cereales', 'subcategoria' => 'Maíz', 'especifico' => 'Maíz Amarillo',
        'tipo' => 'Maíz Amarillo Desgranado Seco', 'unidad' => 'tonelada', 'precio_min' => 1200000, 'precio_max' => 1500000,
        'imagenes' => ['https://images.unsplash.com/photo-1551754655-cd27e38d2076?w=800&q=80']
    ],
    [
        'categoria' => 'procesados', 'subcategoria' => 'Café', 'especifico' => 'Café Tostado',
        'tipo' => 'Café Orgánico Tostión Media', 'unidad' => 'kg', 'precio_min' => 25000, 'precio_max' => 45000,
        'imagenes' => ['https://images.unsplash.com/photo-1559525839-b184a4d698c7?w=800&q=80']
    ]
];

// 3. Crear ubicaciones ficticias en Colombia
$ubicaciones_ficticias = [
    ['departamento' => 'Antioquia', 'municipio' => 'Medellín', 'vereda' => 'Santa Elena', 'lat' => 6.216, 'lng' => -75.498],
    ['departamento' => 'Cundinamarca', 'municipio' => 'Bogotá', 'vereda' => 'Usme Rural', 'lat' => 4.475, 'lng' => -74.148],
    ['departamento' => 'Valle del Cauca', 'municipio' => 'Cali', 'vereda' => 'Pance', 'lat' => 3.332, 'lng' => -76.536],
    ['departamento' => 'Boyacá', 'municipio' => 'Tunja', 'vereda' => 'Pirgua', 'lat' => 5.535, 'lng' => -73.367],
    ['departamento' => 'Santander', 'municipio' => 'Bucaramanga', 'vereda' => 'Morrorico', 'lat' => 7.125, 'lng' => -73.119]
];

$ids_ubicaciones = [];
foreach ($ubicaciones_ficticias as $ubi) {
    $stmt = $conexion->prepare("INSERT INTO ubicaciones (departamento, municipio, vereda, lat, lng) VALUES (:d, :m, :v, :lat, :lng)");
    $stmt->execute([':d' => $ubi['departamento'], ':m' => $ubi['municipio'], ':v' => $ubi['vereda'], ':lat' => $ubi['lat'], ':lng' => $ubi['lng']]);
    $ids_ubicaciones[] = $conexion->lastInsertId();
}

$productos_generados = 0;
$cantidad_a_generar = 30; // Generaremos 30 productos

for ($i = 0; $i < $cantidad_a_generar; $i++) {
    $vendedor_id = $vendedores[array_rand($vendedores)];
    $ubicacion_id = $ids_ubicaciones[array_rand($ids_ubicaciones)];
    $data_prod = $datos_productos[array_rand($datos_productos)];
    
    $precio = rand($data_prod['precio_min'], $data_prod['precio_max']);
    // Redondear a miles
    $precio = round($precio / 100) * 100;
    $cantidad = rand(10, 500);
    $descripcion = "Este es un producto de excelente calidad, cultivado directamente en el campo colombiano por nuestros campesinos. Listo para distribución y consumo.";
    
    // Fecha aleatoria desde el 2 de abril
    $fecha_inicio = strtotime('2026-04-02 00:00:00');
    $fecha_fin = time();
    $fecha_publicacion = date('Y-m-d H:i:s', rand($fecha_inicio, $fecha_fin));
    
    try {
        $conexion->beginTransaction();
        
        $stmt = $conexion->prepare("
            INSERT INTO productos (
                tipo_producto, descripcion, precio, cantidad, unidad, 
                id_ubicacion, id_usuario, estado, fecha_publicacion,
                categoria_principal, subcategoria, producto_especifico
            ) VALUES (
                :tipo, :desc, :precio, :cant, :unidad, 
                :id_ubi, :id_user, 'disponible', :fecha,
                :categoria, :subcategoria, :prod_especifico
            )
        ");
        
        $stmt->execute([
            ':tipo'            => $data_prod['tipo'] . " - Lote " . rand(1, 999),
            ':desc'            => $descripcion,
            ':precio'          => $precio,
            ':cant'            => $cantidad,
            ':unidad'          => $data_prod['unidad'],
            ':id_ubi'          => $ubicacion_id,
            ':id_user'         => $vendedor_id,
            ':fecha'           => $fecha_publicacion,
            ':categoria'       => $data_prod['categoria'],
            ':subcategoria'    => $data_prod['subcategoria'],
            ':prod_especifico' => $data_prod['especifico']
        ]);
        
        $id_producto = $conexion->lastInsertId();
        
        // Generar código
        $prefijos = ['huevos'=>'HUV', 'frutas'=>'FRU', 'verduras'=>'VER', 'lacteos'=>'LAC', 'cereales'=>'CER', 'procesados'=>'PRO'];
        $prefijo = $prefijos[strtolower($data_prod['categoria'])] ?? 'AGR';
        $codigo_producto = 'AGR-2026-' . $prefijo . '-' . str_pad($id_producto, 5, '0', STR_PAD_LEFT);
        
        $conexion->prepare("UPDATE productos SET codigo_producto = ? WHERE id_producto = ?")->execute([$codigo_producto, $id_producto]);
        
        // Insertar imagen
        $imagen_url = $data_prod['imagenes'][0];
        // Opcional: añadir un random para que la imagen cambie un poco si se permite (Unsplash lo permite con ?random=)
        $imagen_url .= "&random=" . rand(1,1000);
        
        $stmt = $conexion->prepare("INSERT INTO imagenes_productos (id_producto, ruta_imagen) VALUES (?, ?)");
        $stmt->execute([$id_producto, $imagen_url]);
        
        $conexion->commit();
        $productos_generados++;
        echo "✅ Producto publicado: " . $data_prod['tipo'] . " ($precio COP) - Código: $codigo_producto<br>";
        
    } catch (Exception $e) {
        $conexion->rollBack();
        echo "❌ Error al crear producto: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>🎉 Se generaron $productos_generados productos con éxito.</h2>";
?>
