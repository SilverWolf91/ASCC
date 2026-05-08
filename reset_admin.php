<?php
require_once __DIR__ . '/config/database.php';

try {
    $password = 'admin123456';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conexion->prepare("UPDATE usuarios SET password = :password WHERE rol = 'admin'");
    $stmt->execute([':password' => $hash]);
    
    echo "<h1>¡Éxito!</h1>";
    echo "<p>La contraseña de todos los administradores (incluyendo admin@ascc.co) ha sido reseteada a: <strong>admin123456</strong></p>";
    echo "<p>Por favor, ve a <a href='/ascc/admin/login.php'>Iniciar Sesión Admin</a> e ingresa.</p>";
    echo "<p><em>Nota: avísame para que yo elimine este archivo por seguridad.</em></p>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
