<?php

/**
 * ASCC — API de Datos para Módulo de Reportes
 * Ruta: api/reportes_data.php
 *
 * Acepta:
 *   GET  ?action=ventas_diarias|productos_ventas|categorias_ventas|
 *              precio_scatter|funnel|visitas_perfil|evolucion_calificacion|
 *              compras_categorias|gasto_mensual|excel|csv
 *              &csrf=TOKEN
 *
 *   POST action=crear_denuncia|generar_token
 *        csrf_token=TOKEN
 *
 * Seguridad:
 *   - CSRF token validado en TODAS las peticiones
 *   - Cada consulta filtra por id_usuario de la sesión
 *   - Whitelist de acciones permitidas
 *   - Acceso por token API (Power BI) sin sesión
 */

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';

// ── Autenticación por sesión o por token API ──────────────────
$id_usuario = null;
$rol        = null;

if (isset($_SESSION['id_usuario'])) {
    // Acceso desde el panel del usuario
    $id_usuario = (int)$_SESSION['id_usuario'];
} elseif (isset($_GET['token'])) {
    // Acceso desde Power BI via token
    $token = trim($_GET['token']);
    $stmt  = $conexion->prepare(
        "SELECT id_usuario FROM api_tokens
         WHERE token = :token AND activo = 1 LIMIT 1"
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    if ($row) {
        $id_usuario = (int)$row['id_usuario'];
        // Actualizar último uso
        $conexion->prepare(
            "UPDATE api_tokens SET ultimo_uso = NOW() WHERE token = :token"
        )->execute([':token' => $token]);
    }
}

if (!$id_usuario) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// ── Verificar CSRF (solo para peticiones desde el navegador, no Power BI) ──
$accion = $_GET['action'] ?? $_POST['action'] ?? '';

if (!isset($_GET['token'])) {
    $csrfRecibido = trim($_GET['csrf'] ?? $_POST['csrf_token'] ?? '');
    $csrfSesion   = $_SESSION['csrf_token'] ?? '';
    if (empty($csrfRecibido) || !hash_equals($csrfSesion, $csrfRecibido)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }
}

// ── Obtener rol del usuario ───────────────────────────────────
$stmt = $conexion->prepare(
    "SELECT rol FROM usuarios WHERE id_usuario = :id LIMIT 1"
);
$stmt->execute([':id' => $id_usuario]);
$user = $stmt->fetch();
$rol  = $user['rol'] ?? 'comprador';

// Vista activa para mixto
$vista = $_SESSION['rep_vista'] ?? 'vendedor';
$es_vendedor  = in_array($rol, ['vendedor', 'mixto'], true) && $vista === 'vendedor';
$es_comprador = $rol === 'comprador' || ($rol === 'mixto' && $vista === 'comprador');

// ── Router de acciones ────────────────────────────────────────
$acciones_get  = [
    'ventas_diarias',
    'productos_ventas',
    'categorias_ventas',
    'precio_scatter',
    'funnel',
    'visitas_perfil',
    'evolucion_calificacion',
    'compras_categorias',
    'gasto_mensual',
    'excel',
    'csv',
];
$acciones_post = ['crear_denuncia', 'generar_token'];

// ── Acceso Power BI via token sin action → devolver feed completo ──
if (isset($_GET['token'])) {
    // Si viene con ?table=X devolver tabla plana directamente
    if (!empty($_GET['table'])) {
        powerBiTable($conexion, $id_usuario, $rol, trim($_GET['table']));
    } else {
        powerBiFeed($conexion, $id_usuario, $rol);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($accion, $acciones_post, true)) {
        echo json_encode(['success' => false, 'message' => 'Acción no permitida']);
        exit;
    }
    switch ($accion) {
        case 'crear_denuncia':
            crearDenuncia($conexion, $id_usuario);
            break;
        case 'generar_token':
            generarToken($conexion, $id_usuario);
            break;
    }
    exit;
}

// GET
if (!in_array($accion, $acciones_get, true)) {
    echo json_encode(['success' => false, 'message' => 'Acción no permitida']);
    exit;
}

switch ($accion) {
    case 'ventas_diarias':
        ventasDiarias($conexion, $id_usuario);
        break;
    case 'productos_ventas':
        productosVentas($conexion, $id_usuario);
        break;
    case 'categorias_ventas':
        categoriasVentas($conexion, $id_usuario);
        break;
    case 'precio_scatter':
        precioScatter($conexion, $id_usuario);
        break;
    case 'funnel':
        funnelConversion($conexion, $id_usuario);
        break;
    case 'visitas_perfil':
        visitasPerfil($conexion, $id_usuario);
        break;
    case 'evolucion_calificacion':
        evolucionCalificacion($conexion, $id_usuario);
        break;
    case 'compras_categorias':
        comprasCategorias($conexion, $id_usuario);
        break;
    case 'gasto_mensual':
        gastoMensual($conexion, $id_usuario);
        break;
    case 'excel':
        exportarExcel($conexion, $id_usuario, $rol);
        break;
    case 'csv':
        exportarCsv($conexion, $id_usuario, $rol);
        break;
}

/* =============================================================================
   FUNCIONES DE DATOS
============================================================================= */

/**
 * Ventas diarias — últimos 30 días vs mes anterior
 */
function ventasDiarias(PDO $pdo, int $uid): void
{
    // Generar etiquetas de los últimos 30 días
    $labels   = [];
    $actual   = [];
    $anterior = [];

    for ($i = 29; $i >= 0; $i--) {
        $fecha = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = date('d/m', strtotime($fecha));

        // Ventas del día actual
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(t.total), 0)
             FROM transacciones t
             INNER JOIN productos p ON t.id_producto = p.id_producto
             WHERE p.id_usuario = :uid
               AND t.estado = 'aprobado'
               AND DATE(t.fecha_creacion) = :fecha"
        );
        $stmt->execute([':uid' => $uid, ':fecha' => $fecha]);
        $actual[] = (float)$stmt->fetchColumn();

        // Mismo día del mes anterior
        $fechaAnt = date('Y-m-d', strtotime($fecha . ' -1 month'));
        $stmt->execute([':uid' => $uid, ':fecha' => $fechaAnt]);
        $anterior[] = (float)$stmt->fetchColumn();
    }

    echo json_encode([
        'success'  => true,
        'labels'   => $labels,
        'actual'   => $actual,
        'anterior' => $anterior,
    ]);
}

/**
 * Productos más y menos vendidos (últimos 30 días)
 */
function productosVentas(PDO $pdo, int $uid): void
{
    $stmt = $pdo->prepare(
        "SELECT p.tipo_producto, COUNT(t.id_transaccion) AS ventas
         FROM productos p
         LEFT JOIN transacciones t
               ON t.id_producto = p.id_producto
              AND t.estado = 'aprobado'
              AND t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         WHERE p.id_usuario = :uid
         GROUP BY p.id_producto, p.tipo_producto
         ORDER BY ventas DESC
         LIMIT 10"
    );
    $stmt->execute([':uid' => $uid]);
    $rows = $stmt->fetchAll();

    // Top 5 más vendidos y los 5 con menos ventas
    $labels = array_column($rows, 'tipo_producto');
    $ventas = array_column($rows, 'ventas');
    $max    = (int)($ventas[0] ?? 0);

    // "menos vendidos" es la inversa — mostrar como diferencia vs el máximo
    $menos = array_map(fn($v) => max(0, $max - (int)$v), $ventas);

    echo json_encode([
        'success' => true,
        'labels'  => $labels,
        'mas'     => array_map('intval', $ventas),
        'menos'   => $menos,
    ]);
}

/**
 * Distribución de ventas por categoría
 */
function categoriasVentas(PDO $pdo, int $uid): void
{
    $stmt = $pdo->prepare(
        "SELECT p.categoria_principal, COUNT(t.id_transaccion) AS ventas
         FROM transacciones t
         INNER JOIN productos p ON t.id_producto = p.id_producto
         WHERE p.id_usuario = :uid
           AND t.estado = 'aprobado'
           AND t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND p.categoria_principal IS NOT NULL
         GROUP BY p.categoria_principal
         ORDER BY ventas DESC
         LIMIT 8"
    );
    $stmt->execute([':uid' => $uid]);
    $rows = $stmt->fetchAll();

    $colores = [
        '#10b981',
        '#f59e0b',
        '#3b82f6',
        '#8b5cf6',
        '#ef4444',
        '#06b6d4',
        '#84cc16',
        '#f97316',
    ];

    $total  = array_sum(array_column($rows, 'ventas'));
    $labels = [];
    $values = [];
    $colors = [];

    foreach ($rows as $i => $row) {
        $labels[] = $row['categoria_principal'];
        $values[] = $total > 0 ? round(($row['ventas'] / $total) * 100) : 0;
        $colors[] = $colores[$i % count($colores)];
    }

    echo json_encode(['success' => true, 'labels' => $labels, 'values' => $values, 'colors' => $colors]);
}

/**
 * Precio vs cantidad vendida (scatter)
 */
function precioScatter(PDO $pdo, int $uid): void
{
    $stmt = $pdo->prepare(
        "SELECT
             p.tipo_producto,
             p.precio,
             COUNT(t.id_transaccion) AS ventas
         FROM productos p
         LEFT JOIN transacciones t
               ON t.id_producto = p.id_producto
              AND t.estado = 'aprobado'
         WHERE p.id_usuario = :uid
           AND p.estado = 'disponible'
         GROUP BY p.id_producto, p.tipo_producto, p.precio"
    );
    $stmt->execute([':uid' => $uid]);
    $rows = $stmt->fetchAll();

    $points = array_map(fn($r) => [
        'x'      => (float)$r['precio'],
        'y'      => (int)$r['ventas'],
        'nombre' => $r['tipo_producto'],
    ], $rows);

    echo json_encode(['success' => true, 'points' => $points]);
}

/**
 * Funnel de conversión
 */
function funnelConversion(PDO $pdo, int $uid): void
{
    // Visitas únicas a productos del vendedor (últimos 30 días)
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM visitas_producto vp
         INNER JOIN productos p ON vp.id_producto = p.id_producto
         WHERE p.id_usuario = :uid
           AND vp.fecha_visita >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $stmt->execute([':uid' => $uid]);
    $visitas = (int)$stmt->fetchColumn();

    // Mensajes enviados como proxy de contactos
    // mensajes no tiene id_producto — se relaciona via conversaciones
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT m.id_mensaje)
         FROM mensajes m
         INNER JOIN conversaciones c ON m.id_conversacion = c.id_conversacion
         INNER JOIN productos p      ON c.id_producto     = p.id_producto
         WHERE p.id_usuario = :uid
           AND m.fecha_envio >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $stmt->execute([':uid' => $uid]);
    $contactos = (int)$stmt->fetchColumn();

    // Ventas completadas
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM transacciones t
         INNER JOIN productos p ON t.id_producto = p.id_producto
         WHERE p.id_usuario = :uid
           AND t.estado = 'aprobado'
           AND t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $stmt->execute([':uid' => $uid]);
    $ventas = (int)$stmt->fetchColumn();

    echo json_encode([
        'success'   => true,
        'visitas'   => $visitas,
        'contactos' => $contactos,
        'ventas'    => $ventas,
    ]);
}

/**
 * Visitas al perfil — últimos 30 días
 */
function visitasPerfil(PDO $pdo, int $uid): void
{
    $labels = [];
    $values = [];

    for ($i = 29; $i >= 0; $i--) {
        $fecha = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = date('d/m', strtotime($fecha));

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM visitas_perfil
             WHERE id_vendedor = :uid AND DATE(fecha_visita) = :fecha"
        );
        $stmt->execute([':uid' => $uid, ':fecha' => $fecha]);
        $values[] = (int)$stmt->fetchColumn();
    }

    echo json_encode(['success' => true, 'labels' => $labels, 'values' => $values]);
}

/**
 * Evolución de calificación promedio por mes (últimos 6 meses)
 */
function evolucionCalificacion(PDO $pdo, int $uid): void
{
    $labels = [];
    $values = [];

    for ($i = 5; $i >= 0; $i--) {
        $mes   = date('Y-m', strtotime("-{$i} months"));
        $label = ucfirst(strftime('%b', strtotime($mes . '-01')));
        $labels[] = $label;

        $stmt = $pdo->prepare(
            "SELECT ROUND(AVG(calificacion), 1)
             FROM resenas_vendedor
             WHERE id_vendedor = :uid
               AND DATE_FORMAT(fecha_resena, '%Y-%m') = :mes"
        );
        $stmt->execute([':uid' => $uid, ':mes' => $mes]);
        $prom     = $stmt->fetchColumn();
        $values[] = $prom ? (float)$prom : null;
    }

    echo json_encode(['success' => true, 'labels' => $labels, 'values' => $values]);
}

/**
 * Compras por categoría (comprador)
 */
function comprasCategorias(PDO $pdo, int $uid): void
{
    $stmt = $pdo->prepare(
        "SELECT p.categoria_principal, COUNT(t.id_transaccion) AS compras
         FROM transacciones t
         INNER JOIN productos p ON t.id_producto = p.id_producto
         WHERE t.id_comprador = :uid
           AND t.estado = 'aprobado'
           AND p.categoria_principal IS NOT NULL
         GROUP BY p.categoria_principal
         ORDER BY compras DESC
         LIMIT 8"
    );
    $stmt->execute([':uid' => $uid]);
    $rows = $stmt->fetchAll();

    $colores = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#84cc16', '#f97316'];
    $total   = array_sum(array_column($rows, 'compras'));

    $labels = [];
    $values = [];
    $colors = [];
    foreach ($rows as $i => $row) {
        $labels[] = $row['categoria_principal'];
        $values[] = $total > 0 ? round(($row['compras'] / $total) * 100) : 0;
        $colors[] = $colores[$i % count($colores)];
    }

    echo json_encode(['success' => true, 'labels' => $labels, 'values' => $values, 'colors' => $colors]);
}

/**
 * Gasto mensual últimos 6 meses (comprador)
 */
function gastoMensual(PDO $pdo, int $uid): void
{
    $labels = [];
    $values = [];

    for ($i = 5; $i >= 0; $i--) {
        $mes   = date('Y-m', strtotime("-{$i} months"));
        $label = ucfirst(strftime('%b', strtotime($mes . '-01')));
        $labels[] = $label;

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total), 0)
             FROM transacciones
             WHERE id_comprador = :uid
               AND estado = 'aprobado'
               AND DATE_FORMAT(fecha_creacion, '%Y-%m') = :mes"
        );
        $stmt->execute([':uid' => $uid, ':mes' => $mes]);
        $values[] = (float)$stmt->fetchColumn();
    }

    echo json_encode(['success' => true, 'labels' => $labels, 'values' => $values]);
}

/**
 * Crear denuncia
 */
function crearDenuncia(PDO $pdo, int $uid): void
{
    $tipo        = trim($_POST['tipo_denuncia'] ?? '');
    $categoria   = trim($_POST['categoria']     ?? '');
    $descripcion = trim($_POST['descripcion']   ?? '');

    $tipos_validos = ['producto', 'vendedor', 'resena'];
    $cats_validas  = [
        'no_entregado',
        'descripcion_enganosa',
        'precio_diferente',
        'mala_calidad',
        'vendedor_no_responde',
        'resena_falsa',
        'lenguaje_inapropiado',
        'otro',
    ];

    if (!in_array($tipo, $tipos_validos, true) || !in_array($categoria, $cats_validas, true)) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        return;
    }

    if (empty($descripcion)) {
        echo json_encode(['success' => false, 'message' => 'La descripción es obligatoria']);
        return;
    }

    try {
        $pdo->prepare(
            "INSERT INTO reportes_denuncias
                (id_denunciante, tipo_denuncia, categoria, descripcion, estado, prioridad)
             VALUES
                (:uid, :tipo, :cat, :desc, 'recibida', 'media')"
        )->execute([
            ':uid'  => $uid,
            ':tipo' => $tipo,
            ':cat'  => $categoria,
            ':desc' => strip_tags($descripcion),
        ]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('ASCC crear_denuncia: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al guardar la denuncia']);
    }
}

/**
 * Generar token API para Power BI
 * Si ya tiene token activo lo devuelve — nunca genera uno nuevo si ya existe
 */
function generarToken(PDO $pdo, int $uid): void
{
    // Siempre buscar primero si ya tiene token activo
    $stmt = $pdo->prepare(
        "SELECT token FROM api_tokens WHERE id_usuario = :uid AND activo = 1 LIMIT 1"
    );
    $stmt->execute([':uid' => $uid]);
    $existente = $stmt->fetchColumn();

    if ($existente) {
        // Ya tiene token — devolver el mismo siempre
        $url = 'http://localhost/ascc/api/reportes_data.php?token=' . $existente;
        echo json_encode(['success' => true, 'token' => $existente, 'url' => $url]);
        return;
    }

    // No tiene token — crear uno nuevo
    $token = bin2hex(random_bytes(32));
    try {
        $pdo->prepare(
            "INSERT INTO api_tokens (id_usuario, token, activo)
             VALUES (:uid, :token, 1)"
        )->execute([':uid' => $uid, ':token' => $token]);

        $url = 'http://localhost/ascc/api/reportes_data.php?token=' . $token;
        echo json_encode(['success' => true, 'token' => $token, 'url' => $url]);
    } catch (PDOException $e) {
        error_log('ASCC generar_token: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al generar token']);
    }
}

/**
 * Exportar a Excel profesional usando HTML tables
 * Excel abre este formato perfectamente con columnas, colores y formato visual
 * Secciones seleccionables via GET: ?secciones=ventas,productos,visitas,valoraciones
 */
function exportarExcel(PDO $pdo, int $uid, string $rol): void
{
    // Secciones solicitadas — si no se especifica ninguna exportar todas
    $secciones_param = $_GET['secciones'] ?? 'ventas,productos,visitas,valoraciones';
    $secciones = array_map('trim', explode(',', $secciones_param));

    $nombre_archivo = 'ASCC_Reporte_' . date('Y-m-d') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');

    // ── Colores y estilos ────────────────────────────────────
    $c_verde       = '#065f46';
    $c_verde_claro = '#d1fae5';
    $c_verde_mid   = '#10b981';
    $c_fila_alt    = '#f0fdf4';
    $c_borde       = '#d1d5db';
    $c_texto       = '#111827';
    $c_subtitulo   = '#6b7280';

    $st_th = "background:{$c_verde};color:#fff;font-weight:bold;font-size:11px;
              padding:10px 14px;border:1px solid #047857;text-align:left;white-space:nowrap;";
    $st_td = "padding:9px 14px;border:1px solid {$c_borde};font-size:11px;color:{$c_texto};vertical-align:middle;";
    $st_td_alt = "padding:9px 14px;border:1px solid {$c_borde};font-size:11px;
                  color:{$c_texto};background:{$c_fila_alt};vertical-align:middle;";
    $st_seccion = "background:{$c_verde};color:#fff;font-size:14px;font-weight:bold;
                   padding:14px 18px;letter-spacing:0.5px;";
    $st_meta = "background:#f8fafc;color:{$c_subtitulo};font-size:10px;
                padding:6px 14px;border-bottom:2px solid {$c_verde_mid};";

    function badge(string $estado): string
    {
        $e = strtoupper($estado);
        if ($e === 'APROBADO')  return "<span style='background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:4px;font-weight:bold;font-size:10px;'>✓ Aprobado</span>";
        if ($e === 'PENDIENTE') return "<span style='background:#fef9c3;color:#713f12;padding:3px 10px;border-radius:4px;font-weight:bold;font-size:10px;'>⏳ Pendiente</span>";
        if ($e === 'RECHAZADO') return "<span style='background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:4px;font-weight:bold;font-size:10px;'>✗ Rechazado</span>";
        return "<span style='background:#f1f5f9;color:#475569;padding:3px 10px;border-radius:4px;font-size:10px;'>{$estado}</span>";
    }

    // ── Obtener nombre del usuario ───────────────────────────
    $stmtU = $pdo->prepare("SELECT nombre, rol FROM usuarios WHERE id_usuario = :uid LIMIT 1");
    $stmtU->execute([':uid' => $uid]);
    $user = $stmtU->fetch();
    $nombre_usuario = $user['nombre'] ?? 'Usuario';
    $rol_usuario    = $user['rol']    ?? $rol;

    echo '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body  { font-family: Calibri, Arial, sans-serif; margin: 20px; }
  table { border-collapse: collapse; width: 100%; margin-bottom: 28px; }
  h1    { color: ' . $c_verde . '; font-size: 20px; margin: 0 0 4px; }
  .sub  { color: ' . $c_subtitulo . '; font-size: 12px; margin-bottom: 20px; }
  .sep  { height: 20px; }
</style>
</head>
<body>';

    // ── Portada ──────────────────────────────────────────────
    echo '<h1>ASCC</h1>';
    echo '<p style="font-size:13px;color:#065f46;font-weight:600;margin:-10px 0 4px;">Aromas y Sabores de mi Campo Colombiano</p>';
    echo '<p class="sub">Reporte generado el ' . date('d/m/Y H:i') . ' · Usuario: ' . htmlspecialchars($nombre_usuario) . ' · Rol: ' . ucfirst($rol_usuario) . '</p>';

    // ════════════════════════════════════════════════════════
    // SECCIÓN 1: VENTAS / COMPRAS
    // ════════════════════════════════════════════════════════
    if (in_array('ventas', $secciones)) {

        if (in_array($rol, ['vendedor', 'mixto'], true)) {
            $stmt = $pdo->prepare(
                "SELECT
                     DATE_FORMAT(t.fecha_creacion,'%d/%m/%Y') AS fecha,
                     p.tipo_producto,
                     p.categoria_principal,
                     uc.nombre AS comprador,
                     t.cantidad,
                     p.unidad,
                     t.precio_unitario,
                     t.total,
                     t.metodo_pago,
                     t.estado,
                     t.referencia
                 FROM transacciones t
                 INNER JOIN productos p  ON t.id_producto  = p.id_producto
                 INNER JOIN usuarios  uc ON t.id_comprador = uc.id_usuario
                 WHERE p.id_usuario = :uid
                 ORDER BY t.fecha_creacion DESC
                 LIMIT 2000"
            );
            $stmt->execute([':uid' => $uid]);
            $ventas = $stmt->fetchAll();

            echo '<table>';
            echo '<tr><td colspan="11" style="' . $st_seccion . '">💰 MIS VENTAS</td></tr>';
            echo '<tr><td colspan="11" style="' . $st_meta . '">Total registros: ' . count($ventas) . ' · Período: Todo el historial</td></tr>';
            echo '<tr>
                    <th style="' . $st_th . '">Fecha</th>
                    <th style="' . $st_th . '">Producto</th>
                    <th style="' . $st_th . '">Categoría</th>
                    <th style="' . $st_th . '">Comprador</th>
                    <th style="' . $st_th . '">Cantidad</th>
                    <th style="' . $st_th . '">Unidad</th>
                    <th style="' . $st_th . '">Precio Unit.</th>
                    <th style="' . $st_th . '">Total COP</th>
                    <th style="' . $st_th . '">Método Pago</th>
                    <th style="' . $st_th . '">Estado</th>
                    <th style="' . $st_th . '">Referencia</th>
                  </tr>';

            if (empty($ventas)) {
                echo '<tr><td colspan="11" style="' . $st_td . 'text-align:center;color:#6b7280;font-style:italic;">
                        Sin ventas registradas aún. Los datos aparecerán cuando se realicen transacciones.
                      </td></tr>';
            } else {
                foreach ($ventas as $i => $v) {
                    $st = $i % 2 === 0 ? $st_td : $st_td_alt;
                    echo '<tr>
                        <td style="' . $st . '">' . $v['fecha'] . '</td>
                        <td style="' . $st . 'font-weight:600;">' . htmlspecialchars($v['tipo_producto']) . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($v['categoria_principal'] ?? '—') . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($v['comprador']) . '</td>
                        <td style="' . $st . 'text-align:center;">' . $v['cantidad'] . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($v['unidad']) . '</td>
                        <td style="' . $st . 'text-align:right;">$' . number_format($v['precio_unitario'], 0, ',', '.') . '</td>
                        <td style="' . $st . 'text-align:right;font-weight:bold;color:#065f46;">$' . number_format($v['total'], 0, ',', '.') . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($v['metodo_pago'] ?? '—') . '</td>
                        <td style="' . $st . '">' . badge($v['estado']) . '</td>
                        <td style="' . $st . 'font-family:monospace;font-size:10px;">' . htmlspecialchars($v['referencia']) . '</td>
                    </tr>';
                }
            }
            echo '</table>';
        }

        if (in_array($rol, ['comprador', 'mixto'], true)) {
            $stmt = $pdo->prepare(
                "SELECT
                     DATE_FORMAT(t.fecha_creacion,'%d/%m/%Y') AS fecha,
                     p.tipo_producto,
                     p.categoria_principal,
                     uv.nombre AS vendedor,
                     t.cantidad,
                     p.unidad,
                     t.precio_unitario,
                     t.total,
                     t.metodo_pago,
                     t.estado,
                     t.referencia
                 FROM transacciones t
                 INNER JOIN productos p  ON t.id_producto = p.id_producto
                 INNER JOIN usuarios  uv ON p.id_usuario  = uv.id_usuario
                 WHERE t.id_comprador = :uid
                 ORDER BY t.fecha_creacion DESC
                 LIMIT 2000"
            );
            $stmt->execute([':uid' => $uid]);
            $compras = $stmt->fetchAll();

            echo '<table>';
            echo '<tr><td colspan="11" style="' . $st_seccion . '">🛒 MIS COMPRAS</td></tr>';
            echo '<tr><td colspan="11" style="' . $st_meta . '">Total registros: ' . count($compras) . ' · Período: Todo el historial</td></tr>';
            echo '<tr>
                    <th style="' . $st_th . '">Fecha</th>
                    <th style="' . $st_th . '">Producto</th>
                    <th style="' . $st_th . '">Categoría</th>
                    <th style="' . $st_th . '">Vendedor</th>
                    <th style="' . $st_th . '">Cantidad</th>
                    <th style="' . $st_th . '">Unidad</th>
                    <th style="' . $st_th . '">Precio Unit.</th>
                    <th style="' . $st_th . '">Total COP</th>
                    <th style="' . $st_th . '">Método Pago</th>
                    <th style="' . $st_th . '">Estado</th>
                    <th style="' . $st_th . '">Referencia</th>
                  </tr>';

            if (empty($compras)) {
                echo '<tr><td colspan="11" style="' . $st_td . 'text-align:center;color:#6b7280;font-style:italic;">
                        Sin compras registradas aún.
                      </td></tr>';
            } else {
                foreach ($compras as $i => $c) {
                    $st = $i % 2 === 0 ? $st_td : $st_td_alt;
                    echo '<tr>
                        <td style="' . $st . '">' . $c['fecha'] . '</td>
                        <td style="' . $st . 'font-weight:600;">' . htmlspecialchars($c['tipo_producto']) . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($c['categoria_principal'] ?? '—') . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($c['vendedor']) . '</td>
                        <td style="' . $st . 'text-align:center;">' . $c['cantidad'] . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($c['unidad']) . '</td>
                        <td style="' . $st . 'text-align:right;">$' . number_format($c['precio_unitario'], 0, ',', '.') . '</td>
                        <td style="' . $st . 'text-align:right;font-weight:bold;color:#065f46;">$' . number_format($c['total'], 0, ',', '.') . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($c['metodo_pago'] ?? '—') . '</td>
                        <td style="' . $st . '">' . badge($c['estado']) . '</td>
                        <td style="' . $st . 'font-family:monospace;font-size:10px;">' . htmlspecialchars($c['referencia']) . '</td>
                    </tr>';
                }
            }
            echo '</table>';
        }
    }

    // ════════════════════════════════════════════════════════
    // SECCIÓN 2: PRODUCTOS
    // ════════════════════════════════════════════════════════
    if (in_array('productos', $secciones) && in_array($rol, ['vendedor', 'mixto'], true)) {
        $stmt = $pdo->prepare(
            "SELECT
                 p.codigo_producto,
                 p.tipo_producto,
                 p.categoria_principal,
                 p.precio,
                 p.cantidad,
                 p.unidad,
                 p.estado,
                 DATE_FORMAT(p.fecha_publicacion,'%d/%m/%Y') AS publicado,
                 DATE_FORMAT(p.fecha_venta,'%d/%m/%Y')       AS vendido_en,
                 u.municipio,
                 u.departamento
             FROM productos p
             INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
             WHERE p.id_usuario = :uid
             ORDER BY p.fecha_publicacion DESC"
        );
        $stmt->execute([':uid' => $uid]);
        $productos = $stmt->fetchAll();

        $total_activos  = count(array_filter($productos, fn($p) => $p['estado'] === 'disponible'));
        $total_vendidos = count(array_filter($productos, fn($p) => $p['estado'] === 'vendido'));
        $valor_inv = array_sum(array_map(fn($p) => $p['precio'] * $p['cantidad'], array_filter($productos, fn($p) => $p['estado'] === 'disponible')));

        echo '<table>';
        echo '<tr><td colspan="11" style="' . $st_seccion . '">📦 MIS PRODUCTOS</td></tr>';
        echo '<tr><td colspan="11" style="' . $st_meta . '">
                Activos: ' . $total_activos . ' · Vendidos: ' . $total_vendidos . ' · 
                Valor inventario: $' . number_format($valor_inv, 0, ',', '.') . ' COP
              </td></tr>';
        echo '<tr>
                <th style="' . $st_th . '">Código</th>
                <th style="' . $st_th . '">Producto</th>
                <th style="' . $st_th . '">Categoría</th>
                <th style="' . $st_th . '">Precio COP</th>
                <th style="' . $st_th . '">Cantidad</th>
                <th style="' . $st_th . '">Unidad</th>
                <th style="' . $st_th . '">Valor Total</th>
                <th style="' . $st_th . '">Estado</th>
                <th style="' . $st_th . '">Publicado</th>
                <th style="' . $st_th . '">Vendido</th>
                <th style="' . $st_th . '">Ubicación</th>
              </tr>';

        foreach ($productos as $i => $p) {
            $st = $i % 2 === 0 ? $st_td : $st_td_alt;
            $valor_total = $p['precio'] * $p['cantidad'];
            $est_badge = $p['estado'] === 'disponible'
                ? "<span style='background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:4px;font-weight:bold;font-size:10px;'>✓ Disponible</span>"
                : "<span style='background:#f1f5f9;color:#475569;padding:3px 10px;border-radius:4px;font-size:10px;'>Vendido</span>";

            echo '<tr>
                <td style="' . $st . 'font-family:monospace;font-size:10px;">' . htmlspecialchars($p['codigo_producto'] ?? '—') . '</td>
                <td style="' . $st . 'font-weight:600;">' . htmlspecialchars($p['tipo_producto']) . '</td>
                <td style="' . $st . '">' . htmlspecialchars($p['categoria_principal'] ?? '—') . '</td>
                <td style="' . $st . 'text-align:right;">$' . number_format($p['precio'], 0, ',', '.') . '</td>
                <td style="' . $st . 'text-align:center;">' . $p['cantidad'] . '</td>
                <td style="' . $st . '">' . htmlspecialchars($p['unidad']) . '</td>
                <td style="' . $st . 'text-align:right;font-weight:bold;">$' . number_format($valor_total, 0, ',', '.') . '</td>
                <td style="' . $st . '">' . $est_badge . '</td>
                <td style="' . $st . '">' . $p['publicado'] . '</td>
                <td style="' . $st . '">' . ($p['vendido_en'] ?? '—') . '</td>
                <td style="' . $st . '">' . htmlspecialchars($p['municipio'] . ', ' . $p['departamento']) . '</td>
            </tr>';
        }
        echo '</table>';
    }

    // ════════════════════════════════════════════════════════
    // SECCIÓN 3: VISITAS
    // ════════════════════════════════════════════════════════
    if (in_array('visitas', $secciones) && in_array($rol, ['vendedor', 'mixto'], true)) {

        // Visitas por producto
        $stmt = $pdo->prepare(
            "SELECT
                 p.tipo_producto,
                 COUNT(*)                     AS total_visitas,
                 COUNT(DISTINCT vp.sesion_id) AS visitantes_unicos,
                 MAX(DATE_FORMAT(vp.fecha_visita,'%d/%m/%Y %H:%i')) AS ultima_visita
             FROM visitas_producto vp
             INNER JOIN productos p ON vp.id_producto = p.id_producto
             WHERE p.id_usuario = :uid
             GROUP BY vp.id_producto, p.tipo_producto
             ORDER BY total_visitas DESC"
        );
        $stmt->execute([':uid' => $uid]);
        $vis_productos = $stmt->fetchAll();

        // Visitas al perfil
        $stmt = $pdo->prepare(
            "SELECT
                 COUNT(*)                     AS total_visitas,
                 COUNT(DISTINCT sesion_id)    AS visitantes_unicos,
                 COUNT(DISTINCT id_visitante) AS usuarios_logueados,
                 MAX(DATE_FORMAT(fecha_visita,'%d/%m/%Y %H:%i')) AS ultima_visita
             FROM visitas_perfil
             WHERE id_vendedor = :uid"
        );
        $stmt->execute([':uid' => $uid]);
        $vis_perfil = $stmt->fetch();

        echo '<table>';
        echo '<tr><td colspan="4" style="' . $st_seccion . '">👁️ VISITAS A MIS PRODUCTOS</td></tr>';
        echo '<tr><td colspan="4" style="' . $st_meta . '">Últimos 30 días · Productos con al menos 1 visita</td></tr>';
        echo '<tr>
                <th style="' . $st_th . '">Producto</th>
                <th style="' . $st_th . '">Total Visitas</th>
                <th style="' . $st_th . '">Visitantes Únicos</th>
                <th style="' . $st_th . '">Última Visita</th>
              </tr>';

        if (empty($vis_productos)) {
            echo '<tr><td colspan="4" style="' . $st_td . 'text-align:center;color:#6b7280;font-style:italic;">Sin visitas registradas aún.</td></tr>';
        } else {
            foreach ($vis_productos as $i => $v) {
                $st = $i % 2 === 0 ? $st_td : $st_td_alt;
                echo '<tr>
                    <td style="' . $st . 'font-weight:600;">' . htmlspecialchars($v['tipo_producto']) . '</td>
                    <td style="' . $st . 'text-align:center;font-weight:bold;color:#065f46;">' . $v['total_visitas'] . '</td>
                    <td style="' . $st . 'text-align:center;">' . $v['visitantes_unicos'] . '</td>
                    <td style="' . $st . '">' . $v['ultima_visita'] . '</td>
                </tr>';
            }
        }
        echo '</table>';

        // Resumen visitas al perfil
        echo '<table>';
        echo '<tr><td colspan="4" style="' . $st_seccion . '">👤 VISITAS A MI PERFIL</td></tr>';
        echo '<tr>
                <th style="' . $st_th . '">Total Visitas</th>
                <th style="' . $st_th . '">Visitantes Únicos</th>
                <th style="' . $st_th . '">Usuarios Logueados</th>
                <th style="' . $st_th . '">Última Visita</th>
              </tr>';
        echo '<tr>
                <td style="' . $st_td . 'text-align:center;font-weight:bold;color:#065f46;font-size:16px;">' . ($vis_perfil['total_visitas'] ?? 0) . '</td>
                <td style="' . $st_td . 'text-align:center;">' . ($vis_perfil['visitantes_unicos'] ?? 0) . '</td>
                <td style="' . $st_td . 'text-align:center;">' . ($vis_perfil['usuarios_logueados'] ?? 0) . '</td>
                <td style="' . $st_td . '">' . ($vis_perfil['ultima_visita'] ?? 'Sin visitas') . '</td>
              </tr>';
        echo '</table>';
    }

    // ════════════════════════════════════════════════════════
    // SECCIÓN 4: VALORACIONES
    // ════════════════════════════════════════════════════════
    if (in_array('valoraciones', $secciones)) {

        if (in_array($rol, ['vendedor', 'mixto'], true)) {
            $stmt = $pdo->prepare(
                "SELECT
                     rv.calificacion,
                     rv.titulo,
                     rv.comentario,
                     DATE_FORMAT(rv.fecha_resena,'%d/%m/%Y') AS fecha,
                     uc.nombre AS comprador
                 FROM resenas_vendedor rv
                 INNER JOIN usuarios uc ON rv.id_comprador = uc.id_usuario
                 WHERE rv.id_vendedor = :uid
                 ORDER BY rv.fecha_resena DESC"
            );
            $stmt->execute([':uid' => $uid]);
            $resenas_v = $stmt->fetchAll();

            $promedio = count($resenas_v) > 0
                ? round(array_sum(array_column($resenas_v, 'calificacion')) / count($resenas_v), 1)
                : 0;

            echo '<table>';
            echo '<tr><td colspan="5" style="' . $st_seccion . '">⭐ VALORACIONES RECIBIDAS</td></tr>';
            echo '<tr><td colspan="5" style="' . $st_meta . '">Total: ' . count($resenas_v) . ' reseñas · Promedio: ' . $promedio . '/5</td></tr>';
            echo '<tr>
                    <th style="' . $st_th . '">Calificación</th>
                    <th style="' . $st_th . '">Comprador</th>
                    <th style="' . $st_th . '">Título</th>
                    <th style="' . $st_th . '">Comentario</th>
                    <th style="' . $st_th . '">Fecha</th>
                  </tr>';

            if (empty($resenas_v)) {
                echo '<tr><td colspan="5" style="' . $st_td . 'text-align:center;color:#6b7280;font-style:italic;">Sin valoraciones aún.</td></tr>';
            } else {
                foreach ($resenas_v as $i => $r) {
                    $st  = $i % 2 === 0 ? $st_td : $st_td_alt;
                    $estrellas = str_repeat('★', (int)$r['calificacion']) . str_repeat('☆', 5 - (int)$r['calificacion']);
                    $color_cal = $r['calificacion'] >= 4 ? '#065f46' : ($r['calificacion'] >= 3 ? '#713f12' : '#991b1b');
                    echo '<tr>
                        <td style="' . $st . 'text-align:center;font-size:14px;color:' . $color_cal . ';">' . $estrellas . ' ' . $r['calificacion'] . '/5</td>
                        <td style="' . $st . 'font-weight:600;">' . htmlspecialchars($r['comprador']) . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($r['titulo'] ?? '—') . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($r['comentario'] ?? '—') . '</td>
                        <td style="' . $st . '">' . $r['fecha'] . '</td>
                    </tr>';
                }
            }
            echo '</table>';
        }

        // Reseñas dadas como comprador
        if (in_array($rol, ['comprador', 'mixto'], true)) {
            $stmt = $pdo->prepare(
                "SELECT
                     rv.calificacion,
                     rv.titulo,
                     rv.comentario,
                     DATE_FORMAT(rv.fecha_resena,'%d/%m/%Y') AS fecha,
                     uv.nombre AS vendedor
                 FROM resenas_vendedor rv
                 INNER JOIN usuarios uv ON rv.id_vendedor = uv.id_usuario
                 WHERE rv.id_comprador = :uid
                 ORDER BY rv.fecha_resena DESC"
            );
            $stmt->execute([':uid' => $uid]);
            $resenas_c = $stmt->fetchAll();

            echo '<table>';
            echo '<tr><td colspan="5" style="' . $st_seccion . '">✍️ VALORACIONES QUE HE DADO</td></tr>';
            echo '<tr><td colspan="5" style="' . $st_meta . '">Total: ' . count($resenas_c) . ' reseñas dadas</td></tr>';
            echo '<tr>
                    <th style="' . $st_th . '">Calificación</th>
                    <th style="' . $st_th . '">Vendedor</th>
                    <th style="' . $st_th . '">Título</th>
                    <th style="' . $st_th . '">Comentario</th>
                    <th style="' . $st_th . '">Fecha</th>
                  </tr>';

            if (empty($resenas_c)) {
                echo '<tr><td colspan="5" style="' . $st_td . 'text-align:center;color:#6b7280;font-style:italic;">No has dado valoraciones aún.</td></tr>';
            } else {
                foreach ($resenas_c as $i => $r) {
                    $st = $i % 2 === 0 ? $st_td : $st_td_alt;
                    $estrellas = str_repeat('★', (int)$r['calificacion']) . str_repeat('☆', 5 - (int)$r['calificacion']);
                    $color_cal = $r['calificacion'] >= 4 ? '#065f46' : ($r['calificacion'] >= 3 ? '#713f12' : '#991b1b');
                    echo '<tr>
                        <td style="' . $st . 'text-align:center;font-size:14px;color:' . $color_cal . ';">' . $estrellas . ' ' . $r['calificacion'] . '/5</td>
                        <td style="' . $st . 'font-weight:600;">' . htmlspecialchars($r['vendedor']) . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($r['titulo'] ?? '—') . '</td>
                        <td style="' . $st . '">' . htmlspecialchars($r['comentario'] ?? '—') . '</td>
                        <td style="' . $st . '">' . $r['fecha'] . '</td>
                    </tr>';
                }
            }
            echo '</table>';
        }
    }

    echo '</body></html>';
}

/**
 * Exportar CSV limpio para Power BI
 * Columnas en inglés sin tildes — Power BI las importa sin transformaciones
 * Secciones seleccionables via GET: ?secciones=ventas,visitas,valoraciones
 */
function exportarCsv(PDO $pdo, int $uid, string $rol): void
{
    $secciones_param = $_GET['secciones'] ?? 'ventas,visitas,valoraciones';
    $secciones = array_map('trim', explode(',', $secciones_param));

    $nombre_archivo = 'ASCC_PowerBI_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    // BOM para compatibilidad UTF-8 en Excel
    fputs($out, "\xEF\xBB\xBF");

    // ── Ventas/Compras ───────────────────────────────────────
    if (in_array('ventas', $secciones)) {
        fputcsv($out, ['## SALES / PURCHASES']);
        fputcsv($out, [
            'date',
            'product_name',
            'category',
            'quantity',
            'unit',
            'unit_price',
            'total',
            'payment_method',
            'status',
            'reference',
            'buyer',
            'seller'
        ]);

        $stmt = $pdo->prepare(
            "SELECT
                 DATE(t.fecha_creacion)     AS date,
                 p.tipo_producto            AS product_name,
                 p.categoria_principal      AS category,
                 t.cantidad                 AS quantity,
                 p.unidad                   AS unit,
                 t.precio_unitario          AS unit_price,
                 t.total                    AS total,
                 t.metodo_pago              AS payment_method,
                 t.estado                   AS status,
                 t.referencia               AS reference,
                 uc.nombre                  AS buyer,
                 uv.nombre                  AS seller
             FROM transacciones t
             INNER JOIN productos p  ON t.id_producto  = p.id_producto
             INNER JOIN usuarios  uc ON t.id_comprador = uc.id_usuario
             INNER JOIN usuarios  uv ON p.id_usuario   = uv.id_usuario
             WHERE (p.id_usuario = :uid OR t.id_comprador = :uid2)
             ORDER BY t.fecha_creacion DESC
             LIMIT 5000"
        );
        $stmt->execute([':uid' => $uid, ':uid2' => $uid]);
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($out, $row);
        }
        fputcsv($out, []); // línea vacía separadora
    }

    // ── Visitas ──────────────────────────────────────────────
    if (in_array('visitas', $secciones) && in_array($rol, ['vendedor', 'mixto'], true)) {
        fputcsv($out, ['## PRODUCT VISITS']);
        fputcsv($out, ['product_name', 'total_visits', 'unique_visitors', 'last_visit']);

        $stmt = $pdo->prepare(
            "SELECT
                 p.tipo_producto,
                 COUNT(*)                     AS total_visits,
                 COUNT(DISTINCT vp.sesion_id) AS unique_visitors,
                 MAX(vp.fecha_visita)         AS last_visit
             FROM visitas_producto vp
             INNER JOIN productos p ON vp.id_producto = p.id_producto
             WHERE p.id_usuario = :uid
             GROUP BY vp.id_producto, p.tipo_producto
             ORDER BY total_visits DESC"
        );
        $stmt->execute([':uid' => $uid]);
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($out, $row);
        }
        fputcsv($out, []);

        fputcsv($out, ['## PROFILE VISITS']);
        fputcsv($out, ['date', 'total_visits', 'unique_visitors']);
        $stmt = $pdo->prepare(
            "SELECT DATE(fecha_visita), COUNT(*), COUNT(DISTINCT sesion_id)
             FROM visitas_perfil
             WHERE id_vendedor = :uid
             GROUP BY DATE(fecha_visita)
             ORDER BY fecha_visita DESC
             LIMIT 90"
        );
        $stmt->execute([':uid' => $uid]);
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($out, $row);
        }
        fputcsv($out, []);
    }

    // ── Valoraciones ─────────────────────────────────────────
    if (in_array('valoraciones', $secciones)) {
        fputcsv($out, ['## RATINGS_RECEIVED']);
        fputcsv($out, ['date', 'rating', 'title', 'comment', 'reviewer']);

        if (in_array($rol, ['vendedor', 'mixto'], true)) {
            $stmt = $pdo->prepare(
                "SELECT DATE(rv.fecha_resena), rv.calificacion, rv.titulo,
                        rv.comentario, uc.nombre
                 FROM resenas_vendedor rv
                 INNER JOIN usuarios uc ON rv.id_comprador = uc.id_usuario
                 WHERE rv.id_vendedor = :uid
                 ORDER BY rv.fecha_resena DESC"
            );
            $stmt->execute([':uid' => $uid]);
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                fputcsv($out, $row);
            }
        }
        fputcsv($out, []);
    }

    fclose($out);
}

/**
 * Feed JSON completo para Power BI
 * Se activa cuando Power BI accede con ?token=XXX sin action
 * Devuelve todos los datos del usuario en formato JSON estructurado
 */
function powerBiFeed(PDO $pdo, int $uid, string $rol): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');

    $data = [
        'generated_at' => date('Y-m-d H:i:s'),
        'user_id'      => $uid,
        'role'         => $rol,
    ];

    // ── Ventas (vendedor/mixto) ───────────────────────────────
    if (in_array($rol, ['vendedor', 'mixto'], true)) {
        $stmt = $pdo->prepare(
            "SELECT
                 DATE(t.fecha_creacion)   AS date,
                 p.tipo_producto          AS product_name,
                 p.categoria_principal    AS category,
                 t.cantidad               AS quantity,
                 p.unidad                 AS unit,
                 t.precio_unitario        AS unit_price,
                 t.total                  AS total,
                 t.metodo_pago            AS payment_method,
                 t.estado                 AS status,
                 uc.nombre                AS buyer
             FROM transacciones t
             INNER JOIN productos p  ON t.id_producto  = p.id_producto
             INNER JOIN usuarios  uc ON t.id_comprador = uc.id_usuario
             WHERE p.id_usuario = :uid
             ORDER BY t.fecha_creacion DESC
             LIMIT 5000"
        );
        $stmt->execute([':uid' => $uid]);
        $data['sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Productos activos
        $stmt = $pdo->prepare(
            "SELECT
                 p.codigo_producto  AS code,
                 p.tipo_producto    AS product_name,
                 p.categoria_principal AS category,
                 p.precio           AS price,
                 p.cantidad         AS stock,
                 p.unidad           AS unit,
                 p.estado           AS status,
                 DATE(p.fecha_publicacion) AS published_date,
                 u.municipio,
                 u.departamento
             FROM productos p
             INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
             WHERE p.id_usuario = :uid
             ORDER BY p.fecha_publicacion DESC"
        );
        $stmt->execute([':uid' => $uid]);
        $data['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Visitas por producto (últimos 30 días)
        $stmt = $pdo->prepare(
            "SELECT
                 p.tipo_producto          AS product_name,
                 COUNT(*)                 AS total_visits,
                 COUNT(DISTINCT sesion_id) AS unique_visitors,
                 MAX(DATE(vp.fecha_visita)) AS last_visit_date
             FROM visitas_producto vp
             INNER JOIN productos p ON vp.id_producto = p.id_producto
             WHERE p.id_usuario = :uid
               AND vp.fecha_visita >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY vp.id_producto, p.tipo_producto
             ORDER BY total_visits DESC"
        );
        $stmt->execute([':uid' => $uid]);
        $data['product_visits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Visitas al perfil por día (últimos 30 días)
        $stmt = $pdo->prepare(
            "SELECT
                 DATE(fecha_visita)       AS visit_date,
                 COUNT(*)                 AS total_visits,
                 COUNT(DISTINCT sesion_id) AS unique_visitors
             FROM visitas_perfil
             WHERE id_vendedor = :uid
               AND fecha_visita >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(fecha_visita)
             ORDER BY visit_date DESC"
        );
        $stmt->execute([':uid' => $uid]);
        $data['profile_visits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Valoraciones recibidas
        $stmt = $pdo->prepare(
            "SELECT
                 rv.calificacion AS rating,
                 rv.titulo       AS title,
                 DATE(rv.fecha_resena) AS review_date,
                 uc.nombre       AS reviewer
             FROM resenas_vendedor rv
             INNER JOIN usuarios uc ON rv.id_comprador = uc.id_usuario
             WHERE rv.id_vendedor = :uid
             ORDER BY rv.fecha_resena DESC"
        );
        $stmt->execute([':uid' => $uid]);
        $data['ratings_received'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // KPIs resumen
        $stmt = $pdo->prepare(
            "SELECT
                 COUNT(DISTINCT t.id_transaccion) AS total_sales,
                 COALESCE(SUM(t.total), 0)        AS total_revenue,
                 COUNT(DISTINCT t.id_comprador)   AS unique_buyers,
                 ROUND(AVG(rv.calificacion), 1)   AS avg_rating
             FROM productos p
             LEFT JOIN transacciones t    ON t.id_producto  = p.id_producto AND t.estado = 'APROBADO'
             LEFT JOIN resenas_vendedor rv ON rv.id_vendedor = p.id_usuario
             WHERE p.id_usuario = :uid"
        );
        $stmt->execute([':uid' => $uid]);
        $data['kpis'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Compras (comprador/mixto) ─────────────────────────────
    if (in_array($rol, ['comprador', 'mixto'], true)) {
        $stmt = $pdo->prepare(
            "SELECT
                 DATE(t.fecha_creacion)   AS date,
                 p.tipo_producto          AS product_name,
                 p.categoria_principal    AS category,
                 t.cantidad               AS quantity,
                 t.precio_unitario        AS unit_price,
                 t.total                  AS total,
                 t.estado                 AS status,
                 uv.nombre                AS seller
             FROM transacciones t
             INNER JOIN productos p  ON t.id_producto = p.id_producto
             INNER JOIN usuarios  uv ON p.id_usuario  = uv.id_usuario
             WHERE t.id_comprador = :uid
             ORDER BY t.fecha_creacion DESC
             LIMIT 5000"
        );
        $stmt->execute([':uid' => $uid]);
        $data['purchases'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Tabla plana para Power BI — una tabla por llamada
 * Power BI la importa directamente sin configuración adicional
 * Uso: ?token=XXX&table=products|sales|product_visits|profile_visits|ratings_received|kpis
 */
function powerBiTable(PDO $pdo, int $uid, string $rol, string $table): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');

    $rows = [];

    switch ($table) {

        case 'products':
            $stmt = $pdo->prepare(
                "SELECT
                     p.codigo_producto  AS code,
                     p.tipo_producto    AS product_name,
                     p.categoria_principal AS category,
                     CAST(p.precio AS FLOAT)    AS price,
                     CAST(p.cantidad AS SIGNED) AS stock,
                     p.unidad           AS unit,
                     p.estado           AS status,
                     DATE(p.fecha_publicacion) AS published_date,
                     u.municipio,
                     u.departamento
                 FROM productos p
                 INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
                 WHERE p.id_usuario = :uid
                 ORDER BY p.fecha_publicacion DESC"
            );
            $stmt->execute([':uid' => $uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'sales':
            $stmt = $pdo->prepare(
                "SELECT
                     DATE(t.fecha_creacion)      AS date,
                     p.tipo_producto             AS product_name,
                     p.categoria_principal       AS category,
                     t.cantidad                  AS quantity,
                     p.unidad                    AS unit,
                     CAST(t.precio_unitario AS FLOAT) AS unit_price,
                     CAST(t.total AS FLOAT)      AS total,
                     t.metodo_pago               AS payment_method,
                     t.estado                    AS status,
                     uc.nombre                   AS buyer
                 FROM transacciones t
                 INNER JOIN productos p  ON t.id_producto  = p.id_producto
                 INNER JOIN usuarios  uc ON t.id_comprador = uc.id_usuario
                 WHERE p.id_usuario = :uid
                 ORDER BY t.fecha_creacion DESC
                 LIMIT 5000"
            );
            $stmt->execute([':uid' => $uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'purchases':
            $stmt = $pdo->prepare(
                "SELECT
                     DATE(t.fecha_creacion)      AS date,
                     p.tipo_producto             AS product_name,
                     p.categoria_principal       AS category,
                     t.cantidad                  AS quantity,
                     CAST(t.precio_unitario AS FLOAT) AS unit_price,
                     CAST(t.total AS FLOAT)      AS total,
                     t.estado                    AS status,
                     uv.nombre                   AS seller
                 FROM transacciones t
                 INNER JOIN productos p  ON t.id_producto = p.id_producto
                 INNER JOIN usuarios  uv ON p.id_usuario  = uv.id_usuario
                 WHERE t.id_comprador = :uid
                 ORDER BY t.fecha_creacion DESC
                 LIMIT 5000"
            );
            $stmt->execute([':uid' => $uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'product_visits':
            $stmt = $pdo->prepare(
                "SELECT
                     p.tipo_producto             AS product_name,
                     p.categoria_principal       AS category,
                     COUNT(*)                    AS total_visits,
                     COUNT(DISTINCT vp.sesion_id) AS unique_visitors,
                     MAX(DATE(vp.fecha_visita))  AS last_visit_date
                 FROM visitas_producto vp
                 INNER JOIN productos p ON vp.id_producto = p.id_producto
                 WHERE p.id_usuario = :uid
                 GROUP BY vp.id_producto, p.tipo_producto, p.categoria_principal
                 ORDER BY total_visits DESC"
            );
            $stmt->execute([':uid' => $uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'profile_visits':
            $stmt = $pdo->prepare(
                "SELECT
                     DATE(fecha_visita)          AS visit_date,
                     COUNT(*)                    AS total_visits,
                     COUNT(DISTINCT sesion_id)   AS unique_visitors
                 FROM visitas_perfil
                 WHERE id_vendedor = :uid
                 GROUP BY DATE(fecha_visita)
                 ORDER BY visit_date DESC
                 LIMIT 90"
            );
            $stmt->execute([':uid' => $uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'ratings_received':
            $stmt = $pdo->prepare(
                "SELECT
                     rv.calificacion             AS rating,
                     rv.titulo                   AS title,
                     rv.comentario               AS comment,
                     DATE(rv.fecha_resena)       AS review_date,
                     uc.nombre                   AS reviewer
                 FROM resenas_vendedor rv
                 INNER JOIN usuarios uc ON rv.id_comprador = uc.id_usuario
                 WHERE rv.id_vendedor = :uid
                 ORDER BY rv.fecha_resena DESC"
            );
            $stmt->execute([':uid' => $uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'kpis':
            $stmt = $pdo->prepare(
                "SELECT
                     COUNT(DISTINCT t.id_transaccion)  AS total_sales,
                     COALESCE(SUM(t.total), 0)         AS total_revenue,
                     COUNT(DISTINCT t.id_comprador)    AS unique_buyers,
                     ROUND(AVG(rv.calificacion), 1)    AS avg_rating,
                     (SELECT COUNT(*) FROM productos WHERE id_usuario = :uid2 AND estado = 'disponible') AS active_products,
                     (SELECT COUNT(*) FROM visitas_perfil WHERE id_vendedor = :uid3
                      AND fecha_visita >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS profile_visits_30d
                 FROM productos p
                 LEFT JOIN transacciones t     ON t.id_producto = p.id_producto AND t.estado = 'APROBADO'
                 LEFT JOIN resenas_vendedor rv  ON rv.id_vendedor = p.id_usuario
                 WHERE p.id_usuario = :uid"
            );
            $stmt->execute([':uid' => $uid, ':uid2' => $uid, ':uid3' => $uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $rows = $row ? [$row] : [];
            break;

        default:
            echo json_encode(['error' => 'Tabla no válida. Use: products, sales, purchases, product_visits, profile_visits, ratings_received, kpis']);
            return;
    }

    // Convertir manualmente los campos numéricos para que Power BI los reconozca
    // No usamos JSON_NUMERIC_CHECK porque convierte product_name a número si es solo dígitos
    $numeric_fields = [
        'price',
        'stock',
        'total',
        'unit_price',
        'quantity',
        'rating',
        'total_visits',
        'unique_visitors',
        'total_sales',
        'total_revenue',
        'unique_buyers',
        'avg_rating',
        'active_products',
        'profile_visits_30d'
    ];

    foreach ($rows as &$row) {
        foreach ($row as $key => &$val) {
            if (in_array($key, $numeric_fields) && $val !== null) {
                $val = is_float($val + 0) ? (float)$val : (int)$val;
            }
        }
    }
    unset($row, $val);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
