/**
 * Aromas y Sabores de mi Campo Colombiano (ASCC) - Admin Categories JS
 * Ruta: admin/assets/js/admin-categories.js
 * Descripción: Lógica de la página de categorías:
 *              gráfica de barras horizontales con Chart.js.
 */

document.addEventListener('DOMContentLoaded', () => {

    // Mantenemos ASCC_CATS porque es la referencia al Backend
    const data = window.ASCC_CATS; 
    if (!data || !data.labels || data.labels.length === 0) return;

    const isDark   = data.theme === 'dark';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';

    const ctx = document.getElementById('catChart');
    if (!ctx) return;

    new Chart(ctx, {
        // ... (el resto del código de la gráfica se mantiene igual)
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: data.lang.disponibles,
                    data : data.dispon,
                    backgroundColor: data.colors.map(c => c + 'cc'), 
                    borderColor    : data.colors,
                    borderWidth    : 1.5,
                    borderRadius   : 6,
                    borderSkipped  : false,
                },
                {
                    label: data.lang.vendidos,
                    data : data.totales.map((t, i) => t - data.dispon[i]),
                    backgroundColor: data.colors.map(() => isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)'),
                    borderColor    : data.colors.map(() => isDark ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.1)'),
                    borderWidth    : 1,
                    borderRadius   : 6,
                    borderSkipped  : false,
                }
            ]
        },
        options: {
            indexAxis  : 'y',
            responsive : true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position : 'top',
                    align    : 'end',
                    labels   : {
                        color    : textColor,
                        font     : { family: 'DM Sans', size: 12 },
                        boxWidth : 12,
                        boxHeight: 12,
                        borderRadius: 3,
                        padding  : 16,
                    }
                },
                tooltip: {
                    backgroundColor: isDark ? '#1e293b' : '#ffffff',
                    titleColor     : isDark ? '#f1f5f9' : '#0f172a',
                    bodyColor      : isDark ? '#94a3b8' : '#475569',
                    borderColor    : isDark ? '#334155' : '#e2e8f0',
                    borderWidth    : 1,
                    padding        : 12,
                    cornerRadius   : 8,
                    titleFont      : { family: 'Syne', size: 13, weight: '700' },
                    bodyFont       : { family: 'DM Sans', size: 12 },
                }
            },
            scales: {
                x: {
                    stacked: true,
                    grid   : { color: gridColor },
                    border : { display: false },
                    ticks  : {
                        color     : textColor,
                        font      : { family: 'DM Sans', size: 11 },
                        stepSize  : 1,
                        precision : 0,
                    }
                },
                y: {
                    stacked: true,
                    grid   : { display: false },
                    border : { display: false },
                    ticks  : {
                        color    : textColor,
                        font     : { family: 'DM Sans', size: 12 },
                        padding  : 8,
                    }
                }
            }
        }
    });

    const height = Math.max(300, data.labels.length * 46);
    ctx.parentElement.style.height = height + 'px';
});