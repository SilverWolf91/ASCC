<?php

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * PERFIL CONTROLLER
 * GestiÃ³n de perfil de usuario y foto
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
     * SUBIR FOTO DE PERFIL
     */
    case 'subir_foto':

        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'No se recibiÃ³ ninguna foto']);
            exit;
        }

        $foto = $_FILES['foto'];

        // Validar tipo de archivo
        $tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($foto['type'], $tipos_permitidos)) {
            echo json_encode(['error' => 'Tipo de archivo no permitido. Solo JPG, PNG, GIF o WEBP']);
            exit;
        }

        // Validar tamaÃ±o (max 5MB)
        if ($foto['size'] > 20 * 1024 * 1024) {
            echo json_encode(['error' => 'La foto no debe superar 5MB']);
            exit;
        }

        try {
            // Crear directorio si no existe
            $directorio_fotos = __DIR__ . '/../public/img/perfiles/';
            if (!file_exists($directorio_fotos)) {
                mkdir($directorio_fotos, 0755, true);
            }

            // Obtener foto anterior
            $stmt = $conexion->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = :id_usuario");
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            $foto_anterior = $usuario['foto_perfil'];

            // Generar nombre Ãºnico
            $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $nombre_archivo = 'perfil_' . $id_usuario . '_' . time() . '.' . $extension;
            $ruta_completa = $directorio_fotos . $nombre_archivo;
            $ruta_bd = 'img/perfiles/' . $nombre_archivo;

            // Mover archivo
            if (move_uploaded_file($foto['tmp_name'], $ruta_completa)) {

                // Actualizar en base de datos
                $stmt = $conexion->prepare("
                    UPDATE usuarios 
                    SET foto_perfil = :foto_perfil 
                    WHERE id_usuario = :id_usuario
                ");
                $stmt->bindParam(':foto_perfil', $ruta_bd);
                $stmt->bindParam(':id_usuario', $id_usuario);
                $stmt->execute();

                // Eliminar foto anterior si existe
                if ($foto_anterior && file_exists(__DIR__ . '/../public/' . $foto_anterior)) {
                    unlink(__DIR__ . '/../public/' . $foto_anterior);
                }

                echo json_encode([
                    'success' => true,
                    'ruta_foto' => '/ascc/frontend/users/public/' . $ruta_bd,
                    'mensaje' => 'Foto actualizada correctamente'
                ]);
            } else {
                echo json_encode(['error' => 'Error al guardar la foto']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
        }
        break;

    /**
     * OBTENER DATOS DEL PERFIL
     */
    case 'obtener_perfil':
        try {
            $stmt = $conexion->prepare("
                SELECT 
                    nombre,
                    cedula,
                    telefono,
                    codigo_pais,
                    email,
                    foto_perfil,
                    fecha_registro
                FROM usuarios 
                WHERE id_usuario = :id_usuario
            ");
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                echo json_encode([
                    'success' => true,
                    'usuario' => $usuario
                ]);
            } else {
                echo json_encode(['error' => 'Usuario no encontrado']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener perfil: ' . $e->getMessage()]);
        }
        break;

    /**
     * ACTUALIZAR DATOS DEL PERFIL
     */
    case 'actualizar_perfil':
        $nombre = trim($_POST['nombre'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $codigo_pais = trim($_POST['codigo_pais'] ?? 'CO');

        if (empty($nombre)) {
            echo json_encode(['error' => 'El nombre es obligatorio']);
            exit;
        }

        try {
            $stmt = $conexion->prepare("
                UPDATE usuarios 
                SET nombre = :nombre,
                    telefono = :telefono,
                    codigo_pais = :codigo_pais
                WHERE id_usuario = :id_usuario
            ");
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':codigo_pais', $codigo_pais);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();

            // Actualizar sesiÃ³n
            $_SESSION['nombre'] = $nombre;

            echo json_encode([
                'success' => true,
                'mensaje' => 'Perfil actualizado correctamente'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar perfil: ' . $e->getMessage()]);
        }
        break;

    /**
     * ELIMINAR FOTO DE PERFIL
     */
    case 'eliminar_foto':
        try {
            // Obtener foto actual
            $stmt = $conexion->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = :id_usuario");
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && $usuario['foto_perfil']) {
                // Eliminar archivo
                $ruta_archivo = __DIR__ . '/../public/' . $usuario['foto_perfil'];
                if (file_exists($ruta_archivo)) {
                    unlink($ruta_archivo);
                }

                // Actualizar BD
                $stmt = $conexion->prepare("
                    UPDATE usuarios 
                    SET foto_perfil = NULL 
                    WHERE id_usuario = :id_usuario
                ");
                $stmt->bindParam(':id_usuario', $id_usuario);
                $stmt->execute();

                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Foto eliminada correctamente'
                ]);
            } else {
                echo json_encode(['error' => 'No hay foto para eliminar']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar foto: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'AcciÃ³n no vÃ¡lida']);
        break;
}
