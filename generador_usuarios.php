<?php
require_once __DIR__ . '/config/database.php';

// Limite de tiempo para que no se detenga
set_time_limit(300);

echo "<h1>Iniciando generación de usuarios...</h1>";

$nombres = ['Juan', 'Maria', 'Carlos', 'Ana', 'Luis', 'Laura', 'Pedro', 'Marta', 'Diego', 'Sofia', 'Andres', 'Camila', 'Jose', 'Lucia', 'Miguel', 'Paula', 'Jorge', 'Elena', 'Fernando', 'Valeria'];
$apellidos = ['Perez', 'Gomez', 'Rodriguez', 'Lopez', 'Martinez', 'Garcia', 'Hernandez', 'Ruiz', 'Diaz', 'Suarez', 'Ramirez', 'Torres', 'Vargas', 'Rios', 'Castro'];
$prefijos_telefono = ['315', '316', '300', '314'];

// Generar Hash de la contraseña "123456"
$password_hash = password_hash('123456', PASSWORD_DEFAULT);

$roles_count = [
    'comprador' => 10,
    'vendedor' => 10,
    'mixto' => 30
];

// Rango de fechas (desde el 2 de abril del 2026 hasta hoy)
$fecha_inicio = strtotime('2026-04-02 00:00:00');
$fecha_fin = time();

$usuarios_generados = 0;

foreach ($roles_count as $rol => $cantidad) {
    for ($i = 0; $i < $cantidad; $i++) {
        // Generar nombre aleatorio
        $nombre = $nombres[array_rand($nombres)] . ' ' . $apellidos[array_rand($apellidos)];
        
        // Generar email (primernombre.apellido[random]@gmail.com)
        $email_base = strtolower(str_replace(' ', '.', $nombre));
        $email = $email_base . rand(100, 999) . '@gmail.com';
        
        // Generar cédula (10 dígitos)
        $cedula = rand(1000000000, 9999999999);
        
        // Generar teléfono (Prefijo + 7 dígitos)
        $prefijo = $prefijos_telefono[array_rand($prefijos_telefono)];
        $resto_telefono = rand(1000000, 9999999);
        $telefono_completo = 'CO-' . $prefijo . $resto_telefono;
        
        // Generar fecha aleatoria
        $timestamp_aleatorio = rand($fecha_inicio, $fecha_fin);
        $fecha_registro = date('Y-m-d H:i:s', $timestamp_aleatorio);
        
        // Insertar en la BD
        try {
            $stmt = $conexion->prepare("
                INSERT INTO usuarios (nombre, email, password, telefono, cedula, rol, estado, fecha_registro) 
                VALUES (:nombre, :email, :password, :telefono, :cedula, :rol, 'activo', :fecha_registro)
            ");
            
            $stmt->execute([
                ':nombre' => $nombre,
                ':email' => $email,
                ':password' => $password_hash,
                ':telefono' => $telefono_completo,
                ':cedula' => $cedula,
                ':rol' => $rol,
                ':fecha_registro' => $fecha_registro
            ]);
            
            $usuarios_generados++;
            echo "✅ Creado $rol: $nombre ($email) - Cédula: $cedula - Tel: $telefono_completo - Fecha: $fecha_registro<br>";
        } catch (PDOException $e) {
            echo "❌ Error al crear $email: " . $e->getMessage() . "<br>";
        }
    }
}

echo "<h2>🎉 Se generaron $usuarios_generados usuarios con éxito.</h2>";
?>
