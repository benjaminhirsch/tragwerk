# Environment Variables

Environment variables are key/value pairs injected into your containers **at
runtime**. Use them for configuration and secrets such as `DATABASE_URL` or API
tokens. Variables are scoped: a variable can apply to the
whole project or to a single [environment](/app/environments), and environment
values override project values.

## Inheritance and scope

Every variable has a **scope**:

- **Whole project** — applies to every environment, including ones that only
  come into existence with a later push.
- **This environment** — applies to a single branch.

Environment-scoped variables additionally carry an **Inherited** flag: the value
is automatically passed down to child environments, so a value set once on a
parent branch is available to its previews.

Independently of the scope, a variable can be marked **Secret**, which shows the
value masked in the UI and in logs.

The variables table marks each row accordingly: **All environments**,
**Inherited** (passed down from a parent branch) or **This environment only**.
When the same key exists at several levels, the more specific one wins —
environment beats inherited, inherited beats project-wide.

::: tip Configure before the first push
A project has no environments until you push a branch, but project-wide
variables can be created right away — the very first build already sees them.
That is the way to avoid a first deploy that fails on missing configuration.
:::

::: tip Editing scope
You can edit or delete project-wide variables anywhere, but environment-scoped
ones only on the environment they belong to. Values inherited from a parent
branch are managed where they were originally defined.
:::

## Create a variable

1. Open **Variables** and click **New variable**.
2. Pick the **Scope** — **Whole project** or **This environment**. Before the
   first push there is no environment to pick, so the variable is project-wide.
3. Enter a **Key** — letters, digits and underscores (e.g. `DATABASE_URL`). The
   key is stored uppercase, so `database_url` becomes `DATABASE_URL`.
4. Enter the **Value**.
5. Optionally tick **Secret** (mask in UI/logs) and, for an environment-scoped
   variable, **Inherited** (pass down to child environments).
6. Click **Create variable**.

The scope of an existing variable cannot be changed. To override a project-wide
value for one environment, create a variable with the same key in that
environment — it takes precedence.

## Edit or delete a variable

In the table, click a key to edit it, or use the trash icon to delete it and
confirm in the dialog.

::: warning Takes effect on next deploy
Adding, changing or deleting a variable does not touch running containers.
[Redeploy](/app/environments) the environment for the change to be applied.
:::

## Not the same as platform variables

These user-defined variables are distinct from the `TRAGWERK_*` variables that
Tragwerk injects itself: the project, environment and commit metadata described
under [platform variables](/app/platform-variables), and the connection details
generated from your `<relationships>` configuration (see
[relationships](/config/relationships)).

Those names are reserved. A key starting with `TRAGWERK_`, or `SERVER_NAME` /
`MAX_REQUESTS`, is rejected when you save it — Tragwerk writes its own values
last, so such a variable would never reach your application anyway.

## Related

- [Platform variables](/app/platform-variables)
- [Relationships configuration](/config/relationships)
- [Services](/app/services)
- [Environments](/app/environments)
- [Deployments](/app/deployments)
