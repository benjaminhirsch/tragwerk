# Integrations & Webhooks

Integrations connect your version control system (VCS) to Tragwerk so that a
`git push` automatically triggers a build and deployment. Each project has its
own webhook integration with a unique signing secret.

## Creating a webhook integration

Open **Integrations** for the active project and choose **Create integration**.
You select the VCS provider that hosts the repository; Tragwerk
generates a random signing secret and exposes a VCS-specific receiver endpoint.

::: info
Only **one** webhook integration may exist per project at a time. Delete the
existing integration before creating a new one.
:::

Supported version control system providers:

| Provider  | Route slug |
| --------- | ---------- |
| GitHub    | `github`   |
| GitLab    | `gitlab`   |
| Forgejo   | `forgejo`  |
| Gitea     | `gitea`    |
| Codeberg  | `codeberg` |
| Bitbucket | `bitbucket`|

After creating the integration, copy the endpoint URL and secret into your
VCS provider's webhook settings (push events only).

### VCS endpoint

Each integration is served at:

```
POST /webhooks/{forge}/{projectId}
```

For example, a GitHub integration for project `abc123` is reached at
`https://your-tragwerk-host/webhooks/github/abc123`. The handler:

1. Resolves the VCS provider from the route slug and loads the project.
2. Looks up the project's stored integration secret.
3. Verifies the request signature against that secret (returns `401` on
   mismatch).
4. Extracts the push payload (branch + commit SHA).
5. Dispatches a build for that branch, or — when the push deletes a branch —
   tears down the matching [environment](/app/environments).

### Deleting an integration

On the **Integrations** page, use **Delete** next to the integration. This
removes the secret and disables the receiver endpoint; existing deployments are
not affected.

## Example: wiring up GitHub

1. In Tragwerk, open **Integrations** and create a **GitHub** integration.
2. Copy the endpoint URL (`/webhooks/github/{projectId}`) and the generated
   secret.
3. In GitHub, go to **Settings → Webhooks → Add webhook**.
4. Set the **Payload URL** to the Tragwerk endpoint, **Content type** to
   `application/json`, paste the **Secret**, and select **Just the push event**.
5. Push a commit — Tragwerk verifies the signature and starts a
   [deployment](/app/deployments).

## Related

- [Projects](/app/projects)
- [Environments](/app/environments)
- [Deployments](/app/deployments)
