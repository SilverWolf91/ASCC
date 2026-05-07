/**
 * ═══════════════════════════════════════════════════════════
 * ASCC PROCESAR PAGO - JAVASCRIPT
 * Integración con Wompi para pagos
 * ═══════════════════════════════════════════════════════════
 */

/**
 * Obtener configuración desde atributos data del body
 */
const wompiPublicKey = document.body.dataset.wompiPublicKey;
const compradorEmail = document.body.dataset.compradorEmail;
const compradorNombre = document.body.dataset.compradorNombre;
const compradorTelefono = document.body.dataset.compradorTelefono;
const compradorCedula = document.body.dataset.compradorCedula;
const productoMunicipio = document.body.dataset.productoMunicipio;
const productoDepartamento = document.body.dataset.productoDepartamento;

/**
 * Función principal para procesar pago con Wompi
 */
function pagarConWompi() {
    const referencia = document.getElementById('referencia').value;
    const total = document.getElementById('total').value; // En centavos
    const productoId = document.getElementById('producto_id').value;
    const cantidad = document.getElementById('cantidad').value;

    // Validar que todos los datos estén presentes
    if (!referencia || !total || !productoId || !cantidad) {
        alert('❌ Error: Faltan datos para procesar el pago');
        return;
    }

    // URL de redirección después del pago
    const redirectUrl = window.location.origin + '/ascc/pago_confirmacion.php?ref=' + 
                        encodeURIComponent(referencia) + 
                        '&producto=' + productoId + 
                        '&cantidad=' + cantidad + 
                        '&total=' + (parseInt(total) / 100);

    // Configuración del checkout de Wompi
    var checkout = new WidgetCheckout({
        currency: 'COP',
        amountInCents: parseInt(total), // Monto en centavos
        reference: referencia,
        publicKey: wompiPublicKey,

        // URL de redirección
        redirectUrl: redirectUrl,

        // Información del cliente
        customerData: {
            email: compradorEmail,
            fullName: compradorNombre,
            phoneNumber: compradorTelefono,
            phoneNumberPrefix: '+57',
            legalId: compradorCedula,
            legalIdType: 'CC'
        },

        // Información de envío (opcional)
        shippingAddress: {
            addressLine1: productoMunicipio,
            city: productoMunicipio,
            phoneNumber: compradorTelefono,
            region: productoDepartamento,
            country: 'CO'
        },

        // Métodos de pago disponibles
        paymentMethods: {
            bancolombia_transfer: true, // PSE
            card: true, // Tarjetas
            nequi: true, // Nequi
            pse: true // PSE
        }
    });

    // Abrir el checkout
    checkout.open(function(result) {
        var transaction = result.transaction;
        console.log('Transaction: ', transaction);

        // Guardar información de la transacción
        if (transaction.status === 'APPROVED') {
            registrarPagoEnServidor(referencia, productoId, cantidad, total, transaction);
        }
    });
}

/**
 * Registrar el pago en el servidor
 */
function registrarPagoEnServidor(referencia, productoId, cantidad, total, transactionData) {
    fetch('/ascc/backend/users/controllers/PagoController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            accion: 'registrar_pago',
            referencia: referencia,
            id_producto: productoId,
            cantidad: cantidad,
            total: total / 100, // Convertir de centavos a pesos
            estado: transactionData.status,
            datos_wompi: transactionData
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Pago registrado:', data);
    })
    .catch(error => {
        console.error('Error al registrar pago:', error);
    });
}