# Example Configurations

Complete, valid `.tragwerk/config.xml` files for common setups. Each validates
against `schema.xsd` and can be used as a starting point. For details on any
element, follow the links in the [overview](/config/overview).

## Minimal single PHP app

The smallest useful configuration: one PHP app served from `public/`, routed to
your default domain. No services, workers, or cron jobs.

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<project xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://www.tragwerk.app/schema.xsd">
    <applications>
        <application name="app" type="php:8.5" root="/">
            <web>
                <location path="/" root="public" index="index.php" passthru="/index.php"/>
            </web>
        </application>
    </applications>
    <routes>
        <route pattern="https://{default}" upstream="app:http"/>
    </routes>
</project>
```

## Full-featured app

A single app exercising most features: worker mode, cron jobs, build and deploy
hooks, a persistent mount, and relationships to PostgreSQL and Redis.

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<project xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://www.tragwerk.app/schema.xsd">
    <applications>
        <application name="My Test Project" type="php:8.5" root="/">
            <web>
                <location path="/" root="public" index="index.php" passthru="/index.php"/>
            </web>
            <workerMode count="4" max-requests="1000"/>
            <crons>
                <cron name="cleanup" command="php artisan app:cleanup" schedule="0 2 * * *"/>
                <cron name="heartbeat" command="php artisan app:heartbeat" schedule="@hourly"/>
            </crons>
            <hooks>
                <hook type="build"><![CDATA[
                  composer install --no-dev --optimize-autoloader
                ]]></hook>
                <hook type="deploy"><![CDATA[
                  php artisan migrate:status
                  php artisan migrate --force
                ]]></hook>
            </hooks>
            <mounts>
                <mount name="storage" source="local" path="storage" clone-from-parent="true"/>
            </mounts>
            <relationships>
                <relationship name="database" target="db"/>
                <relationship name="cache" target="redis"/>
            </relationships>
        </application>
    </applications>
    <services>
        <service name="db" type="postgresql:18" disk="2048"/>
        <service name="redis" type="redis:8"/>
    </services>
    <routes>
        <route pattern="https://{default}" upstream="My Test Project:http"/>
    </routes>
</project>
```

## Multiple applications

Two applications behind two subdomains — a PHP app on the default domain and a
static documentation site on its own placeholder. Both connect to the same
database where relevant.

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<project xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://www.tragwerk.app/schema.xsd">
    <applications>
        <application name="Tragwerk" type="php:8.5" root="app">
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
        <application name="Documentation" type="php:8.5" root="docs">
            <web>
                <location path="/" root="./" index="index.html" passthru="none"/>
            </web>
        </application>
    </applications>
    <services>
        <service name="db" type="postgresql:18" disk="2048"/>
    </services>
    <routes>
        <route pattern="https://{default}" upstream="Tragwerk:http"/>
        <route pattern="https://{docs}" upstream="Documentation:http"/>
    </routes>
</project>
```

## Static site only

A site with no PHP at all — served entirely as static files via `passthru="none"`.
No services or relationships needed.

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<project xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://www.tragwerk.app/schema.xsd">
    <applications>
        <application name="site" type="php:8.5" root="/">
            <web>
                <location path="/" root="public" index="index.html" passthru="none"/>
            </web>
        </application>
    </applications>
    <routes>
        <route pattern="https://{default}" upstream="site:http"/>
    </routes>
</project>
```

## Worker-heavy app

An app focused on background processing: a queue consumer and a scheduler worker,
plus a per-minute cron, backed by PostgreSQL and Redis.

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<project xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://www.tragwerk.app/schema.xsd">
    <applications>
        <application name="app" type="php:8.5" root="/">
            <extensions>
                <extension name="pcntl"/>
                <extension name="pdo_pgsql"/>
                <extension name="sockets"/>
            </extensions>
            <web>
                <location path="/" root="public" index="index.php" passthru="/index.php"/>
            </web>
            <workers>
                <worker name="queue" command="php artisan queue:work --tries=3"/>
                <worker name="scheduler" command="php bin/console messenger:consume async"/>
            </workers>
            <crons>
                <cron name="health" command="php artisan app:health" schedule="* * * * *"/>
            </crons>
            <relationships>
                <relationship name="database" target="db"/>
                <relationship name="cache" target="redis"/>
            </relationships>
        </application>
    </applications>
    <services>
        <service name="db" type="postgresql:18" disk="2048"/>
        <service name="redis" type="redis:8"/>
    </services>
    <routes>
        <route pattern="https://{default}" upstream="app:http"/>
    </routes>
</project>
```

## Related

- [Overview](/config/overview)
- [Applications](/config/applications)
- [Services](/config/services)
- [Routes](/config/routes)
