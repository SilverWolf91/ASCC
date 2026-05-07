<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - FORMULARIO: PUBLICAR PRODUCTO
 * Ruta: C:\xampp\htdocs\ascc\crear_producto.php
 *
 * Responsabilidad: Formulario de 7 pasos para crear producto
 *   - Un solo <head>
 *   - CSS dinámico via ascc_theme_css()
 *   - Traducciones via t()
 *   - sync-global.js al final del body (NO redefine ASCCGlobal)
 *
 * CAMBIOS respecto a versión anterior:
 *   - Paso 7 agregado: revisión de datos antes de publicar
 *   - Indicador actualizado a 7 pasos
 *   - Paso 6 ahora avanza al 7 en lugar de publicar directo
 * ═══════════════════════════════════════════════════════════
 */

// Configuración global (sesión, idioma, tema, t(), ascc_theme_css())
require_once __DIR__ . '/config/app.php';

// Conexión a la base de datos
require_once __DIR__ . '/config/database.php';

// Verificar sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ascc/views/auth/login.php');
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener departamentos para el selector
$stmt_deptos = $conexion->query(
    'SELECT DISTINCT departamento FROM ubicaciones ORDER BY departamento'
);
$departamentos = $stmt_deptos->fetchAll(PDO::FETCH_COLUMN);

// Mensaje de éxito o error
$success = isset($_GET['success']) && $_GET['success'] === '1';
$error   = isset($_GET['error']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('publish_product') ?> - <?= t('app_name') ?></title>
    <link rel="icon" type="image/png" href="/ascc/public/img/logo.png">

    <!-- CSS dinámico según tema (light.css o dark.css) -->
    <?= ascc_theme_css() ?>

    <!-- CSS específico del formulario -->
    <link rel="stylesheet" href="/ascc/public/css/crear-producto.css">

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDfQiFq34PJh6XvksXGxvkpMi3badLWEQc&libraries=places">
    </script>
</head>

<body class="theme-<?= $theme ?>" data-theme="<?= $theme ?>">

    <!-- Header global: botones idioma y tema -->
    <?php include __DIR__ . '/partials/header.php'; ?>

    <div class="form-container">

        <h1 class="form-title"><?= t('publish_new_product') ?></h1>
        <p class="form-subtitle"><?= t('complete_product_info') ?></p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= t('product_published') ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <?php $errorCode = $_GET['error'] ?? ''; ?>
            <div class="alert alert-error">
                <?php if ($errorCode === 'contenido_bloqueado'): ?>
                    🚫 <?= t('error_blocked_content') ?>
                <?php elseif ($errorCode === 'datos_incompletos'): ?>
                    ❌ <?= t('error_incomplete_data') ?>
                <?php elseif ($errorCode === 'ubicacion_incompleta'): ?>
                    📍 <?= t('error_location_required') ?>
                <?php elseif ($errorCode === 'sin_imagenes'): ?>
                    🖼️ <?= t('error_no_images') ?>
                <?php elseif ($errorCode === 'fuera_de_colombia'): ?>
                    🇨🇴 <?= t('error_fuera_de_colombia') ?>
                <?php else: ?>
                    ❌ <?= t('error_publishing') ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Indicador de pasos -->
        <div class="step-indicator">
            <div class="step active" data-step="1">
                <div class="step-circle">1</div>
                <div class="step-label"><?= t('step_category') ?></div>
            </div>
            <div class="step" data-step="2">
                <div class="step-circle">2</div>
                <div class="step-label"><?= t('step_product') ?></div>
            </div>
            <div class="step" data-step="3">
                <div class="step-circle">3</div>
                <div class="step-label"><?= t('step_details') ?></div>
            </div>
            <div class="step" data-step="4">
                <div class="step-circle">4</div>
                <div class="step-label"><?= t('step_price') ?></div>
            </div>
            <div class="step" data-step="5">
                <div class="step-circle">5</div>
                <div class="step-label"><?= t('step_location') ?></div>
            </div>
            <div class="step" data-step="6">
                <div class="step-circle">6</div>
                <div class="step-label"><?= t('step_photos') ?></div>
            </div>
            <div class="step" data-step="7">
                <div class="step-circle">7</div>
                <div class="step-label"><?= t('step_review') ?></div>
            </div>
        </div>

        <form action="/ascc/controllers/ProductoController.php" method="POST" enctype="multipart/form-data"
            id="productForm">

            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="lat" id="lat">
            <input type="hidden" name="lng" id="lng">
            <input type="hidden" name="categoria_principal" id="categoria_principal">
            <input type="hidden" name="subcategoria" id="subcategoria_hidden">
            <input type="hidden" name="producto_especifico" id="producto_especifico">

            <!-- PASO 1: CATEGORÍA -->
            <div class="form-step active" data-step="1">
                <h3 class="step-title"><?= t('select_product_type') ?></h3>
                <div class="category-grid" id="categoryGrid">
                    <!-- Relleno por crear-producto.js -->
                </div>
                <div class="button-group">
                    <button type="button" class="btn-next" onclick="nextStep()">
                        <?= t('next') ?> →
                    </button>
                </div>
            </div>

            <!-- PASO 2: SUBCATEGORÍA Y PRODUCTO -->
            <div class="form-step" data-step="2">
                <h3 class="step-title"><?= t('select_specific_type') ?></h3>
                <div id="subcategoryContainer">
                    <!-- Relleno por crear-producto.js -->
                </div>
                <div class="button-group">
                    <button type="button" class="btn-back" onclick="prevStep()">
                        ← <?= t('back') ?>
                    </button>
                    <button type="button" class="btn-next" onclick="nextStep()">
                        <?= t('next') ?> →
                    </button>
                </div>
            </div>

            <!-- PASO 3: DETALLES -->
            <div class="form-step" data-step="3">
                <h3 class="step-title"><?= t('describe_product') ?></h3>

                <div class="form-group" id="otherProductInput" style="display:none;">
                    <label>🌾 <?= t('product_name') ?> *</label>
                    <input type="text" id="customProductName" name="tipo_producto_custom"
                        placeholder="<?= t('product_name') ?>">
                </div>

                <div class="form-group">
                    <label>🌾 <?= t('product_type') ?> *</label>
                    <input type="text" name="tipo_producto" id="tipo_producto_display"
                        placeholder="<?= t('select_product') ?>" readonly required>
                </div>

                <div class="form-group">
                    <label>📝 <?= t('description') ?> *</label>
                    <textarea name="descripcion" id="descripcion" placeholder="<?= t('description') ?>"
                        required></textarea>
                </div>

                <div class="button-group">
                    <button type="button" class="btn-back" onclick="prevStep()">
                        ← <?= t('back') ?>
                    </button>
                    <button type="button" class="btn-next" onclick="nextStep()">
                        <?= t('next') ?> →
                    </button>
                </div>
            </div>

            <!-- PASO 4: PRECIO -->
            <div class="form-step" data-step="4">
                <h3 class="step-title"><?= t('price_quantity_info') ?></h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>💰 <?= t('price_per_unit') ?> *</label>
                        <input type="text" name="precio" id="precio" placeholder="15.000" required
                            oninput="formatPriceCOP(this)">
                    </div>
                    <div class="form-group">
                        <label>📦 <?= t('available_quantity') ?> *</label>
                        <input type="number" name="cantidad" id="cantidad" placeholder="100" min="1" step="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>📏 <?= t('unit_of_measure') ?> *</label>
                    <select name="unidad" id="unidad" required>
                        <option value=""><?= t('select_one') ?></option>
                        <option value="unidad"><?= t('unit') ?></option>
                        <option value="kg"><?= t('kg') ?></option>
                        <option value="tonelada"><?= t('ton') ?></option>
                        <option value="bulto"><?= t('bag') ?></option>
                        <option value="arroba"><?= t('arroba') ?></option>
                        <option value="litro"><?= t('liter') ?></option>
                        <option value="caja"><?= t('box') ?></option>
                        <option value="docena"><?= t('dozen') ?></option>
                    </select>
                </div>

                <div class="button-group">
                    <button type="button" class="btn-back" onclick="prevStep()">
                        ← <?= t('back') ?>
                    </button>
                    <button type="button" class="btn-next" onclick="nextStep()">
                        <?= t('next') ?> →
                    </button>
                </div>
            </div>

            <!-- PASO 5: UBICACIÓN -->
            <div class="form-step" data-step="5">
                <h3 class="step-title">📍 <?= t('product_location') ?></h3>

                <div class="form-row-3">
                    <div class="form-group">
                        <label>📍 <?= t('department') ?> *</label>
                        <select name="departamento" id="departamento" required>
                            <option value=""><?= t('select') ?></option>
                            <?php foreach ($departamentos as $depto): ?>
                                <option value="<?= htmlspecialchars($depto) ?>">
                                    <?= htmlspecialchars($depto) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🏘️ <?= t('municipality') ?> *</label>
                        <select name="municipio" id="municipio" required disabled>
                            <option value=""><?= t('first_select') ?> <?= t('department') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🏡 <?= t('village') ?> *</label>
                        <select name="vereda" id="vereda" required disabled>
                            <option value=""><?= t('first_select') ?> <?= t('municipality') ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>🗺️ <?= t('select_location_map') ?> *</label>
                    <div id="map"></div>
                    <div class="map-instructions">
                        <?= t('map_instructions') ?>
                    </div>
                </div>

                <div class="button-group">
                    <button type="button" class="btn-back" onclick="prevStep()">
                        ← <?= t('back') ?>
                    </button>
                    <button type="button" class="btn-next" onclick="nextStep()">
                        <?= t('next') ?> →
                    </button>
                </div>
            </div>

            <!-- PASO 6: IMÁGENES -->
            <div class="form-step" data-step="6">
                <h3 class="step-title">📷 <?= t('product_images') ?></h3>

                <div class="form-group">
                    <label><?= t('upload_images') ?> *</label>
                    <input type="file" name="imagenes[]" id="imagenes" accept="image/*" multiple style="display:none;"
                        required>
                    <div class="file-upload-area" id="dropArea">
                        <div class="file-upload-icon">📸</div>
                        <div class="file-upload-text"><?= t('click_or_drag') ?></div>
                        <div class="file-upload-hint"><?= t('image_format') ?></div>
                    </div>
                    <div id="imagePreview"></div>
                </div>

                <div class="button-group">
                    <button type="button" class="btn-back" onclick="prevStep()">
                        ← <?= t('back') ?>
                    </button>
                    <button type="button" class="btn-next" onclick="nextStep()">
                        <?= t('next') ?> →
                    </button>
                </div>
            </div>

            <!-- PASO 7: REVISIÓN -->
            <div class="form-step" data-step="7">
                <h3 class="step-title">✅ <?= t('step_review_title') ?></h3>
                <p class="revision-subtitle"><?= t('step_review_subtitle') ?></p>

                <div class="revision-container" id="revisionContainer">
                    <!-- Relleno por crear-producto.js al llegar a este paso -->
                </div>

                <div class="revision-images" id="revisionImages">
                    <!-- Previsualizaciones de imágenes -->
                </div>

                <div class="button-group">
                    <button type="button" class="btn-back" onclick="prevStep()">
                        ← <?= t('back') ?>
                    </button>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        🚀 <?= t('publish') ?>
                    </button>
                </div>
            </div>

        </form>
    </div>

    <!--
        ── SCRIPTS ────────────────────────────────────────────
        ORDEN OBLIGATORIO:
        1. sync-global.js     → define window.ASCCGlobal (PRIMERO)
        2. crear-producto.js  → lógica del formulario (SEGUNDO)

        NUNCA añadir otro bloque <script> que redefina ASCCGlobal.
    -->
    <script src="/ascc/public/js/colombia_locations.js"></script>
    <script src="/ascc/public/js/sync-global.js"></script>
    <script src="/ascc/public/js/crear-producto.js"></script>

</body>

</html>