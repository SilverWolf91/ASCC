<?php
/**
 * Sistema de Gestion de Idiomas
 * ASCC - Multilenguaje
 */

// Iniciar sesion si no esta iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Idiomas disponibles
define('AVAILABLE_LANGUAGES', ['es', 'en']);
define('DEFAULT_LANGUAGE', 'es');

/**
 * Obtener el idioma actual
 */
function getCurrentLanguage() {
    // 1. Verificar si hay idioma en sesion
    if (isset($_SESSION['language']) && in_array($_SESSION['language'], AVAILABLE_LANGUAGES)) {
        return $_SESSION['language'];
    }
    
    // 2. Verificar si hay idioma en cookie
    if (isset($_COOKIE['ascc_lang']) && in_array($_COOKIE['ascc_lang'], AVAILABLE_LANGUAGES)) {
        $_SESSION['language'] = $_COOKIE['ascc_lang'];
        return $_COOKIE['ascc_lang'];
    }
    
    // 3. Detectar idioma del navegador
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (in_array($browserLang, AVAILABLE_LANGUAGES)) {
            $_SESSION['language'] = $browserLang;
            return $browserLang;
        }
    }
    
    // 4. Retornar idioma por defecto
    $_SESSION['language'] = DEFAULT_LANGUAGE;
    return DEFAULT_LANGUAGE;
}

/**
 * Cambiar el idioma actual
 */
function setLanguage($lang) {
    if (in_array($lang, AVAILABLE_LANGUAGES)) {
        $_SESSION['language'] = $lang;
        // Guardar en cookie por 1 año
        setcookie('ascc_lang', $lang, time() + (365 * 24 * 60 * 60), '/');
        return true;
    }
    return false;
}

/**
 * Cargar las traducciones del idioma actual
 */
function loadTranslations() {
    $lang = getCurrentLanguage();
    $langFile = __DIR__ . "/lang_{$lang}.php";
    
    if (file_exists($langFile)) {
        return require $langFile;
    }
    
    // Si no existe el archivo, cargar idioma por defecto
    $defaultFile = __DIR__ . "/lang_" . DEFAULT_LANGUAGE . ".php";
    return require $defaultFile;
}

// Cargar traducciones globalmente
$GLOBALS['translations'] = loadTranslations();
$GLOBALS['current_lang'] = getCurrentLanguage();

/**
 * Funcion principal de traduccion
 * Uso: t('welcome') o __('welcome')
 */
function t($key, $default = null) {
    $translations = $GLOBALS['translations'];
    
    if (isset($translations[$key])) {
        return $translations[$key];
    }
    
    // Si no existe la traduccion, retornar la clave o un valor por defecto
    return $default !== null ? $default : $key;
}

// Alias de la funcion t()
function __($key, $default = null) {
    return t($key, $default);
}

/**
 * Obtener nombre completo del idioma
 */
function getLanguageName($code) {
    $names = [
        'es' => 'Español',
        'en' => 'English'
    ];
    return $names[$code] ?? $code;
}

/**
 * Obtener bandera del idioma (emoji)
 */
function getLanguageFlag($code) {
    $flags = [
        'es' => '🇪🇸',
        'en' => '🇺🇸'
    ];
    return $flags[$code] ?? '🌍';
}