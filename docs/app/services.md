# Services

Services are backing data stores — databases, caches, and queues — that run
alongside your application containers in an [environment](/app/environments).
You declare them in `.tragwerk/config.xml` and Tragwerk provisions a managed
container for each one on the VPS.

## Declaring services

Services live in the XML `<services>` block. Each service has a name and a
runtime (image + version). See the configuration reference for the full schema:
[Services configuration](/config/services).

```xml
<services>
    <service name="maindb" type="postgresql:17" />
    <service name="cache" type="redis:7" />
</services>
```

To make a service reachable from an application, connect it through a
relationship — see [Relationships](/config/relationships):

```xml
<relationships>
    <relationship name="database" target="maindb" />
    <relationship name="redis" target="cache" />
</relationships>
```

The relationship name becomes the handle your application uses to read the
connection details (host, port, credentials) injected into the environment.

::: info Services stay inside Docker
Service ports are **not exposed** to the host or the internet. A service is
reachable only on the internal Docker network, by the applications in the same
environment that declare a relationship to it. There is no public port — only
your app containers are reachable from outside (through Traefik). To connect a
local client to a database, tunnel in over SSH.
:::

## Supported runtimes

| Category       | Runtime      | Versions               |
| -------------- | ------------ | ---------------------- |
| Database       | MariaDB      | 10.6, 10.11, 11.4, 11.8 |
| Database       | MySQL        | 8                       |
| Database       | PostgreSQL   | 14, 15, 16, 17, 18      |
| Cache / Queue  | Redis        | 6, 7, 8                 |
| Cache / Queue  | Valkey       | 8, 9                    |

The `type` attribute uses the `image:version` form, for example `mariadb:11.8`,
`mysql:8`, `postgresql:18`, `redis:8`, or `valkey:9`.

## Live per-service status

The Configuration view shows a live status badge for each declared service,
polled from the running containers on the VPS:

| Badge       | Meaning                                            |
| ----------- | -------------------------------------------------- |
| Running     | Container is up and healthy.                       |
| Starting    | Container is running but still in its health check. |
| Unhealthy   | Container is running but failing its health check.  |
| Restarting  | Container is restarting.                            |
| Stopped     | Container exited or died.                           |
| Paused      | The environment is paused.                          |
| Not deployed| No container exists yet for this service.           |

::: info
Status is read over SSH and cached briefly (about 15 seconds), so several
viewers polling at once share a single connection rather than each opening their
own.
:::

## Example

A PHP app backed by PostgreSQL and Redis:

```xml
<services>
    <service name="db" type="postgresql:17" />
    <service name="queue" type="valkey:9" />
</services>

<relationships>
    <relationship name="database" target="db" />
    <relationship name="cache" target="queue" />
</relationships>
```

After the next [deployment](/app/deployments), the Configuration view lists
`db` and `queue` with their live status badges.

## Connecting to a database from your machine

Because a service has no published port, you cannot point a client straight at
`your-vps:5432`. You first reach the VPS over SSH, then bridge to the container
**inside** the Docker network. Find the container and network names with
`docker ps` / `docker network ls` on the VPS — the compose project name looks
like `tw-<id>-<branch>`, the service container like `tw-<id>-<branch>-db-1`, and
the network like `tw-<id>-<branch>_default`.

### Quick CLI access (no tunnel)

For a one-off `psql` session, run the client inside the container itself:

```bash
ssh user@your-vps
docker exec -it tw-<id>-<branch>-db-1 psql -U app app
```

### TCP tunnel for a local client

To use a GUI client or local tooling, bridge the service to the VPS loopback,
then forward that port over SSH. Using the **service name** (`db`) rather than a
container IP means the bridge keeps working across redeploys:

```bash
# 1. On the VPS: expose the service on 127.0.0.1 only, via a throwaway socat container
docker run --rm -p 127.0.0.1:55432:5432 \
  --network tw-<id>-<branch>_default \
  alpine/socat tcp-listen:5432,fork,reuseaddr tcp-connect:db:5432

# 2. On your machine: forward your local 5432 to that loopback port
ssh -L 5432:127.0.0.1:55432 user@your-vps
```

Now connect your client to `localhost:5432` with the default
[credentials](/config/services#default-credentials) — database `app`, user
`app`, password `secret`. Redis/Valkey work the same way; just use port `6379`.

::: warning
Binding socat to `127.0.0.1` keeps the service private to the SSH session. Never
publish the service on `0.0.0.0` — that would expose your database to the
internet, which is exactly what Tragwerk avoids by default.
:::

## Related

- [Services configuration](/config/services)
- [Relationships configuration](/config/relationships)
- [Environments](/app/environments)
- [Logs & Containers](/app/logs-containers)
