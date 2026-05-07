/**
 * Selector inteligente de ubicaciones
 * Departamentos -> Municipios -> Veredas
 */

document.addEventListener('DOMContentLoaded', function() {
    
    const departamentoSelect = document.getElementById('departamento');
    const municipioSelect = document.getElementById('municipio');
    const veredaSelect = document.getElementById('vereda');
    const otroMunicipioInput = document.getElementById('otro_municipio');
    const otroVeredaInput = document.getElementById('otro_vereda');

    // Llenar departamentos al cargar
    if (departamentoSelect) {
        Object.keys(colombiaData).sort().forEach(depto => {
            const option = document.createElement('option');
            option.value = depto;
            option.textContent = depto;
            departamentoSelect.appendChild(option);
        });
    }

    // Llenar veredas comunes
    if (veredaSelect) {
        veredasComunes.forEach(vereda => {
            const option = document.createElement('option');
            option.value = vereda;
            option.textContent = vereda;
            veredaSelect.appendChild(option);
        });
    }

    // Cuando cambia el departamento
    if (departamentoSelect) {
        departamentoSelect.addEventListener('change', function() {
            const deptoSeleccionado = this.value;
            
            // Limpiar municipios
            municipioSelect.innerHTML = '<option value="">Selecciona un municipio</option>';
            
            if (otroMunicipioInput) {
                otroMunicipioInput.style.display = 'none';
                otroMunicipioInput.required = false;
            }

            if (deptoSeleccionado && colombiaData[deptoSeleccionado]) {
                // Llenar municipios
                colombiaData[deptoSeleccionado].municipios.forEach(mun => {
                    const option = document.createElement('option');
                    option.value = mun;
                    option.textContent = mun;
                    municipioSelect.appendChild(option);
                });
                
                municipioSelect.disabled = false;
            } else {
                municipioSelect.disabled = true;
            }
        });
    }

    // Cuando cambia el municipio
    if (municipioSelect) {
        municipioSelect.addEventListener('change', function() {
            if (this.value === 'Otro') {
                otroMunicipioInput.style.display = 'block';
                otroMunicipioInput.required = true;
                otroMunicipioInput.focus();
            } else {
                otroMunicipioInput.style.display = 'none';
                otroMunicipioInput.required = false;
            }
        });
    }

    // Cuando cambia la vereda
    if (veredaSelect) {
        veredaSelect.addEventListener('change', function() {
            if (this.value === 'Otro') {
                otroVeredaInput.style.display = 'block';
                otroVeredaInput.required = true;
                otroVeredaInput.focus();
            } else {
                otroVeredaInput.style.display = 'none';
                otroVeredaInput.required = false;
            }
        });
    }
});

// Validar antes de enviar el formulario
function validarUbicacion() {
    const municipioSelect = document.getElementById('municipio');
    const veredaSelect = document.getElementById('vereda');
    const otroMunicipio = document.getElementById('otro_municipio');
    const otroVereda = document.getElementById('otro_vereda');

    if (municipioSelect.value === 'Otro' && !otroMunicipio.value.trim()) {
        alert('❌ Por favor especifica el nombre del municipio');
        otroMunicipio.focus();
        return false;
    }

    if (veredaSelect.value === 'Otro' && !otroVereda.value.trim()) {
        alert('❌ Por favor especifica el nombre de la vereda');
        otroVereda.focus();
        return false;
    }

    return true;
}