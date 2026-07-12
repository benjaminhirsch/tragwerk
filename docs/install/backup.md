# Backup & Restore

All of Tragwerk's state lives in named Docker volumes plus your `.env`. Nothing
of value is stored in the containers themselves, so a backup is a copy of those
volumes and that file.

## What holds state

| Volume | Contents | Back up |
|---|---|---|
| `db-data` | PostgreSQL: users, teams, projects, servers, credentials, deploy history. | Yes |
| `data-repositories` | The bare git repositories your projects push to. | Yes |
| `data-ssh` | The SSH host keys and `authorized_keys` of the git push target. | Yes |
| `traefik-acme` | Issued TLS certificates. | Optional — they are re-requested automatically. |
| `data-logging` | Application logs. | Optional |
| `data-project` | Generated Docker and Compose files per deploy. | No — regenerated on the next deploy. |
| `data-cache` | Config cache. | No — rebuilt on start. |

::: warning The `.env` is part of the backup
`CREDENTIAL_ENCRYPTION_KEY` and `TWO_FACTOR_KEY` live only in `.env`, and the
database stores SSH private keys and 2FA secrets encrypted with them. A database
dump restored without the matching keys leaves you with credentials nobody can
decrypt — including you. Store the `.env` with the same care as the dump itself,
and ideally somewhere separate from it.
:::

## Backing up

Dump the database:

```bash
docker compose -f docker-compose.prod.yaml exec -T db \
  pg_dump -U app app > tragwerk-$(date +%F).sql
```

Archive the repositories and SSH state. These are volumes, not host paths, so
read them through a throwaway container:

```bash
docker run --rm \
  -v tragwerk-prod_data-repositories:/data:ro \
  -v "$PWD":/backup \
  alpine tar czf /backup/repositories-$(date +%F).tar.gz -C /data .

docker run --rm \
  -v tragwerk-prod_data-ssh:/data:ro \
  -v "$PWD":/backup \
  alpine tar czf /backup/ssh-$(date +%F).tar.gz -C /data .
```

The `tragwerk-prod_` prefix comes from the Compose project name. Confirm the
actual names with `docker volume ls`.

Finally, copy the `.env`.

## Restoring

On a fresh machine, clone the repository, restore the `.env`, then bring up only
the database so nothing writes to it while you load the dump:

```bash
docker compose -f docker-compose.prod.yaml up -d db
cat tragwerk-2026-07-12.sql | docker compose -f docker-compose.prod.yaml exec -T db \
  psql -U app app
```

Unpack the volume archives the same way you created them, with `tar xzf` and the
mount not read-only. Then start the rest of the stack:

```bash
docker compose -f docker-compose.prod.yaml up -d
```

The `migrate` service brings the restored schema up to the current version if the
dump came from an older release.

## Stopping without losing data

```bash
docker compose -f docker-compose.prod.yaml down
```

This stops and removes the containers. The volumes — and therefore all your data
— survive, and `up -d` brings everything back.

::: warning `down -v` deletes everything
Adding `-v` removes the named volumes: the database, your git repositories, and
the SSH keys. There is no undo. Only use it when you intend to destroy the
instance.
:::

## Related

- [Install with Docker Compose](/install/docker-compose)
- [Upgrades](/install/upgrades)
