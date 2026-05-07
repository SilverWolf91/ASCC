<?php

/**
 * ASCC — API de Reseñas
 * Ruta: api/reviews.php
 *
 * Endpoint único AJAX para todas las operaciones de reseñas.
 * Acciones GET:  summary, list
 * Acciones POST: create, update, delete
 * Responde siempre JSON.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Solo peticiones AJAX
if (
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'forbidden']);
    exit;
}

// Mapeo tipo → tabla
const TABLA_MAP = [
    'producto'  => 'resenas_producto',
    'vendedor'  => 'resenas_vendedor',
    'comprador' => 'resenas_comprador',
];

// Columnas FK por tabla: [col_reseñado, col_autor]
const FK_MAP = [
    'resenas_producto'  => ['id_producto',  'id_usuario'],
    'resenas_vendedor'  => ['id_vendedor',  'id_comprador'],
    'resenas_comprador' => ['id_comprador', 'id_vendedor'],
];

// Router
$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET'
    ? trim($_GET['action']  ?? '')
    : trim($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'summary':
            handleSummary();
            break;
        case 'list':
            handleList();
            break;
        case 'create':
            handleCreate();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'delete':
            handleDelete();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'invalid_action'], 400);
    }
} catch (Throwable $e) {
    error_log('[ASCC reviews.php] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'server_error'], 500);
}

// ════════════════════════════════════════════════════════════
// HANDLERS
// ════════════════════════════════════════════════════════════

function handleSummary(): void
{
    global $conexion;

    [$tabla, $colResenado] = resolverTablaYCols(INPUT_GET);
    $id = filtrarInt('id', INPUT_GET);

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'id_requerido'], 422);
    }

    $sql = "
        SELECT
            ROUND(AVG(calificacion), 1) AS promedio,
            COUNT(*)                    AS total,
            SUM(calificacion = 5)       AS e5,
            SUM(calificacion = 4)       AS e4,
            SUM(calificacion = 3)       AS e3,
            SUM(calificacion = 2)       AS e2,
            SUM(calificacion = 1)       AS e1
        FROM `{$tabla}`
        WHERE `{$colResenado}` = :id
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    $yaReseno = false;
    if (isset($_SESSION['id_usuario'])) {
        $colAutor = FK_MAP[$tabla][1];
        $yaReseno = yaExisteResena(
            $tabla,
            $colResenado,
            $id,
            $colAutor,
            (int) $_SESSION['id_usuario']
        );
    }

    jsonResponse([
        'success'      => true,
        'promedio'     => (float) ($row['promedio'] ?? 0),
        'total'        => (int)   ($row['total']    ?? 0),
        'distribucion' => [
            5 => (int)($row['e5'] ?? 0),
            4 => (int)($row['e4'] ?? 0),
            3 => (int)($row['e3'] ?? 0),
            2 => (int)($row['e2'] ?? 0),
            1 => (int)($row['e1'] ?? 0),
        ],
        'ya_reseno' => $yaReseno,
    ]);
}

function handleList(): void
{
    global $conexion;

    [$tabla, $colResenado, $colAutor] = resolverTablaYCols(INPUT_GET);
    $id     = filtrarInt('id',   INPUT_GET);
    $page   = max(1, filtrarInt('page', INPUT_GET, 1));
    $limit  = 8;
    $offset = ($page - 1) * $limit;

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'id_requerido'], 422);
    }

    $sql = "
        SELECT
            r.id_resena,
            r.calificacion,
            r.titulo,
            r.comentario,
            r.fecha_resena,
            u.id_usuario  AS autor_id,
            u.nombre      AS autor_nombre,
            u.foto_perfil AS autor_foto,
            u.rol         AS autor_rol
        FROM `{$tabla}` r
        INNER JOIN usuarios u ON u.id_usuario = r.`{$colAutor}`
        WHERE r.`{$colResenado}` = :id
        ORDER BY r.fecha_resena DESC
        LIMIT  :limit
        OFFSET :offset
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':id',     $id,     PDO::PARAM_INT);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $resenas = $stmt->fetchAll();

    jsonResponse([
        'success'    => true,
        'resenas'    => $resenas,
        'pagina'     => $page,
        'por_pagina' => $limit,
        'hay_mas'    => count($resenas) >= $limit,
    ]);
}

function handleCreate(): void
{
    global $conexion;

    if (!isset($_SESSION['id_usuario'])) {
        jsonResponse(['success' => false, 'message' => 'no_sesion'], 401);
    }

    $idAutor = (int) $_SESSION['id_usuario'];

    [$tabla, $colResenado, $colAutor] = resolverTablaYCols(INPUT_POST);

    $idResenado   = filtrarInt('id',           INPUT_POST);
    $calificacion = filtrarInt('calificacion',  INPUT_POST);
    $titulo       = sanitizarTexto($_POST['titulo']     ?? '', 150);
    $comentario   = sanitizarTexto($_POST['comentario'] ?? '', 1000);

    // ── Validaciones de campos ────────────────────────────────
    if (!$idResenado) {
        jsonResponse(['success' => false, 'message' => 'id_requerido'], 422);
    }

    if ($calificacion < 1 || $calificacion > 5) {
        jsonResponse(['success' => false, 'message' => 'calificacion_invalida'], 422);
    }

    if (empty($comentario)) {
        jsonResponse(['success' => false, 'message' => 'comentario_requerido'], 422);
    }

    // ── No puede reseñarse a sí mismo (usuario) ───────────────
    if (
        in_array($tabla, ['resenas_vendedor', 'resenas_comprador'], true) &&
        $idAutor === $idResenado
    ) {
        jsonResponse(['success' => false, 'message' => 'auto_resena'], 422);
    }

    // ── No puede reseñar su propio producto ───────────────────
    if ($tabla === 'resenas_producto') {
        $stmtDueno = $conexion->prepare(
            "SELECT id_usuario FROM productos WHERE id_producto = :id LIMIT 1"
        );
        $stmtDueno->execute([':id' => $idResenado]);
        $idDueno = (int) $stmtDueno->fetchColumn();

        if ($idDueno === $idAutor) {
            jsonResponse(['success' => false, 'message' => 'auto_resena'], 422);
        }
    }

    // ── No puede dejar 2 reseñas al mismo elemento ────────────
    if (yaExisteResena($tabla, $colResenado, $idResenado, $colAutor, $idAutor)) {
        jsonResponse(['success' => false, 'message' => 'ya_resenado'], 422);
    }

    // ── Insertar ──────────────────────────────────────────────
    $sql = "
        INSERT INTO `{$tabla}`
            (`{$colResenado}`, `{$colAutor}`, `calificacion`, `titulo`, `comentario`)
        VALUES
            (:resenado, :autor, :calificacion, :titulo, :comentario)
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':resenado'     => $idResenado,
        ':autor'        => $idAutor,
        ':calificacion' => $calificacion,
        ':titulo'       => $titulo ?: null,
        ':comentario'   => $comentario,
    ]);

    // ── Notificación automática para reseñas entre usuarios ───
    if ($tabla !== 'resenas_producto') {
        crearNotificacionResena($tabla, $idResenado, $idAutor, $calificacion);
    }

    jsonResponse(['success' => true, 'message' => 'resena_creada'], 201);
}

/**
 * Edita una reseña existente.
 * Solo el propio autor puede modificar su reseña.
 */
function handleUpdate(): void
{
    global $conexion;

    if (!isset($_SESSION['id_usuario'])) {
        jsonResponse(['success' => false, 'message' => 'no_sesion'], 401);
    }

    $idAutor = (int) $_SESSION['id_usuario'];

    [$tabla,, $colAutor] = resolverTablaYCols(INPUT_POST);

    $idResena     = filtrarInt('id_resena',    INPUT_POST);
    $calificacion = filtrarInt('calificacion', INPUT_POST);
    $titulo       = sanitizarTexto($_POST['titulo']     ?? '', 150);
    $comentario   = sanitizarTexto($_POST['comentario'] ?? '', 1000);

    // ── Validaciones ──────────────────────────────────────────
    if (!$idResena) {
        jsonResponse(['success' => false, 'message' => 'id_requerido'], 422);
    }

    if ($calificacion < 1 || $calificacion > 5) {
        jsonResponse(['success' => false, 'message' => 'calificacion_invalida'], 422);
    }

    if (empty($comentario)) {
        jsonResponse(['success' => false, 'message' => 'comentario_requerido'], 422);
    }

    // ── Actualizar — solo si la reseña pertenece al autor ─────
    $sql = "
        UPDATE `{$tabla}`
        SET
            calificacion = :calificacion,
            titulo       = :titulo,
            comentario   = :comentario
        WHERE id_resena      = :id_resena
          AND `{$colAutor}`  = :autor
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':calificacion' => $calificacion,
        ':titulo'       => $titulo ?: null,
        ':comentario'   => $comentario,
        ':id_resena'    => $idResena,
        ':autor'        => $idAutor,
    ]);

    if ($stmt->rowCount() > 0) {
        jsonResponse(['success' => true, 'message' => 'resena_actualizada']);
    } else {
        // rowCount = 0 puede ser: no es el autor, o los datos son idénticos
        // Verificamos si la reseña existe y pertenece al autor
        $stmtCheck = $conexion->prepare(
            "SELECT 1 FROM `{$tabla}`
             WHERE id_resena = :id AND `{$colAutor}` = :autor LIMIT 1"
        );
        $stmtCheck->execute([':id' => $idResena, ':autor' => $idAutor]);

        if ($stmtCheck->fetchColumn()) {
            // Existe y es del autor — los datos eran idénticos, igual es éxito
            jsonResponse(['success' => true, 'message' => 'resena_actualizada']);
        } else {
            jsonResponse(['success' => false, 'message' => 'no_autorizado'], 403);
        }
    }
}

function handleDelete(): void
{
    global $conexion;

    if (!isset($_SESSION['id_usuario'])) {
        jsonResponse(['success' => false, 'message' => 'no_sesion'], 401);
    }

    [$tabla,, $colAutor] = resolverTablaYCols(INPUT_POST);
    $idResena  = filtrarInt('id_resena', INPUT_POST);
    $idUsuario = (int) $_SESSION['id_usuario'];
    $esAdmin   = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';

    if (!$idResena) {
        jsonResponse(['success' => false, 'message' => 'id_requerido'], 422);
    }

    if ($esAdmin) {
        $sql    = "DELETE FROM `{$tabla}` WHERE id_resena = :id";
        $params = [':id' => $idResena];
    } else {
        $sql    = "DELETE FROM `{$tabla}` WHERE id_resena = :id AND `{$colAutor}` = :autor";
        $params = [':id' => $idResena, ':autor' => $idUsuario];
    }

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        jsonResponse(['success' => true,  'message' => 'resena_eliminada']);
    } else {
        jsonResponse(['success' => false, 'message' => 'no_autorizado'], 403);
    }
}

// ════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════

function resolverTablaYCols(int $inputType = INPUT_GET): array
{
    $tipo = trim(
        filter_input($inputType, 'tipo', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''
    );

    if (!array_key_exists($tipo, TABLA_MAP)) {
        jsonResponse(['success' => false, 'message' => 'tipo_invalido'], 422);
    }

    $tabla = TABLA_MAP[$tipo];
    [$colResenado, $colAutor] = FK_MAP[$tabla];

    return [$tabla, $colResenado, $colAutor];
}

function yaExisteResena(
    string $tabla,
    string $colResenado,
    int    $idResenado,
    string $colAutor,
    int    $idAutor
): bool {
    global $conexion;

    $sql = "
        SELECT 1 FROM `{$tabla}`
        WHERE `{$colResenado}` = :resenado
          AND `{$colAutor}`    = :autor
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([':resenado' => $idResenado, ':autor' => $idAutor]);
    return (bool) $stmt->fetchColumn();
}

function crearNotificacionResena(
    string $tabla,
    int    $idDestinatario,
    int    $idRemitente,
    int    $calificacion
): void {
    global $conexion, $translations;

    $stmt = $conexion->prepare(
        "SELECT nombre FROM usuarios WHERE id_usuario = :id LIMIT 1"
    );
    $stmt->execute([':id' => $idRemitente]);
    $remitente = $stmt->fetchColumn() ?: 'Un usuario';

    $estrellas = str_repeat('⭐', $calificacion);
    $rolDest   = $tabla === 'resenas_vendedor' ? 'vendedor' : 'comprador';

    $titulo  = $translations['notif_nueva_resena_titulo'] ?? 'Nueva reseña recibida';
    $mensaje = $translations['notif_nueva_resena_msg']    ?? '{nombre} te dejó una reseña de {estrellas}';
    $mensaje = str_replace(
        ['{nombre}',  '{estrellas}'],
        [$remitente,   $estrellas],
        $mensaje
    );

    $sql = "
        INSERT INTO notificaciones
            (titulo, mensaje, tipo, destinatario_rol, id_destinatario, id_remitente)
        VALUES
            (:titulo, :mensaje, 'info', :rol, :destinatario, :remitente)
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':titulo'       => $titulo,
        ':mensaje'      => $mensaje,
        ':rol'          => $rolDest,
        ':destinatario' => $idDestinatario,
        ':remitente'    => $idRemitente,
    ]);
}

function filtrarInt(string $key, int $inputType, int $default = 0): int
{
    return (int) filter_input($inputType, $key, FILTER_SANITIZE_NUMBER_INT) ?: $default;
}

function sanitizarTexto(string $texto, int $max): string
{
    $limpio = htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
    return mb_substr($limpio, 0, $max);
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}