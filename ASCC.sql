-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 05-05-2026 a las 03:12:52
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `ascc`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'FK → usuarios.id_usuario',
  `token` varchar(64) NOT NULL COMMENT 'SHA-256 hex — único por usuario',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_uso` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tokens de API privados para integración con Power BI';

--
-- Volcado de datos para la tabla `api_tokens`
--

INSERT INTO `api_tokens` (`id`, `id_usuario`, `token`, `activo`, `ultimo_uso`, `fecha_creacion`) VALUES
(1, 28, 'ec08dd385fe589b37e9e44a6889b413db777021692fe3bdcf89aa653065fbe93', 1, '2026-04-16 20:27:29', '2026-04-16 16:06:17'),
(3, 26, 'ba6fb6415054916a3f495b168c1d91c9eb8e7f45b6e78522ef12e48175f50381', 1, '2026-04-16 20:27:29', '2026-04-16 18:29:25'),
(4, 29, 'cc06e4cb06dc4744c86ecd07e451eaa07ceeb4ace1cbdff3cc89b1e2ac75e511', 1, '2026-04-24 18:22:16', '2026-04-24 23:21:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `banners`
--

CREATE TABLE `banners` (
  `id_banner` int(11) NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `subtitulo` varchar(255) DEFAULT NULL,
  `url_destino` varchar(500) DEFAULT NULL,
  `ruta_imagen` varchar(500) NOT NULL,
  `alt_imagen` varchar(180) NOT NULL DEFAULT '',
  `posicion` enum('hero','secundario','categorias','sidebar') NOT NULL DEFAULT 'hero',
  `orden` tinyint(3) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `clicks` int(11) NOT NULL DEFAULT 0,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Banners promocionales del marketplace ASCC';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int(10) UNSIGNED NOT NULL,
  `clave` varchar(100) NOT NULL COMMENT 'Clave única del parámetro',
  `valor` text DEFAULT NULL COMMENT 'Valor del parámetro (encriptado para claves sensibles)',
  `grupo` varchar(50) NOT NULL DEFAULT 'general' COMMENT 'Agrupación: general, correo, pagos, seo, seguridad, social, regional',
  `es_secreto` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = valor encriptado en BD',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuración global del sistema ASCC';

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id`, `clave`, `valor`, `grupo`, `es_secreto`, `created_at`, `updated_at`) VALUES
(1, 'site_nombre', 'Aromas y Sabores de mi Campo Colombiano (ASCC)', 'general', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(2, 'site_slogan', 'El campo colombiano en tus manos', 'general', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(3, 'site_email', 'contacto@ascc.co', 'general', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(4, 'site_telefono', '+57 3148416001', 'general', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(5, 'site_direccion', 'Bogotá, Colombia', 'general', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(6, 'site_descripcion', 'Marketplace agropecuario colombiano donde campesinos conectan directamente con compradores.', 'general', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(7, 'site_logo', '', 'general', 0, '2026-03-11 02:18:55', '2026-03-11 02:18:55'),
(8, 'site_favicon', '', 'general', 0, '2026-03-11 02:18:55', '2026-03-11 02:18:55'),
(9, 'site_color', '#06654a', 'general', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(10, 'smtp_host', 'smtp.gmail.com', 'correo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(11, 'smtp_puerto', '587', 'correo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(12, 'smtp_cifrado', 'tls', 'correo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(13, 'smtp_usuario', '', 'correo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(14, 'smtp_password', '', 'correo', 1, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(15, 'smtp_from_nombre', 'ASCC Notificaciones', 'correo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(16, 'smtp_from_email', 'noreply@ascc.co', 'correo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(17, 'smtp_reply_to', 'soporte@ascc.co', 'correo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(18, 'correo_bienvenida', '1', 'correo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(19, 'correo_pedido', '1', 'correo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(20, 'correo_alertas', '1', 'correo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(21, 'pago_pasarela', 'pse', 'pagos', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(22, 'pago_public_key', '', 'pagos', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(23, 'pago_secret_key', '', 'pagos', 1, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(24, 'pago_entorno', 'sandbox', 'pagos', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(25, 'pago_comision', '3.5', 'pagos', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(26, 'pago_iva', '19', 'pagos', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(27, 'pago_efectivo', '1', 'pagos', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(28, 'pago_transferencia', '1', 'pagos', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(29, 'seo_title', 'ASCC — Marketplace Agropecuario de Colombia', 'seo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(30, 'seo_description', 'Compra y vende productos del campo colombiano: papa, yuca, ganado, peces y más.', 'seo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(31, 'seo_keywords', 'marketplace agropecuario, campo colombiano, vender productos agrícolas', 'seo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(32, 'seo_ga_id', '', 'seo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(33, 'seo_gsc_code', '', 'seo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(34, 'seo_og_title', 'ASCC — El campo en tus manos', 'seo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(35, 'seo_og_description', 'El marketplace más grande de productos agropecuarios de Colombia.', 'seo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(36, 'seo_og_image', '', 'seo', 0, '2026-03-11 02:18:55', '2026-03-11 02:18:55'),
(37, 'seo_sitemap', '1', 'seo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(38, 'seo_robots', '1', 'seo', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(39, 'seg_max_intentos', '10', 'seguridad', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(40, 'seg_tiempo_bloqueo', '15', 'seguridad', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(41, 'seg_duracion_sesion', '8', 'seguridad', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(42, 'seg_verificar_email', '1', 'seguridad', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(43, 'seg_recaptcha', '1', 'seguridad', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(44, 'seg_mantenimiento', '1', 'seguridad', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(45, 'seg_mant_mensaje', 'Estamos mejorando ASCC para ti. Volvemos muy pronto.', 'seguridad', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(46, 'seg_mant_fecha', '', 'seguridad', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(47, 'seg_ips_permitidas', '127.0.0.1', 'seguridad', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(48, 'social_facebook', '', 'social', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(49, 'social_instagram', '', 'social', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(50, 'social_whatsapp', '', 'social', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:08'),
(51, 'social_tiktok', '', 'social', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(52, 'social_youtube', '', 'social', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(53, 'social_wa_widget', '1', 'social', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(54, 'social_fb_pixel', '1', 'social', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(55, 'social_fb_pixel_id', '', 'social', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(56, 'social_share_btn', '1', 'social', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(57, 'reg_pais', 'colombia', 'regional', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(58, 'reg_moneda', 'COP', 'regional', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(59, 'reg_timezone', 'America/Bogota', 'regional', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(60, 'reg_idioma', 'es', 'regional', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(61, 'reg_idioma_toggle', '1', 'regional', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(62, 'reg_envio_cobertura', 'nacional', 'regional', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(63, 'reg_envio_base', '12000', 'regional', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(64, 'reg_envio_gratis', '1', 'regional', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(65, 'reg_envio_minimo', '150000', 'regional', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(66, 'reg_google_maps', '1', 'regional', 0, '2026-03-11 02:18:55', '2026-04-22 22:08:09'),
(67, 'reg_maps_key', '', 'regional', 1, '2026-03-11 02:18:55', '2026-04-22 22:08:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conversaciones`
--

CREATE TABLE `conversaciones` (
  `id_conversacion` int(11) NOT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `id_comprador` int(11) DEFAULT NULL,
  `id_vendedor` int(11) DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT current_timestamp(),
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `borrado_por_comprador` tinyint(1) DEFAULT 0,
  `borrado_por_vendedor` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `conversaciones`
--

INSERT INTO `conversaciones` (`id_conversacion`, `id_producto`, `id_comprador`, `id_vendedor`, `fecha_inicio`, `ultima_actualizacion`, `borrado_por_comprador`, `borrado_por_vendedor`) VALUES
(8, 64, 26, 28, '2026-04-15 23:16:14', '2026-04-16 04:16:14', 0, 0),
(9, 70, 29, 26, '2026-04-24 18:20:52', '2026-04-24 23:21:13', 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `descuentos`
--

CREATE TABLE `descuentos` (
  `id_descuento` int(11) NOT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `porcentaje_descuento` decimal(5,2) DEFAULT NULL,
  `precio_original` decimal(10,2) NOT NULL,
  `precio_con_descuento` decimal(10,2) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `imagenes_productos`
--

CREATE TABLE `imagenes_productos` (
  `id_imagen` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `ruta_imagen` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `imagenes_productos`
--

INSERT INTO `imagenes_productos` (`id_imagen`, `id_producto`, `ruta_imagen`) VALUES
(237, 61, 'uploads/productos/prod_61_1772987553_0.jpeg'),
(238, 61, 'uploads/productos/prod_61_1772987553_1.jpeg'),
(239, 61, 'uploads/productos/prod_61_1772987553_2.png'),
(240, 61, 'uploads/productos/prod_61_1772987553_3.png'),
(241, 61, 'uploads/productos/prod_61_1772987553_4.png'),
(247, 63, 'uploads/productos/prod_63_1773446708_0.jpeg'),
(248, 63, 'uploads/productos/prod_63_1773446708_1.jpeg'),
(249, 63, 'uploads/productos/prod_63_1773446708_2.png'),
(250, 63, 'uploads/productos/prod_63_1773446708_3.png'),
(251, 63, 'uploads/productos/prod_63_1773446708_4.png'),
(252, 64, 'uploads/productos/prod_64_1776120126_0.jpeg'),
(253, 64, 'uploads/productos/prod_64_1776120126_1.jpeg'),
(254, 64, 'uploads/productos/prod_64_1776120126_2.jpeg'),
(255, 64, 'uploads/productos/prod_64_1776120126_3.jpg'),
(256, 64, 'uploads/productos/prod_64_1776120126_4.jpg'),
(257, 65, 'uploads/productos/prod_65_1776299668_0.jpeg'),
(258, 65, 'uploads/productos/prod_65_1776299668_1.png'),
(259, 65, 'uploads/productos/prod_65_1776299668_2.png'),
(260, 65, 'uploads/productos/prod_65_1776299668_3.png'),
(261, 65, 'uploads/productos/prod_65_1776299668_4.png'),
(262, 66, 'uploads/productos/prod_66_1776353791_0.jpeg'),
(263, 66, 'uploads/productos/prod_66_1776353791_1.jpeg'),
(264, 66, 'uploads/productos/prod_66_1776353791_2.jpeg'),
(265, 66, 'uploads/productos/prod_66_1776353791_3.jpeg'),
(266, 66, 'uploads/productos/prod_66_1776353791_4.jpeg'),
(267, 67, 'uploads/productos/prod_67_1776816952_0.jpeg'),
(268, 67, 'uploads/productos/prod_67_1776816952_1.jpeg'),
(269, 67, 'uploads/productos/prod_67_1776816952_2.jpeg'),
(270, 67, 'uploads/productos/prod_67_1776816952_3.jpeg'),
(271, 67, 'uploads/productos/prod_67_1776816952_4.jpg'),
(272, 68, 'uploads/productos/prod_68_1776817259_0.jpeg'),
(273, 68, 'uploads/productos/prod_68_1776817259_1.jpeg'),
(274, 68, 'uploads/productos/prod_68_1776817259_2.jpeg'),
(275, 68, 'uploads/productos/prod_68_1776817259_3.jpg'),
(276, 68, 'uploads/productos/prod_68_1776817259_4.jpeg'),
(277, 69, 'uploads/productos/prod_69_1776817452_0.jpg'),
(278, 69, 'uploads/productos/prod_69_1776817452_1.jpeg'),
(279, 69, 'uploads/productos/prod_69_1776817452_2.jpeg'),
(280, 69, 'uploads/productos/prod_69_1776817452_3.jpeg'),
(281, 69, 'uploads/productos/prod_69_1776817452_4.jpeg'),
(282, 70, 'uploads/productos/prod_70_1777072788_0.jpeg'),
(283, 70, 'uploads/productos/prod_70_1777072788_1.jpeg'),
(284, 70, 'uploads/productos/prod_70_1777072788_2.jpeg'),
(285, 70, 'uploads/productos/prod_70_1777072788_3.jpeg'),
(286, 70, 'uploads/productos/prod_70_1777072788_4.jpg'),
(287, 71, 'uploads/productos/prod_71_1777335203_0.jpeg'),
(288, 71, 'uploads/productos/prod_71_1777335203_1.jpeg'),
(289, 71, 'uploads/productos/prod_71_1777335203_2.jpeg'),
(290, 71, 'uploads/productos/prod_71_1777335203_3.jpeg'),
(291, 71, 'uploads/productos/prod_71_1777335203_4.jpeg');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes`
--

CREATE TABLE `mensajes` (
  `id_mensaje` int(11) NOT NULL,
  `id_conversacion` int(11) DEFAULT NULL,
  `id_remitente` int(11) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `fecha_envio` datetime DEFAULT current_timestamp(),
  `leido` tinyint(1) DEFAULT 0,
  `borrado_por_remitente` tinyint(1) DEFAULT 0,
  `borrado_por_destinatario` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mensajes`
--

INSERT INTO `mensajes` (`id_mensaje`, `id_conversacion`, `id_remitente`, `mensaje`, `fecha_envio`, `leido`, `borrado_por_remitente`, `borrado_por_destinatario`) VALUES
(47, 8, 26, 'hola vi que estas interesado en mi producto es verdad', '2026-04-15 23:16:14', 1, 0, 0),
(48, 9, 29, 'hola quiero saber de tu producto', '2026-04-24 18:20:52', 1, 0, 0),
(49, 9, 26, 'bfgbfg', '2026-04-24 18:21:13', 1, 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `destinatario_rol` enum('todos','vendedor','comprador','mixto') DEFAULT NULL COMMENT 'NULL = envío individual',
  `id_destinatario` int(11) DEFAULT NULL COMMENT 'NULL = envío por rol',
  `id_remitente` int(11) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notificaciones del sistema ASCC';

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id_notificacion`, `titulo`, `mensaje`, `tipo`, `destinatario_rol`, `id_destinatario`, `id_remitente`, `activa`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Nueva reseña recibida', 'Elsa Mariela te dejó una reseña de ⭐⭐⭐⭐⭐', 'info', 'vendedor', 26, 28, 1, '2026-04-16 00:48:37', '2026-04-16 00:48:37'),
(2, 'Mantenimiento despues de la 6:00 PM Sabado 8 de Dic 2026', 'Informamos a todos los Usuarios que despues de las 6:00 PM Sabado 8 de Dic 2026 la pplataforma esta ra inhabilidada por 24 Horas aproximadamente por temas de actualización agradecemos su colaboración y espera \r\n\r\natt Servicio tecnico de Aromas y Sabores de mi Campo Colombiano ASCC', 'warning', 'todos', NULL, 25, 1, '2026-04-22 17:07:02', '2026-04-22 17:07:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones_leidas`
--

CREATE TABLE `notificaciones_leidas` (
  `id` int(11) NOT NULL,
  `id_notificacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_lectura` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de notificaciones leídas por usuario';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `usuario_email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expiracion` datetime NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `precio_historico`
--

CREATE TABLE `precio_historico` (
  `id` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL COMMENT 'FK → productos.id_producto',
  `id_usuario` int(11) NOT NULL COMMENT 'FK → usuarios (dueño del producto)',
  `precio_anterior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_nuevo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `motivo` varchar(100) DEFAULT NULL COMMENT 'recomendacion_sistema | edicion_manual',
  `fecha_cambio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de cambios de precio por producto';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL,
  `codigo_producto` varchar(20) DEFAULT NULL COMMENT 'Código único legible. Formato: AGR-AAAA-CAT-NNNNN',
  `id_usuario` int(11) NOT NULL,
  `id_ubicacion` int(11) NOT NULL,
  `tipo_producto` varchar(100) NOT NULL,
  `categoria_principal` varchar(100) DEFAULT NULL,
  `subcategoria` varchar(100) DEFAULT NULL,
  `producto_especifico` varchar(100) DEFAULT NULL,
  `descripcion` text NOT NULL,
  `cantidad` int(11) NOT NULL,
  `unidad` varchar(50) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `estado` enum('disponible','vendido') DEFAULT 'disponible',
  `fecha_publicacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_venta` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `codigo_producto`, `id_usuario`, `id_ubicacion`, `tipo_producto`, `categoria_principal`, `subcategoria`, `producto_especifico`, `descripcion`, `cantidad`, `unidad`, `precio`, `estado`, `fecha_publicacion`, `fecha_venta`) VALUES
(61, 'AGR-2026-AVE-00061', 26, 160, 'Gallina Ponedora', 'aves', 'Gallinas', 'Gallina Ponedora', 'la mejor del campo ', 34, 'unidad', 45000.00, 'disponible', '2026-03-08 16:32:33', NULL),
(63, 'AGR-2026-VER-00063', 26, 31, 'Remolacha', 'verduras', 'Tubérculos y Raíces', 'Remolacha', 'la mejor de la zona \r\n', 15000, 'kg', 2500.00, 'disponible', '2026-03-14 00:05:08', NULL),
(64, 'AGR-2026-VER-00064', 26, 485, 'Papa Criolla', 'verduras', 'Tubérculos y Raíces', 'Papa Criolla', 'papa criolla de la mejor calidad ', 300, 'bulto', 35000.00, 'disponible', '2026-04-13 22:42:06', NULL),
(65, 'AGR-2026-AVE-00065', 28, 69, 'Gallina Ponedora', 'aves', 'Gallinas', 'Gallina Ponedora', 'csfgdsfgsdfgg', 34, 'unidad', 45000.00, 'disponible', '2026-04-16 00:34:28', NULL),
(66, 'AGR-2026-AGR-00066', 26, 371, 'Tilapia roja', 'peces', 'Tilapias', 'Tilapia roja', 'mojarra de la mejor calidad al mejor precio ', 10000, 'kg', 4500.00, 'disponible', '2026-04-16 15:36:31', NULL),
(67, 'AGR-2026-VER-00067', 29, 87, 'Papa Pastusa', 'verduras', 'Tubérculos y Raíces', 'Papa Pastusa', 'la mejor papa al mejor precio es calidad superior no lo dejes perder ', 200, 'bulto', 45000.00, 'disponible', '2026-04-22 00:15:52', NULL),
(68, 'AGR-2026-GAN-00068', 29, 487, 'Oveja de Carne', 'menor', 'Ovinos', 'Oveja de Carne', 'la mejor calidad en ovejas de carne y lana en Sotaquirá', 15, 'unidad', 450000.00, 'disponible', '2026-04-22 00:20:59', NULL),
(69, 'AGR-2026-FRU-00069', 29, 85, 'Mora', 'frutas', 'Frutas de Clima Frío', 'Mora', 'mora de la mejor calidad en el mercado cajas de 35 kilogramos ', 450, 'caja', 70000.00, 'disponible', '2026-04-22 00:24:12', NULL),
(70, 'AGR-2026-VER-00070', 26, 205, 'Papa Pastusa', 'verduras', 'Tubérculos y Raíces', 'Papa Pastusa', 'la mejor de cundinamarca ', 200, 'bulto', 3500000.00, 'disponible', '2026-04-24 23:19:48', NULL),
(71, 'AGR-2026-AVE-00071', 26, 25, 'Ganso', 'aves', 'Otras Aves', 'Ganso', 'los mejores gansos de la región excelentes reproductores, generan bastante carne y además son Gansos de entre seis y ocho meses de nacidos', 13, 'unidad', 90000.00, 'disponible', '2026-04-28 00:13:23', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_custom`
--

CREATE TABLE `productos_custom` (
  `id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `categoria` varchar(100) NOT NULL,
  `subcategoria` varchar(200) NOT NULL DEFAULT '',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos_custom`
--

INSERT INTO `productos_custom` (`id`, `nombre`, `categoria`, `subcategoria`, `creado_en`) VALUES
(1, 'Marihuna', 'aves', 'Gallinas', '2026-04-24 22:02:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes_denuncias`
--

CREATE TABLE `reportes_denuncias` (
  `id_reporte` int(11) NOT NULL,
  `id_denunciante` int(11) NOT NULL COMMENT 'FK → usuarios.id_usuario',
  `id_denunciado` int(11) DEFAULT NULL COMMENT 'FK → usuarios.id_usuario (vendedor)',
  `id_producto` int(11) DEFAULT NULL COMMENT 'FK → productos.id_producto',
  `id_resena` int(11) DEFAULT NULL COMMENT 'FK si viene de una reseña',
  `tipo_denuncia` enum('producto','vendedor','resena') NOT NULL DEFAULT 'producto' COMMENT 'Qué se está denunciando',
  `categoria` enum('no_entregado','descripcion_enganosa','precio_diferente','mala_calidad','vendedor_no_responde','resena_falsa','lenguaje_inapropiado','otro') NOT NULL DEFAULT 'otro',
  `descripcion` text NOT NULL COMMENT 'Descripción del denunciante',
  `estado` enum('recibida','en_revision','pendiente_vendedor','resuelta','cerrada') NOT NULL DEFAULT 'recibida',
  `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media',
  `respuesta_admin` text DEFAULT NULL COMMENT 'Respuesta del administrador',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_resolucion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tickets de denuncia de productos, vendedores y reseñas';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resenas_comprador`
--

CREATE TABLE `resenas_comprador` (
  `id_resena` int(11) NOT NULL,
  `id_comprador` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `calificacion` int(11) NOT NULL,
  `titulo` varchar(150) DEFAULT NULL COMMENT 'Título breve opcional',
  `comentario` text DEFAULT NULL,
  `fecha_resena` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resenas_producto`
--

CREATE TABLE `resenas_producto` (
  `id_resena` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `calificacion` int(11) NOT NULL CHECK (`calificacion` >= 1 and `calificacion` <= 5),
  `titulo` varchar(150) DEFAULT NULL COMMENT 'Título breve opcional',
  `comentario` text DEFAULT NULL,
  `fecha_resena` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `resenas_producto`
--

INSERT INTO `resenas_producto` (`id_resena`, `id_producto`, `id_usuario`, `calificacion`, `titulo`, `comentario`, `fecha_resena`) VALUES
(1, 63, 28, 5, 'el mejor producto', 'la mejor calidadd al mejor precio', '2026-04-13 20:54:29'),
(2, 64, 28, 5, 'excelente producto', 'la verdad es un producto de calidad es excepcional', '2026-04-16 00:48:06'),
(3, 66, 28, 4, 'buen producto la verdad', 'estubimos muy felices de comprar este producto fresco y al alcance', '2026-04-16 18:13:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resenas_vendedor`
--

CREATE TABLE `resenas_vendedor` (
  `id_resena` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `id_comprador` int(11) NOT NULL,
  `calificacion` int(11) NOT NULL CHECK (`calificacion` >= 1 and `calificacion` <= 5),
  `titulo` varchar(150) DEFAULT NULL COMMENT 'Título breve opcional',
  `comentario` text DEFAULT NULL,
  `fecha_resena` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `resenas_vendedor`
--

INSERT INTO `resenas_vendedor` (`id_resena`, `id_vendedor`, `id_comprador`, `calificacion`, `titulo`, `comentario`, `fecha_resena`) VALUES
(1, 26, 28, 5, 'buen vendedor', 'la verdad es confable y atento ademas de que los precios son justos', '2026-04-16 00:48:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transacciones`
--

CREATE TABLE `transacciones` (
  `id_transaccion` int(11) NOT NULL,
  `referencia` varchar(100) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_comprador` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `estado` enum('PENDIENTE','APROBADO','RECHAZADO','CANCELADO') DEFAULT 'PENDIENTE',
  `metodo_pago` varchar(50) NOT NULL,
  `banco` varchar(100) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `datos_pago` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubicaciones`
--

CREATE TABLE `ubicaciones` (
  `id_ubicacion` int(11) NOT NULL,
  `departamento` varchar(100) NOT NULL,
  `municipio` varchar(100) NOT NULL,
  `vereda` varchar(100) NOT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ubicaciones`
--

INSERT INTO `ubicaciones` (`id_ubicacion`, `departamento`, `municipio`, `vereda`, `lat`, `lng`) VALUES
(1, 'Cundinamarca', 'Bogotá', 'Centro', 4.71098900, -74.07209200),
(2, 'Antioquia', 'Medellín', 'El Poblado', 6.24420300, -75.57355300),
(3, 'Valle del Cauca', 'Cali', 'San Antonio', 3.45164700, -76.53198500),
(4, 'Cundinamarca', 'Soacha', 'La Despensa', 4.57953300, -74.21653100),
(5, 'Boyacá', 'Tunja', 'Centro', 5.53527800, -73.36777800),
(6, 'Amazonas', 'Leticia', 'Centro', NULL, NULL),
(7, 'Amazonas', 'Leticia', 'Kilómetro 6', NULL, NULL),
(8, 'Amazonas', 'Leticia', 'Kilómetro 11', NULL, NULL),
(9, 'Amazonas', 'Puerto Nariño', 'Centro', NULL, NULL),
(10, 'Amazonas', 'Puerto Nariño', 'San Juan del Socó', NULL, NULL),
(11, 'Antioquia', 'Medellín', 'El Poblado', NULL, NULL),
(12, 'Antioquia', 'Medellín', 'Laureles', NULL, NULL),
(13, 'Antioquia', 'Medellín', 'Belén', NULL, NULL),
(14, 'Antioquia', 'Medellín', 'La Candelaria', NULL, NULL),
(15, 'Antioquia', 'Bello', 'Centro', 4.72222665, -74.22886631),
(16, 'Antioquia', 'Bello', 'Niquia', NULL, NULL),
(17, 'Antioquia', 'Envigado', 'Centro', NULL, NULL),
(18, 'Antioquia', 'Envigado', 'Zona Industrial', NULL, NULL),
(19, 'Antioquia', 'Itagüí', 'Centro', NULL, NULL),
(20, 'Antioquia', 'Itagüí', 'Calatrava', NULL, NULL),
(21, 'Antioquia', 'Rionegro', 'Centro', NULL, NULL),
(22, 'Antioquia', 'Rionegro', 'Aeropuerto', NULL, NULL),
(23, 'Antioquia', 'Sabaneta', 'Centro', NULL, NULL),
(24, 'Antioquia', 'La Estrella', 'Centro', NULL, NULL),
(25, 'Antioquia', 'Caldas', 'Centro', 6.32408677, -75.59925317),
(26, 'Antioquia', 'Apartadó', 'Centro', 7.93458573, -76.71526055),
(27, 'Antioquia', 'Apartadó', 'Zungo', NULL, NULL),
(28, 'Antioquia', 'Turbo', 'Centro', NULL, NULL),
(29, 'Antioquia', 'Chigorodó', 'Centro', NULL, NULL),
(30, 'Antioquia', 'Carepa', 'Centro', NULL, NULL),
(31, 'Antioquia', 'Carmen de Viboral', 'Centro', 6.08420979, -75.32705542),
(32, 'Antioquia', 'Guarne', 'Centro', NULL, NULL),
(33, 'Antioquia', 'Marinilla', 'Centro', NULL, NULL),
(34, 'Antioquia', 'El Retiro', 'Centro', NULL, NULL),
(35, 'Antioquia', 'La Ceja', 'Centro', NULL, NULL),
(36, 'Antioquia', 'Santa Rosa de Osos', 'Centro', NULL, NULL),
(37, 'Antioquia', 'Yarumal', 'Centro', NULL, NULL),
(38, 'Antioquia', 'Caucasia', 'Centro', NULL, NULL),
(39, 'Antioquia', 'Puerto Berrío', 'Centro', NULL, NULL),
(40, 'Antioquia', 'Andes', 'Centro', NULL, NULL),
(41, 'Antioquia', 'Jardín', 'Centro', NULL, NULL),
(42, 'Antioquia', 'Jericó', 'Centro', NULL, NULL),
(43, 'Antioquia', 'Támesis', 'Centro', NULL, NULL),
(44, 'Antioquia', 'Fredonia', 'Centro', NULL, NULL),
(45, 'Arauca', 'Arauca', 'Centro', NULL, NULL),
(46, 'Arauca', 'Arauca', 'La Pesquera', NULL, NULL),
(47, 'Arauca', 'Arauquita', 'Centro', NULL, NULL),
(48, 'Arauca', 'Cravo Norte', 'Centro', NULL, NULL),
(49, 'Arauca', 'Fortul', 'Centro', NULL, NULL),
(50, 'Arauca', 'Puerto Rondón', 'Centro', NULL, NULL),
(51, 'Arauca', 'Saravena', 'Centro', NULL, NULL),
(52, 'Arauca', 'Tame', 'Centro', NULL, NULL),
(53, 'Atlántico', 'Barranquilla', 'Riomar', NULL, NULL),
(54, 'Atlántico', 'Barranquilla', 'Centro', NULL, NULL),
(55, 'Atlántico', 'Barranquilla', 'Norte', NULL, NULL),
(56, 'Atlántico', 'Barranquilla', 'Sur', NULL, NULL),
(57, 'Atlántico', 'Soledad', 'Centro', NULL, NULL),
(58, 'Atlántico', 'Malambo', 'Centro', NULL, NULL),
(59, 'Atlántico', 'Puerto Colombia', 'Centro', NULL, NULL),
(60, 'Atlántico', 'Sabanalarga', 'Centro', NULL, NULL),
(61, 'Atlántico', 'Galapa', 'Centro', NULL, NULL),
(62, 'Atlántico', 'Baranoa', 'Centro', 10.81500204, -74.85042266),
(63, 'Atlántico', 'Palmar de Varela', 'Centro', NULL, NULL),
(64, 'Atlántico', 'Santo Tomás', 'Centro', NULL, NULL),
(65, 'Atlántico', 'Juan de Acosta', 'Centro', NULL, NULL),
(66, 'Atlántico', 'Usiacurí', 'Centro', NULL, NULL),
(67, 'Atlántico', 'Tubará', 'Centro', NULL, NULL),
(68, 'Bolívar', 'Cartagena', 'Centro Histórico', NULL, NULL),
(69, 'Bolívar', 'Cartagena', 'Bocagrande', 10.41011850, -75.55056960),
(70, 'Bolívar', 'Cartagena', 'Castillogrande', NULL, NULL),
(71, 'Bolívar', 'Cartagena', 'El Laguito', NULL, NULL),
(72, 'Bolívar', 'Cartagena', 'Manga', NULL, NULL),
(73, 'Bolívar', 'Magangué', 'Centro', NULL, NULL),
(74, 'Bolívar', 'Turbaco', 'Centro', NULL, NULL),
(75, 'Bolívar', 'Arjona', 'Centro', NULL, NULL),
(76, 'Bolívar', 'El Carmen de Bolívar', 'Centro', NULL, NULL),
(77, 'Bolívar', 'Mompós', 'Centro', NULL, NULL),
(78, 'Bolívar', 'San Jacinto', 'Centro', NULL, NULL),
(79, 'Bolívar', 'María La Baja', 'Centro', NULL, NULL),
(80, 'Bolívar', 'San Juan Nepomuceno', 'Centro', NULL, NULL),
(81, 'Bolívar', 'Turbana', 'Centro', NULL, NULL),
(82, 'Boyacá', 'Tunja', 'Centro', NULL, NULL),
(83, 'Boyacá', 'Tunja', 'Norte', NULL, NULL),
(84, 'Boyacá', 'Tunja', 'Sur', NULL, NULL),
(85, 'Boyacá', 'Duitama', 'Centro', 5.81659760, -73.04989392),
(86, 'Boyacá', 'Sogamoso', 'Centro', NULL, NULL),
(87, 'Boyacá', 'Chiquinquirá', 'Centro', 5.82658800, -73.03388650),
(88, 'Boyacá', 'Paipa', 'Centro', NULL, NULL),
(89, 'Boyacá', 'Villa de Leyva', 'Centro', NULL, NULL),
(90, 'Boyacá', 'Moniquirá', 'Centro', NULL, NULL),
(91, 'Boyacá', 'Puerto Boyacá', 'Centro', NULL, NULL),
(92, 'Boyacá', 'Garagoa', 'Centro', NULL, NULL),
(93, 'Boyacá', 'Nobsa', 'Centro', NULL, NULL),
(94, 'Boyacá', 'Tibasosa', 'Centro', NULL, NULL),
(95, 'Boyacá', 'Samacá', 'Centro', NULL, NULL),
(96, 'Boyacá', 'Ramiriquí', 'Centro', 5.38283673, -73.36553842),
(97, 'Boyacá', 'Ventaquemada', 'Centro', NULL, NULL),
(98, 'Caldas', 'Manizales', 'Centro', NULL, NULL),
(99, 'Caldas', 'Manizales', 'La Enea', NULL, NULL),
(100, 'Caldas', 'Manizales', 'Versalles', NULL, NULL),
(101, 'Caldas', 'Chinchiná', 'Centro', 4.98142732, -75.69265475),
(102, 'Caldas', 'Villamaría', 'Centro', NULL, NULL),
(103, 'Caldas', 'La Dorada', 'Centro', NULL, NULL),
(104, 'Caldas', 'Riosucio', 'Centro', NULL, NULL),
(105, 'Caldas', 'Anserma', 'Centro', NULL, NULL),
(106, 'Caldas', 'Aguadas', 'Centro', NULL, NULL),
(107, 'Caldas', 'Salamina', 'Centro', NULL, NULL),
(108, 'Caldas', 'Palestina', 'Centro', NULL, NULL),
(109, 'Caldas', 'Neira', 'Centro', NULL, NULL),
(110, 'Caquetá', 'Florencia', 'Centro', NULL, NULL),
(111, 'Caquetá', 'Florencia', 'El Caraño', NULL, NULL),
(112, 'Caquetá', 'San Vicente del Caguán', 'Centro', NULL, NULL),
(113, 'Caquetá', 'Puerto Rico', 'Centro', NULL, NULL),
(114, 'Caquetá', 'Belén de los Andaquíes', 'Centro', NULL, NULL),
(115, 'Caquetá', 'Cartagena del Chairá', 'Centro', NULL, NULL),
(116, 'Caquetá', 'El Doncello', 'Centro', NULL, NULL),
(117, 'Caquetá', 'El Paujil', 'Centro', NULL, NULL),
(118, 'Caquetá', 'La Montañita', 'Centro', NULL, NULL),
(119, 'Caquetá', 'Morelia', 'Centro', NULL, NULL),
(120, 'Casanare', 'Yopal', 'Centro', NULL, NULL),
(121, 'Casanare', 'Yopal', 'El Centro', NULL, NULL),
(122, 'Casanare', 'Aguazul', 'Centro', 5.13339213, -72.49995047),
(123, 'Casanare', 'Villanueva', 'Centro', NULL, NULL),
(124, 'Casanare', 'Monterrey', 'Centro', NULL, NULL),
(125, 'Casanare', 'Paz de Ariporo', 'Centro', NULL, NULL),
(126, 'Casanare', 'Maní', 'Centro', NULL, NULL),
(127, 'Casanare', 'Tauramena', 'Centro', NULL, NULL),
(128, 'Casanare', 'Trinidad', 'Centro', NULL, NULL),
(129, 'Cauca', 'Popayán', 'Centro', 2.47150463, -76.67231292),
(130, 'Cauca', 'Popayán', 'Norte', NULL, NULL),
(131, 'Cauca', 'Popayán', 'Sur', NULL, NULL),
(132, 'Cauca', 'Santander de Quilichao', 'Centro', NULL, NULL),
(133, 'Cauca', 'Puerto Tejada', 'Centro', NULL, NULL),
(134, 'Cauca', 'Patía', 'Centro', NULL, NULL),
(135, 'Cauca', 'Miranda', 'Centro', NULL, NULL),
(136, 'Cauca', 'Guapi', 'Centro', 2.56944113, -77.88680920),
(137, 'Cauca', 'Corinto', 'Centro', 3.20205009, -76.30190255),
(138, 'Cauca', 'Silvia', 'Centro', NULL, NULL),
(139, 'Cauca', 'Piendamó', 'Centro', NULL, NULL),
(140, 'Cauca', 'Cajibío', 'Centro', NULL, NULL),
(141, 'Cesar', 'Valledupar', 'Centro', NULL, NULL),
(142, 'Cesar', 'Valledupar', 'Norte', NULL, NULL),
(143, 'Cesar', 'Valledupar', 'Sur', NULL, NULL),
(144, 'Cesar', 'Aguachica', 'Centro', NULL, NULL),
(145, 'Cesar', 'Bosconia', 'Centro', NULL, NULL),
(146, 'Cesar', 'Codazzi', 'Centro', NULL, NULL),
(147, 'Cesar', 'Chimichagua', 'Centro', NULL, NULL),
(148, 'Cesar', 'Chiriguaná', 'Centro', NULL, NULL),
(149, 'Cesar', 'Curumaní', 'Centro', NULL, NULL),
(150, 'Cesar', 'El Copey', 'Centro', NULL, NULL),
(151, 'Cesar', 'La Jagua de Ibirico', 'Centro', NULL, NULL),
(152, 'Cesar', 'Pailitas', 'Centro', NULL, NULL),
(153, 'Cesar', 'Pelaya', 'Centro', NULL, NULL),
(154, 'Chocó', 'Quibdó', 'Centro', NULL, NULL),
(155, 'Chocó', 'Quibdó', 'Cabí', 3.96504383, -73.69964415),
(156, 'Chocó', 'Istmina', 'Centro', NULL, NULL),
(157, 'Chocó', 'Condoto', 'Centro', NULL, NULL),
(158, 'Chocó', 'Acandí', 'Centro', NULL, NULL),
(159, 'Chocó', 'Bahía Solano', 'Centro', NULL, NULL),
(160, 'Chocó', 'Nuquí', 'Centro', 5.70998455, -77.26549998),
(161, 'Chocó', 'Tadó', 'Centro', NULL, NULL),
(162, 'Chocó', 'Bajo Baudó', 'Centro', 5.18689633, -77.15608533),
(163, 'Córdoba', 'Montería', 'Centro', NULL, NULL),
(164, 'Córdoba', 'Montería', 'Cantaclaro', NULL, NULL),
(165, 'Córdoba', 'Montería', 'Mocari', NULL, NULL),
(166, 'Córdoba', 'Cereté', 'Centro', NULL, NULL),
(167, 'Córdoba', 'Lorica', 'Centro', 9.07515493, -75.81029078),
(168, 'Córdoba', 'Montelíbano', 'Centro', NULL, NULL),
(169, 'Córdoba', 'Sahagún', 'Centro', NULL, NULL),
(170, 'Córdoba', 'Planeta Rica', 'Centro', 8.46503491, -75.52746149),
(171, 'Córdoba', 'Tierralta', 'Centro', NULL, NULL),
(172, 'Córdoba', 'Ayapel', 'Centro', NULL, NULL),
(173, 'Córdoba', 'Ciénaga de Oro', 'Centro', NULL, NULL),
(174, 'Córdoba', 'San Pelayo', 'Centro', NULL, NULL),
(175, 'Córdoba', 'San Bernardo del Viento', 'Centro', NULL, NULL),
(176, 'Cundinamarca', 'Bogotá', 'Usaquén', NULL, NULL),
(177, 'Cundinamarca', 'Bogotá', 'Chapinero', NULL, NULL),
(178, 'Cundinamarca', 'Bogotá', 'Santa Fe', NULL, NULL),
(179, 'Cundinamarca', 'Bogotá', 'San Cristóbal', NULL, NULL),
(180, 'Cundinamarca', 'Bogotá', 'Usme', NULL, NULL),
(181, 'Cundinamarca', 'Bogotá', 'Tunjuelito', NULL, NULL),
(182, 'Cundinamarca', 'Bogotá', 'Bosa', 4.66967600, -74.11754608),
(183, 'Cundinamarca', 'Bogotá', 'Kennedy', NULL, NULL),
(184, 'Cundinamarca', 'Bogotá', 'Fontibón', NULL, NULL),
(185, 'Cundinamarca', 'Bogotá', 'Engativá', NULL, NULL),
(186, 'Cundinamarca', 'Bogotá', 'Suba', NULL, NULL),
(187, 'Cundinamarca', 'Bogotá', 'Barrios Unidos', NULL, NULL),
(188, 'Cundinamarca', 'Bogotá', 'Teusaquillo', NULL, NULL),
(189, 'Cundinamarca', 'Bogotá', 'Los Mártires', NULL, NULL),
(190, 'Cundinamarca', 'Bogotá', 'Antonio Nariño', NULL, NULL),
(191, 'Cundinamarca', 'Bogotá', 'Puente Aranda', NULL, NULL),
(192, 'Cundinamarca', 'Bogotá', 'La Candelaria', NULL, NULL),
(193, 'Cundinamarca', 'Bogotá', 'Rafael Uribe', NULL, NULL),
(194, 'Cundinamarca', 'Bogotá', 'Ciudad Bolívar', NULL, NULL),
(195, 'Cundinamarca', 'Soacha', 'Centro', NULL, NULL),
(196, 'Cundinamarca', 'Soacha', 'Compartir', NULL, NULL),
(197, 'Cundinamarca', 'Soacha', 'San Mateo', 4.58100690, -74.19885550),
(198, 'Cundinamarca', 'Fusagasugá', 'Centro', NULL, NULL),
(199, 'Cundinamarca', 'Chía', 'Centro', NULL, NULL),
(200, 'Cundinamarca', 'Zipaquirá', 'Centro', NULL, NULL),
(201, 'Cundinamarca', 'Facatativá', 'Centro', NULL, NULL),
(202, 'Cundinamarca', 'Girardot', 'Centro', NULL, NULL),
(203, 'Cundinamarca', 'Madrid', 'Centro', 4.73996992, -74.26103821),
(204, 'Cundinamarca', 'Funza', 'Centro', NULL, NULL),
(205, 'Cundinamarca', 'Mosquera', 'Centro', 4.68023670, -74.22967130),
(206, 'Cundinamarca', 'Cajicá', 'Centro', NULL, NULL),
(207, 'Cundinamarca', 'Sopó', 'Centro', NULL, NULL),
(208, 'Cundinamarca', 'La Calera', 'Centro', NULL, NULL),
(209, 'Cundinamarca', 'Cota', 'Centro', NULL, NULL),
(210, 'Cundinamarca', 'Tocancipá', 'Centro', NULL, NULL),
(211, 'Cundinamarca', 'Gachancipá', 'Centro', NULL, NULL),
(212, 'Cundinamarca', 'Tabio', 'Centro', NULL, NULL),
(213, 'Cundinamarca', 'Tenjo', 'Centro', NULL, NULL),
(214, 'Cundinamarca', 'Silvania', 'Centro', NULL, NULL),
(215, 'Cundinamarca', 'Arbeláez', 'Centro', NULL, NULL),
(216, 'Cundinamarca', 'Villeta', 'Centro', NULL, NULL),
(217, 'Cundinamarca', 'Guaduas', 'Centro', NULL, NULL),
(218, 'Guainía', 'Inírida', 'Centro', 3.87672548, -67.90795961),
(219, 'Guainía', 'Barranco Minas', 'Centro', NULL, NULL),
(220, 'Guainía', 'Mapiripana', 'Centro', NULL, NULL),
(221, 'Guaviare', 'San José del Guaviare', 'Centro', NULL, NULL),
(222, 'Guaviare', 'Calamar', 'Centro', 1.95896649, -72.64995538),
(223, 'Guaviare', 'El Retorno', 'Centro', NULL, NULL),
(224, 'Guaviare', 'Miraflores', 'Centro', NULL, NULL),
(225, 'Huila', 'Neiva', 'Centro', NULL, NULL),
(226, 'Huila', 'Neiva', 'Cálamo', NULL, NULL),
(227, 'Huila', 'Pitalito', 'Centro', 1.88503626, -76.00924498),
(228, 'Huila', 'Garzón', 'Centro', NULL, NULL),
(229, 'Huila', 'La Plata', 'Centro', NULL, NULL),
(230, 'Huila', 'Campoalegre', 'Centro', NULL, NULL),
(231, 'Huila', 'Hobo', 'Centro', NULL, NULL),
(232, 'Huila', 'Rivera', 'Centro', NULL, NULL),
(233, 'Huila', 'Gigante', 'Centro', NULL, NULL),
(234, 'Huila', 'Aipe', 'Centro', NULL, NULL),
(235, 'Huila', 'San Agustín', 'Centro', NULL, NULL),
(236, 'La Guajira', 'Riohacha', 'Centro', NULL, NULL),
(237, 'La Guajira', 'Riohacha', 'Tomarrazón', 11.51298328, -72.91946172),
(238, 'La Guajira', 'Maicao', 'Centro', NULL, NULL),
(239, 'La Guajira', 'Uribia', 'Centro', 11.73799096, -72.19205605),
(240, 'La Guajira', 'Manaure', 'Centro', NULL, NULL),
(241, 'La Guajira', 'Albania', 'Centro', NULL, NULL),
(242, 'La Guajira', 'Dibulla', 'Centro', NULL, NULL),
(243, 'La Guajira', 'Fonseca', 'Centro', NULL, NULL),
(244, 'La Guajira', 'San Juan del Cesar', 'Centro', NULL, NULL),
(245, 'La Guajira', 'Villanueva', 'Centro', NULL, NULL),
(246, 'Magdalena', 'Santa Marta', 'Centro', NULL, NULL),
(247, 'Magdalena', 'Santa Marta', 'Rodadero', NULL, NULL),
(248, 'Magdalena', 'Santa Marta', 'Taganga', NULL, NULL),
(249, 'Magdalena', 'Ciénaga', 'Centro', NULL, NULL),
(250, 'Magdalena', 'Fundación', 'Centro', NULL, NULL),
(251, 'Magdalena', 'Zona Bananera', 'Centro', NULL, NULL),
(252, 'Magdalena', 'Plato', 'Centro', NULL, NULL),
(253, 'Magdalena', 'El Banco', 'Centro', NULL, NULL),
(254, 'Magdalena', 'Aracataca', 'Centro', NULL, NULL),
(255, 'Magdalena', 'Santa Ana', 'Centro', NULL, NULL),
(256, 'Meta', 'Villavicencio', 'Centro', NULL, NULL),
(257, 'Meta', 'Villavicencio', 'Kirpas', NULL, NULL),
(258, 'Meta', 'Villavicencio', 'Barzal', NULL, NULL),
(259, 'Meta', 'Acacías', 'Centro', NULL, NULL),
(260, 'Meta', 'Granada', 'Centro', NULL, NULL),
(261, 'Meta', 'San Martín', 'Centro', NULL, NULL),
(262, 'Meta', 'Puerto López', 'Centro', NULL, NULL),
(263, 'Meta', 'Restrepo', 'Centro', NULL, NULL),
(264, 'Meta', 'Cumaral', 'Centro', NULL, NULL),
(265, 'Meta', 'Puerto Gaitán', 'Centro', NULL, NULL),
(266, 'Meta', 'La Macarena', 'Centro', NULL, NULL),
(267, 'Nariño', 'Pasto', 'Centro', NULL, NULL),
(268, 'Nariño', 'Pasto', 'Jongovito', NULL, NULL),
(269, 'Nariño', 'Tumaco', 'Centro', NULL, NULL),
(270, 'Nariño', 'Ipiales', 'Centro', NULL, NULL),
(271, 'Nariño', 'Túquerres', 'Centro', NULL, NULL),
(272, 'Nariño', 'Samaniego', 'Centro', NULL, NULL),
(273, 'Nariño', 'La Cruz', 'Centro', NULL, NULL),
(274, 'Nariño', 'Barbacoas', 'Centro', NULL, NULL),
(275, 'Nariño', 'El Charco', 'Centro', NULL, NULL),
(276, 'Norte de Santander', 'Cúcuta', 'Centro', NULL, NULL),
(277, 'Norte de Santander', 'Cúcuta', 'Norte', NULL, NULL),
(278, 'Norte de Santander', 'Cúcuta', 'Sur', NULL, NULL),
(279, 'Norte de Santander', 'Ocaña', 'Centro', NULL, NULL),
(280, 'Norte de Santander', 'Villa del Rosario', 'Centro', NULL, NULL),
(281, 'Norte de Santander', 'Pamplona', 'Centro', NULL, NULL),
(282, 'Norte de Santander', 'Los Patios', 'Centro', NULL, NULL),
(283, 'Norte de Santander', 'Tibú', 'Centro', NULL, NULL),
(284, 'Norte de Santander', 'El Zulia', 'Centro', NULL, NULL),
(285, 'Norte de Santander', 'Convención', 'Centro', NULL, NULL),
(286, 'Putumayo', 'Mocoa', 'Centro', NULL, NULL),
(287, 'Putumayo', 'Puerto Asís', 'Centro', NULL, NULL),
(288, 'Putumayo', 'Orito', 'Centro', NULL, NULL),
(289, 'Putumayo', 'Valle del Guamuez', 'Centro', NULL, NULL),
(290, 'Putumayo', 'Puerto Guzmán', 'Centro', NULL, NULL),
(291, 'Putumayo', 'Villagarzón', 'Centro', NULL, NULL),
(292, 'Putumayo', 'Sibundoy', 'Centro', NULL, NULL),
(293, 'Quindío', 'Armenia', 'Centro', NULL, NULL),
(294, 'Quindío', 'Armenia', 'Norte', NULL, NULL),
(295, 'Quindío', 'Calarcá', 'Centro', NULL, NULL),
(296, 'Quindío', 'Montenegro', 'Centro', NULL, NULL),
(297, 'Quindío', 'La Tebaida', 'Centro', NULL, NULL),
(298, 'Quindío', 'Quimbaya', 'Centro', NULL, NULL),
(299, 'Quindío', 'Circasia', 'Centro', NULL, NULL),
(300, 'Quindío', 'Filandia', 'Centro', NULL, NULL),
(301, 'Quindío', 'Salento', 'Centro', NULL, NULL),
(302, 'Quindío', 'Génova', 'Centro', NULL, NULL),
(303, 'Quindío', 'Pijao', 'Centro', NULL, NULL),
(304, 'Risaralda', 'Pereira', 'Centro', NULL, NULL),
(305, 'Risaralda', 'Pereira', 'Cuba', NULL, NULL),
(306, 'Risaralda', 'Dosquebradas', 'Centro', NULL, NULL),
(307, 'Risaralda', 'La Virginia', 'Centro', NULL, NULL),
(308, 'Risaralda', 'Santa Rosa de Cabal', 'Centro', NULL, NULL),
(309, 'Risaralda', 'Marsella', 'Centro', NULL, NULL),
(310, 'Risaralda', 'Belén de Umbría', 'Centro', NULL, NULL),
(311, 'Risaralda', 'Pueblo Rico', 'Centro', NULL, NULL),
(312, 'San Andrés y Providencia', 'San Andrés', 'Centro', NULL, NULL),
(313, 'San Andrés y Providencia', 'San Andrés', 'San Luis', NULL, NULL),
(314, 'San Andrés y Providencia', 'Providencia', 'Centro', NULL, NULL),
(315, 'Santander', 'Bucaramanga', 'Centro', NULL, NULL),
(316, 'Santander', 'Bucaramanga', 'Norte', NULL, NULL),
(317, 'Santander', 'Bucaramanga', 'Cabecera', NULL, NULL),
(318, 'Santander', 'Floridablanca', 'Centro', NULL, NULL),
(319, 'Santander', 'Girón', 'Centro', NULL, NULL),
(320, 'Santander', 'Piedecuesta', 'Centro', NULL, NULL),
(321, 'Santander', 'Barrancabermeja', 'Centro', NULL, NULL),
(322, 'Santander', 'San Gil', 'Centro', NULL, NULL),
(323, 'Santander', 'Socorro', 'Centro', NULL, NULL),
(324, 'Santander', 'Barbosa', 'Centro', NULL, NULL),
(325, 'Santander', 'Málaga', 'Centro', NULL, NULL),
(326, 'Santander', 'Vélez', 'Centro', NULL, NULL),
(327, 'Sucre', 'Sincelejo', 'Centro', NULL, NULL),
(328, 'Sucre', 'Sincelejo', 'Berástegui', NULL, NULL),
(329, 'Sucre', 'Corozal', 'Centro', NULL, NULL),
(330, 'Sucre', 'Sampués', 'Centro', NULL, NULL),
(331, 'Sucre', 'San Marcos', 'Centro', NULL, NULL),
(332, 'Sucre', 'Tolú', 'Centro', NULL, NULL),
(333, 'Sucre', 'Coveñas', 'Centro', NULL, NULL),
(334, 'Sucre', 'Majagual', 'Centro', NULL, NULL),
(335, 'Tolima', 'Ibagué', 'Centro', NULL, NULL),
(336, 'Tolima', 'Ibagué', 'Picaleña', NULL, NULL),
(337, 'Tolima', 'Espinal', 'Centro', NULL, NULL),
(338, 'Tolima', 'Melgar', 'Centro', NULL, NULL),
(339, 'Tolima', 'Honda', 'Centro', NULL, NULL),
(340, 'Tolima', 'Chaparral', 'Centro', NULL, NULL),
(341, 'Tolima', 'Líbano', 'Centro', NULL, NULL),
(342, 'Tolima', 'Mariquita', 'Centro', NULL, NULL),
(343, 'Tolima', 'Fresno', 'Centro', NULL, NULL),
(344, 'Tolima', 'Guamo', 'Centro', NULL, NULL),
(345, 'Valle del Cauca', 'Cali', 'Centro', NULL, NULL),
(346, 'Valle del Cauca', 'Cali', 'Norte', NULL, NULL),
(347, 'Valle del Cauca', 'Cali', 'Sur', NULL, NULL),
(348, 'Valle del Cauca', 'Cali', 'Oriente', NULL, NULL),
(349, 'Valle del Cauca', 'Palmira', 'Centro', NULL, NULL),
(350, 'Valle del Cauca', 'Buenaventura', 'Centro', NULL, NULL),
(351, 'Valle del Cauca', 'Tuluá', 'Centro', NULL, NULL),
(352, 'Valle del Cauca', 'Buga', 'Centro', NULL, NULL),
(353, 'Valle del Cauca', 'Cartago', 'Centro', NULL, NULL),
(354, 'Valle del Cauca', 'Jamundí', 'Centro', NULL, NULL),
(355, 'Valle del Cauca', 'Yumbo', 'Centro', NULL, NULL),
(356, 'Valle del Cauca', 'Candelaria', 'Centro', NULL, NULL),
(357, 'Valle del Cauca', 'Florida', 'Centro', NULL, NULL),
(358, 'Valle del Cauca', 'Pradera', 'Centro', NULL, NULL),
(359, 'Valle del Cauca', 'Sevilla', 'Centro', NULL, NULL),
(360, 'Vaupés', 'Mitú', 'Centro', NULL, NULL),
(361, 'Vaupés', 'Carurú', 'Centro', NULL, NULL),
(362, 'Vaupés', 'Taraira', 'Centro', NULL, NULL),
(363, 'Vichada', 'Puerto Carreño', 'Centro', NULL, NULL),
(364, 'Vichada', 'La Primavera', 'Centro', NULL, NULL),
(365, 'Vichada', 'Santa Rosalía', 'Centro', NULL, NULL),
(366, 'Vichada', 'Cumaribo', 'Centro', NULL, NULL),
(367, 'Santander', 'Gámbita', 'Centro', NULL, NULL),
(368, 'Santander', 'Gámbita', 'Calandaima', NULL, NULL),
(369, 'Santander', 'Gámbita', 'Castame', NULL, NULL),
(370, 'Santander', 'Gámbita', 'Chinatá', NULL, NULL),
(371, 'Santander', 'Gámbita', 'Corontunjo', 6.00729000, -73.28250000),
(372, 'Santander', 'Gámbita', 'Cuevas', NULL, NULL),
(373, 'Santander', 'Gámbita', 'El Calvario', NULL, NULL),
(374, 'Santander', 'Gámbita', 'El Palmar', NULL, NULL),
(375, 'Santander', 'Gámbita', 'El Tablón', NULL, NULL),
(376, 'Santander', 'Gámbita', 'Fávita', NULL, NULL),
(377, 'Santander', 'Gámbita', 'Gámbita Viejo', NULL, NULL),
(378, 'Santander', 'Gámbita', 'Guausa', NULL, NULL),
(379, 'Santander', 'Gámbita', 'Huertas', NULL, NULL),
(380, 'Santander', 'Gámbita', 'San Miguel de Huertas', NULL, NULL),
(381, 'Santander', 'Gámbita', 'Juanegro', NULL, NULL),
(382, 'Santander', 'Gámbita', 'La Carrera', NULL, NULL),
(383, 'Santander', 'Gámbita', 'La Palma', NULL, NULL),
(384, 'Santander', 'Gámbita', 'Moscachoque', NULL, NULL),
(385, 'Santander', 'Gámbita', 'Porqueras', NULL, NULL),
(386, 'Santander', 'Gámbita', 'San Vicente', NULL, NULL),
(387, 'Santander', 'Gámbita', 'Supatá', NULL, NULL),
(388, 'Santander', 'Gámbita', 'Vijagual', NULL, NULL),
(411, 'Santander', 'Suaita', 'Centro', NULL, NULL),
(412, 'Santander', 'Suaita', 'San José de Suaita', NULL, NULL),
(413, 'Santander', 'Suaita', 'Vado Real', 6.05225370, -73.38280169),
(414, 'Santander', 'Suaita', 'Olival', NULL, NULL),
(415, 'Santander', 'Suaita', 'Tolotá', NULL, NULL),
(416, 'Santander', 'Suaita', 'La Candelaria', NULL, NULL),
(417, 'Santander', 'Suaita', 'San Luis', NULL, NULL),
(418, 'Santander', 'Suaita', 'San Rafael', NULL, NULL),
(419, 'Santander', 'Suaita', 'El Carmen', NULL, NULL),
(420, 'Santander', 'Suaita', 'Santa Bárbara', NULL, NULL),
(421, 'Santander', 'Suaita', 'Neftalí', NULL, NULL),
(422, 'Santander', 'Suaita', 'Meseta', NULL, NULL),
(423, 'Santander', 'Suaita', 'La Unión', NULL, NULL),
(424, 'Santander', 'Suaita', 'Mantalina', NULL, NULL),
(425, 'Santander', 'Suaita', 'Samaniego', NULL, NULL),
(426, 'Santander', 'Suaita', 'San Pedro', NULL, NULL),
(427, 'Santander', 'Suaita', 'La Chapa', NULL, NULL),
(428, 'Santander', 'Suaita', 'San Joaquín', NULL, NULL),
(429, 'Santander', 'Suaita', 'El Triunfo', NULL, NULL),
(430, 'Santander', 'Suaita', 'La Cristalina', NULL, NULL),
(431, 'Santander', 'Suaita', 'El Mortiño', NULL, NULL),
(432, 'Santander', 'Suaita', 'San Cayetano', NULL, NULL),
(433, 'Santander', 'Suaita', 'La Vega', NULL, NULL),
(434, 'Santander', 'Suaita', 'Los Tejares', NULL, NULL),
(435, 'Santander', 'Suaita', 'Carbonero', NULL, NULL),
(436, 'Santander', 'Suaita', 'El Salto', NULL, NULL),
(437, 'Santander', 'Suaita', 'Loma de la Cruz', NULL, NULL),
(438, 'Santander', 'Suaita', 'San Roque', NULL, NULL),
(439, 'Santander', 'Suaita', 'La Esperanza', NULL, NULL),
(440, 'Santander', 'Suaita', 'Potrero de Ganado', NULL, NULL),
(441, 'Santander', 'Suaita', 'Palo Cabildo', NULL, NULL),
(442, 'Santander', 'Suaita', 'Rodeo', NULL, NULL),
(443, 'Santander', 'Suaita', 'San Isidro', NULL, NULL),
(444, 'Santander', 'Suaita', 'San Miguel', NULL, NULL),
(445, 'Santander', 'Suaita', 'Santa Rosa', NULL, NULL),
(446, 'Santander', 'Suaita', 'Suaitoque', NULL, NULL),
(447, 'Santander', 'Suaita', 'Tiravita', NULL, NULL),
(448, 'Santander', 'Suaita', 'Zarza', NULL, NULL),
(449, 'Santander', 'Gambita', 'El tablon', 4.72339855, -74.22818266),
(450, 'Antioquia', 'Bello', 'Centro', 4.72349172, -74.22803667),
(451, 'Antioquia', 'Medellín', 'La Esperanza', 6.06945682, -73.41231356),
(452, 'Santander', 'Gambita', 'Centro', 4.72341593, -74.22810295),
(453, 'Cundinamarca', 'Mosquera', 'el remanso', 4.72347287, -74.22813461),
(454, 'Santander', 'Gambita', 'supata', 4.72348630, -74.22806186),
(455, 'Cauca', 'Gambita', 'el remanso', 4.72341802, -74.22809641),
(456, 'Santander', 'Gambita', 'Centro', 5.94541209, -73.34408472),
(457, 'Caldas', 'Manizales', 'La Esperanza', 4.97736040, -75.58391911),
(458, 'Cauca', 'Santander de Quilichao', 'El Paraíso', 4.72332396, -74.22818071),
(459, 'Antioquia', 'Medellín', 'Centro', 4.72334242, -74.22821128),
(460, 'Cauca', 'Popayán', 'El Carmen', 4.71955400, -74.22779800),
(461, 'Arauca', 'Arauquita', 'La Esperanza', 4.69905100, -74.18420300),
(462, 'Caquetá', 'Puerto Rico', 'San Antonio', 4.72329890, -74.22821537),
(466, 'Boyacá', 'Duitama', 'El Carmen', 4.72334349, -74.22823830),
(467, 'Chocó', 'Quibdó', 'San Antonio', 4.72336242, -74.22823419),
(468, 'Córdoba', 'Planeta Rica', 'El Paraíso', 8.38679000, -75.54998000),
(469, 'Amazonas', 'Leticia', 'San José', -4.15947780, -69.93613320),
(470, 'Santander', 'Socorro', 'El Paraíso', 6.44508550, -73.25294640),
(471, 'Caquetá', 'San Vicente del Caguán', 'La Esperanza', 2.09733970, -74.72638100),
(472, 'Santander', 'Socorro', 'Bella Vista', 6.46611508, -73.29060488),
(473, 'Casanare', 'Aguazul', 'La Esmeralda', NULL, NULL),
(474, 'Guainía', 'Morichal Nuevo', 'Garza', NULL, NULL),
(475, 'Santander', 'Sabana de Torres', 'Rosablanca', 7.39998000, -73.63331000),
(476, 'Cundinamarca', 'Madrid', 'Los Sauces', 4.72891290, -74.24550130),
(477, 'Chocó', 'Sipi', 'Barranconcito', NULL, NULL),
(478, 'Chocó', 'Bogadó', 'Centro', NULL, NULL),
(479, 'Chocó', 'Bogadó', 'La Sierra', NULL, NULL),
(480, 'Córdoba', 'Momil', 'Centro', NULL, NULL),
(481, 'Córdoba', 'Momil', 'Betulia', NULL, NULL),
(482, 'Cauca', 'Corinto', 'El Jagual', 3.15007000, -76.30005000),
(483, 'Cundinamarca', 'Madrid', 'Vereda Chuscal', NULL, NULL),
(484, 'Cundinamarca', 'Madrid', 'Vda. Chuscal', NULL, NULL),
(485, 'Cundinamarca', 'Cota', 'Vda. el Chacal', 4.83751990, -74.16802000),
(486, 'Boyacá', 'Sotaquirá', 'Centro', NULL, NULL),
(487, 'Boyacá', 'Sotaquirá', 'Batey', 5.78336000, -73.24997000),
(488, 'Antioquia', 'Caldas', 'Vda. Potreritos', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `indicativo` varchar(5) NOT NULL DEFAULT '+57',
  `departamento` varchar(80) DEFAULT NULL,
  `municipio` varchar(80) DEFAULT NULL,
  `vereda` varchar(100) DEFAULT NULL,
  `notif_mensajes` tinyint(1) NOT NULL DEFAULT 1,
  `notif_ventas` tinyint(1) NOT NULL DEFAULT 1,
  `notif_visitas` tinyint(1) NOT NULL DEFAULT 0,
  `notif_promociones` tinyint(1) NOT NULL DEFAULT 0,
  `cedula` varchar(20) NOT NULL,
  `tipo_documento` enum('CC','NIT','PP','CE') NOT NULL DEFAULT 'CC',
  `rol` enum('vendedor','comprador','mixto','admin') NOT NULL DEFAULT 'vendedor',
  `estado` enum('activo','inactivo','suspendido') NOT NULL DEFAULT 'activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `bio`, `email`, `email_verificado`, `foto_perfil`, `password`, `telefono`, `indicativo`, `departamento`, `municipio`, `vereda`, `notif_mensajes`, `notif_ventas`, `notif_visitas`, `notif_promociones`, `cedula`, `tipo_documento`, `rol`, `estado`, `fecha_registro`) VALUES
(25, 'Administrador ASCC', NULL, NULL, 'admin@ascc.co', 0, NULL, '$2y$10$GWzojJqowjDdbYNQcbnyMeH7ripYK4Aojc54syQh.Ocu/rD.StpEO', '3148416001', '+57', NULL, NULL, NULL, 1, 1, 0, 0, '0000000000', 'CC', 'admin', 'activo', '2026-03-08 16:17:15'),
(26, 'Jose samuel', 'López Torres', NULL, 'lopeztorresjosesamuel@gmail.com', 0, 'uploads/avatars/avatar_26_1776128726.png', '$2y$10$pMP2UNg9Sr4Vv0ETWKP9p.KTutZzaqdboR4PeqWPvuL6.PUJ4WscC', '3006389474', '+57', 'Amazonas', NULL, NULL, 1, 1, 1, 1, '1104069754', 'CC', 'mixto', 'activo', '2026-03-08 16:19:48'),
(28, 'Elsa Mariela', 'Torres suarez', 'la mejor calidad de productos', 'torreselsamariela@gmail.com', 0, 'uploads/avatars/avatar_28_1776129578.jpg', '$2y$10$sMLR.uO8/mhIwT7U9ShVEeRlWpOrqfU7raoBivuI3XH91peg.QWCi', '3006389474', '+57', 'Cundinamarca', 'Mosquera', 'centro', 1, 1, 1, 1, '', 'CC', 'mixto', 'activo', '2026-03-14 00:10:58'),
(29, 'Elsa Mariela Torres Suárez', NULL, NULL, 'elsamarielatorressuarez@gmail.com', 0, NULL, '$2y$10$Fk8CRyAOHWHmc3KllEy6G.cLrMJBGc1anBOFGQ5r1AOJqR2bc6d.W', 'CO-3158745612', '+57', NULL, NULL, NULL, 1, 1, 0, 0, '58457845', 'CC', 'mixto', 'activo', '2026-04-22 00:12:46'),
(30, 'Mi casita', 'criolla', NULL, 'micasitacriolla75@gmail.com', 0, 'uploads/avatars/avatar_30_1776992134.png', '$2y$10$N3LZ339fnG3/2DI.OC8oae7WW5Uvip9LeTNImk2SCDYrK/sdVFouC', '3153816475', '+57', 'Cundinamarca', 'Mosquera', 'Centro', 1, 1, 1, 1, '10101010', 'CC', 'mixto', 'activo', '2026-04-24 00:36:50'),
(31, 'Oscar eduardo', 'quintero', NULL, 'oscart@gmail.com', 0, 'uploads/avatars/avatar_31_1777067484.png', '$2y$10$30OyYMYP2AGijwWejRKcYuX2n9BUazzPcyqDQ36.2Qyx6Whybvqu2', '3124567789', '+57', 'Amazonas', NULL, NULL, 1, 1, 1, 1, '15487514', 'CC', 'mixto', 'activo', '2026-04-24 21:50:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `visitas_perfil`
--

CREATE TABLE `visitas_perfil` (
  `id` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL COMMENT 'FK → usuarios.id_usuario (el visitado)',
  `id_visitante` int(11) DEFAULT NULL COMMENT 'NULL = visitante no logueado',
  `ip_visitante` varchar(45) NOT NULL DEFAULT '',
  `sesion_id` varchar(100) NOT NULL DEFAULT '',
  `fecha_visita` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de visitas a perfiles públicos de vendedores';

--
-- Volcado de datos para la tabla `visitas_perfil`
--

INSERT INTO `visitas_perfil` (`id`, `id_vendedor`, `id_visitante`, `ip_visitante`, `sesion_id`, `fecha_visita`) VALUES
(1, 26, 28, '::1', '21b3el8jo7leelith8cc7r1le0', '2026-04-16 04:37:39'),
(2, 26, 28, '::1', 'n1k46d2g37clqcfsa6fk1junj9', '2026-04-16 15:39:46'),
(3, 26, 28, '::1', 'n1k46d2g37clqcfsa6fk1junj9', '2026-04-16 16:00:27'),
(4, 26, 28, '::1', 'n1k46d2g37clqcfsa6fk1junj9', '2026-04-16 18:12:57'),
(5, 28, NULL, '::1', 'r0kjh1lipn202nagmmkgi6ptbr', '2026-04-22 00:04:43'),
(6, 26, NULL, '::1', 'r0kjh1lipn202nagmmkgi6ptbr', '2026-04-22 00:05:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `visitas_producto`
--

CREATE TABLE `visitas_producto` (
  `id` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL COMMENT 'FK → productos.id_producto',
  `id_visitante` int(11) DEFAULT NULL COMMENT 'NULL = visitante no logueado',
  `ip_visitante` varchar(45) NOT NULL DEFAULT '' COMMENT 'IPv4 o IPv6',
  `sesion_id` varchar(100) NOT NULL DEFAULT '' COMMENT 'Para evitar contar recargas',
  `origen` varchar(50) NOT NULL DEFAULT 'directo' COMMENT 'catalogo | busqueda | perfil | directo',
  `fecha_visita` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de visitas a páginas de detalle de producto';

--
-- Volcado de datos para la tabla `visitas_producto`
--

INSERT INTO `visitas_producto` (`id`, `id_producto`, `id_visitante`, `ip_visitante`, `sesion_id`, `origen`, `fecha_visita`) VALUES
(1, 64, 28, '::1', '21b3el8jo7leelith8cc7r1le0', 'directo', '2026-04-16 03:42:17'),
(2, 65, 26, '::1', 'va6i8noajdnr5e822c2m7s8td7', 'directo', '2026-04-16 04:26:32'),
(3, 64, 28, '::1', '21b3el8jo7leelith8cc7r1le0', 'directo', '2026-04-16 04:37:33'),
(4, 63, 28, '::1', '21b3el8jo7leelith8cc7r1le0', 'directo', '2026-04-16 04:40:18'),
(5, 65, 26, '::1', 'e0qort4cv7g2rakddgm6vuap1u', 'directo', '2026-04-16 15:31:46'),
(6, 66, 28, '::1', 'n1k46d2g37clqcfsa6fk1junj9', 'directo', '2026-04-16 15:38:53'),
(7, 66, 28, '::1', 'n1k46d2g37clqcfsa6fk1junj9', 'directo', '2026-04-16 16:00:23'),
(8, 64, 28, '::1', 'n1k46d2g37clqcfsa6fk1junj9', 'perfil', '2026-04-16 16:00:33'),
(9, 66, 28, '::1', 'n1k46d2g37clqcfsa6fk1junj9', 'directo', '2026-04-16 18:12:52'),
(10, 69, 26, '::1', 'h937e3muedvva6iun9jjtq13bq', 'directo', '2026-04-24 21:58:27'),
(11, 70, 29, '::1', 'k0vgtikci1letub2ustki9luu2', 'directo', '2026-04-24 23:20:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vistas_productos`
--

CREATE TABLE `vistas_productos` (
  `id_vista` int(11) NOT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_vista` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_productos_descuento`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_productos_descuento` (
`id_producto` int(11)
,`tipo_producto` varchar(100)
,`precio_original` decimal(10,2)
,`porcentaje_descuento` decimal(5,2)
,`precio_con_descuento` decimal(10,2)
,`fecha_inicio` date
,`fecha_fin` date
,`imagen` varchar(255)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_productos_descuento`
--
DROP TABLE IF EXISTS `vista_productos_descuento`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_productos_descuento`  AS SELECT `p`.`id_producto` AS `id_producto`, `p`.`tipo_producto` AS `tipo_producto`, `p`.`precio` AS `precio_original`, `d`.`porcentaje_descuento` AS `porcentaje_descuento`, `d`.`precio_con_descuento` AS `precio_con_descuento`, `d`.`fecha_inicio` AS `fecha_inicio`, `d`.`fecha_fin` AS `fecha_fin`, (select `imagenes_productos`.`ruta_imagen` from `imagenes_productos` where `imagenes_productos`.`id_producto` = `p`.`id_producto` limit 1) AS `imagen` FROM (`productos` `p` join `descuentos` `d` on(`p`.`id_producto` = `d`.`id_producto`)) WHERE `d`.`activo` = 1 AND curdate() between `d`.`fecha_inicio` and `d`.`fecha_fin` AND `p`.`estado` = 'disponible' ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token` (`token`),
  ADD UNIQUE KEY `uq_id_usuario` (`id_usuario`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id_banner`),
  ADD KEY `idx_posicion` (`posicion`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_orden` (`posicion`,`orden`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_clave` (`clave`),
  ADD KEY `idx_grupo` (`grupo`);

--
-- Indices de la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  ADD PRIMARY KEY (`id_conversacion`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `id_vendedor` (`id_vendedor`),
  ADD KEY `idx_conversaciones_borrado` (`id_comprador`,`id_vendedor`,`borrado_por_comprador`,`borrado_por_vendedor`);

--
-- Indices de la tabla `descuentos`
--
ALTER TABLE `descuentos`
  ADD PRIMARY KEY (`id_descuento`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `imagenes_productos`
--
ALTER TABLE `imagenes_productos`
  ADD PRIMARY KEY (`id_imagen`),
  ADD KEY `idx_producto` (`id_producto`);

--
-- Indices de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `id_remitente` (`id_remitente`),
  ADD KEY `idx_mensajes_borrado` (`id_conversacion`,`borrado_por_remitente`,`borrado_por_destinatario`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_activa` (`activa`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_dest_rol` (`destinatario_rol`),
  ADD KEY `idx_id_destinatario` (`id_destinatario`),
  ADD KEY `idx_id_remitente` (`id_remitente`);

--
-- Indices de la tabla `notificaciones_leidas`
--
ALTER TABLE `notificaciones_leidas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notif_usuario` (`id_notificacion`,`id_usuario`),
  ADD KEY `idx_id_usuario` (`id_usuario`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_email` (`usuario_email`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expiracion` (`expiracion`);

--
-- Indices de la tabla `precio_historico`
--
ALTER TABLE `precio_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_producto` (`id_producto`),
  ADD KEY `idx_id_usuario` (`id_usuario`),
  ADD KEY `idx_fecha` (`fecha_cambio`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `uk_codigo_producto` (`codigo_producto`),
  ADD KEY `id_ubicacion` (`id_ubicacion`),
  ADD KEY `idx_tipo_producto` (`tipo_producto`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_fecha_venta` (`fecha_venta`),
  ADD KEY `idx_fecha_publicacion` (`fecha_publicacion`);

--
-- Indices de la tabla `productos_custom`
--
ALTER TABLE `productos_custom`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_prod` (`nombre`,`categoria`,`subcategoria`);

--
-- Indices de la tabla `reportes_denuncias`
--
ALTER TABLE `reportes_denuncias`
  ADD PRIMARY KEY (`id_reporte`),
  ADD KEY `idx_denunciante` (`id_denunciante`),
  ADD KEY `idx_denunciado` (`id_denunciado`),
  ADD KEY `idx_id_producto` (`id_producto`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_prioridad` (`prioridad`);

--
-- Indices de la tabla `resenas_comprador`
--
ALTER TABLE `resenas_comprador`
  ADD PRIMARY KEY (`id_resena`),
  ADD UNIQUE KEY `uq_comprador_vendedor` (`id_comprador`,`id_vendedor`),
  ADD KEY `idx_comprador` (`id_comprador`),
  ADD KEY `idx_vendedor` (`id_vendedor`);

--
-- Indices de la tabla `resenas_producto`
--
ALTER TABLE `resenas_producto`
  ADD PRIMARY KEY (`id_resena`),
  ADD UNIQUE KEY `uq_usuario_producto` (`id_usuario`,`id_producto`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `idx_producto` (`id_producto`);

--
-- Indices de la tabla `resenas_vendedor`
--
ALTER TABLE `resenas_vendedor`
  ADD PRIMARY KEY (`id_resena`),
  ADD UNIQUE KEY `uq_vendedor_comprador` (`id_vendedor`,`id_comprador`),
  ADD KEY `id_comprador` (`id_comprador`),
  ADD KEY `idx_vendedor` (`id_vendedor`);

--
-- Indices de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD PRIMARY KEY (`id_transaccion`),
  ADD UNIQUE KEY `referencia` (`referencia`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `idx_referencia` (`referencia`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_comprador` (`id_comprador`),
  ADD KEY `idx_vendedor` (`id_vendedor`);

--
-- Indices de la tabla `ubicaciones`
--
ALTER TABLE `ubicaciones`
  ADD PRIMARY KEY (`id_ubicacion`),
  ADD KEY `idx_departamento` (`departamento`),
  ADD KEY `idx_municipio` (`municipio`),
  ADD KEY `idx_vereda` (`vereda`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cedula` (`cedula`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_cedula` (`cedula`);

--
-- Indices de la tabla `visitas_perfil`
--
ALTER TABLE `visitas_perfil`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_vendedor` (`id_vendedor`),
  ADD KEY `idx_id_visitante` (`id_visitante`),
  ADD KEY `idx_fecha_visita` (`fecha_visita`),
  ADD KEY `idx_sesion` (`sesion_id`,`id_vendedor`);

--
-- Indices de la tabla `visitas_producto`
--
ALTER TABLE `visitas_producto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_producto` (`id_producto`),
  ADD KEY `idx_id_visitante` (`id_visitante`),
  ADD KEY `idx_fecha_visita` (`fecha_visita`),
  ADD KEY `idx_sesion` (`sesion_id`,`id_producto`);

--
-- Indices de la tabla `vistas_productos`
--
ALTER TABLE `vistas_productos`
  ADD PRIMARY KEY (`id_vista`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `banners`
--
ALTER TABLE `banners`
  MODIFY `id_banner` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=324;

--
-- AUTO_INCREMENT de la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  MODIFY `id_conversacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `descuentos`
--
ALTER TABLE `descuentos`
  MODIFY `id_descuento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `imagenes_productos`
--
ALTER TABLE `imagenes_productos`
  MODIFY `id_imagen` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=292;

--
-- AUTO_INCREMENT de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  MODIFY `id_mensaje` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `notificaciones_leidas`
--
ALTER TABLE `notificaciones_leidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `precio_historico`
--
ALTER TABLE `precio_historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT de la tabla `productos_custom`
--
ALTER TABLE `productos_custom`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `reportes_denuncias`
--
ALTER TABLE `reportes_denuncias`
  MODIFY `id_reporte` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `resenas_comprador`
--
ALTER TABLE `resenas_comprador`
  MODIFY `id_resena` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `resenas_producto`
--
ALTER TABLE `resenas_producto`
  MODIFY `id_resena` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `resenas_vendedor`
--
ALTER TABLE `resenas_vendedor`
  MODIFY `id_resena` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  MODIFY `id_transaccion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ubicaciones`
--
ALTER TABLE `ubicaciones`
  MODIFY `id_ubicacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=489;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de la tabla `visitas_perfil`
--
ALTER TABLE `visitas_perfil`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `visitas_producto`
--
ALTER TABLE `visitas_producto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `vistas_productos`
--
ALTER TABLE `vistas_productos`
  MODIFY `id_vista` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  ADD CONSTRAINT `conversaciones_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  ADD CONSTRAINT `conversaciones_ibfk_2` FOREIGN KEY (`id_comprador`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `conversaciones_ibfk_3` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `descuentos`
--
ALTER TABLE `descuentos`
  ADD CONSTRAINT `descuentos_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`);

--
-- Filtros para la tabla `imagenes_productos`
--
ALTER TABLE `imagenes_productos`
  ADD CONSTRAINT `imagenes_productos_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD CONSTRAINT `mensajes_ibfk_1` FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`),
  ADD CONSTRAINT `mensajes_ibfk_2` FOREIGN KEY (`id_remitente`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`id_ubicacion`) REFERENCES `ubicaciones` (`id_ubicacion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `resenas_producto`
--
ALTER TABLE `resenas_producto`
  ADD CONSTRAINT `resenas_producto_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE,
  ADD CONSTRAINT `resenas_producto_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `resenas_vendedor`
--
ALTER TABLE `resenas_vendedor`
  ADD CONSTRAINT `resenas_vendedor_ibfk_1` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `resenas_vendedor_ibfk_2` FOREIGN KEY (`id_comprador`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD CONSTRAINT `transacciones_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  ADD CONSTRAINT `transacciones_ibfk_2` FOREIGN KEY (`id_comprador`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `transacciones_ibfk_3` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `vistas_productos`
--
ALTER TABLE `vistas_productos`
  ADD CONSTRAINT `vistas_productos_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  ADD CONSTRAINT `vistas_productos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
