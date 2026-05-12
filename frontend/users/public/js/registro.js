/**
 * ═══════════════════════════════════════════════════════════
 * ASCC REGISTRO - JAVASCRIPT
 * Validaciones profesionales de formulario
 * ═══════════════════════════════════════════════════════════
 */

// ═══════════════════════════════════════════════════════════
// CONFIGURACIÓN DE PAÍSES Y VALIDACIONES
// ═══════════════════════════════════════════════════════════

const PAISES_CONFIG = {
    'CO': { nombre: 'Colombia 🇨🇴', codigo: '+57', digitosTelefono: 10, formatoTelefono: '3XX XXX XXXX' },
    'US': { nombre: 'Estados Unidos 🇺🇸', codigo: '+1', digitosTelefono: 10, formatoTelefono: 'XXX XXX XXXX' },
    'MX': { nombre: 'México 🇲🇽', codigo: '+52', digitosTelefono: 10, formatoTelefono: 'XX XXXX XXXX' },
    'ES': { nombre: 'España 🇪🇸', codigo: '+34', digitosTelefono: 9, formatoTelefono: 'XXX XX XX XX' },
    'AR': { nombre: 'Argentina 🇦🇷', codigo: '+54', digitosTelefono: 10, formatoTelefono: 'XX XXXX XXXX' },
    'PE': { nombre: 'Perú 🇵🇪', codigo: '+51', digitosTelefono: 9, formatoTelefono: 'XXX XXX XXX' },
    'EC': { nombre: 'Ecuador 🇪🇨', codigo: '+593', digitosTelefono: 9, formatoTelefono: 'XX XXX XXXX' },
    'VE': { nombre: 'Venezuela 🇻🇪', codigo: '+58', digitosTelefono: 10, formatoTelefono: 'XXX XXX XXXX' }
};

// ═══════════════════════════════════════════════════════════
// FUNCIONES DE VALIDACIÓN
// ═══════════════════════════════════════════════════════════

/**
 * Validar email con reglas estrictas
 */
function validarEmail(email) {
    const regex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/;
    
    if (!regex.test(email)) {
        return { valido: false, mensaje: '❌ Formato de email inválido' };
    }
    
    const partes = email.split('@');
    const usuario = partes[0];
    const dominio = partes[1];
    
    if (usuario.length === 0) {
        return { valido: false, mensaje: '❌ El email debe tener un nombre de usuario' };
    }
    
    if (!dominio.includes('.')) {
        return { valido: false, mensaje: '❌ El dominio debe incluir una extensión (ej: .com, .edu.co)' };
    }
    
    if (dominio.endsWith('.')) {
        return { valido: false, mensaje: '❌ El dominio no puede terminar en punto' };
    }
    
    const extension = dominio.split('.').pop();
    if (extension.length < 2) {
        return { valido: false, mensaje: '❌ Extensión de dominio inválida' };
    }
    
    return { valido: true, mensaje: '✅ Email válido' };
}

/**
 * Validar teléfono según país seleccionado
 */
function validarTelefono(telefono, codigoPais) {
    const config = PAISES_CONFIG[codigoPais];
    const soloNumeros = telefono.replace(/\D/g, '');
    
    if (soloNumeros.length === 0) {
        return { valido: false, mensaje: '❌ Ingresa tu número de teléfono' };
    }
    
    if (soloNumeros.length !== config.digitosTelefono) {
        return { 
            valido: false, 
            mensaje: `❌ Debe tener ${config.digitosTelefono} dígitos (formato: ${config.formatoTelefono})` 
        };
    }
    
    if (codigoPais === 'CO') {
        if (!soloNumeros.startsWith('3')) {
            return { valido: false, mensaje: '❌ En Colombia los celulares empiezan con 3' };
        }
    }
    
    return { valido: true, mensaje: '✅ Teléfono válido', telefonoLimpio: soloNumeros };
}

/**
 * Validar cédula colombiana
 */
function validarCedula(cedula) {
    const soloNumeros = cedula.replace(/\D/g, '');
    
    if (soloNumeros.length === 0) {
        return { valido: false, mensaje: '❌ Ingresa tu número de cédula' };
    }
    
    if (soloNumeros.length < 6 || soloNumeros.length > 12) {
        return { valido: false, mensaje: '❌ La cédula debe tener entre 6 y 12 dígitos' };
    }
    
    return { valido: true, mensaje: '✅ Cédula válida', cedulaLimpia: soloNumeros };
}

/**
 * Verificar fortaleza de contraseña
 */
function verificarFortalezaPassword(password) {
    const indicator = document.getElementById('strength-indicator');
    const strengthText = document.getElementById('strength-text');
    
    if (password.length === 0) {
        indicator.style.width = '0%';
        strengthText.textContent = '';
        return;
    }
    
    let fuerza = 0;
    let mensaje = '';
    let color = '';
    
    if (password.length >= 6) fuerza++;
    if (password.length >= 10) fuerza++;
    if (password.length >= 14) fuerza++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) fuerza++;
    if (/\d/.test(password)) fuerza++;
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) fuerza++;
    
    if (fuerza <= 2) {
        color = '#f44336';
        mensaje = 'Débil';
        indicator.style.width = '33%';
    } else if (fuerza <= 4) {
        color = '#ff9800';
        mensaje = 'Media';
        indicator.style.width = '66%';
    } else {
        color = '#4caf50';
        mensaje = 'Fuerte';
        indicator.style.width = '100%';
    }
    
    indicator.style.background = color;
    strengthText.textContent = mensaje;
    strengthText.style.color = color;
}

// ═══════════════════════════════════════════════════════════
// FUNCIONES DE UI
// ═══════════════════════════════════════════════════════════

function togglePasswords() {
    const pass1 = document.getElementById('pass1');
    const pass2 = document.getElementById('pass2');
    const type = pass1.type === 'password' ? 'text' : 'password';
    pass1.type = type;
    pass2.type = type;
}

function mostrarError(campo, mensaje) {
    const input = document.getElementById(campo);
    const errorDiv = document.getElementById(campo + '-error');
    const successDiv = document.getElementById(campo + '-success');
    
    input.classList.add('error');
    input.classList.remove('valid');
    
    if (errorDiv) {
        errorDiv.textContent = mensaje;
        errorDiv.classList.add('show');
    }
    
    if (successDiv) {
        successDiv.classList.remove('show');
    }
}

function mostrarExito(campo, mensaje) {
    const input = document.getElementById(campo);
    const errorDiv = document.getElementById(campo + '-error');
    const successDiv = document.getElementById(campo + '-success');
    
    input.classList.remove('error');
    input.classList.add('valid');
    
    if (errorDiv) {
        errorDiv.classList.remove('show');
    }
    
    if (successDiv) {
        successDiv.textContent = mensaje;
        successDiv.classList.add('show');
    }
}

function limpiarValidacion(campo) {
    const input = document.getElementById(campo);
    const errorDiv = document.getElementById(campo + '-error');
    const successDiv = document.getElementById(campo + '-success');
    
    input.classList.remove('error', 'valid');
    
    if (errorDiv) errorDiv.classList.remove('show');
    if (successDiv) successDiv.classList.remove('show');
}

// ═══════════════════════════════════════════════════════════
// VALIDACIÓN DEL FORMULARIO
// ═══════════════════════════════════════════════════════════

function validarFormulario(event) {
    event.preventDefault();
    
    let esValido = true;
    
    // Validar nombre
    const nombre = document.getElementById('nombre').value.trim();
    if (nombre.length < 3) {
        mostrarError('nombre', '❌ El nombre debe tener al menos 3 caracteres');
        esValido = false;
    } else {
        mostrarExito('nombre', '✅ Nombre válido');
    }
    
    // Validar cédula
    const cedula = document.getElementById('cedula').value;
    const validacionCedula = validarCedula(cedula);
    if (!validacionCedula.valido) {
        mostrarError('cedula', validacionCedula.mensaje);
        esValido = false;
    } else {
        mostrarExito('cedula', validacionCedula.mensaje);
        document.getElementById('cedula').value = validacionCedula.cedulaLimpia;
    }
    
    // Validar teléfono
    const telefono = document.getElementById('telefono').value;
    const codigoPais = document.getElementById('codigo-pais').value;
    const validacionTelefono = validarTelefono(telefono, codigoPais);
    if (!validacionTelefono.valido) {
        mostrarError('telefono', validacionTelefono.mensaje);
        esValido = false;
    } else {
        mostrarExito('telefono', validacionTelefono.mensaje);
        document.getElementById('telefono').value = validacionTelefono.telefonoLimpio;
    }
    
    // Validar email
    const email = document.getElementById('email').value.trim();
    const validacionEmail = validarEmail(email);
    if (!validacionEmail.valido) {
        mostrarError('email', validacionEmail.mensaje);
        esValido = false;
    } else {
        mostrarExito('email', validacionEmail.mensaje);
    }
    
    // Validar contraseñas
    const pass1 = document.getElementById('pass1').value;
    const pass2 = document.getElementById('pass2').value;
    
    if (pass1.length < 6) {
        mostrarError('pass1', '❌ La contraseña debe tener al menos 6 caracteres');
        esValido = false;
    } else {
        mostrarExito('pass1', '✅ Contraseña válida');
    }
    
    if (pass1 !== pass2) {
        mostrarError('pass2', '❌ Las contraseñas no coinciden');
        esValido = false;
    } else if (pass2.length > 0) {
        mostrarExito('pass2', '✅ Las contraseñas coinciden');
    }

    // ── Validar rol seleccionado ──────────────────────────
    var rolVal = document.getElementById('rol-seleccionado').value;
    if (!rolVal) {
        var rolError = document.getElementById('rol-error');
        rolError.textContent = '⚠️ Debes elegir un tipo de cuenta';
        rolError.classList.add('show');
        document.querySelector('.rol-selector').scrollIntoView({ behavior: 'smooth', block: 'center' });
        esValido = false;
    }
    // ─────────────────────────────────────────────────────

    // ── Validar Políticas de Privacidad ──────────────────
    const aceptaPoliticas = document.getElementById('acepta_politicas').checked;
    const politicasError = document.getElementById('politicas-error');
    if (!aceptaPoliticas) {
        politicasError.textContent = '❌ Debes aceptar las Políticas de Tratamiento de Datos Personales para continuar.';
        politicasError.classList.add('show');
        esValido = false;
    } else {
        politicasError.classList.remove('show');
    }
    // ─────────────────────────────────────────────────────
    
    // Si todo es válido, enviar formulario
    if (esValido) {
        const btnSubmit = document.querySelector('button[type="submit"]');
        btnSubmit.classList.add('loading');
        btnSubmit.disabled = true;
        event.target.submit();
    }
    
    return false;
}

// ═══════════════════════════════════════════════════════════
// INICIALIZACIÓN
// ═══════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function() {

    document.getElementById('email').addEventListener('blur', function() {
        const validacion = validarEmail(this.value.trim());
        if (this.value.length > 0) {
            if (validacion.valido) {
                mostrarExito('email', validacion.mensaje);
            } else {
                mostrarError('email', validacion.mensaje);
            }
        } else {
            limpiarValidacion('email');
        }
    });
    
    document.getElementById('telefono').addEventListener('blur', function() {
        const codigoPais = document.getElementById('codigo-pais').value;
        const validacion = validarTelefono(this.value, codigoPais);
        if (this.value.length > 0) {
            if (validacion.valido) {
                mostrarExito('telefono', validacion.mensaje);
            } else {
                mostrarError('telefono', validacion.mensaje);
            }
        } else {
            limpiarValidacion('telefono');
        }
    });
    
    document.getElementById('cedula').addEventListener('blur', function() {
        const validacion = validarCedula(this.value);
        if (this.value.length > 0) {
            if (validacion.valido) {
                mostrarExito('cedula', validacion.mensaje);
            } else {
                mostrarError('cedula', validacion.mensaje);
            }
        } else {
            limpiarValidacion('cedula');
        }
    });
    
    document.getElementById('pass1').addEventListener('keyup', function() {
        verificarFortalezaPassword(this.value);
    });
});

// ═══════════════════════════════════════════════════════════
// SELECTOR DE ROL
// ═══════════════════════════════════════════════════════════

/**
 * Marca visualmente la card elegida y guarda el valor en el campo oculto
 */
function seleccionarRol(rol) {
    // Quitar selección de todas las cards
    document.querySelectorAll('.rol-card').forEach(function(card) {
        card.classList.remove('seleccionado');
    });

    // Marcar la card clickeada
    var cardActiva = document.querySelector('.rol-card[data-rol="' + rol + '"]');
    if (cardActiva) {
        cardActiva.classList.add('seleccionado');
    }

    // Escribir valor en campo oculto
    document.getElementById('rol-seleccionado').value = rol;

    // Limpiar error si existía
    var rolError = document.getElementById('rol-error');
    if (rolError) {
        rolError.textContent = '';
        rolError.classList.remove('show');
    }
}

// ═══════════════════════════════════════════════════════════
// POLÍTICAS DE PRIVACIDAD
// ═══════════════════════════════════════════════════════════

function abrirModalPoliticas(event) {
    if(event) event.preventDefault();
    document.getElementById('modalPoliticas').style.display = 'flex';
}

function cerrarModalPoliticas() {
    document.getElementById('modalPoliticas').style.display = 'none';
}

function cerrarModalYMarcar() {
    cerrarModalPoliticas();
    document.getElementById('acepta_politicas').checked = true;
}

// Cerrar modal si hacen click fuera del contenido
window.addEventListener('click', function(event) {
    var modal = document.getElementById('modalPoliticas');
    if (event.target == modal) {
        cerrarModalPoliticas();
    }
});