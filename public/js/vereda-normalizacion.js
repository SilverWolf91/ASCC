/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - SISTEMA DE NORMALIZACIÓN DE VEREDAS (JS)
 * Archivo: public/js/vereda-normalizacion.js
 * CSS: public/css/vereda-normalizacion.css
 * 
 * Previene inconsistencias como "el tablo" vs "El Tablón"
 * ═══════════════════════════════════════════════════════════
 */

(function(window) {
    'use strict';

    // ══════════════════════════════════════════════════════════
    // FUNCIÓN 1: Normalizar texto (mayúsculas, tildes, espacios)
    // ══════════════════════════════════════════════════════════

    function normalizarVereda(texto) {
        // Eliminar espacios extras
        texto = texto.trim().replace(/\s+/g, ' ');
        
        // Capitalizar primera letra de cada palabra
        texto = texto.toLowerCase().replace(/\b\w/g, function(l) { 
            return l.toUpperCase(); 
        });
        
        return texto;
    }

    // ══════════════════════════════════════════════════════════
    // FUNCIÓN 2: Calcular similitud entre textos (Levenshtein)
    // ══════════════════════════════════════════════════════════

    function calcularSimilitud(str1, str2) {
        var s1 = str1.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        var s2 = str2.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        
        var costs = [];
        for (var i = 0; i <= s1.length; i++) {
            var lastValue = i;
            for (var j = 0; j <= s2.length; j++) {
                if (i === 0) {
                    costs[j] = j;
                } else if (j > 0) {
                    var newValue = costs[j - 1];
                    if (s1.charAt(i - 1) !== s2.charAt(j - 1)) {
                        newValue = Math.min(Math.min(newValue, lastValue), costs[j]) + 1;
                    }
                    costs[j - 1] = lastValue;
                    lastValue = newValue;
                }
            }
            if (i > 0) costs[s2.length] = lastValue;
        }
        
        var maxLength = Math.max(s1.length, s2.length);
        var distance = costs[s2.length];
        return ((maxLength - distance) / maxLength) * 100;
    }

    // ══════════════════════════════════════════════════════════
    // FUNCIÓN 3: Buscar vereda similar en base de datos
    // ══════════════════════════════════════════════════════════

    function buscarVeredaSimilar(veredaEscrita, municipio, callback) {
        var xhr = new XMLHttpRequest();
        var url = '/ascc/controllers/UbicacionController.php?accion=buscar_similar&vereda=' + 
                  encodeURIComponent(veredaEscrita) + '&municipio=' + encodeURIComponent(municipio);
        
        xhr.open('GET', url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success && data.sugerencia) {
                            callback({
                                existe: true,
                                sugerencia: data.sugerencia,
                                similitud: data.similitud
                            });
                        } else {
                            callback({ existe: false });
                        }
                    } catch (error) {
                        console.error('[VEREDA] Error parsing JSON:', error);
                        callback({ existe: false });
                    }
                } else {
                    console.error('[VEREDA] Error HTTP:', xhr.status);
                    callback({ existe: false });
                }
            }
        };
        xhr.send();
    }

    // ══════════════════════════════════════════════════════════
    // FUNCIÓN 4: Mostrar sugerencia al usuario
    // ══════════════════════════════════════════════════════════

    function mostrarSugerenciaVereda(veredaEscrita, veredaSugerida, callback) {
        var modal = document.createElement('div');
        modal.className = 'vereda-suggestion-modal';
        modal.innerHTML = 
            '<div class="vereda-suggestion-overlay"></div>' +
            '<div class="vereda-suggestion-content">' +
                '<div class="vereda-suggestion-header">' +
                    '<span class="vereda-suggestion-icon">🤔</span>' +
                    '<h3>¿Quisiste decir?</h3>' +
                '</div>' +
                '<div class="vereda-suggestion-body">' +
                    '<p>Escribiste: <strong>"' + veredaEscrita + '"</strong></p>' +
                    '<p>¿Quisiste decir: <strong>"' + veredaSugerida + '"</strong>?</p>' +
                    '<p class="vereda-suggestion-hint">' +
                        'Usar "' + veredaSugerida + '" ayuda a mantener los datos consistentes' +
                    '</p>' +
                '</div>' +
                '<div class="vereda-suggestion-actions">' +
                    '<button class="btn-sugerencia-aceptar">' +
                        '✓ Usar "' + veredaSugerida + '"' +
                    '</button>' +
                    '<button class="btn-sugerencia-rechazar">' +
                        'Usar "' + veredaEscrita + '"' +
                    '</button>' +
                '</div>' +
            '</div>';
        
        document.body.appendChild(modal);
        
        modal.querySelector('.btn-sugerencia-aceptar').onclick = function() {
            callback(veredaSugerida);
            modal.remove();
        };
        
        modal.querySelector('.btn-sugerencia-rechazar').onclick = function() {
            callback(veredaEscrita);
            modal.remove();
        };
        
        modal.querySelector('.vereda-suggestion-overlay').onclick = function() {
            callback(veredaEscrita);
            modal.remove();
        };
    }

    // ══════════════════════════════════════════════════════════
    // FUNCIÓN 5: Validar vereda con autocompletado
    // ══════════════════════════════════════════════════════════

    function validarVereda(input, municipio) {
        var veredaEscrita = input.value.trim();
        
        if (!veredaEscrita || veredaEscrita.length < 3) {
            return;
        }
        
        // Normalizar entrada
        var veredaNormalizada = normalizarVereda(veredaEscrita);
        
        // Buscar similar
        buscarVeredaSimilar(veredaNormalizada, municipio, function(resultado) {
            if (resultado.existe && resultado.similitud >= 80 && resultado.similitud < 100) {
                // Encontró similar pero no exacta -> Sugerir
                mostrarSugerenciaVereda(veredaNormalizada, resultado.sugerencia, function(veredaFinal) {
                    input.value = veredaFinal;
                });
            } else if (!resultado.existe) {
                // No existe -> Normalizar automáticamente
                input.value = veredaNormalizada;
            }
        });
    }

    // ══════════════════════════════════════════════════════════
    // FUNCIÓN 6: Autocompletado de veredas
    // ══════════════════════════════════════════════════════════

    function inicializarAutocompletadoVeredas(inputId, municipioInputId) {
        var input = document.getElementById(inputId);
        var municipioInput = document.getElementById(municipioInputId);
        
        if (!input || !municipioInput) {
            console.warn('[VEREDA] Elementos no encontrados:', inputId, municipioInputId);
            return;
        }
        
        var timeoutId;
        var autocompleteList = document.createElement('div');
        autocompleteList.className = 'vereda-autocomplete-list';
        input.parentNode.appendChild(autocompleteList);
        
        input.addEventListener('input', function() {
            clearTimeout(timeoutId);
            
            var query = this.value.trim();
            var municipio = municipioInput.value;
            
            if (query.length < 2) {
                autocompleteList.style.display = 'none';
                return;
            }
            
            timeoutId = setTimeout(function() {
                var xhr = new XMLHttpRequest();
                var url = '/ascc/controllers/UbicacionController.php?accion=autocompletar_vereda&query=' + 
                          encodeURIComponent(query) + '&municipio=' + encodeURIComponent(municipio);
                
                xhr.open('GET', url, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            
                            if (data.success && data.veredas.length > 0) {
                                autocompleteList.innerHTML = data.veredas.map(function(vereda) {
                                    return '<div class="vereda-autocomplete-item" data-vereda="' + vereda + '">' + vereda + '</div>';
                                }).join('');
                                autocompleteList.style.display = 'block';
                                
                                // Click en sugerencia
                                var items = autocompleteList.querySelectorAll('.vereda-autocomplete-item');
                                items.forEach(function(item) {
                                    item.onclick = function() {
                                        input.value = item.getAttribute('data-vereda');
                                        autocompleteList.style.display = 'none';
                                    };
                                });
                            } else {
                                autocompleteList.style.display = 'none';
                            }
                        } catch (error) {
                            console.error('[AUTOCOMPLETE] Error:', error);
                        }
                    }
                };
                xhr.send();
            }, 300);
        });
        
        // Ocultar al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !autocompleteList.contains(e.target)) {
                autocompleteList.style.display = 'none';
            }
        });
        
        // Validar al salir del campo
        input.addEventListener('blur', function() {
            var that = this;
            setTimeout(function() {
                if (that.value.trim()) {
                    validarVereda(that, municipioInput.value);
                }
            }, 200);
        });
        
        console.log('[VEREDA] Autocompletado inicializado para:', inputId);
    }

    // ══════════════════════════════════════════════════════════
    // EXPONER FUNCIONES PÚBLICAS
    // ══════════════════════════════════════════════════════════

    window.inicializarAutocompletadoVeredas = inicializarAutocompletadoVeredas;
    window.normalizarVereda = normalizarVereda;
    window.validarVereda = validarVereda;

    console.log('[VEREDA] Sistema de normalización cargado ✓');

})(window);