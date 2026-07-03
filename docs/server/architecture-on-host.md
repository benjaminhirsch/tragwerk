# Architecture on the Host

Everything Tragwerk runs on your server is **generated from your
`.tragwerk/config.xml`** and executed with Docker Compose. This page describes
what that generated stack looks like on the host.

## From XML to running containers

On each deploy, Tragwerk reads your [configuration](/config/overview) and
generates two artifacts on the server:

- A **`Dockerfile.{appSlug}`** per application.
- A **`docker-compose.yml`** describing every container, network, and volume.

It then builds the image(s) and brings up the stack — all automatically as part
of a deployment.

```text
.tragwerk/config.xml
        │  (generation)
        ▼
Dockerfile.{appSlug}  +  docker-compose.yml
        │  (docker compose build / up)
        ▼
running containers on the server
```

## Application containers

Each application runs as a **FrankenPHP** container with a security-hardened,
ephemeral filesystem:

- **`read_only`** root filesystem.
- **tmpfs** mounts for `/tmp`, `/data`, and `/config` (writable, in-memory).
- A **healthcheck** so Compose and Traefik only route to healthy containers.
- Joined to networks **`default`** and **`tragwerk-net`**.

Persistent storage is provided explicitly through [mounts](/config/mounts),
which become Docker volumes — the read-only root keeps everything else stateless.

::: info Two FrankenPHP modes
By default the generated Caddyfile runs FrankenPHP in **classic mode** — a
drop-in PHP-FPM replacement that serves each request in the traditional
per-request lifecycle, so existing apps run unchanged. If the application
declares [`<workerMode>`](/config/applications#workermode), the Caddyfile instead
emits a `frankenphp { worker … }` block that boots the front controller once and
keeps it warm between requests for higher throughput.
:::

## Traefik routing

Traefik runs at the host level (provisioned during
[server setup](/server/server-setup)) and is the single public entrypoint.
The generated Compose file attaches **Traefik labels** to each application
container to declare its hostname-based [route](/config/routes), and the
container joins the shared external network **`tragwerk-net`** so Traefik can
reach it.

Because routing is by host/subdomain, **multiple applications coexist on one
server** — each answers on its own hostname while sharing the same Traefik instance
and Let's Encrypt setup.

### Why Traefik and not FrankenPHP's built-in Caddy

FrankenPHP ships with Caddy and could serve a single app directly, but Tragwerk
needs to host **many independent applications on the same server**, each on its own
hostname. A dedicated host-level reverse proxy in front of all app containers is
the natural fit for that, so Tragwerk uses **Traefik** as the single public
entrypoint rather than exposing each app's built-in Caddy.

Traefik also pairs well with the rest of the stack:

- **Docker-native** — it discovers containers and their routes automatically
  from the **Traefik labels** on each service, with no separate routing config
  to maintain.
- **Blue/green-friendly** — because it watches Docker, Traefik picks up the new
  container and shifts traffic to it as soon as it is healthy, then drops the old
  one, which is exactly what the [zero-downtime](#zero-downtime-deployments) swap
  relies on.

```text
        Internet
           │
        Traefik  (host reverse proxy, TLS / Let's Encrypt)
           │   routes by hostname over tragwerk-net
   ┌───────┼───────────────┐
   ▼       ▼               ▼
 app A   app B   ...   (FrankenPHP containers, read-only + tmpfs)
```

## Zero-downtime deployments

Deployments aim for **zero downtime**: a new release should not interrupt the
version that is currently serving traffic. Tragwerk uses a **blue/green**
strategy to make this the normal case — but it cannot be *guaranteed*, since the
outcome also depends on your application, your migrations, and the health of the
new release (see the pitfalls below).

### How blue/green works

Each application runs in one of two slots, **a** or **b**. On every deploy
Tragwerk targets the slot that is *not* currently live:

1. **Start the new container** — the freshly built image is started in the idle
   slot (e.g. `…-app-b`), attached to the same network as the live container
   (`…-app-a`). Both versions now run side by side against the same database and
   mounts.
2. **Run deploy hooks** — the new container's entrypoint runs your
   [`deploy` hooks](/config/hooks) (for example database migrations) *before* it
   reports healthy.
3. **Wait for health** — Tragwerk polls the new container's healthcheck. If it
   never becomes healthy the deploy **fails**, the new container is discarded,
   and the old one keeps serving — so a broken release cannot take the site down.
4. **Switch traffic** — once healthy, Traefik routes incoming requests to the new
   container.
5. **Remove the old container** — the previous slot is stopped and removed, and
   the active slot is recorded for next time.

Workers and the cron sidecar are **not** part of the swap — they are restarted
separately. Pausing an environment stops the active container.

### Database migrations & other pitfalls

Because both versions run against the **same database** during the overlap in
steps 1–4, the old code keeps serving while your migrations have already run.
Plan migrations to be **backward compatible** with the still-running release —
the expand/contract pattern:

- **Expand first.** Add new columns/tables as **nullable** or with defaults;
  never rename or drop in the same deploy that introduces the new code. A
  dropped or renamed column breaks the old container the moment the migration
  runs.
- **Contract later.** Remove old columns only in a *subsequent* deploy, once no
  running version references them anymore.
- **Avoid long-locking migrations.** A migration that locks a large table blocks
  the old version's queries too; prefer online/concurrent schema changes.
- **Migrations must be idempotent and safe to run while serving** — they execute
  from the new container before it takes traffic, not in a maintenance window.
- **Shared mounts** are likewise written by both versions during the overlap;
  don't change on-disk formats incompatibly within a single deploy.

::: warning
Tragwerk runs `deploy` hooks once per deploy, from the new container, with no
maintenance pause. If a migration is destructive or backward-incompatible the
old version can error during the few seconds of overlap. Split such changes
across two deploys (expand → switch → contract).
:::

::: tip Heavy or risky migrations
For data backfills or rebuilds that should not block the cutover, run them from a
[`post_deploy` hook](/config/hooks) or a one-off [worker](/config/workers)
instead of `deploy`, so the switch isn't held up waiting on them.
:::

## Sidecars and extra containers

The generated Compose file can include additional containers around the app:

- **Services** — backing services such as databases or caches run as their own
  sidecar containers and join `tragwerk-net`. See [Services](/config/services).
- **Workers** — long-running [worker](/config/workers) processes run as
  separate containers that reuse the application image (read-only, healthcheck
  disabled).
- **Cron** — a **supercronic** sidecar (`{app}-cron`) runs the application's
  scheduled [crons](/config/crons) from a generated crontab.

[Mounts](/config/mounts) declared in the configuration are emitted as Docker
volumes shared by the relevant containers.

## Related

- [Configuration Overview](/config/overview)
- [Routes](/config/routes)
- [Services](/config/services)
- [Server Setup](/server/server-setup)
