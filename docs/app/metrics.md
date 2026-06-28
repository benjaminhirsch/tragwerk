# Metrics

Tragwerk surfaces metrics at two scopes: **server metrics** for the VPS as a
whole, and **environment/app metrics** for a specific deployed application. Both
offer a live view and historical graphs.

## Server metrics

Server metrics cover the health of the VPS itself:

- **CPU** usage
- **Memory** usage
- **Disk** usage

Tragwerk samples each server over SSH in the background and stores the readings,
so live values and historical graphs populate automatically — no setup required
on your part.

## Environment / app metrics

Application metrics describe the running app within an
[environment](/app/environments) — aggregated FrankenPHP and Caddy figures
summed across the app's containers:

- **Worker pool** — total / busy / ready workers and queue depth
- **Threads** — total and busy
- **Requests** — total served, plus 4xx and 5xx counts
- **Latency** — average request duration (derived from sample deltas)
- **In-flight** — requests currently being processed

Gauges (workers, threads, in-flight) are point-in-time values; counters
(requests, durations) are cumulative since container start, so rates and average
latency are derived from the difference between consecutive samples.

The **worker pool** figures are most meaningful for apps running in
[worker mode](/config/applications#workermode). Apps in the default classic mode
(the PHP-FPM drop-in) still report request, latency, and thread metrics.

## Live updates and graphs

- **Live tiles** update in real time via **Mercure** (server-sent events). The
  page subscribes to a live endpoint and re-renders tiles as new samples
  arrive.
- **Historical graphs** are drawn with **uPlot** from data fetched on a data
  endpoint, letting you inspect trends over time.

## Example

Open **Metrics**, pick the active project and environment, and watch the live
worker and request tiles update while the graphs fill in history.

## Related

- [Environments](/app/environments)
- [Logs & Containers](/app/logs-containers)
- [Architecture on the host](/self-hosting/architecture-on-host)
