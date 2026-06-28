#!/bin/sh
#
# Production container entrypoint. Runs on every container start (web, workers
# and the one-shot migrate service) before handing off to the actual command.
set -e

# Best-effort wait for the database so workers/web don't crash-loop on boot.
# pg_isready isn't installed; a raw TCP connect via PHP is enough.
if [ -n "${TRAGWERK_DATABASE_HOST:-}" ]; then
    db_port="${TRAGWERK_DATABASE_PORT:-5432}"
    echo "Waiting for database ${TRAGWERK_DATABASE_HOST}:${db_port} ..."
    i=0
    while [ "$i" -lt 30 ]; do
        if php -r 'exit(@fsockopen(getenv("TRAGWERK_DATABASE_HOST"), (int) (getenv("TRAGWERK_DATABASE_PORT") ?: 5432), $e, $s, 2) ? 0 : 1);' 2>/dev/null; then
            break
        fi
        i=$((i + 1))
        sleep 1
    done
fi

# Drop any stale merged-config cache. It is regenerated on the first request
# with the runtime environment present (data/cache is a writable named volume).
php bin/clear-config-cache.php || true

exec "$@"
