FROM columbusinteractive/php-dev:8.5

RUN apt-get update && apt-get install -y gettext

COPY --from=ghcr.io/roadrunner-server/roadrunner /usr/bin/rr /usr/local/bin/rr

RUN install-php-extensions pcov

EXPOSE 80
CMD ["rr", "-c", ".rr.dev.yaml", "serve"]
