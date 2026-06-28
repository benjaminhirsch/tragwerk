# Mounts

Application containers run **read-only** by design. Mounts give you the writable,
persistent storage your app needs — upload directories, generated files, caches —
that survive restarts and re-deploys. You declare them in the optional
`<mounts>` element.

## `<mounts>`

`<mounts>` contains one or more `<mount>` elements.

```xml
<mounts>
    <mount name="storage" source="local" path="storage" clone-from-parent="true"/>
</mounts>
```

## `<mount>`

| Attribute           | Required | Default | Description                                                                                  |
| ------------------- | -------- | ------- | -------------------------------------------------------------------------------------------- |
| `name`              | Yes      | —       | Unique mount name within the application. Used to derive the volume name.                     |
| `source`            | Yes      | —       | Backing storage: `local` or `service`.                                                        |
| `path`              | Yes      | —       | Mount path inside the container. Relative paths are prefixed with `/app/`.                    |
| `clone-from-parent` | No       | `true`  | When creating a new branch environment, copy the mount's contents from the parent env.       |

::: warning Name pattern
`name` must match `[a-zA-Z][a-zA-Z0-9 _-]*` and be unique among the
application's mounts.
:::

### `source`: local vs. service

- **`local`** — a local disk volume on the host. Best for per-environment data
  that lives next to the container.
- **`service`** — shared object storage. Use for data that should be available
  across environments or backed by a storage service.

### `path`

The path is where the volume is mounted inside the container. An absolute path
is used as-is; a relative path like `storage` is prefixed with `/app/`, becoming
`/app/storage`.

### `clone-from-parent`

When you push a new branch, Tragwerk creates a fresh environment. With
`clone-from-parent="true"` (the default), the new environment's mount is
seeded with a copy of the parent environment's mount data — handy for getting a
realistic preview environment. Set it to `false` to start the mount empty.

## Resulting Docker Compose effect

Each mount becomes a named Docker volume `{appSlug}-{mountSlug}`, mounted into the
container at the resolved path. For an app `app` with a mount `storage`, the
volume is `app-storage` mounted at `/app/storage`. The same volumes are attached
to the app's workers and cron sidecar so they share the storage.

```xml
<application name="app" type="php:8.5" root="/">
    <web>
        <location path="/" root="public" index="index.php" passthru="/index.php"/>
    </web>
    <mounts>
        <mount name="storage" source="local" path="storage" clone-from-parent="true"/>
        <mount name="uploads" source="service" path="/app/var/uploads"/>
    </mounts>
</application>
```

## Related

- [Hooks](/config/hooks) — `deploy` hooks can write to mounts while the app dir is read-only
- [Environments](/app/environments) — how branch environments are created
- [Examples](/config/examples)
