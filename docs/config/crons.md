# Crons

Cron jobs run scheduled commands inside your application's runtime — nightly
cleanups, periodic imports, heartbeat checks. You declare them in the optional
`<crons>` element, and Tragwerk runs them on the VPS in a dedicated
**supercronic** sidecar container.

## `<crons>`

`<crons>` contains one or more `<cron>` definitions.

```xml
<crons>
    <cron name="cleanup" command="php artisan app:cleanup" schedule="0 2 * * *"/>
    <cron name="heartbeat" command="php artisan app:heartbeat" schedule="@hourly"/>
</crons>
```

## `<cron>`

| Attribute  | Required | Default | Description                                                                  |
| ---------- | -------- | ------- | ---------------------------------------------------------------------------- |
| `name`     | Yes      | —       | Unique cron name within the application. Used as a log label.                |
| `command`  | Yes      | —       | The command to run on schedule, executed from the application root (`/app`). |
| `schedule` | Yes      | —       | A crontab schedule expression. See the syntax below.                         |

::: warning Name pattern
`name` must match `[a-zA-Z][a-zA-Z0-9 _-]*` and be unique among the
application's cron jobs.
:::

## Schedule syntax

The `schedule` attribute accepts three forms:

### 1. Standard cron fields

A standard **5-field** expression — `minute hour day-of-month month day-of-week`:

```
0 2 * * *      # daily at 02:00
*/15 * * * *   # every 15 minutes
0 9 * * 1-5    # 09:00 Monday–Friday
```

#### Field reference

| Position | Field        | Allowed values                    | Special characters |
| -------- | ------------ | --------------------------------- | ------------------ |
| 1        | Minute       | `0–59`                            | `* / , -`          |
| 2        | Hour         | `0–23`                            | `* / , -`          |
| 3        | Day of month | `1–31`                            | `* / , - ? L W`    |
| 4        | Month        | `1–12` or `JAN–DEC`               | `* / , -`          |
| 5        | Day of week  | `0–7` or `SUN–SAT` (`0` and `7` = Sunday) | `* / , - ? L #` |

#### Special characters

| Char | Meaning                      | Example                                                            |
| ---- | ---------------------------- | ----------------------------------------------------------------- |
| `*`  | Every value                  | `* * * * *` — every minute                                        |
| `,`  | Value list                   | `0 9,12,17 * * *` — at 09:00, 12:00 and 17:00                      |
| `-`  | Range                        | `0 9-17 * * *` — hourly from 09:00 to 17:00                        |
| `/`  | Step (within `*` or a range) | `*/5 * * * *` — every 5 min; `0-30/10 * * * *` — at min 0,10,20,30 |

Month and weekday **names** are case-insensitive and can be ranged or listed:

```
0 8 * * MON-FRI         # 08:00 on weekdays
0 0 1 JAN,APR,JUL,OCT * # midnight on the first of each quarter
```

::: info Advanced day-of-month / day-of-week tokens
The day-of-month and day-of-week fields also accept the advanced tokens `L`
(last), `W` (nearest weekday), `#` (nth weekday) and `?` (no specific value).
These are passed straight through to supercronic — Tragwerk validates the rest
of the expression but does not deep-check these tokens.
:::

::: warning Five fields only
Although supercronic itself can take a six-field expression with a leading
**seconds** field, the project configuration schema currently accepts **only
five-field** expressions (plus the descriptors and `@every` forms below). A
six-field schedule is rejected at build time. Use `@every 30s` for sub-minute
intervals instead.
:::

### 2. Descriptors

| Descriptor             | Meaning                          |
| ---------------------- | -------------------------------- |
| `@yearly` / `@annually`| Once a year, at midnight Jan 1.  |
| `@monthly`             | Once a month, at midnight day 1. |
| `@weekly`              | Once a week, midnight Sunday.    |
| `@daily` / `@midnight` | Once a day, at midnight.         |
| `@hourly`              | Once an hour, on the hour.       |

### 3. `@every` intervals

`@every` followed by a Go-style duration. Valid units are `ns`, `us`, `ms`, `s`,
`m`, `h`, and they can be combined:

```
@every 30m      # every 30 minutes
@every 1h30m    # every 90 minutes
@every 10s      # every 10 seconds
```

### Examples

| Schedule              | Runs                                            |
| --------------------- | ----------------------------------------------- |
| `* * * * *`           | Every minute                                    |
| `*/5 * * * *`         | Every 5 minutes                                 |
| `0 * * * *`           | Top of every hour                               |
| `15,45 * * * *`       | At minute 15 and 45 of every hour               |
| `0 9-17 * * *`        | Hourly from 09:00 to 17:00                      |
| `0 2 * * *`           | Daily at 02:00                                  |
| `0 8 * * MON-FRI`     | 08:00 on weekdays                               |
| `0 0 * * 0`           | Weekly, Sunday midnight                         |
| `0 0 1 * *`           | Monthly, first day at midnight                  |
| `0 0 1 JAN,JUL *`     | Midnight on Jan 1 and Jul 1                     |
| `@hourly`             | Every hour                                      |
| `@daily`              | Daily at midnight                               |
| `@weekly`             | Sunday at midnight                              |
| `@monthly`            | First of the month at midnight                  |
| `@every 10s`          | Every 10 seconds                                |
| `@every 5m`           | Every 5 minutes                                 |
| `@every 1h30m`        | Every 90 minutes                                |

::: warning Schedules are validated at build time
Invalid schedule expressions fail the deploy (the build is rejected before any
container starts). Fix the expression and re-deploy.
:::

## Resulting Docker Compose effect

When an application declares any crons, Tragwerk adds a single supercronic
sidecar container named `{app}-cron`. It shares the application image,
environment, and mounts, bakes your crontab into the image, and runs each
command on schedule. Run history and output are captured from this container's
logs.

```xml
<application name="app" type="php:8.5" root="/">
    <web>
        <location path="/" root="public" index="index.php" passthru="/index.php"/>
    </web>
    <crons>
        <cron name="cleanup" command="php artisan app:cleanup" schedule="0 2 * * *"/>
        <cron name="heartbeat" command="php artisan app:heartbeat" schedule="@hourly"/>
    </crons>
</application>
```

## Related

- [Cron Jobs (app guide)](/app/cronjobs) — run history and live logs in the UI
- [Workers](/config/workers) — for continuously running processes
- [Examples](/config/examples)
