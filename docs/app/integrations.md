# Integrations & Webhooks

Integrations connect your Git hosting provider (forge) to Tragwerk so that a
`git push` automatically triggers a build and deployment. Each project has its
own webhook integration with a unique signing secret.

## Creating a webhook integration

Open **Integrations** for the active project and choose **Create integration**.
You select the Git forge that hosts the repository; Tragwerk generates a random
signing secret and exposes a forge-specific receiver endpoint.

::: info
Only **one** webhook integration may exist per project at a time. Delete the
existing integration before creating a new one for a different forge.
:::

Supported forges:

| Forge     | Route slug |
| --------- | ---------- |
| GitHub    | `github`   |
| GitLab    | `gitlab`   |
| Forgejo   | `forgejo`  |
| Gitea     | `gitea`    |
| Codeberg  | `codeberg` |
| Bitbucket | `bitbucket`|

After creating the integration, copy the endpoint URL and secret into your
forge's webhook settings (push events only).

### Forge endpoint

Each integration is served at:

```
POST /webhooks/{forge}/{projectId}
```

For example, a GitHub integration for project `abc123` is reached at
`https://your-tragwerk-host/webhooks/github/abc123`. The handler:

1. Resolves the forge from the route slug and loads the project.
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

## Generic git-push receiver

Tragwerk also exposes a forge-agnostic receiver used internally and for custom
setups:

```
POST /webhooks/git-push
```

It expects a JSON/form body with `projectId`, `branch`, and `newSha`. A `newSha`
of all zeros (`0000…0000`) signals a deleted branch and removes the
corresponding environment; any other SHA dispatches a build for that branch.

```json
{
  "projectId": "abc123",
  "branch": "main",
  "newSha": "a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2"
}
```

## Internal git-auth endpoint

The server-side Git hosting integration uses an internal endpoint to
authenticate Git operations between the VPS and Tragwerk:

```
POST /internal/git-auth
```

This endpoint is for server-to-server communication only and is not part of the
end-user workflow. It does not require an authenticated user session.

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
