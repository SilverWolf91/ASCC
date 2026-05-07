/**
 * ASCC — Admin Reportes JS
 * Ruta: admin/assets/js/reportes-admin.js
 * Depende de: Chart.js 4.x, admin-dashboard.js (tema)
 */

'use strict';

const RepAdmin = {};

// ── Configuración global ───────────────────────────────────────────────────
RepAdmin.config = window.REP_ADMIN_CONFIG || {};
RepAdmin.csrf   = RepAdmin.config.csrf   || '';
RepAdmin.apiUrl = RepAdmin.config.apiUrl || 'ajax/reportes_admin.php';

// ── Instancias de gráficas activas ─────────────────────────────────────────
RepAdmin.charts = {};

// ── Colores de marca ────────────────────────────────────────────────────────
RepAdmin.colors = {
    green:  '#10b981',
    blue:   '#3b82f6',
    amber:  '#f59e0b',
    red:    '#ef4444',
    purple: '#8b5cf6',
    teal:   '#14b8a6',
    grid:   'rgba(148,163,184,0.15)',
};

// =============================================================================
// 1. TABS
// =============================================================================
RepAdmin.initTabs = function () {
    const btns   = document.querySelectorAll('.rep-tab-btn');
    const panels = document.querySelectorAll('.rep-tab-panel');

    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            btns.forEach(b => b.classList.remove('active'));
            panels.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const target = document.getElementById('tab-' + btn.dataset.tab);
            if (target) {
                target.classList.add('active');
                // Cargar datos de la pestaña si es la primera vez
                RepAdmin.onTabActivated(btn.dataset.tab);
            }
        });
    });

    // Activar pestaña desde URL hash
    const hash = window.location.hash.replace('#', '');
    const hashBtn = document.querySelector(`.rep-tab-btn[data-tab="${hash}"]`);
    if (hashBtn) hashBtn.click();
};

RepAdmin.onTabActivated = function (tab) {
    switch (tab) {
        case 'metricas':
            if (!RepAdmin.charts.ventas)    RepAdmin.cargarVentasDiarias();
            if (!RepAdmin.charts.categorias) RepAdmin.cargarCategorias();
            if (!RepAdmin.charts.roles)     RepAdmin.cargarRoles();
            if (!RepAdmin.charts.hora)      RepAdmin.cargarActividadHora();
            break;
        case 'usuarios':
            RepAdmin.cargarTablaUsuarios();
            break;
        case 'productos':
            RepAdmin.cargarTablaProductos();
            break;
        case 'denuncias':
            RepAdmin.cargarTablaDenuncias();
            break;
        case 'ranking':
            if (!RepAdmin.charts.rankVend)  RepAdmin.cargarRankingVendedores();
            RepAdmin.cargarRankingCompradores();
            break;
    }
};

// =============================================================================
// 2. HELPER AJAX
// =============================================================================
RepAdmin.fetch = function (params) {
    const data = new URLSearchParams();
    data.append('csrf', RepAdmin.csrf);
    Object.entries(params).forEach(([k, v]) => data.append(k, v));

    return fetch(RepAdmin.apiUrl, {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:        data.toString(),
    })
        .then(r => r.json())
        .catch(err => {
            console.error('RepAdmin fetch error:', err);
            return { success: false };
        });
};

// =============================================================================
// 3. GRÁFICA — VENTAS DIARIAS
// =============================================================================
RepAdmin.cargarVentasDiarias = function () {
    RepAdmin.fetch({ action: 'ventas_diarias_global' }).then(data => {
        if (!data.success) return;
        const ctx = document.getElementById('chartVentasGlobal');
        if (!ctx) return;

        if (RepAdmin.charts.ventas) RepAdmin.charts.ventas.destroy();

        RepAdmin.charts.ventas = new Chart(ctx, {
            type: 'line',
            data: {
                labels:   data.labels,
                datasets: [{
                    label:           'Ventas COP',
                    data:            data.valores,
                    borderColor:     RepAdmin.colors.green,
                    backgroundColor: 'rgba(16,185,129,0.1)',
                    borderWidth:     2.5,
                    fill:            true,
                    tension:         0.4,
                    pointRadius:     3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: RepAdmin.colors.green,
                }],
            },
            options: RepAdmin.lineOptions('$', ' COP'),
        });
    });
};

// =============================================================================
// 4. GRÁFICA — CATEGORÍAS
// =============================================================================
RepAdmin.cargarCategorias = function () {
    RepAdmin.fetch({ action: 'categorias_global' }).then(data => {
        if (!data.success) return;
        const ctx = document.getElementById('chartCategoriasGlobal');
        if (!ctx) return;

        if (RepAdmin.charts.categorias) RepAdmin.charts.categorias.destroy();

        RepAdmin.charts.categorias = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels:   data.labels,
                datasets: [{
                    data:            data.valores,
                    backgroundColor: data.colores || [
                        '#10b981','#3b82f6','#f59e0b','#ef4444',
                        '#8b5cf6','#14b8a6','#f97316','#ec4899',
                        '#6366f1','#84cc16','#0ea5e9','#a78bfa',
                    ],
                    borderWidth: 2,
                    borderColor: 'transparent',
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color:    'var(--ag-text-secondary)',
                            boxWidth: 12,
                            padding:  10,
                            font:     { size: 11 },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.raw} productos`,
                        },
                    },
                },
            },
        });
    });
};

// =============================================================================
// 5. GRÁFICA — USUARIOS POR ROL
// =============================================================================
RepAdmin.cargarRoles = function () {
    RepAdmin.fetch({ action: 'usuarios_por_rol' }).then(data => {
        if (!data.success) return;
        const ctx = document.getElementById('chartRoles');
        if (!ctx) return;

        if (RepAdmin.charts.roles) RepAdmin.charts.roles.destroy();

        RepAdmin.charts.roles = new Chart(ctx, {
            type: 'bar',
            data: {
                labels:   data.labels,
                datasets: [{
                    label:           'Usuarios',
                    data:            data.valores,
                    backgroundColor: [RepAdmin.colors.green, RepAdmin.colors.blue, RepAdmin.colors.amber],
                    borderRadius:    6,
                    borderWidth:     0,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.raw} usuarios`,
                        },
                    },
                },
                scales: {
                    x: { grid: { color: RepAdmin.colors.grid }, ticks: { color: 'var(--ag-text-secondary)', font: { size: 12 } } },
                    y: { grid: { color: RepAdmin.colors.grid }, ticks: { color: 'var(--ag-text-secondary)', stepSize: 1 }, beginAtZero: true },
                },
            },
        });
    });
};

// =============================================================================
// 6. GRÁFICA — ACTIVIDAD POR HORA
// =============================================================================
RepAdmin.cargarActividadHora = function () {
    RepAdmin.fetch({ action: 'actividad_por_hora' }).then(data => {
        if (!data.success) return;
        const ctx = document.getElementById('chartActividad');
        if (!ctx) return;

        if (RepAdmin.charts.hora) RepAdmin.charts.hora.destroy();

        RepAdmin.charts.hora = new Chart(ctx, {
            type: 'bar',
            data: {
                labels:   data.labels,
                datasets: [{
                    label:           'Visitas',
                    data:            data.valores,
                    backgroundColor: 'rgba(59,130,246,0.7)',
                    borderRadius:    4,
                    borderWidth:     0,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.raw} visitas`,
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: 'var(--ag-text-secondary)', font: { size: 10 } } },
                    y: { grid: { color: RepAdmin.colors.grid }, ticks: { color: 'var(--ag-text-secondary)' }, beginAtZero: true },
                },
            },
        });
    });
};

// =============================================================================
// 7. TABLA — USUARIOS
// =============================================================================
RepAdmin.filtroUsuarios = 'todos';
RepAdmin.busquedaUsuarios = '';

RepAdmin.cargarTablaUsuarios = function () {
    RepAdmin.fetch({
        action:   'usuarios_tabla',
        filtro:   RepAdmin.filtroUsuarios,
        busqueda: RepAdmin.busquedaUsuarios,
    }).then(data => {
        if (!data.success) return;
        const tbody = document.getElementById('tbodyUsuarios');
        if (!tbody) return;

        if (!data.usuarios.length) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--ag-text-muted)">
                Sin usuarios para mostrar</td></tr>`;
            return;
        }

        tbody.innerHTML = data.usuarios.map((u, i) => {
            const inicial   = (u.nombre || 'U').charAt(0).toUpperCase();
            const semaforo  = RepAdmin.getSemaforo(u.denuncias_recibidas);
            const rolBadge  = RepAdmin.getRolBadge(u.rol);
            const estadoBadge = u.estado === 'activo'
                ? `<span class="rep-badge rep-badge--success">Activo</span>`
                : u.estado === 'suspendido'
                ? `<span class="rep-badge rep-badge--danger">Suspendido</span>`
                : `<span class="rep-badge rep-badge--neutral">Inactivo</span>`;

            const btnEstado = u.estado === 'activo'
                ? `<button class="rep-action-btn rep-action-btn--danger"
                           onclick="RepAdmin.toggleEstadoUsuario(${u.id_usuario}, 'suspendido')">
                       <i class="fas fa-ban"></i> Suspender
                   </button>`
                : `<button class="rep-action-btn rep-action-btn--success"
                           onclick="RepAdmin.toggleEstadoUsuario(${u.id_usuario}, 'activo')">
                       <i class="fas fa-check"></i> Activar
                   </button>`;

            return `<tr>
                <td>
                    <div class="rep-user-cell">
                        <div class="rep-avatar">${inicial}</div>
                        <div>
                            <div class="rep-user-name">${RepAdmin.esc(u.nombre)}</div>
                            <div class="rep-user-email">${RepAdmin.esc(u.email)}</div>
                        </div>
                    </div>
                </td>
                <td>${rolBadge}</td>
                <td style="text-align:center">${u.productos || 0}</td>
                <td style="text-align:center">$${RepAdmin.fmt(u.ventas_total || 0)}</td>
                <td>${RepAdmin.estrellas(u.calificacion || 0)}</td>
                <td>${RepAdmin.fechaCorta(u.fecha_registro)}</td>
                <td>${estadoBadge} ${semaforo}</td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        ${btnEstado}
                        <a href="../perfil_vendedor.php?id=${u.id_usuario}" target="_blank"
                           class="rep-action-btn rep-action-btn--info">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </td>
            </tr>`;
        }).join('');
    });
};

RepAdmin.toggleEstadoUsuario = function (id, nuevoEstado) {
    const accion = nuevoEstado === 1 ? 'activar' : 'suspender';
    if (!confirm(`¿Confirmas ${accion} esta cuenta?`)) return;

    RepAdmin.fetch({ action: 'toggle_usuario', id_usuario: id, estado: nuevoEstado })
        .then(data => {
            if (data.success) {
                RepAdmin.cargarTablaUsuarios();
                RepAdmin.showToast(`Cuenta ${nuevoEstado === 1 ? 'activada' : 'suspendida'} correctamente.`);
            } else {
                RepAdmin.showToast('Error al cambiar estado.', true);
            }
        });
};

// =============================================================================
// 8. TABLA — PRODUCTOS
// =============================================================================
RepAdmin.busquedaProductos = '';

RepAdmin.cargarTablaProductos = function () {
    RepAdmin.fetch({
        action:   'productos_tabla',
        busqueda: RepAdmin.busquedaProductos,
    }).then(data => {
        if (!data.success) return;
        const tbody = document.getElementById('tbodyProductos');
        if (!tbody) return;

        if (!data.productos.length) {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--ag-text-muted)">
                Sin productos para mostrar</td></tr>`;
            return;
        }

        tbody.innerHTML = data.productos.map(p => {
            const estadoBadge = p.estado === 'disponible'
                ? `<span class="rep-badge rep-badge--success">Disponible</span>`
                : `<span class="rep-badge rep-badge--neutral">Vendido</span>`;

            return `<tr>
                <td style="font-family:monospace;font-size:0.75rem">${RepAdmin.esc(p.codigo_producto || '—')}</td>
                <td style="font-weight:600">${RepAdmin.esc(p.tipo_producto)}</td>
                <td>${RepAdmin.esc(p.vendedor_nombre)}</td>
                <td>${RepAdmin.esc(p.categoria_principal || '—')}</td>
                <td style="text-align:right">$${RepAdmin.fmt(p.precio)}</td>
                <td style="text-align:center">${p.cantidad} ${RepAdmin.esc(p.unidad)}</td>
                <td style="text-align:center">${p.visitas || 0}</td>
                <td>${estadoBadge}</td>
                <td>${RepAdmin.fechaCorta(p.fecha_publicacion)}</td>
            </tr>`;
        }).join('');
    });
};

// =============================================================================
// 9. TABLA — DENUNCIAS
// =============================================================================
RepAdmin.filtroDenuncias = 'todas';

RepAdmin.cargarTablaDenuncias = function () {
    RepAdmin.fetch({
        action: 'denuncias_tabla',
        filtro: RepAdmin.filtroDenuncias,
    }).then(data => {
        if (!data.success) return;
        const tbody = document.getElementById('tbodyDenuncias');
        if (!tbody) return;

        if (!data.denuncias.length) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--ag-text-muted)">
                Sin denuncias para mostrar</td></tr>`;
            return;
        }

        tbody.innerHTML = data.denuncias.map(d => {
            const prioBadge = {
                alta:   `<span class="rep-badge rep-badge--danger">Alta</span>`,
                media:  `<span class="rep-badge rep-badge--warning">Media</span>`,
                baja:   `<span class="rep-badge rep-badge--neutral">Baja</span>`,
            }[d.prioridad] || '';

            const estBadge = {
                recibida:           `<span class="rep-badge rep-badge--danger">Recibida</span>`,
                en_revision:        `<span class="rep-badge rep-badge--warning">En revisión</span>`,
                pendiente_vendedor: `<span class="rep-badge rep-badge--info">Pend. vendedor</span>`,
                resuelta:           `<span class="rep-badge rep-badge--success">Resuelta</span>`,
                cerrada:            `<span class="rep-badge rep-badge--neutral">Cerrada</span>`,
            }[d.estado] || '';

            const catLabel = {
                no_entregado:        'No entregado',
                descripcion_enganosa:'Desc. engañosa',
                precio_diferente:    'Precio diferente',
                mala_calidad:        'Mala calidad',
                vendedor_no_responde:'No responde',
                resena_falsa:        'Reseña falsa',
                lenguaje_inapropiado:'Lenguaje inapr.',
                otro:                'Otro',
            }[d.categoria] || d.categoria;

            return `<tr>
                <td style="font-family:monospace;font-size:0.75rem">#${d.id_reporte}</td>
                <td>${RepAdmin.esc(d.denunciante_nombre || '—')}</td>
                <td>${RepAdmin.esc(d.denunciado_nombre || '—')}</td>
                <td>${catLabel}</td>
                <td>${prioBadge}</td>
                <td>${estBadge}</td>
                <td>${RepAdmin.fechaCorta(d.fecha_creacion)}</td>
                <td>
                    <button class="rep-action-btn rep-action-btn--info"
                            onclick="RepAdmin.abrirModalDenuncia(${d.id_reporte}, '${RepAdmin.esc(d.estado)}')">
                        <i class="fas fa-edit"></i> Gestionar
                    </button>
                </td>
            </tr>`;
        }).join('');
    });
};

// =============================================================================
// 10. MODAL — DENUNCIA
// =============================================================================
RepAdmin.idDenunciaActiva = null;

RepAdmin.abrirModalDenuncia = function (id, estadoActual) {
    RepAdmin.idDenunciaActiva = id;

    // Cargar detalle
    RepAdmin.fetch({ action: 'denuncia_detalle', id_reporte: id }).then(data => {
        if (!data.success) return;
        const d = data.denuncia;

        document.getElementById('modalDenId').textContent        = '#' + d.id_reporte;
        document.getElementById('modalDenDenunciante').textContent = d.denunciante_nombre || '—';
        document.getElementById('modalDenDenunciado').textContent  = d.denunciado_nombre  || '—';
        document.getElementById('modalDenDescripcion').textContent = d.descripcion        || '—';
        document.getElementById('modalDenFecha').textContent       = RepAdmin.fechaCorta(d.fecha_creacion);

        const sel = document.getElementById('modalDenEstado');
        if (sel) sel.value = d.estado;

        const resp = document.getElementById('modalDenRespuesta');
        if (resp) resp.value = d.respuesta_admin || '';
    });

    document.getElementById('modalDenBackdrop').classList.add('open');
};

RepAdmin.cerrarModalDenuncia = function () {
    document.getElementById('modalDenBackdrop').classList.remove('open');
    RepAdmin.idDenunciaActiva = null;
};

RepAdmin.guardarDenuncia = function () {
    if (!RepAdmin.idDenunciaActiva) return;

    const estado    = document.getElementById('modalDenEstado').value;
    const respuesta = document.getElementById('modalDenRespuesta').value.trim();

    RepAdmin.fetch({
        action:     'actualizar_denuncia',
        id_reporte: RepAdmin.idDenunciaActiva,
        estado:     estado,
        respuesta:  respuesta,
    }).then(data => {
        if (data.success) {
            RepAdmin.cerrarModalDenuncia();
            RepAdmin.cargarTablaDenuncias();
            RepAdmin.showToast('Denuncia actualizada correctamente.');
        } else {
            RepAdmin.showToast('Error al actualizar.', true);
        }
    });
};

// =============================================================================
// 11. RANKING
// =============================================================================
RepAdmin.cargarRankingVendedores = function () {
    RepAdmin.fetch({ action: 'ranking_vendedores' }).then(data => {
        if (!data.success) return;
        const ctx = document.getElementById('chartRankVendedores');
        if (!ctx) return;

        if (RepAdmin.charts.rankVend) RepAdmin.charts.rankVend.destroy();

        RepAdmin.charts.rankVend = new Chart(ctx, {
            type: 'bar',
            data: {
                labels:   data.labels,
                datasets: [{
                    label:           'Calificación',
                    data:            data.valores,
                    backgroundColor: 'rgba(245,158,11,0.8)',
                    borderRadius:    6,
                    borderWidth:     0,
                }],
            },
            options: {
                indexAxis:           'y',
                responsive:          true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.raw}/5 (${data.resenas[ctx.dataIndex]} reseñas)`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid:  { color: RepAdmin.colors.grid },
                        ticks: { color: 'var(--ag-text-secondary)' },
                        min:   0,
                        max:   5,
                    },
                    y: {
                        grid:  { display: false },
                        ticks: { color: 'var(--ag-text-primary)', font: { size: 11 } },
                    },
                },
            },
        });
    });
};

RepAdmin.cargarRankingCompradores = function () {
    RepAdmin.fetch({ action: 'ranking_compradores' }).then(data => {
        if (!data.success) return;
        const tbody = document.getElementById('tbodyRankCompradores');
        if (!tbody) return;

        tbody.innerHTML = data.compradores.map((c, i) => {
            const posClass  = i < 3 ? `rep-rank-pos--${i+1}` : 'rep-rank-pos--n';
            const inicial   = (c.nombre || 'U').charAt(0).toUpperCase();
            return `<tr>
                <td><span class="rep-rank-pos ${posClass}">${i+1}</span></td>
                <td>
                    <div class="rep-user-cell">
                        <div class="rep-avatar">${inicial}</div>
                        <div class="rep-user-name">${RepAdmin.esc(c.nombre)}</div>
                    </div>
                </td>
                <td style="text-align:center">${c.total_compras}</td>
                <td style="text-align:right">$${RepAdmin.fmt(c.total_gastado)}</td>
            </tr>`;
        }).join('');
    });
};

// =============================================================================
// 12. EXPORTAR
// =============================================================================
RepAdmin.initExportar = function () {
    const btnExcel = document.getElementById('btnExcelAdmin');
    const btnCsv   = document.getElementById('btnCsvAdmin');

    if (btnExcel) {
        btnExcel.addEventListener('click', () => {
            const secs = RepAdmin.getSeccionesSeleccionadas();
            window.location.href = `ajax/reportes_admin.php?action=excel_admin&secciones=${secs}&csrf=${RepAdmin.csrf}`;
        });
    }

    if (btnCsv) {
        btnCsv.addEventListener('click', () => {
            const secs = RepAdmin.getSeccionesSeleccionadas();
            window.location.href = `ajax/reportes_admin.php?action=csv_admin&secciones=${secs}&csrf=${RepAdmin.csrf}`;
        });
    }
};

RepAdmin.getSeccionesSeleccionadas = function () {
    const checks = document.querySelectorAll('.rep-check-export:checked');
    const secs   = Array.from(checks).map(c => c.value);
    return secs.length > 0 ? secs.join(',') : 'usuarios,productos,ventas,denuncias,visitas';
};

// =============================================================================
// 13. FILTROS DE TABLA
// =============================================================================
RepAdmin.initFiltros = function () {
    // Filtros usuarios
    document.querySelectorAll('.rep-filter-usuarios').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.rep-filter-usuarios').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            RepAdmin.filtroUsuarios = btn.dataset.filtro;
            RepAdmin.cargarTablaUsuarios();
        });
    });

    // Filtros denuncias
    document.querySelectorAll('.rep-filter-denuncias').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.rep-filter-denuncias').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            RepAdmin.filtroDenuncias = btn.dataset.filtro;
            RepAdmin.cargarTablaDenuncias();
        });
    });

    // Búsqueda usuarios
    const searchUsr = document.getElementById('searchUsuarios');
    if (searchUsr) {
        let timer;
        searchUsr.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                RepAdmin.busquedaUsuarios = searchUsr.value.trim();
                RepAdmin.cargarTablaUsuarios();
            }, 350);
        });
    }

    // Búsqueda productos
    const searchProd = document.getElementById('searchProductos');
    if (searchProd) {
        let timer;
        searchProd.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                RepAdmin.busquedaProductos = searchProd.value.trim();
                RepAdmin.cargarTablaProductos();
            }, 350);
        });
    }
};

// =============================================================================
// 14. AUTO-REFRESH KPIs
// =============================================================================
RepAdmin.initAutoRefresh = function () {
    RepAdmin.refreshTimer = setInterval(() => {
        if (document.hidden) return;
        RepAdmin.actualizarKpis();
    }, 60000); // cada 60 segundos

    window.addEventListener('beforeunload', () => clearInterval(RepAdmin.refreshTimer));
};

RepAdmin.actualizarKpis = function () {
    RepAdmin.fetch({ action: 'kpis_globales' }).then(data => {
        if (!data.success) return;
        const kpis = data.kpis;
        const set  = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };
        set('kpiUsuariosTotal',  kpis.usuarios_total);
        set('kpiUsuariosHoy',    '+' + kpis.usuarios_hoy);
        set('kpiProductosActivos', kpis.productos_activos);
        set('kpiProductosHoy',   '+' + kpis.productos_hoy);
        set('kpiVentasMes',      '$' + RepAdmin.fmt(kpis.ventas_mes));
        set('kpiDenuncias',      kpis.denuncias_abiertas);
        set('kpiVisitasHoy',     kpis.visitas_hoy);
        set('kpiConversion',     kpis.conversion + '%');

        const lastUpdate = document.getElementById('lastUpdate');
        if (lastUpdate) {
            const now = new Date();
            lastUpdate.textContent = 'Actualizado: ' + now.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
        }
    });
};

// =============================================================================
// 15. UTILIDADES
// =============================================================================

RepAdmin.lineOptions = function (prefix = '', suffix = '') {
    return {
        responsive:          true,
        maintainAspectRatio: false,
        interaction:         { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${prefix}${RepAdmin.fmt(ctx.raw)}${suffix}`,
                },
            },
        },
        scales: {
            x: {
                grid:  { display: false },
                ticks: { color: 'var(--ag-text-secondary)', maxTicksLimit: 10, font: { size: 11 } },
            },
            y: {
                grid:  { color: RepAdmin.colors.grid },
                ticks: {
                    color: 'var(--ag-text-secondary)',
                    callback: v => prefix + RepAdmin.fmt(v),
                    font: { size: 11 },
                },
                beginAtZero: true,
            },
        },
    };
};

RepAdmin.fmt = function (n) {
    return Number(n || 0).toLocaleString('es-CO');
};

RepAdmin.esc = function (str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
};

RepAdmin.fechaCorta = function (fecha) {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

RepAdmin.estrellas = function (cal) {
    const n     = Math.round(Number(cal) || 0);
    const llena = '★'.repeat(Math.min(n, 5));
    const vacia = '☆'.repeat(Math.max(0, 5 - n));
    return `<span class="rep-stars">${llena}${vacia}</span> <span style="font-size:0.75rem;color:var(--ag-text-secondary)">${Number(cal || 0).toFixed(1)}</span>`;
};

RepAdmin.getRolBadge = function (rol) {
    const map = {
        vendedor:  'rep-badge--success',
        comprador: 'rep-badge--info',
        mixto:     'rep-badge--warning',
        admin:     'rep-badge--danger',
    };
    return `<span class="rep-badge ${map[rol] || 'rep-badge--neutral'}">${rol}</span>`;
};

RepAdmin.getSemaforo = function (denuncias) {
    denuncias = parseInt(denuncias || 0);
    if (denuncias >= 5) return `<span class="rep-semaforo rep-semaforo--danger"><span class="rep-semaforo__dot"></span>${denuncias} den.</span>`;
    if (denuncias >= 3) return `<span class="rep-semaforo rep-semaforo--warn"><span class="rep-semaforo__dot"></span>${denuncias} den.</span>`;
    if (denuncias > 0)  return `<span class="rep-semaforo rep-semaforo--ok"><span class="rep-semaforo__dot"></span>${denuncias} den.</span>`;
    return '';
};

RepAdmin.showToast = function (msg, error = false) {
    let toast = document.getElementById('repAdminToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'repAdminToast';
        toast.style.cssText = `
            position:fixed;bottom:24px;right:24px;z-index:9999;
            padding:12px 20px;border-radius:10px;font-size:0.875rem;
            font-weight:600;max-width:320px;transition:all .3s;
            box-shadow:0 4px 20px rgba(0,0,0,0.2);
        `;
        document.body.appendChild(toast);
    }
    toast.style.background = error ? '#ef4444' : '#10b981';
    toast.style.color       = '#fff';
    toast.textContent       = msg;
    toast.style.opacity     = '1';
    toast.style.transform   = 'translateY(0)';

    setTimeout(() => {
        toast.style.opacity   = '0';
        toast.style.transform = 'translateY(10px)';
    }, 3000);
};

// =============================================================================
// 16. INICIALIZACIÓN
// =============================================================================
document.addEventListener('DOMContentLoaded', () => {
    RepAdmin.initTabs();
    RepAdmin.initFiltros();
    RepAdmin.initExportar();
    RepAdmin.initAutoRefresh();

    // Cargar primera pestaña
    RepAdmin.onTabActivated('metricas');
    RepAdmin.actualizarKpis();

    // Cerrar modal con ESC
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') RepAdmin.cerrarModalDenuncia();
    });
});