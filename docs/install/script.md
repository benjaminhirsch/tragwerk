# Install Script

The installer does everything on the [Docker Compose](/install/docker-compose)
page for you: it installs Docker Engine, clones the repository, generates the
`.env` with fresh secrets, checks DNS and ports, opens the firewall, starts the
stack and creates your first user.

```bash
curl -fsSL https://raw.githubusercontent.com/benjaminhirsch/tragwerk/main/install.sh | sudo bash
```

::: tip Prerequisites
A server running a Linux distribution [Docker
supports](https://docs.docker.com/engine/install/), root or `sudo`, and DNS
already pointing at it. The installer checks the DNS records but cannot create
them — see [requirements](/install/requirements).
:::

It asks for four things and derives the rest: the public hostname, the Let's
Encrypt contact address, an optional documentation hostname, and the name and
e-mail of the first user. The password is read from a hidden prompt.

## What it does

1. **Checks the machine.** Root or passwordless `sudo`, the distribution, that
   `curl`, `git`, `openssl` and `iproute2` are installed, that ports 80, 443 and
   the git push port are free, and that there is enough RAM and disk.
2. **Installs Docker Engine and the Compose plugin** from Docker's official
   package repository — the same GPG-verified `apt`/`dnf` repository the manual
   instructions use, not the `get.docker.com` convenience script. Skipped when
   Docker is already usable.
3. **Clones the repository** to `/opt/tragwerk`, or reuses the checkout the
   script is run from.
4. **Writes `.env`** from `.env.prod.dist`: your hostnames, the git push port,
   `MERCURE_TOPIC_BASE`, and five freshly generated secrets. The file is created
   with mode `600`.
5. **Verifies DNS** resolves your hostnames to this machine, before anything can
   burn a Let's Encrypt rate limit.
6. **Opens the firewall** — ports 80, 443 and the git push port in `ufw` or
   `firewalld`, if either is active.
7. **Starts the stack**, waits for the migrations to finish and for the app to
   report healthy, and prints the logs of whatever failed if something does.
8. **Creates the first user** through `bin/cli user:create`, already confirmed,
   so you can log in without SMTP configured.

## Options

Everything the installer would ask can be passed up front. Note the `-s --`
separator when piping from `curl`:

```bash
curl -fsSL https://raw.githubusercontent.com/benjaminhirsch/tragwerk/main/install.sh \
  | sudo bash -s -- --app-host tragwerk.example.com --acme-email admin@example.com --yes
```

| Option | Purpose |
|---|---|
| `--app-host HOST` | Public hostname of the web UI. |
| `--acme-email EMAIL` | Contact address for Let's Encrypt. |
| `--docs-host HOST` | Serve the bundled documentation under this hostname. |
| `--ssh-port PORT` | Host port for the git push target. Default `2222`. |
| `--dir PATH` | Where to clone. Default `/opt/tragwerk`. |
| `--no-registration` | Disable public sign-up at `/register`. |
| `--admin-email`, `--admin-firstname`, `--admin-lastname` | The first user. |
| `--skip-docker` | Never install Docker; fail if it is missing. |
| `--skip-firewall` | Do not touch `ufw` / `firewalld`. |
| `--skip-dns-check` | Do not verify that the hostnames resolve here. |
| `-y`, `--yes` | Never ask. Requires `--app-host` and `--acme-email`. |
| `--check-only` | Run the checks and exit without changing anything. |
| `-h`, `--help` | Show all options. |

Each option also works as an environment variable: `APP_HOST`, `ACME_EMAIL`,
`DOCS_HOST`, `SSH_PORT`, `TRAGWERK_INSTALL_DIR`, `TRAGWERK_REGISTRATION_ENABLED`,
`TRAGWERK_ADMIN_EMAIL`, `TRAGWERK_ADMIN_FIRSTNAME`, `TRAGWERK_ADMIN_LASTNAME`,
`TRAGWERK_ASSUME_YES`. Secrets are always generated and are never read from the
environment.

With `--yes` no account is created, because a password cannot be passed safely on
a command line. The installer prints the `user:create` command to run instead.

## Checking before installing

`--check-only` runs the whole preflight — distribution, Docker, ports, DNS,
firewall — and changes nothing:

```bash
sudo ./install.sh --check-only --app-host tragwerk.example.com
```

Useful before the first install, and afterwards to see why something is
unreachable.

## Running it again

The installer is safe to re-run, for instance after fixing DNS or to bring a
half-finished install to completion. On a second run it:

- keeps the existing `.env` and **never rotates a secret that is already set** —
  `CREDENTIAL_ENCRYPTION_KEY` and `TWO_FACTOR_KEY` encrypt data in the database,
  and replacing them would make stored SSH keys and 2FA secrets permanently
  unreadable. A secret is only generated when its value is still empty;
- updates the hostnames, the git push port and the registration flag to whatever
  you pass, and leaves every other line of the file alone;
- skips the port check while your own stack holds ports 80 and 443;
- skips the Docker install when Docker already works.

A run writes `/var/log/tragwerk-install.log`, appending rather than replacing.

To upgrade an existing installation, do not re-run the installer — follow
[Upgrades](/install/upgrades).

## What it does not do

- **Create DNS records.** It only verifies them. Point the A/AAAA records at the
  server first.
- **Open cloud firewalls.** It handles `ufw` and `firewalld` on the host;
  security groups at your provider are yours to open.
- **Configure SMTP.** Mail stays optional; see the environment reference on the
  [Docker Compose](/install/docker-compose#environment-reference) page.
- **Set up backups.** Read [Backup & Restore](/install/backup) — in particular,
  back up `.env` alongside the database.

## Troubleshooting

The installer stops at the first real problem and prints the relevant container
log. The [Docker Compose](/install/docker-compose#troubleshooting) page lists the
common causes: DNS or a blocked port for certificate failures, an unreachable
database for a failing `migrate` service.

If you would rather see each step yourself, the manual route is fully documented
on the [Docker Compose](/install/docker-compose) page — the installer does
nothing beyond it.

## Related

- [Requirements](/install/requirements)
- [Docker Compose](/install/docker-compose) — the manual installation
- [Upgrades](/install/upgrades)
- [Backup & Restore](/install/backup)
