/**
 * ═══════════════════════════════════════════════════════════
 * ASCC CATÁLOGO - JAVASCRIPT
 * Gestión de filtros, APIs y funcionalidades del catálogo
 * ═══════════════════════════════════════════════════════════
 */

// ═══════════════════════════════════════════════════════════
// GESTIÓN DE FILTROS CON APIs
// ═══════════════════════════════════════════════════════════

const selectDepartamento = document.getElementById('selectDepartamento');
const selectMunicipio = document.getElementById('selectMunicipio');
const selectVereda = document.getElementById('selectVereda');

// Obtener valores pre-seleccionados del servidor (si existen)
const filtroMunicipioActual = selectMunicipio.dataset.selected || '';
const filtroVeredaActual = selectVereda.dataset.selected || '';

/**
 * Cargar municipios cuando se selecciona un departamento
 */
selectDepartamento.addEventListener('change', async function() {
    const departamento = this.value;

    // Resetear y deshabilitar selects dependientes
    selectMunicipio.innerHTML = '<option value="">Cargando...</option>';
    selectMunicipio.disabled = true;
    selectVereda.innerHTML = '<option value="">Selecciona municipio primero</option>';
    selectVereda.disabled = true;

    // Si no hay departamento o es "otro", salir
    if (!departamento || departamento === 'otro') {
        selectMunicipio.innerHTML = '<option value="">Selecciona departamento primero</option>';
        return;
    }

    try {
        // Llamar a la API de municipios
        const response = await fetch(`/ascc/api/get_municipios.php?departamento=${encodeURIComponent(departamento)}`);
        const municipios = await response.json();

        // Llenar select de municipios
        selectMunicipio.innerHTML = '<option value="">Todos los municipios</option>';
        municipios.forEach(municipio => {
            const option = document.createElement('option');
            option.value = municipio;
            option.textContent = municipio;
            if (municipio === filtroMunicipioActual) option.selected = true;
            selectMunicipio.appendChild(option);
        });

        // Agregar opción "Otro"
        const optionOtro = document.createElement('option');
        optionOtro.value = 'otro';
        optionOtro.textContent = '🔹 Otro';
        selectMunicipio.appendChild(optionOtro);

        selectMunicipio.disabled = false;

        // Si hay municipio pre-seleccionado, disparar evento para cargar veredas
        if (filtroMunicipioActual) {
            selectMunicipio.dispatchEvent(new Event('change'));
        }
    } catch (error) {
        console.error('Error al cargar municipios:', error);
        selectMunicipio.innerHTML = '<option value="">Error al cargar</option>';
    }
});

/**
 * Cargar veredas cuando se selecciona un municipio
 */
selectMunicipio.addEventListener('change', async function() {
    const departamento = selectDepartamento.value;
    const municipio = this.value;

    // Resetear select de veredas
    selectVereda.innerHTML = '<option value="">Cargando...</option>';
    selectVereda.disabled = true;

    // Si no hay municipio o es "otro", salir
    if (!municipio || municipio === 'otro') {
        selectVereda.innerHTML = '<option value="">Selecciona municipio primero</option>';
        return;
    }

    try {
        // Llamar a la API de veredas
        const response = await fetch(
            `/ascc/api/get_veredas.php?departamento=${encodeURIComponent(departamento)}&municipio=${encodeURIComponent(municipio)}`
        );
        const veredas = await response.json();

        // Llenar select de veredas
        selectVereda.innerHTML = '<option value="">Todas las veredas</option>';
        veredas.forEach(vereda => {
            const option = document.createElement('option');
            option.value = vereda;
            option.textContent = vereda;
            if (vereda === filtroVeredaActual) option.selected = true;
            selectVereda.appendChild(option);
        });

        // Agregar opción "Otro"
        const optionOtro = document.createElement('option');
        optionOtro.value = 'otro';
        optionOtro.textContent = '🔹 Otro';
        selectVereda.appendChild(optionOtro);

        selectVereda.disabled = false;
    } catch (error) {
        console.error('Error al cargar veredas:', error);
        selectVereda.innerHTML = '<option value="">Error al cargar</option>';
    }
});

/**
 * Inicializar filtros si hay valores pre-seleccionados
 */
window.addEventListener('DOMContentLoaded', () => {
    if (selectDepartamento.value && selectDepartamento.value !== 'otro') {
        selectDepartamento.dispatchEvent(new Event('change'));
    }
});

// ═══════════════════════════════════════════════════════════
// GESTIÓN DE VISTAS (Lista/Mapa)
// ═══════════════════════════════════════════════════════════

/**
 * Cambiar entre vista de lista y vista de mapa
 */
function cambiarVista(vista) {
    const listView = document.getElementById('listView');
    const mapView = document.getElementById('mapView');
    const buttons = document.querySelectorAll('.view-btn');

    // Remover clase active de todos los botones
    buttons.forEach(btn => btn.classList.remove('active'));

    if (vista === 'lista') {
        listView.style.display = 'block';
        mapView.style.display = 'none';
        buttons[0].classList.add('active');
    } else {
        listView.style.display = 'none';
        mapView.style.display = 'block';
        buttons[1].classList.add('active');
        
        // Inicializar mapa si existe la función
        if (typeof initMap === 'function') {
            initMap();
        }
    }
}

// ═══════════════════════════════════════════════════════════
// GEOLOCALIZACIÓN Y BÚSQUEDA POR RADIO
// ═══════════════════════════════════════════════════════════

/**
 * Obtener ubicación del usuario
 */
function obtenerUbicacion() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                // Guardar coordenadas en campos ocultos
                document.getElementById('userLat').value = position.coords.latitude;
                document.getElementById('userLng').value = position.coords.longitude;
                
                // Mostrar opciones de radio
                document.getElementById('radioOptions').style.display = 'block';
                
                alert('✅ Ubicación obtenida. Selecciona el radio de búsqueda.');
            },
            (error) => {
                console.error('Error de geolocalización:', error);
                alert('❌ No se pudo obtener tu ubicación. Verifica los permisos del navegador.');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    } else {
        alert('❌ Tu navegador no soporta geolocalización.');
    }
}

/**
 * Establecer radio de búsqueda y enviar formulario
 */
function setRadio(km) {
    document.getElementById('radioValue').value = km;
    
    // Actualizar visualización de botones
    document.querySelectorAll('.radio-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Enviar formulario automáticamente
    document.getElementById('filterForm').submit();
}

// ═══════════════════════════════════════════════════════════
// UTILIDADES
// ═══════════════════════════════════════════════════════════

/**
 * Calcular distancia entre dos coordenadas (Haversine)
 */
function calcularDistanciaJS(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radio de la Tierra en km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

/**
 * Formatear número como moneda colombiana
 */
function formatearPrecio(precio) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    }).format(precio);
}