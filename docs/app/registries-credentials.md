# Registries & Credentials

Registries and credentials are team-level resources that Tragwerk uses to reach
external systems: **Docker registries** for pulling private images, and
**credentials** for connecting to your servers over SSH.

## Docker registries

A registry entry lets Tragwerk authenticate against a private Docker registry so
it can pull images for your builds and deployments.

### Managing registries

From the **Registries** page you can **create**, **edit**, and **delete**
registry entries. Each entry holds:

| Field        | Description                                                       |
| ------------ | --------------------------------------------------------------- |
| Name         | A label for the registry within Tragwerk.                       |
| URL          | The registry host (e.g. `registry.example.com` or a hub URL).   |
| Repository   | The registry prefix / repository path used for images.          |
| Username     | Login user for the registry.                                    |
| Password     | Login secret / token for the registry.                          |
| Pruning      | Optionally prune old image tags, keeping the most recent N.     |

When pruning is enabled you set how many tags to keep (default 10); Tragwerk can
then remove older tags to control storage use.

### Example

To pull from a private GitHub Container Registry:

1. Open **Registries → Create**.
2. **URL**: `ghcr.io`
3. **Repository**: `your-org/your-app`
4. **Username** / **Password**: your registry login (a PAT with `read:packages`).
5. Save. The registry prefix is now available when building and deploying images.

## Credentials

Credentials store the server-access details Tragwerk uses to reach your servers
over SSH when provisioning, deploying, and reading container state.

### Managing credentials

From the **Credentials** page you can **create**, **edit**, and **delete**
credentials. Each credential holds a name, a username, and an **SSH private
key**.

::: warning
The private key must be a valid PEM-encoded SSH private key — Tragwerk validates
it on save and rejects malformed keys.
:::

::: tip Encrypted at rest
The private key is **encrypted before it is stored** and only ever decrypted in
memory for the moment a connection is established — it is never persisted in
plaintext. Because that key for encryption lives outside the database, a 
leaked `credentials`table is useless without it. 
:::

### Example

To register an SSH key for a server:

1. Open **Credentials → Create**.
2. **Name**: `prod-server`
3. **Username**: `deploy`
4. **Private key**: paste the contents of your PEM-encoded private key
   (e.g. `~/.ssh/id_ed25519`).
5. Save, then attach the credential when adding a [server](/server/servers).

## Related

- [Servers](/server/servers)
- [Server setup](/server/server-setup)
- [Deployments](/app/deployments)
- [Teams](/app/teams)
