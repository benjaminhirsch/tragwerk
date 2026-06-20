/* ============================================================
   Tragwerk — Server detail metrics (uPlot), design theming,
   wired to server.metrics.data (cpu / mem / disk / load).
   ============================================================ */
/* global uPlot */
(function () {
    'use strict';

    var REFRESH_MS = 30000;
    var docEl = document.documentElement;

    var pc = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    function toRGB(css) {
        pc.fillStyle = '#000';
        pc.fillStyle = css;
        pc.fillRect(0, 0, 1, 1);
        var d = pc.getImageData(0, 0, 1, 1).data;
        return d[0] + ', ' + d[1] + ', ' + d[2];
    }
    function resolve(varName) {
        return 'rgb(' + toRGB(getComputedStyle(docEl).getPropertyValue(varName).trim()) + ')';
    }
    function rgba(rgb, a) {
        return rgb.replace('rgb(', 'rgba(').replace(')', ', ' + a + ')');
    }
    function palette() {
        return {
            accent: resolve('--accent'),
            purple: resolve('--purple'),
            ok:     resolve('--ok'),
            warn:   resolve('--warn'),
            faint:  resolve('--text-faint'),
            grid:   rgba(resolve('--border'), 0.7),
            surface: resolve('--surface')
        };
    }

    function tooltipPlugin(fmt) {
        var tip;
        return {
            hooks: {
                init: function (u) {
                    tip = document.createElement('div');
                    tip.className = 'u-tooltip';
                    u.over.appendChild(tip);
                },
                setCursor: function (u) {
                    var idx = u.cursor.idx;
                    if (idx == null || u.cursor.left < 0) { tip.style.display = 'none'; return; }
                    var d = new Date(u.data[0][idx] * 1000);
                    var ts = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    var rows = '';
                    for (var s = 1; s < u.series.length; s++) {
                        var ser = u.series[s];
                        rows += '<div class="tt-row"><i style="background:' + ser._color + '"></i>'
                            + ser.label + ': ' + fmt(u.data[s][idx]) + '</div>';
                    }
                    tip.innerHTML = '<div class="tt-t">' + ts + '</div>' + rows;
                    tip.style.display = 'block';
                    tip.style.left = u.valToPos(u.data[0][idx], 'x') + 'px';
                    var tops = [];
                    for (var i = 1; i < u.series.length; i++) {
                        var v = u.data[i][idx];
                        if (v != null) { tops.push(u.valToPos(v, u.series[i].scale || 'y')); }
                    }
                    tip.style.top = (tops.length ? Math.min.apply(null, tops) : 20) + 'px';
                }
            }
        };
    }

    function baseAxes(p, range, axOpts) {
        return [
            {
                stroke: p.faint,
                grid: { stroke: p.grid, width: 1 },
                ticks: { stroke: p.grid, width: 1, size: 4 },
                font: '11px Manrope', space: 70, size: 30,
                values: function (u, splits) {
                    return splits.map(function (v) {
                        var d = new Date(v * 1000);
                        return range === '7d'
                            ? d.toLocaleDateString([], { weekday: 'short' })
                            : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    });
                }
            },
            Object.assign({
                stroke: p.faint,
                grid: { stroke: p.grid, width: 1 },
                ticks: { show: false },
                font: '11px Manrope', size: 42, side: 3
            }, axOpts || {})
        ];
    }

    function area(rgb) {
        return function (u) {
            var ctx = u.ctx;
            var g = ctx.createLinearGradient(0, u.bbox.top, 0, u.bbox.top + u.bbox.height);
            g.addColorStop(0, rgba(rgb, 0.28));
            g.addColorStop(1, rgba(rgb, 0.01));
            return g;
        };
    }

    function makeChart(el, p, range, def, data, axOpts, fmt) {
        return new uPlot({
            width: el.clientWidth || 400,
            height: el.clientHeight || 150,
            padding: [12, 8, 0, 4],
            cursor: { points: { size: 7, width: 2, stroke: p.surface }, y: false },
            legend: { show: false },
            scales: { x: { time: true }, y: { range: axOpts && axOpts.range } },
            axes: baseAxes(p, range, axOpts),
            series: [
                {},
                { label: def.label, stroke: def.color, width: 2, fill: area(def.color), points: { show: false }, _color: def.color, scale: 'y' }
            ],
            plugins: [tooltipPlugin(fmt)]
        }, [data.x, data.y], el);
    }

    function init(root) {
        if (!root || root.dataset.twInit || typeof uPlot === 'undefined') { return; }
        root.dataset.twInit = '1';

        var baseUrl = root.dataset.url;
        var range = root.dataset.range || '24h';
        var emptyEl = root.querySelector('[data-chart-empty]');
        var gridEl = root.querySelector('[data-chart-grid]');
        var els = {
            cpu: document.getElementById('c-cpu'),
            mem: document.getElementById('c-mem'),
            disk: document.getElementById('c-disk'),
            load: document.getElementById('c-load')
        };
        var charts = {};

        function setSpark(name, text) {
            var el = root.querySelector('[data-spark="' + name + '"]');
            if (el) { el.textContent = text; }
        }
        function last(arr) { return arr && arr.length ? (+arr[arr.length - 1] || 0) : 0; }

        function destroy() {
            Object.keys(charts).forEach(function (k) {
                if (charts[k]) { charts[k].destroy(); charts[k] = null; }
            });
        }

        function render(d) {
            var t = d.t || [];
            var empty = t.length === 0;
            if (emptyEl) { emptyEl.classList.toggle('d-none', !empty); }
            if (gridEl) { gridEl.classList.toggle('d-none', empty); }
            destroy();
            if (empty) {
                ['cpu', 'mem', 'disk', 'load'].forEach(function (n) { setSpark(n, '—'); });
                return;
            }

            var p = palette();
            var pct = { range: [0, 100] };
            charts.cpu = makeChart(els.cpu, p, range, { label: 'CPU', color: p.accent }, { x: t, y: d.cpu || [] }, pct, function (v) { return Math.round(v) + ' %'; });
            charts.mem = makeChart(els.mem, p, range, { label: 'Mem', color: p.purple }, { x: t, y: d.mem || [] }, pct, function (v) { return Math.round(v) + ' %'; });
            charts.disk = makeChart(els.disk, p, range, { label: 'Disk', color: p.warn }, { x: t, y: d.disk || [] }, pct, function (v) { return Math.round(v) + ' %'; });
            charts.load = makeChart(els.load, p, range, { label: 'Load', color: p.ok }, { x: t, y: d.load || [] }, { range: function (u, min, max) { return [0, (max || 1) * 1.2]; } }, function (v) { return (Math.round(v * 100) / 100); });

            setSpark('cpu', Math.round(last(d.cpu)) + ' %');
            setSpark('mem', Math.round(last(d.mem)) + ' %');
            setSpark('disk', Math.round(last(d.disk)) + ' %');
            setSpark('load', (Math.round(last(d.load) * 100) / 100).toString());
        }

        function resize() {
            Object.keys(charts).forEach(function (k) {
                var c = charts[k];
                if (!c) { return; }
                var el = c.root.parentElement;
                c.setSize({ width: el.clientWidth, height: el.clientHeight });
            });
        }

        function fetchData() {
            fetch(baseUrl + '?range=' + encodeURIComponent(range), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) { if (d) { render(d); } })
                .catch(function () { /* transient errors ignored */ });
        }

        var rangeBtns = document.querySelectorAll('#range-seg [data-range]');
        rangeBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                range = btn.dataset.range;
                rangeBtns.forEach(function (b) { b.classList.toggle('active', b === btn); });
                fetchData();
            });
        });

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(function () { if (root.clientWidth > 0) { resize(); } }).observe(root);
        }

        var lastTheme = docEl.getAttribute('data-bs-theme');
        new MutationObserver(function () {
            var th = docEl.getAttribute('data-bs-theme');
            if (th !== lastTheme) { lastTheme = th; fetchData(); }
        }).observe(docEl, { attributes: true, attributeFilter: ['data-bs-theme'] });

        setInterval(fetchData, REFRESH_MS);
        fetchData();
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(fetchData);
        }
    }

    function scan() {
        document.querySelectorAll('#tw-metrics-charts').forEach(init);
    }

    document.addEventListener('DOMContentLoaded', scan);
    document.body.addEventListener('htmx:afterSwap', scan);
}());
