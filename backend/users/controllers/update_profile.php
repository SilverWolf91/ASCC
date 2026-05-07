<?php

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * ASCC â€” Controlador: Actualizar Perfil de Usuario
 * Ruta: C:\xampp\htdocs\ascc\controllers\update_profile.php
 *
 * Recibe POST (multipart/form-data) desde modal-perfil.js
 * Actualiza tabla: usuarios (todos los campos del modal)
 * Responde JSON: { success, message, user? }
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */

ini_set('display_errors', '0');
ini_set('log_errors',     '1');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['success' => false, 'message' => 'MÃ©todo no permitido.']));
}

/* Bufferar solo los includes para absorber cualquier
   whitespace / BOM / aviso que generen */
ob_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

/* â”€â”€ Verificar sesiÃ³n â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => t('session_expired')]));
}

$id_usuario = (int) $_SESSION['id_usuario'];

/* â”€â”€ Verificar CSRF â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$csrf_token  = trim($_POST['csrf_token'] ?? '');
$csrf_sesion = $_SESSION['csrf_token']   ?? '';

if (empty($csrf_token) || !hash_equals($csrf_sesion, $csrf_token)) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => t('invalid_token')]));
}

/* â”€â”€ Leer y sanitizar campos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$nombre        = trim($_POST['nombre']          ?? '');
$apellido      = trim($_POST['apellido']        ?? '');
$bio           = trim($_POST['bio']             ?? '');
$email         = trim($_POST['email']           ?? '');
$indicativo    = trim($_POST['indicativo']      ?? '+57');
$telefono      = trim($_POST['telefono']        ?? '');
$tipo_doc      = trim($_POST['tipo_documento']  ?? 'CC');
$cedula        = trim($_POST['numero_documento'] ?? $_POST['cedula'] ?? '');
$departamento  = trim($_POST['departamento']    ?? '');
$municipio     = trim($_POST['municipio']       ?? '');
$vereda        = trim($_POST['vereda']          ?? '');
$rol           = trim($_POST['rol']             ?? 'vendedor');

/* Notificaciones (el JS siempre envÃ­a '1' o '0') */
$notif_mensajes    = ($_POST['notif_mensajes']    ?? '0') === '1' ? 1 : 0;
$notif_ventas      = ($_POST['notif_ventas']      ?? '0') === '1' ? 1 : 0;
$notif_visitas     = ($_POST['notif_visitas']     ?? '0') === '1' ? 1 : 0;
$notif_promociones = ($_POST['notif_promociones'] ?? '0') === '1' ? 1 : 0;

/* â”€â”€ Obtener datos actuales del usuario (email + foto) â”€â”€â”€â”€ */
$stmt = $conexion->prepare('SELECT foto_perfil, email FROM usuarios WHERE id_usuario = :id');
$stmt->bindParam(':id', $id_usuario, PDO::PARAM_INT);
$stmt->execute();
$fila_actual        = $stmt->fetch(PDO::FETCH_ASSOC);
$email_actual       = $fila_actual['email']       ?? '';
$foto_perfil_actual = $fila_actual['foto_perfil'] ?? null;

/* â”€â”€ Validaciones â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$errores = [];

/* Nombre obligatorio */
if (empty($nombre)) {
    $errores[] = t('validation_required') . ': ' . t('field_nombre');
} elseif (mb_strlen($nombre) > 100) {
    $errores[] = t('field_nombre') . ': mÃ¡ximo 100 caracteres.';
}

/* Email obligatorio y vÃ¡lido */
if (empty($email)) {
    $errores[] = t('validation_required') . ': ' . t('field_email');
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = t('validation_email');
} elseif (mb_strlen($email) > 100) {
    $errores[] = t('field_email') . ': mÃ¡ximo 100 caracteres.';
}

/* TelÃ©fono: opcional, formato bÃ¡sico */
if (!empty($telefono) && !preg_match('/^[\d\s\+\-\(\)]{6,20}$/', $telefono)) {
    $errores[] = 'TelÃ©fono con formato invÃ¡lido.';
}

/* CÃ©dula: opcional, alfanumÃ©rico */
if (!empty($cedula) && !preg_match('/^[A-Za-z0-9\-]{4,20}$/', $cedula)) {
    $errores[] = 'CÃ©dula con formato invÃ¡lido.';
}

/* Tipo documento */
$tipos_doc_validos = ['CC', 'NIT', 'PP', 'CE'];
if (!in_array($tipo_doc, $tipos_doc_validos)) {
    $tipo_doc = 'CC';
}

/* Indicativo */
$indicativos_validos = [
    '+57', '+1', '+52', '+54', '+55', '+56', '+51', '+58',
    '+593', '+591', '+595', '+598', '+507', '+506', '+503',
    '+502', '+504', '+505', '+1809', '+53',
    '+34', '+44', '+49', '+33', '+39', '+351',
];
if (!in_array($indicativo, $indicativos_validos)) {
    $indicativo = '+57';
}

/* Rol */
$roles_validos = ['vendedor', 'comprador', 'mixto'];
if (!in_array($rol, $roles_validos)) {
    $rol = 'vendedor';
}

/* Bio: mÃ¡ximo 300 caracteres */
if (mb_strlen($bio) > 300) {
    $bio = mb_substr($bio, 0, 300);
}

/* â”€â”€ Validar contraseÃ±a (solo si se enviÃ³) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password']     ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$cambiar_password = false;
$nuevo_hash       = null;

if (!empty($new_password) || !empty($confirm_password)) {

    if (empty($current_password)) {
        $errores[] = t('field_current_pass') . ' es requerida.';
    }

    if (mb_strlen($new_password) < 8) {
        $errores[] = t('validation_pass_length');
    }

    if ($new_password !== $confirm_password) {
        $errores[] = t('validation_pass_match');
    }

    /* Verificar contraseÃ±a actual solo si no hay otros errores */
    if (empty($errores)) {
        $stmt = $conexion->prepare(
            'SELECT password FROM usuarios WHERE id_usuario = :id'
        );
        $stmt->bindParam(':id', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($current_password, $row['password'])) {
            $errores[] = 'La contraseÃ±a actual es incorrecta.';
        } else {
            $nuevo_hash       = password_hash($new_password, PASSWORD_BCRYPT);
            $cambiar_password = true;
        }
    }
}

/* â”€â”€ Verificar email Ãºnico â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if (empty($errores)) {
    $stmt = $conexion->prepare(
        'SELECT id_usuario FROM usuarios
         WHERE email = :email AND id_usuario != :id'
    );
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':id',    $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->fetch()) {
        $errores[] = 'Este correo ya estÃ¡ registrado por otro usuario.';
    }
}

/* â”€â”€ 2FA: validar OTP para cambio de email â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if (empty($errores) && $email !== $email_actual) {
    $otp_email  = trim($_POST['otp_email'] ?? '');
    $otp_sesion = $_SESSION['otp']['email'] ?? null;

    if (empty($otp_email)) {
        $errores[] = t('otp_required');
    } elseif (!$otp_sesion || time() > ($otp_sesion['expiry'] ?? 0)) {
        $errores[] = t('otp_expired');
    } elseif (($otp_sesion['destino'] ?? '') !== $email) {
        $errores[] = t('otp_wrong_email');
    } elseif (!hash_equals($otp_sesion['code'], $otp_email)) {
        $errores[] = t('otp_invalid');
    } else {
        unset($_SESSION['otp']['email']);
    }
}

/* â”€â”€ 2FA: validar OTP para cambio de contraseÃ±a â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if (empty($errores) && $cambiar_password) {
    $otp_pass   = trim($_POST['otp_password'] ?? '');
    $otp_sesion = $_SESSION['otp']['password'] ?? null;

    if (empty($otp_pass)) {
        $errores[] = t('otp_required');
    } elseif (!$otp_sesion || time() > ($otp_sesion['expiry'] ?? 0)) {
        $errores[] = t('otp_expired');
    } elseif (!hash_equals($otp_sesion['code'], $otp_pass)) {
        $errores[] = t('otp_invalid');
    } else {
        unset($_SESSION['otp']['password']);
    }
}

/* â”€â”€ Devolver errores si los hay â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if (!empty($errores)) {
    exit(json_encode([
        'success' => false,
        'message' => implode(' | ', $errores),
    ]));
}

/* â”€â”€ Procesar foto de perfil â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$foto_perfil_nueva = null;
// $foto_perfil_actual ya fue obtenida antes de las validaciones

if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {

    $archivo   = $_FILES['avatar'];
    $tipos_ok  = ['image/jpeg', 'image/png', 'image/webp'];
    $ext_map   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $max_bytes = 120 * 1024 * 1024; // 15 MB â€” soporta fotos de iPhone 8K+

    /* Validar MIME real */
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $mime_real = $finfo->file($archivo['tmp_name']);

    if (!in_array($mime_real, $tipos_ok)) {
        exit(json_encode(['success' => false, 'message' => t('avatar_type_error')]));
    }

    if ($archivo['size'] > $max_bytes) {
        exit(json_encode(['success' => false, 'message' => t('avatar_size_error')]));
    }

    /* Nombre de archivo Ãºnico */
    $ext         = $ext_map[$mime_real];
    $nombre_file = 'avatar_' . $id_usuario . '_' . time() . '.' . $ext;
    $dir_destino = __DIR__ . '/../../frontend/users/public/uploads/avatars/';
    $ruta_db     = 'uploads/avatars/' . $nombre_file;

    /* Crear directorio si no existe */
    if (!is_dir($dir_destino)) {
        mkdir($dir_destino, 0755, true);
    }

    if (!move_uploaded_file($archivo['tmp_name'], $dir_destino . $nombre_file)) {
        exit(json_encode([
            'success' => false,
            'message' => 'Error al guardar la imagen. Verifica permisos de la carpeta.',
        ]));
    }

    /* Borrar foto anterior */
    if (!empty($foto_perfil_actual)) {
        $ruta_anterior = __DIR__ . '/../public/' . $foto_perfil_actual;
        if (file_exists($ruta_anterior)) {
            unlink($ruta_anterior);
        }
    }

    $foto_perfil_nueva = $ruta_db;
}

/* â”€â”€ Construir UPDATE dinÃ¡mico â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$campos = [
    'nombre           = :nombre',
    'apellido         = :apellido',
    'bio              = :bio',
    'email            = :email',
    'indicativo       = :indicativo',
    'telefono         = :telefono',
    'tipo_documento   = :tipo_documento',
    'cedula           = :cedula',
    'departamento     = :departamento',
    'municipio        = :municipio',
    'vereda           = :vereda',
    'rol              = :rol',
    'notif_mensajes    = :notif_mensajes',
    'notif_ventas      = :notif_ventas',
    'notif_visitas     = :notif_visitas',
    'notif_promociones = :notif_promociones',
];

$params = [
    ':nombre'           => $nombre,
    ':apellido'         => $apellido ?: null,
    ':bio'              => $bio      ?: null,
    ':email'            => $email,
    ':indicativo'       => $indicativo,
    ':telefono'         => $telefono  ?: null,
    ':tipo_documento'   => $tipo_doc,
    ':cedula'           => $cedula    ?: null,
    ':departamento'     => $departamento ?: null,
    ':municipio'        => $municipio    ?: null,
    ':vereda'           => $vereda       ?: null,
    ':rol'              => $rol,
    ':notif_mensajes'    => $notif_mensajes,
    ':notif_ventas'      => $notif_ventas,
    ':notif_visitas'     => $notif_visitas,
    ':notif_promociones' => $notif_promociones,
    ':id'               => $id_usuario,
];

/* Agregar foto solo si se subiÃ³ una nueva */
if ($foto_perfil_nueva !== null) {
    $campos[]               = 'foto_perfil = :foto_perfil';
    $params[':foto_perfil'] = $foto_perfil_nueva;
}

/* Agregar contraseÃ±a solo si se cambiÃ³ */
if ($cambiar_password) {
    $campos[]            = 'password = :password';
    $params[':password'] = $nuevo_hash;
}

$sql = 'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id_usuario = :id';

try {
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
} catch (PDOException $e) {
    error_log('[ASCC] update_profile PDO error: ' . $e->getMessage());
    exit(json_encode([
        'success' => false,
        'message' => t('profile_error'),
    ]));
}

/* â”€â”€ Actualizar datos en sesiÃ³n â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$_SESSION['nombre'] = $nombre;
$_SESSION['rol']    = $rol;

/* â”€â”€ Construir URL del avatar para la respuesta â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if ($foto_perfil_nueva !== null) {
    $avatar_url = '/ascc/frontend/users/public/' . $foto_perfil_nueva;
} elseif (!empty($foto_perfil_actual)) {
    $avatar_url = '/ascc/frontend/users/public/' . $foto_perfil_actual;
} else {
    $avatar_url = null;
}

/* â”€â”€ Respuesta exitosa â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
echo json_encode([
    'success' => true,
    'message' => t('profile_updated'),
    'user'    => [
        'nombre'     => $nombre,
        'apellido'   => $apellido,
        'email'      => $email,
        'avatar_url' => $avatar_url,
    ],
]);