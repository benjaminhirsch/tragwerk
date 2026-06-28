# Requirements

Tragwerk deploys your PHP applications to your own virtual private server (VPS).
You bring the machine and SSH access; Tragwerk installs and manages everything
else — Docker, the Traefik reverse proxy, and the generated per-application
container stack.

## VPS and SSH access

The only hard requirement is a VPS you can reach over **SSH**. Tragwerk connects
to the server, installs the Docker environment, and from then on drives all
provisioning and deploys remotely.

- A reachable host (public IP or hostname) with an open SSH port.
- A user that can run Docker (typically `root`, or a sudo-capable user).
- Authentication via an SSH key or password — stored as a
  [credential](/app/registries-credentials).

You can register any number of servers and run multiple applications on each.
See [Servers](/self-hosting/servers) to add one.

## Docker support matrix

Tragwerk runs everything on the VPS through Docker and Docker Compose. The
officially supported targets are therefore **whatever Docker itself supports**.

Refer to the official Docker Engine install matrix for supported distributions
and versions: <https://docs.docker.com/engine/install/>.

::: tip
You do not need to install Docker yourself. Tragwerk's
[server setup](/self-hosting/server-setup) installs and configures the Docker
environment for you over SSH.
:::

## What Tragwerk installs and manages

During [server setup](/self-hosting/server-setup) Tragwerk provisions:

- **Docker** and Docker Compose on the host.
- **Traefik** as a host-level reverse proxy that terminates TLS and routes
  public traffic to your applications.
- A shared external Docker network, `tragwerk-net`, that every application
  container joins so Traefik can reach it.

Per application it then generates a `Dockerfile.{appSlug}` and a
`docker-compose.yml` and runs the resulting containers. See
[Architecture on the Host](/self-hosting/architecture-on-host).

## Sizing guidance

Sizing depends entirely on your workload, but as a generic starting point:

- **CPU/RAM:** A small app comfortably runs on 1–2 vCPU and 1–2 GB RAM. Add
  headroom for each additional application, database, or service container, plus
  the memory needed for image builds.
- **Disk:** Plan for your application image(s), build cache, and any persistent
  [mounts](/config/mounts) and service volumes. Image builds and the registry
  cache can grow over time.

Scale up as you add applications — multiple apps coexist on one VPS, so a larger
server lets you consolidate more environments.

## DNS and domains

Public routing and automatic TLS (Let's Encrypt via Traefik) require working DNS:

- Point an **A/AAAA record** for each domain (or a wildcard) at your server's IP.
- Each application is routed by host/subdomain, so distinct apps on the same VPS
  use distinct hostnames.
- Valid public DNS is also a prerequisite for Let's Encrypt certificate issuance.

See [Domains](/app/domains) for configuring hostnames per environment.

## Related

- [Servers](/self-hosting/servers)
- [Server Setup](/self-hosting/server-setup)
- [Architecture on the Host](/self-hosting/architecture-on-host)
- [Domains](/app/domains)
