# Servers

A **Server** is a registered VPS that Tragwerk deploys to. You add the
connection details and credentials once; Tragwerk then provisions the machine
and runs your applications on it.

## Register a server

In the UI, create a new server and provide:

- A **name** to identify the server.
- **Connection details** — the host (IP or hostname) and SSH port.
- A **credential** — the SSH key or password used to authenticate.

Manage reusable SSH keys and passwords under
[Registries & Credentials](/app/registries-credentials), then select one when
registering the server.

Once registered, run [Server Setup](/self-hosting/server-setup) to provision the
Docker environment before deploying.

## How credentials are used

Credentials authenticate Tragwerk's SSH connection to the server. They are used
for:

- The automated [server setup](/self-hosting/server-setup) job.
- Every deploy and management action (build, start/stop containers, blue/green
  switches, cleanup).

A separate kind of credential — a **registry** — lets the server pull private
container images during builds and deploys. See
[Registries & Credentials](/app/registries-credentials).

::: tip
Store SSH keys as credentials rather than passwords where possible, and reuse a
single credential across servers that share a key.
:::

## Edit and delete

- **Edit** a server to update its host, port, or selected credential — for
  example after rotating an SSH key or moving the VPS.
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

- [Server Setup](/self-hosting/server-setup)
- [Registries & Credentials](/app/registries-credentials)
- [Architecture on the Host](/self-hosting/architecture-on-host)
- [Environments](/app/environments)
