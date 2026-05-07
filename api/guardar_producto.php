<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - API: GUARDAR NUEVO PRODUCTO EN LA BD
 * Ruta: ascc/api/guardar_producto.php
 *
 * Segunda línea de defensa contra drogas:
 *   Aunque el JS ya bloqueó en el frontend, este endpoint
 *   vuelve a verificar en el servidor antes de insertar.
 *
 * Método: POST
 * Body JSON:
 *   { "nombre":"Tilapia Azul", "categoria":"peces",
 *     "subcategoria":"Tilapias", "lang":"es" }
 *
 * Respuesta JSON:
 *   { "ok": true,  "guardado": {...} }
 *   { "ok": true,  "ya_existe": true, "mensaje_es":"...", "mensaje_en":"..." }
 *   { "ok": false, "bloqueado": true, "mensaje_es":"...", "mensaje_en":"..." }
 *   { "ok": false, "mensaje": "Error..." }
 * ═══════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/palabras_bloqueadas.php';

/* ── Leer body JSON ─────────────────────────────────────── */
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

$nombre       = isset($body['nombre'])       ? trim($body['nombre'])       : '';
$categoria    = isset($body['categoria'])    ? trim($body['categoria'])    : '';
$subcategoria = isset($body['subcategoria']) ? trim($body['subcategoria']) : '';
$lang         = isset($body['lang'])         ? trim($body['lang'])         : 'es';

/* ── Validación de campos ───────────────────────────────── */
if (empty($nombre) || empty($categoria)) {
    echo json_encode([
        'ok'         => false,
        'mensaje_es' => 'El nombre y la categoría son obligatorios.',
        'mensaje_en' => 'Name and category are required.',
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════
   SEGUNDA LÍNEA DE DEFENSA — VALIDAR DROGAS EN SERVIDOR
   Aunque el JS ya lo bloqueó, siempre validamos en backend.
══════════════════════════════════════════════════════════ */
$bloqueo = verificarPalabrasBloqueadas($nombre);

if ($bloqueo['bloqueado']) {
    $cat = $bloqueo['categoria'];

    $mensajes = [
        'Cocaína y derivados'            => ['es' => '🚫 Producto no permitido. La venta de cocaína y sus derivados es ilegal en Colombia.',      'en' => '🚫 Product not allowed. The sale of cocaine and its derivatives is illegal in Colombia.'],
        'Cannabis y derivados'           => ['es' => '🚫 Producto no permitido. La venta de cannabis no autorizado es ilegal.',                   'en' => '🚫 Product not allowed. The sale of unauthorized cannabis is illegal.'],
        'Drogas sintéticas'              => ['es' => '🚫 Producto no permitido. Las drogas sintéticas están prohibidas en ASCC.',              'en' => '🚫 Product not allowed. Synthetic drugs are prohibited on ASCC.'],
        'Opioides'                       => ['es' => '🚫 Producto no permitido. La venta de opioides sin prescripción es ilegal.',                'en' => '🚫 Product not allowed. Selling opioids without a prescription is illegal.'],
        'Inhalantes'                     => ['es' => '🚫 Producto no permitido. La venta de inhalantes con fines recreativos está prohibida.',    'en' => '🚫 Product not allowed. Selling inhalants for recreational purposes is prohibited.'],
        'Nuevas sustancias psicoactivas' => ['es' => '🚫 Producto no permitido. Las nuevas sustancias psicoactivas están prohibidas en ASCC.', 'en' => '🚫 Product not allowed. New psychoactive substances are prohibited on ASCC.'],
    ];

    $msgDefault = ['es' => '🚫 Producto no permitido en ASCC.', 'en' => '🚫 Product not allowed on ASCC.'];
    $msg        = isset($mensajes[$cat]) ? $mensajes[$cat] : $msgDefault;

    echo json_encode([
        'ok'              => false,
        'bloqueado'       => true,
        'categoria_droga' => $cat,
        'mensaje_es'      => $msg['es'],
        'mensaje_en'      => $msg['en'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Normalizar nombre ──────────────────────────────────── */
function normalizarProducto(string $str): string
{
    $str = preg_replace('/\s+/', ' ', trim($str));
    $str = mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    $articulos = [' De ', ' Del ', ' La ', ' Los ', ' Las ', ' El ', ' Y ', ' En '];
    $minusc    = [' de ', ' del ', ' la ', ' los ', ' las ', ' el ', ' y ', ' en '];
    $str = str_replace($articulos, $minusc, $str);
    return ucfirst($str);
}

$nombre       = normalizarProducto($nombre);
$subcategoria = normalizarProducto($subcategoria);

/* ── Crear tabla si no existe ───────────────────────────── */
try {
    $conexion->exec("
        CREATE TABLE IF NOT EXISTS productos_custom (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            nombre       VARCHAR(200) NOT NULL,
            categoria    VARCHAR(100) NOT NULL,
            subcategoria VARCHAR(200) NOT NULL DEFAULT '',
            creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_prod (nombre, categoria, subcategoria)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) { /* ya existe */
}

/* ── Guardar en BD ──────────────────────────────────────── */
try {
    /* Verificar duplicado */
    $stmtCheck = $conexion->prepare("
        SELECT COUNT(*) FROM productos_custom
        WHERE nombre = :nombre AND categoria = :cat AND subcategoria = :subcat
    ");
    $stmtCheck->execute([':nombre' => $nombre, ':cat' => $categoria, ':subcat' => $subcategoria]);

    if ($stmtCheck->fetchColumn() > 0) {
        echo json_encode([
            'ok'         => true,
            'ya_existe'  => true,
            'mensaje_es' => '✅ Este producto ya estaba registrado en la base de datos.',
            'mensaje_en' => '✅ This product was already registered in the database.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* Insertar */
    $stmtIns = $conexion->prepare("
        INSERT INTO productos_custom (nombre, categoria, subcategoria)
        VALUES (:nombre, :cat, :subcat)
    ");
    $stmtIns->execute([':nombre' => $nombre, ':cat' => $categoria, ':subcat' => $subcategoria]);

    echo json_encode([
        'ok'         => true,
        'mensaje_es' => '✅ Producto guardado correctamente.',
        'mensaje_en' => '✅ Product saved successfully.',
        'guardado'   => [
            'nombre'       => $nombre,
            'categoria'    => $categoria,
            'subcategoria' => $subcategoria,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'ok'         => false,
        'mensaje_es' => 'Error al guardar: ' . $e->getMessage(),
        'mensaje_en' => 'Error saving: '     . $e->getMessage(),
    ]);
}
