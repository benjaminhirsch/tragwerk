# Routes

Routes map incoming domains to your applications. The required `<routes>`
element tells Tragwerk which application should serve which hostname. On the VPS,
these become Traefik labels and the host-level reverse proxy does the actual
routing.

## `<routes>`

`<routes>` contains one or more `<route>` elements.

```xml
<routes>
    <route pattern="https://{default}" upstream="app:http"/>
</routes>
```

## `<route>`

| Attribute  | Required | Default | Description                                                                       |
| ---------- | -------- | ------- | --------------------------------------------------------------------------------- |
| `pattern`  | Yes      | —       | URL pattern with optional `{placeholder}` host tokens, e.g. `https://{default}`.  |
| `upstream` | Yes      | —       | Target application in the form `AppName:endpoint`, e.g. `app:http`.               |

### `pattern` and placeholders

A pattern is a URL like `https://{default}` or `https://api.{default}`. The
`{placeholder}` tokens are replaced at build time with the domains assigned to
that placeholder in the project's [domain settings](/app/domains). For example,
if `default` resolves to `example.com`:

- `https://{default}` → routes `example.com`
- `https://api.{default}` → routes `api.example.com`

You can use different placeholders (`{default}`, `{api}`, `{docs}`, …) to give
different applications their own set of domains.

### `upstream`

`upstream` is the target application in the form `AppName:endpoint`. The name
before the colon must match an `<application name>` (the slugified form is also
accepted); the endpoint is `http`.

::: warning Upstream must match an application
The application name in `upstream` has to correspond to a declared
`<application>`. A route whose upstream does not match any app serves no traffic.
:::

## Multi-application routing example

Two applications, each on its own subdomain:

```xml
<applications>
    <application name="Tragwerk" type="php:8.5" root="app">
        <web>
            <location path="/" root="public" index="index.php" passthru="/index.php"/>
        </web>
    </application>
    <application name="Documentation" type="php:8.5" root="docs">
        <web>
            <location path="/" root="./" index="index.html" passthru="none"/>
        </web>
    </application>
</applications>
<routes>
    <route pattern="https://{default}" upstream="Tragwerk:http"/>
    <route pattern="https://{docs}" upstream="Documentation:http"/>
</routes>
```

## Routing on the host

Tragwerk does not run a proxy inside each project. Instead, each application
container is labeled for **Traefik**, and a single host-level Traefik instance
inspects those labels and routes domains (terminating TLS via Let's Encrypt) to
the right container. This is how multiple applications and environments share one
VPS. See [Architecture on the host](/self-hosting/architecture-on-host).

## Related

- [Domains (app guide)](/app/domains) — assigning domains to placeholders
- [Web](/config/web) — what each application serves
- [Architecture on the host](/self-hosting/architecture-on-host)
- [Examples](/config/examples)
