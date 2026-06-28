# Workers

Workers are long-lived background processes that run alongside your application —
queue consumers, schedulers, metric samplers, and similar. Each worker runs in
its own container that shares the application image but runs a custom command
instead of serving HTTP traffic.

## `<workers>`

The optional `<workers>` element contains one or more `<worker>` definitions.

```xml
<workers>
    <worker name="queue" command="php artisan queue:work"/>
    <worker name="scheduler" command="php bin/console messenger:consume async"/>
</workers>
```

## `<worker>`

| Attribute | Required | Default | Description                                                                       |
| --------- | -------- | ------- | --------------------------------------------------------------------------------- |
| `name`    | Yes      | —       | Unique worker name within the application. Used to derive the container name.      |
| `command` | Yes      | —       | The long-running command the container executes, run from `/app`.                 |

::: warning Name pattern
`name` must match `[a-zA-Z][a-zA-Z0-9 _-]*` and be unique among the
application's workers.
:::

## Resulting Docker Compose effect

For an application slug `app`, a worker named `queue` becomes a container named
`{appSlug}-worker-{slug}` — here `app-worker-queue`. The worker:

- reuses the application image (`build`/`image`), environment variables, mounts,
  and `depends_on` from the app service;
- runs your `command` instead of the web server;
- uses `restart: unless-stopped` so it comes back after crashes;
- has its healthcheck disabled (it is not an HTTP service).

```xml
<application name="app" type="php:8.5" root="/">
    <web>
        <location path="/" root="public" index="index.php" passthru="/index.php"/>
    </web>
    <workers>
        <worker name="queue" command="php artisan queue:work"/>
    </workers>
    <relationships>
        <relationship name="database" target="db"/>
    </relationships>
</application>
```

## Workers vs. cron jobs

A **worker** is a process that stays running continuously (it loops and consumes
work as it arrives). A **cron job** runs a command on a schedule and exits.
Choose a worker for queue consumers and daemons; choose a [cron](/config/crons)
for periodic one-shot commands like nightly cleanup.

## Related

- [Workers (app guide)](/app/workers) — managing and observing workers in the UI
- [Crons](/config/crons) — scheduled commands
- [Relationships](/config/relationships) — wiring workers to services
- [Examples](/config/examples)
