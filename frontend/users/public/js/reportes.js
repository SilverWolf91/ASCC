/**
 * ASCC — Módulo de Reportes JavaScript
 * Ruta: public/js/reportes.js
 *
 * Depende de:
 *   Chart.js 4.4.1 (cargado en reportes.php vía CDN)
 *   window.REP_I18N   → textos traducidos inyectados desde PHP
 *   window.REP_CONFIG → configuración del usuario inyectada desde PHP
 */

'use strict';

/* =============================================================================
   ESTADO GLOBAL
============================================================================= */
const Rep = {
    charts:       {},        // instancias Chart.js activas
    refreshTimer: null,      // setInterval para refresco automático
    tabActivo:    'metricas',
    graficasInit: false,     // para no inicializar Chart.js dos veces
    visitasInit:  false,
    rankingInit:  false,
};

/* =============================================================================
   INIT
============================================================================= */
document.addEventListener('DOMContentLoaded', () => {
    Rep.initTabs();
    Rep.initModal();
    Rep.initGenerarToken();
    Rep.initExportBtns();
    Rep.initAutoRefresh();

    // Inicializar gráfica de ventas (está en la tab de métricas, visible por defecto)
    if (window.REP_CONFIG?.es_vendedor) {
        Rep.cargarGraficaVentas();
    } else if (window.REP_CONFIG?.es_comprador) {
        Rep.cargarGraficaGasto();
    }
});

/* =============================================================================
   1. TABS
============================================================================= */
Rep.initTabs = function () {
    document.querySelectorAll('.rep-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            if (!tab) return;
            Rep.switchTab(tab, btn);
        });
    });

    // Restaurar tab desde sessionStorage
    const saved = sessionStorage.getItem('rep_tab_activo');
    if (saved) {
        const btn = document.querySelector(`.rep-tab-btn[data-tab="${saved}"]`);
        if (btn) Rep.switchTab(saved, btn);
    }
};

Rep.switchTab = function (tab, btn) {
    // Ocultar todos los paneles
    document.querySelectorAll('.rep-tab-panel').forEach(p => {
        p.classList.remove('active');
    });

    // Desactivar todos los botones
    document.querySelectorAll('.rep-tab-btn').forEach(b => {
        b.classList.remove('active');
    });

    // Activar el seleccionado
    const panel = document.getElementById('tab-' + tab);
    if (panel) panel.classList.add('active');
    if (btn)   btn.classList.add('active');

    Rep.tabActivo = tab;
    sessionStorage.setItem('rep_tab_activo', tab);

    // Inicializar gráficas al entrar a la tab de gráficas (lazy init)
    if (tab === 'graficas' && !Rep.graficasInit) {
        Rep.graficasInit = true;
        setTimeout(() => Rep.initGraficasCompletas(), 80);
    }

    // Inicializar gráfica de visitas al perfil
    if (tab === 'visitas' && !Rep.visitasInit) {
        Rep.visitasInit = true;
        setTimeout(() => Rep.cargarGraficaVisitasPerfil(), 80);
    }

    // Inicializar gráfica de evolución de calificación
    if (tab === 'ranking' && !Rep.rankingInit) {
        Rep.rankingInit = true;
        setTimeout(() => Rep.cargarGraficaEvolucion(), 80);
    }
};

/* =============================================================================
   2. COLORES DEL TEMA PARA CHART.JS
   Canvas no puede leer variables CSS — usamos valores fijos por tema.
============================================================================= */
Rep.getThemeColors = function () {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark'
        || document.body.classList.contains('theme-dark');
    return {
        text:    isDark ? '#94a3b8' : '#475569',
        grid:    isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.07)',
        tooltip: isDark ? '#1e293b' : '#ffffff',
        border:  isDark ? '#334155' : '#e2e8f0',
        green:   '#10b981',
        greenFill: isDark ? 'rgba(16,185,129,0.12)' : 'rgba(16,185,129,0.1)',
        muted:   '#94a3b8',
        blue:    '#3b82f6',
        amber:   '#f59e0b',
        red:     '#ef4444',
        purple:  '#8b5cf6',
    };
};

/* =============================================================================
   3. GRÁFICA DE VENTAS (tab métricas)
============================================================================= */
Rep.cargarGraficaVentas = function () {
    const canvas = document.getElementById('chartVentas');
    if (!canvas) return;

    fetch('/ascc/backend/users/api/reportes_data.php?action=ventas_diarias&csrf='
        + encodeURIComponent(window.REP_CONFIG?.csrf_token || ''))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            Rep._renderVentas(canvas, data);
        })
        .catch(() => {
            // Sin conexión — mostrar datos vacíos sin romper la UI
            Rep._renderVentas(canvas, { labels: [], actual: [], anterior: [] });
        });
};

Rep._renderVentas = function (canvas, data) {
    if (Rep.charts.ventas) Rep.charts.ventas.destroy();
    const c = Rep.getThemeColors();

    Rep.charts.ventas = new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [
                {
                    label:           window.REP_I18N?.graf_mes_actual || 'Mes actual',
                    data:            data.actual || [],
                    borderColor:     c.green,
                    backgroundColor: c.greenFill,
                    fill:            true,
                    tension:         0.3,
                    borderWidth:     2,
                    pointRadius:     0,
                    pointHoverRadius: 4,
                },
                {
                    label:       window.REP_I18N?.graf_mes_anterior || 'Mes anterior',
                    data:        data.anterior || [],
                    borderColor: c.muted,
                    borderDash:  [5, 5],
                    fill:        false,
                    tension:     0.3,
                    borderWidth: 1.5,
                    pointRadius: 0,
                },
            ],
        },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: c.tooltip,
                    titleColor:      c.text,
                    bodyColor:       c.text,
                    borderColor:     c.border,
                    borderWidth:     1,
                    padding:         10,
                    callbacks: {
                        label: ctx => ' $' + Math.round(ctx.raw).toLocaleString('es-CO'),
                    },
                },
            },
            scales: {
                x: {
                    ticks: { maxTicksLimit: 8, font: { size: 11 }, color: c.text },
                    grid:  { display: false },
                },
                y: {
                    ticks: {
                        font: { size: 11 },
                        color: c.text,
                        callback: v => '$' + Math.round(v / 1000) + 'k',
                    },
                    grid: { color: c.grid },
                },
            },
        },
    });
};

/* =============================================================================
   4. GRÁFICAS COMPLETAS (tab gráficas — lazy init)
============================================================================= */
Rep.initGraficasCompletas = function () {
    if (window.REP_CONFIG?.es_vendedor) {
        Rep.cargarGraficaProductos();
        Rep.cargarGraficaCategorias();
        Rep.cargarGraficaPrecio();
        Rep.cargarGraficaFunnel();
    } else if (window.REP_CONFIG?.es_comprador) {
        Rep.cargarGraficaComprasCat();
        Rep.cargarGraficaGasto();
    }
};

/* ── Productos más/menos vendidos ── */
Rep.cargarGraficaProductos = function () {
    const canvas = document.getElementById('chartProductos');
    if (!canvas) return;

    fetch('/ascc/backend/users/api/reportes_data.php?action=productos_ventas&csrf='
        + encodeURIComponent(window.REP_CONFIG?.csrf_token || ''))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (Rep.charts.productos) Rep.charts.productos.destroy();
            const c = Rep.getThemeColors();

            Rep.charts.productos = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels || [],
                    datasets: [
                        {
                            label:           window.REP_I18N?.graf_mas_vendidos || 'Más vendidos',
                            data:            data.mas || [],
                            backgroundColor: c.green,
                            borderRadius:    4,
                        },
                        {
                            label:           window.REP_I18N?.graf_menos_vendidos || 'Menos vendidos',
                            data:            data.menos || [],
                            backgroundColor: '#f09595',
                            borderRadius:    4,
                        },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false },
                        tooltip: { backgroundColor: c.tooltip, titleColor: c.text, bodyColor: c.text } },
                    scales: {
                        x: { ticks: { font: { size: 10 }, color: c.text }, grid: { display: false } },
                        y: { ticks: { font: { size: 10 }, color: c.text }, grid: { color: c.grid } },
                    },
                },
            });
        }).catch(() => {});
};

/* ── Distribución por categoría (dona) ── */
Rep.cargarGraficaCategorias = function () {
    const canvas = document.getElementById('chartCategorias');
    if (!canvas) return;

    fetch('/ascc/backend/users/api/reportes_data.php?action=categorias_ventas&csrf='
        + encodeURIComponent(window.REP_CONFIG?.csrf_token || ''))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (Rep.charts.categorias) Rep.charts.categorias.destroy();
            const c = Rep.getThemeColors();

            // Leyenda personalizada
            const legendEl = document.getElementById('legendCategorias');
            if (legendEl) {
                legendEl.innerHTML = (data.labels || []).map((lbl, i) =>
                    `<span>
                        <span class="rep-legend-box" style="background:${data.colors[i]}"></span>
                        ${lbl} ${data.values[i]}%
                    </span>`
                ).join('');
            }

            Rep.charts.categorias = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels:   data.labels || [],
                    datasets: [{
                        data:            data.values || [],
                        backgroundColor: data.colors || [],
                        borderColor:     c.tooltip,
                        borderWidth:     2,
                        hoverOffset:     6,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: c.tooltip, titleColor: c.text, bodyColor: c.text,
                            callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw}%` },
                        },
                    },
                },
            });
        }).catch(() => {});
};

/* ── Precio vs cantidad vendida (scatter) ── */
Rep.cargarGraficaPrecio = function () {
    const canvas = document.getElementById('chartPrecio');
    if (!canvas) return;

    fetch('/ascc/backend/users/api/reportes_data.php?action=precio_scatter&csrf='
        + encodeURIComponent(window.REP_CONFIG?.csrf_token || ''))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (Rep.charts.precio) Rep.charts.precio.destroy();
            const c = Rep.getThemeColors();

            Rep.charts.precio = new Chart(canvas, {
                type: 'scatter',
                data: {
                    datasets: [{
                        label:           'Productos',
                        data:            data.points || [],
                        backgroundColor: c.green,
                        pointRadius:     7,
                        pointHoverRadius: 9,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    layout: { padding: 20 },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: c.tooltip, titleColor: c.text, bodyColor: c.text,
                            callbacks: {
                                label: ctx => {
                                    const p = data.points[ctx.dataIndex];
                                    return ` ${p?.nombre || ''} — $${Math.round(ctx.parsed.x).toLocaleString('es-CO')} / ${Math.round(ctx.parsed.y)} uds`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Precio ($)', color: c.text, font: { size: 11 } },
                            ticks: { font: { size: 10 }, color: c.text,
                                callback: v => '$' + Math.round(v / 1000) + 'k' },
                            grid: { color: c.grid },
                        },
                        y: {
                            title: { display: true, text: 'Unidades vendidas', color: c.text, font: { size: 11 } },
                            ticks: { font: { size: 10 }, color: c.text },
                            grid: { color: c.grid },
                        },
                    },
                },
            });
        }).catch(() => {});
};

/* ── Funnel de conversión ── */
Rep.cargarGraficaFunnel = function () {
    const canvas = document.getElementById('chartFunnel');
    if (!canvas) return;

    fetch('/ascc/backend/users/api/reportes_data.php?action=funnel&csrf='
        + encodeURIComponent(window.REP_CONFIG?.csrf_token || ''))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (Rep.charts.funnel) Rep.charts.funnel.destroy();
            const c = Rep.getThemeColors();

            const labels = [
                window.REP_I18N?.graf_vieron      || 'Vieron producto',
                window.REP_I18N?.graf_contactaron || 'Contactaron',
                window.REP_I18N?.graf_compraron   || 'Compraron',
            ];

            Rep.charts.funnel = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data:            [data.visitas || 0, data.contactos || 0, data.ventas || 0],
                        backgroundColor: [c.blue, c.amber, c.green],
                        borderRadius:    4,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false },
                        tooltip: { backgroundColor: c.tooltip, titleColor: c.text, bodyColor: c.text } },
                    scales: {
                        x: { ticks: { font: { size: 10 }, color: c.text }, grid: { color: c.grid } },
                        y: { ticks: { font: { size: 11 }, color: c.text }, grid: { display: false } },
                    },
                },
            });
        }).catch(() => {});
};

/* ── Compras por categoría (comprador) ── */
Rep.cargarGraficaComprasCat = function () {
    const canvas = document.getElementById('chartComprasCat');
    if (!canvas) return;

    fetch('/ascc/backend/users/api/reportes_data.php?action=compras_categorias&csrf='
        + encodeURIComponent(window.REP_CONFIG?.csrf_token || ''))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (Rep.charts.comprasCat) Rep.charts.comprasCat.destroy();
            const c = Rep.getThemeColors();

            const legendEl = document.getElementById('legendCompras');
            if (legendEl) {
                legendEl.innerHTML = (data.labels || []).map((lbl, i) =>
                    `<span>
                        <span class="rep-legend-box" style="background:${data.colors[i]}"></span>
                        ${lbl}
                    </span>`
                ).join('');
            }

            Rep.charts.comprasCat = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels:   data.labels || [],
                    datasets: [{
                        data:            data.values || [],
                        backgroundColor: data.colors || [],
                        borderColor:     c.tooltip,
                        borderWidth:     2,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: { legend: { display: false },
                        tooltip: { backgroundColor: c.tooltip, titleColor: c.text, bodyColor: c.text } },
                },
            });
        }).catch(() => {});
};

/* ── Gasto mensual (comprador) ── */
Rep.cargarGraficaGasto = function () {
    const canvas = document.getElementById('chartGasto');
    if (!canvas) return;

    fetch('/ascc/backend/users/api/reportes_data.php?action=gasto_mensual&csrf='
        + encodeURIComponent(window.REP_CONFIG?.csrf_token || ''))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (Rep.charts.gasto) Rep.charts.gasto.destroy();
            const c = Rep.getThemeColors();

            Rep.charts.gasto = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data:            data.values || [],
                        backgroundColor: c.blue,
                        borderRadius:    4,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false },
                        tooltip: {
                            backgroundColor: c.tooltip, titleColor: c.text, bodyColor: c.text,
                            callbacks: { label: ctx => ' $' + Math.round(ctx.raw).toLocaleString('es-CO') },
                        },
                    },
                    scales: {
                        x: { ticks: { font: { size: 10 }, color: c.text }, grid: { display: false } },
                        y: { ticks: { font: { size: 10 }, color: c.text,
                            callback: v => '$' + Math.round(v / 1000) + 'k' }, grid: { color: c.grid } },
                    },
                },
            });
        }).catch(() => {});
};

/* ── Visitas al perfil (últimos 30 días) ── */
Rep.cargarGraficaVisitasPerfil = function () {
    const canvas = document.getElementById('chartVisitasPerfil');
    if (!canvas) return;

    fetch('/ascc/backend/users/api/reportes_data.php?action=visitas_perfil&csrf='
        + encodeURIComponent(window.REP_CONFIG?.csrf_token || ''))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (Rep.charts.visitasPerfil) Rep.charts.visitasPerfil.destroy();
            const c = Rep.getThemeColors();

            Rep.charts.visitasPerfil = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data:            data.values || [],
                        borderColor:     c.blue,
                        backgroundColor: 'rgba(59,130,246,0.08)',
                        fill:            true,
                        tension:         0.3,
                        borderWidth:     2,
                        pointRadius:     0,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false },
                        tooltip: { backgroundColor: c.tooltip, titleColor: c.text, bodyColor: c.text } },
                    scales: {
                        x: { ticks: { maxTicksLimit: 8, font: { size: 10 }, color: c.text }, grid: { display: false } },
                        y: { ticks: { font: { size: 10 }, color: c.text, stepSize: 1 }, grid: { color: c.grid },
                            beginAtZero: true },
                    },
                },
            });
        }).catch(() => {});
};

/* ── Evolución calificación (ranking) ── */
Rep.cargarGraficaEvolucion = function () {
    const canvas = document.getElementById('chartEvolucion');
    if (!canvas) return;

    fetch('/ascc/backend/users/api/reportes_data.php?action=evolucion_calificacion&csrf='
        + encodeURIComponent(window.REP_CONFIG?.csrf_token || ''))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (Rep.charts.evolucion) Rep.charts.evolucion.destroy();
            const c = Rep.getThemeColors();

            Rep.charts.evolucion = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data:             data.values || [],
                        borderColor:      c.green,
                        backgroundColor:  c.greenFill,
                        fill:             true,
                        tension:          0.3,
                        borderWidth:      2,
                        pointRadius:      4,
                        pointBackgroundColor: c.green,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false },
                        tooltip: {
                            backgroundColor: c.tooltip, titleColor: c.text, bodyColor: c.text,
                            callbacks: { label: ctx => ` ${ctx.raw.toFixed(1)} ⭐` },
                        },
                    },
                    scales: {
                        x: { ticks: { font: { size: 11 }, color: c.text }, grid: { display: false } },
                        y: {
                            min: 1, max: 5,
                            ticks: { font: { size: 10 }, color: c.text, stepSize: 0.5 },
                            grid: { color: c.grid },
                        },
                    },
                },
            });
        }).catch(() => {});
};

/* =============================================================================
   5. REFRESCO AUTOMÁTICO CADA 30 SEGUNDOS
============================================================================= */
Rep.initAutoRefresh = function () {
    Rep.refreshTimer = setInterval(() => {
        // Solo refrescar si la pestaña está visible
        if (document.hidden) return;

        // Solo refrescar la gráfica de la tab activa — sin mostrar errores
        if (Rep.tabActivo === 'metricas') {
            if (window.REP_CONFIG?.es_vendedor) {
                Rep.cargarGraficaVentas();
            } else {
                Rep.cargarGraficaGasto();
            }
        }
    }, 30000);

    // Detener el timer si el usuario sale de la página
    window.addEventListener('beforeunload', () => {
        clearInterval(Rep.refreshTimer);
    });
};

/* =============================================================================
   6. MODAL DE DENUNCIA
============================================================================= */
Rep.initModal = function () {
    const btnAbrir      = document.getElementById('btnNuevaDenuncia');
    const btnCerrar     = document.getElementById('btnCerrarDenuncia');
    const btnCerrar2    = document.getElementById('btnCerrarDenuncia2');
    const btnEnviar     = document.getElementById('btnEnviarDenuncia');
    const backdrop      = document.getElementById('modalDenBackdrop');
    const modal         = document.getElementById('modalDenuncia');

    if (!modal) return;

    const abrir  = () => {
        modal.classList.add('open');
        backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    const cerrar = () => {
        modal.classList.remove('open');
        backdrop.classList.remove('open');
        document.body.style.overflow = '';
        // Limpiar formulario
        const form = document.getElementById('formDenuncia');
        if (form) form.reset();
        Rep.hideFeedback();
    };

    if (btnAbrir)   btnAbrir.addEventListener('click', abrir);
    if (btnCerrar)  btnCerrar.addEventListener('click', cerrar);
    if (btnCerrar2) btnCerrar2.addEventListener('click', cerrar);
    if (backdrop)   backdrop.addEventListener('click', cerrar);

    // ESC cierra el modal
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') cerrar();
    });

    if (btnEnviar) {
        btnEnviar.addEventListener('click', () => Rep.enviarDenuncia(cerrar));
    }
};

Rep.enviarDenuncia = function (cerrarCb) {
    const tipo        = document.querySelector('input[name="tipo_denuncia"]:checked')?.value;
    const categoria   = document.getElementById('den_categoria')?.value;
    const descripcion = document.getElementById('den_descripcion')?.value?.trim();

    if (!tipo || !categoria || !descripcion) {
        Rep.showFeedback(window.REP_I18N?.den_error_vacio || 'Completa todos los campos.', 'error');
        return;
    }

    const btn = document.getElementById('btnEnviarDenuncia');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Enviando...'; }

    const formData = new FormData();
    formData.append('action',        'crear_denuncia');
    formData.append('tipo_denuncia', tipo);
    formData.append('categoria',     categoria);
    formData.append('descripcion',   descripcion);
    formData.append('csrf_token',    window.REP_CONFIG?.csrf_token || '');

    fetch('/ascc/backend/users/api/reportes_data.php', {
        method: 'POST',
        body:   formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Rep.showToast(window.REP_I18N?.den_creada || 'Denuncia enviada correctamente.');
            setTimeout(cerrarCb, 800);
            // Recargar la tabla de denuncias
            setTimeout(() => window.location.reload(), 1200);
        } else {
            Rep.showFeedback(data.message || window.REP_I18N?.den_error_generico || 'Error.', 'error');
        }
    })
    .catch(() => {
        Rep.showFeedback(window.REP_I18N?.den_error_generico || 'Error de conexión.', 'error');
    })
    .finally(() => {
        if (btn) {
            btn.disabled    = false;
            btn.textContent = '🚨 ' + (window.REP_I18N?.den_btn_enviar || 'Enviar denuncia');
        }
    });
};

Rep.showFeedback = function (msg, tipo) {
    const el = document.getElementById('denFeedback');
    if (!el) return;
    el.textContent  = msg;
    el.className    = `rep-feedback rep-feedback--${tipo}`;
    el.style.display = 'block';
};

Rep.hideFeedback = function () {
    const el = document.getElementById('denFeedback');
    if (el) el.style.display = 'none';
};

/* =============================================================================
   7. BOTONES DE EXPORTACIÓN CON CHECKBOXES
============================================================================= */
Rep.initExportBtns = function () {

    // ── Helper: leer secciones seleccionadas ──────────────────
    function getSeccionesSeleccionadas() {
        const checks = document.querySelectorAll('.rep-check-item input[type="checkbox"]:checked');
        const secciones = Array.from(checks).map(c => c.value);
        return secciones.length > 0 ? secciones.join(',') : 'ventas,productos,visitas,valoraciones';
    }

    // ── Excel ─────────────────────────────────────────────────
    const btnExcel = document.getElementById('btnExcelCompleto');
    if (btnExcel) {
        btnExcel.addEventListener('click', () => {
            const secs = getSeccionesSeleccionadas();
            const csrf = window.REP_CONFIG?.csrf_token || '';
            window.location.href = `/ascc/backend/users/api/reportes_data.php?action=excel&secciones=${secs}&csrf=${csrf}`;
        });
    }

    // ── CSV Power BI ──────────────────────────────────────────
    const btnCsv = document.getElementById('btnCsvPowerBi');
    if (btnCsv) {
        btnCsv.addEventListener('click', () => {
            const secs = getSeccionesSeleccionadas();
            const csrf = window.REP_CONFIG?.csrf_token || '';
            window.location.href = `/ascc/backend/users/api/reportes_data.php?action=csv&secciones=${secs}&csrf=${csrf}`;
        });
    }
};

/* =============================================================================
   8. GENERAR TOKEN API (Power BI)
============================================================================= */
Rep.initGenerarToken = function () {
    const btn = document.getElementById('btnGenerarToken');
    if (!btn) return;

    btn.addEventListener('click', () => {
        btn.disabled    = true;
        btn.textContent = '⏳ Generando...';

        const formData = new FormData();
        formData.append('action',     'generar_token');
        formData.append('csrf_token', window.REP_CONFIG?.csrf_token || '');

        fetch('/ascc/backend/users/api/reportes_data.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.url) {
                    // Mostrar la caja del token
                    const box     = document.getElementById('tokenBox');
                    const urlCode = document.getElementById('tokenUrl');
                    if (box) {
                        box.style.display = 'block';
                        box.style.animation = 'repFadeIn 0.3s ease';
                    }
                    if (urlCode) urlCode.textContent = data.url;
                    btn.textContent = '🔑 Ver mi token API';
                    Rep.showToast('✅ Token generado. Cópialo en Power BI.');
                } else {
                    Rep.showToast('❌ Error al generar el token.', true);
                    btn.textContent = '🔑 ' + (window.REP_I18N?.rep_exp_token || 'Generar token API');
                }
            })
            .catch(() => {
                Rep.showToast('❌ Error de conexión.', true);
                btn.textContent = '🔑 Generar token API';
            })
            .finally(() => {
                btn.disabled = false;
            });
    });
};

/* =============================================================================
   8. COPIAR TOKEN AL PORTAPAPELES
============================================================================= */
window.copiarToken = function () {
    const url = document.getElementById('tokenUrl')?.textContent?.trim();
    if (!url) return;

    navigator.clipboard.writeText(url)
        .then(() => {
            Rep.showToast(window.REP_I18N?.token_copiado || 'URL copiada al portapapeles.');
        })
        .catch(() => {
            // Fallback para navegadores sin clipboard API
            const ta = document.createElement('textarea');
            ta.value = url;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            Rep.showToast(window.REP_I18N?.token_copiado || 'URL copiada al portapapeles.');
        });
};

/* =============================================================================
   9. TOAST
============================================================================= */
Rep.toastTimer = null;

Rep.showToast = function (msg, isError = false, duration = 3500) {
    let toast = document.getElementById('repToast');
    if (!toast) return;

    toast.textContent = msg;
    toast.classList.toggle('rep-toast--error', isError);
    toast.classList.add('show');

    clearTimeout(Rep.toastTimer);
    Rep.toastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
};

/* =============================================================================
   10. SINCRONIZAR GRÁFICAS CUANDO CAMBIA EL TEMA
   sync-global.js despacha un evento 'themeChanged' cuando el usuario cambia tema
============================================================================= */
document.addEventListener('themeChanged', () => {
    // Destruir y re-renderizar todas las gráficas activas
    const renders = {
        ventas:         () => Rep.cargarGraficaVentas(),
        productos:      () => Rep.cargarGraficaProductos(),
        categorias:     () => Rep.cargarGraficaCategorias(),
        precio:         () => Rep.cargarGraficaPrecio(),
        funnel:         () => Rep.cargarGraficaFunnel(),
        visitasPerfil:  () => Rep.cargarGraficaVisitasPerfil(),
        evolucion:      () => Rep.cargarGraficaEvolucion(),
        comprasCat:     () => Rep.cargarGraficaComprasCat(),
        gasto:          () => Rep.cargarGraficaGasto(),
    };

    Object.keys(Rep.charts).forEach(key => {
        if (Rep.charts[key] && renders[key]) {
            renders[key]();
        }
    });
});