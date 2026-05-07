/**
 * ASCC - Admin Reviews JavaScript
 * Ruta: admin/assets/js/admin-reviews.js
 * Descripción: Lógica de la página de gestión de reseñas.
 *              Ver detalle en modal, eliminar via AJAX, toast de feedback.
 */

'use strict';

/* =============================================================================
   MÓDULO: Reviews Admin
============================================================================= */
const AgReviews = {

    // Reseña actualmente seleccionada para eliminar
    _pendingDelete: {
        id:   null,
        tipo: null,
    },

    init() {
        this._bindSearch();
        console.info('✅ ASCC Admin Reviews iniciado.');
    },

    // ── Buscador con submit automático al escribir ───────────
    _bindSearch() {
        const form  = document.querySelector('.ag-reviews-filters__form');
        const input = form ? form.querySelector('input[name="q"]') : null;
        if (!input) return;

        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => form.submit(), 500);
        });
    },

    // ── Ver detalle de una reseña en modal ───────────────────
    verDetalle(resena) {
        const body = document.getElementById('reviewModalBody');
        if (!body) return;

        // Construir estrellas
        let estrellas = '';
        for (let i = 1; i <= 5; i++) {
            const filled = i <= resena.calificacion;
            estrellas += `<i class="fas fa-star ag-reviews-stars__${filled ? 'filled' : 'empty'}"
                            style="font-size:.9rem"></i>`;
        }

        // Etiqueta de tipo
        const tipoLabels = {
            producto:  window.ASCC?.lang?.reviews_tipo_producto  || 'Producto',
            vendedor:  window.ASCC?.lang?.reviews_tipo_vendedor  || 'Vendedor',
            comprador: window.ASCC?.lang?.reviews_tipo_comprador || 'Comprador',
        };

        body.innerHTML = `
            <div class="ag-modal-detail-row">
                <span class="ag-modal-detail-row__label">
                    ${window.ASCC?.lang?.reviews_col_tipo || 'Tipo'}
                </span>
                <span class="ag-modal-detail-row__value">
                    <span class="ag-reviews-type-badge ag-reviews-type-badge--${AgUtils.esc(resena.tipo)}">
                        ${AgUtils.esc(tipoLabels[resena.tipo] || resena.tipo)}
                    </span>
                </span>
            </div>
            <div class="ag-modal-detail-row">
                <span class="ag-modal-detail-row__label">
                    ${window.ASCC?.lang?.reviews_col_autor || 'Autor'}
                </span>
                <span class="ag-modal-detail-row__value">
                    <strong>${AgUtils.esc(resena.autor_nombre)}</strong>
                </span>
            </div>
            <div class="ag-modal-detail-row">
                <span class="ag-modal-detail-row__label">
                    ${window.ASCC?.lang?.reviews_col_entidad || 'Entidad'}
                </span>
                <span class="ag-modal-detail-row__value">
                    ${AgUtils.esc(resena.entidad_nombre)}
                </span>
            </div>
            <div class="ag-modal-detail-row">
                <span class="ag-modal-detail-row__label">
                    ${window.ASCC?.lang?.reviews_col_calificacion || 'Calificación'}
                </span>
                <span class="ag-modal-detail-row__value">
                    <span style="display:flex;align-items:center;gap:4px">
                        ${estrellas}
                        <strong style="margin-left:6px">${resena.calificacion}/5</strong>
                    </span>
                </span>
            </div>
            ${resena.titulo ? `
            <div class="ag-modal-detail-row">
                <span class="ag-modal-detail-row__label">
                    ${window.ASCC?.lang?.reviews_col_titulo || 'Título'}
                </span>
                <span class="ag-modal-detail-row__value">
                    <strong>${AgUtils.esc(resena.titulo)}</strong>
                </span>
            </div>` : ''}
            <div class="ag-modal-detail-row">
                <span class="ag-modal-detail-row__label">
                    ${window.ASCC?.lang?.reviews_col_comentario || 'Comentario'}
                </span>
                <span class="ag-modal-detail-row__value" style="line-height:1.65">
                    ${AgUtils.esc(resena.comentario)}
                </span>
            </div>
            <div class="ag-modal-detail-row">
                <span class="ag-modal-detail-row__label">
                    ${window.ASCC?.lang?.reviews_col_fecha || 'Fecha'}
                </span>
                <span class="ag-modal-detail-row__value">
                    ${AgUtils.esc(resena.fecha_resena)}
                </span>
            </div>
        `;

        // Configurar botón eliminar del modal
        const btnEliminar = document.getElementById('btnEliminarModal');
        if (btnEliminar) {
            btnEliminar.onclick = () => {
                this.cerrarModal();
                this.confirmarEliminar(resena.id_resena, resena.tipo);
            };
        }

        this._abrirModal('reviewModal');
    },

    // ── Abrir modal de confirmación de eliminación ───────────
    confirmarEliminar(id, tipo) {
        this._pendingDelete.id   = id;
        this._pendingDelete.tipo = tipo;

        const btnConfirmar = document.getElementById('btnConfirmarEliminar');
        if (btnConfirmar) {
            btnConfirmar.onclick = () => this._ejecutarEliminar();
        }

        this._abrirModal('deleteModal');
    },

    // ── Ejecutar eliminación via AJAX ────────────────────────
    _ejecutarEliminar() {
        const { id, tipo } = this._pendingDelete;
        if (!id || !tipo) return;

        const btnConfirmar = document.getElementById('btnConfirmarEliminar');
        if (btnConfirmar) {
            btnConfirmar.disabled    = true;
            btnConfirmar.innerHTML   = '<i class="fas fa-spinner fa-spin"></i>';
        }

        const form = new FormData();
        form.append('action',    'delete');
        form.append('tipo',      tipo);
        form.append('id_resena', id);

        fetch('../api/reviews.php', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    form,
        })
        .then(r => r.json())
        .then(data => {
            this.cerrarDeleteModal();
            if (data.success) {
                // Animar y quitar la fila de la tabla
                const fila = document.querySelector(`tr[data-id="${id}"]`);
                if (fila) {
                    fila.style.transition = 'opacity .3s, transform .3s';
                    fila.style.opacity    = '0';
                    fila.style.transform  = 'scale(.98)';
                    setTimeout(() => {
                        fila.remove();
                        this._actualizarContador(-1);
                    }, 320);
                } else {
                    // Si no hay data-id en la fila, recargar la página
                    setTimeout(() => window.location.reload(), 400);
                }
                AgToast.show(
                    window.ASCC?.lang?.reviews_deleted || 'Reseña eliminada.',
                    'success'
                );
            } else {
                AgToast.show(
                    window.ASCC?.lang?.reviews_error || 'Error al eliminar.',
                    'error'
                );
            }
        })
        .catch(() => {
            this.cerrarDeleteModal();
            AgToast.show(
                window.ASCC?.lang?.reviews_error || 'Error de conexión.',
                'error'
            );
        })
        .finally(() => {
            if (btnConfirmar) {
                btnConfirmar.disabled  = false;
                btnConfirmar.innerHTML = '<i class="fas fa-trash-alt"></i> Eliminar';
            }
            this._pendingDelete = { id: null, tipo: null };
        });
    },

    // ── Actualizar contador en cabecera de tabla ─────────────
    _actualizarContador(delta) {
        const contador = document.querySelector('.ag-reviews-count');
        if (!contador) return;
        const actual = parseInt(contador.textContent, 10) || 0;
        contador.textContent = Math.max(0, actual + delta);
    },

    // ── Helpers de modal ─────────────────────────────────────
    _abrirModal(id) {
        const overlay = document.getElementById(id);
        if (!overlay) return;
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.add('ag-modal-overlay--visible');
        document.body.style.overflow = 'hidden';
    },

    cerrarModal() {
        const overlay = document.getElementById('reviewModal');
        if (!overlay) return;
        overlay.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('ag-modal-overlay--visible');
        document.body.style.overflow = '';
    },

    cerrarDeleteModal() {
        const overlay = document.getElementById('deleteModal');
        if (!overlay) return;
        overlay.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('ag-modal-overlay--visible');
        document.body.style.overflow = '';
    },
};

/* =============================================================================
   MÓDULO: Toast de notificaciones
============================================================================= */
const AgToast = {
    _timer: null,

    show(msg, tipo = 'success') {
        const el = document.getElementById('agToast');
        if (!el) return;

        clearTimeout(this._timer);

        el.textContent = msg;
        el.className   = `ag-toast ag-toast--${tipo} ag-toast--visible`;

        this._timer = setTimeout(() => {
            el.classList.remove('ag-toast--visible');
        }, 3500);
    },
};

/* =============================================================================
   Utilidad local de escape XSS
============================================================================= */
const AgUtils = {
    esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    },
    getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    },
    setCookie(name, value, days = 365) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = `${name}=${encodeURIComponent(value)};expires=${expires};path=/;SameSite=Lax`;
    },
};

/* =============================================================================
   Exponer al scope global para los onclick del PHP
============================================================================= */
window.verDetalle       = (r)      => AgReviews.verDetalle(r);
window.confirmarEliminar = (id, t) => AgReviews.confirmarEliminar(id, t);
window.cerrarModal      = ()       => AgReviews.cerrarModal();
window.cerrarDeleteModal = ()      => AgReviews.cerrarDeleteModal();
window.switchLang       = (lang)   => {
    AgUtils.setCookie('ag_lang', lang);
    fetch('../api/set-lang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lang }),
    }).finally(() => window.location.reload());
};

/* =============================================================================
   Inicialización — DOM Ready
============================================================================= */
document.addEventListener('DOMContentLoaded', () => {
    // Reusar AgTheme y AgSidebar del dashboard.js que ya está cargado
    AgReviews.init();

    // Cerrar modales con ESC
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            AgReviews.cerrarModal();
            AgReviews.cerrarDeleteModal();
        }
    });

    // Cerrar modales al hacer clic en overlay
    ['reviewModal', 'deleteModal'].forEach(id => {
        const overlay = document.getElementById(id);
        if (overlay) {
            overlay.addEventListener('click', e => {
                if (e.target === overlay) {
                    AgReviews.cerrarModal();
                    AgReviews.cerrarDeleteModal();
                }
            });
        }
    });
});