/**
 * ASCC - Admin Notifications JS
 * Ruta: admin/assets/js/admin-notifications.js
 */

'use strict';

// ─────────────────────────────────────────────────────────────────────────────
// REFERENCIAS
// ─────────────────────────────────────────────────────────────────────────────
var modal        = document.getElementById('modalCrear');
var modalDetalle = document.getElementById('modalDetalle');
var backdrop     = document.getElementById('modalBackdrop');
var formCrear    = document.getElementById('formCrearNotif');

// ─────────────────────────────────────────────────────────────────────────────
// ABRIR / CERRAR MODAL CREAR
// ─────────────────────────────────────────────────────────────────────────────
function abrirModal() {
    if (!modal || !backdrop) return;
    modal.classList.add('an-modal--open');
    backdrop.classList.add('an-modal-backdrop--visible');
    document.body.style.overflow = 'hidden';
    setTimeout(function () {
        var f = modal.querySelector('#n_titulo');
        if (f) f.focus();
    }, 300);
}

function cerrarModal() {
    if (!modal || !backdrop) return;
    modal.classList.remove('an-modal--open');
    backdrop.classList.remove('an-modal-backdrop--visible');
    document.body.style.overflow = '';
}

var btnAbrir = document.getElementById('btnAbrirCrear');
if (btnAbrir) btnAbrir.addEventListener('click', abrirModal);

// ─────────────────────────────────────────────────────────────────────────────
// ABRIR / CERRAR MODAL DETALLE
// ─────────────────────────────────────────────────────────────────────────────
function verDetalle(data) {
    if (!modalDetalle || !backdrop) return;

    var LANG   = window.NOTIF_LANG || {};
    var tipo   = data.tipo || 'info';
    var icono  = LANG.tipoIconos ? (LANG.tipoIconos[tipo] || 'fa-info-circle') : 'fa-info-circle';
    var clase  = LANG.tipoClases ? (LANG.tipoClases[tipo] || 'an-tipo--info')  : 'an-tipo--info';

    // Header dinámico por tipo
    var header = document.getElementById('detalleHeader');
    if (header) {
        header.className = 'an-modal__header an-modal__header--' + tipo;
    }

    var iconEl = document.getElementById('detalleIcono');
    if (iconEl) iconEl.innerHTML = '<i class="fas ' + icono + '"></i>';

    var titEl = document.getElementById('detalleTitulo');
    if (titEl) titEl.textContent = data.titulo || '—';

    var destEl = document.getElementById('detalleDest');
    if (destEl) destEl.textContent = data.dest || '—';

    var fechaEl = document.getElementById('detalleFecha');
    if (fechaEl) fechaEl.textContent = data.fecha || '—';

    var leidasEl = document.getElementById('detalleLeidas');
    if (leidasEl) leidasEl.textContent = (data.leidas !== undefined ? data.leidas : '—');

    var msgEl = document.getElementById('detalleMensaje');
    if (msgEl) msgEl.textContent = data.mensaje || '—';

    modalDetalle.classList.add('an-modal--open');
    backdrop.classList.add('an-modal-backdrop--visible');
    document.body.style.overflow = 'hidden';
}

function cerrarDetalle() {
    if (!modalDetalle || !backdrop) return;
    modalDetalle.classList.remove('an-modal--open');
    backdrop.classList.remove('an-modal-backdrop--visible');
    document.body.style.overflow = '';
}

// ─────────────────────────────────────────────────────────────────────────────
// TOGGLE DESTINATARIO — rol vs individual
// ─────────────────────────────────────────────────────────────────────────────
function toggleDestinatario() {
    var sel         = document.getElementById('n_dest_tipo');
    var wrapRol     = document.getElementById('wrapDestRol');
    var wrapUsuario = document.getElementById('wrapDestUsuario');
    if (!sel || !wrapRol || !wrapUsuario) return;

    if (sel.value === 'individual') {
        wrapRol.style.display     = 'none';
        wrapUsuario.style.display = 'block';
    } else {
        wrapRol.style.display     = 'block';
        wrapUsuario.style.display = 'none';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PREVIEW EN TIEMPO REAL
// ─────────────────────────────────────────────────────────────────────────────
function actualizarPreview() {
    var LANG       = window.NOTIF_LANG || {};
    var tipoSel    = document.getElementById('n_tipo');
    var tituloSel  = document.getElementById('n_titulo');
    var mensajeSel = document.getElementById('n_mensaje');
    var preview    = document.getElementById('notifPreview');
    var prevIcon   = document.getElementById('previewIcon');
    var prevTitle  = document.getElementById('previewTitle');
    var prevMsg    = document.getElementById('previewMsg');

    if (!preview) return;

    var tipo   = tipoSel  ? tipoSel.value  : 'info';
    var titulo = tituloSel  ? tituloSel.value.trim()  : '';
    var msg    = mensajeSel ? mensajeSel.value.trim() : '';
    var icono  = LANG.tipoIconos ? (LANG.tipoIconos[tipo] || 'fa-info-circle') : 'fa-info-circle';

    // Actualizar clase del preview
    preview.className = 'an-preview an-preview--' + tipo;

    // Ícono
    if (prevIcon) prevIcon.innerHTML = '<i class="fas ' + icono + '"></i>';

    // Título
    if (prevTitle) {
        prevTitle.textContent = titulo || (LANG.previewPlaceholder || 'Escribe un título...');
    }

    // Mensaje
    if (prevMsg) {
        prevMsg.textContent = msg || (LANG.previewMsgPlaceholder || 'El mensaje aparecerá aquí');
    }
}

// Conectar el preview a los inputs de título y mensaje
document.addEventListener('DOMContentLoaded', function () {
    var tituloInput  = document.getElementById('n_titulo');
    var mensajeInput = document.getElementById('n_mensaje');

    if (tituloInput)  tituloInput.addEventListener('input',  actualizarPreview);
    if (mensajeInput) mensajeInput.addEventListener('input', actualizarPreview);

    // Inicializar preview
    actualizarPreview();
});

// ─────────────────────────────────────────────────────────────────────────────
// CONTADOR DE CARACTERES DEL MENSAJE
// ─────────────────────────────────────────────────────────────────────────────
function actualizarContador(textarea) {
    var contador = document.getElementById('contadorMensaje');
    if (!contador) return;
    var len = textarea.value.length;
    contador.textContent = len;
    // Advertencia visual cuando se acerca al límite
    contador.style.color = len > 900 ? '#ef4444' : (len > 700 ? '#d97706' : '');
}

// ─────────────────────────────────────────────────────────────────────────────
// VALIDACIÓN ANTES DE ENVIAR
// ─────────────────────────────────────────────────────────────────────────────
if (formCrear) {
    formCrear.addEventListener('submit', function (e) {
        var titulo   = document.getElementById('n_titulo');
        var mensaje  = document.getElementById('n_mensaje');
        var destTipo = document.getElementById('n_dest_tipo');
        var destUser = document.getElementById('n_dest_usuario');
        var errores  = [];

        if (!titulo || titulo.value.trim() === '') {
            errores.push('El título es obligatorio.');
        }
        if (!mensaje || mensaje.value.trim() === '') {
            errores.push('El mensaje es obligatorio.');
        }
        if (destTipo && destTipo.value === 'individual') {
            if (!destUser || destUser.value === '') {
                errores.push('Selecciona un usuario destinatario.');
            }
        }

        if (errores.length > 0) {
            e.preventDefault();
            alert(errores.join('\n'));
            return;
        }

        var btn = document.getElementById('btnSubmitNotif');
        if (btn) {
            btn.disabled     = true;
            btn.innerHTML    = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// TECLA ESC
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (modalDetalle && modalDetalle.classList.contains('an-modal--open')) {
        cerrarDetalle();
        return;
    }
    if (modal && modal.classList.contains('an-modal--open')) {
        cerrarModal();
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-OCULTAR FEEDBACK TRAS 5 SEGUNDOS
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var feedback = document.querySelector('.au-feedback');
    if (!feedback) return;
    setTimeout(function () {
        feedback.style.transition = 'opacity 0.5s ease';
        feedback.style.opacity    = '0';
        setTimeout(function () { feedback.style.display = 'none'; }, 500);
    }, 5000);
});