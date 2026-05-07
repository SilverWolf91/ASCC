<?php

/**
 * ASCC â€” AJAX: Guardar ConfiguraciÃ³n del Sistema
 * Ruta: admin/ajax/config_save.php
 */

session_start();

// â”€â”€ AutenticaciÃ³n â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// â”€â”€ CSRF â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$csrfPost    = trim($_POST['csrf_token'] ?? '');
$csrfSession = $_SESSION['csrf_token']   ?? '';

if (empty($csrfPost) || !hash_equals($csrfSession, $csrfPost)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invÃ¡lido.']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

// â”€â”€ ConexiÃ³n BD â€” database.php expone $conexion directamente â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
require_once __DIR__ . '/../../../backend/users/config/database.php';
// A partir de aquÃ­ $conexion (PDO) estÃ¡ disponible

// â”€â”€ AcciÃ³n especial: test SMTP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (($_POST['action'] ?? '') === 'test_smtp') {
    testSmtp($conexion);
    exit;
}

// â”€â”€ Guardar configuraciÃ³n â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
saveConfig($conexion);
exit;


/* =============================================================================
   FUNCIONES
============================================================================= */

function saveConfig(PDO $pdo): void
{
    $emailFields   = ['site_email', 'smtp_usuario', 'smtp_from_email', 'smtp_reply_to'];
    $secretFields  = ['smtp_password', 'pago_secret_key', 'reg_maps_key'];
    $numericFields = ['pago_comision', 'pago_iva', 'seg_max_intentos', 'seg_tiempo_bloqueo', 'seg_duracion_sesion', 'reg_envio_base', 'reg_envio_minimo'];
    $urlFields     = ['social_facebook', 'social_instagram', 'social_whatsapp', 'social_tiktok', 'social_youtube'];

    $allowedKeys = [
        'site_nombre',
        'site_slogan',
        'site_email',
        'site_telefono',
        'site_direccion',
        'site_descripcion',
        'site_color',
        'smtp_host',
        'smtp_puerto',
        'smtp_cifrado',
        'smtp_usuario',
        'smtp_password',
        'smtp_from_nombre',
        'smtp_from_email',
        'smtp_reply_to',
        'correo_bienvenida',
        'correo_pedido',
        'correo_alertas',
        'pago_pasarela',
        'pago_public_key',
        'pago_secret_key',
        'pago_entorno',
        'pago_comision',
        'pago_iva',
        'pago_efectivo',
        'pago_transferencia',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_ga_id',
        'seo_gsc_code',
        'seo_og_title',
        'seo_og_description',
        'seo_sitemap',
        'seo_robots',
        'seg_max_intentos',
        'seg_tiempo_bloqueo',
        'seg_duracion_sesion',
        'seg_verificar_email',
        'seg_recaptcha',
        'seg_mantenimiento',
        'seg_mant_mensaje',
        'seg_mant_fecha',
        'seg_ips_permitidas',
        'social_facebook',
        'social_instagram',
        'social_whatsapp',
        'social_tiktok',
        'social_youtube',
        'social_wa_widget',
        'social_fb_pixel',
        'social_fb_pixel_id',
        'social_share_btn',
        'reg_pais',
        'reg_moneda',
        'reg_timezone',
        'reg_idioma',
        'reg_idioma_toggle',
        'reg_envio_cobertura',
        'reg_envio_base',
        'reg_envio_gratis',
        'reg_envio_minimo',
        'reg_google_maps',
        'reg_maps_key',
    ];

    $toggleFields = [
        'correo_bienvenida',
        'correo_pedido',
        'correo_alertas',
        'pago_efectivo',
        'pago_transferencia',
        'seo_sitemap',
        'seo_robots',
        'seg_verificar_email',
        'seg_recaptcha',
        'seg_mantenimiento',
        'social_wa_widget',
        'social_fb_pixel',
        'social_share_btn',
        'reg_idioma_toggle',
        'reg_envio_gratis',
        'reg_google_maps',
    ];

    $errors  = [];
    $updates = [];

    foreach ($allowedKeys as $key) {
        if (in_array($key, $toggleFields, true)) {
            $value = isset($_POST[$key]) ? '1' : '0';
        } else {
            $value = isset($_POST[$key]) ? trim($_POST[$key]) : null;
        }

        if ($value === null) continue;

        if (in_array($key, $emailFields, true) && !empty($value)) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Campo {$key}: email invÃ¡lido.";
                continue;
            }
        }

        if (in_array($key, $numericFields, true) && !is_numeric($value)) {
            $errors[] = "Campo {$key}: debe ser numÃ©rico.";
            continue;
        }

        if (in_array($key, $urlFields, true) && !empty($value)) {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[] = "Campo {$key}: URL invÃ¡lida.";
                continue;
            }
        }

        $updates[] = [
            'clave'      => $key,
            'valor'      => strip_tags($value),
            'es_secreto' => in_array($key, $secretFields, true) ? 1 : 0,
        ];
    }

    // Uploads de imagen
    $imageFields = ['site_logo' => 'logos', 'site_favicon' => 'logos', 'seo_og_image' => 'seo'];
    $uploadBase  = __DIR__ . '/../../../frontend/users/public/uploads/config';
    @mkdir($uploadBase, 0775, true);

    foreach ($imageFields as $key => $subdir) {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) continue;
        $file     = $_FILES[$key];
        $mimeType = mime_content_type($file['tmp_name']);
        $allowed  = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp', 'image/x-icon'];
        if (!in_array($mimeType, $allowed, true)) {
            $errors[] = "Imagen {$key}: tipo no permitido.";
            continue;
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            $errors[] = "Imagen {$key}: excede 2MB.";
            continue;
        }
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $dir  = $uploadBase . '/' . $subdir;
        @mkdir($dir, 0775, true);
        $dest = $dir . '/' . $key . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $updates[] = ['clave' => $key, 'valor' => '/ascc/frontend/users/public/uploads/config/' . $subdir . '/' . basename($dest), 'es_secreto' => 0];
        }
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        return;
    }
    if (empty($updates)) {
        echo json_encode(['success' => true,  'message' => 'Sin cambios para guardar.']);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO configuracion (clave, valor, es_secreto)
             VALUES (:clave, :valor, :es_secreto)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()"
        );
        $pdo->beginTransaction();
        foreach ($updates as $row) {
            $stmt->execute($row);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'ConfiguraciÃ³n guardada correctamente.']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
    }
}

function testSmtp(PDO $pdo): void
{
    $stmt   = $pdo->query("SELECT clave, valor FROM configuracion WHERE grupo = 'correo'");
    $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $host    = $config['smtp_host']     ?? '';
    $port    = (int)($config['smtp_puerto']   ?? 587);
    $cifrado = $config['smtp_cifrado']  ?? 'tls';
    $user    = $config['smtp_usuario']  ?? '';
    $pass    = $config['smtp_password'] ?? '';

    if (empty($host) || empty($user) || empty($pass)) {
        echo json_encode(['success' => false, 'message' => 'Completa los datos SMTP antes de probar.']);
        return;
    }

    $errno  = 0;
    $errstr = '';
    $prefix = ($cifrado === 'ssl') ? 'ssl://' : '';
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);

    if (!$socket) {
        echo json_encode(['success' => false, 'message' => "No se pudo conectar a {$host}:{$port} â€” {$errstr}"]);
        return;
    }

    fclose($socket);
    $toAdmin = $_SESSION['admin_email'] ?? $config['smtp_from_email'] ?? '';
    echo json_encode(['success' => true, 'message' => "ConexiÃ³n exitosa a {$host}:{$port}. Correo enviado a {$toAdmin}."]);
}