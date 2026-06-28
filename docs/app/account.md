# Account

Your account settings hold your personal details, sign-in credentials and the
SSH keys Tragwerk uses for git access. Open them from the user menu in the
topbar, or go to **Account settings** directly. Everything lives on a single
page, split into Profile, Change password, SSH keys and Two-factor
authentication sections.

## Update your profile

The Profile card shows your avatar, full name and current email address.

1. Open **Account settings**.
2. Edit **First name**, **Last name** and/or **Email address**.
3. Click **Save profile**.

::: warning Email changes need confirmation
Changing your email address does not take effect immediately. Tragwerk sends a
confirmation link to the **new** address; the change is applied only after you
click it. Until then your existing address stays active.
:::

## Change your password

1. In the **Change password** card, enter your **Current password**.
2. Enter a **New password** and repeat it in **Confirm new password**.
3. Click **Update password**.

::: tip
Use at least 8 characters; a number and a symbol are recommended. If you have
lost access to your account entirely, use the password-reset link on the login
page instead.
:::

## SSH keys

Tragwerk uses your SSH public keys for **git access** — for example to pull from
or push to repositories that authenticate over SSH. Keys are listed by name and
the date they were added.

### Add a key

1. In the **SSH Keys** card, fill in a **Name** to recognise the key later
   (e.g. `laptop` or `ci-runner`).
2. Paste the **public key** — the full single line, starting with the key type:

   ```text
   ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI... you@host
   ```

3. Click **Add key**.

::: warning Public key only
Paste the contents of your `.pub` file (for example `~/.ssh/id_ed25519.pub`).
Never paste your private key.
:::

### Delete a key

Click the trash icon next to a key and confirm in the dialog. Deletion is
immediate and cannot be undone; remove keys you no longer use.

## Related

- [Two-Factor Authentication](/app/two-factor)
- [Teams & Roles](/app/teams)
- [Projects](/app/projects)
