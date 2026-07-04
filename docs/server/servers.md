# Servers

A **Server** is a registered machine that Tragwerk deploys to. You add the
connection details and credentials once; Tragwerk then provisions the machine
and runs your applications on it.

## Register a server

In the UI, create a new server and provide:

- A **name** to identify the server.
- **Connection details** — the host (IP or hostname) and SSH port.
- A **credential** — the SSH key, login user, and privilege level used to
  authenticate and run commands.

Manage reusable SSH credentials under
[Registries & Credentials](/app/registries-credentials), then select one when
registering the server.

Once registered, run [Server Setup](/server/server-setup) to provision the
Docker environment before deploying.

## How credentials are used

Credentials authenticate Tragwerk's SSH connection to the server. They are used
for:

- The automated [server setup](/server/server-setup) job.
- Every deploy and management action (build, start/stop containers, blue/green
  switches, cleanup).

A separate kind of credential — a **registry** — lets the server pull private
container images during builds and deploys. See
[Registries & Credentials](/app/registries-credentials).

::: tip
Reuse a single credential across servers that share an SSH key. Prefer a
dedicated non-root user in [sudo mode](/app/registries-credentials#privilege-level)
over a root login where your setup allows it.
:::

## Edit and delete

- **Edit** a server to update its host, port, or selected credential — for
  example after rotating an SSH key or moving the server.
- **Delete** a server to remove it from Tragwerk. Removing a server detaches it
  from any projects/environments that targeted it; remove or repoint those
  deployments first.

## Relationship to projects and environments

A server is the deploy target for your [environments](/app/environments). When
an environment deploys, Tragwerk connects to the assigned server over SSH,
generates the per-application Docker configuration, and brings up the containers
there. Multiple applications and environments can share the same server — each
is routed by its own hostname through Traefik.

## Related

- [Server Setup](/server/server-setup)
- [Registries & Credentials](/app/registries-credentials)
- [Architecture on the Host](/server/architecture-on-host)
- [Environments](/app/environments)
