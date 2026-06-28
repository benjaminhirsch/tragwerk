# Services

Services are the stateful backing stores your applications use — relational
databases and key-value caches. They are declared in the optional `<services>`
element and connected to applications via [relationships](/config/relationships).

## `<services>`

`<services>` contains one or more `<service>` elements.

```xml
<services>
    <service name="db" type="postgresql:18" disk="2048"/>
    <service name="redis" type="redis:8"/>
</services>
```

## `<service>`

| Attribute | Required | Default | Description                                                              |
| --------- | -------- | ------- | ------------------------------------------------------------------------ |
| `name`    | Yes      | —       | Unique service name. Referenced by a relationship's `target`.            |
| `type`    | Yes      | —       | Service engine and version. See the matrix below.                        |
| `disk`    | No       | `512`   | Disk allocation in MB (positive integer). Applies to stateful services.  |

::: warning Name pattern
`name` must match `[a-zA-Z][a-zA-Z0-9 _-]*` and be unique within the project.
The name is slugified to become the service's hostname on the Docker network.
:::

## Type matrix

| Engine     | `type` values                                                             |
| ---------- | ------------------------------------------------------------------------- |
| PostgreSQL | `postgresql:14`, `postgresql:15`, `postgresql:16`, `postgresql:17`, `postgresql:18` |
| MySQL      | `mysql:8`                                                                  |
| MariaDB    | `mariadb:10.6`, `mariadb:10.11`, `mariadb:11.4`, `mariadb:11.8`           |
| Redis      | `redis:6`, `redis:7`, `redis:8`                                            |
| Valkey     | `valkey:8`, `valkey:9`                                                     |

## Default credentials

Databases are provisioned with fixed default credentials, exposed to apps via
[relationship env vars](/config/relationships):

| Setting        | Value                          |
| -------------- | ------------------------------ |
| Database name  | `app`                          |
| User           | `app`                          |
| Password       | `secret`                       |
| Root password  | `root` (MySQL/MariaDB only)    |

Redis and Valkey require no credentials in the default setup.

## How applications connect

Applications never reference services directly. Instead, you declare a
[relationship](/config/relationships) whose `target` matches the service `name`.
Tragwerk then injects `TRAGWERK_<NAME>_*` connection variables and adds a
health-gated `depends_on`:

```xml
<application name="app" type="php:8.5" root="/">
    <web>
        <location path="/" root="public" index="index.php" passthru="/index.php"/>
    </web>
    <relationships>
        <relationship name="database" target="db"/>
    </relationships>
</application>
<!-- ... -->
<services>
    <service name="db" type="postgresql:18" disk="2048"/>
</services>
```

## Resulting Docker Compose effect

Each service becomes a container using the resolved official image, with:

- a persistent named volume `{slug}-data` mounted at the engine's data
  directory (so data survives restarts);
- default credentials passed as the engine's standard environment variables
  (e.g. `POSTGRES_DB`/`POSTGRES_USER`/`POSTGRES_PASSWORD`);
- a healthcheck (e.g. `pg_isready`, `mysqladmin ping`, or `redis-cli ping`) that
  relationships' `service_healthy` conditions wait on.

::: tip Choose the right PHP extension
Remember to add the matching PDO extension to your app — `pdo_pgsql` for
PostgreSQL, `pdo_mysql` for MySQL/MariaDB. See [Extensions](/config/applications#extensions).
:::

## Related

- [Relationships](/config/relationships) — connecting apps to services
- [Services (app guide)](/app/services) — managing services in the UI
- [Examples](/config/examples)
