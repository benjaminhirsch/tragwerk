/* ============================================================
   Tragwerk — shared chrome (top bar, switchers, tabs, theming)
   Usage: call Tragwerk.mount({ scope, active, project, env })
   ============================================================ */
(function () {
  const T = window.TW;

  /* ---------- Theme ---------- */
  const THEME_KEY = "tw-theme";
  function getTheme() { return localStorage.getItem(THEME_KEY) || "dark"; }
  function applyTheme(t) {
    document.documentElement.setAttribute("data-bs-theme", t);
    localStorage.setItem(THEME_KEY, t);
    const ic = document.querySelector("#tw-theme-ico");
    if (ic) ic.className = t === "dark" ? "bi bi-moon-stars" : "bi bi-sun";
  }
  function toggleTheme() { applyTheme(getTheme() === "dark" ? "light" : "dark"); }
  applyTheme(getTheme()); // apply ASAP (called again after DOM ready for icon)

  /* ---------- helpers ---------- */
  const esc = (s) => String(s).replace(/[&<>"]/g, c => ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;" }[c]));
  function teamAva(team, cls = "tw-ava") {
    const init = team.name.split(" ").map(w => w[0]).slice(0,2).join("");
    return `<span class="${cls}" style="background:linear-gradient(150deg, oklch(0.6 0.18 ${team.color}), oklch(0.5 0.2 ${(+team.color+30)}))">${esc(init)}</span>`;
  }

  /* ---------- Switcher dropdowns ---------- */
  function teamSwitcher() {
    const t = T.currentTeam;
    const items = T.teams.map(tm => `
      <li><a class="dropdown-item ${tm.id===t.id?"active":""}" href="index.html">
        ${teamAva(tm)} <span class="flex-grow-1">${esc(tm.name)}</span>
        ${tm.id===t.id?'<i class="bi bi-check2"></i>':`<span class="tw-tag">${esc(tm.plan)}</span>`}
      </a></li>`).join("");
    return `
    <div class="dropdown">
      <a class="tw-switcher" data-bs-toggle="dropdown" aria-expanded="false" href="#">
        ${teamAva(t)}
        <span class="tw-switcher__name">${esc(t.name)}</span>
        <i class="bi bi-chevron-expand"></i>
      </a>
      <ul class="dropdown-menu tw-switch-menu">
        <li class="dropdown-header">Teams</li>
        ${items}
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#"><i class="bi bi-plus-lg"></i> Team erstellen</a></li>
        <li><a class="dropdown-item" href="settings.html"><i class="bi bi-gear"></i> Team-Einstellungen</a></li>
      </ul>
    </div>`;
  }

  function projectSwitcher(active) {
    const items = T.projects.map(p => `
      <li><a class="dropdown-item ${p.id===active.id?"active":""}" href="project.html">
        <span class="tw-stack__ico" style="background:${T.stacks[p.stack].bg}">${T.stacks[p.stack].short}</span>
        <span class="flex-grow-1">${esc(p.name)}</span>
        ${p.id===active.id?'<i class="bi bi-check2"></i>':""}
      </a></li>`).join("");
    return `
    <div class="dropdown">
      <a class="tw-switcher" data-bs-toggle="dropdown" href="#">
        <span class="tw-switcher__name">${esc(active.name)}</span>
        <i class="bi bi-chevron-expand"></i>
      </a>
      <ul class="dropdown-menu tw-switch-menu">
        <li class="dropdown-header">Projekte in ${esc(T.currentTeam.name)}</li>
        ${items}
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="projects.html"><i class="bi bi-grid"></i> Alle Projekte</a></li>
        <li><a class="dropdown-item" href="#"><i class="bi bi-plus-lg"></i> Neues Projekt</a></li>
      </ul>
    </div>`;
  }

  function envSwitcher(active) {
    const badge = { ok:"--ok", building:"--accent", paused:"--text-faint", warn:"--warn" };
    const items = T.environments.map(e => `
      <li><a class="dropdown-item ${e.id===active.id?"active":""}" href="environment.html">
        <span class="tw-dot" style="width:8px;height:8px;border-radius:99px;background:var(${badge[e.status]||"--text-faint"})"></span>
        <span class="flex-grow-1">${esc(e.name)}</span>
        ${e.id===active.id?'<i class="bi bi-check2"></i>':""}
      </a></li>`).join("");
    return `
    <div class="dropdown">
      <a class="tw-switcher" data-bs-toggle="dropdown" href="#">
        <span class="tw-switcher__name mono">${esc(active.name)}</span>
        <i class="bi bi-chevron-expand"></i>
      </a>
      <ul class="dropdown-menu tw-switch-menu">
        <li class="dropdown-header">Environments · ${esc(T.currentProject.name)}</li>
        ${items}
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#"><i class="bi bi-git"></i> Branch deployen</a></li>
      </ul>
    </div>`;
  }

  const sep = '<span class="tw-crumb-sep">/</span>';

  /* ---------- Tab sets ---------- */
  const TABS = {
    team: [
      { id:"overview",   label:"Übersicht",  icon:"bi-grid-1x2",   href:"index.html" },
      { id:"projects",   label:"Projekte",   icon:"bi-box-seam",   href:"projects.html",   count: T.projects.length },
      { id:"servers",    label:"Server",     icon:"bi-hdd-stack",  href:"servers.html",    count: T.servers.length },
      { id:"registries", label:"Registries", icon:"bi-archive",    href:"registries.html", count: T.registries.length },
      { id:"members",    label:"Mitglieder", icon:"bi-people",     href:"settings.html",   count: T.currentTeam.members },
      { id:"settings",   label:"Einstellungen", icon:"bi-gear",    href:"settings.html#general" },
    ],
    project: [
      { id:"overview",     label:"Übersicht",    icon:"bi-grid-1x2",     href:"project.html" },
      { id:"environments", label:"Environments", icon:"bi-diagram-3",    href:"project.html#envs", count: T.environments.length },
      { id:"domains",      label:"Domains",      icon:"bi-signpost-2",   href:"domains.html" },
      { id:"metrics",      label:"Metriken",     icon:"bi-graph-up",     href:"metrics.html" },
      { id:"activity",     label:"Aktivität",    icon:"bi-activity",     href:"project.html#activity" },
      { id:"settings",     label:"Einstellungen",icon:"bi-gear",         href:"project.html#settings" },
    ],
    environment: [
      { id:"overview",      label:"Übersicht",     icon:"bi-grid-1x2",          href:"environment.html" },
      { id:"containers",    label:"Container",     icon:"bi-boxes",             href:"containers.html" },
      { id:"deployments",   label:"Deployments",   icon:"bi-rocket-takeoff",    href:"environment.html#deployments" },
      { id:"logs",          label:"Logs",          icon:"bi-terminal",          href:"logs.html" },
      { id:"variables",     label:"Variablen",     icon:"bi-sliders",           href:"variables.html" },
      { id:"configuration", label:"Konfiguration", icon:"bi-file-earmark-code", href:"configuration.html" },
      { id:"settings",      label:"Einstellungen", icon:"bi-gear",              href:"environment.html#settings" },
    ],
    account: [
      { id:"profile",  label:"Profil",     icon:"bi-person",      href:"account.html" },
      { id:"password", label:"Passwort",   icon:"bi-key",         href:"account.html#password" },
      { id:"security", label:"Sicherheit", icon:"bi-shield-lock", href:"account.html#security" },
    ],
  };

  function renderTabs(scope, active) {
    return TABS[scope].map(t => `
      <a class="tw-tab ${t.id===active?"active":""}" href="${t.href}">
        <i class="bi ${t.icon}"></i> ${t.label}
        ${t.count!=null?`<span class="tw-tab__count">${t.count}</span>`:""}
      </a>`).join("");
  }

  /* ---------- Mount ---------- */
  function mount(cfg) {
    cfg = cfg || { scope: "team", active: "overview" };
    const project = cfg.project || T.currentProject;
    const env = cfg.env || T.environments[0];

    let crumbs;
    if (cfg.scope === "account") {
      crumbs = '<a class="tw-switcher" href="account.html"><span class="tw-ava" style="width:20px;height:20px;border-radius:6px">LB</span><span class="tw-switcher__name">Mein Konto</span></a>';
    } else {
      crumbs = teamSwitcher();
      if (cfg.scope === "project" || cfg.scope === "environment") {
        crumbs += sep + projectSwitcher(project);
      }
      if (cfg.scope === "environment") {
        crumbs += sep + envSwitcher(env);
      }
    }

    const bar = document.createElement("header");
    bar.className = "tw-topbar";
    bar.innerHTML = `
      <div class="tw-topbar__row">
        <a class="tw-logo" href="index.html">
          <span class="tw-logo__mark"><svg class="tw-logo__svg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4 20 19 4 19Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 4V19M4 19 12 12M20 19 12 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="4" r="1.5" fill="currentColor"/><circle cx="4" cy="19" r="1.5" fill="currentColor"/><circle cx="20" cy="19" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/></svg></span>
          <span class="tw-hide-sm">Tragwerk</span>
        </a>
        <span class="tw-crumb-sep tw-hide-sm" style="margin:0 .2rem">/</span>
        <div class="tw-crumbs">${crumbs}</div>
        <div class="ms-auto d-flex align-items-center gap-2">
          <label class="tw-search tw-hide-md">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Suchen…" aria-label="Suche">
            <kbd>⌘K</kbd>
          </label>
          <button class="tw-iconbtn tw-hide-md" title="Dokumentation"><i class="bi bi-life-preserver"></i></button>
          <button class="tw-iconbtn" title="Benachrichtigungen" style="position:relative">
            <i class="bi bi-bell"></i>
            <span style="position:absolute;top:7px;right:8px;width:6px;height:6px;border-radius:99px;background:var(--danger)"></span>
          </button>
          <button class="tw-iconbtn" id="tw-theme-btn" title="Theme wechseln"><i id="tw-theme-ico" class="bi bi-moon-stars"></i></button>
          <div class="dropdown">
            <a class="tw-iconbtn" data-bs-toggle="dropdown" href="#" style="width:auto;padding:0 .15rem">
              <span class="tw-ava" style="width:30px;height:30px;border-radius:8px">LB</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:230px">
              <li class="px-2 py-1">
                <div class="fw-semibold" style="font-size:.88rem">Lena Brandt</div>
                <div class="text-faint" style="font-size:.78rem">lena@acme.dev</div>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="account.html"><i class="bi bi-person"></i> Konto</a></li>
              <li><a class="dropdown-item" href="account.html#security"><i class="bi bi-shield-lock"></i> Sicherheit</a></li>
              <li><a class="dropdown-item" href="#"><i class="bi bi-credit-card"></i> Abrechnung</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="login.html"><i class="bi bi-box-arrow-right"></i> Abmelden</a></li>
            </ul>
          </div>
        </div>
      </div>
      <nav class="tw-tabs">${renderTabs(cfg.scope, cfg.active)}</nav>
    `;
    document.body.insertBefore(bar, document.body.firstChild);

    applyTheme(getTheme());
    document.getElementById("tw-theme-btn").addEventListener("click", toggleTheme);
    initSortableTables();
  }

  /* ---------- Sortable tables ---------- */
  function initSortableTables() {
    document.querySelectorAll(".tw-table thead th.sortable").forEach(th => {
      if (!th.querySelector(".bi")) th.insertAdjacentHTML("beforeend", ' <i class="bi bi-chevron-expand"></i>');
      th.addEventListener("click", () => {
        const table = th.closest("table");
        const tbody = table.querySelector("tbody");
        const idx = [...th.parentNode.children].indexOf(th);
        const asc = !th.classList.contains("sort-asc");
        table.querySelectorAll("thead th").forEach(h => { h.classList.remove("sort-asc","sort-desc"); const i=h.querySelector(".bi"); if(i) i.className="bi bi-chevron-expand"; });
        th.classList.add(asc ? "sort-asc" : "sort-desc");
        th.querySelector(".bi").className = "bi " + (asc ? "bi-chevron-up" : "bi-chevron-down");
        const rows = [...tbody.querySelectorAll("tr")];
        rows.sort((a, b) => {
          const av = cellVal(a.children[idx]), bv = cellVal(b.children[idx]);
          if (av < bv) return asc ? -1 : 1;
          if (av > bv) return asc ? 1 : -1;
          return 0;
        });
        rows.forEach(r => tbody.appendChild(r));
      });
    });
  }
  function cellVal(td) {
    if (!td) return "";
    const raw = (td.getAttribute("data-sort") ?? td.textContent).trim();
    const num = parseFloat(raw.replace(/[^0-9.\-]/g, ""));
    return (raw !== "" && !isNaN(num) && /[0-9]/.test(raw)) ? num : raw.toLowerCase();
  }

  window.Tragwerk = { mount, toggleTheme, teamAva };
})();
