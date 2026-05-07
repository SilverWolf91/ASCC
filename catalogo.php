<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - Aromas y Sabores de mi Campo Colombiano
 * CATÁLOGO COMPLETO Y CORREGIDO
 * Versión: 2.0 Final
 * ═══════════════════════════════════════════════════════════
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

session_start();
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/app.php";

$en_iframe = isset($_GET['embed']) && $_GET['embed'] == '1';

// Obtener filtros
$filtro_id          = $_GET['id']           ?? '';
$filtro_texto       = $_GET['buscar']       ?? '';
$filtro_categoria   = $_GET['categoria']    ?? '';
$filtro_departamento= $_GET['departamento'] ?? '';
$filtro_municipio   = $_GET['municipio']    ?? '';
$filtro_vereda      = $_GET['vereda']       ?? '';
$filtro_precio_min  = $_GET['precio_min']   ?? '';
$filtro_precio_max  = $_GET['precio_max']   ?? '';
$filtro_radio       = $_GET['radio']        ?? 0;
$lat_usuario        = $_GET['lat']          ?? 0;
$lng_usuario        = $_GET['lng']          ?? 0;

// Consulta SQL
$sql = "
    SELECT
        p.id_producto,
        p.codigo_producto,
        p.tipo_producto,
        p.categoria_principal,
        p.descripcion,
        p.precio,
        p.cantidad,
        p.unidad,
        p.id_usuario,
        p.fecha_publicacion,
        u.departamento,
        u.municipio,
        u.vereda,
        u.lat,
        u.lng,
        i.ruta_imagen,
        usr.nombre       AS vendedor_nombre,
        usr.telefono     AS vendedor_telefono,
        usr.email        AS vendedor_email,
        usr.rol          AS vendedor_rol
    FROM productos p
    INNER JOIN ubicaciones u   ON p.id_ubicacion = u.id_ubicacion
    INNER JOIN usuarios usr    ON p.id_usuario   = usr.id_usuario
    LEFT  JOIN imagenes_productos i ON p.id_producto = i.id_producto
    WHERE p.estado = 'disponible'
";

$params = [];

if (!empty($filtro_id)) {
    $sql .= " AND p.codigo_producto LIKE :codigo";
    $params[':codigo'] = "%$filtro_id%";
}

if (!empty($filtro_texto)) {
    $busqueda_limpia = strtolower($filtro_texto);
    $sql .= " AND (LOWER(p.tipo_producto) LIKE :texto
                OR LOWER(p.descripcion)    LIKE :texto2
                OR LOWER(p.codigo_producto) LIKE :texto3)";
    $params[':texto']  = "%$busqueda_limpia%";
    $params[':texto2'] = "%$busqueda_limpia%";
    $params[':texto3'] = "%$busqueda_limpia%";
}

$categorias_mapeo = [
    'Huevos y Derivados'  => 'huevos',
    'Aves de Corral'      => 'aves',
    'Ganado Bovino'       => 'bovinos',
    'Caballos y Equinos'  => 'equinos',
    'Ganado Menor'        => 'ganado_menor',
    'Cárnicos y Embutidos'=> 'carnicos',
    'Lácteos'             => 'lacteos',
    'Verduras y Hortalizas'=> 'verduras',
    'Frutas'              => 'frutas',
    'Cereales y Granos'   => 'cereales',
    'Plantas y Semillas'  => 'plantas',
    'Productos Procesados'=> 'procesados',
];

$categoria_bd = null;
if (!empty($filtro_categoria) && isset($categorias_mapeo[$filtro_categoria])) {
    $categoria_bd = $categorias_mapeo[$filtro_categoria];
}

if (!empty($categoria_bd)) {
    $sql .= " AND p.categoria_principal = :categoria";
    $params[':categoria'] = $categoria_bd;
}

if (!empty($filtro_departamento) && $filtro_departamento !== 'otro') {
    $sql .= " AND u.departamento = :departamento";
    $params[':departamento'] = $filtro_departamento;
}

if (!empty($filtro_municipio) && $filtro_municipio !== 'otro') {
    $sql .= " AND u.municipio = :municipio";
    $params[':municipio'] = $filtro_municipio;
}

if (!empty($filtro_vereda) && $filtro_vereda !== 'otro') {
    $sql .= " AND u.vereda = :vereda";
    $params[':vereda'] = $filtro_vereda;
}

if (!empty($filtro_precio_min)) {
    $sql .= " AND p.precio >= :precio_min";
    $params[':precio_min'] = $filtro_precio_min;
}

if (!empty($filtro_precio_max)) {
    $sql .= " AND p.precio <= :precio_max";
    $params[':precio_max'] = $filtro_precio_max;
}

$sql .= " GROUP BY p.id_producto ORDER BY p.fecha_publicacion DESC";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function calcularDistancia($lat1, $lng1, $lat2, $lng2)
{
    $radioTierra = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat / 2) * sin($dLat / 2)
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
          * sin($dLng / 2) * sin($dLng / 2);
    return $radioTierra * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

if ($filtro_radio > 0 && $lat_usuario && $lng_usuario) {
    $productos = array_filter($productos, function ($prod) use ($lat_usuario, $lng_usuario, $filtro_radio) {
        if ($prod['lat'] && $prod['lng']) {
            return calcularDistancia($lat_usuario, $lng_usuario, $prod['lat'], $prod['lng']) <= $filtro_radio;
        }
        return false;
    });
}

$stmt_deptos = $conexion->query("SELECT DISTINCT departamento FROM ubicaciones ORDER BY departamento");
$departamentos = $stmt_deptos->fetchAll(PDO::FETCH_COLUMN);

$stmt_productos_busqueda = $conexion->query("
    SELECT DISTINCT tipo_producto FROM productos
    WHERE estado = 'disponible' ORDER BY tipo_producto
");
$productos_para_busqueda = $stmt_productos_busqueda->fetchAll(PDO::FETCH_COLUMN);
$productos_json = json_encode($productos_para_busqueda, JSON_UNESCAPED_UNICODE);

$categorias = [
    'Huevos y Derivados','Aves de Corral','Ganado Bovino',
    'Caballos y Equinos','Ganado Menor','Cárnicos y Embutidos',
    'Lácteos','Verduras y Hortalizas','Frutas',
    'Cereales y Granos','Plantas y Semillas','Productos Procesados',
];

// ── Determinar si el visitante puede ver perfil del vendedor ──
$visitante_logueado = isset($_SESSION['id_usuario']);
$visitante_id       = $visitante_logueado ? (int)$_SESSION['id_usuario'] : 0;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('catalog') ?> - <?= t('app_name') ?></title>
    <link rel="icon" href="/ascc/public/img/logo.png">
    <link rel="stylesheet" href="/ascc/public/css/ascc-theme.css">
    <link rel="stylesheet" href="/ascc/public/css/<?= $theme ?>.css">

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: var(--bg-body);
        color: var(--text-primary);
        min-height: 100vh;
    }

    .catalogo-container {
        display: grid;
        grid-template-columns: 340px 1fr;
        gap: 2rem;
        max-width: 1600px;
        margin: 0 auto;
        padding: 2rem;
    }

    .filtros-sidebar {
        position: sticky;
        top: 2rem;
        height: calc(100vh - 4rem);
        max-height: calc(100vh - 4rem);
        background: var(--bg-card);
        border-radius: 20px;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .filtros-header {
        padding: 24px 24px 16px;
        border-bottom: 1px solid var(--border-color);
        flex-shrink: 0;
    }

    .filtros-body {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 16px 0;
    }

    .filtros-body::-webkit-scrollbar {
        width: 6px;
    }

    .filtros-body::-webkit-scrollbar-track {
        background: var(--bg-body);
        border-radius: 10px;
    }

    .filtros-body::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 10px;
    }

    .filtros-body::-webkit-scrollbar-thumb:hover {
        background: #10B981;
    }

    .filtros-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border-color);
        background: var(--bg-card);
        flex-shrink: 0;
    }

    .filtro-acordeon {
        border-bottom: 1px solid var(--border-color);
    }

    .filtro-acordeon:last-child {
        border-bottom: none;
    }

    .acordeon-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 24px;
        cursor: pointer;
        transition: all 0.2s;
        user-select: none;
    }

    .acordeon-header:hover {
        background: var(--bg-hover);
    }

    .acordeon-titulo {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .acordeon-icono {
        font-size: 1.125rem;
        transition: transform 0.3s;
        color: var(--text-secondary);
    }

    .acordeon-header.active .acordeon-icono {
        transform: rotate(180deg);
        color: #10B981;
    }

    .acordeon-contenido {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s;
    }

    .acordeon-contenido.active {
        max-height: 1000px;
    }

    .acordeon-body {
        padding: 16px 24px 20px;
    }

    .filtros-titulo {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filtro-grupo {
        margin-bottom: 1.5rem;
    }

    .filtro-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filtro-input,
    .filtro-select {
        width: 100%;
        padding: 12px 14px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 0.9375rem;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.2s;
    }

    .filtro-input:focus,
    .filtro-select:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .rango-precio {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .btn-filtrar {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #10B981, #059669);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 0.9375rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-filtrar:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
    }

    .btn-limpiar {
        width: 100%;
        padding: 12px;
        background: var(--bg-hover);
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 0.75rem;
        text-decoration: none;
        display: block;
        text-align: center;
    }

    .btn-limpiar:hover {
        border-color: #EF4444;
        color: #EF4444;
    }

    .btn-ubicacion {
        width: 100%;
        background: linear-gradient(135deg, #3B82F6, #2563EB);
        color: white;
        padding: 12px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-ubicacion:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
    }

    .radio-options {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .radio-btn {
        padding: 0.75rem;
        border: 2px solid var(--border-color);
        border-radius: 0.75rem;
        text-align: center;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s;
        background: var(--bg-body);
        color: var(--text-secondary);
    }

    .radio-btn:hover {
        border-color: #F59E0B;
        background: rgba(245, 158, 11, 0.1);
    }

    .radio-btn.active {
        background: linear-gradient(135deg, #F59E0B, #D97706);
        color: white;
        border-color: #F59E0B;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    .catalogo-main {
        min-height: 100vh;
    }

    .catalogo-header {
        margin-bottom: 2rem;
    }

    .catalogo-titulo {
        font-size: 2rem;
        font-weight: 900;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .view-toggle {
        background: var(--bg-card);
        padding: 0.5rem;
        border-radius: 1rem;
        box-shadow: var(--shadow-sm);
        margin: 1.5rem 0;
        display: flex;
        gap: 0.5rem;
        border: 1px solid var(--border-color);
    }

    .view-btn {
        flex: 1;
        padding: 0.75rem;
        border: none;
        background: transparent;
        border-radius: 0.75rem;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.25s;
        color: var(--text-secondary);
    }

    .view-btn.active {
        background: linear-gradient(135deg, #10B981, #059669);
        color: white;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .contador-resultados {
        background: var(--bg-card);
        padding: 1rem 1.5rem;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    #listView {
        display: block;
    }

    #mapView {
        display: none;
    }

    #mapView {
        background: var(--bg-card);
        padding: 1.5rem;
        border-radius: 1.25rem;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    #catalogMap {
        width: 100%;
        height: 600px;
        border-radius: 1rem;
        margin-top: 1rem;
    }

    .productos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 2rem;
    }

    .producto-card {
        background: var(--bg-card);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
        transition: all 0.3s;
        cursor: pointer;
        position: relative;
    }

    .producto-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--shadow-xl);
    }

    .producto-imagen {
        width: 100%;
        height: 220px;
        object-fit: cover;
    }

    .producto-badge-nuevo {
        position: absolute;
        top: 12px;
        left: 12px;
        background: linear-gradient(135deg, #F59E0B, #D97706);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        z-index: 3;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.05);
        }
    }

    .producto-id-badge {
        position: absolute;
        left: 12px;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(8px);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        font-family: 'Courier New', monospace;
        z-index: 2;
    }

    .producto-distancia-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: linear-gradient(135deg, #3B82F6, #2563EB);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        z-index: 2;
    }

    .producto-body {
        padding: 20px;
    }

    .producto-titulo {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 12px;
        line-height: 1.3;
    }

    .producto-precio {
        font-size: 1.75rem;
        font-weight: 900;
        color: #10B981;
        margin-bottom: 12px;
        display: flex;
        align-items: baseline;
        gap: 6px;
    }

    .producto-precio-moneda {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .producto-disponibilidad {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        background: rgba(16, 185, 129, 0.1);
        border-radius: 10px;
        margin-bottom: 16px;
    }

    .icono-disponibilidad {
        font-size: 1.125rem;
    }

    .texto-disponibilidad {
        font-size: 0.9375rem;
        color: var(--text-primary);
    }

    .texto-disponibilidad strong {
        color: #10B981;
        font-weight: 700;
    }

    .producto-separador {
        height: 1px;
        background: var(--border-color);
        margin: 16px 0;
    }

    .producto-ubicacion-completa {
        margin-bottom: 12px;
    }

    .ubicacion-row {
        display: flex;
        gap: 10px;
    }

    .ubicacion-icono {
        font-size: 1.125rem;
        flex-shrink: 0;
    }

    .ubicacion-texto {
        flex: 1;
    }

    .ubicacion-principal {
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .ubicacion-vereda {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .producto-fecha {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 0;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .icono-fecha {
        font-size: 1rem;
    }

    .texto-fecha {
        font-weight: 500;
    }

    /* ── VENDEDOR EN TARJETA ── */
    .producto-vendedor {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 0 0;
        border-top: 1px solid var(--border-color);
        margin-top: 8px;
    }

    .icono-vendedor {
        font-size: 1.125rem;
    }

    /* Link del vendedor — clicable pero sin interferir con el onclick de la tarjeta */
    .link-vendedor {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #10B981;
        text-decoration: none;
        transition: color 0.15s;
    }

    .link-vendedor:hover {
        color: #6ee7b7;
        text-decoration: underline;
    }

    .estado-vacio {
        text-align: center;
        padding: 80px 40px;
    }

    .estado-vacio-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }

    .estado-vacio h3 {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .estado-vacio p {
        color: var(--text-secondary);
    }

    @media (max-width:1024px) {
        .catalogo-container {
            grid-template-columns: 1fr;
            padding: 1.5rem;
        }

        .filtros-sidebar {
            position: relative;
            top: 0;
            height: auto;
            max-height: 600px;
        }
    }

    @media (max-width:768px) {
        .catalogo-container {
            padding: 1rem;
            gap: 1.5rem;
        }

        .filtros-sidebar {
            max-height: 500px;
        }

        .productos-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .catalogo-titulo {
            font-size: 1.5rem;
        }

        .radio-options {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body class="theme-<?= $theme ?>">

    <?php if (!$en_iframe): ?>
    <?php include __DIR__ . '/partials/header.php'; ?>
    <?php endif; ?>

    <div class="catalogo-container">
        <aside class="filtros-sidebar">
            <div class="filtros-header">
                <h2 class="filtros-titulo">
                    <span>🔍</span>
                    <span><?= t('filters') ?></span>
                </h2>
            </div>

            <form method="GET" action="/ascc/catalogo.php" id="formFiltros">
                <?php if ($en_iframe): ?>
                <input type="hidden" name="embed" value="1">
                <?php endif; ?>
                <input type="hidden" name="lat" id="userLat" value="<?= $lat_usuario ?>">
                <input type="hidden" name="lng" id="userLng" value="<?= $lng_usuario ?>">
                <input type="hidden" name="radio" id="radioValue" value="<?= $filtro_radio ?>">

                <div class="filtros-body">

                    <div class="filtro-acordeon">
                        <div class="acordeon-header" onclick="toggleAcordeon(this)">
                            <div class="acordeon-titulo"><span>🔖</span><span><?= t('product_code') ?></span></div>
                            <span class="acordeon-icono">▼</span>
                        </div>
                        <div class="acordeon-contenido">
                            <div class="acordeon-body">
                                <input type="text" name="id" class="filtro-input" placeholder="AGR-2026-XXX-00001"
                                    value="<?= htmlspecialchars($filtro_id) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="filtro-acordeon">
                        <div class="acordeon-header" onclick="toggleAcordeon(this)">
                            <div class="acordeon-titulo"><span>🔎</span><span><?= t('search') ?></span></div>
                            <span class="acordeon-icono">▼</span>
                        </div>
                        <div class="acordeon-contenido">
                            <div class="acordeon-body">
                                <input type="text" name="buscar" id="buscarInput" class="filtro-input"
                                    placeholder="<?= t('search_product') ?>"
                                    value="<?= htmlspecialchars($filtro_texto) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="filtro-acordeon">
                        <div class="acordeon-header" onclick="toggleAcordeon(this)">
                            <div class="acordeon-titulo"><span>📦</span><span><?= t('category') ?></span></div>
                            <span class="acordeon-icono">▼</span>
                        </div>
                        <div class="acordeon-contenido">
                            <div class="acordeon-body">
                                <select name="categoria" class="filtro-select">
                                    <option value=""><?= t('all_categories') ?></option>
                                    <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"
                                        <?= $filtro_categoria === $cat ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="filtro-acordeon">
                        <div class="acordeon-header" onclick="toggleAcordeon(this)">
                            <div class="acordeon-titulo">
                                <span>📍</span>
                                <span><?= t('department') ?>, <?= t('municipality') ?>, <?= t('village') ?></span>
                            </div>
                            <span class="acordeon-icono">▼</span>
                        </div>
                        <div class="acordeon-contenido">
                            <div class="acordeon-body">
                                <div class="filtro-grupo" style="margin-bottom:1rem">
                                    <label class="filtro-label">📍 <?= t('department') ?></label>
                                    <select name="departamento" id="selectDepartamento" class="filtro-select">
                                        <option value=""><?= t('all_departments') ?></option>
                                        <?php foreach ($departamentos as $depto): ?>
                                        <option value="<?= htmlspecialchars($depto) ?>"
                                            <?= $filtro_departamento === $depto ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($depto) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filtro-grupo" style="margin-bottom:1rem">
                                    <label class="filtro-label">🏘️ <?= t('municipality') ?></label>
                                    <select name="municipio" id="selectMunicipio" class="filtro-select" disabled>
                                        <option value=""><?= t('select_department_first') ?></option>
                                    </select>
                                </div>
                                <div class="filtro-grupo">
                                    <label class="filtro-label">🏡 <?= t('village') ?></label>
                                    <select name="vereda" id="selectVereda" class="filtro-select" disabled>
                                        <option value=""><?= t('select_municipality_first') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filtro-acordeon">
                        <div class="acordeon-header" onclick="toggleAcordeon(this)">
                            <div class="acordeon-titulo"><span>💰</span><span><?= t('price_range') ?></span></div>
                            <span class="acordeon-icono">▼</span>
                        </div>
                        <div class="acordeon-contenido">
                            <div class="acordeon-body">
                                <div class="rango-precio">
                                    <input type="number" name="precio_min" class="filtro-input" placeholder="Mín"
                                        value="<?= htmlspecialchars($filtro_precio_min) ?>">
                                    <input type="number" name="precio_max" class="filtro-input" placeholder="Máx"
                                        value="<?= htmlspecialchars($filtro_precio_max) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filtro-acordeon">
                        <div class="acordeon-header" onclick="toggleAcordeon(this)">
                            <div class="acordeon-titulo"><span>📡</span><span><?= t('search_near_me') ?></span></div>
                            <span class="acordeon-icono">▼</span>
                        </div>
                        <div class="acordeon-contenido">
                            <div class="acordeon-body">
                                <button type="button" class="btn-ubicacion" onclick="obtenerUbicacion()">
                                    📍 <?= t('use_my_location') ?>
                                </button>
                                <div id="radioOptions"
                                    style="display:<?= $filtro_radio > 0 ? 'block' : 'none' ?>;margin-top:1rem">
                                    <label class="filtro-label"><?= t('search_radius') ?>:</label>
                                    <div class="radio-options" style="margin-top:0.5rem">
                                        <div class="radio-btn <?= $filtro_radio == 10  ? 'active':'' ?>"
                                            onclick="setRadio(10)">10 km</div>
                                        <div class="radio-btn <?= $filtro_radio == 20  ? 'active':'' ?>"
                                            onclick="setRadio(20)">20 km</div>
                                        <div class="radio-btn <?= $filtro_radio == 50  ? 'active':'' ?>"
                                            onclick="setRadio(50)">50 km</div>
                                        <div class="radio-btn <?= $filtro_radio == 100 ? 'active':'' ?>"
                                            onclick="setRadio(100)">100 km</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /filtros-body -->

                <div class="filtros-footer">
                    <button type="submit" class="btn-filtrar">🔍 <?= t('search') ?></button>
                    <a href="/ascc/catalogo.php<?= $en_iframe ? '?embed=1' : '' ?>" class="btn-limpiar">
                        🗑️ <?= t('clear_filters') ?>
                    </a>
                </div>
            </form>
        </aside>

        <main class="catalogo-main">
            <div class="catalogo-header">
                <h1 class="catalogo-titulo"><?= t('product_catalog') ?></h1>
                <div class="view-toggle">
                    <button class="view-btn active" onclick="cambiarVista('lista')">📋 <?= t('list_view') ?></button>
                    <button class="view-btn" onclick="cambiarVista('mapa')">🗺️ <?= t('map_view') ?></button>
                </div>
                <div class="contador-resultados">
                    <span>📊</span>
                    <span><?= count($productos) ?> <?= t('products_found') ?></span>
                </div>
            </div>

            <div id="listView">
                <?php if (count($productos) > 0): ?>
                <div class="productos-grid">
                    <?php foreach ($productos as $prod):

                        $fecha_pub  = new DateTime($prod['fecha_publicacion']);
                        $hoy        = new DateTime();
                        $dias       = $hoy->diff($fecha_pub)->days;
                        $es_nuevo   = $dias <= 7;

                        if ($dias == 0)       $tiempo_publicado = $lang === 'es' ? 'Hoy' : 'Today';
                        elseif ($dias == 1)   $tiempo_publicado = $lang === 'es' ? 'Hace 1 día' : '1 day ago';
                        elseif ($dias < 30)   $tiempo_publicado = $lang === 'es' ? "Hace $dias días" : "$dias days ago";
                        elseif ($dias < 365)  { $m = floor($dias/30); $tiempo_publicado = $lang==='es' ? "Hace $m ".($m==1?'mes':'meses') : "$m ".($m==1?'month':'months')." ago"; }
                        else                  { $a = floor($dias/365); $tiempo_publicado = $lang==='es' ? "Hace $a ".($a==1?'año':'años') : "$a ".($a==1?'year':'years')." ago"; }

                        // Determinar URL del perfil del vendedor
                        $perfil_url = '/ascc/perfil_vendedor.php?id=' . $prod['id_usuario'];
                        $es_mi_producto = $visitante_logueado && $visitante_id === (int)$prod['id_usuario'];
                    ?>
                    <article class="producto-card"
                        onclick="window.location.href='/ascc/producto_detalle.php?id=<?= $prod['id_producto'] ?>'">

                        <?php if ($es_nuevo): ?>
                        <div class="producto-badge-nuevo">
                            ⭐ <?= $lang === 'es' ? 'NUEVO' : 'NEW' ?>
                        </div>
                        <?php endif; ?>

                        <div class="producto-id-badge" style="top:<?= $es_nuevo ? '52px' : '12px' ?>">
                            <?= htmlspecialchars($prod['codigo_producto']) ?>
                        </div>

                        <?php if ($lat_usuario && $lng_usuario && $prod['lat'] && $prod['lng']): ?>
                        <?php $distancia = calcularDistancia($lat_usuario, $lng_usuario, $prod['lat'], $prod['lng']); ?>
                        <div class="producto-distancia-badge">
                            📍 <?= number_format($distancia, 1) ?> km
                        </div>
                        <?php endif; ?>

                        <img src="/ascc/public/<?= $prod['ruta_imagen'] ?? 'img/no-image.png' ?>"
                            alt="<?= htmlspecialchars($prod['tipo_producto']) ?>" class="producto-imagen"
                            onerror="this.src='/ascc/public/img/no-image.png'">

                        <div class="producto-body">
                            <h3 class="producto-titulo"><?= htmlspecialchars($prod['tipo_producto']) ?></h3>

                            <div class="producto-precio">
                                $<?= number_format($prod['precio'], 0, ",", ".") ?>
                                <span class="producto-precio-moneda">COP</span>
                            </div>

                            <div class="producto-disponibilidad">
                                <span class="icono-disponibilidad">📦</span>
                                <span class="texto-disponibilidad">
                                    <strong><?= htmlspecialchars($prod['cantidad']) ?>
                                        <?= htmlspecialchars($prod['unidad']) ?></strong>
                                    <?= $lang === 'es' ? 'disponibles' : 'available' ?>
                                </span>
                            </div>

                            <div class="producto-separador"></div>

                            <div class="producto-ubicacion-completa">
                                <div class="ubicacion-row">
                                    <span class="ubicacion-icono">📍</span>
                                    <div class="ubicacion-texto">
                                        <div class="ubicacion-principal">
                                            <?= htmlspecialchars($prod['municipio']) ?>,
                                            <?= htmlspecialchars($prod['departamento']) ?>
                                        </div>
                                        <div class="ubicacion-vereda">
                                            <?= htmlspecialchars($prod['vereda']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="producto-fecha">
                                <span class="icono-fecha">📅</span>
                                <span class="texto-fecha"><?= $tiempo_publicado ?></span>
                            </div>

                            <!-- ══ VENDEDOR — link al perfil ══ -->
                            <div class="producto-vendedor">
                                <span class="icono-vendedor">👤</span>
                                <?php if ($es_mi_producto): ?>
                                <span class="link-vendedor" style="cursor:default">
                                    <?= htmlspecialchars($prod['vendedor_nombre']) ?>
                                </span>
                                <?php else: ?>
                                <a href="<?= $perfil_url ?>" class="link-vendedor" onclick="event.stopPropagation()">
                                    <?= htmlspecialchars($prod['vendedor_nombre']) ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <!-- ══ FIN VENDEDOR ══ -->

                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="estado-vacio">
                    <div class="estado-vacio-icon">📦</div>
                    <h3><?= t('no_products_found') ?></h3>
                    <p><?= t('try_adjusting_filters') ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div id="mapView">
                <h3 style="margin-bottom:1rem;color:var(--text-primary)">🗺️ <?= t('map_view') ?></h3>
                <div id="catalogMap"></div>
            </div>
        </main>
    </div>

    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDfQiFq34PJh6XvksXGxvkpMi3badLWEQc"></script>
    <script src="/ascc/public/js/sync-global.js"></script>
    <script src="/ascc/public/js/colombia_locations.js"></script>

    <script>
    function toggleAcordeon(header) {
        const contenido = header.nextElementSibling;
        const todosHeaders = document.querySelectorAll('.acordeon-header');
        const todosContenidos = document.querySelectorAll('.acordeon-contenido');
        if (header.classList.contains('active')) {
            header.classList.remove('active');
            contenido.classList.remove('active');
        } else {
            todosHeaders.forEach(h => h.classList.remove('active'));
            todosContenidos.forEach(c => c.classList.remove('active'));
            header.classList.add('active');
            contenido.classList.add('active');
        }
    }

    const selectDepartamento = document.getElementById('selectDepartamento');
    const selectMunicipio = document.getElementById('selectMunicipio');
    const selectVereda = document.getElementById('selectVereda');
    const filtroMunicipioActual = '<?= $filtro_municipio ?>';
    const filtroVeredaActual = '<?= $filtro_vereda ?>';

    selectDepartamento.addEventListener('change', async function() {
        const departamento = this.value;
        selectMunicipio.innerHTML = '<option value="">Cargando...</option>';
        selectMunicipio.disabled = true;
        selectVereda.innerHTML = '<option value="">Selecciona municipio primero</option>';
        selectVereda.disabled = true;
        if (!departamento || departamento === 'otro') {
            selectMunicipio.innerHTML = '<option value="">Selecciona departamento primero</option>';
            return;
        }
        try {
            const r = await fetch(
                `/ascc/api/get_municipios.php?departamento=${encodeURIComponent(departamento)}`);
            const municipios = await r.json();
            selectMunicipio.innerHTML = '<option value="">Todos los municipios</option>';
            municipios.forEach(m => {
                const o = document.createElement('option');
                o.value = m;
                o.textContent = m;
                if (m === filtroMunicipioActual) o.selected = true;
                selectMunicipio.appendChild(o);
            });
            selectMunicipio.disabled = false;
            if (filtroMunicipioActual) selectMunicipio.dispatchEvent(new Event('change'));
        } catch (e) {
            console.error(e);
        }
    });

    selectMunicipio.addEventListener('change', async function() {
        const departamento = selectDepartamento.value;
        const municipio = this.value;
        selectVereda.innerHTML = '<option value="">Cargando...</option>';
        selectVereda.disabled = true;
        if (!municipio || municipio === 'otro') {
            selectVereda.innerHTML = '<option value="">Selecciona municipio primero</option>';
            return;
        }
        try {
            const r = await fetch(
                `/ascc/api/get_veredas.php?departamento=${encodeURIComponent(departamento)}&municipio=${encodeURIComponent(municipio)}`
                );
            const veredas = await r.json();
            selectVereda.innerHTML = '<option value="">Todas las veredas</option>';
            veredas.forEach(v => {
                const o = document.createElement('option');
                o.value = v;
                o.textContent = v;
                if (v === filtroVeredaActual) o.selected = true;
                selectVereda.appendChild(o);
            });
            selectVereda.disabled = false;
        } catch (e) {
            console.error(e);
        }
    });

    window.addEventListener('DOMContentLoaded', () => {
        if (selectDepartamento.value && selectDepartamento.value !== 'otro') {
            selectDepartamento.dispatchEvent(new Event('change'));
        }
    });

    function cambiarVista(vista) {
        const listView = document.getElementById('listView');
        const mapView = document.getElementById('mapView');
        const buttons = document.querySelectorAll('.view-btn');
        buttons.forEach(b => b.classList.remove('active'));
        if (vista === 'lista') {
            listView.style.display = 'block';
            mapView.style.display = 'none';
            buttons[0].classList.add('active');
        } else {
            listView.style.display = 'none';
            mapView.style.display = 'block';
            buttons[1].classList.add('active');
            if (typeof initMap === 'function') initMap();
        }
    }

    function obtenerUbicacion() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => {
                    document.getElementById('userLat').value = pos.coords.latitude;
                    document.getElementById('userLng').value = pos.coords.longitude;
                    document.getElementById('radioOptions').style.display = 'block';
                    alert('<?= t("location_obtained") ?>');
                },
                () => alert('<?= t("location_error") ?>')
            );
        } else {
            alert('<?= t("no_geolocation") ?>');
        }
    }

    function setRadio(km) {
        document.getElementById('radioValue').value = km;
        document.querySelectorAll('.radio-btn').forEach(b => b.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById('formFiltros').submit();
    }

    let mapaInicializado = false;

    function initMap() {
        if (mapaInicializado) return;
        const map = new google.maps.Map(document.getElementById('catalogMap'), {
            zoom: 6,
            center: {
                lat: 4.5709,
                lng: -74.2973
            },
            mapTypeControl: true,
            streetViewControl: false,
            fullscreenControl: true
        });
        const productos = <?= json_encode(array_values($productos)) ?>;
        const bounds = new google.maps.LatLngBounds();
        let hayMarcadores = false;
        productos.forEach(p => {
            if (p.lat && p.lng) {
                const lat = parseFloat(p.lat);
                const lng = parseFloat(p.lng);
                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = new google.maps.Marker({
                        position: {
                            lat,
                            lng
                        },
                        map,
                        title: p.tipo_producto,
                        icon: {
                            url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png'
                        }
                    });
                    const info = new google.maps.InfoWindow({
                        content: `<div style="padding:10px;max-width:250px">
                            <h3 style="margin:0 0 8px;font-size:16px;font-weight:700;color:#065F46">${p.tipo_producto}</h3>
                            <p style="margin:4px 0;font-size:14px;font-weight:600;color:#10B981">$${parseInt(p.precio).toLocaleString('es-CO')} COP</p>
                            <p style="margin:4px 0;font-size:13px;color:#6B7280">📦 ${p.cantidad} ${p.unidad}</p>
                            <p style="margin:4px 0;font-size:13px;color:#6B7280">📍 ${p.vereda}, ${p.municipio}</p>
                            <p style="margin:4px 0;font-size:13px;color:#6B7280">👤 ${p.vendedor_nombre}</p>
                            <a href="/ascc/producto_detalle.php?id=${p.id_producto}"
                               style="display:inline-block;margin-top:8px;padding:6px 12px;background:#10B981;color:white;text-decoration:none;border-radius:6px;font-size:13px;font-weight:600">
                               Ver Detalles
                            </a></div>`
                    });
                    marker.addListener('click', () => info.open(map, marker));
                    bounds.extend({
                        lat,
                        lng
                    });
                    hayMarcadores = true;
                }
            }
        });
        if (hayMarcadores) {
            map.fitBounds(bounds);
            google.maps.event.addListenerOnce(map, 'bounds_changed', () => {
                if (map.getZoom() > 15) map.setZoom(15);
            });
        }
        mapaInicializado = true;
    }

    // ── Búsqueda inteligente ──
    const inputBuscar = document.getElementById('buscarInput');
    const PRODUCTOS_BD = <?= $productos_json ?>;
    const PRODUCTOS_VARIANTES = {};
    PRODUCTOS_BD.forEach(producto => {
        const pl = producto.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        PRODUCTOS_VARIANTES[producto] = [
            pl,
            pl.replace(/\s+/g, ''),
            pl.replace(/á/g, 'a').replace(/é/g, 'e').replace(/í/g, 'i').replace(/ó/g, 'o').replace(/ú/g,
                'u'),
            pl.replace(/h/g, ''),
            pl.replace(/ll/g, 'y'),
            pl.replace(/y/g, 'll'),
        ];
    });
    const CORRECCIONES_COMUNES = {
        'holstein': 'Holstein',
        'olstein': 'Holstein',
        'jolstein': 'Holstein',
        'huevo aaa': 'Huevo AA',
        'aguacate has': 'Aguacate Hass',
        'mais': 'Maíz',
        'papa pastuza': 'Papa Pastusa',
    };
    if (inputBuscar) {
        let t;
        inputBuscar.addEventListener('input', function() {
            clearTimeout(t);
            const val = this.value.trim();
            const ex = document.getElementById('sugerencia-busqueda');
            if (ex) ex.remove();
            if (val.length < 3) return;
            t = setTimeout(() => {
                const n = val.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                const cc = CORRECCIONES_COMUNES[n];
                if (cc && cc.toLowerCase() !== val.toLowerCase()) {
                    mostrarSugerencia(cc);
                    return;
                }
                for (const [orig, vars] of Object.entries(PRODUCTOS_VARIANTES)) {
                    for (const v of vars) {
                        if (v.includes(n) || n.includes(v) || calcularSimilitud(v, n) > 0.7) {
                            if (orig.toLowerCase() !== val.toLowerCase()) {
                                mostrarSugerencia(orig);
                                return;
                            }
                        }
                    }
                }
            }, 500);
        });
    }

    function calcularSimilitud(a, b) {
        const l = a.length > b.length ? a : b;
        const s = a.length > b.length ? b : a;
        if (!l.length) return 1;
        return (l.length - levenshteinDistance(l, s)) / l.length;
    }

    function levenshteinDistance(a, b) {
        const m = [];
        for (let i = 0; i <= b.length; i++) m[i] = [i];
        for (let j = 0; j <= a.length; j++) m[0][j] = j;
        for (let i = 1; i <= b.length; i++)
            for (let j = 1; j <= a.length; j++)
                m[i][j] = b[i - 1] === a[j - 1] ? m[i - 1][j - 1] : Math.min(m[i - 1][j - 1] + 1, m[i][j - 1] + 1, m[i -
                    1][j] + 1);
        return m[b.length][a.length];
    }

    function mostrarSugerencia(texto) {
        const ex = document.getElementById('sugerencia-busqueda');
        if (ex) ex.remove();
        const s = document.createElement('div');
        s.id = 'sugerencia-busqueda';
        s.style.cssText =
            'background:#ECFDF5;border:2px solid #10B981;border-radius:12px;padding:12px 16px;margin-top:8px;font-size:0.875rem;color:#065F46;cursor:pointer;transition:all .2s';
        s.innerHTML =
            `💡 ¿Quisiste decir <strong>"${texto}"</strong>? <span style="color:#10B981;text-decoration:underline">Haz clic para usar</span>`;
        s.onclick = function() {
            document.getElementById('buscarInput').value = texto;
            this.remove();
        };
        s.onmouseover = () => s.style.transform = 'scale(1.02)';
        s.onmouseout = () => s.style.transform = 'scale(1)';
        inputBuscar.parentElement.appendChild(s);
        setTimeout(() => {
            if (s.parentElement) s.remove();
        }, 10000);
    }
    </script>
</body>

</html>