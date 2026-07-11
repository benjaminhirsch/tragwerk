# Tragwerk

**Self-host PHP applications on your own Server.**

Tragwerk turns any server with SSH access into a fully managed PHP hosting platform.
You describe your project in a single XML file and through the web UI; Tragwerk
generates the entire Docker setup, provisions the server, and deploys - no
hand-written Dockerfiles, no compose wrangling, no vendor lock-in.

## What makes Tragwerk different

- **Bring your own server.** Any provider that offers a VPS with SSH works.
  If [Docker runs on it](https://docs.docker.com/engine/install/), Tragwerk runs
  on it. Your infrastructure stays yours.
- **XML config, not YAML or JSON sprawl.** One clear, intuitive project file describes
  your app, services, cron jobs and environment. The full Docker / Docker
  Compose stack is generated from it automatically.
- **Many apps, one server.** A built-in [Traefik](https://traefik.io/) reverse proxy
  routes multiple applications on the same server, each isolated in its own
  containers.
- **Modern PHP runtime.** Apps run on [FrankenPHP](https://frankenphp.dev/):
  classic mode by default - a drop-in PHP-FPM replacement - with optional worker
  mode for persistent, high-throughput serving.
- **Batteries included.** Per-team access control, per-app cron jobs, workers, live server
  metrics, deploy history and encrypted-at-rest SSH credentials come out of the
  box.

## How it works

1. Connect a server over SSH - Tragwerk installs Docker and prepares the host.
2. Define your project in XML and the web UI.
3. Push to deploy - Tragwerk clones the repo, builds the image, and starts the
   containers behind Traefik.

The target server only ever runs finished Docker images; no source code or build
tooling lives on your VPS.

## Documentation

Full guides, configuration reference and tutorials live at
**[docs.tragwerk.app](https://docs.tragwerk.app/)**.

## License

Tragwerk is free and open source software, licensed under the  GNU Affero General Public License v3.0 (AGPL-3.0). 
See the `LICENSE` file for the full text.
