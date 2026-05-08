<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - IMAGE HELPER
 * Archivo : config/image_helper.php
 * Responsabilidad : Subir imágenes a ImgBB y renderizar URLs
 * ═══════════════════════════════════════════════════════════
 */

/**
 * Sube una imagen a ImgBB usando su API REST.
 * 
 * @param string $ruta_temporal Ruta temporal del archivo ($_FILES['tmp_name'])
 * @return string|null URL de la imagen en ImgBB o null si falla.
 */
function subirImagenImgBB($ruta_temporal) {
    $api_key = getenv('IMGBB_API_KEY');
    
    if (!$api_key) {
        error_log("[ImgBB] Error: IMGBB_API_KEY no está configurada en el entorno.");
        return null;
    }

    if (!file_exists($ruta_temporal)) {
        error_log("[ImgBB] Error: El archivo temporal no existe.");
        return null;
    }

    $image = base64_encode(file_get_contents($ruta_temporal));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Usar un array forza a cURL a enviarlo como multipart/form-data, 
    // ideal para imágenes grandes codificadas en base64.
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'key' => $api_key,
        'image' => $image
    ]);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        error_log("[ImgBB] cURL Error: " . $err);
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['success']) && $data['success'] === true) {
        // Retorna la URL directa de la imagen (ej: https://i.ibb.co/...)
        return $data['data']['url'];
    } else {
        error_log("[ImgBB] API Error: " . print_r($data, true));
        return null;
    }
}

/**
 * Devuelve la URL correcta para mostrar una imagen en el frontend.
 * Detecta si es una ruta local (antigua) o una URL externa (ImgBB).
 * 
 * @param string|null $path Ruta guardada en la base de datos
 * @return string URL absoluta o relativa correcta
 */
function getImageUrl($path) {
    if (empty($path)) {
        return '/ascc/public/img/no-image.png';
    }
    // Si ya es una URL externa, se retorna tal cual
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    // Si es local, se le agrega el prefijo público del proyecto
    return '/ascc/public/' . ltrim($path, '/');
}
