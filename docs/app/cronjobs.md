# Cron Jobs

Cron jobs run scheduled commands inside your application's environment — for
example clearing caches, sending digests, or pruning data. You define them in
`.tragwerk/config.xml` and Tragwerk runs them on the server in a dedicated
scheduler container.

## Defining cron jobs

Cron jobs live in the XML `<crons>` block. Each job has a name, a schedule
(standard cron expression), and a command. See the configuration reference:
[Crons configuration](/config/crons).

```xml
<crons>
    <cron name="cache-clear" schedule="0 * * * *" command="bin/console cache:clear" />
    <cron name="digest" schedule="0 6 * * *" command="bin/console app:send-digest" />
</crons>
```

## How it runs on the server

For each application Tragwerk starts a **supercronic** sidecar container named
`{app}-cron`. Supercronic is a cron implementation built for containers: it
reads the generated crontab, runs each command on schedule inside the app's
runtime, and logs structured (JSON) output.

::: info
Supercronic is used instead of the system `cron` daemon because it logs to
stdout in a machine-readable form, runs as a non-root container process, and
needs no MTA — which is exactly what Tragwerk needs to capture run history.
:::

## Run history and live logs

The Cron Jobs view shows three things:

- **Definitions** — the jobs declared in your config (name, schedule, command).
- **Run history** — the last execution time and result (success/failure) per
  job.
- **Live logs** — output streamed as a job runs.

Behind the scenes Tragwerk parses the `{app}-cron` container's supercronic logs,
persists each run (start time, finish time, success flag, captured output), and
streams updates so the UI reflects runs live. A run shows as in-progress until
its finishing log line is ingested. This happens automatically.

## Example

A job that runs every five minutes:

```xml
<crons>
    <cron name="heartbeat" schedule="*/5 * * * *" command="bin/console app:heartbeat" />
</crons>
```

After deployment, open **Cron Jobs**, find `heartbeat`, and watch its run
history populate every five minutes with the last result and log output.

## Related

- [Crons configuration](/config/crons)
- [Workers](/app/workers)
- [Logs & Containers](/app/logs-containers)
