/**
 * ═══════════════════════════════════════════════════════════
 * ASCC PAGO CONFIRMACIÓN - JAVASCRIPT
 * Verificación y actualización del estado del pago
 * ═══════════════════════════════════════════════════════════
 */

/**
 * Obtener parámetros de la URL
 */
const urlParams = new URLSearchParams(window.location.search);
const referencia = urlParams.get('ref');
const autoAprobar = document.body.dataset.autoAprobar === 'true';

/**
 * Mostrar estado de pago aprobado
 */
function mostrarPagoAprobado() {
    document.getElementById('statusContent').innerHTML = `
        <div class="status-icon status-approved">✅</div>
        <h1 class="confirmation-title">¡Pago Aprobado!</h1>
        <p class="confirmation-message">
            Tu pago ha sido procesado exitosamente.<br>
            El vendedor ha sido notificado y se pondrá en contacto contigo.
        </p>
    `;

    document.getElementById('actionButtons').style.display = 'block';
    document.getElementById('vendorContact').style.display = 'block';

    // Reproducir sonido de éxito (opcional)
    // const audio = new Audio('/ascc/public/sounds/success.mp3');
    // audio.play();
}

/**
 * Mostrar estado de pago rechazado
 */
function mostrarPagoRechazado() {
    document.getElementById('statusContent').innerHTML = `
        <div class="status-icon status-rejected">❌</div>
        <h1 class="confirmation-title">Pago Rechazado</h1>
        <p class="confirmation-message">
            Tu pago no pudo ser procesado.<br>
            Por favor, verifica tus datos e intenta nuevamente.
        </p>
    `;

    document.getElementById('actionButtons').innerHTML = `
        <div class="action-buttons">
            <a href="/ascc/catalogo.php" class="btn btn-secondary">🛒 Volver al catálogo</a>
            <a href="javascript:history.back()" class="btn btn-primary">🔄 Intentar nuevamente</a>
        </div>
    `;

    document.getElementById('actionButtons').style.display = 'block';
}

/**
 * Verificar estado del pago (llamada a API)
 * En producción, esto consultaría el backend cada 2 segundos
 */
function verificarEstadoPago() {
    fetch(`/ascc/api/verificar_estado_pago.php?ref=${encodeURIComponent(referencia)}`)
        .then(response => response.json())
        .then(data => {
            if (data.estado === 'APROBADO' || data.estado === 'APPROVED') {
                mostrarPagoAprobado();
            } else if (data.estado === 'RECHAZADO' || data.estado === 'DECLINED' || data.estado === 'ERROR') {
                mostrarPagoRechazado();
            } else {
                // Seguir verificando cada 2 segundos si está pendiente
                setTimeout(verificarEstadoPago, 2000);
            }
        })
        .catch(error => {
            console.error('Error al verificar estado del pago:', error);
            // Reintentar después de 3 segundos
            setTimeout(verificarEstadoPago, 3000);
        });
}

// ═══════════════════════════════════════════════════════════
// INICIALIZACIÓN
// ═══════════════════════════════════════════════════════════

/**
 * Simular aprobación automática (solo para desarrollo)
 * En producción, esta función NO existe y se usa verificarEstadoPago()
 */
if (autoAprobar) {
    setTimeout(function() {
        mostrarPagoAprobado();
    }, 3000); // 3 segundos
}

/**
 * EN PRODUCCIÓN, descomentar esta línea y eliminar el bloque de arriba:
 * verificarEstadoPago();
 */