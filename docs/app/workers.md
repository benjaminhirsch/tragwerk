# Workers

Workers are long-lived background processes that run continuously alongside your
application — typically queue consumers, message handlers, or other daemons. You
define them in `.tragwerk/config.xml` and Tragwerk runs each one as its own
container on the server.

## Defining workers

Workers live in the XML `<workers>` block. Each worker has a name and a command
to run. See the configuration reference: [Workers configuration](/config/workers).

```xml
<workers>
    <worker name="queue" command="bin/console messenger:consume async" />
    <worker name="emails" command="bin/console app:mail-worker" />
</workers>
```

## How it runs on the server

For each declared worker Tragwerk generates a container named
`{app}-worker-{name}` that runs your command in the application's runtime. The
container is kept alive and restarted if the process exits, so a `queue` worker
becomes the container `{app}-worker-queue`.

Workers share the same image, environment variables, mounts, and service
[relationships](/config/relationships) as your application containers, so they
can reach the same databases and caches.

## Example: a queue consumer

```xml
<workers>
    <worker name="async" command="bin/console messenger:consume async --time-limit=3600" />
</workers>
```

On the next [deployment](/app/deployments) Tragwerk starts the container
`{app}-worker-async` running that command. You can watch it on the
[Logs & Containers](/app/logs-containers) page.

## Related

- [Workers configuration](/config/workers)
- [Cron Jobs](/app/cronjobs)
- [Logs & Containers](/app/logs-containers)
- [Architecture on the host](/server/architecture-on-host)
