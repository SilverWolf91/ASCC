<?php
require_once __DIR__ . '/config/database.php';

set_time_limit(500);

echo "<h1>Iniciando generación de 55 productos con 5 fotos cada uno...</h1>";

// 1. Obtener vendedores o mixtos
$stmt = $conexion->query("SELECT id_usuario FROM usuarios WHERE rol IN ('vendedor', 'mixto')");
$vendedores = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($vendedores)) {
    $stmt_any = $conexion->query("SELECT id_usuario FROM usuarios LIMIT 10");
    $vendedores = $stmt_any->fetchAll(PDO::FETCH_COLUMN);
    if (empty($vendedores)) {
        die("❌ ERROR GRAVE: Tu base de datos está completamente vacía de usuarios.");
    }
}

// Banco grande de imágenes agrícolas profesionales para no repetir la misma foto
$banco_imagenes = [
    'verduras' => [
        'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800&q=80',
        'https://images.unsplash.com/photo-1561136594-7f68413baa99?w=800&q=80',
        'https://images.unsplash.com/photo-1518977676601-b53f82aba655?w=800&q=80',
        'https://images.unsplash.com/photo-1590005354167-6da97ce231ce?w=800&q=80',
        'https://images.unsplash.com/photo-1524593166156-312f362cada0?w=800&q=80',
        'https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=800&q=80'
    ],
    'frutas' => [
        'https://images.unsplash.com/photo-1590502593747-4229879946b6?w=800&q=80',
        'https://images.unsplash.com/photo-1623065422900-05049b27aa0a?w=800&q=80',
        'https://images.unsplash.com/photo-1610832958506-aa56368176cf?w=800&q=80',
        'https://images.unsplash.com/photo-1582281268140-522b9e8bb0db?w=800&q=80',
        'https://images.unsplash.com/photo-1550258987-190a2d41a8ba?w=800&q=80',
        'https://images.unsplash.com/photo-1481349518771-20055b2a7b24?w=800&q=80'
    ],
    'huevos' => [
        'https://images.unsplash.com/photo-1506976785307-8732e854ad02?w=800&q=80',
        'https://images.unsplash.com/photo-1587486913049-53fc88980cfc?w=800&q=80',
        'https://images.unsplash.com/photo-1516448650812-25bd51dc04c5?w=800&q=80',
        'https://images.unsplash.com/photo-1582293041079-7814c2f12063?w=800&q=80',
        'https://images.unsplash.com/photo-1598514982205-f36b96d1e8d4?w=800&q=80'
    ],
    'lacteos' => [
        'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=800&q=80',
        'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=800&q=80',
        'https://images.unsplash.com/photo-1528750711129-ea2ca00185e4?w=800&q=80',
        'https://images.unsplash.com/photo-1628088062854-d1870b455389?w=800&q=80',
        'https://images.unsplash.com/photo-1596647414896-b0767c9c054f?w=800&q=80'
    ],
    'cereales' => [
        'https://images.unsplash.com/photo-1551754655-cd27e38d2076?w=800&q=80',
        'https://images.unsplash.com/photo-1586201375761-83865001e8ac?w=800&q=80',
        'https://images.unsplash.com/photo-1601303504899-7da354f5aa0f?w=800&q=80',
        'https://images.unsplash.com/photo-1435882673276-80516fc2d93e?w=800&q=80',
        'https://images.unsplash.com/photo-1574323347407-f5e1ad6d020b?w=800&q=80'
    ],
    'procesados' => [
        'https://images.unsplash.com/photo-1559525839-b184a4d698c7?w=800&q=80',
        'https://images.unsplash.com/photo-1497935586351-b67a49e012bf?w=800&q=80',
        'https://images.unsplash.com/photo-1506377247377-2a5b3b417ebb?w=800&q=80',
        'https://images.unsplash.com/photo-1611162616475-46b635cb6868?w=800&q=80',
        'https://images.unsplash.com/photo-1606791405792-1004f1718d0c?w=800&q=80'
    ]
];

// 2. Definir una gran variedad de productos reales campesinos
$datos_productos = [
    [
        'categoria' => 'verduras', 'subcategoria' => 'Hortalizas', 'especifico' => 'Tomate Chonto',
        'tipos' => ['Caja de Tomate Chonto Campesino', 'Tomate Chonto Primera Calidad', 'Tomate Aliño Rojo'],
        'descripciones' => [
            'Tomate chonto cultivado sin químicos, directo de la finca. Cosecha fresca recogida esta misma semana. Ideal para salsas y guisos.',
            'Excelente tomate rojo, carnoso y jugoso. Cultivado con abonos orgánicos en tierra fría.',
            'Caja de tomate chonto de exportación, tamaños grandes y parejos. Listo para despachar.'
        ],
        'unidad' => 'caja', 'precio_min' => 35000, 'precio_max' => 50000
    ],
    [
        'categoria' => 'frutas', 'subcategoria' => 'Cítricos', 'especifico' => 'Limón Tahití',
        'tipos' => ['Bulto de Limón Tahití', 'Limón Castilla de Finca', 'Limón Tahití Extra'],
        'descripciones' => [
            'Limón Tahití muy jugoso, cáscara delgada. Bulto de 50kg directo del productor.',
            'Cosecha nueva de limón, excelente tamaño y sin manchas. Ideal para jugos o restaurantes.',
            'Limón de tierra caliente, muy ácido y rendidor. Venta por bultos.'
        ],
        'unidad' => 'bulto', 'precio_min' => 60000, 'precio_max' => 90000
    ],
    [
        'categoria' => 'frutas', 'subcategoria' => 'Tropicales', 'especifico' => 'Plátano Hartón',
        'tipos' => ['Racimo de Plátano Hartón Verde', 'Plátano Dominico Pintón', 'Plátano Maduro para Asar'],
        'descripciones' => [
            'Racimos grandes de plátano hartón, gruesos y de muy buena calidad. Cortados hoy.',
            'Plátano de excelente grosor, especial para tajadas o patacones. Cultivo 100% limpio.',
            'Plátano pintón y maduro directo de la vega del río. Muy dulce y de buen peso.'
        ],
        'unidad' => 'racimo', 'precio_min' => 15000, 'precio_max' => 25000
    ],
    [
        'categoria' => 'huevos', 'subcategoria' => 'Gallina', 'especifico' => 'Huevos Campesinos',
        'tipos' => ['Cubeta de Huevos AA Campesinos', 'Huevos Criollos de Finca', 'Huevos Jumbo Pardo'],
        'descripciones' => [
            'Huevos de gallinas libres de pastoreo. Yema bien amarilla y excelente sabor.',
            'Cubeta de 30 unidades, tamaño AA. Gallinas alimentadas con maíz y purina de calidad.',
            'Huevos criollos 100% naturales, empacados en cartón listos para llevar.'
        ],
        'unidad' => 'cubeta', 'precio_min' => 14000, 'precio_max' => 18000
    ],
    [
        'categoria' => 'lacteos', 'subcategoria' => 'Leche', 'especifico' => 'Leche Cruda',
        'tipos' => ['Cantina de Leche Cruda', 'Leche Fresca de Ordeño', 'Leche Entera de Vaca'],
        'descripciones' => [
            'Leche cruda de vacas Holstein, muy cremosa y perfecta para hacer quesos o postres.',
            'Cantina de 40 litros, ordeño de la mañana. Garantía de pureza sin agua añadida.',
            'Leche fresca recién ordeñada en la finca. Vacas con pastoreo rotacional.'
        ],
        'unidad' => 'litro', 'precio_min' => 2000, 'precio_max' => 3000
    ],
    [
        'categoria' => 'cereales', 'subcategoria' => 'Maíz', 'especifico' => 'Maíz Amarillo',
        'tipos' => ['Bulto de Maíz Amarillo', 'Maíz Desgranado Seco', 'Maíz Trillado'],
        'descripciones' => [
            'Maíz amarillo seco, ideal para molienda o alimento de animales. Bulto de 50kg.',
            'Cosecha de maíz de excelente rendimiento, grano grande y limpio sin gorgojo.',
            'Maíz trillado de primera, muy blanco y suave para arepas.'
        ],
        'unidad' => 'bulto', 'precio_min' => 80000, 'precio_max' => 110000
    ],
    [
        'categoria' => 'procesados', 'subcategoria' => 'Café', 'especifico' => 'Café Pergamino',
        'tipos' => ['Carga de Café Pergamino Seco', 'Café Tostado en Grano', 'Café Especial Molido'],
        'descripciones' => [
            'Café variedad Castillo, secado al sol. Excelente taza, sin broca.',
            'Café tostado artesanalmente en la finca, aroma intenso y notas a chocolate.',
            'Café molido tipo exportación, cultivado a 1.700 metros de altura.'
        ],
        'unidad' => 'kg', 'precio_min' => 20000, 'precio_max' => 35000
    ]
];

// 3. Ubicaciones PURAMENTE RURALES
$ubicaciones_ficticias = [
    ['departamento' => 'Santander', 'municipio' => 'San Vicente de Chucurí', 'vereda' => 'Llano Grande', 'lat' => 6.883, 'lng' => -73.416],
    ['departamento' => 'Huila', 'municipio' => 'Pitalito', 'vereda' => 'Guacacallo', 'lat' => 1.865, 'lng' => -76.046],
    ['departamento' => 'Boyacá', 'municipio' => 'Aquitania', 'vereda' => 'Vereda Tota', 'lat' => 5.518, 'lng' => -72.880],
    ['departamento' => 'Antioquia', 'municipio' => 'Urrao', 'vereda' => 'Pabón', 'lat' => 6.314, 'lng' => -76.133],
    ['departamento' => 'Tolima', 'municipio' => 'Cajamarca', 'vereda' => 'Anaime', 'lat' => 4.444, 'lng' => -75.422]
];

$ids_ubicaciones = [];
foreach ($ubicaciones_ficticias as $ubi) {
    $stmt_check = $conexion->prepare("SELECT id_ubicacion FROM ubicaciones WHERE departamento = :d AND municipio = :m AND vereda = :v");
    $stmt_check->execute([':d' => $ubi['departamento'], ':m' => $ubi['municipio'], ':v' => $ubi['vereda']]);
    if ($row = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
        $ids_ubicaciones[] = $row['id_ubicacion'];
    } else {
        $stmt = $conexion->prepare("INSERT INTO ubicaciones (departamento, municipio, vereda, lat, lng) VALUES (:d, :m, :v, :lat, :lng)");
        $stmt->execute([':d' => $ubi['departamento'], ':m' => $ubi['municipio'], ':v' => $ubi['vereda'], ':lat' => $ubi['lat'], ':lng' => $ubi['lng']]);
        $ids_ubicaciones[] = $conexion->lastInsertId();
    }
}

// 4. Generar 55 productos con 5 fotos cada uno
$cantidad_a_generar = 55;
$productos_generados = 0;

for ($i = 0; $i < $cantidad_a_generar; $i++) {
    $vendedor_id = $vendedores[array_rand($vendedores)];
    $ubicacion_id = $ids_ubicaciones[array_rand($ids_ubicaciones)];
    $data_prod = $datos_productos[array_rand($datos_productos)];
    
    $tipo = $data_prod['tipos'][array_rand($data_prod['tipos'])] . " - Lote " . rand(100, 999);
    $descripcion = $data_prod['descripciones'][array_rand($data_prod['descripciones'])];
    
    $precio = rand($data_prod['precio_min'], $data_prod['precio_max']);
    $precio = round($precio / 100) * 100;
    $cantidad = rand(10, 200);
    
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
            ':tipo'            => $tipo,
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
        
        $prefijo = strtoupper(substr($data_prod['categoria'], 0, 3));
        $codigo_producto = 'AGR-2026-' . $prefijo . '-' . str_pad($id_producto, 5, '0', STR_PAD_LEFT);
        $conexion->prepare("UPDATE productos SET codigo_producto = ? WHERE id_producto = ?")->execute([$codigo_producto, $id_producto]);
        
        // Asignar 5 fotos distintas para este producto
        $banco_categoria = $banco_imagenes[$data_prod['categoria']];
        // Desordenar el banco de la categoría
        shuffle($banco_categoria);
        // Tomar hasta 5 fotos únicas (o las que haya disponibles)
        $fotos_a_insertar = array_slice($banco_categoria, 0, 5);
        
        $stmt_img = $conexion->prepare("INSERT INTO imagenes_productos (id_producto, ruta_imagen) VALUES (?, ?)");
        
        foreach ($fotos_a_insertar as $index => $ruta_img) {
            // Añadir random para asegurar carga distinta
            $url_final = $ruta_img . "&cache_bypass=" . rand(1000, 9999);
            $stmt_img->execute([$id_producto, $url_final]);
        }
        
        $conexion->commit();
        $productos_generados++;
        echo "<p>✅ <b>$tipo</b> | Precio: $precio | Fotos: " . count($fotos_a_insertar) . " | Fecha: $fecha_publicacion</p>";
        
    } catch (Exception $e) {
        $conexion->rollBack();
        echo "<p style='color:red;'>❌ Error al crear producto: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>🎉 ¡Éxito! Se inyectaron $productos_generados productos, cada uno con 5 fotos.</h2>";
?>
