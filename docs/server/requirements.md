# Requirements

Tragwerk deploys your PHP applications to your own server.
You bring the machine and SSH access; Tragwerk installs and manages everything
else — Docker, the Traefik reverse proxy, and the generated per-application
container stack.

## Server and SSH access

The only hard requirement is a server you can reach over **SSH**. Tragwerk connects
to the server, installs the Docker environment, and from then on drives all
provisioning and deploys remotely.

- A reachable host (public IP or hostname) with an open SSH port.
- A login user that is either **`root`** or a **passwordless sudo (`NOPASSWD`)**
  user — you declare which on the credential's [privilege
  level](/app/registries-credentials#privilege-level).
- Authentication via an **SSH key** — stored as a
  [credential](/app/registries-credentials).

You can register any number of servers and run multiple applications on each.
See [Servers](/server/servers) to add one.

::: warning Use a fresh server
We recommend dedicating a **fresh, empty server** to Tragwerk rather than one
that already runs other applications. Tragwerk manages the host-level Docker
environment and binds the public web ports for its Traefik reverse proxy, so
sharing the machine with unrelated services risks port conflicts and
interference.
:::

## Ports

Tragwerk's [Traefik reverse proxy](#what-tragwerk-installs-and-manages) terminates
public traffic on the standard web ports, so these must be **free** on the host —
no other web server, proxy, or container may already bind them:

- **80 (HTTP)** — inbound routing and Let's Encrypt HTTP-01 challenges.
- **443 (HTTPS)** — TLS-terminated application traffic.

You also need your **SSH port** (usually 22) reachable for Tragwerk to connect.
Open 80 and 443 in any firewall or cloud security group in front of the server.

## Docker support matrix

Tragwerk runs everything on the server through Docker and Docker Compose. The
officially supported targets are therefore **whatever Docker itself supports**.

Refer to the official Docker Engine install matrix for supported distributions
and versions: <https://docs.docker.com/engine/install/>.

::: tip
You do not need to install Docker yourself. Tragwerk's
[server setup](/server/server-setup) installs and configures the Docker
environment for you over SSH, using the **official Docker package repository**
(apt for Debian/Ubuntu, dnf/yum for RHEL/Fedora/CentOS/Rocky Linux/AlmaLinux) —
signed, pinnable packages rather than the `get.docker.com` convenience script.
Rocky Linux and AlmaLinux are binary-compatible with RHEL and use Docker's CentOS
repository.
:::

## What Tragwerk installs and manages

During [server setup](/server/server-setup) Tragwerk provisions:

- **Docker** and Docker Compose on the host.

On your **first deploy** it then also brings up the shared routing layer:

- A shared external Docker network, `tragwerk-net`, that every application
  container joins so Traefik can reach it.
- **Traefik** as a host-level reverse proxy that terminates TLS and routes
  public traffic to your applications — launched once as `tragwerk-traefik` and
  reused by every later deploy.

Per application it then generates a `Dockerfile.{appSlug}` and a
`docker-compose.yml` and runs the resulting containers. See
[Architecture on the Host](/server/architecture-on-host).

## Sizing guidance

Sizing depends entirely on your workload, but as a generic starting point:

- **CPU/RAM:** A small app comfortably runs on 1–2 vCPU and 1–2 GB RAM. Add
  headroom for each additional application, database, or service container, plus
  the memory needed for image builds.
- **Disk:** Plan for your application image(s), build cache, and any persistent
  [mounts](/config/mounts) and service volumes. Image builds and the registry
  cache can grow over time.

Scale up as you add applications — multiple apps coexist on one server, so a larger
server lets you consolidate more environments.

## DNS and domains

Public routing and automatic TLS (Let's Encrypt via Traefik) require working DNS:

- Point an **A/AAAA record** for each domain (or a wildcard) at your server's IP.
- Each application is routed by host/subdomain, so distinct apps on the same server
  use distinct hostnames.
- Valid public DNS is also a prerequisite for Let's Encrypt certificate issuance.

See [Domains](/app/domains) for configuring hostnames per environment.

## Related

- [Servers](/server/servers)
- [Server Setup](/server/server-setup)
- [Architecture on the Host](/server/architecture-on-host)
- [Domains](/app/domains)
