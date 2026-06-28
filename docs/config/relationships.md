# Relationships

A relationship wires an application to a backing [service](/config/services) — a
database or cache. Declaring a relationship does two things: it injects the
service's connection details as environment variables into the application, and
it makes the application wait for the service to be healthy before starting.

## `<relationships>`

`<relationships>` contains one or more `<relationship>` elements.

```xml
<relationships>
    <relationship name="database" target="db"/>
    <relationship name="cache" target="redis"/>
</relationships>
```

## `<relationship>`

| Attribute | Required | Default | Description                                                                |
| --------- | -------- | ------- | -------------------------------------------------------------------------- |
| `name`    | Yes      | —       | Logical name of the relationship. Used to build the env var prefix.        |
| `target`  | Yes      | —       | Name of the service to connect to. Must match a `<service name>`.          |

::: warning Target must exist
`target` must match the `name` of a service declared under
[`<services>`](/config/services). A relationship that points at a non-existent
service is invalid.
:::

## Environment variable injection

For each relationship, Tragwerk injects connection variables into the
application container (and its workers and cron sidecar). The variable **prefix**
is derived from the relationship `name`:

```
TRAGWERK_<NAME>_
```

where `<NAME>` is the relationship name upper-cased with spaces and hyphens
replaced by underscores. So `name="database"` produces the prefix
`TRAGWERK_DATABASE_`, and `name="read-replica"` produces `TRAGWERK_READ_REPLICA_`.

### Keys for SQL services (postgresql, mysql, mariadb)

| Variable                    | Value (defaults)         |
| --------------------------- | ------------------------ |
| `TRAGWERK_<NAME>_HOST`      | service hostname (slug)  |
| `TRAGWERK_<NAME>_PORT`      | `5432` (pg) / `3306` (mysql, mariadb) |
| `TRAGWERK_<NAME>_DATABASE`  | `app`                    |
| `TRAGWERK_<NAME>_USER`      | `app`                    |
| `TRAGWERK_<NAME>_PASSWORD`  | `secret`                 |

### Keys for cache services (redis, valkey)

| Variable               | Value (defaults)        |
| ---------------------- | ----------------------- |
| `TRAGWERK_<NAME>_HOST` | service hostname (slug) |
| `TRAGWERK_<NAME>_PORT` | `6379`                  |

::: tip
There is no single `*_URL` variable — connection details are exposed as discrete
`HOST` / `PORT` / `DATABASE` / `USER` / `PASSWORD` parts. Build any DSN your
framework needs from these.
:::

## Worked example

```xml
<application name="app" type="php:8.5" root="/">
    <web>
        <location path="/" root="public" index="index.php" passthru="/index.php"/>
    </web>
    <relationships>
        <relationship name="database" target="db"/>
        <relationship name="cache" target="redis"/>
    </relationships>
</application>
<!-- ... -->
<services>
    <service name="db" type="postgresql:18"/>
    <service name="redis" type="redis:8"/>
</services>
```

This injects the following environment variables into the `app` container:

```bash
TRAGWERK_DATABASE_HOST=db
TRAGWERK_DATABASE_PORT=5432
TRAGWERK_DATABASE_DATABASE=app
TRAGWERK_DATABASE_USER=app
TRAGWERK_DATABASE_PASSWORD=secret

TRAGWERK_CACHE_HOST=redis
TRAGWERK_CACHE_PORT=6379
```

## Health-gated startup

Each relationship also adds a `depends_on` entry on the target service with
condition `service_healthy`. The application container does not start until the
database or cache reports healthy (via the service's healthcheck), so your app
never boots against a service that is not ready yet.

## Related

- [Services](/config/services) — defining the services you connect to
- [Variables (app guide)](/app/variables) — user-defined env vars (system `TRAGWERK_*` keys take precedence)
- [Examples](/config/examples)
