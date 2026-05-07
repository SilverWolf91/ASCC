/**
 * ═══════════════════════════════════════════════════════════
 * ASCC PRODUCTO DETALLE - JAVASCRIPT
 * Carrusel, compra, mapa y cálculo de distancias
 * ═══════════════════════════════════════════════════════════
 */

// ═══════════════════════════════════════════════════════════
// VARIABLES GLOBALES (desde PHP)
// ═══════════════════════════════════════════════════════════

const precioUnitario = parseFloat(document.body.dataset.precioUnitario);
const cantidadMaxima = parseInt(document.body.dataset.cantidadMaxima);
const idProducto = parseInt(document.body.dataset.idProducto);
const productoLat = parseFloat(document.body.dataset.productoLat);
const productoLng = parseFloat(document.body.dataset.productoLng);
const productoTipo = document.body.dataset.productoTipo;
const productoVereda = document.body.dataset.productoVereda;
const productoMunicipio = document.body.dataset.productoMunicipio;

let userLat = 0;
let userLng = 0;
let distanciaKm = 0;
let costoEnvio = 0;

// ═══════════════════════════════════════════════════════════
// CARRUSEL DE IMÁGENES
// ═══════════════════════════════════════════════════════════

let currentIndex = 0;
let autoSlideInterval;
const images = document.querySelectorAll('.carousel-image');
const indicators = document.querySelectorAll('.indicator');
const totalImages = images.length;
const carouselContainer = document.getElementById('carouselContainer');

/**
 * Mostrar slide específico
 */
function showSlide(index) {
    if (index >= totalImages) currentIndex = 0;
    else if (index < 0) currentIndex = totalImages - 1;
    else currentIndex = index;

    images.forEach(img => img.classList.remove('active'));
    indicators.forEach(ind => ind.classList.remove('active'));

    if (images[currentIndex]) {
        images[currentIndex].classList.add('active');
    }
    if (indicators[currentIndex]) {
        indicators[currentIndex].classList.add('active');
    }

    const currentSlideElement = document.getElementById('currentSlide');
    if (currentSlideElement) {
        currentSlideElement.textContent = currentIndex + 1;
    }
}

/**
 * Cambiar slide (flechas)
 */
function changeSlide(direction) {
    showSlide(currentIndex + direction);
    resetAutoSlide();
}

/**
 * Ir a slide específico (indicadores)
 */
function goToSlide(index) {
    showSlide(index);
    resetAutoSlide();
}

/**
 * Iniciar carrusel automático
 */
function startAutoSlide() {
    if (totalImages > 1) {
        autoSlideInterval = setInterval(() => {
            showSlide(currentIndex + 1);
        }, 6000); // 6 segundos
    }
}

/**
 * Detener carrusel automático
 */
function stopAutoSlide() {
    clearInterval(autoSlideInterval);
}

/**
 * Reiniciar carrusel automático
 */
function resetAutoSlide() {
    stopAutoSlide();
    startAutoSlide();
}

// Pausar al pasar el cursor sobre el carrusel
if (carouselContainer) {
    carouselContainer.addEventListener('mouseenter', stopAutoSlide);
    carouselContainer.addEventListener('mouseleave', startAutoSlide);
}

// Iniciar carrusel automático al cargar
startAutoSlide();

// ═══════════════════════════════════════════════════════════
// FUNCIONES DE COMPRA
// ═══════════════════════════════════════════════════════════

/**
 * Aumentar cantidad
 */
function increaseQuantity() {
    const input = document.getElementById('quantity');
    let value = parseInt(input.value);
    if (value < cantidadMaxima) {
        input.value = value + 1;
        updateTotal();
    } else {
        alert('⚠️ No hay más unidades disponibles');
    }
}

/**
 * Disminuir cantidad
 */
function decreaseQuantity() {
    const input = document.getElementById('quantity');
    let value = parseInt(input.value);
    if (value > 1) {
        input.value = value - 1;
        updateTotal();
    }
}

/**
 * Actualizar total de la compra
 */
function updateTotal() {
    const quantityInput = document.getElementById('quantity');
    let quantity = parseInt(quantityInput.value);
    
    // Validar cantidad
    if (quantity > cantidadMaxima) {
        quantityInput.value = cantidadMaxima;
        alert('⚠️ Solo hay ' + cantidadMaxima + ' unidades disponibles');
        quantity = cantidadMaxima;
    }
    if (quantity < 1) {
        quantityInput.value = 1;
        quantity = 1;
    }

    const subtotal = precioUnitario * quantity;
    const total = subtotal + costoEnvio;
    
    const totalPriceElement = document.getElementById('totalPrice');
    if (totalPriceElement) {
        totalPriceElement.textContent = total.toLocaleString('es-CO');
    }
}

/**
 * Procesar compra (redirigir a pago)
 */
function procesarCompra() {
    const cantidad = parseInt(document.getElementById('quantity').value);
    const subtotal = precioUnitario * cantidad;
    const total = subtotal + costoEnvio;

    if (confirm('¿Confirmar compra de ' + cantidad + ' unidad(es) por $' + total.toLocaleString('es-CO') + ' COP?')) {
        window.location.href = '/ascc/procesar_pago.php?producto=' + idProducto + 
                               '&cantidad=' + cantidad + 
                               '&total=' + total;
    }
}

// ═══════════════════════════════════════════════════════════
// GOOGLE MAPS
// ═══════════════════════════════════════════════════════════

/**
 * Inicializar mapa del producto
 */
function initMap() {
    if (!productoLat || !productoLng) return;

    const productLocation = {
        lat: productoLat,
        lng: productoLng
    };

    const map = new google.maps.Map(document.getElementById('productMap'), {
        zoom: 14,
        center: productLocation,
        mapTypeControl: true,
        streetViewControl: true,
        styles: [
            {
                featureType: "landscape",
                elementType: "geometry",
                stylers: [{ color: "#F5F1E8" }]
            },
            {
                featureType: "water",
                elementType: "geometry",
                stylers: [{ color: "#3B82A0" }]
            }
        ]
    });

    const marker = new google.maps.Marker({
        position: productLocation,
        map: map,
        title: productoTipo,
        icon: {
            url: 'http://maps.google.com/mapfiles/ms/icons/green-dot.png',
            scaledSize: new google.maps.Size(40, 40)
        }
    });

    const infoWindow = new google.maps.InfoWindow({
        content: `<div style="padding: 12px; font-family: 'Segoe UI', sans-serif;">
                    <strong style="color: #2D5016;">${productoTipo}</strong><br>
                    <span style="color: #8B7355;">${productoVereda}, ${productoMunicipio}</span>
                  </div>`
    });

    marker.addListener('click', () => {
        infoWindow.open(map, marker);
    });
}

// ═══════════════════════════════════════════════════════════
// CÁLCULO DE DISTANCIA Y COSTO DE ENVÍO
// ═══════════════════════════════════════════════════════════

/**
 * Convertir grados a radianes
 */
function toRad(degrees) {
    return degrees * Math.PI / 180;
}

/**
 * Calcular distancia usando fórmula Haversine
 */
function calcularDistanciaHaversine(lat1, lng1, lat2, lng2) {
    const R = 6371; // Radio de la Tierra en km
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
              Math.sin(dLng / 2) * Math.sin(dLng / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

/**
 * Calcular distancia y costo desde ubicación del usuario
 */
function calcularDistanciaYCosto() {
    if (!navigator.geolocation) {
        alert('❌ Tu navegador no soporta geolocalización.');
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            userLat = position.coords.latitude;
            userLng = position.coords.longitude;

            // Calcular distancia
            distanciaKm = calcularDistanciaHaversine(userLat, userLng, productoLat, productoLng);
            
            // Calcular costo de envío (ejemplo: $500 por km)
            costoEnvio = Math.round(distanciaKm * 500);
            
            // Calcular tiempo estimado (ejemplo: 50 km/h promedio)
            const tiempoMinutos = Math.round((distanciaKm / 50) * 60);

            // Actualizar UI
            document.getElementById('distanceValue').textContent = distanciaKm.toFixed(1);
            document.getElementById('timeValue').textContent = tiempoMinutos;
            document.getElementById('costValue').textContent = '$' + costoEnvio.toLocaleString('es-CO');
            document.getElementById('distanceInfo').style.display = 'block';

            document.getElementById('shippingCost').style.display = 'block';
            document.getElementById('shippingPrice').textContent = costoEnvio.toLocaleString('es-CO');

            updateTotal();

            // Actualizar mapa con ambas ubicaciones
            actualizarMapaConRuta();
        },
        (error) => {
            alert('❌ No se pudo obtener tu ubicación. Verifica los permisos del navegador.');
            console.error('Error de geolocalización:', error);
        }
    );
}

/**
 * Actualizar mapa mostrando ruta entre usuario y producto
 */
function actualizarMapaConRuta() {
    const map = new google.maps.Map(document.getElementById('productMap'), {
        zoom: 10,
        center: {
            lat: (userLat + productoLat) / 2,
            lng: (userLng + productoLng) / 2
        },
        styles: [
            {
                featureType: "landscape",
                elementType: "geometry",
                stylers: [{ color: "#F5F1E8" }]
            },
            {
                featureType: "water",
                elementType: "geometry",
                stylers: [{ color: "#3B82A0" }]
            }
        ]
    });

    // Marcador del usuario
    new google.maps.Marker({
        position: { lat: userLat, lng: userLng },
        map: map,
        title: 'Tu ubicación',
        icon: {
            url: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
            scaledSize: new google.maps.Size(40, 40)
        }
    });

    // Marcador del producto
    new google.maps.Marker({
        position: { lat: productoLat, lng: productoLng },
        map: map,
        title: 'Producto',
        icon: {
            url: 'http://maps.google.com/mapfiles/ms/icons/green-dot.png',
            scaledSize: new google.maps.Size(40, 40)
        }
    });

    // Línea conectando ambos puntos
    const line = new google.maps.Polyline({
        path: [
            { lat: userLat, lng: userLng },
            { lat: productoLat, lng: productoLng }
        ],
        geodesic: true,
        strokeColor: '#2D5016',
        strokeOpacity: 1.0,
        strokeWeight: 4
    });
    line.setMap(map);
}

// ═══════════════════════════════════════════════════════════
// INICIALIZACIÓN
// ═══════════════════════════════════════════════════════════

// Inicializar mapa cuando se carga la página
if (productoLat && productoLng) {
    window.addEventListener('load', initMap);
}

// ═══════════════════════════════════════════════════════════
// SISTEMA DE MENSAJES
// ═══════════════════════════════════════════════════════════

function abrirModalMensaje() {
    const btn = document.querySelector('.btn-message');
    const vendedorId = btn.getAttribute('data-vendedor-id');
    const vendedorNombre = btn.getAttribute('data-vendedor-nombre');
    const productoId = btn.getAttribute('data-producto-id');
    const productoNombre = btn.getAttribute('data-producto-nombre');

    // Guardar datos en el modal
    document.getElementById('modalVendedorNombre').textContent = vendedorNombre;
    document.getElementById('modalProductoNombre').textContent = productoNombre;

    // Guardar IDs en atributos del modal para usarlos al enviar
    const modal = document.getElementById('modalMensaje');
    modal.setAttribute('data-vendedor-id', vendedorId);
    modal.setAttribute('data-producto-id', productoId);

    // Limpiar campos
    document.getElementById('mensajeTexto').value = '';
    document.getElementById('mensajeError').style.display = 'none';
    document.getElementById('mensajeExito').style.display = 'none';

    // Mostrar modal
    modal.style.display = 'flex';
}

function cerrarModalMensaje() {
    document.getElementById('modalMensaje').style.display = 'none';
}

// Cerrar modal al hacer clic fuera de él
document.addEventListener('click', function(event) {
    const modal = document.getElementById('modalMensaje');
    if (event.target === modal) {
        cerrarModalMensaje();
    }
});

// Cerrar modal con tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModalMensaje();
    }
});

async function enviarMensaje() {
    const modal = document.getElementById('modalMensaje');
    const vendedorId = modal.getAttribute('data-vendedor-id');
    const productoId = modal.getAttribute('data-producto-id');
    const mensaje = document.getElementById('mensajeTexto').value.trim();

    const errorDiv = document.getElementById('mensajeError');
    const exitoDiv = document.getElementById('mensajeExito');

    // Ocultar mensajes previos
    errorDiv.style.display = 'none';
    exitoDiv.style.display = 'none';

    // Validar
    if (!mensaje) {
        errorDiv.textContent = '❌ Por favor escribe un mensaje';
        errorDiv.style.display = 'block';
        return;
    }

    if (mensaje.length < 10) {
        errorDiv.textContent = '❌ El mensaje debe tener al menos 10 caracteres';
        errorDiv.style.display = 'block';
        return;
    }

    // Deshabilitar botón mientras se envía
    const btnEnviar = document.querySelector('.btn-send-message');
    const textoOriginal = btnEnviar.textContent;
    btnEnviar.textContent = '⏳ Enviando...';
    btnEnviar.disabled = true;

    try {
        const response = await fetch('/ascc/backend/users/controllers/MensajesController.php?action=enviar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_destinatario: vendedorId,
                id_producto: productoId,
                mensaje: mensaje
            })
        });

        const data = await response.json();

        if (data.success) {
            exitoDiv.textContent = '✅ Mensaje enviado correctamente';
            exitoDiv.style.display = 'block';
            document.getElementById('mensajeTexto').value = '';

            // Cerrar modal después de 2 segundos
            setTimeout(() => {
                cerrarModalMensaje();
            }, 2000);
        } else {
            errorDiv.textContent = '❌ ' + (data.message || 'Error al enviar el mensaje');
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        console.error('Error:', error);
        errorDiv.textContent = '❌ Error de conexión. Intenta nuevamente.';
        errorDiv.style.display = 'block';
    } finally {
        btnEnviar.textContent = textoOriginal;
        btnEnviar.disabled = false;
    }
}