# Web

The required `<web>` element defines how HTTP requests are served for an
application. It contains one or more `<location>` elements, each mapping a URL
path prefix to a directory and deciding whether requests are served as static
files or passed to a PHP front controller.

## `<web>`

`<web>` is required on every application and must contain at least one
`<location>`.

```xml
<web>
    <location path="/" root="public" index="index.php" passthru="/index.php"/>
</web>
```

## `<location>`

| Attribute  | Required | Default     | Description                                                                                       |
| ---------- | -------- | ----------- | ------------------------------------------------------------------------------------------------ |
| `path`     | Yes      | —           | URL path prefix this location matches, e.g. `/` or `/api`.                                        |
| `root`     | Yes      | —           | Directory (relative to the app root) served for this path, e.g. `public`.                        |
| `index`    | No       | `index.php` | Index/default file served for directory requests, e.g. `index.php` or `index.html`.              |
| `passthru` | No       | —           | PHP front controller to pass non-static requests to, e.g. `/index.php`. Omit (or `none`) for static-only. |

### Static-only vs. front controller

The `passthru` attribute decides the behavior of a location:

- **Static only** — omit `passthru`, or set `passthru="none"`. Requests are
  served straight from disk via FrankenPHP's `file_server`; no PHP runs. Use this
  for static sites and pre-rendered output.
- **PHP front controller** — set `passthru` to a front controller path such as
  `/index.php`. Requests that don't match a real file are routed to that script,
  the standard pattern for framework-based PHP apps.

A static documentation site:

```xml
<web>
    <location path="/" root="./" index="index.html" passthru="none"/>
</web>
```

A PHP application with a front controller:

```xml
<web>
    <location path="/" root="public" index="index.php" passthru="/index.php"/>
</web>
```

### Static sites & clean URLs

A `passthru="none"` location serves files straight from disk. For pre-rendered
sites that use **clean URLs** (no `.html` in links — VitePress, Astro, Hugo and
similar), Tragwerk also resolves an **extensionless request to `{path}.html`**,
then to a directory `index.html`. So a request for `/guide/intro` is served from
`guide/intro.html`, and deep links work on **direct load and refresh**, not only
through in-app navigation.

A complete static-site project — a documentation app built during the image
build and served on its own subdomain:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<project xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://console.tragwerk.app/schema.xsd">
    <applications>
        <application name="Documentation" type="php:8.5" root="docs">
            <web>
                <location path="/" root=".vitepress/dist" index="index.html" passthru="none"/>
            </web>
            <hooks>
                <hook type="build"><![CDATA[
                  npm ci
                  npm run docs:build
                ]]></hook>
            </hooks>
        </application>
    </applications>
    <routes>
        <route pattern="https://{docs}" upstream="Documentation:http"/>
    </routes>
</project>
```

::: tip
`type` selects the base image, but with `passthru="none"` **no PHP runs** — the
container just serves the built static output. Point `root` at your generator's
output directory (here `.vitepress/dist`) and produce it in a `build` hook.
:::

### Multiple locations

You can declare several locations to serve different paths differently — for
example a static asset directory alongside a PHP app:

```xml
<web>
    <location path="/assets" root="public/assets" passthru="none"/>
    <location path="/" root="public" index="index.php" passthru="/index.php"/>
</web>
```

## Worker mode and passthru

`passthru` works the same way in **both** FrankenPHP modes: in the default
[classic mode](/config/applications#classic-vs-worker-mode) (the PHP-FPM drop-in)
you can declare any number of front-controller locations. The restriction below
applies **only when** the application opts in to worker mode.

::: warning Exactly one passthru in worker mode
If — and only if — the application enables
[worker mode](/config/applications#workermode), the worker script is derived from
the `<web>` location that declares a `passthru`. In that case there must be
**exactly one** `<location>` with a non-null `passthru`. Zero passthru locations
leaves no worker entrypoint; more than one is ambiguous, and the build will fail.
:::

## Related

- [Applications](/config/applications) — `<application>` and `<workerMode>`
- [Routes](/config/routes) — mapping domains to applications
- [Examples](/config/examples)
