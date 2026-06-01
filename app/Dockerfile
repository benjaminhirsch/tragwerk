FROM dunglas/frankenphp:1.12.3-php8.5-trixie

WORKDIR /app

# System tools: git (BareRepository shells out to it), unzip (composer package extraction),
# gettext CLI (msgfmt etc.); the PHP ext-gettext is added below.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip gettext \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions: intl/gettext/pdo_pgsql are required by composer.json + Doctrine/Postgres.
# xdebug/pcov are dev/test tooling. install-php-extensions resolves the apt deps automatically.
RUN install-php-extensions \
        intl \
        gettext \
        pdo_pgsql \
        zip \
        opcache \
        xdebug \
        pcov

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Caddy/FrankenPHP run as the unprivileged host user (see docker-compose: user 1000:1000).
# Port 80 binding works via cap_net_bind_service on the frankenphp binary (set in the base image).
# Caddy still needs a writable autosave/lock location, so point the XDG dirs at writable paths.
ENV XDG_CONFIG_HOME=/tmp/caddy-config \
    XDG_DATA_HOME=/tmp/caddy-data

COPY Caddyfile.dev /etc/caddy/Caddyfile

EXPOSE 80
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
