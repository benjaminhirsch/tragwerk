# Services

Services are backing data stores — databases, caches, and queues — that run
alongside your application containers in an [environment](/app/environments).
You declare them in `.tragwerk/config.xml` and Tragwerk provisions a managed
container for each one on the server.

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
polled from the running containers on the server:

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

By default a service has no published port, so you cannot point a client
straight at `your-server:5432`. Either run the client inside the container over SSH,
or declare a loopback port and forward it over SSH. Find container names with
`docker ps` on the server — the compose project name looks like `tw-<id>-<branch>`
and the service container like `tw-<id>-<branch>-db-1`.

### Quick CLI access (no tunnel)

For a one-off `psql` session, run the client inside the container itself:

```bash
ssh user@your-server
docker exec -it tw-<id>-<branch>-db-1 psql -U app app
```

### Persistent loopback port (config)

If you regularly attach a local client, declare a
[`local-port`](/config/services#service) on the service. Tragwerk then publishes
it on the server loopback for you — no per-session bridge container:

```xml
<service name="db" type="postgresql:18" local-port="55432"/>
```

On the **`main`/`master` branch** the generated Compose binds the configured
port verbatim — `127.0.0.1:55432:5432` (loopback only, never `0.0.0.0`). After
redeploying, forward it over SSH:

```bash
# On your machine: forward your local 5432 to the server loopback port
ssh -L 5432:127.0.0.1:55432 user@your-server
```

Then connect to `localhost:5432` with the default
[credentials](/config/services#default-credentials). MySQL/MariaDB use internal
port `3306`, Redis/Valkey `6379`.

::: info Feature branches get an auto-assigned port
Because `config.xml` is shared across branches, a fixed port would collide when
two branches run on the same server. So only **`main`/`master`** use the configured
`local-port`. Every other branch binds `127.0.0.1::5432` — **Docker picks a free
loopback port** for it. Find the assigned port with `docker ps` on the server (look
at the `127.0.0.1:<port>->5432/tcp` mapping for `tw-<id>-<branch>-db-1`), then
forward that port over SSH.
:::

::: warning
The published port always binds `127.0.0.1` on the server, so the service stays
reachable only through the SSH tunnel. Tragwerk never publishes a service on
`0.0.0.0` — that would expose your database to the internet, which is exactly
what it avoids by default.
:::

## Related

- [Services configuration](/config/services)
- [Relationships configuration](/config/relationships)
- [Environments](/app/environments)
- [Logs & Containers](/app/logs-containers)
