# Core Concepts

Tragwerk organizes everything into a small set of nouns. From the top down: a
**Team** owns **Projects**, each Project has per-branch **Environments**, and
Environments run **Applications** backed by **Services** and exposed through
**Routes** and **Domains**. **Servers** are the VPS targets you deploy to.

## Teams & Roles

A Team is the top-level owner of everything in Tragwerk — projects, servers,
and billing all belong to a team. Membership is governed by role-based access
control with three roles: **Owner**, **Admin**, and **Member**. Owners and
Admins manage members and settings; Members work within the projects they are
granted access to. See [Teams](/app/teams) and your personal
[account settings](/app/account).

## Projects

A Project maps to a single git repository and is the unit you configure and
deploy. It holds the connection to your git provider, shared settings, and the
set of environments created from your branches. See [Projects](/app/projects)
and [integrations](/app/integrations) for connecting a repository.

## Environments

An Environment is one running instance of your project, derived from a git
branch — push a branch and you get an environment. Each environment is isolated,
with its own containers, [services](/app/services), [variables](/app/variables),
and [domains](/app/domains). This makes branches usable as staging or preview
environments alongside production. See [Environments](/app/environments).

## Servers

A Server is a VPS you register with Tragwerk as a deployment target. After you
add a server, an automated setup job provisions it over SSH and turns it into a
Docker host ready to run your apps. One server can host many applications and
environments side by side. See [Servers](/self-hosting/servers) and
[server setup](/self-hosting/server-setup).

## Applications

An Application is a PHP service defined in your
[config.xml](/config/applications) — its runtime (for example `php:8.5`), its
source root, and its [web](/config/web) configuration. Applications are served by
FrankenPHP, which by default acts as a drop-in PHP-FPM replacement (classic mode)
so existing apps run unchanged; adding [`<workerMode>`](/config/applications#workermode)
opts in to worker mode for higher throughput. An environment can run one or more
applications. Long-running tasks are handled by [workers](/app/workers) and
scheduled tasks by [cronjobs](/app/cronjobs).

## Services

A Service is a backing component such as a database or cache (PostgreSQL, MySQL,
Redis, and others) declared in your config. Applications connect to services
through [relationships](/config/relationships), which inject the connection
details as environment variables at runtime. See [Services (app)](/app/services)
and [Services (config)](/config/services).

## Routes & Domains

Routes map incoming URLs to your applications and are declared in the
[routes](/config/routes) section of your config. Traefik uses them to direct
traffic and to terminate TLS. A Domain is a hostname you attach to an
environment; Tragwerk obtains a Let's Encrypt certificate for it automatically.
See [Domains](/app/domains).

## Deployments

A Deployment is one build-and-release cycle for an environment: Tragwerk clones
the repository, builds the Docker image from your generated configuration, and
starts the containers. Deployments are triggered by a git push (via webhook) or
manually as a redeploy, and you can watch their progress in a live log. See
[Deployments](/app/deployments).
