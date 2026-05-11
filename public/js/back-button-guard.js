/**
 * ═══════════════════════════════════════════════════════════
 * ASCC — Escudo del botón "atrás"
 * Ruta: public/js/back-button-guard.js
 *
 * Intercepta el botón "atrás" del navegador / celular para
 * que el usuario no salga de la app por accidente. Muestra
 * un diálogo de confirmación amistoso con dos botones:
 *   - Sí, salir   → redirige a logout y cierra sesión
 *   - No, quedarme → mantiene al usuario en su página
 *
 * Funciona sobre el evento popstate empujando un estado
 * "dummy" en el historial. Carga textos desde window.ASCC_I18N
 * inyectado por partials/header.php.
 * ═══════════════════════════════════════════════════════════
 */
(function () {
    'use strict';

    /* ── Textos (fallback en español si no hay i18n) ─────── */
    var I18N = (typeof window.ASCC_I18N === 'object' && window.ASCC_I18N) ? window.ASCC_I18N : {};
    var T = {
        title:   I18N.exit_app_title   || '¿Salir de ASCC?',
        message: I18N.exit_app_message || 'Si sales, tu sesión se cerrará y tendrás que iniciar sesión de nuevo.',
        yes:     I18N.exit_app_yes     || 'Sí, salir',
        no:      I18N.exit_app_no      || 'No, quedarme'
    };

    var LOGOUT_URL = '/ascc/controllers/logout.php?origen=back_button';
    var modalAbierto = false;

    /* ── Crear el modal en el DOM ────────────────────────── */
    function crearModal() {
        var overlay = document.createElement('div');
        overlay.id = 'asccExitOverlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'asccExitTitle');
        overlay.innerHTML =
            '<div class="ascc-exit-card">' +
                '<div class="ascc-exit-icon">🚪</div>' +
                '<h2 id="asccExitTitle" class="ascc-exit-title"></h2>' +
                '<p class="ascc-exit-msg"></p>' +
                '<div class="ascc-exit-actions">' +
                    '<button type="button" class="ascc-exit-btn ascc-exit-btn-no"></button>' +
                    '<button type="button" class="ascc-exit-btn ascc-exit-btn-yes"></button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(overlay);

        overlay.querySelector('.ascc-exit-title').textContent      = T.title;
        overlay.querySelector('.ascc-exit-msg').textContent        = T.message;
        overlay.querySelector('.ascc-exit-btn-yes').textContent    = T.yes;
        overlay.querySelector('.ascc-exit-btn-no').textContent     = T.no;

        overlay.querySelector('.ascc-exit-btn-yes').addEventListener('click', salir);
        overlay.querySelector('.ascc-exit-btn-no').addEventListener('click', cerrarModal);

        /* Click fuera del card = quedarse */
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) { cerrarModal(); }
        });

        /* Tecla Esc = quedarse */
        document.addEventListener('keydown', function (e) {
            if (modalAbierto && (e.key === 'Escape' || e.key === 'Esc')) {
                cerrarModal();
            }
        });

        return overlay;
    }

    /* ── Inyectar CSS ─────────────────────────────────────── */
    function inyectarEstilos() {
        if (document.getElementById('asccExitStyles')) { return; }
        var s = document.createElement('style');
        s.id = 'asccExitStyles';
        s.textContent =
            '#asccExitOverlay{position:fixed;inset:0;background:rgba(0,0,0,.55);' +
            'display:none;align-items:center;justify-content:center;z-index:99999;' +
            'padding:18px;animation:asccExitFade .2s ease both;' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}' +
            '#asccExitOverlay.is-visible{display:flex;}' +
            '@keyframes asccExitFade{from{opacity:0}to{opacity:1}}' +
            '.ascc-exit-card{background:#fff;border-radius:18px;max-width:380px;width:100%;' +
            'padding:26px 22px 22px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.35);' +
            'animation:asccExitPop .25s cubic-bezier(.34,1.56,.64,1) both;}' +
            '@keyframes asccExitPop{from{transform:scale(.85);opacity:0}to{transform:scale(1);opacity:1}}' +
            '.ascc-exit-icon{font-size:44px;margin-bottom:10px;line-height:1;}' +
            '.ascc-exit-title{margin:0 0 10px;font-size:1.2rem;font-weight:800;color:#065F46;line-height:1.3;}' +
            '.ascc-exit-msg{margin:0 0 22px;font-size:.95rem;color:#374151;line-height:1.5;}' +
            '.ascc-exit-actions{display:flex;gap:10px;flex-direction:column-reverse;}' +
            '.ascc-exit-btn{padding:13px 18px;border-radius:12px;font-size:15px;font-weight:700;' +
            'cursor:pointer;border:none;transition:transform .15s,box-shadow .2s,opacity .2s;font-family:inherit;}' +
            '.ascc-exit-btn-no{background:#ECFDF5;color:#065F46;border:1.5px solid #10B981;}' +
            '.ascc-exit-btn-no:hover{background:#D1FAE5;}' +
            '.ascc-exit-btn-yes{background:linear-gradient(135deg,#F59E0B,#D97706);color:#fff;' +
            'box-shadow:0 6px 18px rgba(245,158,11,.35);}' +
            '.ascc-exit-btn-yes:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(245,158,11,.45);}' +
            '.ascc-exit-btn:active{transform:translateY(0);}' +
            '[data-theme="dark"] .ascc-exit-card{background:#1F2937;}' +
            '[data-theme="dark"] .ascc-exit-title{color:#34D399;}' +
            '[data-theme="dark"] .ascc-exit-msg{color:#D1D5DB;}' +
            '[data-theme="dark"] .ascc-exit-btn-no{background:#064E3B;color:#A7F3D0;border-color:#10B981;}' +
            '[data-theme="dark"] .ascc-exit-btn-no:hover{background:#065F46;}' +
            '@media (min-width:520px){.ascc-exit-actions{flex-direction:row;}.ascc-exit-btn{flex:1;}}' +
            '@media (prefers-reduced-motion:reduce){' +
            '#asccExitOverlay,.ascc-exit-card{animation:none;}}';
        document.head.appendChild(s);
    }

    /* ── Lógica del escudo ────────────────────────────────── */
    var overlay;

    function abrirModal() {
        if (modalAbierto) { return; }
        modalAbierto = true;
        if (!overlay) { overlay = crearModal(); }
        overlay.classList.add('is-visible');

        /* Enfocar el botón "No" por seguridad (evita acciones destructivas accidentales) */
        var btnNo = overlay.querySelector('.ascc-exit-btn-no');
        if (btnNo) {
            setTimeout(function () { btnNo.focus(); }, 50);
        }
    }

    function cerrarModal() {
        if (!modalAbierto) { return; }
        modalAbierto = false;
        if (overlay) { overlay.classList.remove('is-visible'); }
        /* Volver a empujar un estado para que el próximo "atrás" también dispare el modal */
        try { history.pushState({ asccGuard: true }, '', location.href); } catch (e) { /* noop */ }
    }

    function salir() {
        modalAbierto = false;
        window.location.href = LOGOUT_URL;
    }

    function onPopState(e) {
        /* Si el modal ya está visible y se toca "atrás" otra vez → tratar como "No" */
        if (modalAbierto) {
            cerrarModal();
            return;
        }
        abrirModal();
    }

    /* ── Inicialización ───────────────────────────────────── */
    inyectarEstilos();

    /* Empujar marca inicial para que el primer "atrás" sea capturable */
    try {
        history.pushState({ asccGuard: true }, '', location.href);
    } catch (e) { /* noop si el navegador es muy viejo */ }

    window.addEventListener('popstate', onPopState);
})();
