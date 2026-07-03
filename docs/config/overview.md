# XML Configuration

Every Tragwerk project is described by a single XML file, `.tragwerk/config.xml`,
committed to the root of your git repository. This file is the source of truth
for how your project is built and run: Tragwerk reads it on every deploy and
**generates the Docker Compose file and Dockerfiles** that run your applications,
workers, cron jobs, and backing services on the server.

## Where the file lives

The configuration file must be at `.tragwerk/config.xml`, relative to the
repository root:

```
your-repo/
├── .tragwerk/
│   └── config.xml
├── public/
│   └── index.php
└── composer.json
```

## Validation against the schema

Before anything is generated, the file is validated against an XML Schema
(`schema.xsd`). If the file is malformed, references an unknown service, uses an
invalid enum value (for example an unsupported PHP version), or contains an
invalid cron schedule, the deploy fails with a validation error and nothing is
deployed. Keeping configuration valid up front means the generated Compose and
Dockerfiles are always consistent.

You can reference the schema from your file for editor autocompletion:

```xml
<project xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://console.tragwerk.app/schema.xsd">
```

## The `<project>` root

`<project>` is the root element. It has the following children:

| Element            | Required | Description                                                                 |
| ------------------ | -------- | --------------------------------------------------------------------------- |
| `<applications>`   | Yes      | One or more `<application>` definitions — your runnable apps.                |
| `<routes>`         | Yes      | One or more `<route>` definitions mapping domains to applications.           |
| `<services>`       | No       | Backing services (databases, caches) that applications connect to.          |

Names throughout the file (application, service, worker, cron, mount and
relationship names) use a common identifier format:

::: warning Identifier rules
Names must match the pattern `[a-zA-Z][a-zA-Z0-9 _-]*` — they start with a
letter and may contain letters, digits, spaces, hyphens and underscores, 1–64
characters. Each name must be unique within its scope (for example, no two
applications may share a name).
:::

## XML processing quirks

Two transformations happen when the file is parsed; keep them in mind when
reading the rest of this reference:

- **Kebab-case attributes become camelCase internally.** For example
  `clone-from-parent` becomes `cloneFromParent` and `max-requests` becomes
  `maxRequests`. You always write the kebab-case form in XML.
- **`"true"` / `"false"` strings become booleans.** Boolean attributes such as
  `clone-from-parent` accept the literal strings `true` and `false`.

## How it drives generation

When you deploy, Tragwerk turns the validated configuration into concrete Docker
artifacts:

- Each `<application>` becomes a **FrankenPHP** service, with a generated
  `Dockerfile.{app}` that installs your `<extensions>`, bakes in your crontab,
  and applies `<hooks type="build">`. By default FrankenPHP runs in classic mode
  (a drop-in PHP-FPM replacement); adding `<workerMode>` opts in to worker mode.
- Each `<worker>` becomes a long-lived sidecar container.
- A `<crons>` block adds a **supercronic** sidecar per application.
- Each `<service>` becomes a database or cache container with a persistent
  volume.
- Each `<relationship>` injects connection environment variables and adds a
  health-gated `depends_on`.
- `<routes>` become **Traefik** labels so the host-level reverse proxy can route
  domains to the right application.

## A complete example

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<project xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://console.tragwerk.app/schema.xsd">
    <applications>
        <application name="app" type="php:8.5" root="/">
            <extensions>
                <extension name="intl"/>
                <extension name="pdo_pgsql"/>
            </extensions>
            <web>
                <location path="/" root="public" index="index.php" passthru="/index.php"/>
            </web>
            <hooks>
                <hook type="build"><![CDATA[
                  composer install --no-dev --optimize-autoloader
                ]]></hook>
            </hooks>
            <relationships>
                <relationship name="database" target="db"/>
            </relationships>
        </application>
    </applications>
    <services>
        <service name="db" type="postgresql:18" disk="2048"/>
    </services>
    <routes>
        <route pattern="https://{default}" upstream="app:http"/>
    </routes>
</project>
```

## See each element

- [Applications](/config/applications) — `<application>`, `<extensions>`, `<workerMode>`
- [Web](/config/web) — `<web>` / `<location>`
- [Workers](/config/workers) — `<workers>` / `<worker>`
- [Crons](/config/crons) — `<crons>` / `<cron>`
- [Hooks](/config/hooks) — `<hooks>` / `<hook>`
- [Mounts](/config/mounts) — `<mounts>` / `<mount>`
- [Relationships](/config/relationships) — `<relationships>` / `<relationship>`
- [Services](/config/services) — `<services>` / `<service>`
- [Routes](/config/routes) — `<routes>` / `<route>`
- [Examples](/config/examples) — complete, copy-pasteable configurations

## Related

- [Core concepts](/guide/concepts)
- [Architecture on the host](/server/architecture-on-host)
