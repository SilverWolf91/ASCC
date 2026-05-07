/**
 * ═══════════════════════════════════════════════════════════
 * MENSAJES.JS - LÓGICA DEL CHAT
 * ═══════════════════════════════════════════════════════════
 */

console.log('[MENSAJES] Inicializando sistema de chat...');

// ══════════════════════════════════════════════════════════
// VARIABLES GLOBALES
// ══════════════════════════════════════════════════════════

let SESSION_USER_ID = null;
let conversacionActiva = null;
let pollingInterval = null;
let ultimoMensajeId = 0;

// ══════════════════════════════════════════════════════════
// INICIALIZACIÓN
// ══════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function() {
    // Obtener ID del usuario
    const metaUserId = document.querySelector('meta[name="user-id"]');
    if (metaUserId) {
        SESSION_USER_ID = parseInt(metaUserId.getAttribute('content'));
        console.log('[MENSAJES] Usuario ID:', SESSION_USER_ID);
    } else {
        console.error('[MENSAJES] No se encontró user-id');
    }
    
    // Iniciar polling para lista de conversaciones
    iniciarPollingConversaciones();
});

// ══════════════════════════════════════════════════════════
// SELECCIONAR CONVERSACIÓN
// ══════════════════════════════════════════════════════════

function selectConversation(conversationId) {
    console.log('[MENSAJES] Seleccionando conversación:', conversationId);
    
    // Detener polling anterior
    detenerPollingMensajes();
    
    // Guardar conversación activa
    conversacionActiva = conversationId;
    
    // Marcar visualmente como activa
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    
    const selectedItem = document.querySelector(`.conversation-item[data-conversation-id="${conversationId}"]`);
    if (selectedItem) {
        selectedItem.classList.add('active');
        // Quitar badge de no leído
        selectedItem.classList.remove('unread');
        const badge = selectedItem.querySelector('.conversation-badge');
        if (badge) badge.remove();
    }
    
    // Cargar mensajes
    loadMessages(conversationId);
    
    // Iniciar polling de mensajes cada 2 segundos
    iniciarPollingMensajes();
}

// ══════════════════════════════════════════════════════════
// CARGAR MENSAJES
// ══════════════════════════════════════════════════════════

function loadMessages(conversationId) {
    console.log('[MENSAJES] Cargando mensajes...');
    
    fetch(`/ascc/backend/users/controllers/MensajesController.php?accion=obtener_mensajes&id_conversacion=${conversationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderChat(data.mensajes, data.producto, data.es_vendedor);
            } else {
                showNotification('Error al cargar mensajes', 'error');
            }
        })
        .catch(error => {
            console.error('[MENSAJES] Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// ══════════════════════════════════════════════════════════
// RENDERIZAR CHAT COMPLETO
// ══════════════════════════════════════════════════════════

function renderChat(mensajes, producto, esVendedor) {
    console.log('[MENSAJES] Renderizando', mensajes.length, 'mensajes');
    
    const chatArea = document.querySelector('.chat-area');
    if (!chatArea) return;
    
    // Guardar ID del último mensaje
    if (mensajes.length > 0) {
        ultimoMensajeId = Math.max(...mensajes.map(m => parseInt(m.id_mensaje)));
    }
    
    // Limpiar área
    chatArea.innerHTML = '';
    
    // ═══════════════════════════════════════════════════════
    // HEADER CON INFO DEL PRODUCTO
    // ═══════════════════════════════════════════════════════
    
    const header = document.createElement('div');
    header.className = 'chat-header';
    
    const imagenUrl = producto.imagen 
        ? `/ascc/frontend/users/public/${producto.imagen}` 
        : '/ascc/frontend/users/public/img/no-image.png';
    
    header.innerHTML = `
        <div class="chat-product-info">
            <img src="${imagenUrl}" 
                 alt="${escapeHtml(producto.tipo_producto)}" 
                 class="chat-product-image"
                 onclick="openProductModal()"
                 style="cursor: pointer;"
                 title="Click para ver detalles del producto"
                 onerror="this.src='/ascc/frontend/users/public/img/no-image.png'">
            <div class="chat-product-details">
                <h3 class="chat-product-title">${escapeHtml(producto.tipo_producto)}</h3>
                <div class="chat-product-price">$${formatNumber(producto.precio)}</div>
                <div class="chat-product-quantity">${producto.cantidad} ${producto.unidad}</div>
            </div>
        </div>
        <div class="chat-actions">
            <button class="btn-chat-action" onclick="limpiarConversacion()" title="Limpiar mensajes">
                🧹
            </button>
            <button class="btn-chat-action btn-danger" onclick="eliminarConversacion()" title="Eliminar conversación">
                🗑️
            </button>
        </div>
    `;
    
    chatArea.appendChild(header);
    
    // ═══════════════════════════════════════════════════════
    // CUERPO DE MENSAJES
    // ═══════════════════════════════════════════════════════
    
    const messagesBody = document.createElement('div');
    messagesBody.className = 'chat-messages-body';
    messagesBody.id = 'chatMessagesBody';
    
    if (mensajes.length === 0) {
        messagesBody.innerHTML = `
            <div class="chat-empty-messages">
                <div class="chat-empty-messages-icon">💬</div>
                <p>No hay mensajes aún</p>
                <p style="font-size: 14px; margin-top: 8px;">Envía el primer mensaje</p>
            </div>
        `;
    } else {
        mensajes.forEach(msg => {
            const isSent = (SESSION_USER_ID === parseInt(msg.id_remitente));
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-message ${isSent ? 'sent' : 'received'}`;
            
            const fecha = new Date(msg.fecha_envio);
            const hora = fecha.toLocaleTimeString('es-CO', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            messageDiv.innerHTML = `
                <div class="chat-message-bubble">
                    <div class="chat-message-text">${escapeHtml(msg.mensaje)}</div>
                    <div class="chat-message-time">${hora}</div>
                </div>
            `;
            
            messagesBody.appendChild(messageDiv);
        });
    }
    
    chatArea.appendChild(messagesBody);
    
    // ═══════════════════════════════════════════════════════
    // INPUT PARA ESCRIBIR
    // ═══════════════════════════════════════════════════════
    
    const inputContainer = document.createElement('div');
    inputContainer.className = 'chat-input-container';
    inputContainer.innerHTML = `
        <textarea 
            class="chat-input" 
            id="messageInput" 
            placeholder="Escribe un mensaje..."
            rows="1"></textarea>
        <button class="btn-send-message" onclick="sendMessage()">
            📤 Enviar
        </button>
    `;
    
    chatArea.appendChild(inputContainer);
    
    // Auto-resize del textarea
    const textarea = document.getElementById('messageInput');
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    // Enviar con Enter (Shift+Enter = nueva línea)
    textarea.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    // Scroll al final
    setTimeout(() => {
        messagesBody.scrollTop = messagesBody.scrollHeight;
    }, 100);
    
    // Focus en el input
    textarea.focus();
}

// ══════════════════════════════════════════════════════════
// ENVIAR MENSAJE
// ══════════════════════════════════════════════════════════

function sendMessage() {
    const input = document.getElementById('messageInput');
    const mensaje = input.value.trim();
    
    if (!mensaje) {
        return;
    }
    
    if (!conversacionActiva) {
        showNotification('No hay conversación seleccionada', 'error');
        return;
    }
    
    console.log('[MENSAJES] Enviando:', mensaje);
    
    // Deshabilitar input
    input.disabled = true;
    const btn = document.querySelector('.btn-send-message');
    btn.disabled = true;
    
    fetch('/ascc/backend/users/controllers/MensajesController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `accion=enviar_mensaje&id_conversacion=${conversacionActiva}&mensaje=${encodeURIComponent(mensaje)}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                input.style.height = 'auto';
                loadMessages(conversacionActiva);
            } else {
                showNotification('Error al enviar mensaje', 'error');
            }
        })
        .catch(error => {
            console.error('[MENSAJES] Error:', error);
            showNotification('Error de conexión', 'error');
        })
        .finally(() => {
            input.disabled = false;
            btn.disabled = false;
            input.focus();
        });
}

// ══════════════════════════════════════════════════════════
// LIMPIAR CONVERSACIÓN
// ══════════════════════════════════════════════════════════

function limpiarConversacion() {
    if (!conversacionActiva) return;
    
    if (!confirm('¿Eliminar todos los mensajes de esta conversación?\n\nLa conversación se mantendrá activa.')) {
        return;
    }
    
    console.log('[MENSAJES] Limpiando conversación:', conversacionActiva);
    
    fetch('/ascc/backend/users/controllers/MensajesController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `accion=limpiar_conversacion&id_conversacion=${conversacionActiva}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Mensajes eliminados', 'success');
                loadMessages(conversacionActiva);
            } else {
                showNotification('Error al limpiar conversación', 'error');
            }
        })
        .catch(error => {
            console.error('[MENSAJES] Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// ══════════════════════════════════════════════════════════
// ELIMINAR CONVERSACIÓN
// ══════════════════════════════════════════════════════════

function eliminarConversacion() {
    if (!conversacionActiva) return;
    
    if (!confirm('¿Eliminar esta conversación completa?\n\n⚠️ Esta acción NO se puede deshacer.')) {
        return;
    }
    
    console.log('[MENSAJES] Eliminando conversación:', conversacionActiva);
    
    fetch('/ascc/backend/users/controllers/MensajesController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `accion=eliminar_conversacion&id_conversacion=${conversacionActiva}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Conversación eliminada', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification('Error al eliminar conversación', 'error');
            }
        })
        .catch(error => {
            console.error('[MENSAJES] Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

// ══════════════════════════════════════════════════════════
// UTILIDADES
// ══════════════════════════════════════════════════════════

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatNumber(num) {
    return new Intl.NumberFormat('es-CO').format(num);
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideIn 0.3s ease-out reverse';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ══════════════════════════════════════════════════════════
// MODAL DEL PRODUCTO
// ══════════════════════════════════════════════════════════

let productoActual = null;

function openProductModal() {
    if (!conversacionActiva) return;
    
    console.log('[MENSAJES] Abriendo modal del producto...');
    
    // Cargar datos completos del producto
    fetch(`/ascc/backend/users/controllers/ProductoController.php?accion=obtener_producto&id_conversacion=${conversacionActiva}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarProductoEnModal(data.producto);
            } else {
                showNotification('Error al cargar producto', 'error');
            }
        })
        .catch(error => {
            console.error('[MENSAJES] Error:', error);
            showNotification('Error de conexión', 'error');
        });
}

function mostrarProductoEnModal(producto) {
    console.log('[MENSAJES] Mostrando producto:', producto);
    
    productoActual = producto;
    
    const modal = document.getElementById('productModal');
    const modalBody = document.getElementById('productModalBody');
    
    // Preparar imágenes
    const imagenes = producto.imagenes || [];
    const imagenPrincipal = imagenes.length > 0 ? `/ascc/frontend/users/public/${imagenes[0]}` : '/ascc/frontend/users/public/img/no-image.png';
    
    // Generar thumbnails
    let thumbnailsHTML = '';
    if (imagenes.length > 1) {
        thumbnailsHTML = imagenes.map((img, index) => `
            <img src="/ascc/frontend/users/public/${img}" 
                 class="product-detail-thumbnail ${index === 0 ? 'active' : ''}" 
                 onclick="cambiarImagenProducto(${index})"
                 onerror="this.src='/ascc/frontend/users/public/img/no-image.png'">
        `).join('');
    }
    
    modalBody.innerHTML = `
        <div class="product-detail-card">
            <div class="product-detail-images">
                <img src="${imagenPrincipal}" 
                     id="mainProductImage"
                     class="product-detail-main-image"
                     onerror="this.src='/ascc/frontend/users/public/img/no-image.png'">
                ${imagenes.length > 1 ? `
                    <div class="product-detail-thumbnails">
                        ${thumbnailsHTML}
                    </div>
                ` : ''}
            </div>
            
            <div class="product-detail-info">
                <h2>${escapeHtml(producto.tipo_producto)}</h2>
                <div class="product-detail-price">$${formatNumber(producto.precio)}</div>
                
                <div class="product-detail-section">
                    <h3>Información General</h3>
                    <div class="product-detail-row">
                        <span class="product-detail-label">Cantidad</span>
                        <span class="product-detail-value">${producto.cantidad} ${producto.unidad}</span>
                    </div>
                    <div class="product-detail-row">
                        <span class="product-detail-label">Estado</span>
                        <span class="product-detail-value">${producto.estado === 'disponible' ? '✅ Disponible' : '❌ Vendido'}</span>
                    </div>
                    ${producto.categoria_principal ? `
                    <div class="product-detail-row">
                        <span class="product-detail-label">Categoría</span>
                        <span class="product-detail-value">${escapeHtml(producto.categoria_principal)}</span>
                    </div>
                    ` : ''}
                    ${producto.subcategoria ? `
                    <div class="product-detail-row">
                        <span class="product-detail-label">Subcategoría</span>
                        <span class="product-detail-value">${escapeHtml(producto.subcategoria)}</span>
                    </div>
                    ` : ''}
                    ${producto.producto_especifico ? `
                    <div class="product-detail-row">
                        <span class="product-detail-label">Tipo Específico</span>
                        <span class="product-detail-value">${escapeHtml(producto.producto_especifico)}</span>
                    </div>
                    ` : ''}
                    ${producto.codigo_producto ? `
                    <div class="product-detail-row">
                        <span class="product-detail-label">Código</span>
                        <span class="product-detail-value">${escapeHtml(producto.codigo_producto)}</span>
                    </div>
                    ` : ''}
                </div>
                
                ${producto.descripcion ? `
                <div class="product-detail-section">
                    <h3>Descripción</h3>
                    <div class="product-detail-description">${escapeHtml(producto.descripcion)}</div>
                </div>
                ` : ''}
                
                <div class="product-detail-section">
                    <h3>Ubicación</h3>
                    <div class="product-detail-location">
                        📍 ${escapeHtml(producto.vereda)}, ${escapeHtml(producto.municipio)}, ${escapeHtml(producto.departamento)}
                    </div>
                </div>
                
                <div class="product-detail-section">
                    <div class="product-detail-row">
                        <span class="product-detail-label">Publicado</span>
                        <span class="product-detail-value">${formatDate(producto.fecha_publicacion)}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modal.classList.add('active');
}

function cambiarImagenProducto(index) {
    if (!productoActual) return;
    
    const mainImage = document.getElementById('mainProductImage');
    const thumbnails = document.querySelectorAll('.product-detail-thumbnail');
    
    // Cambiar imagen principal
    mainImage.src = `/ascc/frontend/users/public/${productoActual.imagenes[index]}`;
    
    // Actualizar thumbnails activos
    thumbnails.forEach((thumb, i) => {
        if (i === index) {
            thumb.classList.add('active');
        } else {
            thumb.classList.remove('active');
        }
    });
}

function closeProductModal() {
    const modal = document.getElementById('productModal');
    modal.classList.remove('active');
}

function formatDate(dateString) {
    const fecha = new Date(dateString);
    return fecha.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// ══════════════════════════════════════════════════════════
// POLLING EN TIEMPO REAL
// ══════════════════════════════════════════════════════════

let pollingConversacionesInterval = null;

/**
 * Iniciar polling para actualizar lista de conversaciones
 * Revisa cada 5 segundos si hay nuevos mensajes en otras conversaciones
 */
function iniciarPollingConversaciones() {
    console.log('[POLLING] Iniciando polling de conversaciones...');
    
    // Limpiar intervalo anterior si existe
    if (pollingConversacionesInterval) {
        clearInterval(pollingConversacionesInterval);
    }
    
    // Consultar cada 5 segundos
    pollingConversacionesInterval = setInterval(() => {
        actualizarListaConversaciones();
    }, 5000);
}

/**
 * Actualizar lista de conversaciones sin recargar página
 */
function actualizarListaConversaciones() {
    fetch('/ascc/backend/users/controllers/MensajesController.php?accion=obtener_conversaciones')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.conversaciones) {
                actualizarSidebarConversaciones(data.conversaciones);
            }
        })
        .catch(error => {
            console.error('[POLLING] Error actualizando conversaciones:', error);
        });
}

/**
 * Actualizar el sidebar con nuevas conversaciones
 */
function actualizarSidebarConversaciones(conversaciones) {
    const conversacionesList = document.querySelector('.conversaciones-list');
    if (!conversacionesList) return;
    
    // Guardar scroll actual
    const scrollPos = conversacionesList.scrollTop;
    
    // Generar HTML de conversaciones
    let html = '';
    
    if (conversaciones.length === 0) {
        html = `
            <div class="empty-conversations">
                <div class="empty-icon">💬</div>
                <h3>No tienes conversaciones</h3>
                <p>Cuando alguien te escriba, aparecerá aquí</p>
            </div>
        `;
    } else {
        conversaciones.forEach(conv => {
            const iniciales = conv.nombre_otro_usuario.substring(0, 2).toUpperCase();
            const tiempo = calcularTiempoTranscurrido(conv.fecha_ultimo_mensaje);
            const classUnread = conv.mensajes_no_leidos > 0 ? 'unread' : '';
            const classActive = conversacionActiva === conv.id_conversacion ? 'active' : '';
            
            // Construir avatar (foto o iniciales)
            let avatarHtml = '';
            if (conv.foto_otro_usuario) {
                avatarHtml = `
                    <img src="/ascc/frontend/users/public/${conv.foto_otro_usuario}" 
                         alt="${conv.nombre_otro_usuario}" 
                         class="conversation-avatar-img"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="conversation-avatar" style="display:none;">${iniciales}</div>
                `;
            } else {
                avatarHtml = `<div class="conversation-avatar">${iniciales}</div>`;
            }
            
            html += `
                <div class="conversation-item ${classUnread} ${classActive}" 
                     data-conversation-id="${conv.id_conversacion}"
                     onclick="selectConversation(${conv.id_conversacion})">
                    ${avatarHtml}
                    <div class="conversation-info">
                        <div class="conversation-name">${escapeHtml(conv.nombre_otro_usuario)}</div>
                        <div class="conversation-preview">
                            ${escapeHtml((conv.ultimo_mensaje || 'Sin mensajes').substring(0, 50))}
                        </div>
                    </div>
                    <div class="conversation-meta">
                        <div class="conversation-time">${tiempo}</div>
                        ${conv.mensajes_no_leidos > 0 ? 
                            `<div class="conversation-badge">${conv.mensajes_no_leidos}</div>` : 
                            ''}
                    </div>
                </div>
            `;
        });
    }
    
    conversacionesList.innerHTML = html;
    
    // Restaurar scroll
    conversacionesList.scrollTop = scrollPos;
    
    // Actualizar contador en header
    actualizarContadorNoLeidos(conversaciones);
}

/**
 * Calcular tiempo transcurrido desde última actividad
 */
function calcularTiempoTranscurrido(fechaStr) {
    if (!fechaStr) return '';
    
    const fecha = new Date(fechaStr);
    const ahora = new Date();
    const diff = Math.floor((ahora - fecha) / 1000); // segundos
    
    if (diff < 60) return 'ahora';
    if (diff < 3600) return Math.floor(diff / 60) + 'min';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    
    return fecha.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit' });
}

/**
 * Actualizar contador de mensajes no leídos en el header
 */
function actualizarContadorNoLeidos(conversaciones) {
    const header = document.querySelector('.conversaciones-header');
    if (!header) return;
    
    const totalNoLeidos = conversaciones.reduce((sum, conv) => sum + conv.mensajes_no_leidos, 0);
    
    let badge = header.querySelector('.unread-count');
    
    if (totalNoLeidos > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'unread-count';
            header.appendChild(badge);
        }
        badge.textContent = totalNoLeidos;
    } else {
        if (badge) badge.remove();
    }
}

/**
 * Iniciar polling para mensajes nuevos en conversación activa
 * Revisa cada 2 segundos si hay mensajes nuevos
 */
function iniciarPollingMensajes() {
    console.log('[POLLING] Iniciando polling de mensajes cada 2 segundos...');
    
    // Limpiar intervalo anterior si existe
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    
    // Consultar cada 2 segundos
    pollingInterval = setInterval(() => {
        if (conversacionActiva) {
            verificarMensajesNuevos();
        }
    }, 2000);
}

/**
 * Detener polling de mensajes
 */
function detenerPollingMensajes() {
    if (pollingInterval) {
        console.log('[POLLING] Deteniendo polling de mensajes...');
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

/**
 * Verificar si hay mensajes nuevos desde el último ID conocido
 */
function verificarMensajesNuevos() {
    if (!conversacionActiva) return;
    
    console.log('[POLLING] Consultando mensajes nuevos... ID conversación:', conversacionActiva, 'Último ID:', ultimoMensajeId);
    
    fetch(`/ascc/backend/users/controllers/MensajesController.php?accion=obtener_mensajes_nuevos&id_conversacion=${conversacionActiva}&ultimo_id=${ultimoMensajeId}`)
        .then(response => {
            console.log('[POLLING] Respuesta recibida:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('[POLLING] Datos recibidos:', data);
            if (data.success && data.mensajes_nuevos && data.mensajes_nuevos.length > 0) {
                console.log('[POLLING] ✅ ¡Mensajes nuevos!', data.mensajes_nuevos.length);
                agregarMensajesNuevos(data.mensajes_nuevos);
            } else {
                console.log('[POLLING] No hay mensajes nuevos');
            }
        })
        .catch(error => {
            console.error('[POLLING] ❌ Error:', error);
        });
}

/**
 * Agregar mensajes nuevos al chat sin recargar todo
 */
function agregarMensajesNuevos(mensajesNuevos) {
    console.log('[MENSAJES] Agregando mensajes nuevos:', mensajesNuevos);
    
    const messagesBody = document.getElementById('chatMessagesBody');
    if (!messagesBody) {
        console.error('[MENSAJES] ❌ No se encontró chatMessagesBody');
        return;
    }
    
    let hayMensajesRecibidos = false;
    
    // Agregar cada mensaje nuevo
    mensajesNuevos.forEach(msg => {
        const isSent = (SESSION_USER_ID === parseInt(msg.id_remitente));
        console.log('[MENSAJES] Procesando mensaje:', msg.id_mensaje, 'Es mío:', isSent);
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${isSent ? 'sent' : 'received'}`;
        
        const fecha = new Date(msg.fecha_envio);
        const hora = fecha.toLocaleTimeString('es-CO', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        messageDiv.innerHTML = `
            <div class="chat-message-bubble">
                <div class="chat-message-text">${escapeHtml(msg.mensaje)}</div>
                <div class="chat-message-time">${hora}</div>
            </div>
        `;
        
        messagesBody.appendChild(messageDiv);
        
        // Actualizar último mensaje ID
        if (parseInt(msg.id_mensaje) > ultimoMensajeId) {
            ultimoMensajeId = parseInt(msg.id_mensaje);
        }
        
        // Marcar si hay mensajes recibidos
        if (!isSent) {
            hayMensajesRecibidos = true;
        }
    });
    
    // Reproducir sonido UNA VEZ si hay mensajes recibidos
    if (hayMensajesRecibidos) {
        console.log('[AUDIO] 🔊 Reproduciendo sonido...');
        reproducirSonidoNotificacion();
    }
    
    // Scroll al final con animación suave
    messagesBody.scrollTo({
        top: messagesBody.scrollHeight,
        behavior: 'smooth'
    });
    
    console.log('[MENSAJES] ✅ Mensajes agregados. Último ID:', ultimoMensajeId);
}

/**
 * Reproducir sonido cuando llega mensaje nuevo
 */
function reproducirSonidoNotificacion() {
    console.log('[AUDIO] Intentando reproducir sonido...');
    try {
        const audio = new Audio('/ascc/frontend/users/public/sounds/notification.mp3');
        audio.volume = 0.5; // Volumen al 50%
        
        audio.addEventListener('canplaythrough', () => {
            console.log('[AUDIO] ✅ Audio cargado correctamente');
        });
        
        audio.addEventListener('error', (e) => {
            console.error('[AUDIO] ❌ Error al cargar audio:', e);
            console.error('[AUDIO] Verificar que exista: /ascc/frontend/users/public/sounds/notification.mp3');
        });
        
        audio.play()
            .then(() => {
                console.log('[AUDIO] 🔊 Sonido reproducido exitosamente');
            })
            .catch(e => {
                console.error('[AUDIO] ❌ Error al reproducir:', e.message);
                console.error('[AUDIO] Puede ser que el navegador bloqueó la reproducción automática');
            });
    } catch (e) {
        console.error('[AUDIO] ❌ Error al crear audio:', e.message);
    }
}

console.log('[MENSAJES] Sistema de chat listo ✓');