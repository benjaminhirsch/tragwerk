# Applications

An `<application>` is a runnable unit of your project — typically a PHP web app
served by FrankenPHP. Every project has at least one application inside the
required `<applications>` element. Each application becomes its own service (and
generated `Dockerfile.{app}`) in the Docker Compose file.

## `<application>`

| Attribute | Required | Default | Description                                                                                  |
| --------- | -------- | ------- | -------------------------------------------------------------------------------------------- |
| `name`    | Yes      | —       | Unique application name. See the identifier rules below.                                     |
| `type`    | Yes      | —       | Runtime image. One of `php:8.2`, `php:8.3`, `php:8.4`, `php:8.5`.                             |
| `root`    | No       | `/`     | Path within the repository that holds this application's source. Defaults to the repo root.  |

::: warning Name pattern
`name` must match `[a-zA-Z][a-zA-Z0-9 _-]*` (1–64 chars) and be unique within the
project. The name is slugified to derive container names, so `My App` becomes
`my-app`.
:::

### Supported PHP versions

| `type`    | PHP version |
| --------- | ----------- |
| `php:8.2` | PHP 8.2     |
| `php:8.3` | PHP 8.3     |
| `php:8.4` | PHP 8.4     |
| `php:8.5` | PHP 8.5     |

### Child elements

| Element            | Required | Description                                                                 |
| ------------------ | -------- | --------------------------------------------------------------------------- |
| `<web>`            | Yes      | HTTP locations served by this app. See [Web](/config/web).                  |
| `<extensions>`     | No       | PHP extensions to install. See below.                                       |
| `<php>`            | No       | PHP ini settings, e.g. `memory_limit`. See [PHP Settings](/config/php).      |
| `<workerMode>`     | No       | Enable FrankenPHP worker mode. See below.                                   |
| `<workers>`        | No       | Background worker containers. See [Workers](/config/workers).               |
| `<crons>`          | No       | Scheduled commands. See [Crons](/config/crons).                             |
| `<hooks>`          | No       | Lifecycle scripts. See [Hooks](/config/hooks).                              |
| `<mounts>`         | No       | Persistent volumes. See [Mounts](/config/mounts).                           |
| `<relationships>`  | No       | Connections to backing services. See [Relationships](/config/relationships).|

A minimal application:

```xml
<application name="app" type="php:8.5" root="/">
    <web>
        <location path="/" root="public" index="index.php" passthru="/index.php"/>
    </web>
</application>
```

## `<extensions>`

PHP extensions to compile into the application image. Each `<extension>` is
installed with `docker-php-ext-install`; any required system packages (for
example `libicu-dev` for `intl`) are resolved automatically during the build.

### `<extension>`

| Attribute | Required | Default | Description                                                                          |
| --------- | -------- | ------- | ------------------------------------------------------------------------------------ |
| `name`    | Yes      | —       | Extension name as accepted by `docker-php-ext-install`, e.g. `intl`, `pdo_pgsql`.    |

```xml
<extensions>
    <extension name="gettext"/>
    <extension name="intl"/>
    <extension name="pcntl"/>
    <extension name="pdo_pgsql"/>
    <extension name="sockets"/>
    <extension name="curl"/>
</extensions>
```

::: tip
If you connect to a PostgreSQL service, add `pdo_pgsql`. For MySQL/MariaDB add
`pdo_mysql`. The extension list is reflected in the generated `Dockerfile.{app}`.
:::

## Classic vs. worker mode

FrankenPHP can serve an application in two modes, and Tragwerk supports both:

- **Classic mode — the default.** When an application has **no** `<workerMode>`
  element, FrankenPHP handles each request in the traditional per-request
  lifecycle. It is a **drop-in replacement for PHP-FPM**: existing PHP apps run
  unchanged, and no worker-safe code is required. The generated Caddyfile emits a
  plain `frankenphp` directive.
- **Worker mode — opt-in.** Adding `<workerMode>` boots the front controller once
  and keeps it warm between requests for higher throughput. It requires
  worker-safe application code. The generated Caddyfile emits
  `frankenphp { worker … }`.

::: tip Which mode should I use?
Start in classic mode — omit `<workerMode>` and your app runs exactly like it
would under PHP-FPM, with no code changes. Reach for worker mode later, as an
optional performance upgrade, once your application is worker-safe.
:::

## `<workerMode>`

`<workerMode>` is **optional**. Omit it and the application runs in classic mode
(the PHP-FPM drop-in described above). Add it to run the application in
**FrankenPHP worker mode**: the front controller is bootstrapped once and kept
warm between requests, instead of the default per-request bootstrap. This
dramatically reduces per-request overhead for framework-based apps, but requires
worker-safe application code.

| Attribute      | Required | Default          | Description                                                                                              |
| -------------- | -------- | ---------------- | ------------------------------------------------------------------------------------------------------- |
| `count`        | No       | auto (~2× cores) | Number of worker threads (positive integer). Set explicitly on shared hosts to avoid oversubscription.  |
| `max-requests` | No       | `0`              | Restart each worker after this many requests (non-negative integer). `0` means unlimited.               |

```xml
<workerMode count="4" max-requests="1000"/>
```

When `max-requests` is greater than `0`, Tragwerk injects a `MAX_REQUESTS`
environment variable into the container, and the worker script restarts each
worker after that many requests — a guard against memory leaks in long-running
processes.

::: warning Worker mode requires a passthru
Worker mode derives its worker script from the `<web>` location that declares a
`passthru`. You must have **exactly one** `<location>` with a non-null `passthru`
(for example `passthru="/index.php"`). See [Web](/config/web).
:::

## Related

- [Web](/config/web) — the required `<web>` block and the passthru rule
- [Workers](/config/workers) — long-lived background processes
- [Crons](/config/crons) — scheduled commands
- [Hooks](/config/hooks) — build/deploy/post-deploy scripts
- [Mounts](/config/mounts) — persistent storage
- [Relationships](/config/relationships) — connecting to services
- [Examples](/config/examples)
