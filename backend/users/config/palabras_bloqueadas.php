<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - PALABRAS BLOQUEADAS
 * Ruta: ascc/config/palabras_bloqueadas.php
 *
 * Lista estática de palabras clave asociadas a drogas y
 * sustancias prohibidas. Se usa para bloquear publicaciones
 * en el marketplace antes de llegar a la BD.
 *
 * CÓMO AGREGAR UNA NUEVA PALABRA:
 *   Simplemente agrégala al array correspondiente.
 *   No requiere cambios en ningún otro archivo.
 *
 * USO:
 *   require_once __DIR__ . '/palabras_bloqueadas.php';
 *   $resultado = verificarPalabrasBloqueadas($texto);
 *   if ($resultado['bloqueado']) {
 *       // $resultado['categoria'] → tipo de droga detectada
 *       // $resultado['palabra']   → palabra que activó el bloqueo
 *   }
 * ═══════════════════════════════════════════════════════════
 */

/**
 * Retorna el array completo de palabras bloqueadas
 * agrupadas por categoría.
 */
function getPalabrasBloqueadas(): array
{
    return [

        // ── 1. COCAÍNA Y DERIVADOS ───────────────────────────
        'Cocaína y derivados' => [
            'cocaina',
            'cocaína',
            'coca',
            'perico',
            'perika',
            'farlopa',
            'blanca',
            'polvo blanco',
            'linea',
            'línea',
            'pase',
            'pasecito',
            'basuco',
            'bazuco',
            'pasta base',
            'pasta de coca',
            'susto',
            'mono',
            'crack',
            'piedra',
        ],

        // ── 2. CANNABIS Y DERIVADOS ──────────────────────────
        'Cannabis y derivados' => [
            'marihuana',
            'mariguana',
            'incienso salvaje',
            'bareta',
            'bareto',
            'mota',
            'weed',
            'creepy',
            'krippy',
            'cannabis',
            'canabis',
            'ganja',
            'monte',
            'porro',
            'joint',
            'hachis',
            'hash',
            'wax',
            'shatter',
            'resina de cannabis',
            'aceite de cannabis',
            'extracto de cannabis',
        ],

        // ── 3. DROGAS SINTÉTICAS / FIESTAS ──────────────────
        'Drogas sintéticas' => [
            'mdma',
            'extasis',
            'popper',
            'poper',
            'pop',
            'éxtasis',
            'tacha',
            'pepa',
            'molly',
            'cristal',
            'crystal',
            'hielo',
            'meth',
            'metanfetamina',
            'anfetamina',
            'speed',
            'anfeta',
            'tripi',
            'acido',
            'ácido',
            'lsd',
            'carton',
            'cartón',
            'cuadro',
            'ketamina',
            'keta',
            'special k',
        ],

        // ── 4. OPIOIDES ──────────────────────────────────────
        'Opioides' => [
            'heroina',
            'heroína',
            'jaco',
            'morfina',
            'codeina',
            'lean',
            'jarabe morado',
            'fentanilo',
            'fenta',
            'oxicodona',
            'oxy',
            'oxi',
            'opio',
        ],

        // ── 5. INHALANTES ────────────────────────────────────
        'Inhalantes' => [
            'popper',
            'poppers',
            'nitrito',
            'boxer',
            'bóxer',
            'pegante',
            'thinner',
        ],

        // ── 6. NUEVAS SUSTANCIAS PSICOACTIVAS ────────────────
        'Nuevas sustancias psicoactivas' => [
            'spice',
            'k2',
            'cannabinoide sintetico',
            'cannabinoide sintético',
            'catinona',
            'mefedrona',
            'nps',
            'droga legal',
            'hierba legal',
        ],

    ];
}

/**
 * Verifica si un texto contiene alguna palabra bloqueada.
 *
 * Normaliza el texto: minúsculas + quita acentos antes
 * de comparar, para evitar falsos negativos por tildes
 * o mayúsculas.
 *
 * @param  string $texto   Texto a verificar
 * @return array  [
 *   'bloqueado'  => bool,
 *   'categoria'  => string|null,  // categoría de droga detectada
 *   'palabra'    => string|null,  // palabra que activó el bloqueo
 * ]
 */
function verificarPalabrasBloqueadas(string $texto): array
{
    $grupos = getPalabrasBloqueadas();

    // Normalizar el texto de entrada
    $textoNorm = _normalizarBloqueo($texto);

    foreach ($grupos as $categoria => $palabras) {
        foreach ($palabras as $palabra) {
            $palabraNorm = _normalizarBloqueo($palabra);

            // Buscar la palabra como término completo (word boundary simulado)
            // Usamos espacios y puntuación como delimitadores
            $patron = '/(^|[\s,.\-_\/\\\\()\[\]{}!?;:"\'])' .
                preg_quote($palabraNorm, '/') .
                '($|[\s,.\-_\/\\\\()\[\]{}!?;:"\'])/u';

            if (preg_match($patron, ' ' . $textoNorm . ' ')) {
                return [
                    'bloqueado' => true,
                    'categoria' => $categoria,
                    'palabra'   => $palabra,
                ];
            }
        }
    }

    return [
        'bloqueado' => false,
        'categoria' => null,
        'palabra'   => null,
    ];
}

/**
 * Normaliza un string: minúsculas + quita acentos.
 * Función interna — no usar directamente fuera de este archivo.
 */
function _normalizarBloqueo(string $str): string
{
    $str      = mb_strtolower(trim($str), 'UTF-8');
    $buscar   = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'à', 'è', 'ì', 'ò', 'ù', 'â', 'ê', 'î', 'ô', 'û'];
    $reemplaz = ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'];
    $str      = str_replace($buscar, $reemplaz, $str);
    /* Leet speak: números que reemplazan letras */
    $leet   = ['0', '1', '3', '4', '5', '6', '7', '8', '9'];
    $letras = ['o', 'i', 'e', 'a', 's', 'g', 't', 'b', 'g'];
    return str_replace($leet, $letras, $str);
}