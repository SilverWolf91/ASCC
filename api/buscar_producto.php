<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - API: BUSCAR PRODUCTOS SIMILARES (BÚSQUEDA GLOBAL)
 * Ruta: ascc/api/buscar_producto.php
 *
 * Búsqueda inteligente en TODA la tabla productos_custom:
 *   1. Verifica primero si el texto contiene drogas → bloquea
 *   2. Busca en toda la BD (no solo la subcategoría actual)
 *   3. Si encuentra en otra categoría/subcategoría → lo indica
 *   4. Algoritmos: LIKE + similar_text + levenshtein
 *
 * Parámetros GET:
 *   texto        → lo que escribió el usuario
 *   categoria    → categoría actual del campesino
 *   subcategoria → subcategoría actual del campesino
 *   lang         → 'es' | 'en' (para mensajes bilingües)
 *
 * Respuesta JSON:
 *
 *   // Producto bloqueado (droga)
 *   { "bloqueado": true, "categoria_droga": "Cocaína y derivados",
 *     "mensaje_es": "...", "mensaje_en": "..." }
 *
 *   // Sugerencias encontradas
 *   [{ "valor": "Arawana", "similitud": 92,
 *      "misma_categoria": false,
 *      "categoria_original": "peces",
 *      "subcategoria_original": "Especies Amazónicas y Orinoquía",
 *      "mensaje_es": "Ya existe en Especies Amazónicas y Orinoquía",
 *      "mensaje_en": "Already exists in Amazonian and Orinoco Species" }]
 * ═══════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/palabras_bloqueadas.php';

/* ── Parámetros de entrada ──────────────────────────────── */
$texto           = isset($_GET['texto'])        ? trim($_GET['texto'])        : '';
$categoriaActual = isset($_GET['categoria'])    ? trim($_GET['categoria'])    : '';
$subcatActual    = isset($_GET['subcategoria']) ? trim($_GET['subcategoria']) : '';
$lang            = isset($_GET['lang'])         ? trim($_GET['lang'])         : 'es';

/* ── Validación básica ──────────────────────────────────── */
if (strlen($texto) < 2) {
    echo json_encode([]);
    exit;
}

/* ══════════════════════════════════════════════════════════
   PASO 1 — VERIFICAR DROGAS ANTES DE CUALQUIER OTRA COSA
══════════════════════════════════════════════════════════ */
$bloqueo = verificarPalabrasBloqueadas($texto);

if ($bloqueo['bloqueado']) {
    $cat = $bloqueo['categoria'];

    $mensajes = [
        'Cocaína y derivados' => [
            'es' => '🚫 Este producto no está permitido en ASCC. La venta de cocaína y sus derivados es ilegal en Colombia.',
            'en' => '🚫 This product is not allowed on ASCC. The sale of cocaine and its derivatives is illegal in Colombia.',
        ],
        'Cannabis y derivados' => [
            'es' => '🚫 Este producto no está permitido en ASCC. La venta de cannabis no autorizado es ilegal.',
            'en' => '🚫 This product is not allowed on ASCC. The sale of unauthorized cannabis is illegal.',
        ],
        'Drogas sintéticas' => [
            'es' => '🚫 Este producto no está permitido en ASCC. Las drogas sintéticas están prohibidas.',
            'en' => '🚫 This product is not allowed on ASCC. Synthetic drugs are prohibited.',
        ],
        'Opioides' => [
            'es' => '🚫 Este producto no está permitido en ASCC. La venta de opioides sin prescripción es ilegal.',
            'en' => '🚫 This product is not allowed on ASCC. Selling opioids without a prescription is illegal.',
        ],
        'Inhalantes' => [
            'es' => '🚫 Este producto no está permitido en ASCC. La venta de inhalantes con fines recreativos está prohibida.',
            'en' => '🚫 This product is not allowed on ASCC. Selling inhalants for recreational use is prohibited.',
        ],
        'Nuevas sustancias psicoactivas' => [
            'es' => '🚫 Este producto no está permitido en ASCC. Las nuevas sustancias psicoactivas están prohibidas.',
            'en' => '🚫 This product is not allowed on ASCC. New psychoactive substances are prohibited.',
        ],
    ];

    $msgDefault = [
        'es' => '🚫 Este producto no está permitido en ASCC.',
        'en' => '🚫 This product is not allowed on ASCC.',
    ];

    $msg = isset($mensajes[$cat]) ? $mensajes[$cat] : $msgDefault;

    echo json_encode([
        'bloqueado'       => true,
        'categoria_droga' => $cat,
        'palabra'         => $bloqueo['palabra'],
        'mensaje_es'      => $msg['es'],
        'mensaje_en'      => $msg['en'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Normalizar texto ───────────────────────────────────── */
function normalizarTexto(string $str): string
{
    $str      = mb_strtolower(trim($str), 'UTF-8');
    $buscar   = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'à', 'è', 'ì', 'ò', 'ù'];
    $reemplaz = ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u'];
    return str_replace($buscar, $reemplaz, $str);
}

$textoNorm = normalizarTexto($texto);

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

/* ══════════════════════════════════════════════════════════
   PASO 2 — BÚSQUEDA GLOBAL EN TODA LA BD
   Sin filtro de categoría para detectar duplicados en
   cualquier sección del marketplace.
══════════════════════════════════════════════════════════ */
try {
    $stmt = $conexion->prepare("
        SELECT nombre, categoria, subcategoria
        FROM productos_custom
        ORDER BY nombre ASC
    ");
    $stmt->execute();
    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode([]);
    exit;
}

if (empty($candidatos)) {
    echo json_encode([]);
    exit;
}

/* ── Traducciones de subcategorías (bilingüe) ───────────── */
$subcatTrad = [
    // Peces
    'Tilapias'                        => ['es' => 'Tilapias',                           'en' => 'Tilapia'],
    'Salmónidos'                      => ['es' => 'Salmónidos (Aguas frías)',            'en' => 'Salmonids (Cold water)'],
    'Cachamas y Especies Amazónicas'  => ['es' => 'Cachamas y Especies Amazónicas',     'en' => 'Cachama and Amazonian Species'],
    'Bagres'                          => ['es' => 'Bagres',                             'en' => 'Catfish'],
    'Especies Nativas de Río'         => ['es' => 'Especies Nativas de Río',            'en' => 'Native River Species'],
    'Carpas'                          => ['es' => 'Carpas',                             'en' => 'Carp'],
    'Especies Amazónicas y Orinoquía' => ['es' => 'Especies Amazónicas y Orinoquía',    'en' => 'Amazonian and Orinoco Species'],
    'Peces Ornamentales'              => ['es' => 'Peces Ornamentales (Exportación)',    'en' => 'Ornamental Fish (Export)'],
    'Especies Marinas Cultivadas'     => ['es' => 'Especies Marinas Cultivadas',         'en' => 'Farmed Marine Species'],
    // Huevos
    'Huevos de Gallina'               => ['es' => 'Huevos de Gallina',                  'en' => 'Hen Eggs'],
    'Derivados del Huevo'             => ['es' => 'Derivados del Huevo',                'en' => 'Egg Derivatives'],
    // Aves
    'Gallinas'                        => ['es' => 'Gallinas',                           'en' => 'Hens'],
    'Otras Aves'                      => ['es' => 'Otras Aves',                         'en' => 'Other Birds'],
    // Bovinos
    'Razas Lecheras'                  => ['es' => 'Razas Lecheras',                     'en' => 'Dairy Breeds'],
    'Razas de Carne'                  => ['es' => 'Razas de Carne',                     'en' => 'Beef Breeds'],
    'Razas Doble Propósito'           => ['es' => 'Razas Doble Propósito',              'en' => 'Dual Purpose Breeds'],
    'Animales de Trabajo'             => ['es' => 'Animales de Trabajo',                'en' => 'Working Animals'],
    // Equinos
    'Caballos'                        => ['es' => 'Caballos',                           'en' => 'Horses'],
    'Otros Equinos'                   => ['es' => 'Otros Equinos',                      'en' => 'Other Equines'],
    // Ganado menor
    'Porcinos'                        => ['es' => 'Porcinos',                           'en' => 'Swine'],
    'Caprinos'                        => ['es' => 'Caprinos',                           'en' => 'Goats'],
    'Ovinos'                          => ['es' => 'Ovinos',                             'en' => 'Sheep'],
    'Otros'                           => ['es' => 'Otros',                              'en' => 'Others'],
    // Cárnicos
    'Carnes Frescas'                  => ['es' => 'Carnes Frescas',                     'en' => 'Fresh Meats'],
    'Embutidos'                       => ['es' => 'Embutidos',                          'en' => 'Sausages'],
    'Otros Derivados'                 => ['es' => 'Otros Derivados',                    'en' => 'Other Derivatives'],
    // Lácteos
    'Leche'                           => ['es' => 'Leche',                              'en' => 'Milk'],
    'Derivados'                       => ['es' => 'Derivados',                          'en' => 'Derivatives'],
    // Verduras
    'Tubérculos y Raíces'             => ['es' => 'Tubérculos y Raíces',                'en' => 'Tubers and Roots'],
    'Verduras de Hoja'                => ['es' => 'Verduras de Hoja',                   'en' => 'Leafy Vegetables'],
    'Frutos y Otros'                  => ['es' => 'Frutos y Otros',                     'en' => 'Fruits and Others'],
    'Plantas Aromáticas'              => ['es' => 'Plantas Aromáticas',                 'en' => 'Aromatic Plants'],
    // Frutas
    'Frutas Tropicales'               => ['es' => 'Frutas Tropicales',                  'en' => 'Tropical Fruits'],
    'Frutas de Clima Frío'            => ['es' => 'Frutas de Clima Frío',               'en' => 'Cold Weather Fruits'],
    'Cítricos'                        => ['es' => 'Cítricos',                           'en' => 'Citrus Fruits'],
    'Otras Frutas'                    => ['es' => 'Otras Frutas',                       'en' => 'Other Fruits'],
    // Cereales
    'Cereales'                        => ['es' => 'Cereales',                           'en' => 'Cereals'],
    'Leguminosas'                     => ['es' => 'Leguminosas',                        'en' => 'Legumes'],
    'Semillas Oleaginosas'            => ['es' => 'Semillas Oleaginosas',               'en' => 'Oil Seeds'],
    // Plantas
    'Plantas Medicinales'             => ['es' => 'Plantas Medicinales',                'en' => 'Medicinal Plants'],
    'Semillas Certificadas'           => ['es' => 'Semillas Certificadas',              'en' => 'Certified Seeds'],
    'Plántulas'                       => ['es' => 'Plántulas',                          'en' => 'Seedlings'],
    'Flores y Ornamentales'           => ['es' => 'Flores y Ornamentales',              'en' => 'Flowers and Ornamentals'],
    // Procesados
    'Conservas y Encurtidos'          => ['es' => 'Conservas y Encurtidos',             'en' => 'Preserves and Pickles'],
    'Bebidas Artesanales'             => ['es' => 'Bebidas Artesanales',                'en' => 'Artisan Beverages'],
    'Harinas y Almidones'             => ['es' => 'Harinas y Almidones',                'en' => 'Flours and Starches'],
    'Otros Procesados'                => ['es' => 'Otros Procesados',                   'en' => 'Other Processed'],
];

/* ── Traducciones de categorías principales (bilingüe) ───── */
$catTrad = [
    'huevos'     => ['es' => 'Huevos y Derivados',      'en' => 'Eggs and Derivatives'],
    'aves'       => ['es' => 'Aves de Corral',           'en' => 'Poultry'],
    'bovinos'    => ['es' => 'Ganado Bovino',            'en' => 'Cattle'],
    'equinos'    => ['es' => 'Caballos y Equinos',       'en' => 'Horses and Equines'],
    'menor'      => ['es' => 'Ganado Menor',             'en' => 'Small Livestock'],
    'carnicos'   => ['es' => 'Cárnicos y Embutidos',     'en' => 'Meats and Sausages'],
    'lacteos'    => ['es' => 'Lácteos',                  'en' => 'Dairy'],
    'verduras'   => ['es' => 'Verduras y Hortalizas',    'en' => 'Vegetables'],
    'frutas'     => ['es' => 'Frutas',                   'en' => 'Fruits'],
    'cereales'   => ['es' => 'Cereales y Granos',        'en' => 'Cereals and Grains'],
    'plantas'    => ['es' => 'Plantas y Semillas',       'en' => 'Plants and Seeds'],
    'procesados' => ['es' => 'Productos Procesados',     'en' => 'Processed Products'],
    'peces'      => ['es' => 'Peces y Acuicultura',      'en' => 'Fish and Aquaculture'],
];

/* ── Calcular similitud para cada candidato ───────────────── */
$resultados = [];

foreach ($candidatos as $candidato) {
    $nombreCandidato = $candidato['nombre'];
    $catCandidato    = $candidato['categoria'];
    $subcatCandidato = $candidato['subcategoria'];
    $candidatoNorm   = normalizarTexto($nombreCandidato);

    // Método 1: ¿Contiene el texto? (bonus 50 pts)
    $contiene = (strpos($candidatoNorm, $textoNorm) !== false) ? 50 : 0;

    // Método 2: similar_text
    similar_text($textoNorm, $candidatoNorm, $porcentaje);

    // Método 3: levenshtein
    $distancia = levenshtein($textoNorm, $candidatoNorm);
    $maxLen    = max(strlen($textoNorm), strlen($candidatoNorm), 1);
    $similLev  = (1 - ($distancia / $maxLen)) * 100;

    $puntuacion = ($porcentaje * 0.5) + ($similLev * 0.3) + $contiene;

    if ($puntuacion > 35) {
        $mismaCat    = ($catCandidato    === $categoriaActual);
        $mismaSubcat = ($subcatCandidato === $subcatActual && $mismaCat);

        // Obtener nombres traducidos
        $subcatEs = isset($subcatTrad[$subcatCandidato]) ? $subcatTrad[$subcatCandidato]['es'] : $subcatCandidato;
        $subcatEn = isset($subcatTrad[$subcatCandidato]) ? $subcatTrad[$subcatCandidato]['en'] : $subcatCandidato;
        $catEs    = isset($catTrad[$catCandidato])       ? $catTrad[$catCandidato]['es']       : $catCandidato;
        $catEn    = isset($catTrad[$catCandidato])       ? $catTrad[$catCandidato]['en']       : $catCandidato;

        // Mensajes bilingües según el caso
        if ($mismaSubcat) {
            $msgEs = '✅ Ya existe en esta sección — puedes seleccionarlo directamente';
            $msgEn = '✅ Already exists in this section — you can select it directly';
        } elseif ($mismaCat) {
            $msgEs = "📂 Ya existe en \"$subcatEs\" dentro de esta categoría";
            $msgEn = "📂 Already exists in \"$subcatEn\" within this category";
        } else {
            $msgEs = "📦 Ya existe en \"$subcatEs\" dentro de $catEs — ¿deseas ir a esa categoría?";
            $msgEn = "📦 Already exists in \"$subcatEn\" within $catEn — do you want to go to that category?";
        }

        $resultados[] = [
            'valor'                => $nombreCandidato,
            'similitud'            => round($puntuacion),
            'misma_categoria'      => $mismaCat,
            'misma_subcategoria'   => $mismaSubcat,
            'categoria_original'   => $catCandidato,
            'subcategoria_original' => $subcatCandidato,
            'mensaje_es'           => $msgEs,
            'mensaje_en'           => $msgEn,
        ];
    }
}

/* ── Ordenar: misma subcat primero, luego misma cat, luego similitud ── */
usort($resultados, function ($a, $b) {
    if ($a['misma_subcategoria'] !== $b['misma_subcategoria']) {
        return $a['misma_subcategoria'] ? -1 : 1;
    }
    if ($a['misma_categoria'] !== $b['misma_categoria']) {
        return $a['misma_categoria'] ? -1 : 1;
    }
    return $b['similitud'] - $a['similitud'];
});

echo json_encode(array_slice($resultados, 0, 5), JSON_UNESCAPED_UNICODE);
