# Two-Factor Authentication

Two-factor authentication (2FA) adds a second step to every sign-in: after your
password, you enter a time-based one-time code (TOTP) from an authenticator app.
You manage it from the **Security** section of your
[Account settings](/app/account).

::: info Server requirement
The server must have the `TWO_FACTOR_KEY` environment variable set to a
base64-encoded 32-byte value. It is used to encrypt stored TOTP secrets at rest.
Without it, enrolment fails. See
[self-hosting requirements](/self-hosting/requirements).
:::

## Enable two-factor

1. Open **Account settings** and find the **Two-factor authentication** card. If
   it shows the **Inactive** badge, click **Set up two-factor**.
2. Scan the displayed **QR code** with a TOTP app such as 1Password, Authy or
   Google Authenticator. If you cannot scan, enter the shown secret manually.
3. Enter the **6-digit code** from your app and click **Enable**.
4. Tragwerk then shows your **recovery codes** — save them before continuing
   (see below).

Once enabled, the card shows an **Active** badge and the date 2FA was set up.

## Recovery codes

Recovery codes are one-time codes that let you sign in when you cannot reach your
authenticator app (for example, a lost phone).

- They are shown **only once**, right after you enable 2FA. Store them somewhere
  safe.
- **Each code can be used once.** The account card shows how many unused codes
  remain.
- To replace them, open the card and use **Manage codes** to generate a fresh
  set. Generating new codes invalidates the previous ones.

::: warning Save them immediately
You will not be able to view existing codes again. If you lose both your
authenticator app and your recovery codes, you will be locked out and need an
administrator to intervene on the server.
:::

## The login challenge

When 2FA is active, signing in becomes a two-step flow:

1. Enter your email and password as usual.
2. On the **Two-factor authentication** screen, enter the **6-digit code** from
   your app — or choose **Use a recovery code instead** and enter one of your
   recovery codes.
3. Optionally tick **Don't ask again on this device** to mark the current device
   as trusted for a number of days and skip the challenge during that window.

## Disable two-factor

1. In the **Two-factor authentication** card, click **Disable two-factor**.
2. Confirm with your **current password** in the dialog.

After disabling, your account is protected by password only — re-enable 2FA to
restore the second factor.

## Related

- [Account](/app/account)
- [Self-hosting requirements](/self-hosting/requirements)
