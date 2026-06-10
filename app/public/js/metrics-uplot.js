/* ============================================================
   Tragwerk — Metriken (uPlot) · FrankenPHP-Runtime
   ============================================================ */
Tragwerk.mount({ scope: "project", active: "metrics" });

(function () {
  const root = document.documentElement;

  /* ---- color resolution (any CSS color -> rgb, via painted pixel) ---- */
  const pc = document.createElement("canvas").getContext("2d", { willReadFrequently: true });
  function toRGB(css) {
    pc.fillStyle = "#000"; pc.fillStyle = css; pc.fillRect(0, 0, 1, 1);
    const d = pc.getImageData(0, 0, 1, 1).data;
    return d[0] + ", " + d[1] + ", " + d[2];
  }
  function resolve(varName) {
    return "rgb(" + toRGB(getComputedStyle(root).getPropertyValue(varName).trim()) + ")";
  }
  function rgba(rgb, a) { return rgb.replace("rgb(", "rgba(").replace(")", ", " + a + ")"); }

  function palette() {
    return {
      accent: resolve("--accent"),
      purple: resolve("--purple"),
      ok:     resolve("--ok"),
      warn:   resolve("--warn"),
      danger: resolve("--danger"),
      text:   resolve("--text-muted"),
      faint:  resolve("--text-faint"),
      grid:   rgba(resolve("--border"), 0.7),
      surface:resolve("--surface"),
    };
  }

  /* ---- seeded RNG for stable data ---- */
  function rng(seed) { let s = seed; return () => (s = (s * 1103515245 + 12345) & 0x7fffffff) / 0x7fffffff; }

  function timeAxis(n, stepSec) {
    const now = Math.floor(Date.now() / 1000);
    const t = [];
    for (let i = n - 1; i >= 0; i--) t.push(now - i * stepSec);
    return t;
  }
  function wave(n, base, amp, noise, seed, trend = 0) {
    const r = rng(seed), out = [];
    for (let i = 0; i < n; i++) {
      const day = Math.sin((i / n) * Math.PI * 2 - Math.PI / 2);
      const v = base + day * amp + (r() - 0.5) * noise + trend * i;
      out.push(Math.max(0, +v.toFixed(1)));
    }
    return out;
  }

  const RANGES = {
    "1h":  { n: 60, step: 60 },
    "6h":  { n: 72, step: 300 },
    "24h": { n: 96, step: 900 },
    "7d":  { n: 84, step: 7200 },
  };

  const charts = {};
  let cur = { env: "production", range: "24h" };

  function dataFor(range, env) {
    const { n, step } = RANGES[range];
    const t = timeAxis(n, step);
    const mul = env === "staging" ? 0.28 : 1;        // staging has less traffic
    const seed = env === "staging" ? 7 : 3;
    return {
      x: t,
      rps:   wave(n, 200 * mul, 70 * mul, 24 * mul, seed + 1),
      p50:   wave(n, 36, 10, 7, seed + 2),
      p95:   wave(n, 132, 38, 26, seed + 3),
      p99:   wave(n, 300, 70, 60, seed + 4),
      work:  wave(n, env === "staging" ? 9 : 22, 6, 3, seed + 5).map(v => Math.min(32, Math.round(v))),
      mem:   wave(n, 380 * (env === "staging" ? 0.7 : 1), 60, 18, seed + 6, 0.25),
    };
  }

  /* ---- tooltip plugin ---- */
  function tooltipPlugin(fmt) {
    let tip;
    return {
      hooks: {
        init: u => { tip = document.createElement("div"); tip.className = "u-tooltip"; u.over.appendChild(tip); },
        setCursor: u => {
          const { idx, left } = u.cursor;
          if (idx == null || left < 0) { tip.style.display = "none"; return; }
          const d = new Date(u.data[0][idx] * 1000);
          const ts = d.toLocaleTimeString("de-DE", { hour: "2-digit", minute: "2-digit" });
          let rows = "";
          for (let s = 1; s < u.series.length; s++) {
            const ser = u.series[s];
            if (ser.show === false) continue;
            rows += `<div class="tt-row"><i style="background:${ser._color}"></i>${ser.label}: ${fmt(u.data[s][idx], s)}</div>`;
          }
          tip.innerHTML = `<div class="tt-t">${ts}</div>${rows}`;
          tip.style.display = "block";
          tip.style.left = u.valToPos(u.data[0][idx], "x") + "px";
          const topY = Math.min(...u.series.slice(1).map((_, i) => {
            const v = u.data[i + 1][idx]; return v == null ? Infinity : u.valToPos(v, u.series[i + 1].scale || "y");
          }).filter(Number.isFinite));
          tip.style.top = (Number.isFinite(topY) ? topY : 20) + "px";
        },
      },
    };
  }

  function baseAxes(p, opts) {
    return [
      {
        stroke: p.faint, grid: { stroke: p.grid, width: 1 }, ticks: { stroke: p.grid, width: 1, size: 4 },
        font: "11px Manrope", space: 70, size: 30,
        values: (u, splits) => splits.map(v => {
          const d = new Date(v * 1000);
          return cur.range === "7d"
            ? d.toLocaleDateString("de-DE", { weekday: "short" })
            : d.toLocaleTimeString("de-DE", { hour: "2-digit", minute: "2-digit" });
        }),
      },
      Object.assign({
        stroke: p.faint, grid: { stroke: p.grid, width: 1 }, ticks: { show: false },
        font: "11px Manrope", size: 42, side: 3,
      }, opts || {}),
    ];
  }

  function area(p, rgb) {
    return (u) => {
      const ctx = u.ctx, { top, height } = u.bbox;
      const g = ctx.createLinearGradient(0, top, 0, top + height);
      g.addColorStop(0, rgba(rgb, 0.28));
      g.addColorStop(1, rgba(rgb, 0.01));
      return g;
    };
  }

  function makeChart(elId, seriesDefs, data, p, axOpts, fmt) {
    const el = document.getElementById(elId);
    el.innerHTML = "";
    const series = [{}];
    seriesDefs.forEach(s => {
      series.push({
        label: s.label, stroke: s.color, width: 2,
        fill: s.fill ? area(p, s.color) : undefined,
        points: { show: false }, _color: s.color, scale: "y",
      });
    });
    const u = new uPlot({
      width: el.clientWidth || 600,
      height: el.clientHeight || 200,
      padding: [12, 8, 0, 4],
      cursor: { points: { size: 7, width: 2, stroke: p.surface }, y: false },
      legend: { show: false },
      scales: { x: { time: true }, y: { range: axOpts && axOpts.range } },
      axes: baseAxes(p, axOpts),
      series,
      plugins: [tooltipPlugin(fmt || (v => v))],
    }, [data.x, ...seriesDefs.map(s => data[s.key])], el);
    return u;
  }

  /* ---- status codes stacked bar ---- */
  function renderStatus(env) {
    const data = env === "staging"
      ? [["2xx", 94.8, "--ok"], ["3xx", 2.4, "--accent"], ["4xx", 2.2, "--warn"], ["5xx", 0.6, "--danger"]]
      : [["2xx", 96.2, "--ok"], ["3xx", 1.8, "--accent"], ["4xx", 1.6, "--warn"], ["5xx", 0.4, "--danger"]];
    document.getElementById("status-bar").innerHTML = data.map(([, pct, c]) =>
      `<span style="width:${pct}%;background:var(${c})" title="${pct}%"></span>`).join("");
    const labels = { "2xx": "Erfolg", "3xx": "Redirect", "4xx": "Client-Fehler", "5xx": "Server-Fehler" };
    document.getElementById("status-legend").innerHTML = data.map(([code, pct, c]) => `
      <div class="d-flex align-items-center justify-content-between" style="font-size:.82rem">
        <span class="d-flex align-items-center gap-2">
          <i style="width:9px;height:9px;border-radius:3px;background:var(${c});display:inline-block"></i>
          <span class="mono fw-semibold">${code}</span>
          <span class="text-faint">${labels[code]}</span>
        </span>
        <span class="fw-semibold tw-spark">${pct.toFixed(1).replace(".", ",")} %</span>
      </div>`).join("");
  }

  /* ---- build / rebuild all ---- */
  function buildAll() {
    Object.values(charts).forEach(c => c && c.destroy());
    const p = palette();
    const d = dataFor(cur.range, cur.env);

    charts.rps = makeChart("c-rps",
      [{ key: "rps", label: "req/s", color: p.accent, fill: true }],
      d, p, { range: (u, min, max) => [0, max * 1.15] }, v => Math.round(v) + " req/s");

    charts.latency = makeChart("c-latency",
      [{ key: "p50", label: "p50", color: p.accent },
       { key: "p95", label: "p95", color: p.warn },
       { key: "p99", label: "p99", color: p.danger }],
      d, p, { range: (u, min, max) => [0, max * 1.1] }, v => Math.round(v) + " ms");

    charts.workers = makeChart("c-workers",
      [{ key: "work", label: "belegt", color: p.purple, fill: true }],
      d, p, { range: [0, 32] }, v => v + " / 32");

    charts.mem = makeChart("c-mem",
      [{ key: "mem", label: "RSS", color: p.ok, fill: true }],
      d, p, { range: (u, min, max) => [Math.min(...d.mem) * 0.8, max * 1.1] }, v => Math.round(v) + " MB");

    renderStatus(cur.env);
  }

  function resizeAll() {
    Object.entries(charts).forEach(([id, c]) => {
      if (!c) return;
      const el = c.root.parentElement;
      c.setSize({ width: el.clientWidth, height: el.clientHeight });
    });
  }

  /* ---- controls ---- */
  document.querySelectorAll("#env-seg button").forEach(b => b.addEventListener("click", () => {
    document.querySelectorAll("#env-seg button").forEach(x => x.classList.remove("active"));
    b.classList.add("active"); cur.env = b.dataset.env; buildAll();
  }));
  document.querySelectorAll("#range-seg button").forEach(b => b.addEventListener("click", () => {
    document.querySelectorAll("#range-seg button").forEach(x => x.classList.remove("active"));
    b.classList.add("active"); cur.range = b.dataset.range; buildAll();
  }));

  /* ---- theme + resize reactivity ---- */
  new MutationObserver(() => buildAll())
    .observe(root, { attributes: true, attributeFilter: ["data-bs-theme"] });
  let rt; window.addEventListener("resize", () => { clearTimeout(rt); rt = setTimeout(resizeAll, 120); });

  if (document.fonts && document.fonts.ready) document.fonts.ready.then(buildAll);
  buildAll();
})();
