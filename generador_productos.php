<?php
require_once __DIR__ . '/config/database.php';

// Limite de tiempo para que no se detenga
set_time_limit(300);

echo "<h1>Iniciando generación de prueba (1 producto)...</h1>";

// 1. Obtener vendedores o mixtos
$stmt = $conexion->query("SELECT id_usuario FROM usuarios WHERE rol IN ('vendedor', 'mixto') AND estado = 'activo'");
$vendedores = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($vendedores)) {
    die("❌ Error: No hay usuarios con rol vendedor o mixto en la base de datos.");
}

// 2. Definir producto de prueba muy campesino/real
$data_prod = [
    'categoria' => 'verduras', 'subcategoria' => 'Hortalizas', 'especifico' => 'Tomate',
    'tipo' => 'Caja de Tomate Chonto Campesino', 'unidad' => 'caja', 'precio_min' => 35000, 'precio_max' => 45000,
    'imagenes' => ['https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800&q=80']
];

// 3. Ubicaciones campesinas reales (Veredas y pueblos)
$ubicaciones_ficticias = [
    ['departamento' => 'Santander', 'municipio' => 'San Vicente de Chucurí', 'vereda' => 'Llano Grande', 'lat' => 6.883, 'lng' => -73.416],
    ['departamento' => 'Huila', 'municipio' => 'Pitalito', 'vereda' => 'Guacacallo', 'lat' => 1.865, 'lng' => -76.046],
    ['departamento' => 'Boyacá', 'municipio' => 'Aquitania', 'vereda' => 'Vereda Tota', 'lat' => 5.518, 'lng' => -72.880],
    ['departamento' => 'Antioquia', 'municipio' => 'El Santuario', 'vereda' => 'Bodegas', 'lat' => 6.136, 'lng' => -75.263]
];

$ids_ubicaciones = [];
foreach ($ubicaciones_ficticias as $ubi) {
    // Verificar si ya existe para no duplicar
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

// 4. Generar solo 1 producto para probar
$cantidad_a_generar = 1;
$productos_generados = 0;

for ($i = 0; $i < $cantidad_a_generar; $i++) {
    $vendedor_id = $vendedores[array_rand($vendedores)];
    $ubicacion_id = $ids_ubicaciones[array_rand($ids_ubicaciones)];
    
    $precio = rand($data_prod['precio_min'], $data_prod['precio_max']);
    $precio = round($precio / 100) * 100; // Redondear
    $cantidad = rand(5, 50);
    $descripcion = "Tomate chonto cultivado sin químicos, directo de la finca. Cosecha fresca recogida esta misma semana. Ideal para salsas y guisos.";
    
    // Fecha aleatoria diferente (Desde 2 de abril 2026 hasta hoy)
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
            ':tipo'            => $data_prod['tipo'],
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
        
        $codigo_producto = 'AGR-2026-VER-' . str_pad($id_producto, 5, '0', STR_PAD_LEFT);
        $conexion->prepare("UPDATE productos SET codigo_producto = ? WHERE id_producto = ?")->execute([$codigo_producto, $id_producto]);
        
        $imagen_url = $data_prod['imagenes'][0];
        
        $stmt = $conexion->prepare("INSERT INTO imagenes_productos (id_producto, ruta_imagen) VALUES (?, ?)");
        $stmt->execute([$id_producto, $imagen_url]);
        
        $conexion->commit();
        $productos_generados++;
        echo "✅ PRODUCTO DE PRUEBA CREADO: " . $data_prod['tipo'] . "<br>";
        echo "💰 Precio: $precio COP / " . $data_prod['unidad'] . "<br>";
        echo "📅 Fecha aleatoria asignada: $fecha_publicacion <br>";
        
    } catch (Exception $e) {
        $conexion->rollBack();
        echo "❌ Error al crear producto: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>¡Prueba finalizada! Ve al catálogo a revisar si aparece.</h2>";
?>
