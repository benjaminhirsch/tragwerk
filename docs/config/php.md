# PHP Settings

The optional `<php>` element sets PHP ini directives for an application —
`memory_limit`, `upload_max_filesize`, `opcache.*`, and any other ini setting.
Tragwerk writes the directives to a php.ini file that is baked into the
container image under the PHP `conf.d` scan directory.

## `<php>`

`<php>` contains one or more `<setting>` entries. Each `<setting>` is a single
`name` / `value` pair written verbatim as `name=value`.

```xml
<php>
    <setting name="memory_limit" value="256M"/>
    <setting name="upload_max_filesize" value="64M"/>
    <setting name="post_max_size" value="64M"/>
    <setting name="max_execution_time" value="60"/>
    <setting name="opcache.jit" value="tracing"/>
</php>
```

## `<setting>`

| Attribute | Required | Description                                                        |
| --------- | -------- | ------------------------------------------------------------------ |
| `name`    | Yes      | PHP ini directive name, e.g. `memory_limit`, `opcache.jit_buffer_size`. |
| `value`   | Yes      | Value for the directive, e.g. `256M`, `60`, `tracing`.             |

::: warning Unique names
Each directive `name` must be unique within the application's `<php>` block. A
duplicate `name` is rejected at build time.
:::

## How it is applied

Tragwerk generates a `php.{app}.ini` file and copies it into the image at
`/usr/local/etc/php/conf.d/zz-tragwerk.ini`. The `conf.d` directory is scanned
alphabetically, and the `zz-` prefix ensures this file loads **last**, so your
directives override the FrankenPHP base image defaults.

The ini is copied **before** your source and build hooks, so build-time PHP
(Composer, framework CLIs) already runs under these settings — a higher
`memory_limit` here also applies to `composer install`.

Because the settings are baked into the image at build time, changing them
requires a redeploy.

## Worker mode caveat

These directives are plain pass-through ini values; Tragwerk does not filter
them. In [worker mode](/config/workers) the front controller is kept warm
between requests, so process-lifetime settings (for example a very low
`memory_limit`) affect the long-lived worker, not a per-request process. Size
them with worker mode in mind.

## Resulting Dockerfile effect

```dockerfile
COPY php.app.ini /usr/local/etc/php/conf.d/zz-tragwerk.ini
```

```ini
# php.app.ini
memory_limit=256M
upload_max_filesize=64M
opcache.jit=tracing
```

## Related

- [Applications](/config/applications)
- [Extensions](/config/applications) — installing PHP extensions
- [Workers](/config/workers) — FrankenPHP worker mode
- [Examples](/config/examples)
