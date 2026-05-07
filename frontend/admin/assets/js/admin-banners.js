/**
 * ASCC - Admin Banners JS
 * Ruta: admin/assets/js/admin-banners.js
 * Descripción: Lógica del módulo de gestión de banners.
 *              Modal, previsualización de imagen, drag & drop,
 *              actualización de orden, lightbox.
 */

'use strict';

// ─────────────────────────────────────────────────────────────────────────────
// REFERENCIAS AL DOM
// ─────────────────────────────────────────────────────────────────────────────
const modal          = document.getElementById('modalCrear');
const modalBackdrop  = document.getElementById('modalBackdrop');
const btnAbrirCrear  = document.getElementById('btnAbrirCrear');
const uploadZone     = document.getElementById('uploadZone');
const inputImagen    = document.getElementById('inputImagen');
const imgPreview     = document.getElementById('imgPreview');
const uploadPH       = document.getElementById('uploadPlaceholder');
const btnClearImg    = document.getElementById('btnClearImg');
const toggleInput    = document.getElementById('chkActivo');
const toggleText     = document.getElementById('toggleText');
const formCrear      = document.getElementById('formCrearBanner');

// ─────────────────────────────────────────────────────────────────────────────
// ABRIR / CERRAR MODAL
// ─────────────────────────────────────────────────────────────────────────────
function abrirModal() {
    if (!modal) return;
    modal.classList.add('ab-modal--open');
    modalBackdrop.classList.add('ab-modal-backdrop--visible');
    document.body.style.overflow = 'hidden';
    // Focus en el primer campo
    setTimeout(function () {
        var primerInput = modal.querySelector('input[type="text"]');
        if (primerInput) primerInput.focus();
    }, 300);
}

function cerrarModal() {
    if (!modal) return;
    modal.classList.remove('ab-modal--open');
    modalBackdrop.classList.remove('ab-modal-backdrop--visible');
    document.body.style.overflow = '';
}

if (btnAbrirCrear) {
    btnAbrirCrear.addEventListener('click', abrirModal);
}

// ─────────────────────────────────────────────────────────────────────────────
// PREVISUALIZACIÓN DE IMAGEN — input file
// ─────────────────────────────────────────────────────────────────────────────
function mostrarPreview(archivo) {
    if (!archivo) return;

    var reader = new FileReader();
    reader.onload = function (e) {
        imgPreview.src = e.target.result;
        imgPreview.style.display  = 'block';
        uploadPH.style.display    = 'none';
        btnClearImg.style.display = 'flex';
        // Ocultar el input debajo de la imagen (sigue funcional)
        inputImagen.style.height = '0';
    };
    reader.readAsDataURL(archivo);
}

function limpiarImagen() {
    imgPreview.src            = '';
    imgPreview.style.display  = 'none';
    uploadPH.style.display    = 'flex';
    btnClearImg.style.display = 'none';
    inputImagen.value         = '';
    inputImagen.style.height  = '';
}

if (inputImagen) {
    inputImagen.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            mostrarPreview(this.files[0]);
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// DRAG & DROP sobre la zona de subida
// ─────────────────────────────────────────────────────────────────────────────
if (uploadZone) {
    ['dragenter', 'dragover'].forEach(function (evt) {
        uploadZone.addEventListener(evt, function (e) {
            e.preventDefault();
            uploadZone.classList.add('ab-upload-zone--dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (evt) {
        uploadZone.addEventListener(evt, function (e) {
            e.preventDefault();
            uploadZone.classList.remove('ab-upload-zone--dragover');
        });
    });

    uploadZone.addEventListener('drop', function (e) {
        var archivos = e.dataTransfer.files;
        if (archivos && archivos[0]) {
            // Verificar que sea imagen antes de transferir al input
            if (!archivos[0].type.startsWith('image/')) {
                alert('Solo se permiten archivos de imagen.');
                return;
            }
            // Transferir al input para que PHP lo reciba en $_FILES
            var dt = new DataTransfer();
            dt.items.add(archivos[0]);
            inputImagen.files = dt.files;
            mostrarPreview(archivos[0]);
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// TOGGLE ACTIVO — actualiza el texto descriptivo en tiempo real
// ─────────────────────────────────────────────────────────────────────────────
if (toggleInput && toggleText) {
    // Textos inyectados desde PHP vía data-attributes o fallback
    var txtActivo   = toggleText.dataset.txtActivo   || 'Activo — Visible en el marketplace';
    var txtInactivo = toggleText.dataset.txtInactivo || 'Inactivo — No se mostrará';

    toggleInput.addEventListener('change', function () {
        toggleText.textContent = this.checked ? txtActivo : txtInactivo;
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTUALIZAR ORDEN — submit silencioso con fetch
// ─────────────────────────────────────────────────────────────────────────────
function actualizarOrden(inputEl) {
    var idBanner   = inputEl.dataset.id;
    var nuevoOrden = parseInt(inputEl.value, 10);

    if (isNaN(nuevoOrden) || nuevoOrden < 0) {
        inputEl.value = 0;
        nuevoOrden = 0;
    }

    var formData = new FormData();
    formData.append('action',    'actualizar_orden');
    formData.append('id_banner', idBanner);
    formData.append('orden',     nuevoOrden);

    fetch('banners.php', {
        method: 'POST',
        body:   formData
    })
    .then(function (res) {
        if (!res.ok) throw new Error('Error de red');
        // Feedback visual sutil: borde verde momentáneo
        inputEl.style.borderColor = '#10b981';
        inputEl.style.boxShadow   = '0 0 0 3px rgba(16,185,129,0.15)';
        setTimeout(function () {
            inputEl.style.borderColor = '';
            inputEl.style.boxShadow   = '';
        }, 1500);
    })
    .catch(function () {
        inputEl.style.borderColor = '#ef4444';
        setTimeout(function () {
            inputEl.style.borderColor = '';
        }, 2000);
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// LIGHTBOX — previsualizar imagen en pantalla completa
// ─────────────────────────────────────────────────────────────────────────────
function abrirPreview(src, titulo) {
    var lb    = document.getElementById('lightboxBanner');
    var lbImg = document.getElementById('lightboxImg');
    if (!lb || !lbImg) return;

    lbImg.src = src;
    lbImg.alt = titulo || '';
    lb.style.display = 'flex';

    requestAnimationFrame(function () {
        lb.classList.add('ap-lightbox--visible');
    });

    document.body.style.overflow = 'hidden';
}

function cerrarLightbox() {
    var lb = document.getElementById('lightboxBanner');
    if (!lb) return;
    lb.classList.remove('ap-lightbox--visible');
    setTimeout(function () {
        lb.style.display = 'none';
        document.body.style.overflow = '';
    }, 200);
}

// ─────────────────────────────────────────────────────────────────────────────
// VALIDACIÓN DEL FORMULARIO ANTES DE ENVIAR
// ─────────────────────────────────────────────────────────────────────────────
if (formCrear) {
    formCrear.addEventListener('submit', function (e) {
        var titulo  = formCrear.querySelector('#titulo');
        var imagen  = formCrear.querySelector('#inputImagen');
        var errores = [];

        if (!titulo || titulo.value.trim() === '') {
            errores.push('El título es obligatorio.');
        }
        if (!imagen || !imagen.files || !imagen.files[0]) {
            errores.push('Debes seleccionar una imagen.');
        }

        if (errores.length > 0) {
            e.preventDefault();
            alert(errores.join('\n'));
            return;
        }

        // Mostrar estado de carga en el botón
        var btnSubmit = document.getElementById('btnSubmitBanner');
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// TECLA ESC — cierra modal o lightbox
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;

    var lb = document.getElementById('lightboxBanner');
    if (lb && lb.classList.contains('ap-lightbox--visible')) {
        cerrarLightbox();
        return;
    }

    if (modal && modal.classList.contains('ab-modal--open')) {
        cerrarModal();
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// FEEDBACK — auto-ocultar el mensaje de éxito/error tras 5 segundos
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var feedback = document.querySelector('.au-feedback');
    if (!feedback) return;

    setTimeout(function () {
        feedback.style.transition = 'opacity 0.5s ease';
        feedback.style.opacity    = '0';
        setTimeout(function () {
            feedback.style.display = 'none';
        }, 500);
    }, 5000);
});