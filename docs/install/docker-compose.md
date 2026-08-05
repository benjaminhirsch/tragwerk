# Install with Docker Compose

This is the recommended — and only supported — way to run Tragwerk. The images
are built by CI and published to the GitHub Container Registry, so nothing is
compiled on your server: you pull images and start them.

::: tip There is a script for this
Every step below can be done for you, including installing Docker itself:

```bash
curl -fsSL https://raw.githubusercontent.com/benjaminhirsch/tragwerk/main/install.sh | sudo bash
```

See [Install Script](/install/script). This page stays the reference for what
that script does, and the route to take when you want to do it yourself.
:::

::: tip Prerequisites
A server with Docker Engine, the Compose plugin, and DNS already pointing at it.
See [requirements](/install/requirements) — in particular, the DNS records must
resolve **before** you start the stack, or certificate issuance fails.
:::

## 1. Clone the repository

```bash
git clone https://github.com/benjaminhirsch/tragwerk.git
cd tragwerk
```

You only need two files from the clone: `docker-compose.prod.yaml` and the
environment template. The application code itself lives inside the published
images, so you never build or run anything from this source tree.

## 2. Create the environment file

```bash
cp .env.prod.dist .env
```

Open `.env` and fill in the hostname:

```ini
APP_HOST=tragwerk.example.com
ACME_EMAIL=admin@example.com
MERCURE_TOPIC_BASE=https://tragwerk.example.com
```

Optionally, you can serve this documentation from your own installation. Set
`DOCS_HOST` to a second hostname and point a DNS record at the server:

```ini
DOCS_HOST=docs.example.com
```

Leave it empty and no documentation container is started at all — the rest of the
stack is unaffected.

Then generate the secrets. Each command prints one value:

```bash
openssl rand -base64 32   # TRAGWERK_DATABASE_PASSWORD
openssl rand -base64 48   # MERCURE_PUBLISHER_JWT_SECRET
openssl rand -base64 48   # MERCURE_SUBSCRIBER_JWT_SECRET
openssl rand -base64 32   # TWO_FACTOR_KEY
openssl rand -base64 32   # CREDENTIAL_ENCRYPTION_KEY
```

The stack refuses to start if any of them is missing, rather than falling back to
an insecure default.

::: warning These secrets are not regenerable
`CREDENTIAL_ENCRYPTION_KEY` encrypts the SSH private keys of your servers at
rest, and `TWO_FACTOR_KEY` encrypts your users' 2FA secrets. Lose them and the
data in the database becomes permanently unreadable — a database backup alone
will not save you. Back up the `.env` alongside the database, and see
[Backup & Restore](/install/backup).
:::

## 3. Start the stack

```bash
docker compose -f docker-compose.prod.yaml pull
docker compose -f docker-compose.prod.yaml up -d
```

Startup is ordered: PostgreSQL comes up and reports healthy, the one-shot
`migrate` service prepares the queue table and applies the schema migrations,
and only once it exits successfully do the app and the workers start. Traefik
then requests a certificate for `APP_HOST`, and for `DOCS_HOST` if you set one.

The stack is these services:

| Service | Purpose |
|---|---|
| `traefik` | Reverse proxy. Terminates TLS, routes by hostname. The only service binding ports 80 and 443. |
| `app` | The FrankenPHP web server running the Tragwerk UI, plus the Mercure hub for live updates. |
| `worker-queue` | Runs deploy and server-setup jobs. |
| `worker-metrics` | Samples server and application metrics. |
| `worker-crons` | Collects the run history of your applications' cron jobs. |
| `migrate` | One-shot. Prepares the queue and applies migrations, then exits. |
| `db` | PostgreSQL 18. |
| `sshd` | The git push target your projects clone from. |
| `docs` | This documentation, served statically. Only started when `DOCS_HOST` is set. |

The app and all three workers run from the same image; only the command differs.

Check that everything is up:

```bash
docker compose -f docker-compose.prod.yaml ps
```

## 4. Create the first user

There is no seeded admin account. Create yours from the CLI:

```bash
docker compose -f docker-compose.prod.yaml exec app \
  bin/cli user:create you@example.com Ada Lovelace
```

The command prompts for a password, creates the user as **already confirmed**,
and gives them a default team — so you can log in immediately, without a
confirmation mail and therefore without SMTP configured.

::: warning Avoid --password on a shared machine
The command accepts `--password` for scripted installs, but the value then lands
in your shell history and is visible in the process list. Prefer the prompt.
:::

### Closing public sign-up

By default anyone who reaches your instance can register at `/register`. On a
private instance, set this in `.env` and restart the stack:

```dotenv
TRAGWERK_REGISTRATION_ENABLED=false
```

`/register` then redirects to `/login` and the register link disappears from the
UI. Accounts are created two ways instead: `bin/cli user:create` as above, or by
[inviting someone to a team](/app/teams) — invitation links keep working, and an
invited address that has no account yet gets one through the invite link.

## 5. Log in

Open `https://APP_HOST` and sign in. From here, follow
[Getting Started](/guide/getting-started) to register your first deploy target
and ship an application.

::: tip Secure the account
Enable [two-factor authentication](/app/two-factor) straight away. This account
can add servers and read every project's secrets.
:::

## Environment reference

Every variable read from `.env`.

| Variable | Required | Default | Purpose |
|---|---|---|---|
| `APP_HOST` | yes | — | Public hostname of the web UI. Traefik routes on it and requests a certificate for it. |
| `DOCS_HOST` | no | empty | Public hostname of the bundled documentation. Empty means no documentation container is started and no certificate is requested for it. |
| `COMPOSE_PROFILES` | no | `docs` | Leave as-is. Enables nothing by itself; it only lets the optional `docs` service start once `DOCS_HOST` is set. |
| `ACME_EMAIL` | yes | — | Contact address Let's Encrypt registers, used for expiry notices. |
| `TRAGWERK_DATABASE_PASSWORD` | yes | — | Password for the bundled PostgreSQL. |
| `MERCURE_PUBLISHER_JWT_SECRET` | yes | — | Signs the tokens the app uses to publish live updates. |
| `MERCURE_SUBSCRIBER_JWT_SECRET` | yes | — | Signs the tokens browsers use to subscribe to them. |
| `TWO_FACTOR_KEY` | yes | — | Encrypts users' TOTP secrets at rest. Base64, 32 bytes. |
| `CREDENTIAL_ENCRYPTION_KEY` | yes | — | Encrypts stored SSH private keys at rest. Base64, 32 bytes. |
| `MERCURE_TOPIC_BASE` | no | `https://tragwerk.build` | Must match your public app URL, otherwise live updates never reach the browser. |
| `APP_IMAGE`, `SSHD_IMAGE`, `DOCS_IMAGE` | no | `:latest` from ghcr.io | Which images to run. During the beta `:latest` is the only tag published. See [Upgrades](/install/upgrades). |
| `TRAGWERK_DATABASE_HOST`, `_PORT`, `_USER`, `_DATABASE` | no | the bundled `db` | Override only to point at an external PostgreSQL. |
| `SSH_PORT` | no | `2222` | Host port for the git push target. |
| `TRAGWERK_SSH_HOST` | no | `APP_HOST` | Hostname shown in the git clone URL on the project page. |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD` | no | empty | Outgoing mail. See below. |
| `TRAGWERK_REGISTRATION_ENABLED` | no | `true` | Public sign-up at `/register`. Set to `false` for a private instance. See step 4. |
| `LOG_LEVEL` | no | `error` | PSR log level. |
| `FRANKENPHP_NUM_WORKERS` | no | `4` | Worker threads for the web server. |
| `TZ` | no | `UTC` | Timezone for every container. |

::: info Mail is optional, but some flows need it
Without `SMTP_*`, Tragwerk runs fine and you can create users from the CLI as in
step 4. What stops working is anything that mails a link: web self-registration,
email confirmation, and password reset. Configure SMTP before inviting people
who are expected to sign themselves up.
:::

## Troubleshooting

**No certificate, or TLS errors.** DNS does not point at this server, or port 80
or 443 is blocked. Check with `docker compose -f docker-compose.prod.yaml logs
traefik`. Note that Let's Encrypt rate-limits repeated failures, so fix the cause
before retrying.

**`migrate` fails, and the app never starts.** The app and workers deliberately
wait for a successful migration. Read `docker compose -f docker-compose.prod.yaml
logs migrate` — usually the database is unreachable. Fix it and run `up -d`
again.

**The app returns 502.** The app container is unhealthy. `docker compose -f
docker-compose.prod.yaml logs app`.

**Port 80 is already in use.** Another web server or container on the host holds
it. Find it with `ss -ltnp | grep :80`.

**Deploys stay pending.** The queue worker is not running. Check `docker compose
-f docker-compose.prod.yaml ps` and the `worker-queue` logs.

## Related

- [Requirements](/install/requirements)
- [Upgrades](/install/upgrades)
- [Backup & Restore](/install/backup)
- [Getting Started](/guide/getting-started)
