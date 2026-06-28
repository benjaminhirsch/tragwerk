# Logs & Containers

These views give you visibility into a running [environment](/app/environments):
which containers are up, what state they are in, and what the build and deploy
processes are doing — in real time and from history.

## Containers

The **Containers** view lists the containers that make up the active
environment — the application container(s), any [service](/app/services)
containers, [worker](/app/workers) containers, and the
[cron](/app/cronjobs) sidecar. Each row shows the container's live status
(running, starting, restarting, stopped, paused).

Status is polled from the VPS over SSH and refreshes automatically, so the page
reflects the current state of the environment without a manual reload.

## Build & deploy logs

The **Logs** view streams the output of the build and deploy pipeline:

- **Live tail** — output appended in real time as a build or deployment runs.
- **History** — past log lines retained per project and branch, so you can
  review what happened on an earlier deployment.

Log entries are stored with their branch and a type (for example build vs.
deploy output), letting the view separate and replay the right stream for the
selected environment.

::: tip
Use the Logs view together with [Deployments](/app/deployments) to debug a
failed build — the deployment record tells you *that* it failed; the logs tell
you *why*.
:::

## Configuration view

The **Configuration** view shows the live, fully resolved configuration that
Tragwerk derived from `.tragwerk/config.xml` for the current environment. It
brings together, in one place:

- Runtime and **PHP version**
- **Web server** settings
- [Workers](/app/workers)
- **Mounts** and their current on-disk **sizes**
- [Services](/app/services) with live per-service status
- **Hooks** (build / deploy / post-deploy)
- **Routes**

Mount sizes are computed on demand from the VPS, so you can see how much disk
each persistent mount is consuming.

## Example workflow

1. Trigger a [deployment](/app/deployments) (push, or redeploy from the UI).
2. Open **Logs** and watch the live tail until the build finishes.
3. Switch to **Containers** to confirm every container reports **Running**.
4. Open **Configuration** to verify the resolved PHP version, mounts, and routes
   match your config.

## Related

- [Environments](/app/environments)
- [Deployments](/app/deployments)
- [Services](/app/services)
- [Workers](/app/workers)
- [Cron Jobs](/app/cronjobs)
- [Configuration overview](/config/overview)
