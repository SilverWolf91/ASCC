/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - SINCRONIZACIÓN GLOBAL
 * Ruta: public/js/sync-global.js
 *
 * CORRECCIÓN APLICADA:
 *   cambiarIdioma() y cambiarTema() ahora recargan también
 *   todos los iframes activos en la página (crear_producto.php,
 *   catalogo.php) para que el cambio de idioma/tema se aplique
 *   dentro del iframe y no solo en el dashboard padre.
 * ═══════════════════════════════════════════════════════════
 */

// Evitar doble carga
if (window.ASCCGlobal) {
    console.log('[ASCC] sync-global ya cargado, saltando...');
} else {

    (function () {
        'use strict';

        function getCookie(nombre) {
            var pares = document.cookie.split(';');
            for (var i = 0; i < pares.length; i++) {
                var partes = pares[i].trim().split('=');
                if (partes[0] === nombre) {
                    return partes[1] ? decodeURIComponent(partes[1]) : null;
                }
            }
            return null;
        }

        function setCookie(nombre, valor) {
            document.cookie = nombre + '=' + valor
                + '; path=/'
                + '; max-age=31536000'
                + '; SameSite=Lax';
        }

        function getIdioma() {
            return getCookie('ascc_lang') || 'es';
        }

        function getTema() {
            return getCookie('ascc_theme') || 'light';
        }

        /**
         * Recarga todos los iframes visibles en la página.
         * Se llama antes de recargar el dashboard para que
         * el iframe también cargue con el nuevo idioma/tema.
         * CORRECCIÓN: sin esto, el iframe quedaba con el
         * idioma/tema anterior aunque el dashboard cambiara.
         */
        function recargarIframes() {
            var iframes = document.querySelectorAll('iframe');
            iframes.forEach(function (iframe) {
                try {
                    var src = iframe.src;
                    if (src) {
                        iframe.src = src;
                    }
                } catch (e) {
                    console.warn('[ASCC] No se pudo recargar iframe:', e);
                }
            });
        }

        function cambiarIdioma(idioma) {
            if (idioma !== 'es' && idioma !== 'en') { return; }
            setCookie('ascc_lang', idioma);
            // CORRECCIÓN: recargar iframes antes de recargar el dashboard
            recargarIframes();
            window.location.reload();
        }

        function cambiarTema(tema) {
            if (tema !== 'light' && tema !== 'dark') { return; }
            setCookie('ascc_theme', tema);
            // CORRECCIÓN: recargar iframes antes de recargar el dashboard
            recargarIframes();
            window.location.reload();
        }

        function toggleTema() {
            var temaActual = getTema();
            var temaNuevo  = temaActual === 'light' ? 'dark' : 'light';
            cambiarTema(temaNuevo);
        }

        window.ASCCGlobal = {
            cambiarIdioma : cambiarIdioma,
            cambiarTema   : cambiarTema,
            toggleTema    : toggleTema,
            getIdioma     : getIdioma,
            getTema       : getTema
        };

        console.log('[ASCC] sync-global listo | idioma:', getIdioma(), '| tema:', getTema());

    }());

}