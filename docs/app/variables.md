# Environment Variables

Environment variables are key/value pairs injected into your containers at
**build and runtime**. Use them for configuration and secrets such as
`DATABASE_URL` or API tokens. Variables are scoped: a variable can apply to the
whole project or to a single [environment](/app/environments), and environment
values override project values.

## Inheritance and scope

Each variable belongs to a project and a branch and carries two flags:

- **Secret** — the value is stored encrypted and shown masked in the UI and in
  logs.
- **Inherited** — the value is automatically passed down to child environments,
  so a value set once on a parent branch is available to its previews.

The variables table marks each row accordingly: **Inherited** (passed down from
a parent scope) or **This environment only** (defined on the current branch).
When the same key exists at multiple levels, the **environment** value wins over
the project value.

::: tip Editing scope
You can only edit or delete variables that are defined on the **current**
environment. Inherited values are managed where they were originally defined.
:::

## Create a variable

1. Open **Environment variables** for the active environment and click
   **New variable**.
2. Enter a **Key** — uppercase letters, digits and underscores (e.g.
   `DATABASE_URL`).
3. Enter the **Value**.
4. Optionally tick **Secret** (store encrypted, mask in UI/logs) and/or
   **Inherited** (pass down to child environments).
5. Click **Create variable**.

## Edit or delete a variable

In the table, click a key to edit it, or use the trash icon to delete it and
confirm in the dialog.

::: warning Takes effect on next deploy
Adding, changing or deleting a variable does not touch running containers.
[Redeploy](/app/environments) the environment for the change to be applied.
:::

## Not the same as relationship variables

These user-defined variables are distinct from the `TRAGWERK_*` variables that
Tragwerk injects from your `<relationships>` configuration. Relationship
variables describe connections to backing [services](/app/services) (database,
cache, etc.) and are generated automatically — you do not create them here. See
[relationships](/config/relationships).

## Related

- [Relationships configuration](/config/relationships)
- [Services](/app/services)
- [Environments](/app/environments)
- [Deployments](/app/deployments)
