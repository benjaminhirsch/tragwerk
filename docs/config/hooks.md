# Hooks

Hooks are shell scripts that run at specific points in the deployment lifecycle —
installing dependencies during the build, running migrations before traffic is
routed, warming caches after the app goes live. You declare them in the optional
`<hooks>` element.

## `<hooks>`

`<hooks>` contains one or more `<hook>` elements, at most one per type.

```xml
<hooks>
    <hook type="build"><![CDATA[
      composer install --no-dev --optimize-autoloader
    ]]></hook>
    <hook type="deploy"><![CDATA[
      php artisan migrate --force
    ]]></hook>
</hooks>
```

## `<hook>`

The script body is the element's text content. The only attribute is `type`.

| Attribute | Required | Default | Description                                              |
| --------- | -------- | ------- | -------------------------------------------------------- |
| `type`    | Yes      | —       | One of `build`, `deploy`, `post_deploy`. Unique per app. |

### Hook types

| Type          | When it runs                                | Filesystem                                | Network | Traffic                          |
| ------------- | ------------------------------------------- | ----------------------------------------- | ------- | -------------------------------- |
| `build`       | During the image build.                     | App directory **writable**.               | Yes     | Not serving yet.                 |
| `deploy`      | After the container starts, before routing. | App directory **read-only**, mounts writable. | —    | Not yet routed — blocks traffic. |
| `post_deploy` | After traffic is being served.              | App directory read-only, mounts writable. | —       | Already live; runs non-blocking. |

::: tip Choosing a type
- **`build`** — anything that produces build artifacts: `composer install`,
  asset compilation. It runs with network access and a writable app directory,
  and the result is baked into the image.
- **`deploy`** — work that must finish before users hit the new version:
  database migrations. The app directory is read-only here, so write only to
  mounts.
- **`post_deploy`** — best-effort work that should not delay going live: cache
  warming, pinging external services.
:::

## CDATA guidance

::: warning Use CDATA for scripts
Wrap multi-line scripts (and anything containing `&`, `<`, `>`, or quotes) in a
`<![CDATA[ ... ]]>` block so the shell content is not interpreted as XML.
:::

```xml
<hook type="deploy"><![CDATA[
  php artisan migrate:status
  php artisan migrate --force
]]></hook>
```

[Worker commands](/config/workers#multi-line-commands) use the same convention
for their multi-line form.

## How a hook is executed

Every hook body becomes a real `/bin/sh` script with `set -e`, not a chain of
`&&`-joined commands. Comments, `if`/`for`/`case` and multi-line quoting all work
as written. A `build` hook is copied into the image as
`/usr/local/bin/tragwerk-build-hook` and run in a single `RUN` layer; `deploy`
and `post_deploy` hooks become part of the container entrypoint.

```xml
<hook type="build"><![CDATA[
  # Install dependencies
  composer install --no-dev --optimize-autoloader

  if [ -f package.json ]; then
      npm ci && npm run build
  fi
]]></hook>
```

Each line is trimmed and blank lines are dropped, so indentation is not
preserved. Here-documents and other indentation-sensitive constructs therefore do
not survive — keep those in a script file in your repository and call it from the
hook.

## Examples

Install dependencies at build time:

```xml
<hook type="build"><![CDATA[
  composer install --no-dev --optimize-autoloader
]]></hook>
```

Run migrations before traffic is routed:

```xml
<hook type="deploy"><![CDATA[
  php artisan migrate:status
  php artisan migrate --force
]]></hook>
```

Warm caches after going live:

```xml
<hook type="post_deploy"><![CDATA[
  php artisan cache:warm
]]></hook>
```

## Related

- [Applications](/config/applications)
- [Deployments](/app/deployments) — how a deploy progresses
- [Mounts](/config/mounts) — the writable storage available to `deploy` hooks
- [Examples](/config/examples)
