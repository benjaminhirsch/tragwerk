# Platform Variables

Tragwerk injects a set of `TRAGWERK_*` environment variables into every container of an
environment — the application, its [workers](/app/workers) and the
[cron sidecar](/app/cronjobs). They tell your application that it is running on Tragwerk and
which project, environment and commit it belongs to.

They are provided by the platform: you never create them, and unlike
[your own variables](/app/variables) they cannot be overridden. A user-defined variable using one
of these names is rejected when you save it.

## Detecting Tragwerk

```php
if (getenv('TRAGWERK') === '1') {
    // Running on a Tragwerk-deployed container
}
```

## Build or runtime?

`TRAGWERK_PHASE` names the lifecycle stage the current process belongs to:

| Value | Where |
| ----- | ----- |
| `build` | Inside a [`build` hook](/config/hooks), during the image build. |
| `deploy` | Inside a `deploy` hook, before the container takes traffic. |
| `post_deploy` | Inside a `post_deploy` hook, after traffic is being served. |
| `runtime` | The application server, and every worker and cron process. |

```php
$phase = getenv('TRAGWERK_PHASE');

if ($phase === 'build') {
    // No database, no routes — do not touch a service here.
}
```

::: warning No services during the build
The image build runs on the control plane, not on your server: there is no database, no cache and
no network to your other containers. That is why the relationship variables, the routes, the commit
and the deploy id are runtime-only — see [build-time variables](#build-time-variables). Anything
that needs a service belongs in a `deploy` or `post_deploy` hook.
:::

## Scalar variables

| Variable | Example | Description |
| -------- | ------- | ----------- |
| `TRAGWERK` | `1` | Always `1`. The marker to test for. |
| `TRAGWERK_PHASE` | `runtime` | Lifecycle stage — see [above](#build-or-runtime). |
| `TRAGWERK_PROJECT_ID` | `c0ffee00-…` | UUID of the project. |
| `TRAGWERK_PROJECT_NAME` | `My Shop` | Display name of the project. |
| `TRAGWERK_PROJECT_ENTROPY` | `e3b0c442…` | 64 random hex characters. See [below](#tragwerk-project-entropy). |
| `TRAGWERK_ENVIRONMENT` | `new-checkout` | Environment slug — the branch basename, slugified. Matches the container names. |
| `TRAGWERK_BRANCH` | `feature/New Checkout` | The git branch, unmodified. |
| `TRAGWERK_APPLICATION` | `app` | The `<application name>` this container belongs to. |
| `TRAGWERK_COMMIT_SHA` | `9f2c1ab…` | The deployed commit. |
| `TRAGWERK_DEPLOY_ID` | `4b1e…` | UUID of the deployment that started this container. |
| `TRAGWERK_URL` | `https://shop.example.com` | Canonical URL of this application, or empty if it has no route. |

## Structured variables

Routes and relationships are lists, so they arrive as **base64-encoded JSON** in a single
variable. Decode them before use:

```php
/** @var list<array{url: string, host: string, application: string, tls: bool, primary: bool}> $routes */
$routes = json_decode(base64_decode(getenv('TRAGWERK_ROUTES')), true, flags: JSON_THROW_ON_ERROR);

$relationships = json_decode(base64_decode(getenv('TRAGWERK_RELATIONSHIPS')), true, flags: JSON_THROW_ON_ERROR);
```

::: tip Why base64
Docker Compose interpolates `$VAR` inside environment values, and the deploy passes the same
values through a shell command line. base64 has no characters either of them acts on, so the
payload arrives byte-for-byte.
:::

### `TRAGWERK_ROUTES`

A JSON array, one entry per resolved hostname of this application. The first entry is marked
`primary` and is the same value as `TRAGWERK_URL`.

```json
[
  {
    "url": "https://shop.example.com",
    "host": "shop.example.com",
    "application": "app",
    "tls": true,
    "primary": true
  },
  {
    "url": "https://www.shop.example.com",
    "host": "www.shop.example.com",
    "application": "app",
    "tls": true,
    "primary": false
  }
]
```

### `TRAGWERK_RELATIONSHIPS`

A JSON object keyed by [relationship](/config/relationships) name. The shape is the same for every
service type — the keys that do not apply to a cache service are `null` rather than absent.

```json
{
  "database": {
    "type": "postgresql:18",
    "host": "db",
    "port": 5432,
    "database": "app",
    "username": "app",
    "password": "secret"
  },
  "cache": {
    "type": "redis:8",
    "host": "redis",
    "port": 6379,
    "database": null,
    "username": null,
    "password": null
  }
}
```

The same values are also available as the discrete `TRAGWERK_<NAME>_HOST` / `_PORT` / … variables
described under [relationships](/config/relationships) — use whichever fits your configuration
layer. Note that `port` is a real integer here, while `TRAGWERK_<NAME>_PORT` is a string.

## `TRAGWERK_PROJECT_ENTROPY`

A random 64-character hex string, generated once when the project is created and **stable for the
project's whole lifetime**. It is the same value in every environment of the project, so it is a
good seed for hash salting, deriving deterministic keys, or namespacing shared caches.

::: warning Treat it as a secret
It is not shown in the UI and never appears in build logs or in the container image — it reaches
the container only at runtime. Don't log it, don't render it, and don't send it to third parties.
:::

::: tip It never rotates
Tragwerk has no way to change the value, by design: anything you hashed with it would stop
matching. If you need a rotatable secret, create your own
[environment variable](/app/variables) for it.
:::

## Build-time variables

The image build sees the subset of variables that is constant for a branch, so
[`build` hooks](/config/hooks) can branch on the platform:

`TRAGWERK`, `TRAGWERK_PROJECT_ID`, `TRAGWERK_BRANCH`, `TRAGWERK_ENVIRONMENT`,
`TRAGWERK_APPLICATION` — plus `TRAGWERK_PHASE=build`

```xml
<hook type="build"><![CDATA[
  if [ "$TRAGWERK" = "1" ]; then
      composer install --no-dev --optimize-autoloader
  fi
]]></hook>
```

Everything else — the commit, the deploy id, the routes, the relationships and the entropy — is
runtime-only. Two of those reasons are worth knowing: per-deploy values baked into the image would
defeat the image reuse that makes an unchanged redeploy fast, and an `ENV` line is a plaintext
layer that anyone who can pull the image can read.

## Other variables Tragwerk sets

| Variable | Where | Description |
| -------- | ----- | ----------- |
| `SERVER_NAME` | all app containers | `:80` — tells FrankenPHP to listen on port 80 without TLS. TLS terminates at Traefik. |
| `MAX_REQUESTS` | apps using `<workerMode maxRequests>` | Number of requests after which a worker process restarts. |

These names are reserved as well, as is anything starting with `TRAGWERK_`.

## Related

- [Environment Variables](/app/variables) — your own variables
- [Relationships](/config/relationships) — where the connection details come from
- [Hooks](/config/hooks) — build, deploy and post_deploy scripts
- [Architecture on the host](/server/architecture-on-host)
