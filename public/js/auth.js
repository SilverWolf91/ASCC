/**
 * JavaScript para páginas de autenticación
 * Funciones para login y registro
 */

// Función para mostrar/ocultar contraseña
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
    } else {
        input.type = "password";
    }
}

// Función para mostrar/ocultar múltiples contraseñas
function togglePasswords() {
    const pass1 = document.getElementById("pass1");
    const pass2 = document.getElementById("pass2");
    
    if (pass1 && pass2) {
        const type = pass1.type === "password" ? "text" : "password";
        pass1.type = type;
        pass2.type = type;
    }
}

// Verificar fuerza de contraseña
function checkPasswordStrength() {
    const password = document.getElementById("pass1").value;
    const indicator = document.getElementById("strength-indicator");

    if (!indicator) return;

    if (password.length === 0) {
        indicator.textContent = "";
        return;
    }

    let strength = 0;
    
    // Criterios de fuerza
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;

    // Mostrar resultado
    if (strength <= 1) {
        indicator.textContent = "⚠️ Contraseña débil";
        indicator.className = "password-strength strength-weak";
    } else if (strength <= 3) {
        indicator.textContent = "✓ Contraseña media";
        indicator.className = "password-strength strength-medium";
    } else {
        indicator.textContent = "✓ Contraseña fuerte";
        indicator.className = "password-strength strength-strong";
    }
}

// Validar formulario de registro
function validateForm() {
    const pass1 = document.getElementById("pass1");
    const pass2 = document.getElementById("pass2");

    if (!pass1 || !pass2) return true;

    if (pass1.value !== pass2.value) {
        alert("❌ Las contraseñas no coinciden");
        pass2.focus();
        return false;
    }

    if (pass1.value.length < 6) {
        alert("❌ La contraseña debe tener al menos 6 caracteres");
        pass1.focus();
        return false;
    }

    return true;
}

// Validar email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validar teléfono (solo números, 10 dígitos)
function validatePhone(phone) {
    const re = /^[0-9]{10}$/;
    return re.test(phone);
}

// Validar cédula (solo números, 8-10 dígitos)
function validateCedula(cedula) {
    const re = /^[0-9]{8,10}$/;
    return re.test(cedula);
}