<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - CONTROLADOR DE UBICACIONES MEJORADO
 * Búsqueda inteligente de veredas con similitud
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    /**
     * BUSCAR VEREDA SIMILAR
     * Encuentra veredas parecidas para prevenir duplicados
     */
    case 'buscar_similar':
        $vereda = trim($_GET['vereda'] ?? '');
        $municipio = trim($_GET['municipio'] ?? '');

        if (empty($vereda) || strlen($vereda) < 3) {
            echo json_encode(['success' => false, 'error' => 'Vereda muy corta']);
            exit;
        }

        try {
            // Buscar veredas existentes en el municipio
            $stmt = $conexion->prepare("
                SELECT DISTINCT vereda, COUNT(*) as usos
                FROM ubicaciones
                WHERE municipio = :municipio
                    AND vereda IS NOT NULL
                    AND vereda != ''
                GROUP BY vereda
                HAVING usos > 0
                ORDER BY usos DESC
                LIMIT 50
            ");
            $stmt->bindParam(':municipio', $municipio, PDO::PARAM_STR);
            $stmt->execute();
            $veredas_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular similitud con cada vereda
            $mejor_match = null;
            $mejor_similitud = 0;

            foreach ($veredas_existentes as $row) {
                $similitud = calcularSimilitudLevenshtein($vereda, $row['vereda']);

                if ($similitud > $mejor_similitud) {
                    $mejor_similitud = $similitud;
                    $mejor_match = $row['vereda'];
                }
            }

            // Si encuentra similitud >= 80% (pero no 100%), sugerir
            if ($mejor_similitud >= 80 && $mejor_similitud < 100) {
                echo json_encode([
                    'success' => true,
                    'existe' => true,
                    'sugerencia' => $mejor_match,
                    'similitud' => round($mejor_similitud, 1)
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'existe' => false
                ]);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    /**
     * AUTOCOMPLETAR VEREDA
     * Sugerencias mientras el usuario escribe
     */
    case 'autocompletar_vereda':
        $query = trim($_GET['query'] ?? '');
        $municipio = trim($_GET['municipio'] ?? '');

        if (strlen($query) < 2) {
            echo json_encode(['success' => false, 'veredas' => []]);
            exit;
        }

        try {
            $search = '%' . $query . '%';

            $stmt = $conexion->prepare("
                SELECT DISTINCT vereda, COUNT(*) as usos
                FROM ubicaciones
                WHERE municipio = :municipio
                    AND vereda LIKE :search
                    AND vereda IS NOT NULL
                    AND vereda != ''
                GROUP BY vereda
                ORDER BY usos DESC, vereda ASC
                LIMIT 10
            ");
            $stmt->bindParam(':municipio', $municipio, PDO::PARAM_STR);
            $stmt->bindParam(':search', $search, PDO::PARAM_STR);
            $stmt->execute();

            $veredas = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                'success' => true,
                'veredas' => $veredas
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        break;
}

/**
 * FUNCIÓN: Calcular similitud de Levenshtein
 * Retorna porcentaje de similitud entre dos strings
 */
function calcularSimilitudLevenshtein($str1, $str2)
{
    // Normalizar: minúsculas y sin tildes
    $str1 = mb_strtolower(quitarTildes($str1), 'UTF-8');
    $str2 = mb_strtolower(quitarTildes($str2), 'UTF-8');

    $len1 = mb_strlen($str1, 'UTF-8');
    $len2 = mb_strlen($str2, 'UTF-8');

    if ($len1 == 0) return ($len2 == 0) ? 100 : 0;
    if ($len2 == 0) return 0;

    // Calcular distancia de Levenshtein
    $distance = levenshtein($str1, $str2);
    $maxLength = max($len1, $len2);

    // Convertir a porcentaje de similitud
    $similitud = (($maxLength - $distance) / $maxLength) * 100;

    return $similitud;
}

/**
 * FUNCIÓN: Quitar tildes y caracteres especiales
 */
function quitarTildes($str)
{
    $unwanted_array = [
        'Š' => 'S',
        'š' => 's',
        'Ž' => 'Z',
        'ž' => 'z',
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'Æ' => 'A',
        'Ç' => 'C',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ñ' => 'N',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'Ø' => 'O',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ý' => 'Y',
        'Þ' => 'B',
        'ß' => 'Ss',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'å' => 'a',
        'æ' => 'a',
        'ç' => 'c',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ð' => 'o',
        'ñ' => 'n',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'ø' => 'o',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ý' => 'y',
        'þ' => 'b',
        'ÿ' => 'y'
    ];
    return strtr($str, $unwanted_array);
}
