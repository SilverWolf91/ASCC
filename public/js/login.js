/**
 * ═══════════════════════════════════════════════════════════
 * ASCC LOGIN - JAVASCRIPT
 * JavaScript extraído desde views/auth/login.php
 * ═══════════════════════════════════════════════════════════
 */

/**
 * Mostrar/Ocultar contraseña
 * @param {string} inputId - ID del input de contraseña
 */
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
    }
}