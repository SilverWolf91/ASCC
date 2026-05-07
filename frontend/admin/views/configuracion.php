<?php

/**
 * ASCC — Módulo de Configuración del Sistema
 * Ruta: admin/configuracion.php
 */

// ── Sesión y autenticación ────────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// ── Dependencias ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../../backend/users/config/database.php';

// ── Idioma ────────────────────────────────────────────────────────────────────
$idiomaActual = $_SESSION['idioma'] ?? 'es';
$langFile     = __DIR__ . '/../../backend/admin/lang/' . $idiomaActual . '.php';
$lang         = file_exists($langFile) ? require $langFile : require __DIR__ . '/../../backend/admin/lang/es.php';

// ── Leer configuración de la BD ───────────────────────────────────────────────
// database.php expone $conexion (PDO) directamente al hacer require
try {
    $stmt = $conexion->query("SELECT clave, valor FROM configuracion ORDER BY grupo, clave");
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);  // ['clave' => 'valor', ...]
} catch (PDOException $e) {
    $rows = [];
}

/**
 * Helper: obtener valor de configuración con fallback.
 * @param string $key   Clave de configuración
 * @param mixed  $default Valor por defecto
 */
function cfg(string $key, $default = ''): string
{
    global $rows;
    return htmlspecialchars($rows[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper: checkbox/toggle — retorna true si el valor es '1'
 */
function cfgBool(string $key, bool $default = false): bool
{
    global $rows;
    return isset($rows[$key]) ? $rows[$key] === '1' : $default;
}

// ── CSRF Token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Título de página ─────────────────────────────────────────────────────────
$pageTitle = $lang['cfg_page_title'];
?>
<!DOCTYPE html>
<html lang="<?= $idiomaActual ?>" data-theme="<?= htmlspecialchars($_SESSION['tema'] ?? 'dark') ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — ASCC Admin</title>

    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS base del panel -->
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">

    <!-- CSS del módulo configuración -->
    <link rel="stylesheet" href="../assets/css/admin-config.css">
</head>

<body>

    <!-- ═══════════════════════════════════════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════════════════════════════════════ -->
    <aside class="ag-sidebar" id="agSidebar">

        <!-- Logo -->
        <div class="ag-sidebar__logo">
            <span class="ag-sidebar__logo-icon">🌿</span>
            <span class="ag-sidebar__logo-text">ASCC</span>
            <span class="ag-sidebar__logo-badge">ADMIN</span>
        </div>

        <!-- Navegación -->
        <nav class="ag-sidebar__nav">

            <div class="ag-sidebar__nav-label"><?= $lang['nav_main'] ?></div>

            <a href="dashboard.php" class="ag-sidebar__link">
                <i class="fa-solid fa-chart-pie"></i>
                <span><?= $lang['nav_dashboard'] ?></span>
            </a>
            <a href="users.php" class="ag-sidebar__link">
                <i class="fa-solid fa-users"></i>
                <span><?= $lang['nav_users'] ?></span>
            </a>
            <a href="products.php" class="ag-sidebar__link">
                <i class="fa-solid fa-box-open"></i>
                <span><?= $lang['nav_products'] ?></span>
            </a>
            <a href="transactions.php" class="ag-sidebar__link">
                <i class="fa-solid fa-credit-card"></i>
                <span><?= $lang['nav_transactions'] ?></span>
            </a>

            <div class="ag-sidebar__nav-label"><?= $lang['nav_content'] ?></div>

            <a href="categories.php" class="ag-sidebar__link">
                <i class="fa-solid fa-layer-group"></i>
                <span><?= $lang['nav_categories'] ?></span>
            </a>
            <a href="banners.php" class="ag-sidebar__link">
                <i class="fa-solid fa-image"></i>
                <span><?= $lang['nav_banners'] ?></span>
            </a>
            <a href="notifications.php" class="ag-sidebar__link">
                <i class="fa-solid fa-bell"></i>
                <span><?= $lang['nav_notifications'] ?></span>
            </a>
            <a href="reviews.php" class="ag-sidebar__link">
                <i class="fa-solid fa-star"></i>
                <span><?= $lang['nav_reviews'] ?></span>
            </a>

            <div class="ag-sidebar__nav-label"><?= $lang['nav_system'] ?></div>

            <a href="configuracion.php" class="ag-sidebar__link ag-sidebar__link--active">
                <i class="fa-solid fa-gear"></i>
                <span><?= $lang['nav_settings'] ?></span>
            </a>
            <a href="reportes.php" class="ag-sidebar__link">
                <i class="fa-solid fa-chart-bar"></i>
                <span><?= $lang['nav_reports'] ?? 'Reportes' ?></span>
            </a>
            <a href="change-password.php" class="ag-sidebar__link">
                <i class="fa-solid fa-lock"></i>
                <span><?= $lang['cp_nav_label'] ?></span>
            </a>
            <a href="logout.php" class="ag-sidebar__link ag-sidebar__link--danger" style="margin-top:auto;">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span><?= $lang['nav_logout'] ?></span>
            </a>

        </nav>

        <!-- Colapsar -->
        <button class="ag-sidebar__collapse-btn" id="sidebarCollapseBtn" title="Colapsar">
            <i class="fa-solid fa-chevron-left" id="collapseIcon"></i>
        </button>

    </aside>

    <!-- ═══════════════════════════════════════════════════════════════════════════
     OVERLAY MÓVIL
═══════════════════════════════════════════════════════════════════════════ -->
    <div class="ag-overlay" id="agOverlay"></div>

    <!-- ═══════════════════════════════════════════════════════════════════════════
     MAIN
═══════════════════════════════════════════════════════════════════════════ -->
    <div class="ag-main" id="agMain">

        <!-- ── TOPBAR ─────────────────────────────────────────────────────────── -->
        <header class="ag-topbar">
            <div class="ag-topbar__left">
                <!-- Botón hamburguesa móvil -->
                <button class="ag-topbar__menu-btn" id="mobileMenuBtn">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <!-- Breadcrumb -->
                <nav class="ag-topbar__breadcrumb">
                    <span>Admin</span>
                    <i class="fa-solid fa-angle-right"></i>
                    <span><?= $lang['nav_system'] ?></span>
                    <i class="fa-solid fa-angle-right"></i>
                    <span class="ag-topbar__breadcrumb--current"><?= $lang['cfg_page_title'] ?></span>
                </nav>
            </div>

            <div class="ag-topbar__right">
                <!-- Toggle Idioma -->
                <div class="ag-topbar__lang-toggle">
                    <button class="ag-lang-btn <?= $idiomaActual === 'es' ? 'ag-lang-btn--active' : '' ?>"
                        onclick="switchLang('es')">ES</button>
                    <span>/</span>
                    <button class="ag-lang-btn <?= $idiomaActual === 'en' ? 'ag-lang-btn--active' : '' ?>"
                        onclick="switchLang('en')">EN</button>
                </div>

                <!-- Toggle Tema -->
                <button class="ag-theme-toggle" id="themeToggle" title="Cambiar tema">
                    <i class="fa-solid <?= ($_SESSION['tema'] ?? 'dark') === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
                </button>

                <!-- Guardar Todo (topbar) -->
                <button class="cfg-save-all-btn" id="cfg-btn-save-all">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $lang['cfg_save_all'] ?>
                </button>
            </div>
        </header>

        <!-- ── FORMULARIO PRINCIPAL ───────────────────────────────────────────── -->
        <form id="cfg-form" method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <!-- ── CONTENT ────────────────────────────────────────────────────────── -->
            <main class="cfg-body">

                <!-- Page header -->
                <div class="cfg-page-header">
                    <div class="cfg-page-header__info">
                        <h1 class="cfg-page-header__title">
                            <i class="fa-solid fa-gear"></i>
                            <?= $lang['cfg_page_title'] ?>
                        </h1>
                        <p class="cfg-page-header__subtitle"><?= $lang['cfg_page_subtitle'] ?></p>
                    </div>
                </div>

                <!-- ── TABS ─────────────────────────────────────────────────────── -->
                <div class="cfg-tabs-wrapper" role="tablist">
                    <?php
                    $tabs = [
                        'general'   => ['icon' => '🏢', 'label' => $lang['cfg_tab_general']],
                        'correo'    => ['icon' => '📧', 'label' => $lang['cfg_tab_correo']],
                        'pagos'     => ['icon' => '💳', 'label' => $lang['cfg_tab_pagos']],
                        'seo'       => ['icon' => '🌐', 'label' => $lang['cfg_tab_seo']],
                        'seguridad' => ['icon' => '🔒', 'label' => $lang['cfg_tab_seguridad']],
                        'social'    => ['icon' => '📱', 'label' => $lang['cfg_tab_social']],
                        'regional'  => ['icon' => '🗺️', 'label' => $lang['cfg_tab_regional']],
                    ];
                    foreach ($tabs as $key => $tab): ?>
                    <button type="button" class="cfg-tab-btn" data-tab="<?= $key ?>" role="tab" aria-selected="false">
                        <span class="cfg-tab-icon"><?= $tab['icon'] ?></span>
                        <span class="cfg-tab-label"><?= $tab['label'] ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- ════════════════════════════════════════════════════════════════
             TAB: GENERAL
        ════════════════════════════════════════════════════════════════ -->
                <div id="cfg-tab-general" class="cfg-tab-panel">
                    <div class="cfg-grid">

                        <!-- Información del Sitio -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">🏢</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_gen_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_gen_subtitle'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label">
                                    <?= $lang['cfg_gen_nombre'] ?>
                                    <span class="cfg-field__required">*</span>
                                </label>
                                <input type="text" name="site_nombre" class="cfg-input"
                                    value="<?= cfg('site_nombre', 'Aromas y Sabores de mi Campo Colombiano (ASCC)') ?>"
                                    required>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_gen_slogan'] ?></label>
                                <input type="text" name="site_slogan" class="cfg-input"
                                    value="<?= cfg('site_slogan') ?>">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label">
                                    <?= $lang['cfg_gen_email'] ?>
                                    <span class="cfg-field__required">*</span>
                                </label>
                                <input type="email" name="site_email" class="cfg-input"
                                    value="<?= cfg('site_email', 'contacto@ASCC.co') ?>" required>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_gen_telefono'] ?></label>
                                <input type="text" name="site_telefono" class="cfg-input"
                                    value="<?= cfg('site_telefono') ?>">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_gen_direccion'] ?></label>
                                <input type="text" name="site_direccion" class="cfg-input"
                                    value="<?= cfg('site_direccion') ?>">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_gen_descripcion'] ?></label>
                                <textarea name="site_descripcion"
                                    class="cfg-textarea"><?= cfg('site_descripcion') ?></textarea>
                                <div class="cfg-field__hint"><?= $lang['cfg_gen_desc_hint'] ?></div>
                            </div>
                        </div>

                        <!-- Logo y Favicon -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">🖼️</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_logo_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_logo_subtitle'] ?></div>
                                </div>
                            </div>

                            <!-- Logo -->
                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_logo_label'] ?></label>
                                <div class="cfg-upload-zone">
                                    <input type="file" name="site_logo" accept="image/png,image/svg+xml,image/webp">
                                    <div class="cfg-upload-preview">🌿</div>
                                    <span class="cfg-upload-label"><?= $lang['cfg_logo_change'] ?></span>
                                    <span class="cfg-upload-hint"><?= $lang['cfg_logo_hint'] ?></span>
                                </div>
                            </div>

                            <!-- Color Principal -->
                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_color_label'] ?></label>
                                <div class="cfg-color-row">
                                    <input type="color" id="cfg-color-picker" name="site_color" class="cfg-color-input"
                                        value="<?= cfg('site_color', '#06654a') ?>">
                                    <input type="text" id="cfg-color-hex"
                                        class="cfg-input cfg-input--mono cfg-color-hex"
                                        value="<?= cfg('site_color', '#06654a') ?>" maxlength="7" placeholder="#06654a">
                                    <span class="cfg-badge cfg-badge--success">
                                        <span class="cfg-badge__dot"></span>
                                        Activo
                                    </span>
                                </div>
                            </div>

                            <!-- Favicon -->
                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_favicon_label'] ?></label>
                                <div class="cfg-upload-zone">
                                    <input type="file" name="site_favicon" accept="image/x-icon,image/png">
                                    <span class="cfg-upload-icon">🔖</span>
                                    <span class="cfg-upload-label"><?= $lang['cfg_favicon_upload'] ?></span>
                                    <span class="cfg-upload-hint"><?= $lang['cfg_favicon_hint'] ?></span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div><!-- /tab-general -->

                <!-- ════════════════════════════════════════════════════════════════
             TAB: CORREO SMTP
        ════════════════════════════════════════════════════════════════ -->
                <div id="cfg-tab-correo" class="cfg-tab-panel">
                    <div class="cfg-grid">

                        <!-- Servidor SMTP -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">🔌</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_smtp_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_smtp_subtitle'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label">
                                    <?= $lang['cfg_smtp_host'] ?>
                                    <span class="cfg-field__required">*</span>
                                </label>
                                <input type="text" name="smtp_host" class="cfg-input cfg-input--mono"
                                    value="<?= cfg('smtp_host', 'smtp.gmail.com') ?>" placeholder="smtp.ejemplo.com">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_smtp_puerto'] ?></label>
                                <select name="smtp_puerto" class="cfg-select">
                                    <option value="587" <?= cfg('smtp_puerto', '587') === '587' ? 'selected' : '' ?>>
                                        <?= $lang['cfg_smtp_tls'] ?>
                                    </option>
                                    <option value="465" <?= cfg('smtp_puerto') === '465' ? 'selected' : '' ?>>
                                        <?= $lang['cfg_smtp_ssl'] ?>
                                    </option>
                                    <option value="25" <?= cfg('smtp_puerto') === '25' ? 'selected' : '' ?>>
                                        <?= $lang['cfg_smtp_none'] ?>
                                    </option>
                                </select>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_smtp_cifrado'] ?></label>
                                <select name="smtp_cifrado" class="cfg-select">
                                    <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => $lang['cfg_smtp_none']] as $val => $lbl): ?>
                                    <option value="<?= $val ?>"
                                        <?= cfg('smtp_cifrado', 'tls') === $val ? 'selected' : '' ?>>
                                        <?= $lbl ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_smtp_usuario'] ?></label>
                                <input type="email" name="smtp_usuario" class="cfg-input"
                                    value="<?= cfg('smtp_usuario') ?>">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_smtp_password'] ?></label>
                                <div class="cfg-input-group">
                                    <input type="password" name="smtp_password" id="cfg-smtp-pass"
                                        class="cfg-input cfg-input--mono" value="<?= cfg('smtp_password') ?>"
                                        autocomplete="new-password">
                                    <button type="button" class="cfg-input-toggle"
                                        data-target="cfg-smtp-pass">👁️</button>
                                </div>
                            </div>

                            <button type="button" class="cfg-test-btn" id="cfg-smtp-test-btn">
                                <?= $lang['cfg_smtp_test_btn'] ?>
                            </button>
                        </div>

                        <!-- Remitente + Notificaciones -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">✉️</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_from_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_from_subtitle'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_from_nombre'] ?></label>
                                <input type="text" name="smtp_from_nombre" class="cfg-input"
                                    value="<?= cfg('smtp_from_nombre', 'ASCC Notificaciones') ?>">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_from_email'] ?></label>
                                <input type="email" name="smtp_from_email" class="cfg-input"
                                    value="<?= cfg('smtp_from_email', 'noreply@ascc.co') ?>">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_from_reply'] ?></label>
                                <input type="email" name="smtp_reply_to" class="cfg-input"
                                    value="<?= cfg('smtp_reply_to', 'soporte@ascc.co') ?>">
                            </div>

                            <!-- Toggles -->
                            <div style="margin-top:16px;">
                                <div class="cfg-toggle-row">
                                    <div class="cfg-toggle-info">
                                        <div class="cfg-toggle-title"><?= $lang['cfg_correo_bienvenida'] ?></div>
                                        <div class="cfg-toggle-desc"><?= $lang['cfg_correo_bienvenida_d'] ?></div>
                                    </div>
                                    <label class="cfg-switch">
                                        <input type="checkbox" name="correo_bienvenida" value="1"
                                            <?= cfgBool('correo_bienvenida', true) ? 'checked' : '' ?>>
                                        <span class="cfg-slider"></span>
                                    </label>
                                </div>
                                <div class="cfg-toggle-row">
                                    <div class="cfg-toggle-info">
                                        <div class="cfg-toggle-title"><?= $lang['cfg_correo_pedido'] ?></div>
                                        <div class="cfg-toggle-desc"><?= $lang['cfg_correo_pedido_d'] ?></div>
                                    </div>
                                    <label class="cfg-switch">
                                        <input type="checkbox" name="correo_pedido" value="1"
                                            <?= cfgBool('correo_pedido', true) ? 'checked' : '' ?>>
                                        <span class="cfg-slider"></span>
                                    </label>
                                </div>
                                <div class="cfg-toggle-row">
                                    <div class="cfg-toggle-info">
                                        <div class="cfg-toggle-title"><?= $lang['cfg_correo_alertas'] ?></div>
                                        <div class="cfg-toggle-desc"><?= $lang['cfg_correo_alertas_d'] ?></div>
                                    </div>
                                    <label class="cfg-switch">
                                        <input type="checkbox" name="correo_alertas" value="1"
                                            <?= cfgBool('correo_alertas') ? 'checked' : '' ?>>
                                        <span class="cfg-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                    </div>
                </div><!-- /tab-correo -->

                <!-- ════════════════════════════════════════════════════════════════
             TAB: PAGOS
        ════════════════════════════════════════════════════════════════ -->
                <div id="cfg-tab-pagos" class="cfg-tab-panel">
                    <div class="cfg-grid">

                        <!-- Selector de pasarela — full width -->
                        <div class="cfg-card cfg-grid--full">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">💳</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_pago_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_pago_subtitle'] ?></div>
                                </div>
                            </div>

                            <?php
                            $gateways = [
                                'pse'         => ['icon' => '🏦', 'name' => 'PSE',         'desc' => $lang['cfg_pago_pse_desc']],
                                'wompi'       => ['icon' => '💜', 'name' => 'Wompi',       'desc' => $lang['cfg_pago_wompi_desc']],
                                'payu'        => ['icon' => '🔵', 'name' => 'PayU',        'desc' => $lang['cfg_pago_payu_desc']],
                                'mercadopago' => ['icon' => '🟡', 'name' => 'MercadoPago', 'desc' => $lang['cfg_pago_mp_desc']],
                            ];
                            $selectedGateway = cfg('pago_pasarela', 'pse');
                            ?>
                            <div class="cfg-gateway-grid">
                                <?php foreach ($gateways as $key => $gw): ?>
                                <label class="cfg-gateway-card <?= $selectedGateway === $key ? 'selected' : '' ?>">
                                    <input type="radio" name="pago_pasarela" value="<?= $key ?>"
                                        <?= $selectedGateway === $key ? 'checked' : '' ?>>
                                    <div class="cfg-gateway-check">✓</div>
                                    <div class="cfg-gateway-logo"><?= $gw['icon'] ?></div>
                                    <div class="cfg-gateway-name"><?= $gw['name'] ?></div>
                                    <div class="cfg-gateway-desc"><?= $gw['desc'] ?></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Credenciales -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">🔑</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_keys_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_keys_subtitle'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_pago_public_key'] ?></label>
                                <input type="text" name="pago_public_key" class="cfg-input cfg-input--mono"
                                    value="<?= cfg('pago_public_key') ?>" placeholder="pub_...">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_pago_secret_key'] ?></label>
                                <div class="cfg-input-group">
                                    <input type="password" name="pago_secret_key" id="cfg-pago-secret"
                                        class="cfg-input cfg-input--mono" value="<?= cfg('pago_secret_key') ?>"
                                        autocomplete="new-password">
                                    <button type="button" class="cfg-input-toggle"
                                        data-target="cfg-pago-secret">👁️</button>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_pago_entorno'] ?></label>
                                <select name="pago_entorno" class="cfg-select">
                                    <option value="sandbox"
                                        <?= cfg('pago_entorno', 'sandbox') === 'sandbox'    ? 'selected' : '' ?>>
                                        <?= $lang['cfg_pago_sandbox'] ?></option>
                                    <option value="produccion"
                                        <?= cfg('pago_entorno') === 'produccion' ? 'selected' : '' ?>>
                                        <?= $lang['cfg_pago_produccion'] ?></option>
                                </select>
                            </div>
                        </div>

                        <!-- Comisiones -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">💰</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_comision_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_comision_subtitle'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_comision_label'] ?></label>
                                <input type="number" name="pago_comision" class="cfg-input"
                                    value="<?= cfg('pago_comision', '3.5') ?>" min="0" max="100" step="0.5">
                                <div class="cfg-field__hint"><?= $lang['cfg_comision_hint'] ?></div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_iva_label'] ?></label>
                                <input type="number" name="pago_iva" class="cfg-input"
                                    value="<?= cfg('pago_iva', '19') ?>" min="0" max="100">
                            </div>

                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_pago_efectivo'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_pago_efectivo_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="pago_efectivo" value="1"
                                        <?= cfgBool('pago_efectivo', true) ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>
                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_pago_transferencia'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_pago_transferencia_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="pago_transferencia" value="1"
                                        <?= cfgBool('pago_transferencia') ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div><!-- /tab-pagos -->

                <!-- ════════════════════════════════════════════════════════════════
             TAB: SEO
        ════════════════════════════════════════════════════════════════ -->
                <div id="cfg-tab-seo" class="cfg-tab-panel">
                    <div class="cfg-grid">

                        <!-- Meta Tags -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">🔍</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_seo_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_seo_subtitle'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_seo_meta_title'] ?></label>
                                <input type="text" name="seo_title" class="cfg-input" value="<?= cfg('seo_title') ?>">
                                <div class="cfg-field__hint"><?= $lang['cfg_seo_meta_title_hint'] ?></div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_seo_meta_desc'] ?></label>
                                <textarea name="seo_description" class="cfg-textarea"
                                    style="min-height:64px;"><?= cfg('seo_description') ?></textarea>
                                <div class="cfg-field__hint"><?= $lang['cfg_seo_meta_desc_hint'] ?></div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_seo_keywords'] ?></label>
                                <input type="text" name="seo_keywords" class="cfg-input"
                                    value="<?= cfg('seo_keywords') ?>">
                                <div class="cfg-field__hint"><?= $lang['cfg_seo_keywords_hint'] ?></div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_seo_ga'] ?></label>
                                <input type="text" name="seo_ga_id" class="cfg-input cfg-input--mono"
                                    value="<?= cfg('seo_ga_id') ?>" placeholder="G-XXXXXXXXXX">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_seo_gsc'] ?></label>
                                <input type="text" name="seo_gsc_code" class="cfg-input cfg-input--mono"
                                    value="<?= cfg('seo_gsc_code') ?>" placeholder="<?= $lang['cfg_seo_gsc_hint'] ?>">
                            </div>
                        </div>

                        <!-- Open Graph -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">📤</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_og_title_card'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_og_subtitle_card'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_og_title_label'] ?></label>
                                <input type="text" name="seo_og_title" class="cfg-input"
                                    value="<?= cfg('seo_og_title') ?>">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_og_desc_label'] ?></label>
                                <textarea name="seo_og_description" class="cfg-textarea"
                                    style="min-height:64px;"><?= cfg('seo_og_description') ?></textarea>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_og_image_label'] ?></label>
                                <div class="cfg-upload-zone">
                                    <input type="file" name="seo_og_image" accept="image/jpeg,image/png,image/webp">
                                    <span class="cfg-upload-icon">🖼️</span>
                                    <span class="cfg-upload-label"><?= $lang['cfg_og_image_upload'] ?></span>
                                    <span class="cfg-upload-hint"><?= $lang['cfg_og_image_hint'] ?></span>
                                </div>
                            </div>

                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_seo_sitemap'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_seo_sitemap_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="seo_sitemap" value="1"
                                        <?= cfgBool('seo_sitemap', true) ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>
                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_seo_robots'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_seo_robots_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="seo_robots" value="1"
                                        <?= cfgBool('seo_robots', true) ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div><!-- /tab-seo -->

                <!-- ════════════════════════════════════════════════════════════════
             TAB: SEGURIDAD
        ════════════════════════════════════════════════════════════════ -->
                <div id="cfg-tab-seguridad" class="cfg-tab-panel">
                    <div class="cfg-grid">

                        <!-- Control de Acceso -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">🔒</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_seg_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_seg_subtitle'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_seg_intentos'] ?></label>
                                <div class="cfg-range-wrapper">
                                    <input type="range" name="seg_max_intentos" class="cfg-range" min="2" max="10"
                                        value="<?= cfg('seg_max_intentos', '5') ?>" data-value-id="rv-intentos">
                                    <span class="cfg-range-value"
                                        id="rv-intentos"><?= cfg('seg_max_intentos', '5') ?></span>
                                </div>
                                <div class="cfg-field__hint"><?= $lang['cfg_seg_intentos_hint'] ?></div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_seg_bloqueo'] ?></label>
                                <div class="cfg-range-wrapper">
                                    <input type="range" name="seg_tiempo_bloqueo" class="cfg-range" min="5" max="60"
                                        step="5" value="<?= cfg('seg_tiempo_bloqueo', '15') ?>"
                                        data-value-id="rv-bloqueo">
                                    <span class="cfg-range-value"
                                        id="rv-bloqueo"><?= cfg('seg_tiempo_bloqueo', '15') ?></span>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_seg_sesion'] ?></label>
                                <div class="cfg-range-wrapper">
                                    <input type="range" name="seg_duracion_sesion" class="cfg-range" min="1" max="24"
                                        value="<?= cfg('seg_duracion_sesion', '8') ?>" data-value-id="rv-sesion">
                                    <span class="cfg-range-value"
                                        id="rv-sesion"><?= cfg('seg_duracion_sesion', '8') ?></span>
                                </div>
                            </div>

                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_seg_verify_email'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_seg_verify_email_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="seg_verificar_email" value="1"
                                        <?= cfgBool('seg_verificar_email', true) ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>
                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_seg_recaptcha'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_seg_recaptcha_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="seg_recaptcha" value="1"
                                        <?= cfgBool('seg_recaptcha') ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Modo Mantenimiento -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">🚧</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_mant_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_mant_subtitle'] ?></div>
                                </div>
                            </div>

                            <!-- Banner aviso mantenimiento activo -->
                            <div id="cfg-mant-banner"
                                class="cfg-maint-banner <?= !cfgBool('seg_mantenimiento') ? 'cfg-maint-banner--hidden' : '' ?>">
                                <div class="cfg-maint-banner__icon">⚠️</div>
                                <div>
                                    <div class="cfg-maint-banner__title">
                                        <?= $lang['cfg_mant_active_label'] ?>
                                    </div>
                                    <div class="cfg-maint-banner__sub"><?= $lang['cfg_mant_active_msg'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_mant_toggle'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_mant_toggle_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="seg_mantenimiento" id="cfg-mant-toggle" value="1"
                                        <?= cfgBool('seg_mantenimiento') ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>

                            <div class="cfg-field" style="margin-top:16px;">
                                <label class="cfg-field__label"><?= $lang['cfg_mant_mensaje'] ?></label>
                                <textarea name="seg_mant_mensaje" class="cfg-textarea"
                                    style="min-height:64px;"><?= cfg('seg_mant_mensaje') ?></textarea>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_mant_fecha'] ?></label>
                                <input type="datetime-local" name="seg_mant_fecha" class="cfg-input"
                                    value="<?= cfg('seg_mant_fecha') ?>">
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_mant_ips'] ?></label>
                                <textarea name="seg_ips_permitidas" class="cfg-textarea cfg-input--mono"
                                    style="min-height:54px;"
                                    placeholder="127.0.0.1&#10;192.168.1.0/24"><?= cfg('seg_ips_permitidas', '127.0.0.1') ?></textarea>
                                <div class="cfg-field__hint"><?= $lang['cfg_mant_ips_hint'] ?></div>
                            </div>
                        </div>

                    </div>
                </div><!-- /tab-seguridad -->

                <!-- ════════════════════════════════════════════════════════════════
             TAB: SOCIAL
        ════════════════════════════════════════════════════════════════ -->
                <div id="cfg-tab-social" class="cfg-tab-panel">
                    <div class="cfg-grid">

                        <!-- Redes Sociales -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">📱</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_social_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_social_subtitle'] ?></div>
                                </div>
                            </div>

                            <?php
                            $socialLinks = [
                                'social_facebook'  => ['icon' => '📘', 'class' => 'cfg-social-icon--fb', 'ph' => 'https://facebook.com/ascc'],
                                'social_instagram' => ['icon' => '📸', 'class' => 'cfg-social-icon--ig', 'ph' => 'https://instagram.com/ascc'],
                                'social_whatsapp'  => ['icon' => '💬', 'class' => 'cfg-social-icon--wa', 'ph' => 'https://wa.me/573100000000'],
                                'social_tiktok'    => ['icon' => '🎵', 'class' => 'cfg-social-icon--tk', 'ph' => 'https://tiktok.com/@ascc'],
                                'social_youtube'   => ['icon' => '▶️', 'class' => 'cfg-social-icon--yt', 'ph' => 'https://youtube.com/@ascc'],
                            ];
                            foreach ($socialLinks as $name => $s): ?>
                            <div class="cfg-social-field">
                                <div class="cfg-social-icon <?= $s['class'] ?>"><?= $s['icon'] ?></div>
                                <input type="url" name="<?= $name ?>" class="cfg-input" value="<?= cfg($name) ?>"
                                    placeholder="<?= $s['ph'] ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Integraciones -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">🔔</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_integ_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_integ_subtitle'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_wa_widget'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_wa_widget_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="social_wa_widget" value="1"
                                        <?= cfgBool('social_wa_widget', true) ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>

                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_fb_pixel'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_fb_pixel_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="social_fb_pixel" value="1"
                                        <?= cfgBool('social_fb_pixel') ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>

                            <div class="cfg-field" style="margin-top:14px;">
                                <label class="cfg-field__label"><?= $lang['cfg_fb_pixel_id'] ?></label>
                                <input type="text" name="social_fb_pixel_id" class="cfg-input cfg-input--mono"
                                    value="<?= cfg('social_fb_pixel_id') ?>" placeholder="XXXXXXXXXXXXXXXXXX">
                            </div>

                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_share_btn'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_share_btn_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="social_share_btn" value="1"
                                        <?= cfgBool('social_share_btn', true) ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div><!-- /tab-social -->

                <!-- ════════════════════════════════════════════════════════════════
             TAB: REGIONAL
        ════════════════════════════════════════════════════════════════ -->
                <div id="cfg-tab-regional" class="cfg-tab-panel">
                    <div class="cfg-grid">

                        <!-- Configuración Regional -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">🗺️</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_reg_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_reg_subtitle'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_reg_pais'] ?></label>
                                <select name="reg_pais" class="cfg-select">
                                    <option value="colombia"
                                        <?= cfg('reg_pais', 'colombia') === 'colombia' ? 'selected' : '' ?>>🇨🇴
                                        Colombia</option>
                                    <option value="venezuela" <?= cfg('reg_pais') === 'venezuela' ? 'selected' : '' ?>>
                                        🇻🇪 Venezuela</option>
                                    <option value="ecuador" <?= cfg('reg_pais') === 'ecuador' ? 'selected' : '' ?>>🇪🇨
                                        Ecuador</option>
                                </select>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_reg_moneda'] ?></label>
                                <select name="reg_moneda" class="cfg-select">
                                    <option value="COP" <?= cfg('reg_moneda', 'COP') === 'COP' ? 'selected' : '' ?>>COP
                                        — Peso Colombiano ($)</option>
                                    <option value="USD" <?= cfg('reg_moneda') === 'USD' ? 'selected' : '' ?>>USD — Dólar
                                        Americano</option>
                                </select>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_reg_timezone'] ?></label>
                                <select name="reg_timezone" class="cfg-select">
                                    <option value="America/Bogota"
                                        <?= cfg('reg_timezone', 'America/Bogota') === 'America/Bogota' ? 'selected' : '' ?>>
                                        America/Bogota (UTC-5)</option>
                                    <option value="America/New_York"
                                        <?= cfg('reg_timezone') === 'America/New_York' ? 'selected' : '' ?>>
                                        America/New_York (UTC-5)</option>
                                    <option value="UTC" <?= cfg('reg_timezone') === 'UTC' ? 'selected' : '' ?>>UTC
                                    </option>
                                </select>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_reg_idioma'] ?></label>
                                <select name="reg_idioma" class="cfg-select">
                                    <option value="es" <?= cfg('reg_idioma', 'es') === 'es' ? 'selected' : '' ?>>🇪🇸
                                        Español</option>
                                    <option value="en" <?= cfg('reg_idioma') === 'en' ? 'selected' : '' ?>>🇺🇸 English
                                    </option>
                                </select>
                            </div>

                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_reg_idioma_toggle'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_reg_idioma_toggle_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="reg_idioma_toggle" value="1"
                                        <?= cfgBool('reg_idioma_toggle', true) ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Envíos -->
                        <div class="cfg-card">
                            <div class="cfg-card__header">
                                <div class="cfg-card__icon">🚚</div>
                                <div>
                                    <div class="cfg-card__title"><?= $lang['cfg_envio_title'] ?></div>
                                    <div class="cfg-card__subtitle"><?= $lang['cfg_envio_subtitle'] ?></div>
                                </div>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_envio_cobertura'] ?></label>
                                <select name="reg_envio_cobertura" class="cfg-select">
                                    <option value="nacional"
                                        <?= cfg('reg_envio_cobertura', 'nacional') === 'nacional' ? 'selected' : '' ?>>
                                        <?= $lang['cfg_envio_nacional'] ?></option>
                                    <option value="bogota"
                                        <?= cfg('reg_envio_cobertura') === 'bogota'   ? 'selected' : '' ?>>
                                        <?= $lang['cfg_envio_bogota'] ?></option>
                                    <option value="punto"
                                        <?= cfg('reg_envio_cobertura') === 'punto'    ? 'selected' : '' ?>>
                                        <?= $lang['cfg_envio_punto'] ?></option>
                                </select>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_envio_base'] ?></label>
                                <input type="number" name="reg_envio_base" class="cfg-input"
                                    value="<?= cfg('reg_envio_base', '12000') ?>" min="0" step="500">
                                <div class="cfg-field__hint"><?= $lang['cfg_envio_base_hint'] ?></div>
                            </div>

                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_envio_gratis'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_envio_gratis_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="reg_envio_gratis" value="1"
                                        <?= cfgBool('reg_envio_gratis', true) ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_envio_minimo'] ?></label>
                                <input type="number" name="reg_envio_minimo" class="cfg-input"
                                    value="<?= cfg('reg_envio_minimo', '150000') ?>" min="0" step="1000">
                            </div>

                            <div class="cfg-toggle-row">
                                <div class="cfg-toggle-info">
                                    <div class="cfg-toggle-title"><?= $lang['cfg_maps'] ?></div>
                                    <div class="cfg-toggle-desc"><?= $lang['cfg_maps_d'] ?></div>
                                </div>
                                <label class="cfg-switch">
                                    <input type="checkbox" name="reg_google_maps" value="1"
                                        <?= cfgBool('reg_google_maps', true) ? 'checked' : '' ?>>
                                    <span class="cfg-slider"></span>
                                </label>
                            </div>

                            <div class="cfg-field">
                                <label class="cfg-field__label"><?= $lang['cfg_maps_key'] ?></label>
                                <div class="cfg-input-group">
                                    <input type="password" name="reg_maps_key" id="cfg-maps-key"
                                        class="cfg-input cfg-input--mono" value="<?= cfg('reg_maps_key') ?>"
                                        autocomplete="new-password" placeholder="AIzaSy...">
                                    <button type="button" class="cfg-input-toggle"
                                        data-target="cfg-maps-key">👁️</button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div><!-- /tab-regional -->

            </main><!-- /cfg-body -->

            <!-- ── FOOTER STICKY ──────────────────────────────────────────────────── -->
            <footer class="cfg-footer">
                <div class="cfg-footer__hint">
                    💡 <?= $lang['cfg_footer_hint'] ?>
                    <code>configuracion</code>
                </div>
                <div class="cfg-footer__actions">
                    <button type="button" class="cfg-btn-cancel" id="cfg-btn-discard">
                        <?= $lang['cfg_discard'] ?>
                    </button>
                    <button type="button" class="cfg-btn-save" id="cfg-btn-save-tab">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <?= $lang['cfg_save_tab'] ?>
                    </button>
                </div>
            </footer>

        </form><!-- /cfg-form -->

    </div><!-- /ag-main -->

    <!-- ── TOAST ──────────────────────────────────────────────────────────────── -->
    <div class="cfg-toast" id="cfg-toast"></div>

    <!-- ─────────────────────────────────────────────────────────────────────────
     SCRIPTS
───────────────────────────────────────────────────────────────────────── -->

    <!-- Variables PHP → JS (CSRF + Lang strings para el módulo) -->
    <script>
    window.csrfToken = <?= json_encode($csrfToken) ?>;
    window.cfgLang = {
        saved_ok: <?= json_encode($lang['cfg_saved_ok']) ?>,
        saved_error: <?= json_encode($lang['cfg_saved_error']) ?>,
        test_smtp_ok: <?= json_encode($lang['cfg_test_smtp_ok']) ?>,
        test_smtp_error: <?= json_encode($lang['cfg_test_smtp_error']) ?>,
        saving: <?= json_encode($lang['loading']) ?>,
        discard_confirm: <?= json_encode($idiomaActual === 'es' ? '¿Descartar todos los cambios sin guardar?' : 'Discard all unsaved changes?') ?>
    };
    </script>

    <!-- Sidebar + tema + idioma (sync-global.js ya existente en el proyecto) -->
    <script src="../assets/js/sync-global.js"></script>

    <!-- Módulo configuración -->
    <script src="../assets/js/admin-config.js"></script>

    <!-- Inline: cambio de idioma -->
    <script>
    function switchLang(lang) {
        fetch('/ascc/backend/admin/ajax/switch_lang.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'lang=' + lang + '&csrf_token=' + encodeURIComponent(window.csrfToken),
        }).then(() => window.location.reload());
    }
    </script>

</body>

</html>