/* ============================================================
   Tragwerk — companion for the NO-JS header
   ------------------------------------------------------------
   The header itself (top bar, switcher dropdowns, tabs, user menu)
   needs ZERO JavaScript — it is static HTML + chrome-nojs.css
   (:focus-within). This tiny file only covers the two things that
   genuinely require scripting and are NOT layout:
     1) Remembering the chosen theme across page loads.
     2) Click-to-sort tables (a body feature, not the header).
   Remove this file and the header still renders and the dropdowns
   still open — you'd only lose theme persistence and table sorting.
   ============================================================ */
(function () {
  var KEY = "tw-theme";
  var root = document.documentElement;

  /* ---- 1) Theme: apply saved choice + wire the toggle button ---- */
  function applyTheme(t) {
    root.setAttribute("data-bs-theme", t);
    try { localStorage.setItem(KEY, t); } catch (e) {}
    var ic = document.getElementById("tw-theme-ico");
    if (ic) ic.className = "bi " + (t === "dark" ? "bi-moon-stars" : "bi-sun");
  }
  // apply immediately (the <head> inline snippet already set the attribute to
  // avoid a flash; this syncs the icon and is the single source of truth)
  applyTheme(localStorage.getItem(KEY) || root.getAttribute("data-bs-theme") || "dark");

  document.addEventListener("click", function (e) {
    if (!e.target.closest("#tw-theme-btn")) return;
    applyTheme(root.getAttribute("data-bs-theme") === "dark" ? "light" : "dark");
  });

  /* ---- 2) Sortable tables (optional body feature) ---- */
  function cellVal(td) {
    if (!td) return "";
    var raw = (td.getAttribute("data-sort") != null ? td.getAttribute("data-sort") : td.textContent).trim();
    var num = parseFloat(raw.replace(/[^0-9.\-]/g, ""));
    return (raw !== "" && !isNaN(num) && /[0-9]/.test(raw)) ? num : raw.toLowerCase();
  }
  function initSort() {
    document.querySelectorAll(".tw-table thead th.sortable").forEach(function (th) {
      if (!th.querySelector(".bi")) th.insertAdjacentHTML("beforeend", ' <i class="bi bi-chevron-expand"></i>');
      th.addEventListener("click", function () {
        var table = th.closest("table");
        var tbody = table.querySelector("tbody");
        var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
        var asc = !th.classList.contains("sort-asc");
        table.querySelectorAll("thead th").forEach(function (h) {
          h.classList.remove("sort-asc", "sort-desc");
          var i = h.querySelector(".bi"); if (i) i.className = "bi bi-chevron-expand";
        });
        th.classList.add(asc ? "sort-asc" : "sort-desc");
        th.querySelector(".bi").className = "bi " + (asc ? "bi-chevron-up" : "bi-chevron-down");
        var rows = Array.prototype.slice.call(tbody.querySelectorAll("tr"));
        rows.sort(function (a, b) {
          var av = cellVal(a.children[idx]), bv = cellVal(b.children[idx]);
          return av < bv ? (asc ? -1 : 1) : av > bv ? (asc ? 1 : -1) : 0;
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
      });
    });
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initSort);
  else initSort();
})();
