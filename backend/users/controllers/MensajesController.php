<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * MENSAJES CONTROLLER - CORREGIDO
 * GestiÃ³n completa del sistema de chat
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */

session_start();
require_once __DIR__ . "/../config/database.php";

// Verificar autenticaciÃ³n
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

header('Content-Type: application/json');

$id_usuario = $_SESSION['id_usuario'];
$accion = $_POST['accion'] ?? $_GET['accion'] ?? $_GET['action'] ?? '';

switch ($accion) {

    /**
     * ENVIAR MENSAJE DIRECTO DESDE PRODUCTO
     */
    case 'enviar':
        // Obtener datos JSON del body
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $id_destinatario = (int)($data['id_destinatario'] ?? 0);
        $id_producto = (int)($data['id_producto'] ?? 0);
        $mensaje = trim($data['mensaje'] ?? '');

        if (!$id_destinatario || !$id_producto || empty($mensaje)) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos incompletos'
            ]);
            exit;
        }

        if (strlen($mensaje) < 10) {
            echo json_encode([
                'success' => false,
                'message' => 'El mensaje debe tener al menos 10 caracteres'
            ]);
            exit;
        }

        if ($id_destinatario == $id_usuario) {
            echo json_encode([
                'success' => false,
                'message' => 'No puedes enviarte mensajes a ti mismo'
            ]);
            exit;
        }

        try {
            // Verificar que el producto existe y obtener el vendedor
            $stmt = $conexion->prepare("
                SELECT id_usuario as id_vendedor
                FROM productos
                WHERE id_producto = :id_producto
            ");
            $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
            $stmt->execute();
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ]);
                exit;
            }

            $id_vendedor = (int)$producto['id_vendedor'];
            $id_comprador = $id_usuario;

            // Si el usuario actual es el vendedor, intercambiar roles
            if ($id_usuario == $id_vendedor) {
                $id_vendedor = $id_destinatario;
                $id_comprador = $id_usuario;
            }

            // Buscar o crear conversaciÃ³n
            $stmt = $conexion->prepare("
                SELECT id_conversacion
                FROM conversaciones
                WHERE id_producto = :id_producto
                    AND id_comprador = :id_comprador
                    AND id_vendedor = :id_vendedor
            ");
            $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
            $stmt->bindParam(':id_comprador', $id_comprador, PDO::PARAM_INT);
            $stmt->bindParam(':id_vendedor', $id_vendedor, PDO::PARAM_INT);
            $stmt->execute();
            $conversacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($conversacion) {
                // Ya existe la conversaciÃ³n
                $id_conversacion = (int)$conversacion['id_conversacion'];
            } else {
                // Crear nueva conversaciÃ³n
                $stmt = $conexion->prepare("
                    INSERT INTO conversaciones (id_producto, id_comprador, id_vendedor)
                    VALUES (:id_producto, :id_comprador, :id_vendedor)
                ");
                $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
                $stmt->bindParam(':id_comprador', $id_comprador, PDO::PARAM_INT);
                $stmt->bindParam(':id_vendedor', $id_vendedor, PDO::PARAM_INT);
                $stmt->execute();

                $id_conversacion = (int)$conexion->lastInsertId();
            }

            // Insertar mensaje
            $stmt = $conexion->prepare("
                INSERT INTO mensajes (id_conversacion, id_remitente, mensaje)
                VALUES (:id_conversacion, :id_remitente, :mensaje)
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_remitente', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':mensaje', $mensaje, PDO::PARAM_STR);
            $stmt->execute();

            // Actualizar timestamp de conversaciÃ³n
            $stmt = $conexion->prepare("
                UPDATE conversaciones
                SET ultima_actualizacion = NOW()
                WHERE id_conversacion = :id_conversacion
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Mensaje enviado correctamente',
                'id_conversacion' => $id_conversacion
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al enviar mensaje: ' . $e->getMessage()
            ]);
        }
        break;

    /**
     * OBTENER CONVERSACIONES
     */
    case 'obtener_conversaciones':
    case 'conversaciones':
        try {
            $stmt = $conexion->prepare("
                SELECT 
                    c.id_conversacion,
                    c.id_producto,
                    c.ultima_actualizacion,
                    p.tipo_producto,
                    p.precio,
                    (SELECT ruta_imagen FROM imagenes_productos WHERE id_producto = p.id_producto LIMIT 1) as imagen_producto,
                    CASE 
                        WHEN c.id_comprador = :id_usuario1 THEN c.id_vendedor
                        ELSE c.id_comprador
                    END as id_otro_usuario,
                    CASE 
                        WHEN c.id_comprador = :id_usuario2 THEN u_vendedor.nombre
                        ELSE u_comprador.nombre
                    END as nombre_otro_usuario,
                    CASE 
                        WHEN c.id_comprador = :id_usuario3 THEN u_vendedor.foto_perfil
                        ELSE u_comprador.foto_perfil
                    END as foto_otro_usuario,
                    (SELECT mensaje FROM mensajes WHERE id_conversacion = c.id_conversacion ORDER BY fecha_envio DESC LIMIT 1) as ultimo_mensaje,
                    (SELECT fecha_envio FROM mensajes WHERE id_conversacion = c.id_conversacion ORDER BY fecha_envio DESC LIMIT 1) as fecha_ultimo_mensaje,
                    (SELECT COUNT(*) FROM mensajes WHERE id_conversacion = c.id_conversacion AND id_remitente != :id_usuario4 AND leido = 0) as mensajes_no_leidos
                FROM conversaciones c
                INNER JOIN productos p ON c.id_producto = p.id_producto
                LEFT JOIN usuarios u_vendedor ON c.id_vendedor = u_vendedor.id_usuario
                LEFT JOIN usuarios u_comprador ON c.id_comprador = u_comprador.id_usuario
                WHERE (c.id_comprador = :id_usuario5 OR c.id_vendedor = :id_usuario6)
                    AND NOT (
                        (c.id_comprador = :id_usuario7 AND c.borrado_por_comprador = 1)
                        OR (c.id_vendedor = :id_usuario8 AND c.borrado_por_vendedor = 1)
                    )
                ORDER BY c.ultima_actualizacion DESC
            ");
            $stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario3', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario4', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario5', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario6', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario7', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario8', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();
            $conversaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'conversaciones' => $conversaciones
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    /**
     * OBTENER MENSAJES DE UNA CONVERSACIÃ“N
     */
    case 'obtener_mensajes':
    case 'mensajes':
        $id_conversacion = (int)($_GET['id_conversacion'] ?? 0);

        if (!$id_conversacion) {
            echo json_encode(['success' => false, 'message' => 'ConversaciÃ³n no especificada']);
            exit;
        }

        try {
            // Verificar acceso y obtener info de la conversaciÃ³n
            $stmt = $conexion->prepare("
                SELECT 
                    c.*,
                    p.tipo_producto,
                    p.precio,
                    p.cantidad,
                    p.unidad,
                    (SELECT ruta_imagen FROM imagenes_productos WHERE id_producto = p.id_producto LIMIT 1) as imagen
                FROM conversaciones c
                INNER JOIN productos p ON c.id_producto = p.id_producto
                WHERE c.id_conversacion = :id_conversacion
                    AND (c.id_comprador = :id_usuario1 OR c.id_vendedor = :id_usuario2)
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            $conversacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$conversacion) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }

            // Obtener mensajes (excluyendo los borrados por este usuario)
            $stmt = $conexion->prepare("
                SELECT 
                    m.id_mensaje,
                    m.id_remitente,
                    m.mensaje,
                    m.fecha_envio,
                    m.leido,
                    u.nombre as nombre_remitente
                FROM mensajes m
                INNER JOIN usuarios u ON m.id_remitente = u.id_usuario
                WHERE m.id_conversacion = :id_conversacion
                    AND NOT (
                        (m.id_remitente = :id_usuario1 AND m.borrado_por_remitente = 1)
                        OR (m.id_remitente != :id_usuario2 AND m.borrado_por_destinatario = 1)
                    )
                ORDER BY m.fecha_envio ASC
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();
            $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Marcar como leÃ­dos
            $stmt = $conexion->prepare("
                UPDATE mensajes 
                SET leido = 1
                WHERE id_conversacion = :id_conversacion 
                    AND id_remitente != :id_usuario 
                    AND leido = 0
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            // Preparar info del producto
            $producto = [
                'tipo_producto' => $conversacion['tipo_producto'],
                'precio' => $conversacion['precio'],
                'cantidad' => $conversacion['cantidad'],
                'unidad' => $conversacion['unidad'],
                'imagen' => $conversacion['imagen']
            ];

            // Determinar si es vendedor
            $es_vendedor = ($conversacion['id_vendedor'] == $id_usuario);

            echo json_encode([
                'success' => true,
                'mensajes' => $mensajes,
                'producto' => $producto,
                'es_vendedor' => $es_vendedor
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    /**
     * ENVIAR MENSAJE EN CONVERSACIÃ“N EXISTENTE
     */
    case 'enviar_mensaje':
        $id_conversacion = (int)($_POST['id_conversacion'] ?? 0);
        $mensaje = trim($_POST['mensaje'] ?? '');

        if (!$id_conversacion || empty($mensaje)) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        try {
            // Verificar acceso
            $stmt = $conexion->prepare("
                SELECT * FROM conversaciones 
                WHERE id_conversacion = :id_conversacion
                    AND (id_comprador = :id_usuario1 OR id_vendedor = :id_usuario2)
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit;
            }

            // Insertar mensaje
            $stmt = $conexion->prepare("
                INSERT INTO mensajes (id_conversacion, id_remitente, mensaje)
                VALUES (:id_conversacion, :id_remitente, :mensaje)
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_remitente', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':mensaje', $mensaje, PDO::PARAM_STR);
            $stmt->execute();

            $id_mensaje = $conexion->lastInsertId();

            // Actualizar timestamp de conversaciÃ³n
            $stmt = $conexion->prepare("
                UPDATE conversaciones
                SET ultima_actualizacion = NOW()
                WHERE id_conversacion = :id_conversacion
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'id_mensaje' => $id_mensaje,
                'fecha_envio' => date('Y-m-d H:i:s')
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al enviar mensaje: ' . $e->getMessage()]);
        }
        break;

    /**
     * LIMPIAR CONVERSACIÃ“N (BORRADO SUAVE - SOLO PARA QUIEN BORRA)
     */
    case 'limpiar_conversacion':
        $id_conversacion = (int)($_POST['id_conversacion'] ?? 0);

        if (!$id_conversacion) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        try {
            // Verificar acceso y determinar rol
            $stmt = $conexion->prepare("
                SELECT 
                    id_comprador,
                    id_vendedor
                FROM conversaciones 
                WHERE id_conversacion = :id_conversacion
                    AND (id_comprador = :id_usuario1 OR id_vendedor = :id_usuario2)
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            $conversacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$conversacion) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit;
            }

            // Determinar si es remitente o destinatario y marcar mensajes como borrados
            // Los mensajes enviados POR el usuario â†’ marcar borrado_por_remitente = 1
            // Los mensajes recibidos POR el usuario â†’ marcar borrado_por_destinatario = 1

            // Marcar como borrados los mensajes ENVIADOS por este usuario
            $stmt = $conexion->prepare("
                UPDATE mensajes 
                SET borrado_por_remitente = 1
                WHERE id_conversacion = :id_conversacion 
                    AND id_remitente = :id_usuario
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            // Marcar como borrados los mensajes RECIBIDOS por este usuario
            $stmt = $conexion->prepare("
                UPDATE mensajes 
                SET borrado_por_destinatario = 1
                WHERE id_conversacion = :id_conversacion 
                    AND id_remitente != :id_usuario
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Mensajes eliminados (solo para ti)']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al limpiar: ' . $e->getMessage()]);
        }
        break;

    /**
     * ELIMINAR CONVERSACIÃ“N COMPLETA (BORRADO SUAVE - SOLO PARA QUIEN ELIMINA)
     */
    case 'eliminar_conversacion':
        $id_conversacion = (int)($_POST['id_conversacion'] ?? 0);

        if (!$id_conversacion) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        try {
            // Verificar acceso y determinar rol
            $stmt = $conexion->prepare("
                SELECT 
                    id_comprador,
                    id_vendedor
                FROM conversaciones 
                WHERE id_conversacion = :id_conversacion
                    AND (id_comprador = :id_usuario1 OR id_vendedor = :id_usuario2)
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            $conversacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$conversacion) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit;
            }

            // Determinar si es comprador o vendedor y marcar la conversaciÃ³n como borrada
            if ($conversacion['id_comprador'] == $id_usuario) {
                // Es el comprador, marcar borrado_por_comprador
                $stmt = $conexion->prepare("
                    UPDATE conversaciones
                    SET borrado_por_comprador = 1
                    WHERE id_conversacion = :id_conversacion
                ");
            } else {
                // Es el vendedor, marcar borrado_por_vendedor
                $stmt = $conexion->prepare("
                    UPDATE conversaciones
                    SET borrado_por_vendedor = 1
                    WHERE id_conversacion = :id_conversacion
                ");
            }

            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->execute();

            // TambiÃ©n marcar todos los mensajes como borrados para este usuario
            // Mensajes enviados
            $stmt = $conexion->prepare("
                UPDATE mensajes 
                SET borrado_por_remitente = 1
                WHERE id_conversacion = :id_conversacion 
                    AND id_remitente = :id_usuario
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            // Mensajes recibidos
            $stmt = $conexion->prepare("
                UPDATE mensajes 
                SET borrado_por_destinatario = 1
                WHERE id_conversacion = :id_conversacion 
                    AND id_remitente != :id_usuario
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => 'ConversaciÃ³n eliminada (solo para ti)']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al eliminar: ' . $e->getMessage()]);
        }
        break;

    /**
     * OBTENER SOLO MENSAJES NUEVOS (PARA POLLING)
     */
    case 'obtener_mensajes_nuevos':
        $id_conversacion = (int)($_GET['id_conversacion'] ?? 0);
        $ultimo_id = (int)($_GET['ultimo_id'] ?? 0);

        if (!$id_conversacion) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        try {
            // Verificar acceso
            $stmt = $conexion->prepare("
                SELECT * FROM conversaciones 
                WHERE id_conversacion = :id_conversacion
                    AND (id_comprador = :id_usuario1 OR id_vendedor = :id_usuario2)
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit;
            }

            // Obtener solo mensajes nuevos (con ID mayor al Ãºltimo conocido, excluyendo borrados)
            $stmt = $conexion->prepare("
                SELECT 
                    m.id_mensaje,
                    m.id_remitente,
                    m.mensaje,
                    m.fecha_envio,
                    m.leido,
                    u.nombre as nombre_remitente
                FROM mensajes m
                INNER JOIN usuarios u ON m.id_remitente = u.id_usuario
                WHERE m.id_conversacion = :id_conversacion
                    AND m.id_mensaje > :ultimo_id
                    AND NOT (
                        (m.id_remitente = :id_usuario1 AND m.borrado_por_remitente = 1)
                        OR (m.id_remitente != :id_usuario2 AND m.borrado_por_destinatario = 1)
                    )
                ORDER BY m.fecha_envio ASC
            ");
            $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $stmt->bindParam(':ultimo_id', $ultimo_id, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
            $stmt->execute();
            $mensajes_nuevos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Marcar como leÃ­dos los mensajes nuevos que no son del usuario actual
            if (count($mensajes_nuevos) > 0) {
                $stmt = $conexion->prepare("
                    UPDATE mensajes 
                    SET leido = 1
                    WHERE id_conversacion = :id_conversacion 
                        AND id_remitente != :id_usuario 
                        AND leido = 0
                        AND id_mensaje > :ultimo_id
                ");
                $stmt->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
                $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
                $stmt->bindParam(':ultimo_id', $ultimo_id, PDO::PARAM_INT);
                $stmt->execute();
            }

            echo json_encode([
                'success' => true,
                'mensajes_nuevos' => $mensajes_nuevos,
                'total' => count($mensajes_nuevos)
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'AcciÃ³n no vÃ¡lida']);
        break;
}
