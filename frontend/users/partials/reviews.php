<?php

/**
 * ASCC — Partial: Módulo de Reseñas
 * Ruta: partials/reviews.php
 *
 * MODO SIMPLE (perfil_vendedor.php):
 *   $review_tipo = 'vendedor';
 *   $review_id   = $vendedor['id_usuario'];
 *   include __DIR__ . '/partials/reviews.php';
 *
 * MODO TABS (producto_detalle.php):
 *   $review_tipo      = 'producto';
 *   $review_id        = $producto['id_producto'];
 *   $review_tipo_alt  = 'vendedor';
 *   $review_id_alt    = $producto['vendedor_id'];
 *   $review_modo      = 'tabs';
 *   $review_es_propio = $es_mi_producto;  ← true si es tu producto
 *   include __DIR__ . '/partials/reviews.php';
 */

if (!isset($review_tipo, $review_id)) return;

$_rv_logueado   = isset($_SESSION['id_usuario']);
$_rv_usuario_id = $_rv_logueado ? (int) $_SESSION['id_usuario'] : 0;
$_rv_es_admin   = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$_rv_modo_tabs  = isset($review_modo) && $review_modo === 'tabs'
    && isset($review_tipo_alt, $review_id_alt);

// ── Control de propiedad ─────────────────────────────────────
// Si $review_es_propio = true → solo puede VER reseñas, no crearlas.
// En perfil_vendedor.php: si el visitante ES el vendedor, tampoco puede reseñarse.
$_rv_es_propio = false;

if (isset($review_es_propio) && $review_es_propio === true) {
    // Viene explícitamente desde producto_detalle.php
    $_rv_es_propio = true;
} elseif ($_rv_logueado && $review_tipo === 'vendedor') {
    // Si está viendo su propio perfil de vendedor
    $_rv_es_propio = ((int) $review_id === $_rv_usuario_id);
} elseif ($_rv_logueado && $review_tipo === 'comprador') {
    // Si está viendo su propio perfil de comprador
    $_rv_es_propio = ((int) $review_id === $_rv_usuario_id);
}

// Puede escribir si: está logueado, NO es su propio elemento, NO es admin viendo su panel
$_rv_puede_resenar = $_rv_logueado && !$_rv_es_propio;

$_rv_labels = [
    'producto'  => '📦 ' . t('reviews_tab_producto'),
    'vendedor'  => '👤 ' . t('reviews_tab_vendedor'),
    'comprador' => '🛒 ' . t('reviews_tab_comprador'),
];
?>

<section class="ascc-reviews" id="ascc-reviews" data-tipo="<?= htmlspecialchars($review_tipo, ENT_QUOTES) ?>"
    data-id="<?= (int) $review_id ?>" <?php if ($_rv_modo_tabs): ?>
    data-tipo-alt="<?= htmlspecialchars($review_tipo_alt, ENT_QUOTES) ?>" data-id-alt="<?= (int) $review_id_alt ?>"
    data-modo="tabs" <?php endif; ?> data-usuario-id="<?= $_rv_usuario_id ?>"
    data-es-admin="<?= $_rv_es_admin ? '1' : '0' ?>" aria-label="<?= t('reviews_seccion') ?>">
    <!-- ══ CABECERA ══════════════════════════════════════════ -->
    <div class="rv-header">
        <h2 class="rv-titulo">
            <span class="rv-titulo-icono" aria-hidden="true">⭐</span>
            <?= t('reviews_titulo') ?>
        </h2>

        <?php if ($_rv_modo_tabs): ?>
            <div class="rv-tabs" role="tablist" aria-label="<?= t('reviews_seleccionar_tipo') ?>">
                <button class="rv-tab rv-tab--activo" role="tab" aria-selected="true" aria-controls="rv-panel-principal"
                    data-tipo="<?= htmlspecialchars($review_tipo, ENT_QUOTES) ?>" data-id="<?= (int) $review_id ?>">
                    <?= $_rv_labels[$review_tipo] ?? $review_tipo ?>
                </button>
                <button class="rv-tab" role="tab" aria-selected="false" aria-controls="rv-panel-principal"
                    data-tipo="<?= htmlspecialchars($review_tipo_alt, ENT_QUOTES) ?>" data-id="<?= (int) $review_id_alt ?>">
                    <?= $_rv_labels[$review_tipo_alt] ?? $review_tipo_alt ?>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ══ PANEL PRINCIPAL ═══════════════════════════════════ -->
    <div id="rv-panel-principal">

        <!-- Resumen de calificaciones -->
        <div class="rv-resumen" id="rv-resumen" aria-live="polite">
            <div class="rv-skeleton rv-skeleton--resumen"></div>
        </div>

        <?php if ($_rv_es_propio): ?>
            <!-- ── Es tu propio producto/perfil: solo lectura ──── -->
            <div class="rv-aviso-propio" role="status">
                <span aria-hidden="true">👁️</span>
                <?= t('reviews_solo_lectura') ?>
            </div>

        <?php elseif ($_rv_puede_resenar): ?>
            <!-- ── Formulario para usuarios que SÍ pueden reseñar -->
            <div class="rv-form-wrapper" id="rv-form-wrapper">
                <h3 class="rv-form-titulo"><?= t('reviews_escribe') ?></h3>
                <div class="rv-form" id="rv-form">

                    <!-- Selector de estrellas CSS puro (rtl trick) -->
                    <fieldset class="rv-stars-fieldset">
                        <legend class="rv-label"><?= t('reviews_tu_calificacion') ?></legend>
                        <div class="rv-star-selector" role="radiogroup" aria-label="<?= t('reviews_tu_calificacion') ?>">
                            <?php for ($s = 5; $s >= 1; $s--): ?>
                                <input type="radio" name="rv_calificacion" id="rv-star-<?= $s ?>" value="<?= $s ?>"
                                    class="rv-star-radio" aria-label="<?= $s ?> <?= t('reviews_estrellas') ?>">
                                <label for="rv-star-<?= $s ?>" class="rv-star-label"
                                    title="<?= $s ?> <?= t('reviews_estrellas') ?>" aria-hidden="true">★</label>
                            <?php endfor; ?>
                        </div>
                        <p class="rv-star-hint" id="rv-star-hint" aria-live="polite"></p>
                    </fieldset>

                    <!-- Título opcional -->
                    <div class="rv-campo">
                        <label for="rv-titulo" class="rv-label">
                            <?= t('reviews_titulo_campo') ?>
                            <span class="rv-opcional">(<?= t('reviews_opcional') ?>)</span>
                        </label>
                        <input type="text" id="rv-titulo" class="rv-input" maxlength="150"
                            placeholder="<?= t('reviews_titulo_placeholder') ?>" autocomplete="off">
                    </div>

                    <!-- Comentario requerido -->
                    <div class="rv-campo">
                        <label for="rv-comentario" class="rv-label">
                            <?= t('reviews_comentario') ?>
                            <span class="rv-requerido" aria-hidden="true">*</span>
                        </label>
                        <textarea id="rv-comentario" class="rv-textarea" rows="4" maxlength="1000"
                            placeholder="<?= t('reviews_comentario_placeholder') ?>" required></textarea>
                        <span class="rv-char-count" id="rv-char-count" aria-live="polite">
                            0 / 1000
                        </span>
                    </div>

                    <!-- Mensaje de feedback -->
                    <div class="rv-mensaje" id="rv-mensaje" role="alert" aria-live="assertive"></div>

                    <!-- Botón publicar -->
                    <button type="button" class="rv-btn-enviar" id="rv-btn-enviar">
                        <span class="rv-btn-texto"><?= t('reviews_publicar') ?></span>
                        <span class="rv-spinner rv-spinner--sm" aria-hidden="true"></span>
                    </button>

                </div>
            </div>

        <?php else: ?>
            <!-- ── No logueado: invitar a iniciar sesión ─────── -->
            <div class="rv-login-aviso">
                <span aria-hidden="true">💬</span>
                <?= t('reviews_login_para_resenar') ?>
            </div>
        <?php endif; ?>

        <!-- Listado de reseñas -->
        <div class="rv-lista-seccion">
            <div class="rv-lista" id="rv-lista" aria-live="polite">
                <div class="rv-skeleton rv-skeleton--card"></div>
                <div class="rv-skeleton rv-skeleton--card"></div>
                <div class="rv-skeleton rv-skeleton--card"></div>
            </div>
            <button class="rv-btn-mas" id="rv-btn-mas" aria-label="<?= t('reviews_cargar_mas') ?>" style="display:none">
                <?= t('reviews_cargar_mas') ?>
            </button>
        </div>

    </div><!-- /rv-panel-principal -->

</section>