<?php

/**
 * ASCC — Perfil Público del Comprador
 * Ruta: perfil_comprador.php
 *
 * Acceso: ?id=X (id_usuario del comprador)
 * Seguridad: Solo vendedores y mixtos logueados pueden ver este perfil
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../../backend/users/config/app.php';
require_once __DIR__ . '/../../../backend/users/config/database.php';

// ── Autenticación ─────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ascc/frontend/users/views/auth/login.php');
    exit;
}

$id_visitante = (int)$_SESSION['id_usuario'];

// ── Verificar que quien visita es vendedor o mixto ────────────
$stmtV = $conexion->prepare(
    "SELECT rol FROM usuarios WHERE id_usuario = :id LIMIT 1"
);
$stmtV->execute([':id' => $id_visitante]);
$visitante = $stmtV->fetch();

if (!$visitante || !in_array($visitante['rol'], ['vendedor', 'mixto'], true)) {
    header('Location: /ascc/catalogo.php');
    exit;
}

// ── Validar parámetro ─────────────────────────────────────────
$id_comprador = (int)($_GET['id'] ?? 0);
if ($id_comprador <= 0) {
    header('Location: /ascc/reportes.php');
    exit;
}

// No puede ver su propio perfil como comprador desde aquí
if ($id_comprador === $id_visitante) {
    header('Location: /ascc/reportes.php');
    exit;
}

// ── Obtener datos del comprador ───────────────────────────────
$stmt = $conexion->prepare(
    "SELECT id_usuario, nombre, email, foto_perfil,
            telefono, rol, estado, fecha_registro
     FROM usuarios
     WHERE id_usuario = :id
       AND estado = 'activo'
       AND rol IN ('comprador','mixto')
     LIMIT 1"
);
$stmt->execute([':id' => $id_comprador]);
$comprador = $stmt->fetch();

if (!$comprador) {
    header('Location: /ascc/reportes.php');
    exit;
}

// ── KPIs del comprador ────────────────────────────────────────
// Total compras completadas
$r = $conexion->prepare(
    "SELECT COUNT(*) FROM transacciones
     WHERE id_comprador = :id AND estado = 'APROBADO'"
);
$r->execute([':id' => $id_comprador]);
$total_compras = (int)$r->fetchColumn();

// Total gastado
$r = $conexion->prepare(
    "SELECT COALESCE(SUM(total), 0) FROM transacciones
     WHERE id_comprador = :id AND estado = 'APROBADO'"
);
$r->execute([':id' => $id_comprador]);
$total_gastado = (float)$r->fetchColumn();

// Última compra
$r = $conexion->prepare(
    "SELECT MAX(fecha_creacion) FROM transacciones
     WHERE id_comprador = :id AND estado = 'APROBADO'"
);
$r->execute([':id' => $id_comprador]);
$ultima_compra = $r->fetchColumn();

// Categorías favoritas (top 3)
$r = $conexion->prepare(
    "SELECT p.categoria_principal, COUNT(*) AS total
     FROM transacciones t
     INNER JOIN productos p ON t.id_producto = p.id_producto
     WHERE t.id_comprador = :id
       AND t.estado = 'APROBADO'
       AND p.categoria_principal IS NOT NULL
     GROUP BY p.categoria_principal
     ORDER BY total DESC
     LIMIT 3"
);
$r->execute([':id' => $id_comprador]);
$categorias_fav = $r->fetchAll();

// ── Historial de compras (últimas 10) ─────────────────────────
$r = $conexion->prepare(
    "SELECT t.id_transaccion, t.total, t.estado, t.fecha_creacion,
            p.tipo_producto, p.precio, p.unidad,
            (SELECT ruta_imagen FROM imagenes_productos
             WHERE id_producto = p.id_producto LIMIT 1) AS imagen
     FROM transacciones t
     INNER JOIN productos p ON t.id_producto = p.id_producto
     WHERE t.id_comprador = :id
     ORDER BY t.fecha_creacion DESC
     LIMIT 10"
);
$r->execute([':id' => $id_comprador]);
$historial = $r->fetchAll();

// ── Reseñas que ha dado este comprador ───────────────────────
$r = $conexion->prepare(
    "SELECT rv.calificacion, rv.titulo, rv.comentario, rv.fecha_resena,
            u.nombre AS nombre_vendedor, u.id_usuario AS id_vendedor
     FROM resenas_vendedor rv
     INNER JOIN usuarios u ON rv.id_vendedor = u.id_usuario
     WHERE rv.id_comprador = :id
     ORDER BY rv.fecha_resena DESC
     LIMIT 5"
);
$r->execute([':id' => $id_comprador]);
$resenas_dadas = $r->fetchAll();

// ── Estado de transacciones ───────────────────────────────────
// ── Primer producto activo del vendedor logueado (contexto para mensajes) ──
$r = $conexion->prepare(
    "SELECT id_producto FROM productos
     WHERE id_usuario = :uid AND estado = 'disponible'
     ORDER BY fecha_publicacion DESC LIMIT 1"
);
$r->execute([':uid' => $id_visitante]);
$primer_producto_id = (int)($r->fetchColumn() ?: 0);

$estado_badge = [
    'APROBADO'   => 'badge-success',
    'PENDIENTE'  => 'badge-warning',
    'RECHAZADO'  => 'badge-danger',
    'CANCELADO'  => 'badge-muted',
];

$estado_label = [
    'APROBADO'   => t('status_completed') ?? 'Completada',
    'PENDIENTE'  => t('status_pending')   ?? 'Pendiente',
    'RECHAZADO'  => t('status_failed')    ?? 'Fallida',
    'CANCELADO'  => t('status_failed')    ?? 'Cancelada',
];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($comprador['nombre']) ?> — <?= t('app_name') ?></title>
    <link rel="icon" type="image/png" href="/ascc/frontend/users/public/img/logo.png">
    <?= ascc_theme_css() ?>
    <link rel="stylesheet" href="/ascc/frontend/users/public/css/visitas-detalle.css?v=<?= time() ?>">
</head>

<body class="theme-<?= $theme ?>" data-theme="<?= $theme ?>">

    <div class="vis-container">

        <!-- ── CABECERA ──────────────────────────────────────── -->
        <a href="javascript:history.back()" class="vis-back">Volver</a>

        <!-- ── TARJETA PRINCIPAL DEL COMPRADOR ───────────────── -->
        <div class="pc-hero-card">

            <!-- Avatar -->
            <div class="pc-avatar-wrap">
                <?php if ($comprador['foto_perfil']): ?>
                    <img src="/ascc/frontend/users/public/<?= htmlspecialchars($comprador['foto_perfil']) ?>"
                        alt="<?= htmlspecialchars($comprador['nombre']) ?>" class="pc-avatar-img"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="pc-avatar" style="display:none">
                        <?= strtoupper(substr($comprador['nombre'], 0, 2)) ?>
                    </div>
                <?php else: ?>
                    <div class="pc-avatar">
                        <?= strtoupper(substr($comprador['nombre'], 0, 2)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info principal -->
            <div class="pc-hero-info">
                <h1 class="pc-nombre"><?= htmlspecialchars($comprador['nombre']) ?></h1>

                <div class="pc-badges">
                    <span class="pc-badge pc-badge--rol">
                        🛒 <?= ucfirst($comprador['rol']) ?>
                    </span>
                    <?php if ($total_compras >= 5): ?>
                        <span class="pc-badge pc-badge--verified">
                            ✅ <?= t('pc_comprador_confiable') ?>
                        </span>
                    <?php endif; ?>
                </div>

                <p class="pc-miembro">
                    📅 <?= t('pc_miembro_desde') ?>
                    <?= date('F Y', strtotime($comprador['fecha_registro'])) ?>
                </p>

                <!-- Categorías favoritas -->
                <?php if (!empty($categorias_fav)): ?>
                    <div class="pc-categorias">
                        <span class="pc-categorias-label">
                            🏷️ <?= t('pc_categorias_favoritas') ?>:
                        </span>
                        <?php foreach ($categorias_fav as $cat): ?>
                            <span class="pc-cat-tag">
                                <?= htmlspecialchars($cat['categoria_principal']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- KPIs -->
            <div class="pc-kpis">
                <div class="pc-kpi">
                    <span class="pc-kpi__val"><?= number_format($total_compras) ?></span>
                    <span class="pc-kpi__lbl"><?= t('pc_compras_completadas') ?></span>
                </div>
                <div class="pc-kpi">
                    <span class="pc-kpi__val">
                        $<?= number_format($total_gastado, 0, ',', '.') ?>
                    </span>
                    <span class="pc-kpi__lbl">Total gastado</span>
                </div>
                <?php if ($ultima_compra): ?>
                    <div class="pc-kpi">
                        <span class="pc-kpi__val">
                            <?= date('d/m/Y', strtotime($ultima_compra)) ?>
                        </span>
                        <span class="pc-kpi__lbl"><?= t('pc_ultima_compra') ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Botón contactar -->
            <div class="pc-contactar">
                <button class="vis-btn-primary"
                    onclick="abrirModal(<?= $id_comprador ?>, '<?= addslashes($comprador['nombre']) ?>')">
                    💬 <?= t('pc_contactar') ?>
                </button>
            </div>

        </div><!-- /pc-hero-card -->

        <!-- ── HISTORIAL DE COMPRAS ───────────────────────────── -->
        <div class="vis-card" style="margin-top:1.5rem">
            <div class="vis-card__header">
                <h3>📋 <?= t('pc_historial_compras') ?></h3>
            </div>
            <?php if (empty($historial)): ?>
                <div class="vis-empty">
                    <p><?= t('pc_sin_compras') ?></p>
                </div>
            <?php else: ?>
                <div class="vis-card__body--flush">
                    <div class="vis-table-wrap">
                        <table class="vis-table">
                            <thead>
                                <tr>
                                    <th><?= t('pc_col_producto') ?></th>
                                    <th><?= t('pc_col_fecha') ?></th>
                                    <th><?= t('pc_col_total') ?></th>
                                    <th><?= t('pc_col_estado') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial as $h): ?>
                                    <tr>
                                        <td>
                                            <div class="pc-prod-cell">
                                                <img src="/ascc/frontend/users/public/<?= htmlspecialchars($h['imagen'] ?? 'img/no-image.png') ?>"
                                                    alt="<?= htmlspecialchars($h['tipo_producto']) ?>" class="pc-prod-thumb"
                                                    onerror="this.src='/ascc/frontend/users/public/img/no-image.png'">
                                                <span><?= htmlspecialchars($h['tipo_producto']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($h['fecha_creacion'])) ?></td>
                                        <td>$<?= number_format($h['total'], 0, ',', '.') ?></td>
                                        <td>
                                            <span class="rep-badge <?= $estado_badge[$h['estado']] ?? 'badge-muted' ?>">
                                                <?= $estado_label[$h['estado']] ?? $h['estado'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── RESEÑAS QUE HA DADO ────────────────────────────── -->
        <div class="vis-card" style="margin-top:1.5rem">
            <div class="vis-card__header">
                <h3>⭐ <?= t('pc_resenas_dadas') ?></h3>
            </div>
            <?php if (empty($resenas_dadas)): ?>
                <div class="vis-empty">
                    <p><?= t('pc_sin_resenas') ?></p>
                </div>
            <?php else: ?>
                <div class="vis-card__body">
                    <?php foreach ($resenas_dadas as $res): ?>
                        <div class="pc-resena-item">
                            <div class="pc-resena-header">
                                <div class="pc-resena-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?= $i <= $res['calificacion'] ? '⭐' : '☆' ?>
                                    <?php endfor; ?>
                                </div>
                                <div class="pc-resena-meta">
                                    <?= t('vendor') ?>:
                                    <a href="/ascc/frontend/users/views/perfil_vendedor.php?id=<?= $res['id_vendedor'] ?>" class="vis-link">
                                        <?= htmlspecialchars($res['nombre_vendedor']) ?>
                                    </a>
                                    · <?= date('d/m/Y', strtotime($res['fecha_resena'])) ?>
                                </div>
                            </div>
                            <?php if ($res['titulo']): ?>
                                <div class="pc-resena-titulo">
                                    <?= htmlspecialchars($res['titulo']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($res['comentario']): ?>
                                <div class="pc-resena-comentario">
                                    <?= nl2br(htmlspecialchars($res['comentario'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                Para: <strong id="modalNombre"></strong>
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
        var csrfToken = '<?= $_SESSION['csrf_token'] ?>';
        var primerProductoId = <?= $primer_producto_id ?>;

        function abrirModal(userId, nombre) {
            destinatarioId = userId;
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

            if (!primerProductoId) {
                var fb = document.getElementById('modalFeedback');
                fb.textContent = 'Debes tener al menos un producto activo para iniciar una conversación.';
                fb.className = 'vis-modal__feedback vis-feedback--error';
                fb.style.display = 'block';
                return;
            }

            var btn = document.getElementById('btnEnviarMsg');
            btn.disabled = true;
            btn.textContent = '⏳';

            fetch('/ascc/backend/users/controllers/MensajesController.php?action=enviar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id_destinatario: destinatarioId,
                        id_producto: primerProductoId,
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