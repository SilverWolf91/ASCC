<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - HEADER GLOBAL (PARTIAL)
 * Ruta: C:\xampp\htdocs\ascc\partials\header.php
 *
 * Responsabilidad: HTML del selector de idioma y tema
 *   - Usa $lang y $theme definidos por config/app.php
 *   - Llama a ASCCGlobal (definido en sync-global.js)
 *
 * COMPORTAMIENTO INTELIGENTE:
 *   - Si la página se abre DIRECTA (standalone) → muestra los botones
 *   - Si la página se abre dentro de un IFRAME del dashboard → oculta
 *     los botones automáticamente via JavaScript, porque el dashboard
 *     ya tiene su propio sidebar con los controles de idioma y tema.
 *     Así se evita duplicar controles y confundir al usuario.
 *
 * NO redefine window.ASCCGlobal
 * NO contiene CSS en archivo externo (solo estilos del widget)
 * ═══════════════════════════════════════════════════════════
 */

// Garantizar que las variables necesarias estén disponibles
if (!isset($lang) || !isset($theme)) {
    require_once __DIR__ . '/../config/app.php';
}
?>

<style>
    /* ── WIDGET: selector de idioma y tema (header global) ──── */
    .ascc-header-widget {
        position: fixed;
        top: 20px;
        right: 20px;
        display: flex;
        gap: 10px;
        z-index: 9999;
    }

    /* Contenedor de cada selector */
    .ascc-lang-box,
    .ascc-theme-box {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 6px;
        border-radius: 14px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
    }

    /* Colores según tema activo */
    body.theme-light .ascc-lang-box,
    body[data-theme="light"] .ascc-lang-box {
        background: #ffffff;
        border: 2px solid #E5E7EB;
    }

    body.theme-dark .ascc-lang-box,
    body[data-theme="dark"] .ascc-lang-box {
        background: #334155;
        border: 2px solid #475569;
    }

    body.theme-light .ascc-theme-box,
    body[data-theme="light"] .ascc-theme-box {
        background: #1E293B;
    }

    body.theme-dark .ascc-theme-box,
    body[data-theme="dark"] .ascc-theme-box {
        background: linear-gradient(135deg, #F59E0B, #D97706);
    }

    /* Botones de idioma */
    .ascc-lang-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 10px;
        font-weight: 800;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.25s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: transparent;
    }

    body.theme-light .ascc-lang-btn,
    body[data-theme="light"] .ascc-lang-btn {
        color: #6B7280;
    }

    body.theme-dark .ascc-lang-btn,
    body[data-theme="dark"] .ascc-lang-btn {
        color: #CBD5E1;
    }

    .ascc-lang-btn.activo {
        background: linear-gradient(135deg, #10B981, #059669);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
    }

    body.theme-light .ascc-lang-btn:not(.activo):hover,
    body[data-theme="light"] .ascc-lang-btn:not(.activo):hover {
        background: #F3F4F6;
    }

    body.theme-dark .ascc-lang-btn:not(.activo):hover,
    body[data-theme="dark"] .ascc-lang-btn:not(.activo):hover {
        background: rgba(255, 255, 255, 0.1);
    }

    /* Botón de tema */
    .ascc-theme-btn {
        padding: 8px 14px;
        border: none;
        border-radius: 10px;
        background: transparent;
        color: #ffffff;
        font-size: 20px;
        cursor: pointer;
        transition: all 0.25s ease;
        line-height: 1;
    }

    .ascc-theme-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.1);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .ascc-header-widget {
            top: 10px;
            right: 10px;
            gap: 6px;
        }

        .ascc-lang-btn {
            padding: 6px 10px;
            font-size: 11px;
        }

        .ascc-theme-btn {
            padding: 6px 10px;
            font-size: 18px;
        }
    }

    /* Estado oculto cuando está dentro de iframe del dashboard */
    .ascc-header-widget.dentro-de-iframe {
        display: none !important;
    }
</style>

<div class="ascc-header-widget" id="asccHeaderWidget">

    <!-- SELECTOR DE IDIOMA -->
    <div class="ascc-lang-box">
        <button class="ascc-lang-btn <?= $lang === 'es' ? 'activo' : '' ?>"
            onclick="ASCCGlobal.cambiarIdioma('es')" title="Cambiar a Español" aria-label="Español">
            ES
        </button>
        <button class="ascc-lang-btn <?= $lang === 'en' ? 'activo' : '' ?>"
            onclick="ASCCGlobal.cambiarIdioma('en')" title="Switch to English" aria-label="English">
            EN
        </button>
    </div>

    <!-- SELECTOR DE TEMA -->
    <div class="ascc-theme-box">
        <button class="ascc-theme-btn" onclick="ASCCGlobal.toggleTema()"
            title="<?= $theme === 'light' ? t('dark_mode') : t('light_mode') ?>"
            aria-label="<?= $theme === 'light' ? t('dark_mode') : t('light_mode') ?>">
            <?= $theme === 'light' ? '🌙' : '☀️' ?>
        </button>
    </div>

</div>

<script>
    /**
     * Detección de iframe:
     * Si esta página está cargada dentro de un iframe del dashboard,
     * ocultamos el widget porque el dashboard ya tiene sus propios controles.
     * Si está abierta de forma independiente (standalone), lo mostramos.
     *
     * window.self !== window.top → estamos dentro de un iframe
     * window.self === window.top → página abierta directamente
     */
    (function() {
        try {
            var dentroDeIframe = window.self !== window.top;
            if (dentroDeIframe) {
                var widget = document.getElementById('asccHeaderWidget');
                if (widget) {
                    widget.classList.add('dentro-de-iframe');
                }
            }
        } catch (e) {
            // Si hay error de cross-origin, estamos en un iframe externo.
            // En ese caso también ocultamos para evitar conflictos.
            var widget = document.getElementById('asccHeaderWidget');
            if (widget) {
                widget.classList.add('dentro-de-iframe');
            }
        }
    }());
</script>