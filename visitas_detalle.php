<?php

/**
 * ASCC — Detalle de Visitas
 * Ruta: visitas_detalle.php
 *
 * Acceso:
 *   ?tipo=producto&id=X  → quién vio el producto X
 *   ?tipo=perfil         → quién visitó mi perfil
 *
 * Seguridad:
 *   - Solo vendedores y mixtos logueados
 *   - El producto debe pertenecer al usuario logueado
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// ── Autenticación ─────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ascc/views/auth/login.php?redirect=visitas_detalle');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

// ── Verificar rol ─────────────────────────────────────────────
$stmtU = $conexion->prepare(
    "SELECT nombre, rol, foto_perfil FROM usuarios WHERE id_usuario = :id LIMIT 1"
);
$stmtU->execute([':id' => $id_usuario]);
$usuario = $stmtU->fetch();

if (!$usuario || !in_array($usuario['rol'], ['vendedor', 'mixto'], true)) {
    header('Location: /ascc/reportes.php');
    exit;
}

// ── Parámetros ────────────────────────────────────────────────
$tipo       = $_GET['tipo'] ?? 'perfil';
$id_item    = (int)($_GET['id'] ?? 0);
$filtro     = $_GET['filtro'] ?? 'todos'; // todos | logueados | anonimos
$pagina     = max(1, (int)($_GET['p'] ?? 1));
$por_pagina = 20;
$offset     = ($pagina - 1) * $por_pagina;

if (!in_array($tipo, ['producto', 'perfil'], true)) {
    header('Location: /ascc/reportes.php');
    exit;
}

// ── Validar que el producto pertenece al usuario ──────────────
$producto_info = null;
if ($tipo === 'producto') {
    if ($id_item <= 0) {
        header('Location: /ascc/reportes.php');
        exit;
    }
    $stmtP = $conexion->prepare(
        "SELECT p.id_producto, p.tipo_producto, p.precio, p.unidad,
                p.cantidad, p.estado, p.fecha_publicacion,
                (SELECT ruta_imagen FROM imagenes_productos
                 WHERE id_producto = p.id_producto LIMIT 1) AS imagen
         FROM productos p
         WHERE p.id_producto = :id AND p.id_usuario = :uid
         LIMIT 1"
    );
    $stmtP->execute([':id' => $id_item, ':uid' => $id_usuario]);
    $producto_info = $stmtP->fetch();

    if (!$producto_info) {
        header('Location: /ascc/reportes.php');
        exit;
    }
}

// ── KPIs de visitas ───────────────────────────────────────────
$kpis = ['total' => 0, 'unicas' => 0, 'hoy' => 0, 'semana' => 0];

if ($tipo === 'producto') {
    $where_kpi = "WHERE id_producto = :id";
    $params_kpi = [':id' => $id_item];
} else {
    $where_kpi  = "WHERE id_vendedor = :id";
    $params_kpi = [':id' => $id_usuario];
}

$tabla_vis = $tipo === 'producto' ? 'visitas_producto' : 'visitas_perfil';

// Total
$r = $conexion->prepare("SELECT COUNT(*) FROM {$tabla_vis} {$where_kpi}");
$r->execute($params_kpi);
$kpis['total'] = (int)$r->fetchColumn();

// Únicas (por sesion_id)
$r = $conexion->prepare("SELECT COUNT(DISTINCT sesion_id) FROM {$tabla_vis} {$where_kpi}");
$r->execute($params_kpi);
$kpis['unicas'] = (int)$r->fetchColumn();

// Hoy
$r = $conexion->prepare(
    "SELECT COUNT(*) FROM {$tabla_vis} {$where_kpi} AND DATE(fecha_visita) = CURDATE()"
);
$r->execute($params_kpi);
$kpis['hoy'] = (int)$r->fetchColumn();

// Esta semana
$r = $conexion->prepare(
    "SELECT COUNT(*) FROM {$tabla_vis} {$where_kpi}
     AND fecha_visita >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$r->execute($params_kpi);
$kpis['semana'] = (int)$r->fetchColumn();

// ── Visitantes frecuentes (top 5 con más visitas) ─────────────
$frecuentes = [];
if ($tipo === 'producto') {
    $r = $conexion->prepare(
        "SELECT u.id_usuario, u.nombre, u.rol, u.foto_perfil,
                COUNT(*) AS total_visitas,
                MAX(vp.fecha_visita) AS ultima_visita
         FROM visitas_producto vp
         INNER JOIN usuarios u ON vp.id_visitante = u.id_usuario
         WHERE vp.id_producto = :id
         GROUP BY vp.id_visitante
         ORDER BY total_visitas DESC
         LIMIT 5"
    );
    $r->execute([':id' => $id_item]);
} else {
    $r = $conexion->prepare(
        "SELECT u.id_usuario, u.nombre, u.rol, u.foto_perfil,
                COUNT(*) AS total_visitas,
                MAX(vp.fecha_visita) AS ultima_visita
         FROM visitas_perfil vp
         INNER JOIN usuarios u ON vp.id_visitante = u.id_usuario
         WHERE vp.id_vendedor = :id
         GROUP BY vp.id_visitante
         ORDER BY total_visitas DESC
         LIMIT 5"
    );
    $r->execute([':id' => $id_usuario]);
}
$frecuentes = $r->fetchAll();

// ── Lista principal de visitas con filtro y paginación ─────────
$filtro_sql  = '';
$params_list = $params_kpi;

if ($filtro === 'logueados') {
    $filtro_sql = ' AND id_visitante IS NOT NULL';
} elseif ($filtro === 'anonimos') {
    $filtro_sql = ' AND id_visitante IS NULL';
}

// Total para paginación
$r = $conexion->prepare(
    "SELECT COUNT(*) FROM {$tabla_vis} {$where_kpi} {$filtro_sql}"
);
$r->execute($params_list);
$total_registros = (int)$r->fetchColumn();
$total_paginas   = max(1, (int)ceil($total_registros / $por_pagina));

// Registros de la página actual
if ($tipo === 'producto') {
    $r = $conexion->prepare(
        "SELECT
             vp.id, vp.fecha_visita, vp.origen, vp.ip_visitante,
             u.id_usuario, u.nombre, u.rol, u.foto_perfil,
             DATE_FORMAT(vp.fecha_visita, '%d/%m/%Y %H:%i') AS fecha_fmt,
             TIMESTAMPDIFF(SECOND, vp.fecha_visita, NOW())  AS segundos_ago
         FROM visitas_producto vp
         LEFT JOIN usuarios u ON vp.id_visitante = u.id_usuario
         WHERE vp.id_producto = :id {$filtro_sql}
         ORDER BY vp.fecha_visita DESC
         LIMIT :limit OFFSET :offset"
    );
    $r->bindValue(':id',     $id_item,    PDO::PARAM_INT);
} else {
    $r = $conexion->prepare(
        "SELECT
             vp.id, vp.fecha_visita, vp.ip_visitante,
             u.id_usuario, u.nombre, u.rol, u.foto_perfil,
             DATE_FORMAT(vp.fecha_visita, '%d/%m/%Y %H:%i') AS fecha_fmt,
             TIMESTAMPDIFF(SECOND, vp.fecha_visita, NOW())  AS segundos_ago
         FROM visitas_perfil vp
         LEFT JOIN usuarios u ON vp.id_visitante = u.id_usuario
         WHERE vp.id_vendedor = :id {$filtro_sql}
         ORDER BY vp.fecha_visita DESC
         LIMIT :limit OFFSET :offset"
    );
    $r->bindValue(':id', $id_usuario, PDO::PARAM_INT);
}
$r->bindValue(':limit',  $por_pagina, PDO::PARAM_INT);
$r->bindValue(':offset', $offset,     PDO::PARAM_INT);
$r->execute();
$visitas = $r->fetchAll();

// ── Etiquetas de origen ───────────────────────────────────────
$origen_labels = [
    'catalogo' => t('vis_origen_catalogo'),
    'busqueda' => t('vis_origen_busqueda'),
    'perfil'   => t('vis_origen_perfil'),
    'directo'  => t('vis_origen_directo'),
];

// ── Para visitas de perfil — obtener primer producto activo del vendedor
// Se usa como contexto al enviar mensaje desde esta página
$primer_producto_id = 0;
if ($tipo === 'perfil') {
    $r = $conexion->prepare(
        "SELECT id_producto FROM productos
         WHERE id_usuario = :uid AND estado = 'disponible'
         ORDER BY fecha_publicacion DESC LIMIT 1"
    );
    $r->execute([':uid' => $id_usuario]);
    $primer_producto_id = (int)($r->fetchColumn() ?: 0);
}

// ── Página título ─────────────────────────────────────────────
$page_title = $tipo === 'producto'
    ? t('vis_page_title_producto') . ' — ' . htmlspecialchars($producto_info['tipo_producto'])
    : t('vis_page_title_perfil');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — <?= t('app_name') ?></title>
    <link rel="icon" type="image/png" href="/ascc/public/img/logo.png">
    <?= ascc_theme_css() ?>
    <link rel="stylesheet" href="/ascc/public/css/visitas-detalle.css?v=<?= time() ?>">
</head>

<body class="theme-<?= $theme ?>" data-theme="<?= $theme ?>">

    <div class="vis-container">

        <!-- ── CABECERA ──────────────────────────────────────── -->
        <div class="vis-header">
            <a href="/ascc/reportes.php#visitas" class="vis-back">
                <?= t('vis_back_reportes') ?>
            </a>

            <div class="vis-header__info">
                <?php if ($tipo === 'producto' && $producto_info): ?>
                    <div class="vis-producto-info">
                        <img src="<?= htmlspecialchars(getImageUrl($producto_info['imagen'])) ?>"
                            alt="<?= htmlspecialchars($producto_info['tipo_producto']) ?>" class="vis-producto-img"
                            onerror="this.src='/ascc/public/img/no-image.png'">
                        <div>
                            <h1 class="vis-title">
                                👁️ <?= t('vis_page_title_producto') ?>
                            </h1>
                            <p class="vis-subtitle">
                                <?= htmlspecialchars($producto_info['tipo_producto']) ?>
                                — $<?= number_format($producto_info['precio'], 0, ',', '.') ?>
                                / <?= htmlspecialchars($producto_info['unidad']) ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <h1 class="vis-title">👁️ <?= t('vis_page_title_perfil') ?></h1>
                    <p class="vis-subtitle">
                        <?= htmlspecialchars($usuario['nombre']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── KPIs ──────────────────────────────────────────── -->
        <div class="vis-kpi-grid">
            <div class="vis-kpi">
                <span class="vis-kpi__label"><?= t('vis_total_visitas') ?></span>
                <span class="vis-kpi__value"><?= number_format($kpis['total']) ?></span>
            </div>
            <div class="vis-kpi">
                <span class="vis-kpi__label"><?= t('vis_visitas_unicas') ?></span>
                <span class="vis-kpi__value"><?= number_format($kpis['unicas']) ?></span>
            </div>
            <div class="vis-kpi">
                <span class="vis-kpi__label"><?= t('vis_visitas_hoy') ?></span>
                <span class="vis-kpi__value"><?= number_format($kpis['hoy']) ?></span>
            </div>
            <div class="vis-kpi">
                <span class="vis-kpi__label"><?= t('vis_visitas_semana') ?></span>
                <span class="vis-kpi__value"><?= number_format($kpis['semana']) ?></span>
            </div>
        </div>

        <!-- ── VISITANTES FRECUENTES ─────────────────────────── -->
        <?php if (!empty($frecuentes)): ?>
            <div class="vis-card" style="margin-bottom:1.5rem">
                <div class="vis-card__header">
                    <h3>🔁 <?= t('vis_frecuentes') ?></h3>
                    <span class="vis-card__sub"><?= t('vis_frecuentes_sub') ?></span>
                </div>
                <div class="vis-frecuentes-grid">
                    <?php foreach ($frecuentes as $f): ?>
                        <div class="vis-frecuente-card">
                            <div class="vis-avatar <?= $f['rol'] === 'comprador' ? 'vis-avatar--blue' : 'vis-avatar--green' ?>">
                                <?= strtoupper(substr($f['nombre'], 0, 1)) ?>
                            </div>
                            <div class="vis-frecuente-info">
                                <div class="vis-frecuente-nombre">
                                    <?php if (in_array($f['rol'], ['comprador', 'mixto'], true)): ?>
                                        <a href="/ascc/perfil_comprador.php?id=<?= $f['id_usuario'] ?>" class="vis-link">
                                            <?= htmlspecialchars($f['nombre']) ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="/ascc/perfil_vendedor.php?id=<?= $f['id_usuario'] ?>" class="vis-link">
                                            <?= htmlspecialchars($f['nombre']) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="vis-frecuente-meta">
                                    <?= $f['total_visitas'] ?> <?= t('vis_col_veces') ?>
                                    · <?= ucfirst($f['rol']) ?>
                                </div>
                            </div>
                            <button class="vis-btn-msg"
                                onclick="abrirModal(<?= $f['id_usuario'] ?>, '<?= addslashes($f['nombre']) ?>', <?= $tipo === 'producto' ? $id_item : 0 ?>)"
                                title="<?= t('vis_enviar_mensaje') ?>">
                                💬
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── FILTROS ────────────────────────────────────────── -->
        <div class="vis-filtros">
            <?php
            $base_url = "visitas_detalle.php?tipo={$tipo}" . ($tipo === 'producto' ? "&id={$id_item}" : '');
            $filtros  = [
                'todos'     => t('vis_filtro_todos'),
                'logueados' => t('vis_filtro_logueados'),
                'anonimos'  => t('vis_filtro_anonimos'),
            ];
            foreach ($filtros as $key => $label):
            ?>
                <a href="<?= $base_url ?>&filtro=<?= $key ?>"
                    class="vis-filtro-btn <?= $filtro === $key ? 'active' : '' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>

            <span class="vis-filtro-total">
                <?= number_format($total_registros) ?> registros
            </span>
        </div>

        <!-- ── TABLA PRINCIPAL ────────────────────────────────── -->
        <div class="vis-card">
            <div class="vis-card__body--flush">
                <?php if (empty($visitas)): ?>
                    <div class="vis-empty">
                        <p><?= $tipo === 'producto'
                                ? t('vis_sin_visitas')
                                : t('vis_sin_visitas_perfil') ?></p>
                    </div>
                <?php else: ?>
                    <div class="vis-table-wrap">
                        <table class="vis-table">
                            <thead>
                                <tr>
                                    <th><?= t('vis_col_visitante') ?></th>
                                    <th><?= t('vis_col_rol') ?></th>
                                    <th><?= t('vis_col_fecha') ?></th>
                                    <?php if ($tipo === 'producto'): ?>
                                        <th><?= t('vis_col_origen') ?></th>
                                    <?php endif; ?>
                                    <th><?= t('vis_col_acciones') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visitas as $v):
                                    $es_logueado  = !empty($v['id_usuario']);
                                    $nombre_vis   = $es_logueado
                                        ? htmlspecialchars($v['nombre'])
                                        : t('vis_anonimo');
                                    $rol_vis      = $es_logueado ? ucfirst($v['rol']) : '—';
                                    $origen_label = $origen_labels[$v['origen'] ?? 'directo']
                                        ?? t('vis_origen_directo');
                                    // Usar fecha formateada por MySQL — evita diferencias de zona horaria
                                    $fecha_fmt    = $v['fecha_fmt'] ?? date('d/m/Y H:i', strtotime($v['fecha_visita']));
                                    $seg_ago      = isset($v['segundos_ago']) ? (int)$v['segundos_ago'] : null;
                                ?>
                                    <tr>
                                        <td>
                                            <div class="vis-visitante-cell">
                                                <div class="vis-avatar vis-avatar--sm
                                        <?= $es_logueado ? 'vis-avatar--green' : 'vis-avatar--muted' ?>">
                                                    <?= $es_logueado
                                                        ? strtoupper(substr($v['nombre'], 0, 1))
                                                        : '?' ?>
                                                </div>
                                                <div>
                                                    <?php if ($es_logueado): ?>
                                                        <?php if (in_array($v['rol'], ['comprador', 'mixto'], true)): ?>
                                                            <a href="/ascc/perfil_comprador.php?id=<?= $v['id_usuario'] ?>"
                                                                class="vis-link">
                                                                <?= $nombre_vis ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="/ascc/perfil_vendedor.php?id=<?= $v['id_usuario'] ?>"
                                                                class="vis-link">
                                                                <?= $nombre_vis ?>
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="vis-anonimo-text"><?= $nombre_vis ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="vis-badge vis-badge--<?= $es_logueado ? 'role' : 'muted' ?>">
                                                <?= $rol_vis ?>
                                            </span>
                                        </td>
                                        <td class="vis-fecha">
                                            <?php
                                            // Tiempo relativo calculado desde MySQL
                                            if ($seg_ago !== null) {
                                                if ($seg_ago < 60)    echo 'Hace un momento';
                                                elseif ($seg_ago < 3600)  echo 'Hace ' . (int)($seg_ago / 60) . ' min';
                                                elseif ($seg_ago < 7200)  echo 'Hace 1 hora';
                                                elseif ($seg_ago < 86400) echo 'Hace ' . (int)($seg_ago / 3600) . ' horas';
                                                else echo $fecha_fmt;
                                            } else {
                                                echo $fecha_fmt;
                                            }
                                            ?>
                                            <small style="display:block;opacity:0.6;font-size:0.7rem">
                                                <?= $fecha_fmt ?>
                                            </small>
                                        </td>
                                        <?php if ($tipo === 'producto'): ?>
                                            <td>
                                                <span class="vis-badge vis-badge--origen">
                                                    <?= $origen_label ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($es_logueado): ?>
                                                <button class="vis-btn-action"
                                                    onclick="abrirModal(<?= $v['id_usuario'] ?>, '<?= addslashes($v['nombre']) ?>', <?= $tipo === 'producto' ? $id_item : 0 ?>)"
                                                    title="<?= t('vis_enviar_mensaje') ?>">
                                                    💬 <?= t('vis_enviar_mensaje') ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="vis-sin-accion">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="vis-paginacion">
                            <?php for ($p = 1; $p <= $total_paginas; $p++): ?>
                                <a href="<?= $base_url ?>&filtro=<?= $filtro ?>&p=<?= $p ?>"
                                    class="vis-pag-btn <?= $p === $pagina ? 'active' : '' ?>">
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>

    </div><!-- /vis-container -->

    <!-- ── MODAL MENSAJE ─────────────────────────────────────── -->
    <div class="vis-modal-backdrop" id="modalBackdrop"></div>
    <div class="vis-modal" id="modalMensaje" role="dialog">
        <div class="vis-modal__header">
            <h2>💬 <?= t('pc_modal_mensaje_titulo') ?></h2>
            <button class="vis-modal__close" onclick="cerrarModal()">✕</button>
        </div>
        <div class="vis-modal__body">
            <p class="vis-modal__dest">
                <?= t('vendor') ?>: <strong id="modalNombre"></strong>
            </p>
            <textarea id="modalTexto" class="vis-modal__textarea" rows="5"
                placeholder="<?= t('write_message') ?>"></textarea>
            <div id="modalFeedback" class="vis-modal__feedback" style="display:none"></div>
        </div>
        <div class="vis-modal__footer">
            <button class="vis-btn-secondary" onclick="cerrarModal()">
                <?= t('cancel') ?>
            </button>
            <button class="vis-btn-primary" id="btnEnviarMsg" onclick="enviarMensaje()">
                ✅ <?= t('send_message') ?>
            </button>
        </div>
    </div>

    <script>
        var destinatarioId = null;
        var productoCtxId = null;
        var csrfToken = '<?= $_SESSION['csrf_token'] ?>';
        var remitente_id = <?= $id_usuario ?>;
        var primerProductoId = <?= $primer_producto_id ?>;

        function abrirModal(userId, nombre, idProducto) {
            destinatarioId = userId;
            // Si no se pasa producto usa el primer producto activo del vendedor
            productoCtxId = (idProducto && idProducto > 0) ? idProducto : primerProductoId;
            document.getElementById('modalNombre').textContent = nombre;
            document.getElementById('modalTexto').value = '';
            document.getElementById('modalFeedback').style.display = 'none';
            document.getElementById('modalBackdrop').classList.add('open');
            document.getElementById('modalMensaje').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal() {
            document.getElementById('modalBackdrop').classList.remove('open');
            document.getElementById('modalMensaje').classList.remove('open');
            document.body.style.overflow = '';
        }

        function enviarMensaje() {
            var texto = document.getElementById('modalTexto').value.trim();
            if (!texto || texto.length < 10) {
                var fb = document.getElementById('modalFeedback');
                fb.textContent = 'El mensaje debe tener al menos 10 caracteres.';
                fb.className = 'vis-modal__feedback vis-feedback--error';
                fb.style.display = 'block';
                return;
            }

            if (!productoCtxId) {
                var fb = document.getElementById('modalFeedback');
                fb.textContent = 'No se puede determinar el producto de contexto.';
                fb.className = 'vis-modal__feedback vis-feedback--error';
                fb.style.display = 'block';
                return;
            }

            var btn = document.getElementById('btnEnviarMsg');
            btn.disabled = true;
            btn.textContent = '⏳';

            fetch('/ascc/controllers/MensajesController.php?action=enviar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id_destinatario: destinatarioId,
                        id_producto: productoCtxId,
                        mensaje: texto
                    })
                })
                .then(r => r.json())
                .then(data => {
                    var fb = document.getElementById('modalFeedback');
                    if (data.success) {
                        fb.textContent = '<?= t('pc_mensaje_enviado') ?>';
                        fb.className = 'vis-modal__feedback vis-feedback--success';
                        fb.style.display = 'block';
                        setTimeout(cerrarModal, 1200);
                    } else {
                        fb.textContent = data.message || '<?= t('pc_mensaje_error') ?>';
                        fb.className = 'vis-modal__feedback vis-feedback--error';
                        fb.style.display = 'block';
                    }
                })
                .catch(() => {
                    var fb = document.getElementById('modalFeedback');
                    fb.textContent = '<?= t('pc_mensaje_error') ?>';
                    fb.className = 'vis-modal__feedback vis-feedback--error';
                    fb.style.display = 'block';
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = '✅ <?= t('send_message') ?>';
                });
        }

        document.getElementById('modalBackdrop')
            .addEventListener('click', cerrarModal);

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') cerrarModal();
        });
    </script>

</body>

</html>