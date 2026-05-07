/**
 * ASCC - Admin Transactions JS
 * Ruta: admin/assets/js/admin-transactions.js
 */

// ── Modal detalle ────────────────────────────────────────────────────────────
function verDetalle(t) {
    document.getElementById('det_ref').textContent        = t.ref       || '—';
    document.getElementById('det_producto').textContent   = t.producto  || '—';
    document.getElementById('det_comprador').textContent  = t.comprador || '—';
    document.getElementById('det_vendedor').textContent   = t.vendedor  || '—';
    document.getElementById('det_metodo').textContent     = t.metodo    || '—';
    document.getElementById('det_banco').textContent      = t.banco !== '—' ? t.banco : '—';
    document.getElementById('det_fecha').textContent      = t.fecha     || '—';
    document.getElementById('det_cantidad').textContent   = t.cantidad  || '—';
    document.getElementById('det_precio_unit').textContent = '$' + t.precio_unit;
    document.getElementById('det_total').textContent      = '$' + t.total;

    // Badge de estado
    const badge = document.getElementById('det_estado_badge');
    badge.className = 'at-estado-badge ' + t.estado_class;
    badge.innerHTML = '<i class="fas ' + t.estado_icon + '"></i> ' + t.estado;

    // Datos de pago adicionales
    const preEl   = document.getElementById('det_datos_pago');
    const wrapEl  = document.getElementById('det_extra_wrap');

    if (t.datos_pago) {
        try {
            const parsed = JSON.parse(t.datos_pago);
            preEl.textContent = JSON.stringify(parsed, null, 2);
        } catch (e) {
            preEl.textContent = t.datos_pago;
        }
    } else {
        preEl.textContent = window.TXN_LANG?.noData || 'Sin datos adicionales';
    }
    wrapEl.style.display = 'block';

    // Mostrar modal
    document.getElementById('modalBackdrop').classList.add('at-modal-backdrop--visible');
    document.getElementById('modalDetalle').classList.add('at-modal--open');
    document.body.style.overflow = 'hidden';
}

function cerrarDetalle() {
    document.getElementById('modalBackdrop').classList.remove('at-modal-backdrop--visible');
    document.getElementById('modalDetalle').classList.remove('at-modal--open');
    document.body.style.overflow = '';
}

// Cerrar con ESC
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') cerrarDetalle();
});