<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - API: GUARDAR NUEVA UBICACIÓN EN LA BD
 * Ruta: ascc/api/guardar_ubicacion.php
 *
 * Cuando un usuario escribe un municipio o vereda que NO
 * existe en la BD, este endpoint lo registra para que
 * futuros usuarios lo encuentren con el autocompletado.
 *
 * FLUJO SEGURO SIN DUPLICADOS:
 *   El ProductoController.php ya hace SELECT antes de INSERT.
 *   Si este endpoint guardó primero, el controlador encuentra
 *   el registro y solo actualiza las coordenadas. ✓
 *   Si no guardó antes, el controlador lo crea con lat/lng. ✓
 *   En ningún caso hay duplicados.
 *
 * Texto normalizado antes de guardar:
 *   "el guamal" → "El Guamal"
 *   "la  chorrera" → "La Chorrera"
 *
 * Método: POST
 * Body JSON:
 *   { "departamento":"Boyacá", "municipio":"El Guamal", "vereda":"La Chorrera" }
 *
 * Respuesta JSON:
 *   { "ok": true,  "mensaje": "Guardado", "guardado": {...} }
 *   { "ok": true,  "mensaje": "Ya existe" }
 *   { "ok": false, "mensaje": "Error..." }
 * ═══════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

// Ruta correcta: ascc/api/ → un nivel arriba → ascc/config/
require_once __DIR__ . '/../config/database.php';

/* ── Leer body JSON (o POST normal como fallback) ───────── */
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

$departamento = isset($body['departamento']) ? trim($body['departamento']) : '';
$municipio    = isset($body['municipio'])    ? trim($body['municipio'])    : '';
$vereda       = isset($body['vereda'])       ? trim($body['vereda'])       : '';

/* Coordenadas opcionales: si el usuario ya posicionó el marcador en el
   mapa, guardamos esas coords con la nueva fila para que futuras
   búsquedas devuelvan el punto exacto sin depender de Google Geocoder. */
$lat = isset($body['lat']) && is_numeric($body['lat']) ? (float) $body['lat'] : null;
$lng = isset($body['lng']) && is_numeric($body['lng']) ? (float) $body['lng'] : null;

/* Validación geográfica: Colombia ≈ lat[-4.5, 13.0] lng[-79.5, -66.5] */
if ($lat !== null && ($lat < -4.5 || $lat > 13.0)) { $lat = null; }
if ($lng !== null && ($lng < -79.5 || $lng > -66.5)) { $lng = null; }
if ($lat === null || $lng === null) { $lat = null; $lng = null; }

/* ── Validación ─────────────────────────────────────────── */
if (empty($departamento) || empty($municipio)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Departamento y municipio son obligatorios']);
    exit;
}

/* ── Normalizar: "el guamal" → "El Guamal" ──────────────── */
function normalizarNombre($str)
{
    // Eliminar espacios múltiples
    $str = preg_replace('/\s+/', ' ', trim($str));
    // Primera letra de cada palabra en mayúscula
    $str = mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    // Artículos en minúscula (excepto si es la primera palabra)
    $articulos = [' De ', ' Del ', ' La ', ' Los ', ' Las ', ' El ', ' Y ', ' En ', ' Del '];
    $minusc    = [' de ', ' del ', ' la ', ' los ', ' las ', ' el ', ' y ', ' en ', ' del '];
    $str = str_replace($articulos, $minusc, $str);
    // La primera letra siempre en mayúscula
    return ucfirst($str);
}

$departamento = normalizarNombre($departamento);
$municipio    = normalizarNombre($municipio);
if (!empty($vereda)) {
    $vereda = normalizarNombre($vereda);
}

/* ── Guardar en BD ──────────────────────────────────────── */
try {
    if (!empty($vereda)) {
        /* Guardar municipio + vereda nueva */

        // Verificar que no exista ya
        $stmtCheck = $conexion->prepare("
            SELECT COUNT(*) FROM ubicaciones
            WHERE departamento = :depto
              AND municipio    = :muni
              AND vereda       = :vereda
        ");
        $stmtCheck->execute([
            ':depto'  => $departamento,
            ':muni'   => $municipio,
            ':vereda' => $vereda
        ]);

        if ($stmtCheck->fetchColumn() > 0) {
            /* Si ya existe pero sin coords y ahora nos llegan coords válidas,
               actualizar la fila para enriquecer el dato. */
            if ($lat !== null && $lng !== null) {
                $stmtUpd = $conexion->prepare("
                    UPDATE ubicaciones
                       SET lat = :lat, lng = :lng
                     WHERE departamento = :depto
                       AND municipio    = :muni
                       AND vereda       = :vereda
                       AND lat IS NULL
                       AND lng IS NULL
                ");
                $stmtUpd->execute([
                    ':lat'    => $lat,
                    ':lng'    => $lng,
                    ':depto'  => $departamento,
                    ':muni'   => $municipio,
                    ':vereda' => $vereda
                ]);
            }
            echo json_encode([
                'ok'       => true,
                'mensaje'  => 'Ya existe en la base de datos',
                'ya_existe' => true
            ]);
            exit;
        }

        // Insertar nueva fila con coords del marcador si las hay
        $stmtIns = $conexion->prepare("
            INSERT INTO ubicaciones (departamento, municipio, vereda, lat, lng)
            VALUES (:depto, :muni, :vereda, :lat, :lng)
        ");
        $stmtIns->execute([
            ':depto'  => $departamento,
            ':muni'   => $municipio,
            ':vereda' => $vereda,
            ':lat'    => $lat,
            ':lng'    => $lng
        ]);

        echo json_encode([
            'ok'      => true,
            'mensaje' => 'Vereda guardada correctamente',
            'guardado' => [
                'departamento' => $departamento,
                'municipio'    => $municipio,
                'vereda'       => $vereda
            ]
        ]);
    } else {
        /* Guardar solo municipio nuevo (sin vereda específica) */

        $stmtCheck = $conexion->prepare("
            SELECT COUNT(*) FROM ubicaciones
            WHERE departamento = :depto
              AND municipio    = :muni
        ");
        $stmtCheck->execute([
            ':depto' => $departamento,
            ':muni'  => $municipio
        ]);

        if ($stmtCheck->fetchColumn() > 0) {
            /* Si ya existe el municipio pero la fila 'Centro' está sin coords
               y ahora tenemos coords válidas, enriquecerla. */
            if ($lat !== null && $lng !== null) {
                $stmtUpd = $conexion->prepare("
                    UPDATE ubicaciones
                       SET lat = :lat, lng = :lng
                     WHERE departamento = :depto
                       AND municipio    = :muni
                       AND vereda       = 'Centro'
                       AND lat IS NULL
                       AND lng IS NULL
                    LIMIT 1
                ");
                $stmtUpd->execute([
                    ':lat'   => $lat,
                    ':lng'   => $lng,
                    ':depto' => $departamento,
                    ':muni'  => $municipio
                ]);
            }
            echo json_encode([
                'ok'       => true,
                'mensaje'  => 'Municipio ya existe en la base de datos',
                'ya_existe' => true
            ]);
            exit;
        }

        // Insertar con vereda "Centro" como placeholder y las coords del marcador (si las hay)
        $stmtIns = $conexion->prepare("
            INSERT INTO ubicaciones (departamento, municipio, vereda, lat, lng)
            VALUES (:depto, :muni, 'Centro', :lat, :lng)
        ");
        $stmtIns->execute([
            ':depto' => $departamento,
            ':muni'  => $municipio,
            ':lat'   => $lat,
            ':lng'   => $lng
        ]);

        echo json_encode([
            'ok'      => true,
            'mensaje' => 'Municipio guardado correctamente',
            'guardado' => [
                'departamento' => $departamento,
                'municipio'    => $municipio
            ]
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'ok'     => false,
        'mensaje' => 'Error al guardar: ' . $e->getMessage()
    ]);
}