# Teams & Roles

Teams group your projects, environments and members. Every project belongs to a
team, and your **active team** determines which projects and resources you see.
Each member of a team has a role that controls what they may do.

## Create a team

1. Open **Teams** from the navigation.
2. Click **Create Team**.
3. Enter a name and save.

The creator becomes the team **Owner**.

## Switch the active team

If you belong to more than one team, switch the active team from the team
switcher in the topbar. All project, environment, domain and variable views are
scoped to the active team.

## The team overview

Opening a team shows its **overview**: a list of projects with live deploy
status, the most recent activity, and a **Settings** button (visible to members
who may edit the team).

## Members and invitations

### Invite members

1. Open the team and go to its **Members** area.
2. Enter one or more **email addresses** to invite.
3. Choose the **role** to grant (Admin or Member).
4. Send the invitations.

Each invitee receives an email with an invitation link.

### Accept an invitation

A new user opening the invitation link lands on a **Complete registration** page
with their email pre-filled. They set their name and password to create an
account and are added to the team automatically.

### Change a role or remove a member

From the Members area you can change a member's role or remove them from the
team. Both actions require the **ManageMembers** permission.

## Roles and permissions

Tragwerk ships three roles. **Owner** is conferred only at team creation (or via
explicit ownership transfer); when inviting or editing members you may grant
**Admin** or **Member**.

| Permission       | Owner | Admin | Member |
| ---------------- | :---: | :---: | :----: |
| ViewTeam         |  ✅   |  ✅   |   ✅   |
| EditTeam         |  ✅   |  ✅   |   —    |
| ManageMembers    |  ✅   |  ✅   |   —    |
| DeleteTeam       |  ✅   |  —    |   —    |

What each permission covers:

- **ViewTeam** — see the team, its projects and environments.
- **EditTeam** — change team settings (e.g. its name).
- **ManageMembers** — invite, remove and re-role members.
- **DeleteTeam** — permanently delete the team.

## Delete a team

Deleting a team is restricted to the **Owner** (DeleteTeam permission). Open the
team's settings and use the delete action; this removes the team and confirm the
action in the dialog.

::: warning
Deleting a team removes its association with its projects and resources. Make
sure nothing important depends on it first.
:::

## Related

- [Projects](/app/projects)
- [Account](/app/account)
- [Environments](/app/environments)
