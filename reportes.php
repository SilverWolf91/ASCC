<?php

/**
 * ASCC — Módulo de Reportes del Usuario
 * Ruta: reportes.php
 *
 * Roles soportados:
 *   vendedor → ve sus ventas, productos, visitas, ranking, recomendaciones
 *   comprador → ve sus compras, proveedores favoritos, historial
 *   mixto    → toggle entre vista vendedor y vista comprador
 *
 * Depende de:
 *   config/app.php       → $lang, $theme, t(), ascc_theme_css()
 *   config/database.php  → $conexion
 *   lang/es.php          → claves rep_*
 *   lang/en.php          → claves rep_*
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// ── Autenticación ─────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ascc/views/auth/login.php?redirect=reportes');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

// ── Obtener rol del usuario ───────────────────────────────────
$stmtUser = $conexion->prepare(
    "SELECT nombre, rol, foto_perfil FROM usuarios WHERE id_usuario = :id LIMIT 1"
);
$stmtUser->execute([':id' => $id_usuario]);
$usuario = $stmtUser->fetch();

if (!$usuario || !in_array($usuario['rol'], ['vendedor', 'comprador', 'mixto'], true)) {
    header('Location: /ascc/catalogo.php');
    exit;
}

$rol         = $usuario['rol'];
$nombre      = $usuario['nombre'];
$foto_perfil = $usuario['foto_perfil'];

// ── Vista activa para mixto (vendedor | comprador) ────────────
$vista_activa = 'vendedor';
if ($rol === 'comprador') {
    $vista_activa = 'comprador';
} elseif ($rol === 'mixto') {
    $vista_activa = $_GET['vista'] ?? $_SESSION['rep_vista'] ?? 'vendedor';
    $vista_activa = in_array($vista_activa, ['vendedor', 'comprador'], true)
        ? $vista_activa : 'vendedor';
    $_SESSION['rep_vista'] = $vista_activa;
}

$es_vendedor = in_array($rol, ['vendedor', 'mixto'], true) && $vista_activa === 'vendedor';
$es_comprador = $rol === 'comprador' || ($rol === 'mixto' && $vista_activa === 'comprador');

// ── Subtítulo según rol/vista ─────────────────────────────────
if ($rol === 'mixto') {
    $subtitulo = t('rep_page_subtitle_mixto');
} elseif ($es_vendedor) {
    $subtitulo = t('rep_page_subtitle_vendedor');
} else {
    $subtitulo = t('rep_page_subtitle_comprador');
}

// ═════════════════════════════════════════════════════════════
// DATOS PARA VENDEDOR
// ═════════════════════════════════════════════════════════════
$kpi_v = [
    'ingresos_mes'       => 0,
    'ingresos_anterior'  => 0,
    'productos_activos'  => 0,
    'stock_bajo'         => 0,
    'compradores_unicos' => 0,
    'compradores_semana' => 0,
    'calificacion'       => 0,
    'total_resenas'      => 0,
    'visitas_mes'        => 0,
    'conversion'         => 0,
];

$visitas_frecuentes  = [];
$visitantes_recientes = [];
$productos_por_visitas = [];
$recomendaciones     = [];
$ranking_pos         = 0;
$ranking_total       = 0;
$ranking_top         = [];

if ($es_vendedor) {

    // Ingresos mes actual
    $r = $conexion->prepare(
        "SELECT COALESCE(SUM(t.total),0) AS total
         FROM transacciones t
         INNER JOIN productos p ON t.id_producto = p.id_producto
         WHERE p.id_usuario = :uid
           AND t.estado = 'aprobado'
           AND MONTH(t.fecha_creacion) = MONTH(NOW())
           AND YEAR(t.fecha_creacion)  = YEAR(NOW())"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_v['ingresos_mes'] = (float)$r->fetchColumn();

    // Ingresos mes anterior
    $r = $conexion->prepare(
        "SELECT COALESCE(SUM(t.total),0) AS total
         FROM transacciones t
         INNER JOIN productos p ON t.id_producto = p.id_producto
         WHERE p.id_usuario = :uid
           AND t.estado = 'aprobado'
           AND MONTH(t.fecha_creacion) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
           AND YEAR(t.fecha_creacion)  = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_v['ingresos_anterior'] = (float)$r->fetchColumn();

    // Productos activos y stock bajo (cantidad <= 5)
    $r = $conexion->prepare(
        "SELECT
             COUNT(*) AS activos,
             SUM(CASE WHEN cantidad <= 5 THEN 1 ELSE 0 END) AS stock_bajo
         FROM productos
         WHERE id_usuario = :uid AND estado = 'disponible'"
    );
    $r->execute([':uid' => $id_usuario]);
    $row = $r->fetch();
    $kpi_v['productos_activos'] = (int)($row['activos']    ?? 0);
    $kpi_v['stock_bajo']        = (int)($row['stock_bajo'] ?? 0);

    // Compradores únicos del mes
    $r = $conexion->prepare(
        "SELECT COUNT(DISTINCT t.id_comprador) AS total
         FROM transacciones t
         INNER JOIN productos p ON t.id_producto = p.id_producto
         WHERE p.id_usuario = :uid
           AND t.estado = 'aprobado'
           AND MONTH(t.fecha_creacion) = MONTH(NOW())
           AND YEAR(t.fecha_creacion)  = YEAR(NOW())"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_v['compradores_unicos'] = (int)$r->fetchColumn();

    // Compradores nuevos esta semana
    $r = $conexion->prepare(
        "SELECT COUNT(DISTINCT t.id_comprador) AS total
         FROM transacciones t
         INNER JOIN productos p ON t.id_producto = p.id_producto
         WHERE p.id_usuario = :uid
           AND t.estado = 'aprobado'
           AND t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_v['compradores_semana'] = (int)$r->fetchColumn();

    // Calificación promedio
    $r = $conexion->prepare(
        "SELECT ROUND(AVG(calificacion),1) AS prom, COUNT(*) AS total
         FROM resenas_vendedor WHERE id_vendedor = :uid"
    );
    $r->execute([':uid' => $id_usuario]);
    $rowCal = $r->fetch();
    $kpi_v['calificacion']  = (float)($rowCal['prom']  ?? 0);
    $kpi_v['total_resenas'] = (int)($rowCal['total'] ?? 0);

    // Visitas al perfil este mes
    $r = $conexion->prepare(
        "SELECT COUNT(*) FROM visitas_perfil
         WHERE id_vendedor = :uid
           AND MONTH(fecha_visita) = MONTH(NOW())
           AND YEAR(fecha_visita)  = YEAR(NOW())"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_v['visitas_mes'] = (int)$r->fetchColumn();

    // Tasa de conversión: ventas / total visitas productos (últimos 30 días)
    // Usamos COUNT(*) — contar todas las visitas registradas, no solo sesiones únicas
    $r = $conexion->prepare(
        "SELECT COUNT(*) FROM visitas_producto vp
         INNER JOIN productos p ON vp.id_producto = p.id_producto
         WHERE p.id_usuario = :uid
           AND vp.fecha_visita >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $r->execute([':uid' => $id_usuario]);
    $total_visitas_prod = (int)$r->fetchColumn();

    $r = $conexion->prepare(
        "SELECT COUNT(*) FROM transacciones t
         INNER JOIN productos p ON t.id_producto = p.id_producto
         WHERE p.id_usuario = :uid
           AND t.estado = 'aprobado'
           AND t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $r->execute([':uid' => $id_usuario]);
    $total_ventas_prod = (int)$r->fetchColumn();

    $kpi_v['conversion'] = $total_visitas_prod > 0
        ? round(($total_ventas_prod / $total_visitas_prod) * 100)
        : 0;

    // Visitantes frecuentes (>= 3 visitas esta semana al perfil)
    $r = $conexion->prepare(
        "SELECT u.nombre, u.rol, COUNT(*) AS visitas
         FROM visitas_perfil vp
         INNER JOIN usuarios u ON vp.id_visitante = u.id_usuario
         WHERE vp.id_vendedor = :uid
           AND vp.fecha_visita >= DATE_SUB(NOW(), INTERVAL 7 DAY)
           AND vp.id_visitante != :uid2
         GROUP BY vp.id_visitante
         HAVING visitas >= 3
         ORDER BY visitas DESC
         LIMIT 3"
    );
    $r->execute([':uid' => $id_usuario, ':uid2' => $id_usuario]);
    $visitas_frecuentes = $r->fetchAll();

    // Visitantes recientes al perfil (últimas 24h)
    // TIMESTAMPDIFF calcula la diferencia en MySQL — evita problemas de zona horaria entre PHP y MySQL
    $r = $conexion->prepare(
        "SELECT u.nombre, u.rol, vp.fecha_visita,
                TIMESTAMPDIFF(SECOND, vp.fecha_visita, NOW()) AS segundos_ago
         FROM visitas_perfil vp
         LEFT JOIN usuarios u ON vp.id_visitante = u.id_usuario
         WHERE vp.id_vendedor = :uid
           AND vp.fecha_visita >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY vp.fecha_visita DESC
         LIMIT 10"
    );
    $r->execute([':uid' => $id_usuario]);
    $visitantes_recientes = $r->fetchAll();

    // Productos por visitas con conversión
    $r = $conexion->prepare(
        "SELECT
             p.id_producto,
             p.tipo_producto,
             COUNT(*)                      AS visitas,
             COUNT(DISTINCT m.id_mensaje)  AS contactos,
             SUM(CASE WHEN t.estado = 'aprobado' THEN 1 ELSE 0 END) AS ventas
         FROM productos p
         LEFT JOIN visitas_producto vp ON vp.id_producto = p.id_producto
         LEFT JOIN conversaciones cv ON cv.id_producto = p.id_producto
         LEFT JOIN mensajes m ON m.id_conversacion = cv.id_conversacion
         LEFT JOIN transacciones t ON t.id_producto = p.id_producto
         WHERE p.id_usuario = :uid
         GROUP BY p.id_producto, p.tipo_producto
         ORDER BY visitas DESC
         LIMIT 8"
    );
    $r->execute([':uid' => $id_usuario]);
    $productos_por_visitas = $r->fetchAll();

    // Posición en el ranking de vendedores
    $r = $conexion->query(
        "SELECT id_vendedor,
                ROUND(AVG(calificacion),2) AS prom,
                COUNT(*) AS total_res
         FROM resenas_vendedor
         GROUP BY id_vendedor
         HAVING total_res >= 1
         ORDER BY prom DESC, total_res DESC"
    );
    $ranking_all   = $r->fetchAll();
    $ranking_total = count($ranking_all);
    $ranking_pos   = 0;
    foreach ($ranking_all as $idx => $row) {
        if ((int)$row['id_vendedor'] === $id_usuario) {
            $ranking_pos = $idx + 1;
            break;
        }
    }

    // Top 5 ranking vendedores
    $r = $conexion->prepare(
        "SELECT u.nombre, u.foto_perfil,
                ROUND(AVG(rv.calificacion),1) AS prom,
                COUNT(rv.id_resena) AS total_res
         FROM resenas_vendedor rv
         INNER JOIN usuarios u ON rv.id_vendedor = u.id_usuario
         GROUP BY rv.id_vendedor
         HAVING total_res >= 1
         ORDER BY prom DESC, total_res DESC
         LIMIT 5"
    );
    $r->execute();
    $ranking_top = $r->fetchAll();

    // Recomendaciones de precio
    $r = $conexion->prepare(
        "SELECT
             p.id_producto,
             p.tipo_producto,
             p.precio,
             p.categoria_principal,
             (SELECT COUNT(*) FROM transacciones t2
              WHERE t2.id_producto = p.id_producto
                AND t2.estado = 'aprobado'
                AND t2.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ) AS ventas_mes,
             (SELECT COUNT(*) FROM imagenes_productos ip
              WHERE ip.id_producto = p.id_producto
             ) AS tiene_fotos,
             (SELECT AVG(p2.precio)
              FROM productos p2
              WHERE p2.categoria_principal = p.categoria_principal
                AND p2.estado = 'disponible'
                AND p2.id_usuario != :uid
             ) AS precio_promedio_cat
         FROM productos p
         WHERE p.id_usuario = :uid2
           AND p.estado = 'disponible'
         LIMIT 20"
    );
    $r->execute([':uid' => $id_usuario, ':uid2' => $id_usuario]);
    $productos_rec = $r->fetchAll();

    foreach ($productos_rec as $pr) {
        $prom_cat = (float)($pr['precio_promedio_cat'] ?? 0);
        $precio   = (float)$pr['precio'];
        $ventas   = (int)$pr['ventas_mes'];
        $nombre_p = htmlspecialchars($pr['tipo_producto']);

        // Sin foto
        if ((int)$pr['tiene_fotos'] === 0) {
            $recomendaciones[] = [
                'tipo'    => 'warning',
                'titulo'  => $nombre_p . ' — ' . t('rep_rec_sin_foto'),
                'cuerpo'  => '',
            ];
        }

        // Precio alto sin ventas (>15 días con precio 20% sobre promedio)
        if ($prom_cat > 0 && $precio > $prom_cat * 1.20 && $ventas === 0) {
            $pct        = round((($precio - $prom_cat) / $prom_cat) * 100);
            $precio_sug = number_format($prom_cat * 1.05, 0, ',', '.');
            $recomendaciones[] = [
                'tipo'   => 'danger',
                'titulo' => t('rep_rec_baja_precio') . ' ' . $nombre_p,
                'cuerpo' => "Tu precio (\$" . number_format($precio, 0, ',', '.')
                    . ") está {$pct}% por encima del promedio (\$"
                    . number_format($prom_cat, 0, ',', '.')
                    . "). Precio sugerido: \${$precio_sug}.",
            ];
        }

        // Producto que se vende muy rápido — subir precio
        if ($ventas >= 10) {
            $precio_sug = number_format($precio * 1.12, 0, ',', '.');
            $recomendaciones[] = [
                'tipo'   => 'success',
                'titulo' => t('rep_rec_sube_precio') . ' ' . $nombre_p,
                'cuerpo' => "Se vendieron {$ventas} unidades este mes. "
                    . "Podrías subir el precio un 12% (\${$precio_sug}) sin afectar las ventas.",
            ];
        }
    }

    // Limitar recomendaciones a 6
    $recomendaciones = array_slice($recomendaciones, 0, 6);
}

// ═════════════════════════════════════════════════════════════
// DATOS PARA COMPRADOR
// ═════════════════════════════════════════════════════════════
$kpi_c = [
    'gastado_mes'         => 0,
    'gastado_anterior'    => 0,
    'pedidos_mes'         => 0,
    'pedidos_completados' => 0,
    'vendedores_fav'      => 0,
    'categorias'          => 0,
];
$ranking_compradores = [];

if ($es_comprador) {

    // Gasto mes actual
    $r = $conexion->prepare(
        "SELECT COALESCE(SUM(total),0) FROM transacciones
         WHERE id_comprador = :uid AND estado = 'aprobado'
           AND MONTH(fecha_creacion) = MONTH(NOW())
           AND YEAR(fecha_creacion)  = YEAR(NOW())"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_c['gastado_mes'] = (float)$r->fetchColumn();

    // Gasto mes anterior
    $r = $conexion->prepare(
        "SELECT COALESCE(SUM(total),0) FROM transacciones
         WHERE id_comprador = :uid AND estado = 'aprobado'
           AND MONTH(fecha_creacion) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
           AND YEAR(fecha_creacion)  = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_c['gastado_anterior'] = (float)$r->fetchColumn();

    // Pedidos del mes
    $r = $conexion->prepare(
        "SELECT COUNT(*) FROM transacciones
         WHERE id_comprador = :uid
           AND MONTH(fecha_creacion) = MONTH(NOW())
           AND YEAR(fecha_creacion)  = YEAR(NOW())"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_c['pedidos_mes'] = (int)$r->fetchColumn();

    // Pedidos completados
    $r = $conexion->prepare(
        "SELECT COUNT(*) FROM transacciones
         WHERE id_comprador = :uid AND estado = 'aprobado'"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_c['pedidos_completados'] = (int)$r->fetchColumn();

    // Vendedores únicos con los que ha comprado
    $r = $conexion->prepare(
        "SELECT COUNT(DISTINCT p.id_usuario) FROM transacciones t
         INNER JOIN productos p ON t.id_producto = p.id_producto
         WHERE t.id_comprador = :uid AND t.estado = 'aprobado'"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_c['vendedores_fav'] = (int)$r->fetchColumn();

    // Categorías únicas compradas
    $r = $conexion->prepare(
        "SELECT COUNT(DISTINCT p.categoria_principal) FROM transacciones t
         INNER JOIN productos p ON t.id_producto = p.id_producto
         WHERE t.id_comprador = :uid AND t.estado = 'aprobado'"
    );
    $r->execute([':uid' => $id_usuario]);
    $kpi_c['categorias'] = (int)$r->fetchColumn();

    // Top compradores más confiables (más transacciones completadas)
    $r = $conexion->prepare(
        "SELECT u.nombre, u.foto_perfil, COUNT(*) AS total_compras
         FROM transacciones t
         INNER JOIN usuarios u ON t.id_comprador = u.id_usuario
         WHERE t.estado = 'aprobado'
         GROUP BY t.id_comprador
         ORDER BY total_compras DESC
         LIMIT 5"
    );
    $r->execute();
    $ranking_compradores = $r->fetchAll();
}

// ═════════════════════════════════════════════════════════════
// DENUNCIAS ENVIADAS POR EL USUARIO
// ═════════════════════════════════════════════════════════════
$r = $conexion->prepare(
    "SELECT id_reporte, tipo_denuncia, categoria, estado, fecha_creacion
     FROM reportes_denuncias
     WHERE id_denunciante = :uid
     ORDER BY fecha_creacion DESC
     LIMIT 10"
);
$r->execute([':uid' => $id_usuario]);
$mis_denuncias = $r->fetchAll();

// Denuncias recibidas contra el usuario (para semáforo)
$r = $conexion->prepare(
    "SELECT COUNT(*) FROM reportes_denuncias
     WHERE id_denunciado = :uid
       AND estado IN ('resuelta','cerrada')"
);
$r->execute([':uid' => $id_usuario]);
$denuncias_contra = (int)$r->fetchColumn();

// Semáforo de cuenta
if ($denuncias_contra >= 5) {
    $semaforo = 'rojo';
} elseif ($denuncias_contra >= 3) {
    $semaforo = 'amarillo';
} else {
    $semaforo = 'verde';
}

// ═════════════════════════════════════════════════════════════
// TOKEN API (Power BI)
// ═════════════════════════════════════════════════════════════
$r = $conexion->prepare(
    "SELECT token FROM api_tokens WHERE id_usuario = :uid AND activo = 1 LIMIT 1"
);
$r->execute([':uid' => $id_usuario]);
$api_token = $r->fetchColumn();
$api_url   = $api_token
    ? "http://localhost/ascc/api/reportes_data.php?token={$api_token}"
    : '';

// ═════════════════════════════════════════════════════════════
// DELTA % INGRESOS vs MES ANTERIOR
// ═════════════════════════════════════════════════════════════
function calcularDelta(float $actual, float $anterior): string
{
    if ($anterior <= 0) return '+0%';
    $pct = round((($actual - $anterior) / $anterior) * 100);
    return ($pct >= 0 ? '+' : '') . $pct . '%';
}

$delta_ingresos = $es_vendedor
    ? calcularDelta($kpi_v['ingresos_mes'], $kpi_v['ingresos_anterior'])
    : calcularDelta($kpi_c['gastado_mes'],  $kpi_c['gastado_anterior']);

// ═════════════════════════════════════════════════════════════
// ESTADO DE VISITAS FRECUENTES — para el banner de alerta
// ═════════════════════════════════════════════════════════════
$alerta_visitante = null;
if (!empty($visitas_frecuentes)) {
    $top = $visitas_frecuentes[0];
    $alerta_visitante = htmlspecialchars($top['nombre']) . ' '
        . t('rep_vis_alerta') . ' '
        . $top['visitas'] . ' '
        . t('rep_vis_veces');
}

// ═════════════════════════════════════════════════════════════
// HELPER: tiempo relativo
// ═════════════════════════════════════════════════════════════
function tiempoRelativo(string $fecha, ?int $segundos_ago = null): string
{
    // Usar segundos calculados en MySQL si están disponibles (evita diferencias de zona horaria)
    $diff = $segundos_ago ?? (time() - strtotime($fecha));

    if ($diff < 60)    return 'Hace un momento';
    if ($diff < 3600)  return sprintf(t('rep_vis_hace_min'),   (int)($diff / 60));
    if ($diff < 7200)  return sprintf(t('rep_vis_hace_hora'),  1);
    if ($diff < 86400) return sprintf(t('rep_vis_hace_horas'), (int)($diff / 3600));
    return sprintf(t('rep_vis_hace_dias'), (int)($diff / 86400));
}

// ═════════════════════════════════════════════════════════════
// MAPA DE ETIQUETAS DE CATEGORÍA DE DENUNCIA
// ═════════════════════════════════════════════════════════════
$cat_labels = [
    'no_entregado'      => t('rep_den_cat_no_entregado'),
    'descripcion_enganosa' => t('rep_den_cat_desc_enganosa'),
    'precio_diferente'  => t('rep_den_cat_precio_dif'),
    'mala_calidad'      => t('rep_den_cat_mala_calidad'),
    'vendedor_no_responde' => t('rep_den_cat_no_responde'),
    'resena_falsa'      => t('rep_den_cat_resena_falsa'),
    'lenguaje_inapropiado' => t('rep_den_cat_lenguaje'),
    'otro'              => t('rep_den_cat_otro'),
];

$estado_labels = [
    'recibida'           => t('rep_den_estado_recibida'),
    'en_revision'        => t('rep_den_estado_revision'),
    'pendiente_vendedor' => t('rep_den_estado_pendiente'),
    'resuelta'           => t('rep_den_estado_resuelta'),
    'cerrada'            => t('rep_den_estado_cerrada'),
];

$estado_badge = [
    'recibida'           => 'badge-info',
    'en_revision'        => 'badge-warning',
    'pendiente_vendedor' => 'badge-warning',
    'resuelta'           => 'badge-success',
    'cerrada'            => 'badge-muted',
];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('rep_page_title') ?> — <?= t('app_name') ?></title>

    <link rel="icon" type="image/png" href="/ascc/public/img/logo.png">

    <?= ascc_theme_css() ?>

    <link rel="stylesheet" href="/ascc/public/css/reportes.css?v=<?= time() ?>">

    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

    <!-- i18n para JS del módulo -->
    <script>
    window.REP_I18N = <?= json_encode([
                                'cargando'          => t('rep_cargando'),
                                'sin_datos'         => t('rep_sin_datos'),
                                'error_cargar'      => t('rep_error_cargar'),
                                'actualizado'       => t('rep_actualizado'),
                                'den_creada'        => t('rep_den_creada'),
                                'den_error_vacio'   => t('rep_den_error_vacio'),
                                'den_error_generico' => t('rep_den_error_generico'),
                                'token_copiado'     => t('rep_exp_token_copiado'),
                                'graf_mes_actual'   => t('rep_graf_mes_actual'),
                                'graf_mes_anterior' => t('rep_graf_mes_anterior'),
                                'graf_mas_vendidos' => t('rep_graf_mas_vendidos'),
                                'graf_menos_vendidos' => t('rep_graf_menos_vendidos'),
                                'graf_vieron'       => t('rep_graf_vieron'),
                                'graf_contactaron'  => t('rep_graf_contactaron'),
                                'graf_compraron'    => t('rep_graf_compraron'),
                            ], JSON_UNESCAPED_UNICODE) ?>;

    window.REP_CONFIG = {
        id_usuario: <?= $id_usuario ?>,
        rol: '<?= $rol ?>',
        vista_activa: '<?= $vista_activa ?>',
        es_vendedor: <?= $es_vendedor  ? 'true' : 'false' ?>,
        es_comprador: <?= $es_comprador ? 'true' : 'false' ?>,
        api_url: '<?= $api_url ?>',
        csrf_token: '<?= $_SESSION['csrf_token'] ?>',
    };
    </script>
</head>

<body class="theme-<?= $theme ?>" data-theme="<?= $theme ?>">

    <div class="rep-container">

        <!-- ══ CABECERA ══════════════════════════════════════ -->
        <div class="rep-header">
            <div class="rep-header__info">
                <a href="/ascc/dashboard.php" class="rep-back-link">
                    <?= t('dashboard') ?>
                </a>
                <h1 class="rep-header__title">
                    📊 <?= t('rep_page_title') ?>
                </h1>
                <p class="rep-header__subtitle"><?= $subtitulo ?></p>
            </div>

            <?php if ($rol === 'mixto'): ?>
            <!-- Toggle vendedor / comprador para mixtos -->
            <div class="rep-vista-toggle">
                <a href="?vista=vendedor" class="rep-vista-btn <?= $vista_activa === 'vendedor' ? 'active' : '' ?>">
                    🌾 <?= t('rep_vista_vendedor') ?>
                </a>
                <a href="?vista=comprador" class="rep-vista-btn <?= $vista_activa === 'comprador' ? 'active' : '' ?>">
                    🛒 <?= t('rep_vista_comprador') ?>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- ══ TABS ═══════════════════════════════════════════ -->
        <div class="rep-tabs" id="repTabs">
            <button class="rep-tab-btn active" data-tab="metricas">
                📈 <?= t('rep_tab_metricas') ?>
            </button>
            <button class="rep-tab-btn" data-tab="graficas">
                📊 <?= t('rep_tab_graficas') ?>
            </button>
            <?php if ($es_vendedor): ?>
            <button class="rep-tab-btn" data-tab="recomendaciones">
                💡 <?= t('rep_tab_recomendaciones') ?>
            </button>
            <button class="rep-tab-btn" data-tab="visitas">
                👁️ <?= t('rep_tab_visitas') ?>
            </button>
            <button class="rep-tab-btn" data-tab="ranking">
                🏆 <?= t('rep_tab_ranking') ?>
            </button>
            <?php endif; ?>
            <button class="rep-tab-btn" data-tab="denuncias">
                🚨 <?= t('rep_tab_denuncias') ?>
            </button>
            <button class="rep-tab-btn" data-tab="exportar">
                📥 <?= t('rep_tab_exportar') ?>
            </button>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB: MÉTRICAS
        ════════════════════════════════════════════════════ -->
        <div id="tab-metricas" class="rep-tab-panel active">

            <!-- KPIs vendedor -->
            <?php if ($es_vendedor): ?>
            <div class="rep-kpi-grid">

                <div class="rep-kpi">
                    <div class="rep-kpi__icon rep-kpi--green">💰</div>
                    <div class="rep-kpi__body">
                        <span class="rep-kpi__label"><?= t('rep_kpi_ingresos_mes') ?></span>
                        <span class="rep-kpi__value">
                            $<?= number_format($kpi_v['ingresos_mes'], 0, ',', '.') ?>
                        </span>
                        <span
                            class="rep-kpi__delta <?= str_starts_with($delta_ingresos, '+') ? 'delta-up' : 'delta-down' ?>">
                            <?= $delta_ingresos ?> <?= t('rep_kpi_vs_mes_anterior') ?>
                        </span>
                    </div>
                </div>

                <div class="rep-kpi">
                    <div class="rep-kpi__icon rep-kpi--blue">📦</div>
                    <div class="rep-kpi__body">
                        <span class="rep-kpi__label"><?= t('rep_kpi_productos_activos') ?></span>
                        <span class="rep-kpi__value"><?= $kpi_v['productos_activos'] ?></span>
                        <?php if ($kpi_v['stock_bajo'] > 0): ?>
                        <span class="rep-kpi__delta delta-warn">
                            <?= $kpi_v['stock_bajo'] ?> <?= t('rep_kpi_stock_bajo') ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rep-kpi">
                    <div class="rep-kpi__icon rep-kpi--amber">👥</div>
                    <div class="rep-kpi__body">
                        <span class="rep-kpi__label"><?= t('rep_kpi_compradores_unicos') ?></span>
                        <span class="rep-kpi__value"><?= $kpi_v['compradores_unicos'] ?></span>
                        <span class="rep-kpi__delta delta-up">
                            +<?= $kpi_v['compradores_semana'] ?> <?= t('rep_kpi_esta_semana') ?>
                        </span>
                    </div>
                </div>

                <div class="rep-kpi">
                    <div class="rep-kpi__icon rep-kpi--green">⭐</div>
                    <div class="rep-kpi__body">
                        <span class="rep-kpi__label"><?= t('rep_kpi_calificacion') ?></span>
                        <span class="rep-kpi__value"><?= number_format($kpi_v['calificacion'], 1) ?></span>
                        <?php if ($ranking_pos > 0): ?>
                        <span class="rep-kpi__delta delta-up">
                            <?= t('rep_kpi_top_vendedores') ?> #<?= $ranking_pos ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rep-kpi">
                    <div class="rep-kpi__icon rep-kpi--purple">🔄</div>
                    <div class="rep-kpi__body">
                        <span class="rep-kpi__label"><?= t('rep_kpi_conversion') ?></span>
                        <span class="rep-kpi__value"><?= $kpi_v['conversion'] ?>%</span>
                    </div>
                </div>

            </div><!-- /kpi-grid vendedor -->

            <!-- Gráfica de ventas en métricas -->
            <div class="rep-card" style="margin-top:1.5rem">
                <div class="rep-card__header">
                    <h3><?= t('rep_graf_ventas_titulo') ?></h3>
                </div>
                <div class="rep-card__body">
                    <div class="rep-legend">
                        <span><span class="rep-legend-box"
                                style="background:#1D9E75"></span><?= t('rep_graf_mes_actual') ?></span>
                        <span><span class="rep-legend-box"
                                style="background:#94a3b8;border:1px dashed #64748b"></span><?= t('rep_graf_mes_anterior') ?></span>
                    </div>
                    <div style="position:relative;width:100%;height:220px">
                        <canvas id="chartVentas" role="img" aria-label="<?= t('rep_graf_ventas_titulo') ?>">
                        </canvas>
                    </div>
                </div>
            </div>

            <?php elseif ($es_comprador): ?>
            <!-- KPIs comprador -->
            <div class="rep-kpi-grid">

                <div class="rep-kpi">
                    <div class="rep-kpi__icon rep-kpi--blue">🛒</div>
                    <div class="rep-kpi__body">
                        <span class="rep-kpi__label"><?= t('rep_kpi_gastado_mes') ?></span>
                        <span class="rep-kpi__value">
                            $<?= number_format($kpi_c['gastado_mes'], 0, ',', '.') ?>
                        </span>
                        <span
                            class="rep-kpi__delta <?= str_starts_with($delta_ingresos, '+') ? 'delta-up' : 'delta-down' ?>">
                            <?= $delta_ingresos ?> <?= t('rep_kpi_vs_mes_anterior') ?>
                        </span>
                    </div>
                </div>

                <div class="rep-kpi">
                    <div class="rep-kpi__icon rep-kpi--green">📋</div>
                    <div class="rep-kpi__body">
                        <span class="rep-kpi__label"><?= t('rep_kpi_pedidos') ?></span>
                        <span class="rep-kpi__value"><?= $kpi_c['pedidos_mes'] ?></span>
                        <span class="rep-kpi__delta delta-up">
                            <?= $kpi_c['pedidos_completados'] ?> <?= t('rep_kpi_pedidos_completados') ?>
                        </span>
                    </div>
                </div>

                <div class="rep-kpi">
                    <div class="rep-kpi__icon rep-kpi--amber">🤝</div>
                    <div class="rep-kpi__body">
                        <span class="rep-kpi__label"><?= t('rep_kpi_vendedores_fav') ?></span>
                        <span class="rep-kpi__value"><?= $kpi_c['vendedores_fav'] ?></span>
                    </div>
                </div>

                <div class="rep-kpi">
                    <div class="rep-kpi__icon rep-kpi--purple">🏷️</div>
                    <div class="rep-kpi__body">
                        <span class="rep-kpi__label"><?= t('rep_kpi_categorias') ?></span>
                        <span class="rep-kpi__value"><?= $kpi_c['categorias'] ?></span>
                    </div>
                </div>

            </div><!-- /kpi-grid comprador -->
            <?php endif; ?>

        </div><!-- /tab-metricas -->

        <!-- ════════════════════════════════════════════════════
             TAB: GRÁFICAS
        ════════════════════════════════════════════════════ -->
        <div id="tab-graficas" class="rep-tab-panel">
            <div class="rep-grid-2">

                <?php if ($es_vendedor): ?>
                <!-- Productos más / menos vendidos -->
                <div class="rep-card">
                    <div class="rep-card__header">
                        <h3><?= t('rep_graf_productos_titulo') ?></h3>
                    </div>
                    <div class="rep-card__body">
                        <div class="rep-legend">
                            <span><span class="rep-legend-box"
                                    style="background:#1D9E75"></span><?= t('rep_graf_mas_vendidos') ?></span>
                            <span><span class="rep-legend-box"
                                    style="background:#F09595"></span><?= t('rep_graf_menos_vendidos') ?></span>
                        </div>
                        <div style="position:relative;width:100%;height:240px">
                            <canvas id="chartProductos" role="img" aria-label="<?= t('rep_graf_productos_titulo') ?>">
                            </canvas>
                        </div>
                    </div>
                </div>

                <!-- Distribución por categoría (dona) -->
                <div class="rep-card">
                    <div class="rep-card__header">
                        <h3><?= t('rep_graf_categorias_titulo') ?></h3>
                    </div>
                    <div class="rep-card__body">
                        <div id="legendCategorias" class="rep-legend" style="flex-wrap:wrap"></div>
                        <div style="position:relative;width:100%;height:220px">
                            <canvas id="chartCategorias" role="img" aria-label="<?= t('rep_graf_categorias_titulo') ?>">
                            </canvas>
                        </div>
                    </div>
                </div>

                <!-- Precio vs cantidad vendida (scatter) -->
                <div class="rep-card">
                    <div class="rep-card__header">
                        <h3><?= t('rep_graf_precio_titulo') ?></h3>
                        <span class="rep-card__sub"><?= t('rep_graf_precio_sub') ?></span>
                    </div>
                    <div class="rep-card__body">
                        <div style="position:relative;width:100%;height:220px">
                            <canvas id="chartPrecio" role="img" aria-label="<?= t('rep_graf_precio_titulo') ?>">
                            </canvas>
                        </div>
                    </div>
                </div>

                <!-- Funnel de conversión -->
                <div class="rep-card">
                    <div class="rep-card__header">
                        <h3><?= t('rep_graf_funnel_titulo') ?></h3>
                    </div>
                    <div class="rep-card__body">
                        <div style="position:relative;width:100%;height:220px">
                            <canvas id="chartFunnel" role="img" aria-label="<?= t('rep_graf_funnel_titulo') ?>">
                            </canvas>
                        </div>
                    </div>
                </div>

                <?php elseif ($es_comprador): ?>
                <!-- Compras por categoría -->
                <div class="rep-card">
                    <div class="rep-card__header">
                        <h3><?= t('rep_graf_compras_titulo') ?></h3>
                    </div>
                    <div class="rep-card__body">
                        <div id="legendCompras" class="rep-legend" style="flex-wrap:wrap"></div>
                        <div style="position:relative;width:100%;height:220px">
                            <canvas id="chartComprasCat" role="img" aria-label="<?= t('rep_graf_compras_titulo') ?>">
                            </canvas>
                        </div>
                    </div>
                </div>

                <!-- Gasto mensual -->
                <div class="rep-card">
                    <div class="rep-card__header">
                        <h3><?= t('rep_graf_gasto_titulo') ?></h3>
                    </div>
                    <div class="rep-card__body">
                        <div style="position:relative;width:100%;height:220px">
                            <canvas id="chartGasto" role="img" aria-label="<?= t('rep_graf_gasto_titulo') ?>">
                            </canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div><!-- /tab-graficas -->

        <!-- ════════════════════════════════════════════════════
             TAB: RECOMENDACIONES (solo vendedor)
        ════════════════════════════════════════════════════ -->
        <?php if ($es_vendedor): ?>
        <div id="tab-recomendaciones" class="rep-tab-panel">
            <h3 class="rep-section-title">💡 <?= t('rep_rec_titulo') ?></h3>

            <?php if (empty($recomendaciones)): ?>
            <div class="rep-empty">
                <p><?= t('rep_rec_sin_datos') ?></p>
            </div>
            <?php else: ?>
            <div class="rep-rec-list">
                <?php foreach ($recomendaciones as $rec): ?>
                <div class="rep-rec-item rep-rec--<?= $rec['tipo'] ?>">
                    <div class="rep-rec__icon">
                        <?= $rec['tipo'] === 'success' ? '✅' : ($rec['tipo'] === 'danger' ? '🔴' : '⚠️') ?>
                    </div>
                    <div class="rep-rec__body">
                        <div class="rep-rec__titulo"><?= $rec['titulo'] ?></div>
                        <?php if (!empty($rec['cuerpo'])): ?>
                        <div class="rep-rec__cuerpo"><?= $rec['cuerpo'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div><!-- /tab-recomendaciones -->

        <!-- ════════════════════════════════════════════════════
             TAB: VISITAS (solo vendedor)
        ════════════════════════════════════════════════════ -->
        <div id="tab-visitas" class="rep-tab-panel">

            <!-- Alerta visitante frecuente -->
            <?php if ($alerta_visitante): ?>
            <div class="rep-alert rep-alert--warning">
                👀 <?= $alerta_visitante ?>
            </div>
            <?php endif; ?>

            <!-- Gráfica visitas al perfil -->
            <div class="rep-card" style="margin-bottom:1.5rem">
                <div class="rep-card__header">
                    <h3><?= t('rep_vis_titulo_perfil') ?></h3>
                    <a href="/ascc/visitas_detalle.php?tipo=perfil" class="rep-btn-primary rep-btn-primary--sm" style="text-decoration:none">
                        👁️ <?= t('vis_ver_perfil') ?>
                    </a>
                </div>
                <div class="rep-card__body">
                    <div style="position:relative;width:100%;height:180px">
                        <canvas id="chartVisitasPerfil" role="img" aria-label="<?= t('rep_vis_titulo_perfil') ?>">
                        </canvas>
                    </div>
                </div>
            </div>

            <!-- Visitantes recientes -->
            <?php if (!empty($visitantes_recientes)): ?>
            <div class="rep-card" style="margin-bottom:1.5rem">
                <div class="rep-card__header">
                    <h3><?= t('rep_vis_visitantes_rec') ?></h3>
                    <span class="rep-card__meta"><?= t('rep_vis_ultimas_24h') ?></span>
                </div>
                <div class="rep-card__body rep-card__body--flush">
                    <?php foreach ($visitantes_recientes as $v): ?>
                    <div class="rep-visit-row">
                        <div class="rep-visit-avatar">
                            <?= strtoupper(substr($v['nombre'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div class="rep-visit-name">
                            <?= htmlspecialchars($v['nombre'] ?? t('rep_vis_sin_visitas')) ?>
                            <span class="rep-visit-role">(<?= htmlspecialchars($v['rol'] ?? '') ?>)</span>
                        </div>
                        <div class="rep-visit-time">
                            <?= tiempoRelativo($v['fecha_visita'], isset($v['segundos_ago']) ? (int)$v['segundos_ago'] : null) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tabla productos por visitas -->
            <div class="rep-card">
                <div class="rep-card__header">
                    <h3><?= t('rep_vis_titulo_productos') ?></h3>
                </div>
                <div class="rep-card__body rep-card__body--flush">
                    <?php if (empty($productos_por_visitas)): ?>
                    <p class="rep-empty-text"><?= t('rep_vis_sin_visitas') ?></p>
                    <?php else: ?>
                    <div class="rep-table-wrap">
                        <table class="rep-table">
                            <thead>
                                <tr>
                                    <th><?= t('rep_vis_col_producto') ?></th>
                                    <th><?= t('rep_vis_col_visitas') ?></th>
                                    <th><?= t('rep_vis_col_contactos') ?></th>
                                    <th><?= t('rep_vis_col_ventas') ?></th>
                                    <th><?= t('rep_vis_col_conversion') ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos_por_visitas as $pv):
                                            $vis = (int)$pv['visitas'];
                                            $ven = (int)$pv['ventas'];
                                            $conv = $vis > 0 ? round(($ven / $vis) * 100) : 0;
                                            $badge = $conv >= 20 ? 'badge-success' : ($conv >= 10 ? 'badge-warning' : 'badge-muted');
                                        ?>
                                <tr>
                                    <td><?= htmlspecialchars($pv['tipo_producto']) ?></td>
                                    <td><?= number_format($vis) ?></td>
                                    <td><?= number_format((int)$pv['contactos']) ?></td>
                                    <td><?= number_format($ven) ?></td>
                                    <td><span class="rep-badge <?= $badge ?>"><?= $conv ?>%</span></td>
                                    <td>
                                        <a href="/ascc/visitas_detalle.php?tipo=producto&id=<?= $pv['id_producto'] ?>"
                                            class="rep-badge badge-info" style="text-decoration:none">
                                            👁️
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /tab-visitas -->

        <!-- ════════════════════════════════════════════════════
             TAB: RANKING (solo vendedor)
        ════════════════════════════════════════════════════ -->
        <div id="tab-ranking" class="rep-tab-panel">

            <!-- Mi posición -->
            <?php if ($ranking_pos > 0): ?>
            <div class="rep-mi-posicion">
                <div class="rep-pos-num">#<?= $ranking_pos ?></div>
                <div>
                    <div class="rep-pos-label"><?= t('rep_rank_mi_posicion') ?></div>
                    <?php if ($ranking_pos > 20): ?>
                    <div class="rep-pos-sub">
                        <?= t('rep_rank_necesitas') ?>
                        <?= max(0, $ranking_pos - 20) ?>
                        <?= t('rep_rank_resenas_mas') ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="rep-empty">
                <p><?= t('rep_rank_sin_datos') ?></p>
            </div>
            <?php endif; ?>

            <!-- Gráfica evolución calificación -->
            <div class="rep-card" style="margin-bottom:1.5rem">
                <div class="rep-card__header">
                    <h3><?= t('rep_rank_evolucion') ?></h3>
                </div>
                <div class="rep-card__body">
                    <div style="position:relative;width:100%;height:180px">
                        <canvas id="chartEvolucion" role="img" aria-label="<?= t('rep_rank_evolucion') ?>">
                        </canvas>
                    </div>
                </div>
            </div>

            <!-- Top vendedores -->
            <?php if (!empty($ranking_top)): ?>
            <div class="rep-card">
                <div class="rep-card__header">
                    <h3>🏆 <?= t('rep_rank_top_vendedores') ?></h3>
                </div>
                <div class="rep-card__body">
                    <?php foreach ($ranking_top as $idx => $rv): ?>
                    <div class="rep-rank-row">
                        <div class="rep-rank-num <?= $idx < 3 ? 'rep-rank-num--top' : '' ?>">
                            <?= $idx + 1 ?>
                        </div>
                        <div class="rep-rank-avatar">
                            <?= strtoupper(substr($rv['nombre'], 0, 1)) ?>
                        </div>
                        <div class="rep-rank-info">
                            <div class="rep-rank-name"><?= htmlspecialchars($rv['nombre']) ?></div>
                            <div class="rep-rank-stars">
                                <?= number_format($rv['prom'], 1) ?> ⭐
                                — <?= $rv['total_res'] ?> <?= t('rep_rank_resenas') ?>
                            </div>
                            <div class="rep-stars-bar">
                                <div class="rep-stars-fill" style="width:<?= round(($rv['prom'] / 5) * 100) ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /tab-ranking -->
        <?php endif; // es_vendedor 
        ?>

        <!-- ════════════════════════════════════════════════════
             TAB: DENUNCIAS
        ════════════════════════════════════════════════════ -->
        <div id="tab-denuncias" class="rep-tab-panel">

            <div class="rep-den-header">
                <h3 class="rep-section-title">🚨 <?= t('rep_den_titulo') ?></h3>
                <button class="rep-btn-primary" id="btnNuevaDenuncia">
                    + <?= t('rep_den_btn_nueva') ?>
                </button>
            </div>

            <!-- Semáforo de cuenta -->
            <div class="rep-semaforo rep-semaforo--<?= $semaforo ?>">
                <?= $semaforo === 'verde'
                    ? t('rep_den_semaforo_verde')
                    : ($semaforo === 'amarillo'
                        ? t('rep_den_semaforo_amarillo')
                        : t('rep_den_semaforo_rojo')) ?>
            </div>

            <!-- Tabla de denuncias enviadas -->
            <?php if (empty($mis_denuncias)): ?>
            <div class="rep-empty">
                <p><?= t('rep_den_sin_denuncias') ?></p>
            </div>
            <?php else: ?>
            <div class="rep-card" style="margin-top:1rem">
                <div class="rep-card__body rep-card__body--flush">
                    <div class="rep-table-wrap">
                        <table class="rep-table">
                            <thead>
                                <tr>
                                    <th><?= t('rep_den_col_ticket') ?></th>
                                    <th><?= t('rep_den_col_motivo') ?></th>
                                    <th><?= t('rep_den_col_fecha') ?></th>
                                    <th><?= t('rep_den_col_estado') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mis_denuncias as $den): ?>
                                <tr>
                                    <td>#DEN-<?= str_pad($den['id_reporte'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= $cat_labels[$den['categoria']] ?? $den['categoria'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($den['fecha_creacion'])) ?></td>
                                    <td>
                                        <span class="rep-badge <?= $estado_badge[$den['estado']] ?? 'badge-muted' ?>">
                                            <?= $estado_labels[$den['estado']] ?? $den['estado'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /tab-denuncias -->

        <!-- ════════════════════════════════════════════════════
             TAB: EXPORTAR
        ════════════════════════════════════════════════════ -->
        <div id="tab-exportar" class="rep-tab-panel">

            <h3 class="rep-section-title">📥 <?= t('rep_exp_titulo') ?></h3>
            <p class="rep-section-sub"><?= t('rep_exp_subtitulo') ?></p>

            <!-- ── SELECCIÓN DE SECCIONES ── -->
            <div class="rep-card" style="margin-bottom:1.5rem">
                <div class="rep-card__header">
                    <h3>☑️ Selecciona qué exportar</h3>
                </div>
                <div class="rep-card__body">
                    <div class="rep-check-grid">

                        <?php if (in_array($rol, ['vendedor', 'mixto'])): ?>
                        <label class="rep-check-item">
                            <input type="checkbox" id="chk_ventas" value="ventas" checked>
                            <div class="rep-check-body">
                                <span class="rep-check-icon">💰</span>
                                <div>
                                    <div class="rep-check-title"><?= t('rep_exp_ventas') ?></div>
                                    <div class="rep-check-sub">Fecha, producto, comprador, total, estado</div>
                                </div>
                            </div>
                        </label>
                        <?php endif; ?>

                        <?php if (in_array($rol, ['comprador', 'mixto'])): ?>
                        <label class="rep-check-item">
                            <input type="checkbox" id="chk_compras" value="compras" checked>
                            <div class="rep-check-body">
                                <span class="rep-check-icon">🛒</span>
                                <div>
                                    <div class="rep-check-title">Mis Compras</div>
                                    <div class="rep-check-sub">Fecha, producto, vendedor, total, estado</div>
                                </div>
                            </div>
                        </label>
                        <?php endif; ?>

                        <?php if (in_array($rol, ['vendedor', 'mixto'])): ?>
                        <label class="rep-check-item">
                            <input type="checkbox" id="chk_productos" value="productos" checked>
                            <div class="rep-check-body">
                                <span class="rep-check-icon">📦</span>
                                <div>
                                    <div class="rep-check-title"><?= t('rep_exp_productos') ?></div>
                                    <div class="rep-check-sub">Código, precio, cantidad, estado, ubicación</div>
                                </div>
                            </div>
                        </label>

                        <label class="rep-check-item">
                            <input type="checkbox" id="chk_visitas" value="visitas" checked>
                            <div class="rep-check-body">
                                <span class="rep-check-icon">👁️</span>
                                <div>
                                    <div class="rep-check-title"><?= t('rep_exp_visitas') ?></div>
                                    <div class="rep-check-sub">Visitas a productos y perfil — últimos 30 días</div>
                                </div>
                            </div>
                        </label>
                        <?php endif; ?>

                        <label class="rep-check-item">
                            <input type="checkbox" id="chk_valoraciones" value="valoraciones" checked>
                            <div class="rep-check-body">
                                <span class="rep-check-icon">⭐</span>
                                <div>
                                    <div class="rep-check-title"><?= t('rep_exp_valoraciones') ?></div>
                                    <div class="rep-check-sub">Calificaciones, comentarios, fechas</div>
                                </div>
                            </div>
                        </label>

                    </div><!-- /rep-check-grid -->
                </div>
            </div>

            <!-- ── BOTONES DE DESCARGA ── -->
            <div class="rep-export-btns">
                <button class="rep-btn-export" id="btnExcelCompleto">
                    📊 <?= t('rep_exp_excel') ?>
                    <span style="font-size:0.7rem;opacity:0.8;display:block">Abre en Excel con formato</span>
                </button>
                <button class="rep-btn-export" id="btnCsvPowerBi">
                    📄 <?= t('rep_exp_csv') ?>
                    <span style="font-size:0.7rem;opacity:0.8;display:block">Para importar en Power BI</span>
                </button>
                <button class="rep-btn-export" id="btnGenerarToken">
                    🔑 <?= $api_token ? 'Ver mi token API' : t('rep_exp_token') ?>
                    <span style="font-size:0.7rem;opacity:0.8;display:block">Conexión automática Power BI</span>
                </button>
            </div>

            <!-- ── TOKEN API ── -->
            <div class="rep-token-box" id="tokenBox" style="<?= $api_url ? '' : 'display:none' ?>">
                <p class="rep-token-hint">
                    🔗 <?= t('rep_exp_token_hint') ?><br>
                    <small style="opacity:0.7">Power BI Desktop → Obtener datos → Web → pega esta URL</small>
                </p>
                <div class="rep-token-url">
                    <code id="tokenUrl"><?= htmlspecialchars($api_url) ?></code>
                    <button class="rep-btn-copy" onclick="copiarToken()" title="Copiar URL">📋</button>
                </div>
            </div>

            <!-- ── EXPLICACIÓN POWER BI ── -->
            <div class="rep-card" style="margin-top:1.5rem">
                <div class="rep-card__header">
                    <h3>📊 ¿Qué es Power BI y cómo usarlo?</h3>
                </div>
                <div class="rep-card__body">
                    <div class="rep-powerbi-grid">

                        <div class="rep-powerbi-paso">
                            <div class="rep-powerbi-num">1</div>
                            <div>
                                <div class="rep-powerbi-titulo">Descarga Power BI Desktop</div>
                                <div class="rep-powerbi-desc">Es gratis. Descárgalo desde
                                    <a href="https://www.microsoft.com/es-es/power-platform/products/power-bi/downloads"
                                        target="_blank" style="color:#10b981">powerbi.microsoft.com</a>
                                </div>
                            </div>
                        </div>

                        <div class="rep-powerbi-paso">
                            <div class="rep-powerbi-num">2</div>
                            <div>
                                <div class="rep-powerbi-titulo">Genera tu token API</div>
                                <div class="rep-powerbi-desc">Haz clic en "Generar token API" arriba. Se crea una URL
                                    privada solo tuya.</div>
                            </div>
                        </div>

                        <div class="rep-powerbi-paso">
                            <div class="rep-powerbi-num">3</div>
                            <div>
                                <div class="rep-powerbi-titulo">Conecta en Power BI</div>
                                <div class="rep-powerbi-desc">Abre Power BI → Obtener datos → Web → pega tu URL del
                                    token.</div>
                            </div>
                        </div>

                        <div class="rep-powerbi-paso">
                            <div class="rep-powerbi-num">4</div>
                            <div>
                                <div class="rep-powerbi-titulo">Actualización automática</div>
                                <div class="rep-powerbi-desc">Power BI consulta tus datos en tiempo real cada vez que
                                    abres el reporte.</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div><!-- /tab-exportar -->

    </div><!-- /rep-container -->

    <!-- ══ MODAL: NUEVA DENUNCIA ══════════════════════════════ -->
    <div class="rep-modal-backdrop" id="modalDenBackdrop"></div>
    <div class="rep-modal" id="modalDenuncia" role="dialog" aria-modal="true">

        <div class="rep-modal__header">
            <h2>🚨 <?= t('rep_den_modal_titulo') ?></h2>
            <button class="rep-modal__close" id="btnCerrarDenuncia">✕</button>
        </div>

        <form id="formDenuncia" class="rep-modal__body">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="rep-form-field">
                <label class="rep-form-label">
                    <?= t('rep_den_tipo_label') ?> <span class="rep-required">*</span>
                </label>
                <div class="rep-tipo-grid">
                    <label class="rep-tipo-card">
                        <input type="radio" name="tipo_denuncia" value="producto" checked>
                        <span>📦 <?= t('rep_den_tipo_producto') ?></span>
                    </label>
                    <label class="rep-tipo-card">
                        <input type="radio" name="tipo_denuncia" value="vendedor">
                        <span>👤 <?= t('rep_den_tipo_vendedor') ?></span>
                    </label>
                    <label class="rep-tipo-card">
                        <input type="radio" name="tipo_denuncia" value="resena">
                        <span>⭐ <?= t('rep_den_tipo_resena') ?></span>
                    </label>
                </div>
            </div>

            <div class="rep-form-field">
                <label class="rep-form-label" for="den_categoria">
                    <?= t('rep_den_categoria_label') ?> <span class="rep-required">*</span>
                </label>
                <select id="den_categoria" name="categoria" class="rep-form-select">
                    <option value="no_entregado"><?= t('rep_den_cat_no_entregado') ?></option>
                    <option value="descripcion_enganosa"><?= t('rep_den_cat_desc_enganosa') ?></option>
                    <option value="precio_diferente"><?= t('rep_den_cat_precio_dif') ?></option>
                    <option value="mala_calidad"><?= t('rep_den_cat_mala_calidad') ?></option>
                    <option value="vendedor_no_responde"><?= t('rep_den_cat_no_responde') ?></option>
                    <option value="resena_falsa"><?= t('rep_den_cat_resena_falsa') ?></option>
                    <option value="lenguaje_inapropiado"><?= t('rep_den_cat_lenguaje') ?></option>
                    <option value="otro"><?= t('rep_den_cat_otro') ?></option>
                </select>
            </div>

            <div class="rep-form-field">
                <label class="rep-form-label" for="den_descripcion">
                    <?= t('rep_den_descripcion_label') ?> <span class="rep-required">*</span>
                </label>
                <textarea id="den_descripcion" name="descripcion" class="rep-form-textarea" rows="4"
                    placeholder="<?= t('rep_den_descripcion_ph') ?>" maxlength="1000"></textarea>
            </div>

            <div id="denFeedback" class="rep-feedback" style="display:none"></div>

        </form>

        <div class="rep-modal__footer">
            <button type="button" class="rep-btn-secondary" id="btnCerrarDenuncia2">
                <?= t('cancel') ?>
            </button>
            <button type="button" class="rep-btn-primary" id="btnEnviarDenuncia">
                🚨 <?= t('rep_den_btn_enviar') ?>
            </button>
        </div>

    </div><!-- /modal denuncia -->

    <!-- ══ TOAST ══════════════════════════════════════════════ -->
    <div class="rep-toast" id="repToast"></div>

    <!-- Scripts -->
    <script src="/ascc/public/js/reportes.js?v=<?= time() ?>"></script>

</body>

</html>