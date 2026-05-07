<?php

/**
 * ASCC - Archivo de idioma Español
 * Ruta: admin/lang/es.php
 */

return [

    /* =========================================================================
       GENERAL
    ========================================================================= */
    'admin'             => 'Administrador',
    'save'              => 'Guardar',
    'cancel'            => 'Cancelar',
    'delete'            => 'Eliminar',
    'edit'              => 'Editar',
    'search'            => 'Buscar',
    'filter'            => 'Filtrar',
    'export'            => 'Exportar',
    'see_all'           => 'Ver todos',
    'loading'           => 'Cargando...',
    'no_results'        => 'Sin resultados',
    'confirm'           => 'Confirmar',
    'back'              => 'Volver',

    /* =========================================================================
       DÍAS DE LA SEMANA
    ========================================================================= */
    'mon' => 'Lun',
    'tue' => 'Mar',
    'wed' => 'Mié',
    'thu' => 'Jue',
    'fri' => 'Vie',
    'sat' => 'Sáb',
    'sun' => 'Dom',

    /* =========================================================================
       NAVEGACIÓN — SIDEBAR
    ========================================================================= */
    'nav_main'          => 'Principal',
    'nav_dashboard'     => 'Dashboard',
    'nav_users'         => 'Usuarios',
    'nav_products'      => 'Productos',
    'nav_transactions'  => 'Transacciones',
    'nav_content'       => 'Contenido',
    'nav_categories'    => 'Categorías',
    'nav_banners'       => 'Banners',
    'nav_notifications' => 'Notificaciones',
    'nav_system'        => 'Sistema',
    'nav_settings'      => 'Configuración',
    'nav_logout'        => 'Cerrar Sesión',

    /* =========================================================================
       TOPBAR
    ========================================================================= */
    'topbar_search_placeholder' => 'Buscar usuarios, productos... (/)',

    /* =========================================================================
       DASHBOARD
    ========================================================================= */
    'admin_dashboard_title' => 'Panel de Administración',
    'dashboard_welcome'     => 'Bienvenido de nuevo',
    'dashboard_subtitle'    => 'Aquí tienes un resumen de la actividad en ASCC.',

    /* =========================================================================
       KPI CARDS
    ========================================================================= */
    'kpi_section_label'      => 'Indicadores clave de rendimiento',
    'kpi_total_users'        => 'Usuarios Totales',
    'kpi_active_products'    => 'Productos Activos',
    'kpi_monthly_sales'      => 'Ventas del Mes',
    'kpi_daily_transactions' => 'Transacciones Hoy',
    'kpi_reported_products'  => 'Productos Reportados',
    'kpi_today'              => 'hoy',
    'kpi_pending'            => 'pendientes de aprobación',
    'kpi_vs_last_month'      => 'vs. mes anterior',
    'kpi_vs_yesterday'       => 'vs. ayer',
    'kpi_requires_attention' => 'Requiere atención',
    'kpi_review_pending'     => 'Revisar pendientes',
    'kpi_review_reports'     => 'Revisar reportes',
    'kpi_see_transactions'   => 'Ver transacciones',

    /* =========================================================================
       ROLES — Coinciden exactamente con el ENUM de la BD:
       enum('vendedor', 'comprador', 'mixto', 'admin')
    ========================================================================= */
    'role_vendedor'    => 'Vendedor',
    'role_vendedores'  => 'Vendedores',
    'role_comprador'   => 'Comprador',
    'role_compradores' => 'Compradores',
    'role_mixto'       => 'Mixto',
    'role_admin'       => 'Administrador',

    /* =========================================================================
       GRÁFICAS
    ========================================================================= */
    'chart_sales_by_category' => 'Ventas por Categoría',
    'chart_new_users'         => 'Nuevos Usuarios',
    'chart_last_7_days'       => 'Últimos 7 días',
    'chart_users_label'       => 'Usuarios',

    /* =========================================================================
       TABLAS
    ========================================================================= */
    'table_recent_transactions' => 'Últimas Transacciones',
    'table_recent_users'        => 'Usuarios Recientes',
    'col_id'                    => 'ID',
    'col_buyer'                 => 'Comprador',
    'col_product'               => 'Producto',
    'col_amount'                => 'Monto',
    'col_status'                => 'Estado',
    'col_name'                  => 'Nombre',
    'col_role'                  => 'Rol',
    'col_location'              => 'Ubicación',
    'col_date'                  => 'Fecha',

    /* =========================================================================
       ESTADOS DE TRANSACCIÓN
    ========================================================================= */
    'status_completed' => 'Completada',
    'status_pending'   => 'Pendiente',
    'status_failed'    => 'Fallida',
    'status_disputed'  => 'En disputa',

    /* =========================================================================
       CATEGORÍAS OFICIALES ASCC (14 categorías)
    ========================================================================= */
    'cat_eggs'       => 'Huevos y Derivados',
    'cat_poultry'    => 'Aves de Corral',
    'cat_cattle'     => 'Ganado Bovino',
    'cat_horses'     => 'Caballos y Equinos',
    'cat_livestock'  => 'Ganado Menor',
    'cat_meats'      => 'Cárnicos y Embutidos',
    'cat_dairy'      => 'Lácteos',
    'cat_vegetables' => 'Verduras y Hortalizas',
    'cat_fruits'     => 'Frutas',
    'cat_grains'     => 'Cereales y Granos',
    'cat_plants'     => 'Plantas y Semillas',
    'cat_processed'  => 'Productos Procesados',
    'cat_fish'       => 'Peces y Acuicultura',

    /* =========================================================================
       LOGIN DE ADMIN
    ========================================================================= */
    'login_page_title'           => 'Acceso Administrador',
    'login_title'                => 'Acceso Seguro',
    'login_subtitle'             => 'Panel exclusivo de administración ASCC',
    'login_brand_title'          => 'Gestiona el marketplace agropecuario de Colombia',
    'login_brand_subtitle'       => 'Controla usuarios, productos, transacciones y toda la operación desde un solo lugar.',
    'login_stat_users'           => 'Usuarios',
    'login_stat_products'        => 'Productos',
    'login_stat_categories'      => 'Categorías',
    'login_email_label'          => 'Correo electrónico',
    'login_email_placeholder'    => 'admin@ascc.co',
    'login_password_label'       => 'Contraseña',
    'login_password_placeholder' => '••••••••••',
    'login_submit_btn'           => 'Ingresar al Panel',
    'login_back_to_site'         => '← Volver al sitio',
    'login_back_link'            => 'ASCC.co',
    'login_invalid_credentials'  => 'Credenciales incorrectas. Te quedan %d intentos.',
    'login_locked'               => 'Acceso bloqueado por %d minutos por seguridad.',
    'login_fields_required'      => 'Por favor completa todos los campos.',
    'login_invalid_csrf'         => 'Token de seguridad inválido. Recarga la página.',

    /* =========================================================================
       MENSAJES DEL SISTEMA
    ========================================================================= */
    'error_unauthorized' => 'No tienes permiso para acceder a esta página.',
    'error_not_found'    => 'El recurso solicitado no fue encontrado.',
    'error_server'       => 'Error del servidor. Por favor intenta de nuevo.',
    'success_saved'      => 'Los cambios han sido guardados correctamente.',
    'success_deleted'    => 'El elemento ha sido eliminado.',

    /* =========================================================================
       USUARIOS — users.php
    ========================================================================= */
    'users_page_title'         => 'Gestión de Usuarios',
    'users_subtitle'           => 'usuarios registrados',
    'users_blocked_count'      => 'bloqueado',
    'users_blocked_count_pl'   => 'bloqueados',
    'users_kpi_total'          => 'Total',
    'users_kpi_sellers'        => 'Vendedores',
    'users_kpi_buyers'         => 'Compradores',
    'users_kpi_mixed'          => 'Mixtos',
    'users_search_placeholder' => 'Buscar por nombre, email o cédula...',
    'users_col_user'           => 'Usuario',
    'users_col_email'          => 'Email',
    'users_col_cedula'         => 'Cédula',
    'users_col_phone'          => 'Teléfono',
    'users_col_role'           => 'Rol',
    'users_col_status'         => 'Estado',
    'users_col_registered'     => 'Registro',
    'users_col_actions'        => 'Acciones',
    'users_empty'              => 'No se encontraron usuarios',
    'users_status_active'      => 'Activo',
    'users_status_blocked'     => 'Bloqueado',
    'users_action_edit'        => 'Editar usuario',
    'users_action_block'       => 'Bloquear usuario',
    'users_action_unblock'     => 'Desbloquear usuario',
    'users_action_delete'      => 'Eliminar usuario',
    'users_confirm_block'      => '¿Bloquear a',
    'users_confirm_unblock'    => '¿Desbloquear a',
    'users_confirm_delete'     => '¿Eliminar a',
    'users_delete_warning'     => 'Esta acción eliminará también sus productos, conversaciones y transacciones. No se puede deshacer.',
    'users_modal_edit_title'   => 'Editar Usuario',
    'users_modal_name'         => 'Nombre completo',
    'users_modal_email'        => 'Correo electrónico',
    'users_modal_phone'        => 'Teléfono',
    'users_modal_cedula'       => 'Cédula',
    'users_modal_cedula_note'  => '(solo lectura)',
    'users_modal_role'         => 'Rol',
    'users_email_duplicate'    => 'Ese correo electrónico ya está en uso por otro usuario.',
    'users_invalid_data'       => 'Datos inválidos. Verifica el formulario.',
    'users_updated'            => 'Usuario actualizado correctamente.',
    'users_status_updated'     => 'Estado del usuario actualizado.',
    'users_deleted'            => 'Usuario eliminado correctamente.',
    'users_op_not_allowed'     => 'Operación no permitida.',
    'users_showing'            => 'Mostrando',
    'users_of'                 => 'de',

    /* =========================================================================
       PRODUCTOS — products.php
    ========================================================================= */
    'products_page_title'         => 'Gestión de Productos',
    'products_kpi_total'          => 'Total productos',
    'products_kpi_available'      => 'Disponibles',
    'products_kpi_sold'           => 'Vendidos',
    'products_kpi_inventory'      => 'Valor inventario',
    'products_search_placeholder' => 'Buscar por código, producto o vendedor...',
    'products_filter_all_states'  => 'Todos los estados',
    'products_filter_all_cats'    => 'Todas las categorías',
    'products_col_num'            => '#',
    'products_col_code'           => 'Código',
    'products_col_product'        => 'Producto',
    'products_col_category'       => 'Categoría',
    'products_col_seller'         => 'Vendedor',
    'products_col_price'          => 'Precio',
    'products_col_quantity'       => 'Cantidad',
    'products_col_status'         => 'Estado',
    'products_col_published'      => 'Publicado',
    'products_col_actions'        => 'Acciones',
    'products_empty'              => 'No se encontraron productos',
    'products_status_available'   => 'Disponible',
    'products_status_sold'        => 'Vendido',
    'products_action_view'        => 'Ver detalle',
    'products_action_toggle'      => 'Cambiar estado',
    'products_action_delete'      => 'Eliminar producto',
    'products_confirm_toggle'     => '¿Cambiar estado a',
    'products_confirm_delete'     => '¿Eliminar',
    'products_delete_warning'     => 'Se eliminarán también sus conversaciones y transacciones. No se puede deshacer.',
    'products_modal_detail_title' => 'Detalle del Producto',
    'products_detail_code'        => 'Código',
    'products_detail_status'      => 'Estado',
    'products_detail_name'        => 'Producto',
    'products_detail_category'    => 'Categoría',
    'products_detail_subcategory' => 'Subcategoría',
    'products_detail_price'       => 'Precio',
    'products_detail_quantity'    => 'Cantidad',
    'products_detail_seller'      => 'Vendedor',
    'products_detail_published'   => 'Publicado',
    'products_detail_description' => 'Descripción',
    'products_no_description'     => 'Sin descripción.',
    'products_status_updated'     => 'Estado del producto actualizado.',
    'products_deleted'            => 'Producto eliminado correctamente.',
    'products_invalid_state'      => 'Estado inválido.',
    'products_invalid_id'         => 'ID de producto inválido.',
    'products_registered'         => 'producto registrado',
    'products_registered_pl'      => 'productos registrados',
    'products_detail_images'      => 'Fotos del Producto',
    'products_detail_no_images'   => 'Sin fotos disponibles.',
    'products_detail_total'       => 'Valor Total',

    /* =========================================================================
       CAMBIO DE CONTRASEÑA
    ========================================================================= */
    'cp_page_title'          => 'Cambiar Contraseña',
    'cp_page_subtitle'       => 'Actualiza tu contraseña de acceso al panel de administración.',
    'cp_nav_label'           => 'Cambiar Contraseña',
    'cp_label_current'       => 'Contraseña actual',
    'cp_label_new'           => 'Nueva contraseña',
    'cp_label_confirm'       => 'Confirmar nueva contraseña',
    'cp_placeholder_current' => 'Ingresa tu contraseña actual',
    'cp_placeholder_new'     => 'Mínimo 8 caracteres',
    'cp_placeholder_confirm' => 'Repite la nueva contraseña',
    'cp_hint_min'            => 'Mínimo 8 caracteres. Usa mayúsculas, números y símbolos para mayor seguridad.',
    'cp_btn_save'            => 'Guardar nueva contraseña',
    'cp_error_empty'         => 'Por favor completa todos los campos.',
    'cp_error_min_length'    => 'La nueva contraseña debe tener al menos 8 caracteres.',
    'cp_error_mismatch'      => 'Las contraseñas nuevas no coinciden.',
    'cp_error_wrong_current' => 'La contraseña actual es incorrecta.',
    'cp_error_user_not_found' => 'Usuario no encontrado.',
    'cp_success'             => 'Contraseña actualizada correctamente.',
    'cp_strength_weak'       => 'Muy débil',
    'cp_strength_fair'       => 'Regular',
    'cp_strength_good'       => 'Buena',
    'cp_strength_strong'     => 'Fuerte',
    'cp_match_ok'            => 'Las contraseñas coinciden',
    'cp_match_fail'          => 'Las contraseñas no coinciden',

    /* =========================================================================
       CATEGORIAS
    ========================================================================= */
    'cat_page_title'      => 'Gestión de Categorías',
    'cat_page_subtitle'   => 'Distribución de productos por categoría agropecuaria en tiempo real.',
    'cat_kpi_total_cats'  => 'Categorías activas',
    'cat_kpi_total_prods' => 'Total productos',
    'cat_kpi_available'   => 'Disponibles',
    'cat_kpi_inventory'   => 'Valor inventario',
    'cat_top_badge'       => '🏆 Top',
    'cat_product_sing'    => 'producto',
    'cat_product_pl'      => 'productos',
    'cat_available_label' => 'disponibles',
    'cat_stat_available'  => 'Disponibles',
    'cat_stat_sold'       => 'Vendidos',
    'cat_stat_sellers'    => 'Vendedores',
    'cat_avg_price'       => 'Precio promedio',
    'cat_see_products'    => 'Ver productos',
    'cat_chart_title'     => 'Productos por Categoría',
    'cat_chart_sub'       => 'Disponibles vs. Vendidos',
    'cat_empty_title'     => 'Sin categorías aún',
    'cat_empty_text'      => 'Aún no hay productos registrados. Las categorías aparecerán aquí cuando los vendedores publiquen sus productos.',
    'cat_empty_btn'       => 'Ver productos',

    /* =========================================================================
       BANNERS
    ========================================================================= */
    'banner_page_title'           => 'Banners',
    'banner_registered'           => 'banners registrados',
    'banner_kpi_total'            => 'Total banners',
    'banner_kpi_active'           => 'Activos',
    'banner_kpi_inactive'         => 'Inactivos',
    'banner_kpi_clicks'           => 'Clics totales',
    'banner_pos_hero'             => 'Hero / Slider principal',
    'banner_pos_secundario'       => 'Banner secundario',
    'banner_pos_categorias'       => 'Sección categorías',
    'banner_pos_sidebar'          => 'Barra lateral',
    'banner_active'               => 'Activo',
    'banner_inactive'             => 'Inactivo',
    'banner_expired'              => 'Expirado',
    'banner_filter_all_positions' => 'Todas las posiciones',
    'banner_filter_all_states'    => 'Todos los estados',
    'banner_clear_filters'        => 'Limpiar filtros',
    'banner_btn_create'           => 'Nuevo banner',
    'banner_btn_save'             => 'Guardar banner',
    'banner_activate'             => 'Activar',
    'banner_deactivate'           => 'Desactivar',
    'banner_delete'               => 'Eliminar',
    'banner_confirm_delete'       => '¿Eliminar este banner? La imagen también se borrará del servidor. No se puede deshacer.',
    'banner_clicks'               => 'clics',
    'banner_order'                => 'Orden de aparición',
    'banner_created'              => 'Banner creado correctamente.',
    'banner_updated'              => 'Estado del banner actualizado.',
    'banner_order_updated'        => 'Orden actualizado correctamente.',
    'banner_deleted'              => 'Banner eliminado correctamente.',
    'banner_error_titulo'         => 'El título del banner es obligatorio.',
    'banner_error_posicion'       => 'La posición seleccionada no es válida.',
    'banner_error_imagen'         => 'Debes seleccionar una imagen para el banner.',
    'banner_modal_title'          => 'Nuevo banner',
    'banner_field_title'          => 'Título',
    'banner_field_title_ph'       => 'Ej: Temporada de papa criolla',
    'banner_field_subtitle'       => 'Subtítulo',
    'banner_field_subtitle_ph'    => 'Ej: Mejores precios directos del campo',
    'banner_field_url'            => 'URL de destino',
    'banner_field_url_ph'         => 'https://... o /ruta-interna',
    'banner_field_alt'            => 'Texto alternativo (accesibilidad)',
    'banner_field_alt_ph'         => 'Describe brevemente la imagen',
    'banner_field_position'       => 'Posición en el marketplace',
    'banner_field_order'          => 'Orden de aparición',
    'banner_field_order_hint'     => '0 = primero en su posición',
    'banner_field_start'          => 'Fecha de inicio',
    'banner_field_end'            => 'Fecha de fin',
    'banner_field_date_hint'      => 'Dejar vacío = sin restricción de fecha',
    'banner_upload_prompt'        => 'Arrastra la imagen aquí o haz clic para seleccionar',
    'banner_upload_hint'          => 'JPG, PNG, WebP · Máximo 3 MB · Mínimo 800px de ancho',
    'banner_toggle_hint'          => 'Visible en el marketplace',
    'banner_toggle_off'           => 'Inactivo — No se mostrará en el marketplace',
    'banner_empty_title'          => 'No hay banners aún',
    'banner_empty_desc'           => 'Crea tu primer banner para comenzar a promocionar ASCC.',

    /* =========================================================================
       NOTIFICACIONES
    ========================================================================= */
    'notif_page_title'          => 'Notificaciones',
    'notif_registered'          => 'notificaciones activas',
    'notif_kpi_total'           => 'Enviadas',
    'notif_kpi_read'            => 'Leídas',
    'notif_kpi_warnings'        => 'Advertencias',
    'notif_kpi_alerts'          => 'Alertas',
    'notif_tipo_info'           => 'Información',
    'notif_tipo_success'        => 'Éxito',
    'notif_tipo_warning'        => 'Advertencia',
    'notif_tipo_danger'         => 'Alerta',
    'notif_dest_todos'          => 'Todos los usuarios',
    'notif_dest_vendedor'       => 'Solo vendedores',
    'notif_dest_comprador'      => 'Solo compradores',
    'notif_dest_mixto'          => 'Solo mixtos',
    'notif_dest_individual'     => 'Usuario específico',
    'notif_dest_por_rol'        => 'Por rol de usuario',
    'notif_filter_all_types'    => 'Todos los tipos',
    'notif_filter_all_dest'     => 'Todos los destinatarios',
    'notif_clear_filters'       => 'Limpiar',
    'notif_btn_create'          => 'Nueva notificación',
    'notif_btn_send'            => 'Enviar notificación',
    'notif_btn_delete_all'      => 'Eliminar todas',
    'notif_action_view'         => 'Ver detalle',
    'notif_action_delete'       => 'Eliminar',
    'notif_confirm_delete'      => '¿Eliminar esta notificación?',
    'notif_confirm_delete_all'  => '¿Eliminar todas las notificaciones activas? No se puede deshacer.',
    'notif_created'             => 'Notificación enviada correctamente.',
    'notif_deleted'             => 'Notificación eliminada.',
    'notif_deleted_all'         => 'Todas las notificaciones fueron eliminadas.',
    'notif_updated'             => 'Notificación actualizada.',
    'notif_error_titulo'        => 'El título es obligatorio.',
    'notif_error_mensaje'       => 'El mensaje es obligatorio.',
    'notif_error_tipo'          => 'El tipo de notificación no es válido.',
    'notif_error_usuario'       => 'Selecciona un usuario destinatario.',
    'notif_error_usuario_404'   => 'El usuario seleccionado no existe.',
    'notif_col_type'            => 'Tipo',
    'notif_col_title'           => 'Título',
    'notif_col_message'         => 'Mensaje',
    'notif_col_dest'            => 'Destinatario',
    'notif_col_read'            => 'Leídas',
    'notif_col_date'            => 'Fecha',
    'notif_col_actions'         => 'Acciones',
    'notif_modal_title'         => 'Nueva notificación',
    'notif_field_title'         => 'Título',
    'notif_field_title_ph'      => 'Ej: Mantenimiento programado esta noche',
    'notif_field_type'          => 'Tipo de notificación',
    'notif_field_dest_type'     => 'Enviar a',
    'notif_field_dest_role'     => 'Rol destinatario',
    'notif_field_dest_user'     => 'Usuario destinatario',
    'notif_field_message'       => 'Mensaje',
    'notif_field_message_ph'    => 'Escribe el contenido de la notificación...',
    'notif_field_preview'       => 'Vista previa',
    'notif_select_user'         => '— Selecciona un usuario —',
    'notif_preview_placeholder'     => 'Escribe un título...',
    'notif_preview_msg_placeholder' => 'El mensaje aparecerá aquí',
    'notif_detail_dest'         => 'Destinatario',
    'notif_detail_date'         => 'Fecha de envío',
    'notif_detail_reads'        => 'Veces leída',
    'notif_detail_message'      => 'Mensaje completo',
    'notif_empty_title'         => 'No hay notificaciones',
    'notif_empty_desc'          => 'Crea la primera notificación para comunicarte con tus usuarios.',
    'close'                     => 'Cerrar',

    /* =========================================================================
       CONFIGURACIÓN DEL SISTEMA — configuracion.php
    ========================================================================= */

    // Página principal
    'cfg_page_title'          => 'Configuración del Sistema',
    'cfg_page_subtitle'       => 'Administra todos los parámetros globales de ASCC desde un solo lugar.',
    'cfg_save_all'            => 'Guardar Todo',
    'cfg_save_tab'            => 'Guardar Cambios',
    'cfg_discard'             => 'Descartar',
    'cfg_footer_hint'         => 'Los cambios se guardan en la tabla',
    'cfg_saved_ok'            => '✅ Configuración guardada correctamente.',
    'cfg_saved_error'         => '❌ Error al guardar. Intenta de nuevo.',
    'cfg_test_smtp_ok'        => '📧 Correo de prueba enviado correctamente.',
    'cfg_test_smtp_error'     => '❌ No se pudo conectar al servidor SMTP.',
    'cfg_required_field'      => 'Este campo es obligatorio.',

    // Tabs
    'cfg_tab_general'         => 'General',
    'cfg_tab_correo'          => 'Correo SMTP',
    'cfg_tab_pagos'           => 'Pagos',
    'cfg_tab_seo'             => 'SEO',
    'cfg_tab_seguridad'       => 'Seguridad',
    'cfg_tab_social'          => 'Redes Sociales',
    'cfg_tab_regional'        => 'Regional',

    // Tab General
    'cfg_gen_title'           => 'Información del Sitio',
    'cfg_gen_subtitle'        => 'Datos principales de la plataforma',
    'cfg_gen_nombre'          => 'Nombre del Sitio',
    'cfg_gen_slogan'          => 'Slogan',
    'cfg_gen_email'           => 'Email de Contacto',
    'cfg_gen_telefono'        => 'Teléfono / WhatsApp',
    'cfg_gen_direccion'       => 'Dirección',
    'cfg_gen_descripcion'     => 'Descripción del Sitio',
    'cfg_gen_desc_hint'       => 'Aparece en el pie de página y en algunas secciones del sitio público.',
    'cfg_logo_title'          => 'Logo y Favicon',
    'cfg_logo_subtitle'       => 'Identidad visual del sitio',
    'cfg_logo_label'          => 'Logo Principal',
    'cfg_logo_change'         => 'Clic para cambiar logo',
    'cfg_logo_hint'           => 'PNG, SVG · Máx. 2MB · Fondo transparente recomendado',
    'cfg_color_label'         => 'Color Principal',
    'cfg_favicon_label'       => 'Favicon',
    'cfg_favicon_hint'        => '32×32px recomendado',
    'cfg_favicon_upload'      => 'Subir favicon (.ico / .png)',

    // Tab Correo
    'cfg_smtp_title'          => 'Servidor SMTP',
    'cfg_smtp_subtitle'       => 'Configuración de salida de correos',
    'cfg_smtp_host'           => 'Host SMTP',
    'cfg_smtp_puerto'         => 'Puerto',
    'cfg_smtp_cifrado'        => 'Cifrado',
    'cfg_smtp_usuario'        => 'Usuario SMTP',
    'cfg_smtp_password'       => 'Contraseña SMTP',
    'cfg_smtp_test_btn'       => '🧪 Enviar correo de prueba',
    'cfg_smtp_tls'            => '587 — TLS (recomendado)',
    'cfg_smtp_ssl'            => '465 — SSL',
    'cfg_smtp_none'           => '25 — Sin cifrado',
    'cfg_from_title'          => 'Remitente',
    'cfg_from_subtitle'       => 'Nombre y dirección de envío',
    'cfg_from_nombre'         => 'Nombre del Remitente',
    'cfg_from_email'          => 'Email Remitente',
    'cfg_from_reply'          => 'Email para Respuestas',
    'cfg_correo_bienvenida'   => 'Correo de bienvenida',
    'cfg_correo_bienvenida_d' => 'Enviar al registrar nuevo usuario',
    'cfg_correo_pedido'       => 'Confirmación de pedido',
    'cfg_correo_pedido_d'     => 'Notificar al comprador y vendedor',
    'cfg_correo_alertas'      => 'Alertas al admin',
    'cfg_correo_alertas_d'    => 'Nuevos usuarios, pedidos, reportes',

    // Tab Pagos
    'cfg_pago_title'          => 'Pasarela de Pagos Activa',
    'cfg_pago_subtitle'       => 'Selecciona el proveedor para transacciones en ASCC',
    'cfg_pago_pse_desc'       => 'Débito bancario directo · ACH Colombia',
    'cfg_pago_wompi_desc'     => 'Bancolombia · Tarjetas · Nequi',
    'cfg_pago_payu_desc'      => 'Pagos internacionales y nacionales',
    'cfg_pago_mp_desc'        => 'Múltiples métodos · Wallet',
    'cfg_keys_title'          => 'Credenciales API',
    'cfg_keys_subtitle'       => 'Claves de integración',
    'cfg_pago_public_key'     => 'Public Key',
    'cfg_pago_secret_key'     => 'Private Key / Secret',
    'cfg_pago_entorno'        => 'Entorno',
    'cfg_pago_sandbox'        => '🧪 Sandbox (Pruebas)',
    'cfg_pago_produccion'     => '🚀 Producción',
    'cfg_comision_title'      => 'Comisiones',
    'cfg_comision_subtitle'   => 'Porcentaje por transacción',
    'cfg_comision_label'      => 'Comisión de la plataforma (%)',
    'cfg_comision_hint'       => 'Porcentaje sobre cada venta, descontado al vendedor.',
    'cfg_iva_label'           => 'IVA aplicado (%)',
    'cfg_pago_efectivo'       => 'Pagos en efectivo',
    'cfg_pago_efectivo_d'     => 'Efecty, Baloto, corresponsal',
    'cfg_pago_transferencia'  => 'Transferencia bancaria',
    'cfg_pago_transferencia_d' => 'Pago manual verificado por admin',

    // Tab SEO
    'cfg_seo_title'           => 'Meta Tags Globales',
    'cfg_seo_subtitle'        => 'Posicionamiento en buscadores',
    'cfg_seo_meta_title'      => 'Meta Title',
    'cfg_seo_meta_title_hint' => 'Recomendado: 50–60 caracteres',
    'cfg_seo_meta_desc'       => 'Meta Description',
    'cfg_seo_meta_desc_hint'  => 'Recomendado: 150–160 caracteres',
    'cfg_seo_keywords'        => 'Palabras Clave (Keywords)',
    'cfg_seo_keywords_hint'   => 'Separadas por coma',
    'cfg_seo_ga'              => 'Google Analytics ID',
    'cfg_seo_gsc'             => 'Google Search Console',
    'cfg_seo_gsc_hint'        => 'Código de verificación',
    'cfg_og_title_label'      => 'OG Title',
    'cfg_og_desc_label'       => 'OG Description',
    'cfg_og_image_label'      => 'OG Image',
    'cfg_og_image_hint'       => '1200×630px recomendado · JPG/PNG · Máx. 1MB',
    'cfg_og_image_upload'     => 'Imagen para compartir en redes',
    'cfg_og_title_card'       => 'Open Graph / Social',
    'cfg_og_subtitle_card'    => 'Previsualización al compartir en redes',
    'cfg_seo_sitemap'         => 'Sitemap XML automático',
    'cfg_seo_sitemap_d'       => 'Generar /sitemap.xml para Google',
    'cfg_seo_robots'          => 'Robots.txt',
    'cfg_seo_robots_d'        => 'Permitir indexación de buscadores',

    // Tab Seguridad
    'cfg_seg_title'           => 'Control de Acceso',
    'cfg_seg_subtitle'        => 'Parámetros de login y sesiones',
    'cfg_seg_intentos'        => 'Intentos máx. de login fallido',
    'cfg_seg_intentos_hint'   => 'Después de N intentos, la cuenta se bloquea temporalmente.',
    'cfg_seg_bloqueo'         => 'Tiempo de bloqueo (minutos)',
    'cfg_seg_sesion'          => 'Duración de sesión (horas)',
    'cfg_seg_verify_email'    => 'Verificación de email al registro',
    'cfg_seg_verify_email_d'  => 'Usuario debe confirmar su correo',
    'cfg_seg_recaptcha'       => 'reCAPTCHA en login',
    'cfg_seg_recaptcha_d'     => 'Protección contra bots',
    'cfg_mant_title'          => 'Modo Mantenimiento',
    'cfg_mant_subtitle'       => 'Control de disponibilidad del sitio',
    'cfg_mant_active_label'   => 'ACTIVO',
    'cfg_mant_active_msg'     => 'Los usuarios verán la página de mantenimiento',
    'cfg_mant_toggle'         => 'Activar mantenimiento',
    'cfg_mant_toggle_d'       => 'El sitio público mostrará aviso de mantenimiento',
    'cfg_mant_mensaje'        => 'Mensaje de mantenimiento',
    'cfg_mant_fecha'          => 'Fecha estimada de retorno',
    'cfg_mant_ips'            => 'IPs permitidas (acceso admin)',
    'cfg_mant_ips_hint'       => 'Una IP por línea. Los admins siempre pueden acceder.',

    // Tab Social
    'cfg_social_title'        => 'Redes Sociales',
    'cfg_social_subtitle'     => 'Links oficiales de ASCC',
    'cfg_integ_title'         => 'Integraciones Externas',
    'cfg_integ_subtitle'      => 'Widgets y botones en el sitio público',
    'cfg_wa_widget'           => 'Botón flotante WhatsApp',
    'cfg_wa_widget_d'         => 'Widget en esquina del sitio público',
    'cfg_fb_pixel'            => 'Píxel de Facebook',
    'cfg_fb_pixel_d'          => 'Para campañas de remarketing',
    'cfg_fb_pixel_id'         => 'Facebook Pixel ID',
    'cfg_share_btn'           => 'Compartir producto en redes',
    'cfg_share_btn_d'         => 'Botones share en cada producto',

    // Tab Regional
    'cfg_reg_title'           => 'Configuración Regional',
    'cfg_reg_subtitle'        => 'Moneda, zona horaria e idioma',
    'cfg_reg_pais'            => 'País por defecto',
    'cfg_reg_moneda'          => 'Moneda',
    'cfg_reg_formato'         => 'Formato de precio',
    'cfg_reg_formato_hint'    => "Formato visual usando toLocaleString('es-CO')",
    'cfg_reg_timezone'        => 'Zona Horaria',
    'cfg_reg_idioma'          => 'Idioma por defecto',
    'cfg_reg_idioma_toggle'   => 'Selector de idioma visible',
    'cfg_reg_idioma_toggle_d' => 'Mostrar toggle ES/EN a los usuarios',
    'cfg_envio_title'         => 'Envíos y Logística',
    'cfg_envio_subtitle'      => 'Parámetros de entrega',
    'cfg_envio_cobertura'     => 'Cobertura de envíos',
    'cfg_envio_nacional'      => 'Nacional (toda Colombia)',
    'cfg_envio_bogota'        => 'Solo Bogotá y alrededores',
    'cfg_envio_punto'         => 'Solo retiro en punto',
    'cfg_envio_base'          => 'Costo de envío base (COP)',
    'cfg_envio_base_hint'     => 'El vendedor puede sobrescribir este valor por producto.',
    'cfg_envio_gratis'        => 'Envío gratis desde monto',
    'cfg_envio_gratis_d'      => 'Incentivar compras grandes',
    'cfg_envio_minimo'        => 'Monto mínimo para envío gratis (COP)',
    'cfg_maps'                => 'Integración Google Maps',
    'cfg_maps_d'              => 'Dirección con mapa en checkout',
    'cfg_maps_key'            => 'Google Maps API Key',

    // =========================================================================
    // MÓDULO REPORTES ADMIN
    // =========================================================================
    'nav_reports'                    => 'Reportes',
    'rep_admin_title'                => 'Reportes de Plataforma',
    'rep_admin_subtitle'             => 'Métricas globales de ASCC — Aromas y Sabores de mi Campo Colombiano',

    // Tabs
    'rep_tab_metricas'               => 'Métricas',
    'rep_tab_usuarios'               => 'Usuarios',
    'rep_tab_productos'              => 'Productos',
    'rep_tab_denuncias'              => 'Denuncias',
    'rep_tab_ranking'                => 'Ranking',
    'rep_tab_exportar'               => 'Exportar',

    // KPIs globales
    'rep_kpi_usuarios_total'         => 'Usuarios totales',
    'rep_kpi_usuarios_hoy'           => 'Nuevos hoy',
    'rep_kpi_productos_activos'      => 'Productos activos',
    'rep_kpi_productos_hoy'          => 'Publicados hoy',
    'rep_kpi_ventas_mes'             => 'Ventas del mes',
    'rep_kpi_ventas_vs'              => 'vs mes anterior',
    'rep_kpi_denuncias_abiertas'     => 'Denuncias abiertas',
    'rep_kpi_denuncias_urgentes'     => 'urgentes',
    'rep_kpi_visitas_hoy'            => 'Visitas hoy',
    'rep_kpi_conversion'             => 'Conversión global',

    // Gráficas
    'rep_graf_ventas_diarias'        => 'Ventas diarias — últimos 30 días',
    'rep_graf_categorias'            => 'Distribución por categoría',
    'rep_graf_usuarios_rol'          => 'Usuarios por rol',
    'rep_graf_top_vendedores'        => 'Top 10 vendedores',
    'rep_graf_top_productos'         => 'Top 10 productos más vistos',
    'rep_graf_actividad_hora'        => 'Actividad por hora del día',

    // Tab usuarios
    'rep_usr_titulo'                 => 'Gestión de usuarios',
    'rep_usr_todos'                  => 'Todos',
    'rep_usr_vendedores'             => 'Vendedores',
    'rep_usr_compradores'            => 'Compradores',
    'rep_usr_mixtos'                 => 'Mixtos',
    'rep_usr_col_nombre'             => 'Nombre',
    'rep_usr_col_rol'                => 'Rol',
    'rep_usr_col_productos'          => 'Productos',
    'rep_usr_col_ventas'             => 'Ventas',
    'rep_usr_col_calificacion'       => 'Calificación',
    'rep_usr_col_registro'           => 'Registro',
    'rep_usr_col_estado'             => 'Estado',
    'rep_usr_col_acciones'           => 'Acciones',
    'rep_usr_suspender'              => 'Suspender',
    'rep_usr_activar'                => 'Activar',
    'rep_usr_ver_perfil'             => 'Ver perfil',
    'rep_usr_sin_datos'              => 'No hay usuarios registrados',

    // Tab productos
    'rep_prod_titulo'                => 'Inventario global de productos',
    'rep_prod_col_codigo'            => 'Código',
    'rep_prod_col_producto'          => 'Producto',
    'rep_prod_col_vendedor'          => 'Vendedor',
    'rep_prod_col_categoria'         => 'Categoría',
    'rep_prod_col_precio'            => 'Precio',
    'rep_prod_col_stock'             => 'Stock',
    'rep_prod_col_visitas'           => 'Visitas',
    'rep_prod_col_estado'            => 'Estado',
    'rep_prod_col_fecha'             => 'Publicado',
    'rep_prod_sin_datos'             => 'No hay productos registrados',

    // Tab denuncias
    'rep_den_titulo'                 => 'Gestión de denuncias',
    'rep_den_todas'                  => 'Todas',
    'rep_den_recibidas'              => 'Recibidas',
    'rep_den_en_revision'            => 'En revisión',
    'rep_den_resueltas'              => 'Resueltas',
    'rep_den_col_id'                 => 'ID',
    'rep_den_col_denunciante'        => 'Denunciante',
    'rep_den_col_denunciado'         => 'Denunciado',
    'rep_den_col_categoria'          => 'Categoría',
    'rep_den_col_prioridad'          => 'Prioridad',
    'rep_den_col_estado'             => 'Estado',
    'rep_den_col_fecha'              => 'Fecha',
    'rep_den_col_acciones'           => 'Acciones',
    'rep_den_resolver'               => 'Resolver',
    'rep_den_cerrar'                 => 'Cerrar',
    'rep_den_sin_datos'              => 'No hay denuncias registradas',
    'rep_den_cambiar_estado'         => 'Cambiar estado',
    'rep_den_respuesta'              => 'Respuesta del administrador',
    'rep_den_guardar'                => 'Guardar respuesta',

    // Prioridades y estados
    'rep_prioridad_alta'             => 'Alta',
    'rep_prioridad_media'            => 'Media',
    'rep_prioridad_baja'             => 'Baja',
    'rep_estado_recibida'            => 'Recibida',
    'rep_estado_en_revision'         => 'En revisión',
    'rep_estado_pendiente_vendedor'  => 'Pend. vendedor',
    'rep_estado_resuelta'            => 'Resuelta',
    'rep_estado_cerrada'             => 'Cerrada',

    // Tab ranking
    'rep_rank_titulo'                => 'Ranking global de la plataforma',
    'rep_rank_top_vendedores'        => 'Top vendedores mejor valorados',
    'rep_rank_top_compradores'       => 'Compradores más activos',
    'rep_rank_col_pos'               => '#',
    'rep_rank_col_nombre'            => 'Nombre',
    'rep_rank_col_calificacion'      => 'Calificación',
    'rep_rank_col_resenas'           => 'Reseñas',
    'rep_rank_col_ventas'            => 'Ventas',
    'rep_rank_col_compras'           => 'Compras',

    // Tab exportar
    'rep_exp_titulo'                 => 'Exportar datos de plataforma',
    'rep_exp_excel'                  => 'Excel completo',
    'rep_exp_csv'                    => 'CSV para Power BI',
    'rep_exp_usuarios'               => 'Usuarios',
    'rep_exp_productos'              => 'Productos',
    'rep_exp_ventas'                 => 'Ventas',
    'rep_exp_denuncias'              => 'Denuncias',
    'rep_exp_visitas'                => 'Visitas',

    // Alertas automáticas
    'rep_alerta_suspendido'          => 'Cuenta con 5+ denuncias — suspendida automáticamente',
    'rep_alerta_advertencia'         => 'Cuenta con 3+ denuncias — requiere revisión',
    'rep_alerta_ok'                  => 'Cuenta en buen estado',

    // =========================================================================
    // MÓDULO REVIEWS ADMIN — claves completas
    // =========================================================================
    'nav_reviews'                    => 'Reseñas',
    'reviews_admin_title'            => 'Gestión de Reseñas',
    'reviews_admin_subtitle'         => 'Modera las reseñas de productos y vendedores',

    // KPIs
    'reviews_kpi_label'              => 'Métricas de reseñas',
    'reviews_kpi_total'              => 'Total reseñas',
    'reviews_kpi_this_week'          => 'esta semana',
    'reviews_kpi_productos'          => 'Reseñas de productos',
    'reviews_kpi_vendedores'         => 'Reseñas de vendedores',
    'reviews_kpi_compradores'        => 'Reseñas de compradores',
    'reviews_kpi_promedio'           => 'Calificación promedio',
    'reviews_kpi_ver'                => 'Ver todas',

    // Filtros
    'reviews_filter_todos'           => 'Todos',
    'reviews_filter_producto'        => 'Productos',
    'reviews_filter_vendedor'        => 'Vendedores',
    'reviews_filter_comprador'       => 'Compradores',

    // Buscador
    'reviews_search_placeholder'     => 'Buscar por autor o comentario...',

    // Tabla
    'reviews_table_title'            => 'Reseñas',
    'reviews_col_tipo'               => 'Tipo',
    'reviews_col_autor'              => 'Autor',
    'reviews_col_entidad'            => 'Reseñado',
    'reviews_col_calificacion'       => 'Calificación',
    'reviews_col_comentario'         => 'Comentario',
    'reviews_col_fecha'              => 'Fecha',
    'reviews_col_acciones'           => 'Acciones',

    // Tipos de reseña
    'reviews_tipo_producto'          => 'Producto',
    'reviews_tipo_vendedor'          => 'Vendedor',
    'reviews_tipo_comprador'         => 'Comprador',

    // Acciones
    'reviews_action_ver'             => 'Ver detalle',
    'reviews_action_eliminar'        => 'Eliminar',
    'reviews_eliminar'               => 'Eliminar',

    // Paginación
    'reviews_mostrando'              => 'Mostrando',
    'reviews_de'                     => 'de',

    // Modal detalle
    'reviews_modal_title'            => 'Detalle de reseña',
    'reviews_confirm_delete_title'   => 'Confirmar eliminación',
    'reviews_confirm_delete_msg'     => '¿Estás seguro de que deseas eliminar esta reseña? Esta acción no se puede deshacer.',

    // Estado
    'reviews_deleted'                => 'Reseña eliminada correctamente.',
    'reviews_error'                  => 'Error al eliminar la reseña.',
    'reviews_empty'                  => 'No hay reseñas que coincidan con los filtros.',
    'reviews_sin_datos'              => 'Sin reseñas registradas',

    // Genéricos usados en reviews
    'close'                          => 'Cerrar',
    'delete'                         => 'Eliminar',
    'cancel'                         => 'Cancelar',

    // =========================================================================
    // MÓDULO TRANSACTIONS ADMIN — claves completas
    // =========================================================================
    'txn_page_title'                 => 'Transacciones',
    'txn_registered'                 => 'transacciones registradas',

    // Estados
    'txn_status_pendiente'           => 'Pendiente',
    'txn_status_aprobado'            => 'Aprobado',
    'txn_status_rechazado'           => 'Rechazado',
    'txn_status_cancelado'           => 'Cancelado',

    // KPIs
    'txn_kpi_total'                  => 'Total transacciones',
    'txn_kpi_aprobadas'              => 'Aprobadas',
    'txn_kpi_pendientes'             => 'Pendientes',
    'txn_kpi_rechazadas'             => 'Rechazadas/Canceladas',
    'txn_kpi_monto'                  => 'Monto total aprobado',

    // Filtros
    'txn_search_placeholder'         => 'Buscar por referencia, comprador o producto...',
    'txn_filter_all_states'          => 'Todos los estados',
    'txn_filter_all_methods'         => 'Todos los métodos',

    // Columnas tabla
    'txn_col_ref'                    => 'Referencia',
    'txn_col_product'                => 'Producto',
    'txn_col_buyer'                  => 'Comprador',
    'txn_col_seller'                 => 'Vendedor',
    'txn_col_amount'                 => 'Total',
    'txn_col_method'                 => 'Método',
    'txn_col_status'                 => 'Estado',
    'txn_col_date'                   => 'Fecha',
    'txn_col_actions'                => 'Acciones',

    // Acciones
    'txn_action_view'                => 'Ver detalle',
    'txn_empty'                      => 'Sin transacciones para',

    // Modal detalle
    'txn_modal_title'                => 'Detalle de transacción',
    'txn_detail_product'             => 'Producto',
    'txn_detail_buyer'               => 'Comprador',
    'txn_detail_seller'              => 'Vendedor',
    'txn_detail_method'              => 'Método de pago',
    'txn_detail_bank'                => 'Banco',
    'txn_detail_date'                => 'Fecha',
    'txn_detail_qty'                 => 'Cantidad',
    'txn_detail_unit_price'          => 'Precio unitario',
    'txn_detail_total'               => 'Total',
    'txn_detail_data'                => 'Datos adicionales del pago',
    'txn_no_data'                    => 'Sin datos adicionales',

];
