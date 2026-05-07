<?php
/**
 * Endpoint de Login para React
 * Retorna JSON en lugar de redirigir
 */

require_once __DIR__ . '/../config/cors.php'; // Habilitar CORS para React
session_start();
require_once __DIR__ . '/../config/database.php';

// Asegurarse de que se recibe JSON
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Obtener datos del payload JSON o del POST normal
    $email = trim($input['email'] ?? $_POST["email"] ?? '');
    $password = $input['password'] ?? $_POST["password"] ?? '';

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Faltan credenciales"]);
        exit;
    }

    try {
        $stmt = $conexion->prepare(
            "SELECT id_usuario, nombre, email, password, rol, estado
             FROM usuarios WHERE email = :email LIMIT 1"
        );
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "credenciales_invalidas"]);
            exit;
        }

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($password, $usuario["password"])) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "credenciales_invalidas"]);
            exit;
        }

        if ($usuario["estado"] === "bloqueado") {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "cuenta_bloqueada"]);
            exit;
        }

        // Iniciar sesión
        $_SESSION["id_usuario"] = $usuario["id_usuario"];
        $_SESSION["nombre"]     = $usuario["nombre"];
        $_SESSION["rol"]        = $usuario["rol"];

        echo json_encode([
            "status" => "success",
            "message" => "Login exitoso",
            "user" => [
                "id_usuario" => $usuario["id_usuario"],
                "nombre" => $usuario["nombre"],
                "email" => $usuario["email"],
                "rol" => $usuario["rol"]
            ]
        ]);
        exit;
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error de base de datos"]);
        exit;
    }
}

// Si no es POST
http_response_code(405);
echo json_encode(["status" => "error", "message" => "Método no permitido"]);
exit;
