/* global uPlot */
(function () {
    'use strict';

    var REFRESH_MS = 30000;

    function axisColor() {
        return getComputedStyle(document.documentElement).getPropertyValue('--bs-body-color').trim() || '#212529';
    }

    function axisConfig(extra) {
        return Object.assign({ stroke: axisColor(), ticks: { stroke: axisColor() }, grid: { stroke: axisColor(), width: 0.5, dash: [4, 4] } }, extra || {});
    }

    function workersOpts(width) {
        return {
            width: width,
            height: 170,
            series: [
                {},
                { label: 'Busy',  stroke: '#0d6efd', width: 1.5 },
                { label: 'Total', stroke: '#6c757d', width: 1.5 },
                { label: 'Ready', stroke: '#198754', width: 1.5 },
                { label: 'Queue', stroke: '#fd7e14', width: 1.5 }
            ],
            axes: [axisConfig(), axisConfig()]
        };
    }

    function httpOpts(width) {
        return {
            width: width,
            height: 170,
            scales: { rate: {}, pct: { range: [0, 100] } },
            series: [
                {},
                { label: 'req/s', scale: 'rate', stroke: '#0d6efd', width: 1.5 },
                { label: 'err %', scale: 'pct',  stroke: '#dc3545', width: 1.5 }
            ],
            axes: [
                axisConfig(),
                axisConfig({ scale: 'rate' }),
                axisConfig({ scale: 'pct', side: 1, values: function (u, vals) { return vals.map(function (v) { return v + '%'; }); } })
            ]
        };
    }

    function latencyOpts(width) {
        return {
            width: width,
            height: 140,
            series: [{}, { label: 'ms', scale: 'ms', stroke: '#6f42c1', width: 1.5 }],
            axes: [axisConfig(), axisConfig({ scale: 'ms' })],
            scales: { ms: {} }
        };
    }

    function init(root) {
        if (!root || root.dataset.twInit || typeof uPlot === 'undefined') {
            return;
        }
        root.dataset.twInit = '1';

        var baseUrl = root.dataset.url;
        var range = root.dataset.range || '1h';
        var workersEl = root.querySelector('[data-chart="workers"]');
        var httpEl = root.querySelector('[data-chart="http"]');
        var latencyEl = root.querySelector('[data-chart="latency"]');
        var emptyEl = root.querySelector('[data-chart-empty]');
        var labelEls = root.querySelectorAll('[data-chart-label]');
        var workers = null;
        var http = null;
        var latency = null;

        function width() {
            return Math.max(240, root.clientWidth);
        }

        function resize() {
            if (workers) { workers.setSize({ width: width(), height: 170 }); }
            if (http) { http.setSize({ width: width(), height: 170 }); }
            if (latency) { latency.setSize({ width: width(), height: 140 }); }
        }

        // ResizeObserver fires when the container becomes visible (tab shown) and on window resize.
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(function () { if (root.clientWidth > 0) { resize(); } }).observe(root);
        }

        function render(d) {
            var t = d.t || [];
            var empty = t.length === 0;

            if (emptyEl) {
                emptyEl.classList.toggle('d-none', !empty);
            }
            [workersEl, httpEl, latencyEl].forEach(function (el) { el.classList.toggle('d-none', empty); });
            labelEls.forEach(function (el) { el.classList.toggle('d-none', empty); });
            if (empty) {
                return;
            }

            var workersData = [t, d.busy || [], d.total || [], d.ready || [], d.queue || []];
            var httpData = [t, d.reqRate || [], d.errPct || []];
            var latencyData = [t, d.latencyMs || []];

            workers = draw(workers, workersOpts(width()), workersData, workersEl);
            http = draw(http, httpOpts(width()), httpData, httpEl);
            latency = draw(latency, latencyOpts(width()), latencyData, latencyEl);
        }

        function draw(chart, opts, data, el) {
            if (chart) {
                chart.setData(data);
                return chart;
            }
            return new uPlot(opts, data, el);
        }

        function fetchData() {
            fetch(baseUrl + '&range=' + encodeURIComponent(range), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) { if (d) { render(d); } })
                .catch(function () { /* transient errors are ignored; next refresh retries */ });
        }

        root.querySelectorAll('[data-range]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                range = btn.dataset.range;
                root.querySelectorAll('[data-range]').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                fetchData();
            });
        });

        window.addEventListener('resize', resize);

        // Rebuild charts when Bootstrap theme switches (dark↔light) so axis colors update.
        var lastTheme = document.documentElement.getAttribute('data-bs-theme');
        new MutationObserver(function () {
            var t = document.documentElement.getAttribute('data-bs-theme');
            if (t !== lastTheme) {
                lastTheme = t;
                if (workers) { workers.destroy(); workers = null; }
                if (http)    { http.destroy();    http    = null; }
                if (latency) { latency.destroy(); latency = null; }
                fetchData();
            }
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

        setInterval(fetchData, REFRESH_MS);
        fetchData();
    }

    function scan() {
        document.querySelectorAll('#tw-app-metrics-charts').forEach(init);
    }

    document.addEventListener('DOMContentLoaded', scan);
    document.body.addEventListener('htmx:afterSwap', scan);
}());
