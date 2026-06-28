# Deployments

A deployment runs the build-and-release pipeline for an
[environment](/app/environments): Tragwerk clones the branch, builds the Docker
image and starts the containers. Each run is a **deploy job** you can watch live
and revisit later in the deployment history.

## Deploy job statuses

| Status        | Meaning                                              |
| ------------- | --------------------------------------------------- |
| **Pending**   | The job is queued and waiting to start.             |
| **Running**   | The build/deploy is in progress.                    |
| **Completed** | The deployment finished successfully.               |
| **Failed**    | The deployment stopped with an error.               |

## What triggers a deployment

- **Git push webhook** — pushing to a branch that maps to an environment.
- **Manual redeploy** — the **Redeploy** button on an
  [environment](/app/environments) re-deploys the latest commit.

## The deployments view

Open **Deployments** for the active environment to see a two-pane view:

- On the left, the **history list** of build and deploy log entries. You can
  filter by type (all, build, deploy) and load older entries.
- On the right, the **terminal** showing the selected entry's output, with its
  status badge, commit, duration and timestamp, plus **Copy** and **Download**
  buttons.

## Live log streaming

While a job is **Pending** or **Running**, its terminal streams output in real
time, appending each line as it arrives with a blinking cursor while the job is
active.

## Example: follow a deploy after pushing

1. Push to a branch that has an environment, or click **Redeploy**.
2. Open the environment — the new job appears in the **Deployments** table with
   a **Pending**/**Running** badge.
3. Select it to open the terminal and watch the log stream until the badge turns
   **Completed** (or **Failed**).

## Related

- [Environments](/app/environments)
- [Projects](/app/projects)
- [Integrations](/app/integrations)
