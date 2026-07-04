# Server Setup

After you [register a server](/server/servers), Tragwerk provisions it with
an automated **setup job**. The job connects over SSH and prepares the host to
run your applications.

## What the setup job provisions

- **Docker** and Docker Compose on the host — installed from the **official Docker
  package repository** (apt for Debian/Ubuntu, dnf/yum for RHEL/Fedora/CentOS and
  the RHEL rebuilds Rocky Linux/AlmaLinux, which use Docker's CentOS repo), the
  daemon is enabled, and it is skipped if Docker is already present.
- **Traefik** as a host-level reverse proxy — it terminates TLS (Let's Encrypt)
  and routes public traffic to your application containers.
- The shared external Docker network **`tragwerk-net`** that every application
  container joins so Traefik can reach it.

Privileged commands run as `root` or via `sudo -n`, depending on the credential's
[privilege level](/app/registries-credentials#privilege-level).

After setup completes, the server is ready to receive deploys. See
[Architecture on the Host](/server/architecture-on-host) for what the
running stack looks like.

## Live setup logs and statuses

Server setup runs asynchronously and streams **live progress logs** to the UI as
it works. The job moves through these statuses:

- **Pending** — queued, not yet started.
- **Running** — actively provisioning the host.
- **Completed** — the server is provisioned and ready.
- **Failed** — provisioning stopped on an error; check the logs.

The UI updates in real time as setup progresses.

## Running setup

Setup is triggered entirely from the UI: open the server and start the setup
job. Tragwerk connects over SSH and provisions the machine in the background
while streaming progress to the page — there is nothing to run yourself.

::: warning
If the SSH connection details or the selected credential are wrong, setup will
**Fail** early. Verify the host, port, and that the
[credential](/app/registries-credentials) (SSH key, username, and privilege
level) is correct and authorized on the server. In sudo mode, also confirm the
user has **passwordless sudo (`NOPASSWD`)** — `sudo -n` fails fast otherwise.
Then retry.
:::

## Related

- [Servers](/server/servers)
- [Architecture on the Host](/server/architecture-on-host)
