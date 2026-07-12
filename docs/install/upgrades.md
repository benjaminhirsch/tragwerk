# Upgrades

Upgrading Tragwerk means pulling newer images and recreating the containers.
There is no manual migration step: the one-shot `migrate` service runs on every
start, is idempotent, and the app and workers are gated on it finishing
successfully — so the schema is always applied before any code that depends on
it runs.

## Upgrading

```bash
cd tragwerk
git pull                                          # updates the Compose file
docker compose -f docker-compose.prod.yaml pull   # fetches the new images
docker compose -f docker-compose.prod.yaml up -d
```

`git pull` only refreshes `docker-compose.prod.yaml` and `.env.prod.dist`; the
application code arrives with the images. Your `.env` is untracked and stays as
it is — but compare it against `.env.prod.dist` after pulling, in case a release
introduced a new variable.

Expect a short interruption while the containers are recreated. Deploy jobs that
were mid-flight are re-queued and picked up again by the restarted worker.

## Pin a version

::: warning During the beta there is nothing to pin
Tragwerk has no releases yet. Every change that lands on the `main` branch
produces a new image, and `:latest` is the only tag that exists — so a `pull`
always gives you the newest build, and there is no older tag to fall back to.
Treat the beta accordingly: back up before you upgrade, and expect to move
forward rather than sideways.

This changes once Tragwerk is released. The rest of this section describes how
pinning will work then; it does not apply yet.
:::

Once versioned releases exist, do not track `:latest` in production. `:latest`
moves whenever CI publishes a build, which means a routine `pull` can hand you a
release whose notes you have not read. Pin the tag in `.env` instead:

```ini
APP_IMAGE=ghcr.io/benjaminhirsch/tragwerk:v1.4.0
SSHD_IMAGE=ghcr.io/benjaminhirsch/tragwerk-sshd:v1.4.0
DOCS_IMAGE=ghcr.io/benjaminhirsch/tragwerk-docs:v1.4.0
```

Upgrading is then a deliberate act: edit the tag, `pull`, `up -d`. You can also
pin a digest (`@sha256:…`) if you want the image to be byte-for-byte immutable —
that already works today, and is the one way to hold a specific beta build.

## Rolling back

Point `APP_IMAGE` at the build you want to return to and bring the stack back up:

```bash
docker compose -f docker-compose.prod.yaml up -d
```

During the beta that means a digest, since no older tag exists. Note the digest
you are running (`docker compose -f docker-compose.prod.yaml images`) **before**
you upgrade — afterwards, the old one is no longer discoverable from `:latest`.

::: warning A rollback does not undo migrations
The `migrate` service only ever applies migrations forward. If the release you
are leaving changed the schema in a way the older code cannot read, the older
code will fail against the newer database. Restore the database from a backup
taken before the upgrade — which is the reason to take one first. See
[Backup & Restore](/install/backup).
:::

## Related

- [Install with Docker Compose](/install/docker-compose)
- [Backup & Restore](/install/backup)
