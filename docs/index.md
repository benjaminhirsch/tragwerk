---
layout: home

hero:
  name: Tragwerk
  text: PHP hosting on your own server
  tagline: A single XML file generates your whole Docker stack — FrankenPHP (a drop-in PHP-FPM replacement, with optional worker mode), Traefik routing, automatic SSL. Host as many PHP apps as you like on any server with SSH.
  image:
    src: /logo.svg
    alt: Tragwerk
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: What is Tragwerk?
      link: /guide/introduction

features:
  - title: XML-driven config
    details: Commit a single .tragwerk/config.xml to your repo. Tragwerk generates the entire Docker and docker-compose setup from it — no YAML! You're wondering, "Why XML?" Because you get autocompletion and validation for free.
  - title: FrankenPHP runtime
    details: Apps run on FrankenPHP — by default a drop-in PHP-FPM replacement, with optional worker mode for high throughput. PHP 8.x runtimes are selected per application in your config.
  - title: Branch-based environments
    details: Every git branch becomes an isolated environment with its own containers, services, and domains. Push a branch, get an environment.
  - title: Backing services
    details: Declare databases and caches (PostgreSQL, MySQL, Redis, and more) as services and bind them to applications through relationships.
  - title: Auto SSL via Traefik
    details: Traefik fronts every app as a reverse proxy and provisions Let's Encrypt certificates automatically, so multiple apps share one server over HTTPS.
  - title: Self-hosted on any server
    details: Bring your own server. Register a server, run the automated setup job, and Tragwerk turns it into a Docker host ready to deploy to.
---

Tragwerk is a self-hosted platform for deploying PHP applications to your own
server. You describe your application once in XML; Tragwerk generates the Docker
configuration, builds your images, starts the containers, and routes traffic
through Traefik with automatic HTTPS.

New here? Start with [What is Tragwerk?](/guide/introduction), learn the
[core concepts](/guide/concepts), then follow the
[getting started guide](/guide/getting-started).
