/**
 * ASCC — Admin Configuration JS
 * Ruta: admin/assets/js/admin-config.js
 * Depende de: sync-global.js (tema/idioma ya inicializados)
 */

'use strict';

/* =============================================================================
   ESTADO GLOBAL DEL MÓDULO
============================================================================= */

const CfgModule = {
    activeTab:    'general',
    isSaving:     false,
    toastTimer:   null,
    isDirty:      false,   // cambios sin guardar
};

/* =============================================================================
   INIT
============================================================================= */

document.addEventListener('DOMContentLoaded', () => {
    CfgModule.init();
});

CfgModule.init = function () {
    this.bindTabs();
    this.bindGatewayCards();
    this.bindRangeSliders();
    this.bindPasswordToggles();
    this.bindMaintenanceToggle();
    this.bindColorSync();
    this.bindSaveButtons();
    this.bindSmtpTest();
    this.bindDirtyTracking();
    this.bindUploadZones();
    this.restoreActiveTab();
};

/* =============================================================================
   1. TABS
============================================================================= */

CfgModule.bindTabs = function () {
    document.querySelectorAll('.cfg-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            if (!tab) return;
            this.switchTab(tab);
        });
    });
};

CfgModule.switchTab = function (tabName) {
    // Panels
    document.querySelectorAll('.cfg-tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    const target = document.getElementById('cfg-tab-' + tabName);
    if (target) target.classList.add('active');

    // Buttons
    document.querySelectorAll('.cfg-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });

    this.activeTab = tabName;

    // Guardar en sessionStorage para restaurar al recargar
    try { sessionStorage.setItem('cfg_active_tab', tabName); } catch (e) {}
};

CfgModule.restoreActiveTab = function () {
    let saved = null;
    try { saved = sessionStorage.getItem('cfg_active_tab'); } catch (e) {}

    // También leer desde URL hash: ?tab=correo
    const urlParams = new URLSearchParams(window.location.search);
    const urlTab    = urlParams.get('tab');

    const tab = urlTab || saved || 'general';
    this.switchTab(tab);
};

/* =============================================================================
   2. PASARELAS DE PAGO — Selector visual
============================================================================= */

CfgModule.bindGatewayCards = function () {
    document.querySelectorAll('.cfg-gateway-card').forEach(card => {
        card.addEventListener('click', () => {
            // Desmarcar todas
            document.querySelectorAll('.cfg-gateway-card').forEach(c => {
                c.classList.remove('selected');
                const radio = c.querySelector('input[type="radio"]');
                if (radio) radio.checked = false;
            });
            // Marcar la seleccionada
            card.classList.add('selected');
            const radio = card.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;

            this.markDirty();
        });
    });
};

/* =============================================================================
   3. RANGE SLIDERS — Actualizar valor en tiempo real
============================================================================= */

CfgModule.bindRangeSliders = function () {
    document.querySelectorAll('.cfg-range').forEach(range => {
        const valueEl = document.getElementById(range.dataset.valueId);
        if (!valueEl) return;

        // Valor inicial
        valueEl.textContent = range.value;

        range.addEventListener('input', () => {
            valueEl.textContent = range.value;
            this.markDirty();
        });
    });
};

/* =============================================================================
   4. PASSWORD TOGGLES
============================================================================= */

CfgModule.bindPasswordToggles = function () {
    document.querySelectorAll('.cfg-input-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const input    = document.getElementById(targetId);
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type       = isPassword ? 'text' : 'password';
            btn.textContent  = isPassword ? '🙈' : '👁️';
        });
    });
};

/* =============================================================================
   5. MODO MANTENIMIENTO — Banner dinámico
============================================================================= */

CfgModule.bindMaintenanceToggle = function () {
    const toggle = document.getElementById('cfg-mant-toggle');
    const banner = document.getElementById('cfg-mant-banner');
    if (!toggle || !banner) return;

    toggle.addEventListener('change', () => {
        banner.classList.toggle('cfg-maint-banner--hidden', !toggle.checked);
        this.markDirty();
    });
};

/* =============================================================================
   6. COLOR SYNC — Input color ↔ hex text
============================================================================= */

CfgModule.bindColorSync = function () {
    const colorInput = document.getElementById('cfg-color-picker');
    const hexInput   = document.getElementById('cfg-color-hex');
    if (!colorInput || !hexInput) return;

    colorInput.addEventListener('input', () => {
        hexInput.value = colorInput.value;
        this.markDirty();
    });

    hexInput.addEventListener('input', () => {
        const val = hexInput.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
            colorInput.value = val;
        }
        this.markDirty();
    });
};

/* =============================================================================
   7. UPLOAD ZONES — Preview de imagen seleccionada
============================================================================= */

CfgModule.bindUploadZones = function () {
    document.querySelectorAll('.cfg-upload-zone').forEach(zone => {
        const input   = zone.querySelector('input[type="file"]');
        const preview = zone.querySelector('.cfg-upload-preview');
        if (!input) return;

        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) return;

            if (preview && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.innerHTML = `<img src="${e.target.result}"
                        style="width:100%;height:100%;object-fit:contain;border-radius:8px;" alt="preview">`;
                };
                reader.readAsDataURL(file);
            }
            this.markDirty();
        });
    });
};

/* =============================================================================
   8. DIRTY TRACKING — Detectar cambios sin guardar
============================================================================= */

CfgModule.bindDirtyTracking = function () {
    // Track inputs, selects y textareas
    document.querySelectorAll(
        '.cfg-input, .cfg-select, .cfg-textarea, .cfg-switch input'
    ).forEach(el => {
        el.addEventListener('change', () => this.markDirty());
        if (el.tagName === 'INPUT' && el.type !== 'checkbox' && el.type !== 'radio') {
            el.addEventListener('input', () => this.markDirty());
        }
    });

    // Advertir al salir si hay cambios sin guardar
    window.addEventListener('beforeunload', (e) => {
        if (this.isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
};

CfgModule.markDirty = function () {
    this.isDirty = true;
};

CfgModule.markClean = function () {
    this.isDirty = false;
};

/* =============================================================================
   9. GUARDAR — AJAX
============================================================================= */

CfgModule.bindSaveButtons = function () {
    // Botón "Guardar Todo" en topbar
    const btnAll = document.getElementById('cfg-btn-save-all');
    if (btnAll) {
        btnAll.addEventListener('click', () => this.saveAll());
    }

    // Botón "Guardar Cambios" en footer
    const btnTab = document.getElementById('cfg-btn-save-tab');
    if (btnTab) {
        btnTab.addEventListener('click', () => this.saveTab(this.activeTab));
    }

    // Botón "Descartar"
    const btnDiscard = document.getElementById('cfg-btn-discard');
    if (btnDiscard) {
        btnDiscard.addEventListener('click', () => {
            if (!this.isDirty) return;
            if (confirm(window.cfgLang?.discard_confirm || '¿Descartar todos los cambios?')) {
                this.markClean();
                window.location.reload();
            }
        });
    }
};

CfgModule.saveAll = function () {
    if (this.isSaving) return;
    this.performSave('all');
};

CfgModule.saveTab = function (tab) {
    if (this.isSaving) return;
    this.performSave(tab);
};

CfgModule.performSave = function (scope) {
    this.isSaving = true;

    const form = document.getElementById('cfg-form');
    if (!form) {
        this.isSaving = false;
        return;
    }

    const formData = new FormData(form);
    formData.append('scope', scope);
    formData.append('csrf_token', window.csrfToken || '');

    // Agregar checkboxes desmarcados (FormData no los incluye)
    form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        if (!cb.checked) {
            formData.set(cb.name, '0');
        }
    });

    // Estado visual del botón
    const btn = scope === 'all'
        ? document.getElementById('cfg-btn-save-all')
        : document.getElementById('cfg-btn-save-tab');

    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '⏳ ' + (window.cfgLang?.saving || 'Guardando...');
        btn.disabled  = true;
    }

    fetch('/ascc/backend/admin/ajax/config_save.php', {
        method:  'POST',
        body:    formData,
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            this.markClean();
            this.showToast(
                window.cfgLang?.saved_ok || '✅ Configuración guardada correctamente.',
                false
            );
        } else {
            this.showToast(
                data.message || window.cfgLang?.saved_error || '❌ Error al guardar.',
                true
            );
        }
    })
    .catch(() => {
        this.showToast(
            window.cfgLang?.saved_error || '❌ Error de conexión. Intenta de nuevo.',
            true
        );
    })
    .finally(() => {
        this.isSaving = false;
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled  = false;
        }
    });
};

/* =============================================================================
   10. TEST SMTP
============================================================================= */

CfgModule.bindSmtpTest = function () {
    const btn = document.getElementById('cfg-smtp-test-btn');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const originalText = btn.innerHTML;
        btn.innerHTML      = '⏳ Enviando...';
        btn.disabled       = true;

        const csrfToken = window.csrfToken || '';

        fetch('/ascc/backend/admin/ajax/config_save.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'action=test_smtp&csrf_token=' + encodeURIComponent(csrfToken),
        })
        .then(res => res.json())
        .then(data => {
            const isError = !data.success;
            this.showToast(
                data.message || (isError
                    ? (window.cfgLang?.test_smtp_error || '❌ Error SMTP')
                    : (window.cfgLang?.test_smtp_ok   || '📧 Correo enviado')
                ),
                isError
            );
        })
        .catch(() => {
            this.showToast(window.cfgLang?.test_smtp_error || '❌ Error de conexión', true);
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled  = false;
        });
    });
};

/* =============================================================================
   11. TOAST
============================================================================= */

CfgModule.showToast = function (message, isError = false) {
    let toast = document.getElementById('cfg-toast');

    // Crear si no existe
    if (!toast) {
        toast = document.createElement('div');
        toast.id        = 'cfg-toast';
        toast.className = 'cfg-toast';
        document.body.appendChild(toast);
    }

    toast.textContent = message;
    toast.classList.toggle('cfg-toast--error', isError);

    // Mostrar
    clearTimeout(this.toastTimer);
    toast.classList.add('cfg-toast--show');

    this.toastTimer = setTimeout(() => {
        toast.classList.remove('cfg-toast--show');
    }, 3500);
};
