# How Tragwerk Compares

The closest thing to Tragwerk in the self-hosted world is
[Coolify](https://coolify.io/), and it is worth being direct about the
relationship: Coolify is the more capable platform for most people. It deploys
anything, it is mature, and it has a large community.

Tragwerk is not trying to be a better Coolify. It is a **specialised
alternative** — it does one language, and it makes opinionated choices that a
general-purpose platform cannot make. If those choices match how you work, the
result is less configuration and less to think about. If they do not, Coolify is
the better tool and you should use it.

Both are self-hosted and open source, both run Docker behind Traefik with
automatic Let's Encrypt certificates on servers you own, and — amusingly — both
are written in PHP. The difference is **breadth versus depth**.

::: info About the Coolify facts on this page
Checked against Coolify's public documentation in July 2026. Coolify moves fast,
so treat anything here as a pointer, not a promise, and verify against
[coolify.io/docs](https://coolify.io/docs) before making a decision.
:::

## What Tragwerk optimises for

### PHP, and nothing else

Coolify deploys any stack — Nixpacks, Railpack, a Dockerfile, a Compose file,
static sites. Tragwerk deploys **PHP 8.2 to 8.5 and nothing else**.

That restriction is what buys the depth. Because Tragwerk knows the runtime is
PHP, it can resolve your extensions itself — sorting them into native
`docker-php-ext-install` versus PECL builds, and pulling in the apt build
dependencies each one needs — bake your `php.ini` settings into the image, run
[FrankenPHP in worker mode](/config/workers) when you ask for it, and wire up
cron sidecars. None of that is configuration you write.

The price is absolute: if a service in your stack is not PHP, Tragwerk cannot
deploy it.

### Configuration lives in your repository, not in a database

Coolify is UI-first: you click a service together, and the resulting
configuration lives in Coolify's own database.

In Tragwerk, [`.tragwerk/config.xml`](/config/overview) in your repository is the
only source of truth. It is validated against a schema that the app serves at
`/schema.xsd`, so your editor autocompletes it — and an invalid config aborts the
deploy *before* anything is built, rather than failing halfway through.

The price is that there is no click-it-together path. You write the file.

### You do not own the Dockerfile

Tragwerk generates the Dockerfile, the Compose file, the Caddyfile, the `php.ini`
and the crontab from your XML, every single time. A `Dockerfile` sitting in your
repository is not read. The FrankenPHP base image cannot be swapped out.

This is a deliberate trade of control for automation, and it is the choice most
likely to be a dealbreaker. It is not total, though: [build
hooks](/config/hooks) let you run arbitrary commands during the image build, so
you are not locked out of your own build — you just do not own the file that
describes it.

Coolify hands you the Dockerfile and, with it, the responsibility.

### The target server never sees your source code

Tragwerk builds the image on the Tragwerk instance, pushes it to a container
registry, and the target server only ever runs `docker pull` and swaps the
container. A registry is **mandatory**; there is no build-on-target path to fall
back to. Your servers hold finished images, never source code or build tooling.

Coolify can do this too — it has an opt-in build server — but it is a setup step,
and a machine acting as a build server cannot also be a deploy target. In
Tragwerk it is simply how deploys work.

### Deploy mechanics you rarely get at this size

Three things fall out of Tragwerk being narrow enough to automate aggressively:

- **Blue/green swaps.** The new container starts alongside the old one and has to
  pass its health check — with its startup logs scanned for errors — before the
  old one is removed. If it fails, the old container stays up.
- **Automatic database major upgrades.** Bump PostgreSQL 16 to 17 in your config
  and Tragwerk runs `pg_upgrade` for you on the next deploy. MariaDB gets
  `mariadb-upgrade`. Elsewhere this is a manual maintenance window.
- **Preview environments with real data.** The first deploy of a branch clones the
  parent branch's data volumes — via reflink copy-on-write where the filesystem
  supports it, otherwise rsync. Your preview comes up with data in it.

## When Tragwerk is the wrong choice

Reach for Coolify — or something else — if any of these apply:

- **You deploy more than PHP.** One Node service in the stack and Tragwerk is out.
  This is the big one.
- **You need more than one host per project.** Tragwerk deploys a project to
  exactly one server; there is no clustering. Coolify spans multiple servers.
- **You want managed backups of your application's data.** Tragwerk does not back
  up the databases of the apps it deploys — that remains your job. Coolify has
  scheduled backups to S3-compatible storage with one-click restore. (Backing up
  *Tragwerk's own* state is a different question, and is
  [documented](/install/backup).)
- **You want a large service catalog.** Coolify offers 280+ one-click services.
  Tragwerk offers five families — PostgreSQL, MySQL, MariaDB, Redis, Valkey — as
  a closed list of pinned versions. Anything else, you do without.
- **You need something production-ready today.** Tragwerk is in beta. Coolify is
  mature and has been for a while.

## Side by side

| | Tragwerk | Coolify |
|---|---|---|
| **Deploys** | PHP 8.2–8.5 only | Any stack (Nixpacks, Dockerfile, Compose, static) |
| **License** | AGPL-3.0 | Apache-2.0 |
| **Configuration** | `.tragwerk/config.xml` in your repo, schema-validated | UI-first, stored in Coolify's database |
| **Build artifacts** | Generated; a repo Dockerfile is ignored | Yours to write |
| **Where builds run** | Always on the Tragwerk instance; registry required | On the target by default; separate build server opt-in |
| **Container swap** | Blue/green with health check and rollback | Zero-downtime option |
| **DB major upgrades** | Automatic (`pg_upgrade`, `mariadb-upgrade`) | Manual |
| **Preview environments** | Branch = environment, parent's data cloned in | PR previews, without a data clone |
| **Multiple servers** | One server per project | Multiple servers; Swarm (being retired in v5) |
| **App database backups** | None | Scheduled, to S3, one-click restore |
| **Backing services** | 5 families, pinned versions | 280+ one-click services |
| **Maturity** | Beta | Mature |

## Related

- [Introduction](/guide/introduction)
- [XML Configuration](/config/overview)
- [Self-Hosting Tragwerk](/install/requirements)
