/* =====================================================================
   QueueEase - Dashboard and report charts (uses Chart.js, stored locally
   in assets/js/vendor so it works without internet)
   File: assets/js/charts.js
   Reads chart data from <script id="chartData" type="application/json">.
   Colours follow the light/dark theme and update when it changes.
   ===================================================================== */
(function () {
    'use strict';

    var source = document.getElementById('chartData');
    if (!source || typeof Chart === 'undefined') { return; }
    var data = JSON.parse(source.textContent);
    var charts = [];

    var palette = {
        primary: '#6d5dfc', violet: '#a855f7', success: '#10b981', warning: '#f59e0b', danger: '#f43f5e',
        info: '#0ea5e9', purple: '#8b5cf6', orange: '#f97316', slate: '#94a3b8', cyan: '#06b6d4'
    };
    var statusColors = [palette.warning, palette.info, palette.purple, palette.success, palette.orange, palette.danger];
    var serviceColors = [palette.primary, palette.cyan, palette.success, palette.warning, palette.violet, palette.danger, palette.orange, palette.slate];

    function themeColors() {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            text: dark ? '#8a90b2' : '#6b7189',
            grid: dark ? 'rgba(138,144,178,.12)' : 'rgba(15,18,34,.06)',
            empty: dark ? '#22264a' : '#e6e8f2',
            surface: dark ? '#10132a' : '#ffffff',
            tooltip: dark ? '#1b1f42' : '#0f1222'
        };
    }

    function applyDefaults() {
        var c = themeColors();
        Chart.defaults.font.family = '"Plus Jakarta Sans", "Segoe UI", system-ui, sans-serif';
        Chart.defaults.font.size = 12;
        Chart.defaults.color = c.text;
        Chart.defaults.borderColor = c.grid;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.boxWidth = 8;
        Chart.defaults.plugins.legend.labels.padding = 16;
        Chart.defaults.plugins.tooltip.backgroundColor = c.tooltip;
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.cornerRadius = 10;
        Chart.defaults.plugins.tooltip.titleFont = { weight: '700' };
        return c;
    }

    // Soft vertical gradient for bars and line fills
    function gradient(ctx, color, alphaTop, alphaBottom) {
        var chart = ctx.chart;
        if (!chart.chartArea) { return color; }
        var g = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
        g.addColorStop(0, hexToRgba(color, alphaTop));
        g.addColorStop(1, hexToRgba(color, alphaBottom));
        return g;
    }
    function hexToRgba(hex, a) {
        var n = parseInt(hex.slice(1), 16);
        return 'rgba(' + (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
    }

    function make(id, config) {
        var el = document.getElementById(id);
        if (el) { charts.push(new Chart(el, config)); }
    }

    function build() {
        charts.forEach(function (ch) { ch.destroy(); });
        charts = [];
        var c = applyDefaults();
        var axes = {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: c.grid }, border: { display: false } },
            x: { grid: { display: false }, border: { display: false } }
        };

        if (data.daily) {
            make('dailyChart', {
                type: 'bar',
                data: {
                    labels: data.daily.labels,
                    datasets: [
                        { label: 'Generated', data: data.daily.generated, backgroundColor: function (ctx) { return gradient(ctx, palette.primary, 1, .55); }, borderRadius: 8, maxBarThickness: 26 },
                        { label: 'Served', data: data.daily.served, backgroundColor: function (ctx) { return gradient(ctx, palette.success, 1, .55); }, borderRadius: 8, maxBarThickness: 26 },
                        { label: 'Skipped / cancelled', data: data.daily.not_served, backgroundColor: function (ctx) { return gradient(ctx, palette.danger, 1, .55); }, borderRadius: 8, maxBarThickness: 26 }
                    ]
                },
                options: { scales: axes, plugins: { legend: { position: 'bottom' } } }
            });
        }

        if (data.status) {
            var hasStatus = data.status.data.some(function (v) { return v > 0; });
            make('statusChart', {
                type: 'doughnut',
                data: {
                    labels: data.status.labels,
                    datasets: [{ data: hasStatus ? data.status.data : [1], backgroundColor: hasStatus ? statusColors : [c.empty], borderColor: c.surface, borderWidth: 3, hoverOffset: 6 }]
                },
                options: {
                    cutout: '70%',
                    plugins: {
                        legend: { position: 'bottom', display: hasStatus },
                        tooltip: { enabled: hasStatus },
                        title: { display: !hasStatus, text: 'No tickets yet today', position: 'bottom' }
                    }
                }
            });
        }

        if (data.services) {
            make('serviceChart', {
                type: 'bar',
                data: {
                    labels: data.services.labels,
                    datasets: [{ label: 'Tickets', data: data.services.data, backgroundColor: serviceColors, borderRadius: 8, maxBarThickness: 24 }]
                },
                options: { indexAxis: 'y', scales: { x: axes.y, y: { grid: { display: false }, border: { display: false } } }, plugins: { legend: { display: false } } }
            });
        }

        if (data.trend) {
            make('trendChart', {
                type: 'line',
                data: {
                    labels: data.trend.labels,
                    datasets: [
                        { label: 'Generated', data: data.trend.generated, borderColor: palette.primary, backgroundColor: function (ctx) { return gradient(ctx, palette.primary, .35, 0); }, fill: true, tension: .4, borderWidth: 3, pointRadius: 3, pointBackgroundColor: palette.primary },
                        { label: 'Served', data: data.trend.served, borderColor: palette.success, backgroundColor: function (ctx) { return gradient(ctx, palette.success, .25, 0); }, fill: true, tension: .4, borderWidth: 3, pointRadius: 3, pointBackgroundColor: palette.success }
                    ]
                },
                options: { scales: axes, plugins: { legend: { position: 'bottom' } }, interaction: { mode: 'index', intersect: false } }
            });
        }

        if (data.ratings) {
            make('ratingChart', {
                type: 'bar',
                data: {
                    labels: ['1 star', '2 stars', '3 stars', '4 stars', '5 stars'],
                    datasets: [{ label: 'Ratings', data: data.ratings, backgroundColor: ['#f43f5e', '#f97316', '#f59e0b', '#84cc16', '#10b981'], borderRadius: 8, maxBarThickness: 34 }]
                },
                options: { scales: axes, plugins: { legend: { display: false } } }
            });
        }
    }

    build();
    window.addEventListener('themechange', build);
})();
