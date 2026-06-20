/* ============================================================
   Tragwerk — Metrics (uPlot) · FrankenPHP runtime
   Design theming from the handoff, wired to live /metrics/data.
   ============================================================ */
/* global uPlot */
(function () {
    'use strict';

    var REFRESH_MS = 30000;
    var docEl = document.documentElement;

    /* ---- color resolution (any CSS color -> rgb, via painted pixel) ---- */
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
            danger: resolve('--danger'),
            text:   resolve('--text-muted'),
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
                    var left = u.cursor.left;
                    if (idx == null || left < 0) { tip.style.display = 'none'; return; }
                    var d = new Date(u.data[0][idx] * 1000);
                    var ts = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    var rows = '';
                    for (var s = 1; s < u.series.length; s++) {
                        var ser = u.series[s];
                        if (ser.show === false) { continue; }
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

    function makeChart(el, p, range, seriesDefs, data, axOpts, fmt) {
        var series = [{}];
        var cols = [data.x];
        seriesDefs.forEach(function (s) {
            series.push({
                label: s.label, stroke: s.color, width: 2,
                fill: s.fill ? area(s.color) : undefined,
                points: { show: false }, _color: s.color, scale: 'y'
            });
            cols.push(data[s.key] || []);
        });
        return new uPlot({
            width: el.clientWidth || 600,
            height: el.clientHeight || 220,
            padding: [12, 8, 0, 4],
            cursor: { points: { size: 7, width: 2, stroke: p.surface }, y: false },
            legend: { show: false },
            scales: { x: { time: true }, y: { range: axOpts && axOpts.range } },
            axes: baseAxes(p, range, axOpts),
            series: series,
            plugins: [tooltipPlugin(fmt || function (v) { return v; })]
        }, cols, el);
    }

    function init(root) {
        if (!root || root.dataset.twInit || typeof uPlot === 'undefined') { return; }
        root.dataset.twInit = '1';

        var baseUrl = root.dataset.url;
        var range = root.dataset.range || '1h';
        var emptyEl = root.querySelector('[data-chart-empty]');
        var gridEl = root.querySelector('[data-chart-grid]');
        var els = {
            rps: document.getElementById('c-rps'),
            latency: document.getElementById('c-latency'),
            workers: document.getElementById('c-workers')
        };
        var charts = {};

        function num(v) {
            v = +v || 0;
            return v < 10 ? Math.round(v * 100) / 100 : Math.round(v);
        }
        function avg(arr) {
            if (!arr || !arr.length) { return 0; }
            var s = 0;
            for (var i = 0; i < arr.length; i++) { s += (+arr[i] || 0); }
            return s / arr.length;
        }
        function last(arr) { return arr && arr.length ? (+arr[arr.length - 1] || 0) : 0; }
        function setSpark(name, text) {
            var el = root.querySelector('[data-spark="' + name + '"]');
            if (el) { el.textContent = text; }
        }

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
                setSpark('rps', '—');
                setSpark('rps-avg', '');
                setSpark('latency', '—');
                return;
            }

            var p = palette();
            var data = {
                x: t,
                rps: d.reqRate || [],
                ms: d.latencyMs || [],
                busy: d.busy || [],
                total: d.total || []
            };

            charts.rps = makeChart(els.rps, p, range,
                [{ key: 'rps', label: 'req/s', color: p.accent, fill: true }],
                data, { range: function (u, min, max) { return [0, (max || 1) * 1.15]; } },
                function (v) { return num(v) + ' req/s'; });

            charts.latency = makeChart(els.latency, p, range,
                [{ key: 'ms', label: 'avg', color: p.purple, fill: true }],
                data, { range: function (u, min, max) { return [0, (max || 1) * 1.1]; } },
                function (v) { return Math.round(v) + ' ms'; });

            charts.workers = makeChart(els.workers, p, range,
                [{ key: 'busy', label: 'busy', color: p.accent, fill: true },
                 { key: 'total', label: 'total', color: p.faint }],
                data, { range: function (u, min, max) { return [0, (max || 1) * 1.1]; } },
                function (v) { return Math.round(v); });

            setSpark('rps', num(last(data.rps)) + ' req/s');
            setSpark('rps-avg', 'avg ' + num(avg(data.rps)) + ' req/s');
            setSpark('latency', Math.round(last(data.ms)) + ' ms');
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
            fetch(baseUrl + '&range=' + encodeURIComponent(range), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) { if (d) { render(d); } })
                .catch(function () { /* transient errors ignored; next refresh retries */ });
        }

        var rangeBtns = document.querySelectorAll('#range-seg [data-range]');
        rangeBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                range = btn.dataset.range;
                rangeBtns.forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
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
        // Redraw once webfonts are ready so axis text metrics are correct.
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(fetchData);
        }
    }

    function scan() {
        document.querySelectorAll('#tw-app-metrics-charts').forEach(init);
    }

    document.addEventListener('DOMContentLoaded', scan);
    document.body.addEventListener('htmx:afterSwap', scan);
}());
