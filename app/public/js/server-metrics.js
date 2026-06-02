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

    function usageOpts(width) {
        return {
            width: width,
            height: 180,
            scales: { y: { range: [0, 100] } },
            series: [
                {},
                { label: 'CPU',  stroke: '#0d6efd', width: 1.5 },
                { label: 'Mem',  stroke: '#198754', width: 1.5 },
                { label: 'Disk', stroke: '#fd7e14', width: 1.5 }
            ],
            axes: [axisConfig(), axisConfig({ values: function (u, vals) { return vals.map(function (v) { return v + '%'; }); } })]
        };
    }

    function loadOpts(width) {
        return {
            width: width,
            height: 140,
            series: [{}, { label: 'Load', stroke: '#6f42c1', width: 1.5 }],
            axes: [axisConfig(), axisConfig()]
        };
    }

    function init(root) {
        if (!root || root.dataset.twInit || typeof uPlot === 'undefined') {
            return;
        }
        root.dataset.twInit = '1';

        var baseUrl = root.dataset.url;
        var range = root.dataset.range || '1h';
        var usageEl = root.querySelector('[data-chart="usage"]');
        var loadEl = root.querySelector('[data-chart="load"]');
        var emptyEl = root.querySelector('[data-chart-empty]');
        var labelEls = root.querySelectorAll('[data-chart-label]');
        var usage = null;
        var load = null;
        var timer = null;

        function width() {
            return Math.max(240, root.clientWidth);
        }

        function resize() {
            if (usage) { usage.setSize({ width: width(), height: 180 }); }
            if (load) { load.setSize({ width: width(), height: 140 }); }
        }

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(function () { if (root.clientWidth > 0) { resize(); } }).observe(root);
        }

        function render(d) {
            var t = d.t || [];
            var empty = t.length === 0;

            if (emptyEl) {
                emptyEl.classList.toggle('d-none', !empty);
            }
            usageEl.classList.toggle('d-none', empty);
            loadEl.classList.toggle('d-none', empty);
            labelEls.forEach(function (el) { el.classList.toggle('d-none', empty); });
            if (empty) {
                return;
            }

            var usageData = [t, d.cpu || [], d.mem || [], d.disk || []];
            var loadData = [t, d.load || []];

            if (usage) {
                usage.setData(usageData);
            } else {
                usage = new uPlot(usageOpts(width()), usageData, usageEl);
            }

            if (load) {
                load.setData(loadData);
            } else {
                load = new uPlot(loadOpts(width()), loadData, loadEl);
            }
        }

        function fetchData() {
            fetch(baseUrl + '?range=' + encodeURIComponent(range), { headers: { Accept: 'application/json' } })
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

        var lastTheme = document.documentElement.getAttribute('data-bs-theme');
        new MutationObserver(function () {
            var t = document.documentElement.getAttribute('data-bs-theme');
            if (t !== lastTheme) {
                lastTheme = t;
                if (usage) { usage.destroy(); usage = null; }
                if (load)  { load.destroy();  load  = null; }
                fetchData();
            }
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

        if (timer) { clearInterval(timer); }
        timer = setInterval(fetchData, REFRESH_MS);

        fetchData();
    }

    function scan() {
        document.querySelectorAll('#tw-metrics-charts').forEach(init);
    }

    document.addEventListener('DOMContentLoaded', scan);
    document.body.addEventListener('htmx:afterSwap', scan);
}());
