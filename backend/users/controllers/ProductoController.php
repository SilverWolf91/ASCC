<?php
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/palabras_bloqueadas.php";

if (!isset($_SESSION["id_usuario"])) {
    header("Location: /ascc/frontend/users/views/auth/login.php");
    exit;
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$id_usuario = $_SESSION["id_usuario"];

try {
    switch ($accion) {
        case 'crear':
            crearProducto($conexion, $id_usuario);
            break;

        case 'marcar_vendido':
            marcarVendido($conexion, $id_usuario);
            break;

        case 'eliminar':
            eliminarProducto($conexion, $id_usuario);
            break;

        case 'obtener_producto':
            obtenerProductoPorConversacion($conexion, $id_usuario);
            break;

        default:
            header("Location: /ascc/dashboard.php");
            exit;
    }
} catch (Exception $e) {
    error_log("Error en ProductoController: " . $e->getMessage());
    header("Location: /ascc/dashboard.php?error=1");
    exit;
}

// ============================================
// FUNCIÓN: CREAR PRODUCTO
// ============================================
function crearProducto($conexion, $id_usuario)
{
    // Validar datos requeridos
    $tipo_producto = $_POST['tipo_producto'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $cantidad = $_POST['cantidad'] ?? 0;
    $unidad = $_POST['unidad'] ?? '';
    $departamento = $_POST['departamento'] ?? '';
    $municipio = $_POST['municipio'] ?? '';
    $vereda = $_POST['vereda'] ?? '';
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;

    // AÑADIDO: limpiar precio (viene "15.000" → necesitamos 15000)
    $precio = floatval(str_replace('.', '', $_POST['precio'] ?? '0'));

    // AÑADIDO: campos de categoría que envía el formulario
    $categoria_principal = trim($_POST['categoria_principal'] ?? '');
    $subcategoria        = trim($_POST['subcategoria']        ?? '');
    $producto_especifico = trim($_POST['producto_especifico'] ?? '');

    if (empty($tipo_producto) || empty($descripcion) || $precio <= 0 || $cantidad <= 0 || empty($unidad)) {
        header("Location: /ascc/crear_producto.php?error=datos_incompletos");
        exit;
    }

    /* ── Verificar palabras bloqueadas (drogas/sustancias) ── */
    $textoTotal = $tipo_producto . ' ' . $producto_especifico . ' ' . mb_substr($descripcion, 0, 500);
    $bloqueo    = verificarPalabrasBloqueadas($textoTotal);
    if ($bloqueo['bloqueado']) {
        error_log('[ASCC] Producto bloqueado por contenido (' . $bloqueo['categoria'] . '): ' . $bloqueo['palabra'] . ' — usuario ' . $id_usuario);
        header("Location: /ascc/crear_producto.php?error=contenido_bloqueado");
        exit;
    }

    if (empty($departamento) || empty($municipio) || empty($vereda) || empty($lat) || empty($lng)) {
        header("Location: /ascc/crear_producto.php?error=ubicacion_incompleta");
        exit;
    }

    /* ── Validar que las coordenadas estén dentro de Colombia ── */
    $latF = (float)$lat;
    $lngF = (float)$lng;
    if ($latF < -4.5 || $latF > 13.0 || $lngF < -79.5 || $lngF > -66.5) {
        error_log('[ASCC] Ubicación fuera de Colombia: lat=' . $latF . ' lng=' . $lngF . ' — usuario ' . $id_usuario);
        header("Location: /ascc/crear_producto.php?error=fuera_de_colombia");
        exit;
    }

    // Verificar imágenes
    if (!isset($_FILES['imagenes']) || empty($_FILES['imagenes']['name'][0])) {
        header("Location: /ascc/crear_producto.php?error=sin_imagenes");
        exit;
    }

    $conexion->beginTransaction();

    try {
        // 1. Buscar o crear ubicación
        $stmt = $conexion->prepare("
            SELECT id_ubicacion FROM ubicaciones 
            WHERE departamento = :depto AND municipio = :muni AND vereda = :vereda 
            LIMIT 1
        ");
        $stmt->execute([
            ':depto' => $departamento,
            ':muni' => $municipio,
            ':vereda' => $vereda
        ]);

        $ubicacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ubicacion) {
            $id_ubicacion = $ubicacion['id_ubicacion'];

            // Actualizar coordenadas si existen
            $stmt = $conexion->prepare("
                UPDATE ubicaciones 
                SET lat = :lat, lng = :lng 
                WHERE id_ubicacion = :id
            ");
            $stmt->execute([
                ':lat' => $lat,
                ':lng' => $lng,
                ':id' => $id_ubicacion
            ]);
        } else {
            // Crear nueva ubicación
            $stmt = $conexion->prepare("
                INSERT INTO ubicaciones (departamento, municipio, vereda, lat, lng) 
                VALUES (:depto, :muni, :vereda, :lat, :lng)
            ");
            $stmt->execute([
                ':depto' => $departamento,
                ':muni' => $municipio,
                ':vereda' => $vereda,
                ':lat' => $lat,
                ':lng' => $lng
            ]);
            $id_ubicacion = $conexion->lastInsertId();
        }

        // 2. Crear producto
        // AÑADIDO: categoria_principal, subcategoria, producto_especifico al INSERT
        $stmt = $conexion->prepare("
            INSERT INTO productos (
                tipo_producto, descripcion, precio, cantidad, unidad, 
                id_ubicacion, id_usuario, estado, fecha_publicacion,
                categoria_principal, subcategoria, producto_especifico
            ) VALUES (
                :tipo, :desc, :precio, :cant, :unidad, 
                :id_ubi, :id_user, 'disponible', NOW(),
                :categoria, :subcategoria, :prod_especifico
            )
        ");

        $stmt->execute([
            ':tipo'            => $tipo_producto,
            ':desc'            => $descripcion,
            ':precio'          => $precio,
            ':cant'            => $cantidad,
            ':unidad'          => $unidad,
            ':id_ubi'          => $id_ubicacion,
            ':id_user'         => $id_usuario,
            ':categoria'       => $categoria_principal ?: null,
            ':subcategoria'    => $subcategoria        ?: null,
            ':prod_especifico' => $producto_especifico ?: null,
        ]);

        $id_producto = $conexion->lastInsertId();

        // AÑADIDO: generar y guardar codigo_producto dentro de la misma transacción
        $prefijos = [
            'huevos' => 'HUV',
            'aves' => 'AVE',
            'bovinos' => 'BOV',
            'equinos' => 'EQU',
            'menor' => 'GAN',
            'carnicos' => 'CAR',
            'lacteos' => 'LAC',
            'verduras' => 'VER',
            'frutas' => 'FRU',
            'cereales' => 'CER',
            'plantas' => 'PLA',
            'procesados' => 'PRO',
        ];
        $prefijo         = $prefijos[strtolower(trim($categoria_principal))] ?? 'AGR';
        $codigo_producto = 'AGR-' . date('Y') . '-' . $prefijo . '-' . str_pad($id_producto, 5, '0', STR_PAD_LEFT);

        $stmt = $conexion->prepare("UPDATE productos SET codigo_producto = :codigo WHERE id_producto = :id");
        $stmt->execute([':codigo' => $codigo_producto, ':id' => $id_producto]);

        // 3. Subir imágenes
        $uploadDir = __DIR__ . "/../../frontend/users/public/uploads/productos/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imagenes = $_FILES['imagenes'];
        $totalImagenes = count($imagenes['name']);
        $maxImagenes = min($totalImagenes, 5);

        for ($i = 0; $i < $maxImagenes; $i++) {
            if ($imagenes['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $imagenes['tmp_name'][$i];
                $originalName = $imagenes['name'][$i];
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                // Validar extensión
                $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($extension, $extensionesPermitidas)) {
                    continue;
                }

                // Validar tamaño (5MB máximo)
                if ($imagenes['size'][$i] > 5 * 1024 * 1024) {
                    continue;
                }

                // Generar nombre único
                $nuevoNombre = 'prod_' . $id_producto . '_' . time() . '_' . $i . '.' . $extension;
                $rutaDestino = $uploadDir . $nuevoNombre;

                if (move_uploaded_file($tmpName, $rutaDestino)) {
                    // Guardar en BD
                    $rutaRelativa = 'uploads/productos/' . $nuevoNombre;
                    $stmt = $conexion->prepare("
                        INSERT INTO imagenes_productos (id_producto, ruta_imagen) 
                        VALUES (:id_prod, :ruta)
                    ");
                    $stmt->execute([
                        ':id_prod' => $id_producto,
                        ':ruta' => $rutaRelativa
                    ]);
                }
            }
        }

        $conexion->commit();

        // Redirigir con éxito
        header("Location: /ascc/catalogo.php?success=producto_creado");
        exit;
    } catch (Exception $e) {
        $conexion->rollBack();
        error_log("Error al crear producto: " . $e->getMessage());
        header("Location: /ascc/crear_producto.php?error=1");
        exit;
    }
}

// ============================================
// FUNCIÓN: MARCAR VENDIDO
// ============================================
function marcarVendido($conexion, $id_usuario)
{
    $id_producto = $_GET['id'] ?? 0;

    if (!$id_producto) {
        header("Location: /ascc/dashboard.php?error=id_invalido");
        exit;
    }

    // Verificar que el producto pertenece al usuario
    $stmt = $conexion->prepare("
        SELECT id_producto FROM productos 
        WHERE id_producto = :id AND id_usuario = :id_user
    ");
    $stmt->execute([
        ':id' => $id_producto,
        ':id_user' => $id_usuario
    ]);

    if ($stmt->rowCount() === 0) {
        header("Location: /ascc/dashboard.php?error=no_autorizado");
        exit;
    }

    // Marcar como vendido
    $stmt = $conexion->prepare("
        UPDATE productos 
        SET estado = 'vendido', fecha_venta = NOW() 
        WHERE id_producto = :id
    ");
    $stmt->execute([':id' => $id_producto]);

    header("Location: /ascc/dashboard.php?success=producto_vendido");
    exit;
}

// ============================================
// FUNCIÓN: ELIMINAR PRODUCTO
// ============================================
function eliminarProducto($conexion, $id_usuario)
{
    $id_producto = $_GET['id'] ?? 0;

    if (!$id_producto) {
        header("Location: /ascc/dashboard.php?error=id_invalido");
        exit;
    }

    // Verificar que el producto pertenece al usuario
    $stmt = $conexion->prepare("
        SELECT id_producto FROM productos 
        WHERE id_producto = :id AND id_usuario = :id_user
    ");
    $stmt->execute([
        ':id' => $id_producto,
        ':id_user' => $id_usuario
    ]);

    if ($stmt->rowCount() === 0) {
        header("Location: /ascc/dashboard.php?error=no_autorizado");
        exit;
    }

    $conexion->beginTransaction();

    try {
        // 1. Obtener rutas de imágenes
        $stmt = $conexion->prepare("SELECT ruta_imagen FROM imagenes_productos WHERE id_producto = :id");
        $stmt->execute([':id' => $id_producto]);
        $imagenes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Eliminar imágenes físicas
        foreach ($imagenes as $ruta) {
            $rutaCompleta = __DIR__ . "/../public/" . $ruta;
            if (file_exists($rutaCompleta)) {
                unlink($rutaCompleta);
            }
        }

        // 3. Eliminar registros de imágenes
        $stmt = $conexion->prepare("DELETE FROM imagenes_productos WHERE id_producto = :id");
        $stmt->execute([':id' => $id_producto]);

        // 4. Eliminar producto
        $stmt = $conexion->prepare("DELETE FROM productos WHERE id_producto = :id");
        $stmt->execute([':id' => $id_producto]);

        $conexion->commit();

        header("Location: /ascc/dashboard.php?success=producto_eliminado");
        exit;
    } catch (Exception $e) {
        $conexion->rollBack();
        error_log("Error al eliminar producto: " . $e->getMessage());
        header("Location: /ascc/dashboard.php?error=error_eliminar");
        exit;
    }
}

// ============================================
// FUNCIÓN: OBTENER PRODUCTO POR CONVERSACIÓN
// ============================================
function obtenerProductoPorConversacion($conexion, $id_usuario)
{
    header('Content-Type: application/json');

    $id_conversacion = (int)($_GET['id_conversacion'] ?? 0);

    if (!$id_conversacion) {
        echo json_encode(['success' => false, 'error' => 'ID conversación requerido']);
        exit;
    }

    try {
        // Verificar acceso a la conversación y obtener el producto
        $stmt = $conexion->prepare("
            SELECT 
                p.id_producto,
                p.codigo_producto,
                p.id_usuario,
                p.id_ubicacion,
                p.tipo_producto,
                p.categoria_principal,
                p.subcategoria,
                p.producto_especifico,
                p.descripcion,
                p.cantidad,
                p.unidad,
                p.precio,
                p.estado,
                p.fecha_publicacion,
                p.fecha_venta,
                u.departamento,
                u.municipio,
                u.vereda,
                GROUP_CONCAT(ip.ruta_imagen ORDER BY ip.id_imagen) as imagenes
            FROM conversaciones c
            INNER JOIN productos p ON c.id_producto = p.id_producto
            INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
            LEFT JOIN imagenes_productos ip ON p.id_producto = ip.id_producto
            WHERE c.id_conversacion = :id_conversacion
                AND (c.id_comprador = :id_usuario1 OR c.id_vendedor = :id_usuario2)
            GROUP BY p.id_producto
        ");

        $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
        $stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
        $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();

        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            echo json_encode([
                'success' => false,
                'error' => 'Producto no encontrado',
                'debug' => [
                    'id_conversacion' => $id_conversacion,
                    'id_usuario' => $id_usuario
                ]
            ]);
            exit;
        }

        // Convertir imagenes de string a array
        if (!empty($producto['imagenes'])) {
            $producto['imagenes'] = explode(',', $producto['imagenes']);
        } else {
            $producto['imagenes'] = [];
        }

        echo json_encode([
            'success' => true,
            'producto' => $producto
        ]);
        exit;
    } catch (PDOException $e) {
        error_log("Error al obtener producto: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error de base de datos',
            'sql_error' => $e->getMessage(),
            'sql_code' => $e->getCode()
        ]);
        exit;
    }
}