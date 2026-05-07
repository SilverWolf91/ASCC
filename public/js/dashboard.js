/**
 * ═══════════════════════════════════════════════════════════
 * ASCC - DASHBOARD JS - CORREGIDO
 * Ruta: public/js/dashboard.js
 * ═══════════════════════════════════════════════════════════
 */

const DashboardState = {
    theme: 'light',
    sidebarCollapsed: true,
    currentTab: 'inicio',
    currentConversation: null
};

// ID del usuario actual (se obtiene del body data-user-id)
var SESSION_USER_ID = null;

document.addEventListener('DOMContentLoaded', function () {
    // Obtener ID del usuario desde el body
    var userIdMeta = document.querySelector('meta[name="user-id"]');
    if (userIdMeta) {
        SESSION_USER_ID = parseInt(userIdMeta.getAttribute('content'));
    }
    
    initTheme();
    initSidebar();
    initTabs();
    initMobileMenu();
    initNotificaciones();
    console.log('[ASCC] Dashboard cargado - Usuario:', SESSION_USER_ID);
});

function initTheme() {
    var temaActual = document.body.getAttribute('data-theme') || 'light';
    DashboardState.theme = temaActual;
    var toggleSwitch = document.querySelector('.toggle-switch');
    if (toggleSwitch) {
        if (temaActual === 'dark') {
            toggleSwitch.classList.add('activo');
        } else {
            toggleSwitch.classList.remove('activo');
        }
    }
}

function toggleTheme() {
    if (window.ASCCGlobal) {
        window.ASCCGlobal.toggleTema();
    }
}

function initSidebar() {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) { return; }
    var savedState = localStorage.getItem('ascc_sidebar');
    if (savedState === 'open') {
        sidebar.classList.remove('collapsed');
        DashboardState.sidebarCollapsed = false;
    } else {
        sidebar.classList.add('collapsed');
        DashboardState.sidebarCollapsed = true;
    }
}

function toggleSidebar() {
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) { return; }
    sidebar.classList.toggle('collapsed');
    DashboardState.sidebarCollapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem(
        'ascc_sidebar',
        DashboardState.sidebarCollapsed ? 'closed' : 'open'
    );
    if (overlay) {
        if (DashboardState.sidebarCollapsed) {
            overlay.classList.remove('active');
        } else {
            overlay.classList.add('active');
        }
    }
}

function initMobileMenu() {
    document.addEventListener('click', function (e) {
        if (window.innerWidth > 1024) { return; }
        var sidebar = document.querySelector('.sidebar');
        var toggle  = document.querySelector('.sidebar-toggle');
        if (!sidebar || sidebar.classList.contains('collapsed')) { return; }
        if (toggle && toggle.contains(e.target)) { return; }
        if (!sidebar.contains(e.target)) {
            sidebar.classList.add('collapsed');
            DashboardState.sidebarCollapsed = true;
            localStorage.setItem('ascc_sidebar', 'closed');
        }
    });
}

function toggleMobileSidebar() {
    toggleSidebar();
}

function initTabs() {
    var savedTab = localStorage.getItem('ascc_active_tab') || 'inicio';
    DashboardState.currentTab = savedTab;
    showTab(savedTab);
}

function openTab(event, tabName) {
    var tabContents = document.getElementsByClassName('tab-content');
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].classList.remove('active');
    }
    var navItems = document.getElementsByClassName('nav-item');
    for (var i = 0; i < navItems.length; i++) {
        navItems[i].classList.remove('active');
    }
    var tabElement = document.getElementById(tabName);
    if (tabElement) { tabElement.classList.add('active'); }
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }
    DashboardState.currentTab = tabName;
    localStorage.setItem('ascc_active_tab', tabName);
    if (tabName === 'mensajes') { loadConversations(); }
    if (window.innerWidth <= 1024) {
        var sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.add('collapsed');
            localStorage.setItem('ascc_sidebar', 'closed');
        }
    }
}

function showTab(tabName) {
    var tabContents = document.getElementsByClassName('tab-content');
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].classList.remove('active');
    }
    var tabElement = document.getElementById(tabName);
    if (tabElement) { tabElement.classList.add('active'); }
    var navItems = document.getElementsByClassName('nav-item');
    for (var i = 0; i < navItems.length; i++) {
        var onclick = navItems[i].getAttribute('onclick');
        if (onclick && onclick.includes(tabName)) {
            navItems[i].classList.add('active');
        }
    }
}

function showNotification(message, type) {
    type = type || 'success';
    var notification = document.createElement('div');
    notification.className = 'notification notification-' + type;
    var icons = { success: '✓', error: '✕', info: 'ⓘ', warning: '⚠' };
    notification.innerHTML =
        '<div class="notification-icon">' + icons[type] + '</div>' +
        '<div class="notification-message">' + message + '</div>' +
        '<button class="notification-close" onclick="this.parentElement.remove()">✕</button>';
    document.body.appendChild(notification);
    setTimeout(function () {
        notification.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(function () { notification.remove(); }, 300);
    }, 4000);
}

var notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    .notification {
        position: fixed; top: 24px; right: 24px;
        min-width: 320px; max-width: 420px;
        background: white; border-radius: 12px; padding: 16px;
        display: flex; align-items: center; gap: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        z-index: 9999; animation: slideInRight 0.3s ease;
    }
    [data-theme="dark"] .notification { background: #27272A; }
    .notification-icon {
        width: 32px; height: 32px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 16px; flex-shrink: 0;
    }
    .notification-success .notification-icon { background: #10B981; color: white; }
    .notification-error   .notification-icon { background: #EF4444; color: white; }
    .notification-info    .notification-icon { background: #3B82F6; color: white; }
    .notification-warning .notification-icon { background: #F59E0B; color: white; }
    .notification-message { flex: 1; font-size: 14px; font-weight: 500; color: #212529; }
    [data-theme="dark"] .notification-message { color: #F4F4F5; }
    .notification-close {
        width: 24px; height: 24px; border: none; background: transparent;
        color: #6C757D; font-size: 18px; cursor: pointer; border-radius: 4px;
    }
    @keyframes slideInRight  { from { opacity:0; transform:translateX(100px); } to { opacity:1; transform:translateX(0); } }
    @keyframes slideOutRight { from { opacity:1; transform:translateX(0); } to { opacity:0; transform:translateX(100px); } }
`;
document.head.appendChild(notificationStyles);

function marcarVendido(id) {
    if (confirm('¿Marcar este producto como vendido?')) {
        window.location.href = '/ascc/controllers/ProductoController.php?accion=marcar_vendido&id=' + id;
    }
}

function eliminarProducto(id) {
    if (confirm('¿Estas seguro de eliminar este producto?\n\nEsta accion no se puede deshacer.')) {
        window.location.href = '/ascc/controllers/ProductoController.php?accion=eliminar&id=' + id;
    }
}

function loadConversations() {
    fetch('/ascc/controllers/MensajesController.php?accion=obtener_conversaciones')
        .then(function(r) { return r.json(); })
        .then(function(data) { 
            console.log('Conversaciones:', data);
            if (data.success) { renderConversations(data.conversaciones); } 
        })
        .catch(function(err) { 
            console.error('Error cargando conversaciones:', err);
            showNotification('Error al cargar conversaciones', 'error'); 
        });
}

function renderConversations(conversaciones) {
    var container = document.querySelector('.conversations-list');
    if (!container) { return; }
    if (conversaciones.length === 0) {
        container.innerHTML = '<div class="empty-state">No tienes conversaciones</div>';
        return;
    }
    var html = '<h3 class="section-title" style="margin-bottom:20px;">Conversaciones</h3>';
    conversaciones.forEach(function (conv) {
        var avatar = conv.nombre_otro_usuario.substring(0, 2).toUpperCase();
        var badge  = conv.mensajes_no_leidos > 0
            ? '<div class="unread-badge"></div>' : '';
        var unreadClass = conv.mensajes_no_leidos > 0 ? 'unread' : '';
        html += '<div class="conversation-item ' + unreadClass + '" onclick="selectConversation(event,' + conv.id_conversacion + ')">';
        html += '<div class="conversation-avatar">' + avatar + '</div>';
        html += '<div class="conversation-info">';
        html += '<div class="conversation-name">' + conv.nombre_otro_usuario + '</div>';
        html += '<div class="conversation-preview">' + (conv.ultimo_mensaje || 'Sin mensajes') + '</div>';
        html += '</div>';
        html += '<div class="conversation-time">Ahora</div>';
        html += badge;
        html += '</div>';
    });
    container.innerHTML = html;
}

function selectConversation(e, id) {
    console.log('Seleccionando conversación:', id);
    DashboardState.currentConversation = id;

    document.querySelectorAll('.conversation-item').forEach(function (item) {
        item.classList.remove('active');
    });

    if (e && e.currentTarget) {
        e.currentTarget.classList.add('active');
    }

    loadMessages(id);
}

function loadMessages(id) {
    console.log('Cargando mensajes de conversación:', id);
    fetch('/ascc/controllers/MensajesController.php?accion=obtener_mensajes&id_conversacion=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) { 
            console.log('Mensajes recibidos:', data);
            if (data.success) { 
                renderMessages(data.mensajes, data.producto, data.es_vendedor); 
            } else {
                showNotification(data.error || 'Error al cargar mensajes', 'error');
            }
        })
        .catch(function(err) { 
            console.error('Error:', err);
            showNotification('Error al cargar mensajes', 'error'); 
        });
}

function renderMessages(mensajes, producto, esVendedor) {
    var container = document.querySelector('.messages-area');
    if (!container) { 
        console.error('No se encontró .messages-area');
        return; 
    }
    
    console.log('Renderizando', mensajes.length, 'mensajes');
    
    // Obtener ID del usuario actual desde una meta tag o del DOM
    if (!SESSION_USER_ID) {
        // Intentar obtener del primer mensaje
        if (mensajes.length > 0) {
            // Asumimos que el primer mensaje del otro usuario no es nuestro
            SESSION_USER_ID = esVendedor ? producto.id_vendedor : producto.id_comprador;
        }
    }
    
    var html = '<div class="messages-header">';
    html += '<h3>Chat sobre: ' + (producto ? producto.tipo_producto : 'Producto') + '</h3>';
    html += '</div>';
    
    html += '<div class="messages-body" id="messagesBody">';
    
    mensajes.forEach(function (msg) {
        var isMine = SESSION_USER_ID && (msg.id_remitente == SESSION_USER_ID);
        html += '<div class="message ' + (isMine ? 'message-sent' : 'message-received') + '">';
        html += '<div class="message-content">';
        html += '<div class="message-text">' + escapeHtml(msg.mensaje) + '</div>';
        html += '<div class="message-time">' + formatTime(msg.fecha_envio) + '</div>';
        html += '</div>';
        html += '</div>';
    });
    
    html += '</div>';
    
    html += '<div class="message-input-container">';
    html += '<input type="text" id="messageInput" class="message-input" placeholder="Escribe un mensaje..."';
    html += ' onkeypress="if(event.key===\'Enter\') sendMessage()">';
    html += '<button class="btn-send-message" onclick="sendMessage()">📤 Enviar</button>';
    html += '</div>';
    
    container.innerHTML = html;
    
    // Scroll al final
    setTimeout(function() {
        var body = document.getElementById('messagesBody');
        if (body) { 
            body.scrollTop = body.scrollHeight;
            console.log('Scroll aplicado');
        }
    }, 100);
}

function sendMessage() {
    if (!DashboardState.currentConversation) { 
        showNotification('Selecciona una conversación primero', 'warning');
        return; 
    }
    var input = document.getElementById('messageInput');
    if (!input || !input.value.trim()) { 
        showNotification('Escribe un mensaje', 'warning');
        return; 
    }
    
    var mensaje = input.value.trim();
    input.value = '';
    input.disabled = true;
    
    var formData = new FormData();
    formData.append('accion', 'enviar_mensaje');
    formData.append('id_conversacion', DashboardState.currentConversation);
    formData.append('mensaje', mensaje);
    
    fetch('/ascc/controllers/MensajesController.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            console.log('Respuesta enviar:', data);
            if (data.success) { 
                loadMessages(DashboardState.currentConversation); 
                showNotification('Mensaje enviado', 'success');
            } else { 
                showNotification(data.error || 'Error al enviar mensaje', 'error'); 
                input.value = mensaje;
            }
            input.disabled = false;
        })
        .catch(function(err) {
            console.error('Error enviando:', err);
            showNotification('Error de conexión', 'error');
            input.value = mensaje;
            input.disabled = false;
        });
}

function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function formatNumber(num) { return new Intl.NumberFormat('es-CO').format(num); }

function formatTime(d) { 
    try {
        return new Date(d).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }); 
    } catch(e) {
        return '';
    }
}

function refreshAllData() { location.reload(); }

var selectedFile = null;

function openPhotoModal() {
    var modal = document.getElementById('photoModal');
    if (modal) { modal.classList.add('open'); }
}

function closePhotoModal() {
    var modal = document.getElementById('photoModal');
    if (modal) { modal.classList.remove('open'); }
    selectedFile = null;
    var inp = document.getElementById('photoInput');
    var btn = document.getElementById('btnUpload');
    if (inp) { inp.value = ''; }
    if (btn) { btn.disabled = true; }
}

function previewPhoto(event) {
    var file = event.target.files[0];
    if (!file) { return; }
    var allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
        showNotification('Solo JPG, PNG o WebP', 'error'); return;
    }
    if (file.size > 5 * 1024 * 1024) {
        showNotification('Máximo 5MB', 'error'); return;
    }
    selectedFile = file;
    var reader = new FileReader();
    reader.onload = function(e) {
        var prev = document.getElementById('photoPreview');
        if (prev) { prev.innerHTML = '<img src="' + e.target.result + '" alt="Preview">'; }
        var btn = document.getElementById('btnUpload');
        if (btn) { btn.disabled = false; }
    };
    reader.readAsDataURL(file);
}

function uploadPhoto() {
    if (!selectedFile) { showNotification('Selecciona una imagen primero', 'error'); return; }
    var btn = document.getElementById('btnUpload');
    if (btn) { btn.disabled = true; btn.textContent = 'Subiendo...'; }
    var formData = new FormData();
    formData.append('accion', 'subir_foto');
    formData.append('foto', selectedFile);
    fetch('/ascc/controllers/PerfilController.php', { method: 'POST', body: formData })
        .then(function(r) { return r.text(); })
        .then(function(text) {
            try {
                var data = JSON.parse(text);
                if (data.success) {
                    showNotification('Foto actualizada correctamente', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotification(data.error || 'Error al subir foto', 'error');
                    if (btn) { btn.disabled = false; btn.textContent = 'Guardar'; }
                }
            } catch(e) {
                showNotification('Error inesperado', 'error');
                if (btn) { btn.disabled = false; btn.textContent = 'Guardar'; }
            }
        })
        .catch(function() {
            showNotification('Error de conexión', 'error');
            if (btn) { btn.disabled = false; btn.textContent = 'Guardar'; }
        });
}

function editProfilePhoto() { openPhotoModal(); }
function updateProfile() {
    var modal = document.getElementById('modalActualizarDatos');
    if (modal) {
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    } else {
        showNotification('Modal no encontrado', 'error');
    }
}
function changeLanguage(lang) {
    if (window.ASCCGlobal) { window.ASCCGlobal.cambiarIdioma(lang); }
}

window.toggleTheme         = toggleTheme;
window.toggleSidebar       = toggleSidebar;
window.toggleMobileSidebar = toggleMobileSidebar;
window.openTab             = openTab;
window.marcarVendido       = marcarVendido;
window.eliminarProducto    = eliminarProducto;
window.selectConversation  = selectConversation;
window.sendMessage         = sendMessage;
window.refreshAllData      = refreshAllData;
window.showNotification    = showNotification;
window.editProfilePhoto    = editProfilePhoto;
window.updateProfile       = updateProfile;
window.changeLanguage      = changeLanguage;
window.openPhotoModal      = openPhotoModal;
window.closePhotoModal     = closePhotoModal;
window.previewPhoto        = previewPhoto;
window.uploadPhoto         = uploadPhoto;

/* ═══════════════════════════════════════════════════════════
   TENDENCIAS — CAMBIO DE PANEL
   ═══════════════════════════════════════════════════════════ */
function switchTrending(btn, panelId) {
    var section = btn.closest('.trending-section');
    if (!section) return;
    section.querySelectorAll('.trending-tab').forEach(function (t) {
        t.classList.remove('is-active');
    });
    section.querySelectorAll('.trending-panel').forEach(function (p) {
        p.style.display = 'none';
    });
    btn.classList.add('is-active');
    var panel = document.getElementById(panelId);
    if (panel) panel.style.display = '';
}
window.switchTrending = switchTrending;

/* ═══════════════════════════════════════════════════════════
   NOTIFICACIONES EN TIEMPO REAL
   ═══════════════════════════════════════════════════════════ */
var NotifState = { ids: [], intervaloId: null };

function initNotificaciones() {
    cargarNotificaciones();
    NotifState.intervaloId = setInterval(cargarNotificaciones, 30000);

    var btnTodas = document.getElementById('btnMarcarTodas');
    if (btnTodas) btnTodas.addEventListener('click', marcarTodasLeidas);
}

async function cargarNotificaciones() {
    try {
        var res  = await fetch('/ascc/api/get_notificaciones.php', { method: 'GET' });
        var data = await res.json();
        if (data.success) renderNotificaciones(data.notificaciones, data.total_no_leidas);
    } catch (_) { /* silenciar errores de red */ }
}

function renderNotificaciones(lista, total) {
    var listaEl = document.getElementById('notifLista');
    var emptyEl = document.getElementById('notifEmpty');
    var badgeEl = document.getElementById('notifBadge');
    if (!listaEl) return;

    /* Badge con contador */
    if (badgeEl) {
        badgeEl.textContent    = total > 0 ? total : '';
        badgeEl.style.display  = total > 0 ? 'inline-flex' : 'none';
    }

    /* Detectar notificaciones nuevas desde el último polling */
    var nuevosIds = lista.map(function (n) { return n.id_notificacion; });
    if (NotifState.ids.length > 0) {
        var hayNuevas = nuevosIds.some(function (id) { return NotifState.ids.indexOf(id) === -1; });
        if (hayNuevas) showNotification((typeof notifLang !== 'undefined' ? notifLang.nueva_notif : 'Nueva notificación'), 'info');
    }
    NotifState.ids = nuevosIds;

    /* Limpiar items previos */
    listaEl.querySelectorAll('.notif-item').forEach(function (el) { el.remove(); });

    if (lista.length === 0) {
        if (emptyEl) emptyEl.style.display = 'flex';
        return;
    }
    if (emptyEl) emptyEl.style.display = 'none';

    lista.forEach(function (n) {
        var el        = document.createElement('div');
        el.className  = 'notif-item notif-item--' + n.tipo;
        el.dataset.id = n.id_notificacion;
        el.innerHTML  =
            '<span class="notif-icon">' + notifIcono(n.tipo) + '</span>' +
            '<div class="notif-content">' +
                '<p class="notif-titulo">'  + escHtml(n.titulo)   + '</p>' +
                '<p class="notif-mensaje">' + escHtml(n.mensaje)  + '</p>' +
                '<span class="notif-tiempo">' + notifTiempo(n.fecha_creacion) + '</span>' +
            '</div>' +
            '<button class="notif-close" data-id="' + n.id_notificacion + '" aria-label="Marcar como leída">×</button>';

        listaEl.insertBefore(el, emptyEl);
    });

    /* Listeners de cierre individuales */
    listaEl.querySelectorAll('.notif-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            marcarLeida(parseInt(this.dataset.id, 10));
        });
    });
}

async function marcarLeida(id) {
    try {
        var fd = new FormData();
        fd.append('action', 'marcar_leida');
        fd.append('id_notificacion', id);
        await fetch('/ascc/api/get_notificaciones.php', { method: 'POST', body: fd });

        var el = document.querySelector('.notif-item[data-id="' + id + '"]');
        if (el) {
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); notifCheckEmpty(); }, 250);
        }
        NotifState.ids = NotifState.ids.filter(function (x) { return x !== id; });
        notifActualizarBadge(NotifState.ids.length);
    } catch (_) {}
}

async function marcarTodasLeidas() {
    try {
        var fd = new FormData();
        fd.append('action', 'marcar_todas');
        await fetch('/ascc/api/get_notificaciones.php', { method: 'POST', body: fd });
        document.querySelectorAll('.notif-item').forEach(function (el) { el.remove(); });
        NotifState.ids = [];
        notifActualizarBadge(0);
        notifCheckEmpty();
    } catch (_) {}
}

function notifCheckEmpty() {
    var listaEl = document.getElementById('notifLista');
    var emptyEl = document.getElementById('notifEmpty');
    if (!listaEl || !emptyEl) return;
    emptyEl.style.display = listaEl.querySelectorAll('.notif-item').length === 0 ? 'flex' : 'none';
}

function notifActualizarBadge(n) {
    var badgeEl = document.getElementById('notifBadge');
    if (!badgeEl) return;
    badgeEl.textContent   = n > 0 ? n : '';
    badgeEl.style.display = n > 0 ? 'inline-flex' : 'none';
}

function notifIcono(tipo) {
    return ({ info: 'ℹ️', success: '✅', warning: '⚠️', danger: '🚨' })[tipo] || 'ℹ️';
}

function notifTiempo(fechaStr) {
    var fecha = new Date(fechaStr.replace(' ', 'T'));
    var diff  = Math.floor((Date.now() - fecha.getTime()) / 1000);
    if (diff < 60)    return (typeof notifLang !== 'undefined' ? notifLang.ahora    : 'Ahora mismo');
    if (diff < 3600)  return (typeof notifLang !== 'undefined' ? notifLang.hace_min : 'hace {n} min').replace('{n}', Math.floor(diff / 60));
    if (diff < 86400) return (typeof notifLang !== 'undefined' ? notifLang.hace_h   : 'hace {n} h').replace('{n}', Math.floor(diff / 3600));
    return (typeof notifLang !== 'undefined' ? notifLang.hace_d : 'hace {n} d').replace('{n}', Math.floor(diff / 86400));
}

function escHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}