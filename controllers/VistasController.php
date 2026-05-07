<?php

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * VISTAS CONTROLLER
 * Tracking de vistas de productos
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */

session_start();
require_once __DIR__ . "/../config/database.php";

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    /**
     * REGISTRAR VISTA DE PRODUCTO
     */
    case 'registrar_vista':
        $id_producto = $_POST['id_producto'] ?? $_GET['id_producto'] ?? 0;

        if (!$id_producto) {
            echo json_encode(['error' => 'Producto no especificado']);
            exit;
        }

        try {
            $id_usuario = $_SESSION['id_usuario'] ?? null;
            $ip_visitante = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            // Evitar registrar vista del propio vendedor
            if ($id_usuario) {
                $stmt = $conexion->prepare("
                    SELECT id_usuario 
                    FROM productos 
                    WHERE id_producto = :id_producto
                ");
                $stmt->bindParam(':id_producto', $id_producto);
                $stmt->execute();
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($producto && $producto['id_usuario'] == $id_usuario) {
                    echo json_encode([
                        'success' => true,
                        'mensaje' => 'Vista no registrada (es tu producto)'
                    ]);
                    exit;
                }
            }

            // Registrar vista
            $stmt = $conexion->prepare("
                INSERT INTO vistas_productos (id_producto, id_usuario, ip_visitante) 
                VALUES (:id_producto, :id_usuario, :ip_visitante)
            ");
            $stmt->bindParam(':id_producto', $id_producto);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':ip_visitante', $ip_visitante);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'mensaje' => 'Vista registrada'
            ]);
        } catch (PDOException $e) {
            // No es crÃ­tico si falla
            echo json_encode([
                'success' => false,
                'error' => 'Error al registrar vista'
            ]);
        }
        break;

    /**
     * OBTENER PRODUCTOS MÃS VISTOS (TENDENCIA)
     */
    case 'obtener_tendencias':
        $dias = $_GET['dias'] ?? 7;
        $limite = $_GET['limite'] ?? 4;

        try {
            $stmt = $conexion->prepare("
                SELECT 
                    p.id_producto,
                    p.tipo_producto,
                    p.precio,
                    COUNT(v.id_vista) as total_vistas,
                    (SELECT ruta_imagen FROM imagenes_productos WHERE id_producto = p.id_producto LIMIT 1) as imagen
                FROM productos p
                LEFT JOIN vistas_productos v ON p.id_producto = v.id_producto 
                    AND v.fecha_vista >= DATE_SUB(NOW(), INTERVAL :dias DAY)
                WHERE p.estado = 'disponible'
                GROUP BY p.id_producto
                ORDER BY total_vistas DESC, p.fecha_publicacion DESC
                LIMIT :limite
            ");
            $stmt->bindParam(':dias', $dias, PDO::PARAM_INT);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'productos' => $productos
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener tendencias: ' . $e->getMessage()]);
        }
        break;

    /**
     * OBTENER MIS PRODUCTOS MÃS VISTOS
     */
    case 'mis_productos_mas_vistos':
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(['error' => 'No autenticado']);
            exit;
        }

        $id_usuario = $_SESSION['id_usuario'];
        $limite = $_GET['limite'] ?? 5;

        try {
            $stmt = $conexion->prepare("
                SELECT 
                    p.id_producto,
                    p.tipo_producto,
                    p.precio,
                    COUNT(v.id_vista) as total_vistas,
                    (SELECT ruta_imagen FROM imagenes_productos WHERE id_producto = p.id_producto LIMIT 1) as imagen
                FROM productos p
                LEFT JOIN vistas_productos v ON p.id_producto = v.id_producto
                WHERE p.id_usuario = :id_usuario
                    AND p.estado = 'disponible'
                GROUP BY p.id_producto
                ORDER BY total_vistas DESC
                LIMIT :limite
            ");
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
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

    /**
     * OBTENER ESTADÃSTICAS DE VISTAS
     */
    case 'estadisticas_vistas':
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(['error' => 'No autenticado']);
            exit;
        }

        $id_usuario = $_SESSION['id_usuario'];

        try {
            // Total de vistas
            $stmt = $conexion->prepare("
                SELECT COUNT(*) as total_vistas
                FROM vistas_productos v
                INNER JOIN productos p ON v.id_producto = p.id_producto
                WHERE p.id_usuario = :id_usuario
            ");
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $total = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vistas hoy
            $stmt = $conexion->prepare("
                SELECT COUNT(*) as vistas_hoy
                FROM vistas_productos v
                INNER JOIN productos p ON v.id_producto = p.id_producto
                WHERE p.id_usuario = :id_usuario
                    AND DATE(v.fecha_vista) = CURDATE()
            ");
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $hoy = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vistas Ãºltimos 7 dÃ­as
            $stmt = $conexion->prepare("
                SELECT COUNT(*) as vistas_semana
                FROM vistas_productos v
                INNER JOIN productos p ON v.id_producto = p.id_producto
                WHERE p.id_usuario = :id_usuario
                    AND v.fecha_vista >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $semana = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'estadisticas' => [
                    'total_vistas' => (int)$total['total_vistas'],
                    'vistas_hoy' => (int)$hoy['vistas_hoy'],
                    'vistas_semana' => (int)$semana['vistas_semana']
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener estadÃ­sticas: ' . $e->getMessage()]);
        }
        break;

    /**
     * QUIÃ‰N HA VISTO MIS PRODUCTOS
     */
    case 'quien_vio_mis_productos':
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(['error' => 'No autenticado']);
            exit;
        }

        $id_usuario = $_SESSION['id_usuario'];
        $limite = $_GET['limite'] ?? 10;

        try {
            $stmt = $conexion->prepare("
                SELECT 
                    v.fecha_vista,
                    p.tipo_producto,
                    u.nombre as visitante,
                    CASE 
                        WHEN v.id_usuario IS NOT NULL THEN 'Usuario registrado'
                        ELSE 'Visitante anÃ³nimo'
                    END as tipo_visitante
                FROM vistas_productos v
                INNER JOIN productos p ON v.id_producto = p.id_producto
                LEFT JOIN usuarios u ON v.id_usuario = u.id_usuario
                WHERE p.id_usuario = :id_usuario
                ORDER BY v.fecha_vista DESC
                LIMIT :limite
            ");
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            $vistas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'vistas' => $vistas
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener vistas: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'AcciÃ³n no vÃ¡lida']);
        break;
}
