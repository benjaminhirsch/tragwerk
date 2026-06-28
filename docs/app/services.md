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
    <relationship name="database" service="maindb" />
    <relationship name="redis" service="cache" />
</relationships>
```

The relationship name becomes the handle your application uses to read the
connection details (host, port, credentials) injected into the environment.

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
    <relationship name="database" service="db" />
    <relationship name="cache" service="queue" />
</relationships>
```

After the next [deployment](/app/deployments), the Configuration view lists
`db` and `queue` with their live status badges.

## Related

- [Services configuration](/config/services)
- [Relationships configuration](/config/relationships)
- [Environments](/app/environments)
- [Logs & Containers](/app/logs-containers)
