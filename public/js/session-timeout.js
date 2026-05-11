/**
 * ═══════════════════════════════════════════════════════════
 * ASCC — Cierre automático de sesión por inactividad
 * Ruta: public/js/session-timeout.js
 *
 * Cuenta 10 minutos de inactividad en el navegador. Si el
 * usuario no mueve el mouse, escribe, hace clic o toca la
 * pantalla en ese lapso, redirige a /controllers/logout.php
 * (que destruye la sesión y manda a login).
 *
 * El servidor también valida el mismo límite (config/app.php)
 * — este script existe para que el cierre ocurra incluso si el
 * usuario no genera tráfico al servidor.
 * ═══════════════════════════════════════════════════════════
 */
(function () {
    'use strict';

    var TIMEOUT_MS = 10 * 60 * 1000; // 10 minutos
    var LOGOUT_URL = '/ascc/controllers/logout.php?error=sesion_expirada';

    var timerId = null;
    var warningTimerId = null;
    var avisado = false;

    function disparar() {
        window.location.href = LOGOUT_URL;
    }

    function reiniciar() {
        avisado = false;
        if (timerId)        { clearTimeout(timerId); }
        if (warningTimerId) { clearTimeout(warningTimerId); }

        // Aviso 30 s antes del cierre
        warningTimerId = setTimeout(function () {
            if (!avisado) {
                avisado = true;
                try {
                    console.info('[ASCC] Tu sesión se cerrará en 30 segundos por inactividad.');
                } catch (e) { /* noop */ }
            }
        }, TIMEOUT_MS - 30000);

        timerId = setTimeout(disparar, TIMEOUT_MS);
    }

    var eventos = [
        'mousemove', 'mousedown', 'keydown',
        'scroll', 'touchstart', 'click', 'wheel'
    ];

    eventos.forEach(function (evt) {
        document.addEventListener(evt, reiniciar, { passive: true });
    });

    // Si el usuario vuelve a la pestaña después de un buen rato,
    // forzar verificación inmediata recargando (el servidor decide)
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            reiniciar();
        }
    });

    reiniciar();
})();
