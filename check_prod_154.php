<?php
require_once __DIR__ . '/backend/users/config/database.php';
$stmt = $conexion->query("
    SELECT
        p.id_producto,
        usr.nombre as vendedor_nombre,
        usr.apellido as vendedor_apellido,
        usr.foto_perfil as vendedor_foto
    FROM productos p
    INNER JOIN usuarios usr ON p.id_usuario = usr.id_usuario
    WHERE p.id_producto = 154
");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
