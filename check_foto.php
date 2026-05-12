<?php
require_once __DIR__ . '/backend/users/config/database.php';
$stmt = $conexion->query("SELECT id_usuario, nombre, apellido, foto_perfil FROM usuarios WHERE nombre LIKE '%Yonatan%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
