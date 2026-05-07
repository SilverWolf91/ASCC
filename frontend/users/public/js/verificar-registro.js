/**
 * ASCC — Verificación de Registro
 * Ruta: public/js/verificar-registro.js
 *
 * - Countdown de 5 minutos con alerta visual en los últimos 60s
 * - Reenvío de código via AJAX con límite de velocidad
 * - Formateo automático del input (mayúsculas)
 */

(function () {
    'use strict';

    const form        = document.getElementById('formVerificar');
    const tokenInput  = document.getElementById('tokenInput');
    const btnVerify   = document.getElementById('btnVerify');
    const btnResend   = document.getElementById('btnResend');
    const timerEl     = document.getElementById('countdownTimer');
    const expiredMsg  = document.getElementById('countdownExpired');

    /* ── Tiempo restante desde el servidor ─────────────────── */
    const expiryTs  = parseInt(document.getElementById('expiryTs')?.value ?? '0', 10);
    const nowTs     = Math.floor(Date.now() / 1000);
    let   remaining = Math.max(0, expiryTs - nowTs);

    /* ── Formatear mm:ss ──────────────────────────────────── */
    function fmt(s) {
        const m = String(Math.floor(s / 60)).padStart(2, '0');
        const c = String(s % 60).padStart(2, '0');
        return m + ':' + c;
    }

    /* ── Actualizar el timer en el DOM ───────────────────── */
    function tick() {
        if (!timerEl) return;

        timerEl.textContent = fmt(remaining);

        if (remaining <= 60) {
            timerEl.classList.add('is-urgent');
        }

        if (remaining <= 0) {
            clearInterval(intervalId);
            timerEl.textContent = '00:00';
            if (expiredMsg) expiredMsg.style.display = 'block';
            if (btnVerify) btnVerify.disabled = true;
            timerEl.classList.add('is-urgent');
        }

        remaining--;
    }

    tick();
    const intervalId = setInterval(tick, 1000);

    /* ── Auto-formato del input ──────────────────────────── */
    tokenInput?.addEventListener('input', function () {
        /* Solo letras y números, máximo 8 */
        this.value = this.value.replace(/[^A-Za-z0-9]/g, '').slice(0, 8);
        this.classList.remove('is-error');
    });

    /* ── Validación antes de enviar ──────────────────────── */
    form?.addEventListener('submit', function (e) {
        const val = tokenInput?.value.trim() ?? '';

        if (val.length !== 8) {
            e.preventDefault();
            tokenInput?.classList.add('is-error');
            tokenInput?.focus();
        }
    });

    /* ── Reenviar código via AJAX ─────────────────────────── */
    btnResend?.addEventListener('click', async function () {
        this.disabled = true;
        this.classList.add('is-loading');

        try {
            const res  = await fetch('/ascc/backend/users/api/reenviar_token_registro.php', { method: 'POST' });
            const data = await res.json();

            showToast(data.success ? 'success' : 'error', data.message);

            if (data.success) {
                /* Reiniciar contador */
                remaining  = 300;
                timerEl?.classList.remove('is-urgent');
                if (expiredMsg) expiredMsg.style.display = 'none';
                if (btnVerify)  btnVerify.disabled = false;

                clearInterval(intervalId);
                const newId = setInterval(tick, 1000);
                tick();

                /* Esperar 60s antes de permitir otro reenvío */
                setTimeout(function () {
                    btnResend.disabled = false;
                    btnResend.classList.remove('is-loading');
                }, 60000);
            } else {
                this.disabled = false;
                this.classList.remove('is-loading');
            }
        } catch (e) {
            showToast('error', 'Error de conexión. Verifica tu internet.');
            this.disabled = false;
            this.classList.remove('is-loading');
        }
    });

    /* ── Toast ────────────────────────────────────────────── */
    function showToast(type, message) {
        let toast = document.getElementById('vr-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'vr-toast';
            Object.assign(toast.style, {
                position:     'fixed',
                bottom:       '28px',
                left:         '50%',
                transform:    'translateX(-50%) translateY(16px)',
                background:   '#fff',
                border:       '1px solid #e5e7eb',
                borderRadius: '10px',
                padding:      '12px 22px',
                fontSize:     '14px',
                boxShadow:    '0 4px 20px rgba(0,0,0,.15)',
                zIndex:       '9999',
                opacity:      '0',
                transition:   'opacity .25s, transform .25s',
                whiteSpace:   'nowrap',
                fontFamily:   'Segoe UI, Arial, sans-serif',
            });
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.style.color       = type === 'success' ? '#2D5016' : '#dc2626';
        toast.style.borderColor = type === 'success' ? '#bbf7d0' : '#fecaca';
        toast.style.opacity     = '1';
        toast.style.transform   = 'translateX(-50%) translateY(0)';

        setTimeout(function () {
            toast.style.opacity   = '0';
            toast.style.transform = 'translateX(-50%) translateY(16px)';
        }, 3800);
    }

})();
