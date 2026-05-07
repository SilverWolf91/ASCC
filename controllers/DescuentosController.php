<?php

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * DESCUENTOS CONTROLLER
 * GestiÃ³n de descuentos para productos
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */

session_start();
require_once __DIR__ . "/../config/database.php";

// Verificar autenticaciÃ³n
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    /**
     * CREAR DESCUENTO
     */
    case 'crear_descuento':
        $id_producto = $_POST['id_producto'] ?? 0;
        $porcentaje = $_POST['porcentaje_descuento'] ?? 0;
        $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
        $fecha_fin = $_POST['fecha_fin'] ?? '';

        if (!$id_producto || !$porcentaje || !$fecha_fin) {
            echo json_encode(['error' => 'Datos incompletos']);
            exit;
        }

        if ($porcentaje < 1 || $porcentaje > 90) {
            echo json_encode(['error' => 'El descuento debe estar entre 1% y 90%']);
            exit;
        }

        try {
            // Verificar que el producto es del usuario
            $stmt = $conexion->prepare("
                SELECT precio 
                FROM productos 
                WHERE id_producto = :id_producto 
                    AND id_usuario = :id_usuario
                    AND estado = 'disponible'
            ");
            $stmt->bindParam(':id_producto', $id_producto);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                echo json_encode(['error' => 'Producto no encontrado o no te pertenece']);
                exit;
            }

            // Verificar si ya existe un descuento activo
            $stmt = $conexion->prepare("
                SELECT id_descuento 
                FROM descuentos 
                WHERE id_producto = :id_producto 
                    AND activo = TRUE
                    AND CURDATE() BETWEEN fecha_inicio AND fecha_fin
            ");
            $stmt->bindParam(':id_producto', $id_producto);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                echo json_encode(['error' => 'Este producto ya tiene un descuento activo']);
                exit;
            }

            // Calcular precios
            $precio_original = $producto['precio'];
            $precio_con_descuento = $precio_original - ($precio_original * $porcentaje / 100);

            // Insertar descuento
            $stmt = $conexion->prepare("
                INSERT INTO descuentos (
                    id_producto, 
                    porcentaje_descuento, 
                    precio_original, 
                    precio_con_descuento,
                    fecha_inicio, 
                    fecha_fin
                ) VALUES (
                    :id_producto, 
                    :porcentaje, 
                    :precio_original, 
                    :precio_con_descuento,
                    :fecha_inicio, 
                    :fecha_fin
                )
            ");
            $stmt->bindParam(':id_producto', $id_producto);
            $stmt->bindParam(':porcentaje', $porcentaje);
            $stmt->bindParam(':precio_original', $precio_original);
            $stmt->bindParam(':precio_con_descuento', $precio_con_descuento);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->execute();

            $id_descuento = $conexion->lastInsertId();

            echo json_encode([
                'success' => true,
                'id_descuento' => $id_descuento,
                'precio_original' => $precio_original,
                'precio_con_descuento' => $precio_con_descuento,
                'mensaje' => 'Descuento creado correctamente'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear descuento: ' . $e->getMessage()]);
        }
        break;

    /**
     * OBTENER DESCUENTOS ACTIVOS DEL USUARIO
     */
    case 'obtener_mis_descuentos':
        try {
            $stmt = $conexion->prepare("
                SELECT 
                    d.id_descuento,
                    d.id_producto,
                    d.porcentaje_descuento,
                    d.precio_original,
                    d.precio_con_descuento,
                    d.fecha_inicio,
                    d.fecha_fin,
                    p.tipo_producto,
                    p.cantidad,
                    p.unidad,
                    (SELECT ruta_imagen FROM imagenes_productos WHERE id_producto = p.id_producto LIMIT 1) as imagen
                FROM descuentos d
                INNER JOIN productos p ON d.id_producto = p.id_producto
                WHERE p.id_usuario = :id_usuario
                    AND d.activo = TRUE
                    AND CURDATE() BETWEEN d.fecha_inicio AND d.fecha_fin
                    AND p.estado = 'disponible'
                ORDER BY d.fecha_creacion DESC
            ");
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $descuentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'descuentos' => $descuentos
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener descuentos: ' . $e->getMessage()]);
        }
        break;

    /**
     * DESACTIVAR DESCUENTO
     */
    case 'desactivar_descuento':
        $id_descuento = $_POST['id_descuento'] ?? 0;

        if (!$id_descuento) {
            echo json_encode(['error' => 'Descuento no especificado']);
            exit;
        }

        try {
            // Verificar que el descuento pertenece a un producto del usuario
            $stmt = $conexion->prepare("
                SELECT d.id_descuento 
                FROM descuentos d
                INNER JOIN productos p ON d.id_producto = p.id_producto
                WHERE d.id_descuento = :id_descuento 
                    AND p.id_usuario = :id_usuario
            ");
            $stmt->bindParam(':id_descuento', $id_descuento);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                echo json_encode(['error' => 'Descuento no encontrado']);
                exit;
            }

            // Desactivar
            $stmt = $conexion->prepare("
                UPDATE descuentos 
                SET activo = FALSE 
                WHERE id_descuento = :id_descuento
            ");
            $stmt->bindParam(':id_descuento', $id_descuento);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'mensaje' => 'Descuento desactivado correctamente'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al desactivar descuento: ' . $e->getMessage()]);
        }
        break;

    /**
     * OBTENER PRODUCTOS CON DESCUENTO (PÃšBLICO)
     */
    case 'obtener_productos_descuento':
        try {
            $stmt = $conexion->prepare("
                SELECT 
                    p.id_producto,
                    p.tipo_producto,
                    p.descripcion,
                    p.cantidad,
                    p.unidad,
                    d.precio_original,
                    d.precio_con_descuento,
                    d.porcentaje_descuento,
                    d.fecha_fin,
                    u.nombre as vendedor,
                    ub.departamento,
                    ub.municipio,
                    (SELECT ruta_imagen FROM imagenes_productos WHERE id_producto = p.id_producto LIMIT 1) as imagen
                FROM productos p
                INNER JOIN descuentos d ON p.id_producto = d.id_producto
                INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                INNER JOIN ubicaciones ub ON p.id_ubicacion = ub.id_ubicacion
                WHERE p.estado = 'disponible'
                    AND d.activo = TRUE
                    AND CURDATE() BETWEEN d.fecha_inicio AND d.fecha_fin
                ORDER BY d.porcentaje_descuento DESC
                LIMIT 20
            ");
            $stmt->execute();
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'productos' => $productos
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener productos: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'AcciÃ³n no vÃ¡lida']);
        break;
}
