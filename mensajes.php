<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - Aromas y Sabores de mi Campo Colombiano - SISTEMA DE MENSAJERÍA
 * Ruta: C:\xampp\htdocs\ascc\mensajes.php
 * 
 * Sistema de chat independiente estilo WhatsApp
 * ═══════════════════════════════════════════════════════════
 */

// Configuración global
require_once __DIR__ . '/config/app.php';

// Verificar autenticación
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ascc/views/auth/login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
$id_usuario = $_SESSION['id_usuario'];

// Obtener conversaciones del usuario
$stmt = $conexion->prepare("
    SELECT 
        c.id_conversacion,
        c.id_producto,
        c.ultima_actualizacion,
        p.tipo_producto,
        CASE 
            WHEN c.id_comprador = :id_usuario1 THEN u_vendedor.nombre
            ELSE u_comprador.nombre
        END as nombre_otro_usuario,
        CASE 
            WHEN c.id_comprador = :id_usuario2 THEN u_vendedor.id_usuario
            ELSE u_comprador.id_usuario
        END as id_otro_usuario,
        CASE 
            WHEN c.id_comprador = :id_usuario3 THEN u_vendedor.foto_perfil
            ELSE u_comprador.foto_perfil
        END as foto_otro_usuario,
        (SELECT mensaje FROM mensajes WHERE id_conversacion = c.id_conversacion ORDER BY fecha_envio DESC LIMIT 1) as ultimo_mensaje,
        (SELECT fecha_envio FROM mensajes WHERE id_conversacion = c.id_conversacion ORDER BY fecha_envio DESC LIMIT 1) as fecha_ultimo_mensaje,
        (SELECT COUNT(*) FROM mensajes WHERE id_conversacion = c.id_conversacion AND id_remitente != :id_usuario4 AND leido = 0) as mensajes_no_leidos
    FROM conversaciones c
    INNER JOIN productos p ON c.id_producto = p.id_producto
    LEFT JOIN usuarios u_vendedor ON c.id_vendedor = u_vendedor.id_usuario
    LEFT JOIN usuarios u_comprador ON c.id_comprador = u_comprador.id_usuario
    WHERE (c.id_comprador = :id_usuario5 OR c.id_vendedor = :id_usuario6)
        AND NOT (
            (c.id_comprador = :id_usuario7 AND c.borrado_por_comprador = 1)
            OR (c.id_vendedor = :id_usuario8 AND c.borrado_por_vendedor = 1)
        )
    ORDER BY c.ultima_actualizacion DESC
");
$stmt->bindParam(':id_usuario1', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':id_usuario2', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':id_usuario3', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':id_usuario4', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':id_usuario5', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':id_usuario6', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':id_usuario7', $id_usuario, PDO::PARAM_INT);
$stmt->bindParam(':id_usuario8', $id_usuario, PDO::PARAM_INT);
$stmt->execute();
$conversaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar mensajes no leídos
$mensajes_no_leidos = 0;
foreach ($conversaciones as $conv) {
    $mensajes_no_leidos += $conv['mensajes_no_leidos'];
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="user-id" content="<?= $id_usuario ?>">
    <title><?= t('menu_messages') ?> - <?= t('app_name') ?></title>

    <!-- CSS según tema -->
    <?= ascc_theme_css() ?>
    <link rel="stylesheet" href="/ascc/public/css/mensajes.css">
</head>

<body class="theme-<?= $theme ?>" data-theme="<?= $theme ?>">

    <div class="mensajes-layout">

        <!-- ════════════════════════════════════════════════════ -->
        <!-- SIDEBAR: LISTA DE CONVERSACIONES -->
        <!-- ════════════════════════════════════════════════════ -->
        <aside class="conversaciones-sidebar">
            <div class="conversaciones-header">
                <h2>💬 <?= t('conversations') ?></h2>
                <?php if ($mensajes_no_leidos > 0): ?>
                <span class="unread-count"><?= $mensajes_no_leidos ?></span>
                <?php endif; ?>
            </div>

            <div class="conversaciones-list">
                <?php if (count($conversaciones) > 0): ?>
                <?php foreach ($conversaciones as $conv): 
                        $iniciales = strtoupper(substr($conv['nombre_otro_usuario'], 0, 2));
                        $tiempo = '';
                        if ($conv['fecha_ultimo_mensaje']) {
                            $diff = time() - strtotime($conv['fecha_ultimo_mensaje']);
                            if ($diff < 3600) {
                                $tiempo = floor($diff / 60) . 'min';
                            } elseif ($diff < 86400) {
                                $tiempo = floor($diff / 3600) . 'h';
                            } else {
                                $tiempo = date('d/m', strtotime($conv['fecha_ultimo_mensaje']));
                            }
                        }
                        $class_unread = $conv['mensajes_no_leidos'] > 0 ? 'unread' : '';
                    ?>
                <div class="conversation-item <?= $class_unread ?>"
                    data-conversation-id="<?= $conv['id_conversacion'] ?>"
                    onclick="selectConversation(<?= $conv['id_conversacion'] ?>)">
                    <?php if (!empty($conv['foto_otro_usuario'])): ?>
                    <img src="/ascc/public/<?= htmlspecialchars($conv['foto_otro_usuario']) ?>"
                        alt="<?= htmlspecialchars($conv['nombre_otro_usuario']) ?>" class="conversation-avatar-img"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="conversation-avatar" style="display:none;"><?= $iniciales ?></div>
                    <?php else: ?>
                    <div class="conversation-avatar"><?= $iniciales ?></div>
                    <?php endif; ?>
                    <div class="conversation-info">
                        <div class="conversation-name">
                            <?= htmlspecialchars($conv['nombre_otro_usuario']) ?>
                        </div>
                        <div class="conversation-preview">
                            <?= htmlspecialchars(substr($conv['ultimo_mensaje'] ?? 'Sin mensajes', 0, 50)) ?>
                        </div>
                    </div>
                    <div class="conversation-meta">
                        <div class="conversation-time"><?= $tiempo ?></div>
                        <?php if ($conv['mensajes_no_leidos'] > 0): ?>
                        <div class="conversation-badge"><?= $conv['mensajes_no_leidos'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-conversations">
                    <div class="empty-icon">💬</div>
                    <h3><?= t('no_conversations') ?></h3>
                    <p><?= t('no_conversations_text') ?></p>
                </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- ════════════════════════════════════════════════════ -->
        <!-- ÁREA DE CHAT -->
        <!-- ════════════════════════════════════════════════════ -->
        <main class="chat-area">
            <div class="chat-empty-state">
                <div class="chat-empty-icon">💬</div>
                <h3><?= t('select_conversation') ?></h3>
                <p><?= t('choose_conversation_to_start') ?></p>
            </div>
        </main>

    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- MODAL: DETALLES DEL PRODUCTO -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="product-modal" id="productModal">
        <div class="product-modal-overlay" onclick="closeProductModal()"></div>
        <div class="product-modal-content">
            <button class="product-modal-close" onclick="closeProductModal()">✕</button>
            <div class="product-modal-body" id="productModalBody">
                <!-- El contenido se carga dinámicamente desde JS -->
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/ascc/public/js/sync-global.js"></script>
    <script src="/ascc/public/js/mensajes.js"></script>

</body>

</html>