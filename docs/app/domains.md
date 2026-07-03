# Domains & SSL

Domains connect a custom hostname to one of your project's environments. Traefik
routes incoming requests by hostname and issues TLS certificates automatically,
so every domain is served over HTTPS without manual certificate handling.

## How domains map to your config

A domain is attached to a **routing placeholder** rather than directly to a
container. In `.tragwerk/config.xml` your [routes](/config/routes) reference
placeholders like `{default}`, `{frontend}` or `{api}`; a domain you add here
fills the matching placeholder. This keeps the XML portable while the concrete
hostnames live in the WebUI.

## Add a domain

1. Open **Domains** and click **Add domain**.
2. Enter the **Domain** — hostname only, without `https://`
   (e.g. `shop.my-company.com`).
3. Optionally enable **Wildcard domain**. With a wildcard, each environment gets
   an auto-derived subdomain (e.g. `feature-x.preview.my-company.com`) as long as
   no explicit domain is assigned to the same placeholder.
4. Set the **Target placeholder** (defaults to `default`) to the placeholder
   from your config, such as `{default}`.
5. Click **Add domain**.

::: tip DNS
Point a CNAME, A and or AAAA record for the hostname at your server. Once DNS resolves,
Traefik requests a Let's Encrypt certificate automatically — no extra steps.
:::

## Set the primary domain

Exactly **one** primary domain is allowed per project. It is used for absolute
links and redirects. In the Domains table, click **Set as primary** on the
domain you want; the **Primary** badge moves to it.

## Delete a domain

Click the trash icon next to a domain and confirm. The hostname stops being
routed to the project. If you remove the primary domain, another domain is
promoted to primary automatically.

## Automatic SSL

TLS is handled by **Traefik** with **Let's Encrypt**. Certificates are issued
and renewed automatically for every domain that resolves to the server; you do
not upload or manage certificates yourself.

## Related

- [Routes configuration](/config/routes)
- [Environments](/app/environments)
- [Architecture on the host](/server/architecture-on-host)
