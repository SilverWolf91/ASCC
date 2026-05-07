/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - SINCRONIZACIÓN DEL DASHBOARD
 * Archivo : public/js/dashboard-sync.js
 *
 * Responsabilidad : SOLO JavaScript.
 *   Complemento de sync-global.js exclusivo para el dashboard.
 *   - Actualiza el estado visual del toggle de tema
 *   - Actualiza el estado visual de los botones de idioma
 *
 * CORRECCIÓN APLICADA:
 *   Se eliminaron los listeners de respaldo (addEventListener)
 *   que duplicaban los onclick del HTML en dashboard.php.
 *   Tener onclick + addEventListener en el mismo elemento
 *   causaba que toggleTema() y cambiarIdioma() se ejecutaran
 *   DOS veces por clic, provocando recargas dobles y
 *   comportamiento errático.
 *
 * REQUISITO OBLIGATORIO:
 *   sync-global.js debe cargarse ANTES que este archivo.
 *   Ese archivo define window.ASCCGlobal.
 *   Este archivo NUNCA redefine window.ASCCGlobal.
 * ═══════════════════════════════════════════════════════════
 */

(function (window) {
    'use strict';

    /* ─────────────────────────────────────────────────────────
       ESTADO VISUAL DEL TOGGLE DE TEMA
       ───────────────────────────────────────────────────────── */

    /**
     * Aplica la clase visual 'activo' al toggle-switch del sidebar
     * según el tema que viene del servidor (PHP).
     * El servidor ya renderiza la clase en el HTML, pero si hay
     * algún desfase, esta función lo corrige.
     */
    function actualizarToggleTema() {
        var temaActual   = window.ASCCGlobal.getTema();
        var toggleSwitch = document.querySelector('.toggle-switch');

        if (!toggleSwitch) { return; }

        if (temaActual === 'dark') {
            toggleSwitch.classList.add('activo');
        } else {
            toggleSwitch.classList.remove('activo');
        }
    }

    /* ─────────────────────────────────────────────────────────
       ESTADO VISUAL DE LOS BOTONES DE IDIOMA
       ───────────────────────────────────────────────────────── */

    /**
     * Marca con la clase 'active' el botón de idioma correcto
     * en el selector del sidebar.
     */
    function actualizarBotonesIdioma() {
        var idiomaActual = window.ASCCGlobal.getIdioma();
        var langBtns     = document.querySelectorAll('.language-btn[data-lang]');

        langBtns.forEach(function (btn) {
            if (btn.getAttribute('data-lang') === idiomaActual) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    /* ─────────────────────────────────────────────────────────
       INICIALIZACIÓN
       ───────────────────────────────────────────────────────── */

    function init() {
        // Verificar que sync-global.js esté disponible
        if (typeof window.ASCCGlobal === 'undefined') {
            console.error('[ASCC] dashboard-sync.js necesita sync-global.js cargado primero.');
            return;
        }

        actualizarToggleTema();
        actualizarBotonesIdioma();

        console.log('[ASCC] dashboard-sync listo | idioma:',
            window.ASCCGlobal.getIdioma(),
            '| tema:',
            window.ASCCGlobal.getTema()
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}(window));