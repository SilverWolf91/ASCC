<?php

/**
 * ASCC — Admin Reportes API
 * Ruta: admin/ajax/reportes_admin.php
 *
 * Endpoints disponibles (POST):
 *   kpis_globales, ventas_diarias_global, categorias_global,
 *   usuarios_por_rol, actividad_por_hora,
 *   usuarios_tabla, toggle_usuario,
 *   productos_tabla,
 *   denuncias_tabla, denuncia_detalle, actualizar_denuncia,
 *   ranking_vendedores, ranking_compradores
 *
 * Endpoints GET (descarga):
 *   excel_admin, csv_admin
 */

session_start();

// ── Seguridad triple capa (igual que admin/dashboard.php) ──────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}
if (
    !isset($_SESSION['admin_token']) ||
    $_SESSION['admin_token'] !== hash('sha256', $_SESSION['user_id'] . 'ASCC_ADMIN_SECRET')
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de sesión inválido']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$accion = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Router ────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');

switch ($accion) {

    // ─────────────────────────────────────────────────────────────────────
    // KPIs GLOBALES
    // ─────────────────────────────────────────────────────────────────────
    case 'kpis_globales':
        try {
            $kpis = [];

            // Usuarios totales
            $kpis['usuarios_total'] = (int)$conexion->query(
                "SELECT COUNT(*) FROM usuarios WHERE rol != 'admin'"
            )->fetchColumn();

            // Nuevos hoy
            $kpis['usuarios_hoy'] = (int)$conexion->query(
                "SELECT COUNT(*) FROM usuarios WHERE DATE(fecha_registro) = CURDATE() AND rol != 'admin'"
            )->fetchColumn();

            // Productos activos
            $kpis['productos_activos'] = (int)$conexion->query(
                "SELECT COUNT(*) FROM productos WHERE estado = 'disponible'"
            )->fetchColumn();

            // Productos publicados hoy
            $kpis['productos_hoy'] = (int)$conexion->query(
                "SELECT COUNT(*) FROM productos WHERE DATE(fecha_publicacion) = CURDATE()"
            )->fetchColumn();

            // Ventas del mes (APROBADO)
            $kpis['ventas_mes'] = (float)($conexion->query(
                "SELECT COALESCE(SUM(total),0) FROM transacciones
                 WHERE estado = 'APROBADO'
                   AND MONTH(fecha_creacion) = MONTH(NOW())
                   AND YEAR(fecha_creacion)  = YEAR(NOW())"
            )->fetchColumn() ?? 0);

            // Denuncias abiertas (no cerradas ni resueltas)
            $kpis['denuncias_abiertas'] = (int)$conexion->query(
                "SELECT COUNT(*) FROM reportes_denuncias
                 WHERE estado NOT IN ('resuelta','cerrada')"
            )->fetchColumn();

            // Visitas hoy (productos + perfil)
            $vp = (int)$conexion->query(
                "SELECT COUNT(*) FROM visitas_producto WHERE DATE(fecha_visita) = CURDATE()"
            )->fetchColumn();
            $vf = (int)$conexion->query(
                "SELECT COUNT(*) FROM visitas_perfil WHERE DATE(fecha_visita) = CURDATE()"
            )->fetchColumn();
            $kpis['visitas_hoy'] = $vp + $vf;

            // Conversión global (ventas / visitas productos últimos 30 días)
            $visitas30 = (int)$conexion->query(
                "SELECT COUNT(*) FROM visitas_producto
                 WHERE fecha_visita >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            )->fetchColumn();
            $ventas30  = (int)$conexion->query(
                "SELECT COUNT(*) FROM transacciones
                 WHERE estado = 'APROBADO'
                   AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            )->fetchColumn();
            $kpis['conversion'] = $visitas30 > 0
                ? round(($ventas30 / $visitas30) * 100, 1)
                : 0;

            echo json_encode(['success' => true, 'kpis' => $kpis]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // VENTAS DIARIAS GLOBALES — últimos 30 días
    // ─────────────────────────────────────────────────────────────────────
    case 'ventas_diarias_global':
        try {
            $stmt = $conexion->query(
                "SELECT DATE(fecha_creacion) AS dia, COALESCE(SUM(total),0) AS total
                 FROM transacciones
                 WHERE estado = 'APROBADO'
                   AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY DATE(fecha_creacion)
                 ORDER BY dia ASC"
            );
            $rows   = $stmt->fetchAll();
            $labels = array_map(fn($r) => date('d/m', strtotime($r['dia'])), $rows);
            $vals   = array_map(fn($r) => (float)$r['total'], $rows);

            echo json_encode(['success' => true, 'labels' => $labels, 'valores' => $vals]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // CATEGORÍAS GLOBALES
    // ─────────────────────────────────────────────────────────────────────
    case 'categorias_global':
        try {
            $stmt = $conexion->query(
                "SELECT categoria_principal, COUNT(*) AS total
                 FROM productos
                 WHERE categoria_principal IS NOT NULL
                 GROUP BY categoria_principal
                 ORDER BY total DESC
                 LIMIT 12"
            );
            $rows   = $stmt->fetchAll();
            $labels = array_column($rows, 'categoria_principal');
            $vals   = array_map(fn($r) => (int)$r['total'], $rows);

            $colores = [
                '#10b981',
                '#3b82f6',
                '#f59e0b',
                '#ef4444',
                '#8b5cf6',
                '#14b8a6',
                '#f97316',
                '#ec4899',
                '#6366f1',
                '#84cc16',
                '#0ea5e9',
                '#a78bfa',
            ];

            echo json_encode([
                'success' => true,
                'labels'  => $labels,
                'valores' => $vals,
                'colores' => array_slice($colores, 0, count($rows)),
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // USUARIOS POR ROL
    // ─────────────────────────────────────────────────────────────────────
    case 'usuarios_por_rol':
        try {
            $stmt = $conexion->query(
                "SELECT rol, COUNT(*) AS total
                 FROM usuarios
                 WHERE rol IN ('vendedor','comprador','mixto')
                 GROUP BY rol ORDER BY total DESC"
            );
            $rows   = $stmt->fetchAll();
            $labels = array_map(fn($r) => ucfirst($r['rol']), $rows);
            $vals   = array_map(fn($r) => (int)$r['total'], $rows);

            echo json_encode(['success' => true, 'labels' => $labels, 'valores' => $vals]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // ACTIVIDAD POR HORA (visitas + transacciones)
    // ─────────────────────────────────────────────────────────────────────
    case 'actividad_por_hora':
        try {
            $stmt = $conexion->query(
                "SELECT HOUR(fecha_visita) AS hora, COUNT(*) AS total
                 FROM visitas_producto
                 WHERE fecha_visita >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY HOUR(fecha_visita)
                 ORDER BY hora ASC"
            );
            $rows = $stmt->fetchAll();

            // Rellenar las 24 horas
            $mapa = array_fill(0, 24, 0);
            foreach ($rows as $r) {
                $mapa[(int)$r['hora']] = (int)$r['total'];
            }

            $labels = array_map(fn($h) => str_pad($h, 2, '0', STR_PAD_LEFT) . 'h', range(0, 23));
            $vals   = array_values($mapa);

            echo json_encode(['success' => true, 'labels' => $labels, 'valores' => $vals]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // TABLA DE USUARIOS
    // ─────────────────────────────────────────────────────────────────────
    case 'usuarios_tabla':
        try {
            $filtro   = $_POST['filtro']   ?? 'todos';
            $busqueda = $_POST['busqueda'] ?? '';

            $where  = "WHERE u.rol != 'admin'";
            $params = [];

            if (!empty($busqueda)) {
                $where .= " AND (u.nombre LIKE :b OR u.email LIKE :b2)";
                $params[':b']  = "%$busqueda%";
                $params[':b2'] = "%$busqueda%";
            }

            if ($filtro !== 'todos' && in_array($filtro, ['vendedor', 'comprador', 'mixto'])) {
                $where .= " AND u.rol = :rol";
                $params[':rol'] = $filtro;
            }

            $stmt = $conexion->prepare(
                "SELECT
                     u.id_usuario,
                     u.nombre,
                     u.email,
                     u.rol,
                     u.estado,
                     u.fecha_registro,
                     COUNT(DISTINCT p.id_producto)      AS productos,
                     COALESCE(SUM(t.total), 0)          AS ventas_total,
                     ROUND(AVG(rv.calificacion), 1)     AS calificacion,
                     COUNT(DISTINCT rd.id_reporte)      AS denuncias_recibidas
                 FROM usuarios u
                 LEFT JOIN productos p         ON p.id_usuario  = u.id_usuario AND p.estado = 'disponible'
                 LEFT JOIN transacciones t     ON t.id_vendedor = u.id_usuario AND t.estado = 'APROBADO'
                 LEFT JOIN resenas_vendedor rv ON rv.id_vendedor = u.id_usuario
                 LEFT JOIN reportes_denuncias rd ON rd.id_denunciado = u.id_usuario
                             AND rd.estado NOT IN ('resuelta','cerrada')
                 $where
                 GROUP BY u.id_usuario
                 ORDER BY u.fecha_registro DESC
                 LIMIT 100"
            );
            $stmt->execute($params);
            $usuarios = $stmt->fetchAll();

            echo json_encode(['success' => true, 'usuarios' => $usuarios]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // TOGGLE ESTADO USUARIO (activar/suspender)
    // ─────────────────────────────────────────────────────────────────────
    case 'toggle_usuario':
        try {
            $id_usuario   = (int)($_POST['id_usuario'] ?? 0);
            $nuevo_estado = $_POST['estado'] ?? '';

            if (!$id_usuario || !in_array($nuevo_estado, ['activo', 'suspendido'])) {
                echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
                break;
            }

            $stmt = $conexion->prepare(
                "UPDATE usuarios SET estado = :estado WHERE id_usuario = :id AND rol != 'admin'"
            );
            $stmt->execute([':estado' => $nuevo_estado, ':id' => $id_usuario]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // TABLA DE PRODUCTOS GLOBALES
    // ─────────────────────────────────────────────────────────────────────
    case 'productos_tabla':
        try {
            $busqueda = $_POST['busqueda'] ?? '';
            $params   = [];
            $where    = "WHERE 1=1";

            if (!empty($busqueda)) {
                $where .= " AND (p.tipo_producto LIKE :b OR p.codigo_producto LIKE :b2 OR u.nombre LIKE :b3)";
                $params[':b']  = "%$busqueda%";
                $params[':b2'] = "%$busqueda%";
                $params[':b3'] = "%$busqueda%";
            }

            $stmt = $conexion->prepare(
                "SELECT
                     p.id_producto,
                     p.codigo_producto,
                     p.tipo_producto,
                     p.categoria_principal,
                     p.precio,
                     p.cantidad,
                     p.unidad,
                     p.estado,
                     p.fecha_publicacion,
                     u.nombre AS vendedor_nombre,
                     COUNT(vp.id) AS visitas
                 FROM productos p
                 INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                 LEFT  JOIN visitas_producto vp ON vp.id_producto = p.id_producto
                 $where
                 GROUP BY p.id_producto
                 ORDER BY p.fecha_publicacion DESC
                 LIMIT 200"
            );
            $stmt->execute($params);
            $productos = $stmt->fetchAll();

            echo json_encode(['success' => true, 'productos' => $productos]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // TABLA DE DENUNCIAS
    // ─────────────────────────────────────────────────────────────────────
    case 'denuncias_tabla':
        try {
            $filtro = $_POST['filtro'] ?? 'todas';
            $where  = "WHERE 1=1";
            $params = [];

            if ($filtro === 'recibidas') {
                $where .= " AND rd.estado = 'recibida'";
            } elseif ($filtro === 'en_revision') {
                $where .= " AND rd.estado = 'en_revision'";
            } elseif ($filtro === 'resueltas') {
                $where .= " AND rd.estado IN ('resuelta','cerrada')";
            }

            $stmt = $conexion->prepare(
                "SELECT
                     rd.id_reporte,
                     rd.categoria,
                     rd.estado,
                     rd.prioridad,
                     rd.fecha_creacion,
                     ud.nombre AS denunciante_nombre,
                     ue.nombre AS denunciado_nombre
                 FROM reportes_denuncias rd
                 LEFT JOIN usuarios ud ON rd.id_denunciante = ud.id_usuario
                 LEFT JOIN usuarios ue ON rd.id_denunciado  = ue.id_usuario
                 $where
                 ORDER BY
                     FIELD(rd.prioridad,'alta','media','baja'),
                     rd.fecha_creacion DESC
                 LIMIT 200"
            );
            $stmt->execute($params);
            $denuncias = $stmt->fetchAll();

            echo json_encode(['success' => true, 'denuncias' => $denuncias]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // DETALLE DE UNA DENUNCIA
    // ─────────────────────────────────────────────────────────────────────
    case 'denuncia_detalle':
        try {
            $id = (int)($_POST['id_reporte'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                break;
            }

            $stmt = $conexion->prepare(
                "SELECT
                     rd.*,
                     ud.nombre AS denunciante_nombre,
                     ue.nombre AS denunciado_nombre,
                     p.tipo_producto AS producto_nombre
                 FROM reportes_denuncias rd
                 LEFT JOIN usuarios ud ON rd.id_denunciante = ud.id_usuario
                 LEFT JOIN usuarios ue ON rd.id_denunciado  = ue.id_usuario
                 LEFT JOIN productos p  ON rd.id_producto   = p.id_producto
                 WHERE rd.id_reporte = :id LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $denuncia = $stmt->fetch();

            if (!$denuncia) {
                echo json_encode(['success' => false, 'message' => 'No encontrada']);
                break;
            }

            echo json_encode(['success' => true, 'denuncia' => $denuncia]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // ACTUALIZAR DENUNCIA
    // ─────────────────────────────────────────────────────────────────────
    case 'actualizar_denuncia':
        try {
            $id        = (int)($_POST['id_reporte'] ?? 0);
            $estado    = $_POST['estado']    ?? '';
            $respuesta = strip_tags($_POST['respuesta'] ?? '');

            $estados_validos = ['recibida', 'en_revision', 'pendiente_vendedor', 'resuelta', 'cerrada'];
            if (!$id || !in_array($estado, $estados_validos)) {
                echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
                break;
            }

            $fecha_res = in_array($estado, ['resuelta', 'cerrada']) ? 'NOW()' : 'NULL';

            $stmt = $conexion->prepare(
                "UPDATE reportes_denuncias
                 SET estado           = :estado,
                     respuesta_admin  = :respuesta,
                     fecha_resolucion = $fecha_res
                 WHERE id_reporte = :id"
            );
            $stmt->execute([
                ':estado'    => $estado,
                ':respuesta' => $respuesta ?: null,
                ':id'        => $id,
            ]);

            // Si hay 5+ denuncias abiertas contra el denunciado → suspender automáticamente
            $r = $conexion->prepare(
                "SELECT id_denunciado FROM reportes_denuncias WHERE id_reporte = :id LIMIT 1"
            );
            $r->execute([':id' => $id]);
            $den = $r->fetch();
            if ($den && $den['id_denunciado']) {
                $count = (int)$conexion->prepare(
                    "SELECT COUNT(*) FROM reportes_denuncias
                     WHERE id_denunciado = :uid AND estado NOT IN ('resuelta','cerrada')"
                )->execute([':uid' => $den['id_denunciado']]) ? $conexion->query(
                    "SELECT COUNT(*) FROM reportes_denuncias
                     WHERE id_denunciado = {$den['id_denunciado']} AND estado NOT IN ('resuelta','cerrada')"
                )->fetchColumn() : 0;

                if ($count >= 5) {
                    $conexion->prepare(
                        "UPDATE usuarios SET activo = 0 WHERE id_usuario = :uid AND rol != 'admin'"
                    )->execute([':uid' => $den['id_denunciado']]);
                }
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // RANKING VENDEDORES
    // ─────────────────────────────────────────────────────────────────────
    case 'ranking_vendedores':
        try {
            $stmt = $conexion->query(
                "SELECT
                     u.nombre,
                     ROUND(AVG(rv.calificacion), 1) AS calificacion,
                     COUNT(rv.id_resena)            AS total_resenas
                 FROM usuarios u
                 INNER JOIN resenas_vendedor rv ON rv.id_vendedor = u.id_usuario
                 GROUP BY u.id_usuario
                 HAVING total_resenas >= 1
                 ORDER BY calificacion DESC, total_resenas DESC
                 LIMIT 10"
            );
            $rows    = $stmt->fetchAll();
            $labels  = array_column($rows, 'nombre');
            $vals    = array_map(fn($r) => (float)$r['calificacion'], $rows);
            $resenas = array_map(fn($r) => (int)$r['total_resenas'], $rows);

            echo json_encode([
                'success' => true,
                'labels'  => $labels,
                'valores' => $vals,
                'resenas' => $resenas,
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // RANKING COMPRADORES
    // ─────────────────────────────────────────────────────────────────────
    case 'ranking_compradores':
        try {
            $stmt = $conexion->query(
                "SELECT
                     u.nombre,
                     COUNT(t.id_transaccion)    AS total_compras,
                     COALESCE(SUM(t.total), 0)  AS total_gastado
                 FROM usuarios u
                 INNER JOIN transacciones t ON t.id_comprador = u.id_usuario AND t.estado = 'APROBADO'
                 GROUP BY u.id_usuario
                 ORDER BY total_compras DESC, total_gastado DESC
                 LIMIT 10"
            );
            $compradores = $stmt->fetchAll();

            echo json_encode(['success' => true, 'compradores' => $compradores]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────────────
    // EXPORTAR EXCEL ADMIN
    // ─────────────────────────────────────────────────────────────────────
    case 'excel_admin':
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="ASCC_Admin_Reporte_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: no-cache');

        $secciones = array_map('trim', explode(',', $_GET['secciones'] ?? 'usuarios,productos,ventas,denuncias,visitas'));

        $verde     = '#065f46';
        $st_th     = "background:{$verde};color:#fff;font-weight:bold;font-size:11px;padding:10px 14px;border:1px solid #047857;text-align:left;";
        $st_td     = "padding:9px 14px;border:1px solid #d1d5db;font-size:11px;color:#111827;";
        $st_td_alt = "padding:9px 14px;border:1px solid #d1d5db;font-size:11px;color:#111827;background:#f0fdf4;";
        $st_sec    = "background:{$verde};color:#fff;font-size:14px;font-weight:bold;padding:14px;";

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: Calibri, Arial, sans-serif; margin: 20px; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 28px; }
        </style></head><body>';

        echo '<h1 style="color:#065f46;font-size:22px;margin:0 0 2px">ASCC</h1>';
        echo '<p style="font-size:13px;color:#065f46;margin:0 0 4px">Aromas y Sabores de mi Campo Colombiano</p>';
        echo '<p style="font-size:12px;color:#6b7280;margin:0 0 20px">Reporte de Administrador — Generado el ' . date('d/m/Y H:i') . '</p>';

        // SECCIÓN USUARIOS
        if (in_array('usuarios', $secciones)) {
            $stmt = $conexion->query(
                "SELECT u.nombre, u.email, u.rol,
                        CASE WHEN u.estado = 'activo' THEN 'Activo' WHEN u.estado = 'suspendido' THEN 'Suspendido' ELSE 'Inactivo' END AS estado,
                        DATE_FORMAT(u.fecha_registro,'%d/%m/%Y') AS registro,
                        COUNT(DISTINCT p.id_producto) AS productos,
                        ROUND(AVG(rv.calificacion),1) AS calificacion
                 FROM usuarios u
                 LEFT JOIN productos p ON p.id_usuario = u.id_usuario
                 LEFT JOIN resenas_vendedor rv ON rv.id_vendedor = u.id_usuario
                 WHERE u.rol != 'admin'
                 GROUP BY u.id_usuario
                 ORDER BY u.fecha_registro DESC"
            );
            $usuarios = $stmt->fetchAll();

            echo '<table>';
            echo "<tr><td colspan='7' style='{$st_sec}'>👥 USUARIOS DE LA PLATAFORMA</td></tr>";
            echo "<tr>
                <th style='{$st_th}'>Nombre</th>
                <th style='{$st_th}'>Email</th>
                <th style='{$st_th}'>Rol</th>
                <th style='{$st_th}'>Estado</th>
                <th style='{$st_th}'>Registro</th>
                <th style='{$st_th}'>Productos</th>
                <th style='{$st_th}'>Calificación</th>
            </tr>";
            if (empty($usuarios)) {
                echo "<tr><td colspan='7' style='{$st_td}text-align:center;color:#6b7280'>Sin datos</td></tr>";
            } else {
                foreach ($usuarios as $i => $u) {
                    $st = $i % 2 === 0 ? $st_td : $st_td_alt;
                    echo "<tr>
                        <td style='{$st}font-weight:600'>" . htmlspecialchars($u['nombre']) . "</td>
                        <td style='{$st}'>" . htmlspecialchars($u['email']) . "</td>
                        <td style='{$st}'>" . ucfirst($u['rol']) . "</td>
                        <td style='{$st}'>" . $u['estado'] . "</td>
                        <td style='{$st}'>" . $u['registro'] . "</td>
                        <td style='{$st}text-align:center'>" . $u['productos'] . "</td>
                        <td style='{$st}text-align:center'>" . ($u['calificacion'] ?? '—') . "</td>
                    </tr>";
                }
            }
            echo '</table>';
        }

        // SECCIÓN PRODUCTOS
        if (in_array('productos', $secciones)) {
            $stmt = $conexion->query(
                "SELECT p.codigo_producto, p.tipo_producto, p.categoria_principal,
                        u.nombre AS vendedor, p.precio, p.cantidad, p.unidad,
                        p.estado, DATE_FORMAT(p.fecha_publicacion,'%d/%m/%Y') AS publicado
                 FROM productos p
                 INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                 ORDER BY p.fecha_publicacion DESC
                 LIMIT 2000"
            );
            $productos = $stmt->fetchAll();

            echo '<table>';
            echo "<tr><td colspan='9' style='{$st_sec}'>📦 PRODUCTOS DE LA PLATAFORMA</td></tr>";
            echo "<tr>
                <th style='{$st_th}'>Código</th>
                <th style='{$st_th}'>Producto</th>
                <th style='{$st_th}'>Categoría</th>
                <th style='{$st_th}'>Vendedor</th>
                <th style='{$st_th}'>Precio COP</th>
                <th style='{$st_th}'>Cantidad</th>
                <th style='{$st_th}'>Unidad</th>
                <th style='{$st_th}'>Estado</th>
                <th style='{$st_th}'>Publicado</th>
            </tr>";
            if (empty($productos)) {
                echo "<tr><td colspan='9' style='{$st_td}text-align:center;color:#6b7280'>Sin datos</td></tr>";
            } else {
                foreach ($productos as $i => $p) {
                    $st = $i % 2 === 0 ? $st_td : $st_td_alt;
                    echo "<tr>
                        <td style='{$st}font-family:monospace;font-size:10px'>" . htmlspecialchars($p['codigo_producto'] ?? '—') . "</td>
                        <td style='{$st}font-weight:600'>" . htmlspecialchars($p['tipo_producto']) . "</td>
                        <td style='{$st}'>" . htmlspecialchars($p['categoria_principal'] ?? '—') . "</td>
                        <td style='{$st}'>" . htmlspecialchars($p['vendedor']) . "</td>
                        <td style='{$st}text-align:right'>\$" . number_format($p['precio'], 0, ',', '.') . "</td>
                        <td style='{$st}text-align:center'>" . $p['cantidad'] . "</td>
                        <td style='{$st}'>" . htmlspecialchars($p['unidad']) . "</td>
                        <td style='{$st}'>" . $p['estado'] . "</td>
                        <td style='{$st}'>" . $p['publicado'] . "</td>
                    </tr>";
                }
            }
            echo '</table>';
        }

        // SECCIÓN VENTAS
        if (in_array('ventas', $secciones)) {
            $stmt = $conexion->query(
                "SELECT DATE_FORMAT(t.fecha_creacion,'%d/%m/%Y') AS fecha,
                        p.tipo_producto, uc.nombre AS comprador, uv.nombre AS vendedor,
                        t.cantidad, t.precio_unitario, t.total, t.estado, t.metodo_pago
                 FROM transacciones t
                 INNER JOIN productos p  ON t.id_producto  = p.id_producto
                 INNER JOIN usuarios uc  ON t.id_comprador = uc.id_usuario
                 INNER JOIN usuarios uv  ON p.id_usuario   = uv.id_usuario
                 ORDER BY t.fecha_creacion DESC
                 LIMIT 5000"
            );
            $ventas = $stmt->fetchAll();

            echo '<table>';
            echo "<tr><td colspan='9' style='{$st_sec}'>💰 TRANSACCIONES DE LA PLATAFORMA</td></tr>";
            echo "<tr>
                <th style='{$st_th}'>Fecha</th>
                <th style='{$st_th}'>Producto</th>
                <th style='{$st_th}'>Comprador</th>
                <th style='{$st_th}'>Vendedor</th>
                <th style='{$st_th}'>Cantidad</th>
                <th style='{$st_th}'>Precio Unit.</th>
                <th style='{$st_th}'>Total COP</th>
                <th style='{$st_th}'>Estado</th>
                <th style='{$st_th}'>Método Pago</th>
            </tr>";
            if (empty($ventas)) {
                echo "<tr><td colspan='9' style='{$st_td}text-align:center;color:#6b7280'>Sin transacciones registradas aún</td></tr>";
            } else {
                foreach ($ventas as $i => $v) {
                    $st = $i % 2 === 0 ? $st_td : $st_td_alt;
                    echo "<tr>
                        <td style='{$st}'>" . $v['fecha'] . "</td>
                        <td style='{$st}font-weight:600'>" . htmlspecialchars($v['tipo_producto']) . "</td>
                        <td style='{$st}'>" . htmlspecialchars($v['comprador']) . "</td>
                        <td style='{$st}'>" . htmlspecialchars($v['vendedor']) . "</td>
                        <td style='{$st}text-align:center'>" . $v['cantidad'] . "</td>
                        <td style='{$st}text-align:right'>\$" . number_format($v['precio_unitario'], 0, ',', '.') . "</td>
                        <td style='{$st}text-align:right;font-weight:bold;color:#065f46'>\$" . number_format($v['total'], 0, ',', '.') . "</td>
                        <td style='{$st}'>" . $v['estado'] . "</td>
                        <td style='{$st}'>" . htmlspecialchars($v['metodo_pago'] ?? '—') . "</td>
                    </tr>";
                }
            }
            echo '</table>';
        }

        // SECCIÓN DENUNCIAS
        if (in_array('denuncias', $secciones)) {
            $stmt = $conexion->query(
                "SELECT rd.id_reporte, ud.nombre AS denunciante, ue.nombre AS denunciado,
                        rd.categoria, rd.estado, rd.prioridad,
                        DATE_FORMAT(rd.fecha_creacion,'%d/%m/%Y') AS fecha
                 FROM reportes_denuncias rd
                 LEFT JOIN usuarios ud ON rd.id_denunciante = ud.id_usuario
                 LEFT JOIN usuarios ue ON rd.id_denunciado  = ue.id_usuario
                 ORDER BY FIELD(rd.prioridad,'alta','media','baja'), rd.fecha_creacion DESC"
            );
            $denuncias = $stmt->fetchAll();

            echo '<table>';
            echo "<tr><td colspan='7' style='{$st_sec}'>🚨 DENUNCIAS</td></tr>";
            echo "<tr>
                <th style='{$st_th}'>ID</th>
                <th style='{$st_th}'>Denunciante</th>
                <th style='{$st_th}'>Denunciado</th>
                <th style='{$st_th}'>Categoría</th>
                <th style='{$st_th}'>Estado</th>
                <th style='{$st_th}'>Prioridad</th>
                <th style='{$st_th}'>Fecha</th>
            </tr>";
            if (empty($denuncias)) {
                echo "<tr><td colspan='7' style='{$st_td}text-align:center;color:#6b7280'>Sin denuncias registradas</td></tr>";
            } else {
                foreach ($denuncias as $i => $d) {
                    $st = $i % 2 === 0 ? $st_td : $st_td_alt;
                    echo "<tr>
                        <td style='{$st}font-family:monospace'>#" . $d['id_reporte'] . "</td>
                        <td style='{$st}'>" . htmlspecialchars($d['denunciante'] ?? '—') . "</td>
                        <td style='{$st}'>" . htmlspecialchars($d['denunciado'] ?? '—') . "</td>
                        <td style='{$st}'>" . htmlspecialchars($d['categoria']) . "</td>
                        <td style='{$st}'>" . $d['estado'] . "</td>
                        <td style='{$st}'>" . $d['prioridad'] . "</td>
                        <td style='{$st}'>" . $d['fecha'] . "</td>
                    </tr>";
                }
            }
            echo '</table>';
        }

        echo '</body></html>';
        exit;

        // ─────────────────────────────────────────────────────────────────────
        // EXPORTAR CSV ADMIN (para Power BI)
        // ─────────────────────────────────────────────────────────────────────
    case 'csv_admin':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ASCC_Admin_PowerBI_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-cache');

        $secciones = array_map('trim', explode(',', $_GET['secciones'] ?? 'usuarios,productos,ventas,denuncias'));
        $out       = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");

        if (in_array('usuarios', $secciones)) {
            fputcsv($out, ['## USERS']);
            fputcsv($out, ['name', 'email', 'role', 'status', 'registered_date', 'products', 'avg_rating']);
            $stmt = $conexion->query(
                "SELECT u.nombre, u.email, u.rol,
                        CASE WHEN u.estado='activo' THEN 'active' ELSE u.estado END,
                        DATE(u.fecha_registro),
                        COUNT(DISTINCT p.id_producto),
                        ROUND(AVG(rv.calificacion),1)
                 FROM usuarios u
                 LEFT JOIN productos p ON p.id_usuario = u.id_usuario
                 LEFT JOIN resenas_vendedor rv ON rv.id_vendedor = u.id_usuario
                 WHERE u.rol != 'admin' GROUP BY u.id_usuario"
            );
            while ($r = $stmt->fetch(PDO::FETCH_NUM)) fputcsv($out, $r);
            fputcsv($out, []);
        }

        if (in_array('ventas', $secciones)) {
            fputcsv($out, ['## SALES']);
            fputcsv($out, ['date', 'product', 'category', 'buyer', 'seller', 'quantity', 'unit_price', 'total', 'status']);
            $stmt = $conexion->query(
                "SELECT DATE(t.fecha_creacion), p.tipo_producto, p.categoria_principal,
                        uc.nombre, uv.nombre,
                        t.cantidad, t.precio_unitario, t.total, t.estado
                 FROM transacciones t
                 INNER JOIN productos p ON t.id_producto  = p.id_producto
                 INNER JOIN usuarios uc ON t.id_comprador = uc.id_usuario
                 INNER JOIN usuarios uv ON p.id_usuario   = uv.id_usuario
                 ORDER BY t.fecha_creacion DESC LIMIT 5000"
            );
            while ($r = $stmt->fetch(PDO::FETCH_NUM)) fputcsv($out, $r);
            fputcsv($out, []);
        }

        if (in_array('productos', $secciones)) {
            fputcsv($out, ['## PRODUCTS']);
            fputcsv($out, ['code', 'product_name', 'category', 'seller', 'price', 'stock', 'unit', 'status', 'published_date']);
            $stmt = $conexion->query(
                "SELECT p.codigo_producto, p.tipo_producto, p.categoria_principal,
                        u.nombre, p.precio, p.cantidad, p.unidad, p.estado, DATE(p.fecha_publicacion)
                 FROM productos p INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                 ORDER BY p.fecha_publicacion DESC LIMIT 2000"
            );
            while ($r = $stmt->fetch(PDO::FETCH_NUM)) fputcsv($out, $r);
            fputcsv($out, []);
        }

        fclose($out);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
