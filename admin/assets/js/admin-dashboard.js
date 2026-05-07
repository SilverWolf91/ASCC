/**
 * ASCC - Admin Dashboard JavaScript
 * Ruta: assets/js/admin-dashboard.js
 * Descripción: Lógica del panel de administración: gráficas, tema, sidebar, animaciones
 */

'use strict';

/* =============================================================================
   MÓDULO: Utilidades
============================================================================= */
const AgUtils = {
    /**
     * Formatea un número con separadores de miles colombianos
     * @param {number} num
     * @returns {string}
     */
    formatNumber(num) {
        return new Intl.NumberFormat('es-CO').format(num);
    },

    /**
     * Retorna la fecha actual formateada en el idioma de la página
     * @returns {string}
     */
    getFormattedDate() {
        const lang = document.documentElement.lang || 'es';
        const locale = lang === 'es' ? 'es-CO' : 'en-US';
        return new Date().toLocaleDateString(locale, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    },

    /**
     * Lee una cookie por nombre
     * @param {string} name
     * @returns {string|null}
     */
    getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    },

    /**
     * Escribe una cookie
     * @param {string} name
     * @param {string} value
     * @param {number} days
     */
    setCookie(name, value, days = 365) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = `${name}=${encodeURIComponent(value)};expires=${expires};path=/;SameSite=Lax`;
    },
};

/* =============================================================================
   MÓDULO: Tema (Dark / Light)
============================================================================= */
const AgTheme = {
    KEY: 'ag_theme',
    DARK: 'dark',
    LIGHT: 'light',

    init() {
        // El tema viene de la sesión de ASCC, pero sincronizamos con localStorage como fallback
        const phpTheme = window.ASCC?.theme || this.LIGHT;
        const storedTheme = localStorage.getItem(this.KEY) || phpTheme;
        this.apply(storedTheme, false);
        this._bindToggle();
    },

    apply(theme, save = true) {
        document.documentElement.setAttribute('data-theme', theme);
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = theme === this.DARK ? 'fas fa-sun' : 'fas fa-moon';
        }
        if (save) {
            localStorage.setItem(this.KEY, theme);
            AgUtils.setCookie('ag_theme', theme);
            // Informar al backend del cambio via fetch (no bloqueante)
            this._syncToServer(theme);
        }
    },

    toggle() {
        const current = document.documentElement.getAttribute('data-theme') || this.LIGHT;
        this.apply(current === this.DARK ? this.LIGHT : this.DARK);
    },

    _syncToServer(theme) {
        // Sincroniza la preferencia de tema con la sesión PHP
        fetch('../api/set-theme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ theme }),
        }).catch(() => {
            // Silenciar error si el endpoint no existe aún
        });
    },

    _bindToggle() {
        const btn = document.getElementById('themeToggle');
        if (btn) {
            btn.addEventListener('click', () => this.toggle());
        }
    },
};

/* =============================================================================
   MÓDULO: Sidebar
============================================================================= */
const AgSidebar = {
    KEY: 'ag_sidebar_collapsed',

    init() {
        this.$sidebar = document.getElementById('agSidebar');
        this.$main    = document.getElementById('agMain');
        this.$toggle  = document.getElementById('sidebarToggle');
        this.$icon    = document.getElementById('sidebarToggleIcon');
        this.$mobileBtn = document.getElementById('mobileMenuBtn');

        // Crear overlay para móvil
        this.$overlay = document.createElement('div');
        this.$overlay.className = 'ag-overlay';
        this.$overlay.id = 'agOverlay';
        document.body.appendChild(this.$overlay);

        // Restaurar estado previo en desktop
        const wasCollapsed = localStorage.getItem(this.KEY) === 'true';
        if (wasCollapsed) this._collapse(false);

        this._bindEvents();
    },

    _bindEvents() {
        // Toggle colapsar en desktop
        if (this.$toggle) {
            this.$toggle.addEventListener('click', () => this.toggleCollapse());
        }

        // Abrir en móvil
        if (this.$mobileBtn) {
            this.$mobileBtn.addEventListener('click', () => this._openMobile());
        }

        // Cerrar al hacer clic en overlay
        if (this.$overlay) {
            this.$overlay.addEventListener('click', () => this._closeMobile());
        }

        // Cerrar con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this._closeMobile();
        });
    },

    toggleCollapse() {
        const isCollapsed = this.$sidebar.classList.contains('ag-sidebar--collapsed');
        isCollapsed ? this._expand() : this._collapse();
    },

    _collapse(save = true) {
        this.$sidebar.classList.add('ag-sidebar--collapsed');
        this.$main.classList.add('ag-main--expanded');
        if (this.$icon) this.$icon.className = 'fas fa-chevron-right';
        if (save) localStorage.setItem(this.KEY, 'true');
    },

    _expand() {
        this.$sidebar.classList.remove('ag-sidebar--collapsed');
        this.$main.classList.remove('ag-main--expanded');
        if (this.$icon) this.$icon.className = 'fas fa-chevron-left';
        localStorage.setItem(this.KEY, 'false');
    },

    _openMobile() {
        this.$sidebar.classList.add('ag-sidebar--mobile-open');
        this.$overlay.classList.add('ag-overlay--visible');
        document.body.style.overflow = 'hidden';
    },

    _closeMobile() {
        this.$sidebar.classList.remove('ag-sidebar--mobile-open');
        this.$overlay.classList.remove('ag-overlay--visible');
        document.body.style.overflow = '';
    },
};

/* =============================================================================
   MÓDULO: Animación de contadores KPI
============================================================================= */
const AgCounters = {
    /**
     * Anima todos los elementos con data-count desde 0 hasta su valor objetivo
     */
    init() {
        const elements = document.querySelectorAll('[data-count]');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this._animateCount(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        elements.forEach(el => observer.observe(el));
    },

    _animateCount(el) {
        const target    = parseInt(el.dataset.count, 10);
        const duration  = 1200; // ms
        const startTime = performance.now();

        const step = (currentTime) => {
            const elapsed  = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Easing: easeOutExpo
            const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            const current = Math.round(eased * target);

            el.textContent = AgUtils.formatNumber(current);

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    },
};

/* =============================================================================
   MÓDULO: Gráficas (Chart.js)
============================================================================= */
const AgCharts = {
    _charts: {},

    init() {
        if (typeof Chart === 'undefined') {
            console.warn('ASCC: Chart.js no disponible.');
            return;
        }

        // Configurar Chart.js con los colores del tema actual
        this._setupChartDefaults();
        this._renderCategoryChart();
        this._renderUsersChart();

        // Re-renderizar gráficas cuando cambia el tema
        document.documentElement.addEventListener('themeChanged', () => {
            this._setupChartDefaults();
            this._destroyAll();
            this._renderCategoryChart();
            this._renderUsersChart();
        });
    },

    _getThemeColors() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            text:    isDark ? '#8ab894' : '#5a7260',
            grid:    isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
            tooltip: isDark ? '#111e12' : '#ffffff',
        };
    },

    _setupChartDefaults() {
        const colors = this._getThemeColors();
        Chart.defaults.color  = colors.text;
        Chart.defaults.font.family = "'DM Sans', sans-serif";
        Chart.defaults.font.size   = 12;
    },

    /**
     * Gráfica de dona — Ventas por categoría
     */
    _renderCategoryChart() {
        const canvas = document.getElementById('categoryChart');
        if (!canvas) return;

        const data = window.ASCC?.categoryChartData || [];
        const colors = this._getThemeColors();

        this._charts.category = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.label),
                datasets: [{
                    data:            data.map(d => d.value),
                    backgroundColor: data.map(d => d.color),
                    borderColor:     colors.tooltip,
                    borderWidth:     3,
                    hoverOffset:     8,
                }],
            },
            options: {
                responsive:         true,
                maintainAspectRatio: false,
                cutout:             '68%',
                plugins: {
                    legend: {
                        display: false, // Usamos leyenda personalizada en HTML
                    },
                    tooltip: {
                        backgroundColor: colors.tooltip,
                        titleColor:      colors.text,
                        bodyColor:       colors.text,
                        borderColor:     colors.grid,
                        borderWidth:     1,
                        padding:         10,
                        callbacks: {
                            label: (ctx) => ` ${ctx.label}: ${ctx.raw}%`,
                        },
                    },
                },
                animation: {
                    animateScale:  true,
                    animateRotate: true,
                    duration:      900,
                    easing:        'easeOutQuart',
                },
            },
        });
    },

    /**
     * Gráfica de barras — Nuevos usuarios por día
     */
    _renderUsersChart() {
        const canvas = document.getElementById('usersChart');
        if (!canvas) return;

        const data   = window.ASCC?.userRegistrationsData || [];
        const label  = window.ASCC?.lang?.chartUsersLabel || 'Usuarios';
        const colors = this._getThemeColors();

        this._charts.users = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.map(d => d.day),
                datasets: [{
                    label,
                    data:            data.map(d => d.count),
                    backgroundColor: 'rgba(45, 106, 79, 0.8)',
                    hoverBackgroundColor: '#52b788',
                    borderRadius:    6,
                    borderSkipped:   false,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color:      colors.grid,
                            drawBorder: false,
                        },
                        border: { display: false, dash: [4, 4] },
                        ticks: {
                            stepSize: 10,
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: colors.tooltip,
                        titleColor:      colors.text,
                        bodyColor:       colors.text,
                        borderColor:     colors.grid,
                        borderWidth:     1,
                        padding:         10,
                        callbacks: {
                            label: (ctx) => ` ${ctx.raw} ${label}`,
                        },
                    },
                },
                animation: {
                    duration: 800,
                    easing:   'easeOutBounce',
                },
            },
        });
    },

    _destroyAll() {
        Object.values(this._charts).forEach(chart => chart?.destroy());
        this._charts = {};
    },
};

/* =============================================================================
   MÓDULO: Internacionalización (cambio de idioma)
============================================================================= */
const AgLang = {
    /**
     * Redirige a la misma página con el parámetro de idioma
     * @param {string} lang — 'es' o 'en'
     */
    switch(lang) {
        // Sincronizar con servidor via cookie y recargar
        AgUtils.setCookie('ag_lang', lang);
        // Enviar al backend y recargar
        fetch('../api/set-lang.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lang }),
        })
        .finally(() => window.location.reload());
    },
};

// Exponer al scope global para los onclick del PHP
window.switchLang = (lang) => AgLang.switch(lang);

/* =============================================================================
   MÓDULO: Fecha actual
============================================================================= */
const AgDate = {
    init() {
        const el = document.getElementById('currentDate');
        if (el) el.textContent = AgUtils.getFormattedDate();
    },
};

/* =============================================================================
   MÓDULO: Búsqueda rápida en topbar
============================================================================= */
const AgSearch = {
    init() {
        const input = document.querySelector('.ag-topbar__search input');
        if (!input) return;

        let debounceTimer;

        input.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const query = e.target.value.trim();
                if (query.length > 2) {
                    // TODO: Implementar búsqueda AJAX cuando el endpoint esté listo
                    console.info('ASCC Search:', query);
                }
            }, 350);
        });

        // Atajo de teclado: / para enfocar el buscador
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
                e.preventDefault();
                input.focus();
            }
        });
    },
};

/* =============================================================================
   INICIALIZACIÓN GENERAL — DOM Ready
============================================================================= */
document.addEventListener('DOMContentLoaded', () => {
    AgTheme.init();
    AgSidebar.init();
    AgCounters.init();
    AgCharts.init();
    AgDate.init();
    AgSearch.init();

    console.info('✅ ASCC Admin Dashboard iniciado correctamente.');
});