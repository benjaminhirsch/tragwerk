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

| Attribute      | Required  | Default          | Description                                                                   |
| -------------- | --------- | ---------------- | ------------------------------------------------------------------------------ |
| `name`         | Yes       | —                | Unique worker name within the application. Used to derive the container name.   |
| `command`      | See below | —                | The long-running command the container executes, run from `/app`.               |
| `memory-limit` | No        | the app's `<php>` | PHP `memory_limit` for this worker only, e.g. `2G`.                            |

Every worker needs exactly one command, given **either** as the `command`
attribute (one-liner) **or** as the element body (multi-line script). Supplying
both — or neither — is a configuration error and fails validation.

::: warning Name pattern
`name` must match `[a-zA-Z][a-zA-Z0-9 _-]*` and be unique among the
application's workers.
:::

## Multi-line commands

XML collapses newlines inside an attribute value, so a script spanning several
lines goes into the element body instead — the same convention as
[hooks](/config/hooks). Wrap it in `CDATA` so `&`, `<` and `>` need no escaping.

```xml
<workers>
    <worker name="mailer"><![CDATA[
        export APP_ROLE=mailer
        exec bin/cli worker:start mail
    ]]></worker>
</workers>
```

::: tip End on `exec`
A plain single-line command is `exec`'d for you, so it becomes the container's
main process and receives `docker stop`'s `SIGTERM` directly. As soon as the
command sequences several commands — anything containing `;`, `&` or `|`, so
`&&` and `||` included — that is skipped, because `exec a && b` would replace the
shell with `a` and never reach `b`. The same goes for a multi-line script. In
both cases put `exec` in front of the long-running command yourself:

```xml
<worker name="queue"><![CDATA[
    bin/cli worker:prepare-queue && exec bin/cli worker:start default -w 4
]]></worker>
```

Without it the shell stays in the way and shutdown falls back to `SIGKILL` after
the stop timeout.
:::

::: warning CDATA does not decode entities
Inside `<![CDATA[ ... ]]>` the content is taken literally, so write `&&`, not
`&amp;&amp;` — the latter reaches the shell verbatim and fails.
:::

Each line is trimmed and blank lines are dropped, exactly like a hook body. That
means indentation is not preserved, so here-documents and other
indentation-sensitive constructs do not survive; put those in a script file in
your repository and call it from the worker command.

## Resulting Docker Compose effect

For an application slug `app`, a worker named `queue` becomes a container named
`{appSlug}-worker-{slug}` — here `app-worker-queue`. The worker:

- reuses the application image (`build`/`image`), environment variables, mounts,
  and `depends_on` from the app service;
- runs your command instead of the web server — Tragwerk bakes it into the image
  as `/usr/local/bin/tragwerk-worker-{slug}` and points the compose `command:` at
  that script, so the command runs under `/bin/sh` and shell syntax (`&&`, pipes,
  `$VAR`) works as written;
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

## Memory

A worker inherits the application's [`<php>` settings](/config/php), including
`memory_limit`. Set `memory-limit` on the worker when one job legitimately needs
more than the web application:

```xml
<workers>
    <worker name="queue" command="bin/cli worker:start default"/>
    <worker name="import" command="bin/cli import:run" memory-limit="2G"/>
</workers>
```

Only `memory_limit` is replaced — every other `<php>` setting and all loaded
extensions stay as they are.

::: warning `-1` removes a safety net, it does not add headroom
A limit is worth most in a process that runs for days: it turns a leak into a
clean crash the restart policy recovers from. Without one, a leaking worker grows
until the kernel's out-of-memory killer intervenes, and that kill can land on a
different container on the same host. Raise the number rather than removing it.
:::

::: tip `max_execution_time` needs no setting
The PHP CLI ignores `max_execution_time` from any `php.ini` — for a worker it is
always unlimited. Only the application's own code can impose one, via
`ini_set()` or `set_time_limit()`. If a worker dies after a fixed number of
seconds, that is where to look.
:::

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
