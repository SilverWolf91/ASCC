<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - CONFIGURACIÓN GLOBAL
 * Ruta: C:\xampp\htdocs\ascc\config\app.php
 *
 * Responsabilidad: SOLO PHP
 *   - Manejo de sesión
 *   - Detección de idioma y tema
 *   - Sincronización SESSION ↔ COOKIE
 *   - Carga de traducciones
 *   - Helpers PHP: t() y ascc_theme_css()
 *
 * NO contiene: JavaScript, CSS, HTML
 *
 * ═══════════════════════════════════════════════════════════
 * CAMBIOS APLICADOS (José - 19/02/2026):
 * - Tema por defecto cambiado de 'light' a 'dark'
 * - Idioma por defecto cambiado a 'es' (español)
 * ═══════════════════════════════════════════════════════════
 */

// ── 1. SESIÓN ────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 2. CONSTANTE DE SEGURIDAD PARA PARTIALS ──────────────────
if (!defined('ASCC')) {
    define('ASCC', true);
}

// ── 3. TOKEN CSRF ─────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── 4. COOKIES → SESSION ─────────────────────────────────────
if (isset($_COOKIE['ascc_lang']) && in_array($_COOKIE['ascc_lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_COOKIE['ascc_lang'];
}

if (isset($_COOKIE['ascc_theme']) && in_array($_COOKIE['ascc_theme'], ['light', 'dark'], true)) {
    $_SESSION['theme'] = $_COOKIE['ascc_theme'];
}

// ── 5. CAMBIO POR PARÁMETRO URL ──────────────────────────────
$_ascc_url_changed = false;

if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('ascc_lang', $_GET['lang'], [
        'expires'  => time() + (86400 * 365),
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
    $_ascc_url_changed = true;
}

if (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark'], true)) {
    $_SESSION['theme'] = $_GET['theme'];
    setcookie('ascc_theme', $_GET['theme'], [
        'expires'  => time() + (86400 * 365),
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
    $_ascc_url_changed = true;
}

if ($_ascc_url_changed) {
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ── 6. VALORES POR DEFECTO ───────────────────────────────────
if (empty($_SESSION['lang'])) {
    $_SESSION['lang'] = !empty($_COOKIE['ascc_lang'])
        ? $_COOKIE['ascc_lang']
        : 'es';
}

if (empty($_SESSION['theme'])) {
    $_SESSION['theme'] = !empty($_COOKIE['ascc_theme'])
        ? $_COOKIE['ascc_theme']
        : 'dark';
}

// ── 7. VARIABLES DISPONIBLES PARA TODAS LAS VISTAS ───────────
$lang         = $_SESSION['lang'];
$current_lang = $lang;
$theme        = $_SESSION['theme'];

// ── 8. CREAR COOKIES SI AÚN NO EXISTEN ───────────────────────
if (!isset($_COOKIE['ascc_lang'])) {
    setcookie('ascc_lang', $lang, [
        'expires'  => time() + (86400 * 365),
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
}

if (!isset($_COOKIE['ascc_theme'])) {
    setcookie('ascc_theme', $theme, [
        'expires'  => time() + (86400 * 365),
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
}

// ── 9. CARGAR TRADUCCIONES ────────────────────────────────────
$_lang_file = __DIR__ . '/../lang/' . $lang . '.php';

$translations = file_exists($_lang_file)
    ? require $_lang_file
    : require __DIR__ . '/../lang/es.php';

// ── 10. HELPER: TRADUCCIÓN ────────────────────────────────────
/**
 * Devuelve el texto traducido para la clave dada.
 * Si la clave no existe, devuelve la propia clave.
 *
 * @param string $key
 * @return string
 */
function t(string $key): string
{
    global $translations;
    return $translations[$key] ?? $key;
}

// ── 11. HELPER: TAG CSS DINÁMICO ──────────────────────────────
/**
 * Devuelve el tag <link> del CSS según el tema activo.
 *
 * @return string
 */
function ascc_theme_css(): string
{
    global $theme;
    $file     = ($theme === 'dark') ? 'dark.css' : 'light.css';
    $filepath = $_SERVER['DOCUMENT_ROOT'] . '/ascc/public/css/' . $file;
    $version  = file_exists($filepath) ? filemtime($filepath) : '1';

    return '<link rel="stylesheet" id="ascc-theme-css"'
        . ' href="/ascc/public/css/' . $file . '?v=' . $version . '">';
}
