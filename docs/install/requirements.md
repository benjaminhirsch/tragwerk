# Installation Requirements

Tragwerk is self-hosted: you run the Tragwerk instance itself on a server you
own, and it deploys your applications to other servers over SSH. This page
covers what the machine running Tragwerk needs.

::: info Two different servers
This section is about the server that **runs Tragwerk** — the control plane.
The [Server](/server/requirements) section is about the servers Tragwerk
**deploys your applications to**. They can be the same machine only in a
single-server experiment; see [Ports](#ports) for why that gets crowded.
:::

## The machine

- **Docker Engine** with the **Compose plugin**. Nothing else is installed on
  the host: every Tragwerk service runs in a container. Follow the official
  install matrix at <https://docs.docker.com/engine/install/> — the supported
  operating systems are whatever Docker itself supports.
- **git**, to clone this repository. Only the Compose file and the environment
  template come from the clone; the application code ships inside the images.
- Root or `sudo` access, since Tragwerk's containers bind the public web ports
  and mount the Docker socket.

::: warning Tragwerk mounts the Docker socket
The app and worker containers get `/var/run/docker.sock` mounted so they can
drive Docker. That is effectively root on the host. Treat the Tragwerk machine
as a trusted, administrative box and do not share it with untrusted workloads.
:::

## Ports

Tragwerk's bundled Traefik terminates public traffic, so these ports must be
**free** on the host and reachable from the internet:

- **80 (HTTP)** — inbound routing, and the redirect to HTTPS.
- **443 (HTTPS)** — the web UI and the documentation, TLS-terminated.
- **2222 (SSH)** — the git push target that your projects clone from. The port
  is configurable via `SSH_PORT`; it must not collide with the host's own SSH
  daemon.

Open 80, 443 and your `SSH_PORT` in any firewall or cloud security group in
front of the machine.

Because Tragwerk itself binds 80 and 443, a server hosting a Tragwerk instance
cannot at the same time be a deploy target for your applications — the
per-application Traefik would fight for the same ports. Give Tragwerk its own
machine.

## DNS

Traefik requests Let's Encrypt certificates over the TLS-ALPN challenge, which
resolves your hostnames against public DNS.

- Point an **A/AAAA record** for your app host (`APP_HOST`) at the server.
- Point a second one for the bundled documentation (`DOCS_HOST`).

::: warning DNS must exist before the first start
If the records do not resolve to this server when the stack first comes up, the
certificate challenge fails. Let's Encrypt rate-limits repeated failures, so fix
DNS first and start Tragwerk second.
:::

## Sizing

Tragwerk's control plane is modest: the web server, three worker processes, a
PostgreSQL database, an SSH daemon and Traefik. **2 vCPU and 2 GB RAM** is a
comfortable starting point.

The load that actually grows is deployment work — cloning repositories and
streaming build output — and it grows with the number of projects and how often
you deploy, not with the traffic your applications serve. That traffic hits your
deploy targets, not this machine.

Disk usage is dominated by the git repositories Tragwerk stores for your
projects, plus the database. Both grow slowly.

## Next steps

- Install the stack with [Docker Compose](/install/docker-compose).
- Read what the [target servers](/server/requirements) for your applications
  need.
