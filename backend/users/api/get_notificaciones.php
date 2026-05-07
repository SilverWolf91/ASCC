<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC — API: Notificaciones en Tiempo Real
 * Ruta: api/get_notificaciones.php
 *
 * GET  → Devuelve notificaciones no leídas del usuario.
 * POST action=marcar_leida  → Marca una notificación como leída.
 * POST action=marcar_todas  → Marca todas como leídas.
 * ═══════════════════════════════════════════════════════════
 */

ini_set('display_errors', '0');
ini_set('log_errors',     '1');

ob_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

/* ── Verificar sesión ──────────────────────────────────────── */
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado.']));
}

$id_usuario = (int) $_SESSION['id_usuario'];
$rol        = $_SESSION['rol'] ?? 'comprador';

ob_end_clean();

/* ════════════════════════════════════════════════════════════
   GET — Listar notificaciones no leídas
════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $sql = '
        SELECT n.id_notificacion, n.titulo, n.mensaje, n.tipo, n.fecha_creacion
        FROM   notificaciones n
        WHERE  n.activa = 1
          AND (
                n.id_destinatario = :id_usuario
             OR n.destinatario_rol = \'todos\'
             OR n.destinatario_rol = :rol
          )
          AND  n.id_notificacion NOT IN (
                SELECT nl.id_notificacion
                FROM   notificaciones_leidas nl
                WHERE  nl.id_usuario = :id_usuario2
          )
        ORDER BY n.fecha_creacion DESC
        LIMIT 15
    ';

    try {
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':id_usuario',  $id_usuario, PDO::PARAM_INT);
        $stmt->bindParam(':rol',         $rol,        PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[ASCC] get_notificaciones GET error: ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Error al obtener notificaciones.']));
    }

    exit(json_encode([
        'success'        => true,
        'notificaciones' => $notificaciones,
        'total_no_leidas'=> count($notificaciones),
    ]));
}

/* ════════════════════════════════════════════════════════════
   POST — Marcar como leída(s)
════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim($_POST['action'] ?? '');

    /* ── Marcar una sola ─────────────────────────────────── */
    if ($action === 'marcar_leida') {
        $id_notif = (int) ($_POST['id_notificacion'] ?? 0);
        if ($id_notif <= 0) {
            exit(json_encode(['success' => false, 'message' => 'ID inválido.']));
        }

        try {
            $stmt = $conexion->prepare('
                INSERT IGNORE INTO notificaciones_leidas (id_notificacion, id_usuario)
                VALUES (:id_notif, :id_usuario)
            ');
            $stmt->bindParam(':id_notif',    $id_notif,   PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario',  $id_usuario, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log('[ASCC] get_notificaciones marcar_leida error: ' . $e->getMessage());
            exit(json_encode(['success' => false, 'message' => 'Error al marcar.']));
        }

        exit(json_encode(['success' => true]));
    }

    /* ── Marcar todas ────────────────────────────────────── */
    if ($action === 'marcar_todas') {
        try {
            $stmt = $conexion->prepare('
                INSERT IGNORE INTO notificaciones_leidas (id_notificacion, id_usuario)
                SELECT n.id_notificacion, :id_usuario
                FROM   notificaciones n
                WHERE  n.activa = 1
                  AND (
                        n.id_destinatario = :id_usuario2
                     OR n.destinatario_rol = \'todos\'
                     OR n.destinatario_rol = :rol
                  )
                  AND n.id_notificacion NOT IN (
                        SELECT nl.id_notificacion
                        FROM   notificaciones_leidas nl
                        WHERE  nl.id_usuario = :id_usuario3
                  )
            ');
            $stmt->bindParam(':id_usuario',  $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario3', $id_usuario, PDO::PARAM_INT);
            $stmt->bindParam(':rol',         $rol,        PDO::PARAM_STR);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log('[ASCC] get_notificaciones marcar_todas error: ' . $e->getMessage());
            exit(json_encode(['success' => false, 'message' => 'Error al marcar todas.']));
        }

        exit(json_encode(['success' => true]));
    }

    exit(json_encode(['success' => false, 'message' => 'Acción desconocida.']));
}

http_response_code(405);
exit(json_encode(['success' => false, 'message' => 'Método no permitido.']));
