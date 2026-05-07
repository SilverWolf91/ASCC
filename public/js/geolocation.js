/**
 * Geolocalización con Google Maps
 */

let map;
let marker;
let geocoder;

function initMap() {
    const defaultLocation = { lat: 4.570868, lng: -74.297333 };

    map = new google.maps.Map(document.getElementById('map'), {
        center: defaultLocation,
        zoom: 6,
        mapTypeControl: true,
        streetViewControl: false,
        fullscreenControl: true,
        zoomControl: true
    });

    geocoder = new google.maps.Geocoder();

    map.addListener('click', function(e) {
        placeMarker(e.latLng);
        getAddressFromLatLng(e.latLng);
    });

    setTimeout(() => {
        if (navigator.geolocation) {
            mostrarDialogoUbicacion();
        }
    }, 1000);
}

function mostrarDialogoUbicacion() {
    const usar = confirm('📍 ¿Deseas usar tu ubicación actual?\n\nEsto ayudará a los compradores a encontrarte más fácilmente.');
    
    if (usar) {
        obtenerUbicacionActual();
    }
}

function obtenerUbicacionActual() {
    const loadingMsg = document.getElementById('loading-location');
    if (loadingMsg) loadingMsg.style.display = 'block';

    navigator.geolocation.getCurrentPosition(
        function(position) {
            const pos = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };

            map.setCenter(pos);
            map.setZoom(15);
            placeMarker(new google.maps.LatLng(pos.lat, pos.lng));
            getAddressFromLatLng(new google.maps.LatLng(pos.lat, pos.lng));

            if (loadingMsg) loadingMsg.style.display = 'none';

            alert('✅ Ubicación detectada correctamente\n\nPuedes ajustarla haciendo clic en el mapa o arrastrando el marcador.');
        },
        function(error) {
            if (loadingMsg) loadingMsg.style.display = 'none';
            
            let mensaje = '⚠️ No se pudo obtener tu ubicación\n\n';
            
            if (error.code === 1) {
                mensaje += 'Permiso denegado. Por favor permite el acceso a tu ubicación en tu navegador.';
            } else if (error.code === 2) {
                mensaje += 'Ubicación no disponible.';
            } else {
                mensaje += 'Tiempo de espera agotado.';
            }
            
            mensaje += '\n\nPuedes seleccionarla manualmente:\n- Haz clic en el mapa\n- Busca una dirección\n- Arrastra el marcador';
            
            alert(mensaje);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

function placeMarker(location) {
    if (marker) {
        marker.setPosition(location);
    } else {
        marker = new google.maps.Marker({
            position: location,
            map: map,
            draggable: true,
            animation: google.maps.Animation.DROP,
            title: 'Tu ubicación'
        });

        marker.addListener('dragend', function(e) {
            getAddressFromLatLng(e.latLng);
        });
    }

    document.getElementById('latitud').value = location.lat();
    document.getElementById('longitud').value = location.lng();

    const infoWindow = new google.maps.InfoWindow({
        content: '<div style="padding: 10px;"><strong>📍 Ubicación seleccionada</strong><br>Lat: ' + location.lat().toFixed(6) + '<br>Lng: ' + location.lng().toFixed(6) + '<br><small>Puedes arrastrar el marcador</small></div>'
    });
    infoWindow.open(map, marker);
}

function getAddressFromLatLng(latLng) {
    geocoder.geocode({ location: latLng }, function(results, status) {
        if (status === 'OK' && results[0]) {
            const address = results[0].formatted_address;
            document.getElementById('direccion_mapa').value = address;
            autocompletarUbicacion(results[0]);
        }
    });
}

function autocompletarUbicacion(result) {
    let departamento = '';
    let municipio = '';

    result.address_components.forEach(component => {
        if (component.types.includes('administrative_area_level_1')) {
            departamento = component.long_name;
        }
        if (component.types.includes('locality') || component.types.includes('administrative_area_level_2')) {
            municipio = component.long_name;
        }
    });

    const deptoSelect = document.getElementById('departamento');
    const munSelect = document.getElementById('municipio');

    if (deptoSelect && departamento) {
        for (let i = 0; i < deptoSelect.options.length; i++) {
            const option = deptoSelect.options[i];
            if (option.value.toLowerCase().includes(departamento.toLowerCase()) || 
                departamento.toLowerCase().includes(option.value.toLowerCase())) {
                deptoSelect.value = option.value;
                deptoSelect.dispatchEvent(new Event('change'));
                break;
            }
        }
    }

    setTimeout(() => {
        if (munSelect && municipio) {
            for (let i = 0; i < munSelect.options.length; i++) {
                const option = munSelect.options[i];
                if (option.value.toLowerCase().includes(municipio.toLowerCase()) || 
                    municipio.toLowerCase().includes(option.value.toLowerCase())) {
                    munSelect.value = option.value;
                    break;
                }
            }
        }
    }, 500);
}

function buscarUbicacion() {
    const direccion = document.getElementById('buscar_direccion').value;
    
    if (!direccion) {
        alert('❌ Escribe una dirección para buscar');
        return;
    }

    geocoder.geocode({ address: direccion + ', Colombia' }, function(results, status) {
        if (status === 'OK' && results[0]) {
            map.setCenter(results[0].geometry.location);
            map.setZoom(15);
            placeMarker(results[0].geometry.location);
            getAddressFromLatLng(results[0].geometry.location);
        } else {
            alert('⚠️ No se encontró la ubicación. Intenta con otra dirección.');
        }
    });
}

window.initMap = initMap;