# Getting Started

This guide walks through the happy path from zero to a deployed PHP application:
register a server, connect a project, add your config, point a domain at it, and
deploy.

::: tip Prerequisites
You need a running Tragwerk instance — see
[installation](/install/docker-compose) if you have not set one up yet — plus a
server with SSH access (any provider works) and a git repository containing a
PHP application. See [requirements](/server/requirements) for the supported
server baseline.
:::

## 1. Create an account and team

Sign up, then create (or join) a [Team](/app/teams). The team owns your
projects, servers, and members. If you are inviting collaborators, assign them
the Owner, Admin, or Member role as appropriate.

::: tip Secure your account
Enable [two-factor authentication](/app/two-factor) before adding servers or
production projects.
:::

## 2. Register a server and run setup

Add your server under [Servers](/server/servers) by providing its host and
SSH credentials. Tragwerk then runs an automated setup job that connects over
SSH and provisions Docker and the base stack on the machine — all from the UI.

Watch the setup progress until the server reports ready. Details on what the job
does are in [server setup](/server/server-setup) and
[architecture on the host](/server/architecture-on-host).

## 3. Create a project pointing at a git repo

Create a [Project](/app/projects) and connect it to your git repository through
an [integration](/app/integrations). The branch you push becomes an
[environment](/app/environments) automatically.

## 4. Add `.tragwerk/config.xml`

Tragwerk generates your entire Docker setup from one XML file committed at
`.tragwerk/config.xml` in your repository. Here is a minimal example that
defines a single PHP 8.5 application served from `public/` and routed on your
default domain:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<project xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://www.tragwerk.app/schema.xsd">
    <applications>
        <application name="app" type="php:8.5" root="/">
            <web>
                <location path="/" root="public" passthru="/index.php"/>
            </web>
        </application>
    </applications>
    <routes>
        <route pattern="https://{default}" upstream="app:http"/>
    </routes>
</project>
```

The `{default}` placeholder in the route resolves to the environment's domain.
For the full set of options — [applications](/config/applications),
[web](/config/web), [workers](/config/workers), [crons](/config/crons),
[hooks](/config/hooks), [mounts](/config/mounts),
[relationships](/config/relationships), [services](/config/services), and
[routes](/config/routes) — see the [configuration overview](/config/overview)
and [examples](/config/examples).

::: info Add a backing service
To attach a database, declare it under `<services>` and bind it with a
`<relationship>`. Connection details are injected as environment variables at
runtime. See [Services](/config/services) and
[relationships](/config/relationships).
:::

## 5. Add a domain

Attach a hostname to your environment under [Domains](/app/domains) and point
that hostname's DNS at your server. Traefik will request a Let's Encrypt
certificate for it automatically, so the `https://` route just works.

## 6. Push to deploy

Push your branch. The git webhook triggers a [deployment](/app/deployments):
Tragwerk clones the repo, builds the Docker image from your generated config,
and starts the containers. You can also trigger a **redeploy** from the
environment page at any time.

```bash
git add .tragwerk/config.xml
git commit -m "Add Tragwerk config"
git push origin main
```

## 7. Watch the live deployment log

Open the environment's [deployment](/app/deployments) view to follow the build
and release in real time. Once it finishes, visit your domain to see the app
running.

## Next steps

- Manage runtime config with [environment variables](/app/variables).
- Add background [workers](/app/workers) and [cronjobs](/app/cronjobs).
- Inspect [container logs](/app/logs-containers) and [metrics](/app/metrics).
- Pull private images using [registry credentials](/app/registries-credentials).
