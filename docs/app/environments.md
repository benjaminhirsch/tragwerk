# Environments

An environment is one deployed git branch of a [project](/app/projects), running
as an isolated set of containers with its own services, variables and domains.
Deployments are branch-based: the root branch is your production environment, and
any other branch can be deployed as a preview environment.

## Status badges

The environment page and the project's environment table show the current state:

| Badge          | Meaning                                                        |
| -------------- | ------------------------------------------------------------- |
| **Running**    | Latest deployment completed; containers are up.               |
| **Deploying**  | A deployment is pending or running.                           |
| **Failed**     | The latest deployment failed.                                 |
| **Not deployed** | The branch has never been deployed.                         |
| **Paused**     | The environment was paused; its containers are stopped.       |

## Open an environment

From the project overview, click a branch in the **Environments** table. The
environment page shows live metrics, status codes, and the **Deployments** table
streamed in real time. If a primary domain exists, an **Open** button links to
the live site.

## Operations

The header buttons drive the lifecycle of an environment.

### Redeploy

**Redeploy** triggers a new deployment of the latest commit on the branch — use
it to re-run a deploy with current code or after changing
[variables](/app/variables). Confirm in the dialog; you are redirected back to
the environment page where the new deploy job streams live.

### Pause

**Pause** (previously *Disable*) stops the environment's blue/green containers
without deleting anything. Data and configuration are kept, and the status
switches to **Paused**. A redeploy reactivates it.

### Delete

**Delete** stops and removes the environment's containers, destroys its database
volume and deletes the branch's environment.

::: warning
Deleting an environment is irreversible — its containers and data volume are
destroyed. Pause instead if you only want to stop it temporarily.
:::

### Sync environment data

**Sync environment data** pulls data from the running container down to your
local setup, so you can work with a copy of the environment's data.

## Example: redeploy after changing a variable

1. Add or edit an [environment variable](/app/variables).
2. Open the environment and click **Redeploy**.
3. Confirm — variables are injected into the container on the next deploy.
4. Watch the new job complete in the **Deployments** table.

## Related

- [Deployments](/app/deployments)
- [Domains & SSL](/app/domains)
- [Environment Variables](/app/variables)
- [Projects](/app/projects)
