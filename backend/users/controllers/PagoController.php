<?php
/**
 * Controlador de Pagos
 * Maneja el procesamiento de pagos con Wompi
 */

session_start();
require_once __DIR__ . "/../config/database.php";

// Permitir recibir JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Si es una petición JSON
if ($data && isset($data['accion'])) {
    
    if ($data['accion'] === 'registrar_pago') {
        
        $referencia = $data['referencia'];
        $id_producto = (int)$data['id_producto'];
        $cantidad = (int)$data['cantidad'];
        $total = (float)$data['total'];
        $estado = $data['estado'];
        $datos_wompi = json_encode($data['datos_wompi']);
        
        // Obtener información del producto
        $stmt = $conexion->prepare("SELECT * FROM productos WHERE id_producto = :id");
        $stmt->bindParam(":id", $id_producto);
        $stmt->execute();
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            $id_vendedor = $producto['id_usuario'];
            $id_comprador = $_SESSION["id_usuario"] ?? 0;
            $precio_unitario = $producto['precio'];
            
            // Insertar transacción
            $sql = "INSERT INTO transacciones 
                    (referencia, id_producto, id_comprador, id_vendedor, cantidad, precio_unitario, total, estado, metodo_pago, banco, datos_pago)
                    VALUES (:ref, :prod, :comp, :vend, :cant, :precio, :total, :estado, 'WOMPI', 'WOMPI', :datos)";
            
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(":ref", $referencia);
            $stmt->bindParam(":prod", $id_producto);
            $stmt->bindParam(":comp", $id_comprador);
            $stmt->bindParam(":vend", $id_vendedor);
            $stmt->bindParam(":cant", $cantidad);
            $stmt->bindParam(":precio", $precio_unitario);
            $stmt->bindParam(":total", $total);
            $stmt->bindParam(":estado", $estado);
            $stmt->bindParam(":datos", $datos_wompi);
            $stmt->execute();
            
            // Si el pago fue aprobado, actualizar stock
            if ($estado === 'APPROVED') {
                $nuevo_stock = $producto['cantidad'] - $cantidad;
                
                if ($nuevo_stock <= 0) {
                    // Marcar producto como vendido
                    $stmt = $conexion->prepare("UPDATE productos SET cantidad = 0, estado = 'vendido', fecha_venta = NOW() WHERE id_producto = :id");
                    $stmt->bindParam(":id", $id_producto);
                    $stmt->execute();
                } else {
                    // Actualizar stock
                    $stmt = $conexion->prepare("UPDATE productos SET cantidad = :stock WHERE id_producto = :id");
                    $stmt->bindParam(":stock", $nuevo_stock);
                    $stmt->bindParam(":id", $id_producto);
                    $stmt->execute();
                }
                
                // TODO: Enviar email al vendedor y comprador
                // TODO: Enviar notificación por WhatsApp (opcional)
            }
            
            echo json_encode(['success' => true, 'message' => 'Transacción registrada']);
            exit;
        }
    }
}

// Verificar que el usuario esté logueado
if (!isset($_SESSION["id_usuario"])) {
    die("❌ No autorizado. <a href='/ascc/frontend/users/views/auth/login.php'>Iniciar sesión</a>");
}

// Si no es JSON, redirigir
header("Location: /ascc/catalogo.php");
exit;
?>