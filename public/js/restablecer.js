/**
 * ═══════════════════════════════════════════════════════════
 * ASCC RESTABLECER CONTRASEÑA - JAVASCRIPT
 * Con indicador visual de fortaleza (débil/medio/fuerte)
 * ═══════════════════════════════════════════════════════════
 */

/**
 * Mostrar/Ocultar contraseñas
 */
function togglePasswords() {
    const pass1 = document.getElementById('pass1');
    const pass2 = document.getElementById('pass2');
    
    if (pass1 && pass2) {
        const type = pass1.type === 'password' ? 'text' : 'password';
        pass1.type = type;
        pass2.type = type;
    }
}

/**
 * Verificar fortaleza de contraseña CON INDICADOR VISUAL
 */
function checkPasswordStrength() {
    const pass = document.getElementById('pass1').value;
    const indicator = document.getElementById('strength-indicator');
    const strengthText = document.getElementById('strength-text');

    if (!indicator || !strengthText) return;

    // Si está vacío, ocultar todo
    if (pass.length === 0) {
        indicator.style.width = '0';
        strengthText.classList.remove('show');
        strengthText.textContent = '';
        return;
    }

    let strength = 0;
    let mensaje = '';
    let color = '';
    let width = '';
    let className = '';

    // Calcular fortaleza
    if (pass.length >= 6) strength++;
    if (pass.length >= 10) strength++;
    if (/[a-z]/.test(pass) && /[A-Z]/.test(pass)) strength++;
    if (/\d/.test(pass)) strength++;
    if (/[!@#$%^&*(),.?":{}|<>]/.test(pass)) strength++;

    // Determinar nivel
    if (strength <= 2) {
        color = '#F44336';      // Rojo
        width = '33%';
        mensaje = '❌ Débil';
        className = 'strength-weak';
    } else if (strength <= 4) {
        color = '#FF9800';      // Naranja
        width = '66%';
        mensaje = '⚠️ Media';
        className = 'strength-medium';
    } else {
        color = '#4CAF50';      // Verde
        width = '100%';
        mensaje = '✅ Fuerte';
        className = 'strength-strong';
    }

    // Aplicar estilos
    indicator.style.width = width;
    indicator.style.background = color;
    
    // Mostrar texto
    strengthText.textContent = mensaje;
    strengthText.className = 'strength-text show ' + className;
}

/**
 * Validar formulario antes de enviar
 */
function validateForm() {
    const pass1 = document.getElementById('pass1').value;
    const pass2 = document.getElementById('pass2').value;

    if (pass1 !== pass2) {
        alert('❌ Las contraseñas no coinciden');
        return false;
    }

    if (pass1.length < 6) {
        alert('❌ La contraseña debe tener al menos 6 caracteres');
        return false;
    }

    return true;
}