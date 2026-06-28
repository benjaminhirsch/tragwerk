# Projects

A project is a single application managed by Tragwerk. It belongs to a
[team](/app/teams), targets a [server](/self-hosting/servers) and a container
[registry](/app/registries-credentials), and points at a git repository. Each
git branch of that repository can be deployed as an isolated
[environment](/app/environments).

## Create a project

1. Open **Projects** in the active team and click **Create project**.
2. Enter a **Project name**.
3. Pick the **Server** this project deploys to.
4. Pick the **Container registry** used to store built images.
5. Click **Create project**.

::: tip Servers and registries first
The Server and Registry dropdowns only list resources that already exist in the
team. Add a [server](/self-hosting/servers) and a
[registry](/app/registries-credentials) before creating the project if the lists
are empty.
:::

## The project overview

Opening a project shows a stat strip and live tables:

- **Production URL** — the primary domain of the production (root) environment,
  if one is deployed.
- **Environments** — how many branches are deployed as environments.
- **Last commit** — the most recent commit on the production environment.
- **Last deploy** — when production was last deployed.
- **Environments table** — every branch deployed as an environment, with live
  status (updated over Mercure SSE).
- **Activity** — a feed of recent deploy jobs.
- **Configuration** — the stack (PHP 8.5), the target server and registry, and
  the **Git clone URL** Tragwerk uses to fetch your code.

Use **Open production** to visit the live site, and **Settings** to edit the
project.

## Edit a project

From **Settings** you can change the project **name**, its **server** and its
**registry**. Save your changes with **Save changes**.

## Delete a project

The Settings page has a **Danger zone**. Click **Delete project** and confirm in
the dialog.

::: warning
Deleting a project permanently removes it and all associated resources
(environments, containers and data). This cannot be undone.
:::

## Related

- [Environments](/app/environments)
- [Deployments](/app/deployments)
- [Domains & SSL](/app/domains)
- [Environment Variables](/app/variables)
- [Configuration overview](/config/overview)
