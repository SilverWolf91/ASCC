/**
 * ═══════════════════════════════════════════════════════════
 * ASCC — Modal Actualizar Datos
 * Ruta: C:\xampp\htdocs\ascc\public\js\modal-perfil.js
 *
 * Depende de: agroLang (definido en modal-perfil.php)
 * ═══════════════════════════════════════════════════════════
 */

(function () {
    'use strict';

    /* ── Selectores generales ── */
    const backdrop     = document.getElementById('modalActualizarDatos');
    const btnAbrir     = document.getElementById('btnActualizarDatos');
    const btnCerrar    = backdrop?.querySelector('.agro-modal__close');
    const btnCancelar  = backdrop?.querySelector('.agro-btn-cancel');
    const btnGuardar   = backdrop?.querySelector('.agro-btn-save');
    const tabs         = backdrop?.querySelectorAll('.agro-modal__tab');
    const panels       = backdrop?.querySelectorAll('.agro-tab-panel');
    const form         = document.getElementById('formActualizarDatos');
    const btnUpload    = backdrop?.querySelector('.agro-btn-upload');
    const inputFile    = backdrop?.querySelector('#inputAvatarFile');
    const avatarCircle = backdrop?.querySelector('.agro-avatar-circle');
    const inputNewPass = backdrop?.querySelector('#inputNewPassword');
    const strengthSegs = backdrop?.querySelectorAll('.agro-password-strength__segment');
    const strengthLbl  = backdrop?.querySelector('.agro-password-strength__label');
    const toggles      = backdrop?.querySelectorAll('.agro-toggle');

    /* ── Selectores 2FA ── */
    const emailInput            = backdrop?.querySelector('#inputEmail');
    const originalEmail         = emailInput?.dataset.emailOriginal ?? '';
    const seccionOtpEmail       = backdrop?.querySelector('#seccionOtpEmail');
    const btnEnviarOtpEmail     = backdrop?.querySelector('#btnEnviarOtpEmail');
    const otpEmailWrapper       = backdrop?.querySelector('#inputOtpEmailWrapper');
    const inputOtpEmail         = backdrop?.querySelector('#inputOtpEmail');
    const otpEmailTimer         = backdrop?.querySelector('#otpEmailTimer');

    const seccionOtpPassword    = backdrop?.querySelector('#seccionOtpPassword');
    const btnEnviarOtpPassword  = backdrop?.querySelector('#btnEnviarOtpPassword');
    const otpPasswordWrapper    = backdrop?.querySelector('#inputOtpPasswordWrapper');
    const inputOtpPassword      = backdrop?.querySelector('#inputOtpPassword');
    const otpPasswordTimer      = backdrop?.querySelector('#otpPasswordTimer');

    let toastTimeout    = null;
    let timerEmailId    = null;
    let timerPasswordId = null;

    /* ════════════════════════════════════════════════════════
       ABRIR / CERRAR
    ════════════════════════════════════════════════════════ */
    function openModal() {
        backdrop.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        switchTab(0);
    }

    function closeModal() {
        backdrop.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    if (btnAbrir)   btnAbrir.addEventListener('click', openModal);
    if (btnCerrar)  btnCerrar.addEventListener('click', closeModal);
    if (btnCancelar) btnCancelar.addEventListener('click', closeModal);

    /* Clic fuera del modal */
    backdrop?.addEventListener('click', function (e) {
        if (e.target === backdrop) closeModal();
    });

    /* Tecla ESC */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && backdrop?.classList.contains('is-open')) {
            closeModal();
        }
    });

    /* ════════════════════════════════════════════════════════
       PESTAÑAS
    ════════════════════════════════════════════════════════ */
    function switchTab(index) {
        tabs?.forEach((tab, i) => tab.classList.toggle('is-active', i === index));
        panels?.forEach((panel, i) => panel.classList.toggle('is-active', i === index));
    }

    tabs?.forEach((tab, index) => {
        tab.addEventListener('click', () => switchTab(index));
    });

    /* ════════════════════════════════════════════════════════
       UPLOAD DE AVATAR
    ════════════════════════════════════════════════════════ */
    btnUpload?.addEventListener('click', () => inputFile?.click());

    inputFile?.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            showToast('error', agroLang.avatar_type_error);
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showToast('error', agroLang.avatar_size_error);
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            if (avatarCircle) {
                avatarCircle.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
            }
        };
        reader.readAsDataURL(file);
    });

    /* ════════════════════════════════════════════════════════
       FORTALEZA DE CONTRASEÑA
    ════════════════════════════════════════════════════════ */
    function getPasswordStrength(pwd) {
        let score = 0;
        if (pwd.length >= 8)          score++;
        if (/[A-Z]/.test(pwd))        score++;
        if (/[0-9]/.test(pwd))        score++;
        if (/[^A-Za-z0-9]/.test(pwd)) score++;
        return score;
    }

    const strengthLabels = {
        0: '',
        1: agroLang?.pass_weak   || 'Débil',
        2: agroLang?.pass_fair   || 'Regular',
        3: agroLang?.pass_good   || 'Buena',
        4: agroLang?.pass_strong || 'Fuerte',
    };

    inputNewPass?.addEventListener('input', function () {
        const score = getPasswordStrength(this.value);
        strengthSegs?.forEach((seg, i) => {
            seg.className = 'agro-password-strength__segment';
            if (i < score) seg.classList.add('level-' + score);
        });
        if (strengthLbl) {
            strengthLbl.textContent = this.value.length > 0 ? strengthLabels[score] : '';
        }
    });

    /* ════════════════════════════════════════════════════════
       TOGGLES DE NOTIFICACIONES
    ════════════════════════════════════════════════════════ */
    toggles?.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const isOn = this.classList.toggle('is-on');
            this.setAttribute('aria-pressed', isOn ? 'true' : 'false');
        });
    });

    /* ════════════════════════════════════════════════════════
       2FA — DETECCIÓN DE CAMBIO DE EMAIL
    ════════════════════════════════════════════════════════ */
    emailInput?.addEventListener('input', function () {
        const changed = this.value.trim() !== originalEmail;
        if (seccionOtpEmail) seccionOtpEmail.style.display = changed ? 'block' : 'none';
        if (!changed && otpEmailWrapper) {
            otpEmailWrapper.style.display = 'none';
            if (inputOtpEmail) inputOtpEmail.value = '';
            resetOtpBtn(btnEnviarOtpEmail, 'otp_send_btn');
        }
    });

    btnEnviarOtpEmail?.addEventListener('click', async function () {
        const emailNuevo = emailInput?.value.trim();
        if (!emailNuevo) return;
        await solicitarOtp(this, 'email', emailNuevo, otpEmailWrapper, otpEmailTimer, 'timerEmailId');
    });

    /* ════════════════════════════════════════════════════════
       2FA — DETECCIÓN DE CAMBIO DE CONTRASEÑA
    ════════════════════════════════════════════════════════ */
    function checkPasswordOtpVisibility() {
        const currentPass = form?.querySelector('#inputCurrentPassword')?.value ?? '';
        const newPass     = inputNewPass?.value ?? '';
        const show = currentPass.length > 0 && newPass.length >= 8;
        if (seccionOtpPassword) seccionOtpPassword.style.display = show ? 'block' : 'none';
        if (!show && otpPasswordWrapper) {
            otpPasswordWrapper.style.display = 'none';
            if (inputOtpPassword) inputOtpPassword.value = '';
            resetOtpBtn(btnEnviarOtpPassword, 'otp_send_btn');
        }
    }

    inputNewPass?.addEventListener('input', checkPasswordOtpVisibility);
    form?.querySelector('#inputCurrentPassword')?.addEventListener('input', checkPasswordOtpVisibility);

    btnEnviarOtpPassword?.addEventListener('click', async function () {
        await solicitarOtp(this, 'password', null, otpPasswordWrapper, otpPasswordTimer, 'timerPasswordId');
    });

    /* ════════════════════════════════════════════════════════
       2FA — FUNCIÓN COMPARTIDA PARA SOLICITAR OTP
    ════════════════════════════════════════════════════════ */
    async function solicitarOtp(btn, tipo, emailNuevo, wrapper, timerEl, timerKey) {
        btn.disabled = true;
        btn.textContent = agroLang.otp_sending;

        try {
            const csrf = form?.querySelector('[name="csrf_token"]')?.value ?? '';
            const fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('tipo', tipo);
            if (emailNuevo) fd.append('email_nuevo', emailNuevo);

            const res  = await fetch('/ascc/api/solicitar_otp.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                showToast('success', data.message);
                if (wrapper) wrapper.style.display = 'block';
                iniciarTimer(timerEl, 300, timerKey, function () {
                    btn.disabled = false;
                    btn.textContent = agroLang.otp_resend;
                });
            } else {
                showToast('error', data.message);
                btn.disabled = false;
                btn.textContent = agroLang.otp_send_btn;
            }
        } catch (e) {
            showToast('error', agroLang.network_error);
            btn.disabled = false;
            btn.textContent = agroLang.otp_send_btn;
        }
    }

    function iniciarTimer(timerEl, seconds, timerKey, onExpire) {
        if (timerKey === 'timerEmailId'    && timerEmailId)    clearInterval(timerEmailId);
        if (timerKey === 'timerPasswordId' && timerPasswordId) clearInterval(timerPasswordId);

        let rem = seconds;
        if (timerEl) timerEl.textContent = formatTimer(rem);

        const id = setInterval(function () {
            rem--;
            if (timerEl) timerEl.textContent = formatTimer(rem);
            if (rem <= 0) {
                clearInterval(id);
                if (timerEl) timerEl.textContent = '';
                onExpire();
            }
        }, 1000);

        if (timerKey === 'timerEmailId')    timerEmailId    = id;
        if (timerKey === 'timerPasswordId') timerPasswordId = id;
    }

    function formatTimer(s) {
        return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    }

    function resetOtpBtn(btn, langKey) {
        if (!btn) return;
        btn.disabled = false;
        btn.textContent = agroLang[langKey] ?? 'Enviar código';
        if (timerEmailId    && btn === btnEnviarOtpEmail)    { clearInterval(timerEmailId);    timerEmailId    = null; }
        if (timerPasswordId && btn === btnEnviarOtpPassword) { clearInterval(timerPasswordId); timerPasswordId = null; }
    }

    /* ════════════════════════════════════════════════════════
       VALIDACIÓN
    ════════════════════════════════════════════════════════ */
    function clearErrors() {
        form?.querySelectorAll('.is-error').forEach(el => el.classList.remove('is-error'));
        form?.querySelectorAll('.agro-field__error').forEach(el => el.classList.remove('is-visible'));
    }

    function showFieldError(fieldId, message) {
        const input   = form?.querySelector('#' + fieldId);
        const errorEl = form?.querySelector('#' + fieldId + 'Error');
        input?.classList.add('is-error');
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.add('is-visible');
        }
    }

    function validateForm() {
        clearErrors();
        let valid = true;

        /* Nombre */
        const nombre = form?.querySelector('#inputNombre')?.value.trim();
        if (!nombre) {
            showFieldError('inputNombre', agroLang.validation_required);
            valid = false;
        }

        /* Apellido */
        const apellido = form?.querySelector('#inputApellido')?.value.trim();
        if (!apellido) {
            showFieldError('inputApellido', agroLang.validation_required);
            valid = false;
        }

        /* Email */
        const email = form?.querySelector('#inputEmail')?.value.trim();
        const reEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !reEmail.test(email)) {
            showFieldError('inputEmail', agroLang.validation_email);
            valid = false;
        }

        /* Contraseña (solo si se escribió algo) */
        const newPass     = form?.querySelector('#inputNewPassword')?.value || '';
        const confirmPass = form?.querySelector('#inputConfirmPassword')?.value || '';

        if (newPass || confirmPass) {
            if (newPass.length < 8) {
                showFieldError('inputNewPassword', agroLang.validation_pass_length);
                valid = false;
            } else if (newPass !== confirmPass) {
                showFieldError('inputConfirmPassword', agroLang.validation_pass_match);
                valid = false;
            }
        }

        /* OTP — cambio de email */
        const emailChanged = (emailInput?.value.trim() ?? '') !== originalEmail;
        if (emailChanged) {
            const otpVal = inputOtpEmail?.value.trim() ?? '';
            if (otpVal.length !== 6 || !/^\d{6}$/.test(otpVal)) {
                showFieldError('inputOtpEmail', agroLang.otp_required);
                valid = false;
            }
        }

        /* OTP — cambio de contraseña */
        if (newPass.length >= 8 && newPass === confirmPass) {
            const otpVal = inputOtpPassword?.value.trim() ?? '';
            if (otpVal.length !== 6 || !/^\d{6}$/.test(otpVal)) {
                showFieldError('inputOtpPassword', agroLang.otp_required);
                valid = false;
            }
        }

        return valid;
    }

    /* ════════════════════════════════════════════════════════
       ENVÍO AJAX
    ════════════════════════════════════════════════════════ */
    btnGuardar?.addEventListener('click', async function () {
        if (!validateForm()) {
            /* Ir a la pestaña con el primer error */
            const firstError = form?.querySelector('.is-error');
            if (firstError) {
                panels?.forEach(function (panel, i) {
                    if (panel.contains(firstError)) switchTab(i);
                });
            }
            return;
        }

        setLoading(true);

        try {
            const formData = new FormData(form);

            /* Agregar estados de los toggles */
            toggles?.forEach(function (toggle) {
                const key = toggle.dataset.notifKey;
                if (key) formData.append('notif_' + key, toggle.classList.contains('is-on') ? '1' : '0');
            });

            /* Avatar si se seleccionó */
            const avatarFile = inputFile?.files[0];
            if (avatarFile) formData.append('avatar', avatarFile);

            const response = await fetch('/ascc/controllers/update_profile.php', {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (data.success) {
                showToast('success', data.message || agroLang.profile_updated);
                closeModal();
                updateDashboardUI(data.user || {});
            } else {
                showToast('error', data.message || agroLang.profile_error);
            }

        } catch (err) {
            showToast('error', agroLang.network_error);
        } finally {
            setLoading(false);
        }
    });

    function setLoading(loading) {
        if (!btnGuardar) return;
        btnGuardar.disabled = loading;
        btnGuardar.classList.toggle('is-loading', loading);
    }

    /* ════════════════════════════════════════════════════════
       ACTUALIZAR UI SIN RECARGAR
    ════════════════════════════════════════════════════════ */
    function updateDashboardUI(user) {
        /* Nombre en el sidebar y topbar */
        if (user.nombre) {
            document.querySelectorAll('.user-name, .profile-name').forEach(function (el) {
                el.textContent = (user.nombre + ' ' + (user.apellido || '')).trim();
            });
        }
        /* Avatar en todas las apariciones */
        if (user.avatar_url) {
            document.querySelectorAll('.agro-dash-avatar, .user-avatar-img, .profile-photo-img')
                .forEach(function (el) {
                    if (el.tagName === 'IMG') {
                        el.src = user.avatar_url;
                    } else {
                        el.innerHTML = '<img src="' + user.avatar_url + '" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
                    }
                });
        }
    }

    /* ════════════════════════════════════════════════════════
       MOSTRAR / OCULTAR CONTRASEÑA
    ════════════════════════════════════════════════════════ */
    backdrop?.querySelectorAll('.agro-pass-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = this.closest('.agro-pass-wrap')?.querySelector('input');
            if (!input) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            this.querySelector('.icon-eye').style.display     = showing ? ''     : 'none';
            this.querySelector('.icon-eye-off').style.display = showing ? 'none' : '';
            this.setAttribute('aria-label', showing
                ? (agroLang.show_password || 'Mostrar contraseña')
                : (agroLang.hide_password || 'Ocultar contraseña'));
        });
    });

    /* ════════════════════════════════════════════════════════
       GPS — DETECTAR UBICACIÓN
    ════════════════════════════════════════════════════════ */
    const btnGps   = backdrop?.querySelector('#btnDetectarUbicacion');
    const gpsLabel = backdrop?.querySelector('#gpsLabel');

    /* Normaliza para comparación: NFC + minúsculas */
    function gpsNorm(s) {
        return (s || '').normalize('NFC').toLowerCase().trim();
    }

    /* Elimina tildes para comparación tolerante */
    function gpsSinTildes(s) {
        return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    }

    /* Busca el departamento en el <select> usando 3 estrategias:
       1. Coincidencia exacta NFC
       2. Coincidencia sin tildes
       3. Coincidencia parcial sin tildes (ej: "Bogotá" dentro de "Bogotá D.C.") */
    function mapDepartamento(rawState) {
        if (!rawState) return null;
        const sel = backdrop?.querySelector('#inputDepartamento');
        if (!sel) return null;

        const norm      = gpsNorm(rawState);
        const sinTildes = gpsSinTildes(rawState);

        for (const opt of sel.options) {
            if (gpsNorm(opt.value) === norm) return opt.value;
        }
        for (const opt of sel.options) {
            if (gpsSinTildes(opt.value) === sinTildes) return opt.value;
        }
        for (const opt of sel.options) {
            const optST = gpsSinTildes(opt.value);
            if (optST.includes(sinTildes) || sinTildes.includes(optST)) return opt.value;
        }
        return null;
    }

    function setGpsLoading(loading) {
        if (!btnGps || !gpsLabel) return;
        btnGps.disabled      = loading;
        gpsLabel.textContent = loading ? agroLang.gps_detecting : agroLang.gps_btn;
    }

    btnGps?.addEventListener('click', function () {
        if (!navigator.geolocation) {
            showToast('error', agroLang.gps_not_supported);
            return;
        }

        setGpsLoading(true);

        navigator.geolocation.getCurrentPosition(
            async function (pos) {
                try {
                    const { latitude, longitude } = pos.coords;
                    const url = 'https://nominatim.openstreetmap.org/reverse'
                        + '?lat='    + latitude
                        + '&lon='   + longitude
                        + '&format=json&addressdetails=1&accept-language=es';

                    const res  = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();

                    console.log('[ASCC GPS] Nominatim →', data.address);

                    if ((data.address?.country_code ?? '') !== 'co') {
                        showToast('error', agroLang.gps_not_colombia);
                        return;
                    }

                    const addr    = data.address ?? {};
                    let campos    = 0;

                    /* ── Departamento: intenta state, luego state_district ── */
                    const rawState = addr.state || addr.state_district || '';
                    const dept     = mapDepartamento(rawState);
                    console.log('[ASCC GPS] state:', JSON.stringify(rawState), '→', dept);
                    const selDept  = backdrop?.querySelector('#inputDepartamento');
                    if (selDept && dept) { selDept.value = dept; campos++; }

                    /* ── Municipio: varios campos según el tipo de área ── */
                    const ciudad   = addr.city || addr.town || addr.municipality
                                   || addr.county || addr.village || '';
                    console.log('[ASCC GPS] ciudad:', JSON.stringify(ciudad));
                    const inputMun = backdrop?.querySelector('#inputMunicipio');
                    if (inputMun && ciudad) { inputMun.value = ciudad; campos++; }

                    /* ── Vereda / barrio ── */
                    const barrio     = addr.suburb || addr.neighbourhood || addr.quarter
                                     || addr.residential || '';
                    const inputVereda = backdrop?.querySelector('#inputVereda');
                    if (inputVereda && barrio) { inputVereda.value = barrio; campos++; }

                    if (campos > 0) {
                        showToast('success', agroLang.gps_success);
                    } else {
                        showToast('error', agroLang.gps_error);
                    }
                } catch (e) {
                    console.error('[ASCC GPS] Error:', e);
                    showToast('error', agroLang.gps_error);
                } finally {
                    setGpsLoading(false);
                }
            },
            function (err) {
                showToast('error', err.code === 1 ? agroLang.gps_denied : agroLang.gps_error);
                setGpsLoading(false);
            },
            { timeout: 10000, maximumAge: 300000 }
        );
    });

    /* ════════════════════════════════════════════════════════
       TOAST
    ════════════════════════════════════════════════════════ */
    function showToast(type, message) {
        let toast = document.getElementById('agroToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'agroToast';
            toast.className = 'agro-toast';
            document.body.appendChild(toast);
        }

        toast.className = 'agro-toast agro-toast--' + type;
        toast.textContent = message;

        clearTimeout(toastTimeout);
        requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        toastTimeout = setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 3500);
    }

})();