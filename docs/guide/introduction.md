# Introduction

Tragwerk is a self-hosted platform for hosting PHP applications on your own
server. If you can get a server with SSH access from any provider,
you can run Tragwerk on it.

## The problem it solves

Deploying PHP to a server traditionally means wiring up a web server, a PHP
runtime, TLS certificates, databases, and a reverse proxy by hand — and then
doing it again for every app and every branch. Managed PaaS offerings remove
that toil but take away control and lock you into someone else's
infrastructure.

Tragwerk sits in the middle: you keep full ownership of the server, but the
platform automates the tedious parts. You own the server; Tragwerk makes hosting
PHP on it simple.

## How it works

The heart of Tragwerk is a single configuration file, `.tragwerk/config.xml`,
committed to your repository. Everything flows from it:

1. **XML config** — you describe your applications, their runtimes, web roots,
   backing services, routes, and more in `.tragwerk/config.xml`.
2. **Docker generation** — Tragwerk reads that XML and generates the complete
   Docker and docker-compose configuration. You never hand-write YAML.
3. **Build & run** — on push, Tragwerk builds your Docker image and starts the
   containers on your server. Applications run on FrankenPHP, by default as a
   drop-in PHP-FPM replacement, with optional worker mode for higher throughput.
4. **Traefik routing** — Traefik acts as the reverse proxy in front of every
   app, so multiple applications can share one server, each on its own domain with
   automatic Let's Encrypt TLS.

Deployments are **branch-based**: each git branch maps to one isolated
[environment](/app/environments) with its own containers, services, and
domains.

::: info Why XML?
Tragwerk's config is intentionally XML, validated against a schema. That gives
you autocomplete and validation in your editor, and a single unambiguous source
of truth that generates the underlying Docker setup. See the
[XML overview](/config/overview).
:::

## When to use it

Tragwerk is a good fit when you:

- already have (or want) your own server and prefer self-hosting over a managed PaaS;
- run one or more PHP applications and want per-branch preview environments;
- want declarative, version-controlled infrastructure without writing Docker by hand.

Tragwerk is deliberately narrow: it deploys PHP and nothing else, and it trades
control for automation. [How Tragwerk Compares](/guide/comparison) sets it
against a general-purpose platform like Coolify — including the cases where
Tragwerk is the wrong answer.

## Next steps

- Learn the vocabulary in [Core Concepts](/guide/concepts).
- Follow the [Getting Started](/guide/getting-started) walkthrough.
- Browse the [configuration reference](/config/overview).
