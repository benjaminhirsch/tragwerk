/* ============================================================
   Tragwerk — shared mock data
   ============================================================ */
window.TW = (function () {
  const teams = [
    { id: "acme",      name: "Acme Engineering", plan: "Scale",      members: 14, projects: 6, color: "256" },
    { id: "northwind", name: "Northwind Labs",   plan: "Pro",        members: 5,  projects: 3, color: "295" },
    { id: "personal",  name: "Personal",         plan: "Hobby",      members: 1,  projects: 2, color: "155" },
  ];

  const stacks = {
    node:   { label: "Node.js", bg: "oklch(0.62 0.15 145)", short: "JS" },
    go:     { label: "Go",      bg: "oklch(0.62 0.12 220)", short: "Go" },
    php:    { label: "PHP",     bg: "oklch(0.5 0.13 285)",  short: "PHP" },
    python: { label: "Python",  bg: "oklch(0.62 0.13 250)", short: "Py" },
    static: { label: "Static",  bg: "oklch(0.55 0.02 270)", short: "—" },
    rust:   { label: "Rust",    bg: "oklch(0.55 0.14 45)",  short: "Rs" },
  };

  const projects = [
    { id: "storefront",  name: "storefront",        stack: "node",   region: "eu-3",  envs: 4, status: "ok",       deploy: "12m ago",  branch: "main",   members: 8 },
    { id: "api-gateway", name: "api-gateway",       stack: "go",     region: "eu-3",  envs: 3, status: "building",  deploy: "now",      branch: "main",   members: 6 },
    { id: "checkout",    name: "checkout-service",  stack: "node",   region: "us-2",  envs: 3, status: "ok",       deploy: "1h ago",   branch: "main",   members: 5 },
    { id: "marketing",   name: "marketing-site",    stack: "php",    region: "eu-1",  envs: 2, status: "ok",       deploy: "3h ago",   branch: "main",   members: 4 },
    { id: "analytics",   name: "analytics-pipeline",stack: "python", region: "us-2",  envs: 5, status: "warn",     deploy: "1d ago",   branch: "main",   members: 3 },
    { id: "docs",        name: "docs",              stack: "static", region: "eu-1",  envs: 2, status: "ok",       deploy: "2d ago",   branch: "main",   members: 2 },
  ];

  const environments = [
    { id: "production", name: "production", type: "production", status: "ok",       region: "eu-3", url: "storefront.tragwerk.app",                  commit: "a91f3c2", branch: "main",              deploy: "12m ago",  by: "CI · main" },
    { id: "staging",    name: "staging",    type: "staging",    status: "ok",       region: "eu-3", url: "staging-storefront.tragwerk.app",          commit: "7d2e9b1", branch: "develop",           deploy: "44m ago",  by: "L. Brandt" },
    { id: "checkout-v2",name: "feature/checkout-v2", type: "development", status: "building", region: "eu-3", url: "checkout-v2-storefront.tragwerk.app", commit: "c03ba7e", branch: "feature/checkout-v2", deploy: "now",   by: "M. Sayed" },
    { id: "hotfix",     name: "hotfix/cart", type: "development", status: "paused", region: "eu-3", url: "hotfix-cart-storefront.tragwerk.app",      commit: "f51d8a0", branch: "hotfix/cart",       deploy: "5h ago",   by: "J. Okoro" },
  ];

  const servers = [
    { id: "eu-app-01", name: "eu-app-01", role: "Application", region: "eu-3", type: "Standard 4× / 8 GB", status: "ok",      cpu: 38, mem: 61, ip: "51.158.12.4" },
    { id: "eu-app-02", name: "eu-app-02", role: "Application", region: "eu-3", type: "Standard 4× / 8 GB", status: "ok",      cpu: 52, mem: 70, ip: "51.158.12.5" },
    { id: "eu-db-01",  name: "eu-db-01",  role: "Database",    region: "eu-3", type: "Memory 8× / 32 GB",  status: "ok",      cpu: 24, mem: 48, ip: "51.158.20.1" },
    { id: "us-app-01", name: "us-app-01", role: "Application", region: "us-2", type: "Standard 4× / 8 GB", status: "warn",    cpu: 81, mem: 88, ip: "147.75.40.2" },
    { id: "us-db-01",  name: "us-db-01",  role: "Database",    region: "us-2", type: "Memory 8× / 32 GB",  status: "ok",      cpu: 19, mem: 41, ip: "147.75.40.9" },
    { id: "eu-cache",  name: "eu-cache-01",role: "Cache",      region: "eu-3", type: "Memory 2× / 8 GB",   status: "provisioning", cpu: 0, mem: 0, ip: "—" },
  ];

  const registries = [
    { id: "acme-prod",  name: "acme-production",  host: "registry.tragwerk.io/acme",     visibility: "private", images: 24, storage: "8.4 GB",  pulls: "12.4k", updated: "20m ago" },
    { id: "acme-base",  name: "base-images",      host: "registry.tragwerk.io/acme-base",visibility: "private", images: 9,  storage: "3.1 GB",  pulls: "48.9k", updated: "2d ago" },
    { id: "acme-ext",   name: "vendor-mirror",    host: "registry.tragwerk.io/vendor",   visibility: "public",  images: 41, storage: "22.7 GB", pulls: "—",     updated: "6h ago" },
  ];

  const members = [
    { name: "Lena Brandt",   email: "lena@acme.dev",     role: "Owner",     status: "active", initials: "LB", last: "Online" },
    { name: "Mahmoud Sayed", email: "mahmoud@acme.dev",  role: "Admin",     status: "active", initials: "MS", last: "5m ago" },
    { name: "JON Okoro",     email: "jon@acme.dev",      role: "Developer", status: "active", initials: "JO", last: "1h ago" },
    { name: "Petra Vogel",   email: "petra@acme.dev",    role: "Developer", status: "active", initials: "PV", last: "Yesterday" },
    { name: "Tomáš Novák",   email: "tomas@acme.dev",    role: "Viewer",    status: "active", initials: "TN", last: "3d ago" },
    { name: "Ada Reyes",     email: "ada@partner.io",    role: "Developer", status: "invited",initials: "AR", last: "Invite sent" },
  ];

  return { teams, stacks, projects, environments, servers, registries, members,
           currentTeam: teams[0], currentProject: projects[0] };
})();
