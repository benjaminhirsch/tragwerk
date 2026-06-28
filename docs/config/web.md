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
