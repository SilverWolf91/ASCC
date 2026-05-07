<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - API: BUSCAR SIMILITUD DE MUNICIPIOS / VEREDAS
 * Ruta: ascc/api/buscar_similitud.php
 *
 * Dado un texto escrito por el usuario (posiblemente con
 * errores ortográficos), busca en la BD los municipios o
 * veredas más parecidos usando tres algoritmos combinados:
 *   1. Búsqueda LIKE (contiene el texto)
 *   2. similar_text() de PHP  (porcentaje de similitud)
 *   3. levenshtein()          (distancia de edición)
 *
 * Ejemplo: el usuario escribe "Guamla"
 *   → el sistema encuentra "Guamal" con 87% de similitud
 *
 * Parámetros GET:
 *   tipo         → 'municipio' | 'vereda'
 *   texto        → lo que escribió el usuario
 *   departamento → obligatorio siempre
 *   municipio    → obligatorio solo cuando tipo = 'vereda'
 *
 * Respuesta JSON:
 *   [{ "valor": "Guamal", "similitud": 87 }, ...]
 *   Máximo 5 resultados ordenados por similitud descendente.
 * ═══════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Ruta correcta: ascc/api/ → un nivel arriba → ascc/config/
require_once __DIR__ . '/../config/database.php';

/* ── Parámetros de entrada ──────────────────────────────── */
$tipo         = isset($_GET['tipo'])         ? trim($_GET['tipo'])         : 'municipio';
$texto        = isset($_GET['texto'])        ? trim($_GET['texto'])        : '';
$departamento = isset($_GET['departamento']) ? trim($_GET['departamento']) : '';
$municipio    = isset($_GET['municipio'])    ? trim($_GET['municipio'])    : '';

/* ── Validación básica ──────────────────────────────────── */
if (strlen($texto) < 2 || empty($departamento)) {
    echo json_encode([]);
    exit;
}

/* ── Función: quitar acentos y pasar a minúsculas ────────── */
function normalizarTexto($str)
{
    $str      = mb_strtolower(trim($str), 'UTF-8');
    $buscar   = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'à', 'è', 'ì', 'ò', 'ù'];
    $reemplaz = ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u'];
    return str_replace($buscar, $reemplaz, $str);
}

$textoNorm = normalizarTexto($texto);

/* ── Obtener todos los candidatos de la BD ──────────────── */
try {
    if ($tipo === 'municipio') {
        $stmt = $conexion->prepare("
            SELECT DISTINCT municipio AS valor
            FROM ubicaciones
            WHERE departamento = :depto
            ORDER BY municipio ASC
        ");
        $stmt->bindParam(':depto', $departamento);
    } else {
        $stmt = $conexion->prepare("
            SELECT DISTINCT vereda AS valor
            FROM ubicaciones
            WHERE departamento = :depto
              AND municipio    = :muni
            ORDER BY vereda ASC
        ");
        $stmt->bindParam(':depto', $departamento);
        $stmt->bindParam(':muni',  $municipio);
    }

    $stmt->execute();
    $candidatos = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    echo json_encode([]);
    exit;
}

if (empty($candidatos)) {
    echo json_encode([]);
    exit;
}

/* ── Calcular similitud para cada candidato ─────────────── */
$resultados = [];

foreach ($candidatos as $candidato) {
    $candidatoNorm = normalizarTexto($candidato);

    // Método 1: ¿Contiene el texto buscado? (bonus de 50 puntos)
    $contiene = (strpos($candidatoNorm, $textoNorm) !== false) ? 50 : 0;

    // Método 2: similar_text → porcentaje 0-100
    similar_text($textoNorm, $candidatoNorm, $porcentaje);

    // Método 3: levenshtein → distancia de edición convertida a porcentaje
    $distancia = levenshtein($textoNorm, $candidatoNorm);
    $maxLen    = max(strlen($textoNorm), strlen($candidatoNorm), 1);
    $similLev  = (1 - ($distancia / $maxLen)) * 100;

    // Puntuación final ponderada
    $puntuacion = ($porcentaje * 0.5) + ($similLev * 0.3) + $contiene;

    // Solo incluir si la similitud supera el umbral mínimo
    if ($puntuacion > 35) {
        $resultados[] = [
            'valor'     => $candidato,
            'similitud' => round($puntuacion)
        ];
    }
}

/* ── Ordenar por similitud y limitar a 5 ───────────────── */
usort($resultados, function ($a, $b) {
    return $b['similitud'] - $a['similitud'];
});

echo json_encode(array_slice($resultados, 0, 5), JSON_UNESCAPED_UNICODE);