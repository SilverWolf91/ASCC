<?php

/**
 * UPLOAD FOTO DE PERFIL - ASCC
 * Ruta: /ASCC/upload_photo.php
 */

// MODO DEBUG - Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Limpiar cualquier output previo
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

session_start();

// Verificar autenticación
if (!isset($_SESSION['id_usuario'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'No autenticado']));
}

// Incluir conexión a base de datos
require_once __DIR__ . "/../../../backend/users/config/database.php";

// Verificar que existe la variable $conexion
if (!isset($conexion)) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'Error de conexión a base de datos']));
}

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Método no permitido']));
}

// Verificar que venga un archivo
if (!isset($_FILES['foto_perfil'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'No se recibió el campo foto_perfil']));
}

if ($_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = 'Error al subir: ';
    switch ($_FILES['foto_perfil']['error']) {
        case UPLOAD_ERR_INI_SIZE:
            $error_msg .= 'El archivo excede upload_max_filesize en php.ini';
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $error_msg .= 'El archivo excede MAX_FILE_SIZE';
            break;
        case UPLOAD_ERR_PARTIAL:
            $error_msg .= 'El archivo se subió parcialmente';
            break;
        case UPLOAD_ERR_NO_FILE:
            $error_msg .= 'No se subió ningún archivo';
            break;
        default:
            $error_msg .= 'Error desconocido (' . $_FILES['foto_perfil']['error'] . ')';
    }
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => $error_msg]));
}

$id_usuario = $_SESSION['id_usuario'];
$archivo = $_FILES['foto_perfil'];

// VALIDACIONES
$extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp'];
$tamano_maximo = 20 * 1024 * 1024; // 5MB

// Obtener extensión
$nombre_archivo = $archivo['name'];
$extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));

// Validar extensión
if (!in_array($extension, $extensiones_permitidas)) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'Formato no permitido. Solo JPG, PNG o WebP']));
}

// Validar tamaño
if ($archivo['size'] > $tamano_maximo) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'La imagen es muy grande. Máximo 5MB']));
}

// Validar que sea una imagen real
$imagen_info = @getimagesize($archivo['tmp_name']);
if ($imagen_info === false) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'El archivo no es una imagen válida']));
}

// PROCESAR IMAGEN
try {
    // Crear imagen desde el archivo
    $imagen_original = null;
    switch ($imagen_info['mime']) {
        case 'image/jpeg':
            $imagen_original = @imagecreatefromjpeg($archivo['tmp_name']);
            break;
        case 'image/png':
            $imagen_original = @imagecreatefrompng($archivo['tmp_name']);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $imagen_original = @imagecreatefromwebp($archivo['tmp_name']);
            } else {
                throw new Exception('WebP no soportado en este servidor');
            }
            break;
        default:
            throw new Exception('Formato no soportado: ' . $imagen_info['mime']);
    }

    if (!$imagen_original) {
        throw new Exception('Error al procesar la imagen');
    }

    // Obtener dimensiones originales
    $ancho_original = imagesx($imagen_original);
    $alto_original = imagesy($imagen_original);

    // Tamaño final (cuadrado, alta calidad)
    $tamano_final = 400;

    // Calcular dimensiones para recorte centrado
    if ($ancho_original > $alto_original) {
        // Imagen horizontal - recortar lados
        $nuevo_ancho = $alto_original;
        $nuevo_alto = $alto_original;
        $origen_x = ($ancho_original - $alto_original) / 2;
        $origen_y = 0;
    } else {
        // Imagen vertical o cuadrada - recortar arriba/abajo
        $nuevo_ancho = $ancho_original;
        $nuevo_alto = $ancho_original;
        $origen_x = 0;
        $origen_y = ($alto_original - $ancho_original) / 2;
    }

    // Crear imagen cuadrada redimensionada
    $imagen_final = imagecreatetruecolor($tamano_final, $tamano_final);

    if (!$imagen_final) {
        throw new Exception('Error al crear imagen final');
    }

    // Preservar transparencia para PNG
    if ($extension === 'png') {
        imagealphablending($imagen_final, false);
        imagesavealpha($imagen_final, true);
        $transparente = imagecolorallocatealpha($imagen_final, 0, 0, 0, 127);
        imagefill($imagen_final, 0, 0, $transparente);
    }

    // Redimensionar y recortar al centro
    $resultado = imagecopyresampled(
        $imagen_final,
        $imagen_original,
        0,
        0,
        $origen_x,
        $origen_y,
        $tamano_final,
        $tamano_final,
        $nuevo_ancho,
        $nuevo_alto
    );

    if (!$resultado) {
        throw new Exception('Error al redimensionar imagen');
    }

    // Generar nombre único
    $nombre_nuevo = 'perfil_' . $id_usuario . '_' . time() . '.jpg';

    // Ruta del directorio (crear carpeta profiles dentro de uploads)
    $ruta_directorio = __DIR__ . '/../../../public/uploads/profiles/';
    $ruta_completa = $ruta_directorio . $nombre_nuevo;

    // Crear directorio si no existe
    if (!is_dir($ruta_directorio)) {
        if (!mkdir($ruta_directorio, 0755, true)) {
            throw new Exception('No se pudo crear la carpeta. Crea manualmente: /public/uploads/profiles/');
        }
    }

    // Verificar permisos de escritura
    if (!is_writable($ruta_directorio)) {
        throw new Exception('Sin permisos de escritura. Da click derecho en la carpeta > Propiedades > Seguridad > Permitir escritura');
    }

    // Obtener foto anterior del usuario
    $stmt = $conexion->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = :id_usuario");
    $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Eliminar foto anterior si existe
    if ($usuario && !empty($usuario['foto_perfil'])) {
        $foto_anterior = __DIR__ . '/../../../public/' . $usuario['foto_perfil'];
        if (file_exists($foto_anterior)) {
            @unlink($foto_anterior);
        }
    }

    // Guardar nueva imagen (calidad 90%)
    if (!imagejpeg($imagen_final, $ruta_completa, 90)) {
        throw new Exception('Error al guardar la imagen');
    }

    // Liberar memoria
    imagedestroy($imagen_original);
    imagedestroy($imagen_final);

    // Actualizar base de datos
    $ruta_bd = 'uploads/profiles/' . $nombre_nuevo;
    $stmt = $conexion->prepare("UPDATE usuarios SET foto_perfil = :foto_perfil WHERE id_usuario = :id_usuario");
    $stmt->bindParam(':foto_perfil', $ruta_bd, PDO::PARAM_STR);
    $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);

    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar la base de datos');
    }

    // Respuesta exitosa
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Foto actualizada correctamente',
        'foto_url' => '/ascc/public/' . $ruta_bd
    ]);
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}