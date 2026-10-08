# Alloy

[Grafana Alloy](https://grafana.com/oss/alloy/) for [my-sites-ide](https://github.com/yiendos/my-sites-ide):
the monitoring stack's collector. It ships every IDE container's logs to Loki, and is the one
address your apps send OpenTelemetry to - `alloy:4318` - passing traces to Tempo, logs to Loki and
metrics to Prometheus. Its UI is at http://localhost:12345.

Written for: developers running sites in my-sites-ide who want their sites' logs and telemetry
collected without wiring each app to each backend.

## Contents

- [Installation](#installation)
- [Pipelines](#pipelines)
- [Sending OpenTelemetry from your apps](#sending-opentelemetry-from-your-apps)
- [Command reference](#command-reference)
- [Configuration](#configuration)
- [What it uses from the IDE](#what-it-uses-from-the-ide)
- [Troubleshooting](#troubleshooting)
- [Known gaps](#known-gaps)

## Installation

A [my-sites-ide](https://github.com/yiendos/my-sites-ide) plugin. Add it to the `require` section
of the IDE's `composer.local.json` - or add
[`yiendos/my-sites-ide-preset-monitoring`](https://github.com/yiendos/my-sites-ide-preset-monitoring)
instead, for the whole monitoring stack:

```json
"yiendos/my-sites-ide-monitoring-alloy": "@dev"
```

Then, from the IDE root:

```
composer update
php my-sites-ide monitoring:alloy-start
```

Alloy is opt-in: it doesn't autostart. Start it with `monitoring:alloy-start`, which writes its
config first.

## Pipelines

`monitoring:alloy-start` reads the IDE's plugin list (`_dev/cache/plugins.php`) and writes
`storage/plugins/alloy/conf/config.alloy` from the package's `stubs/*.alloy`:

| Installed | Pipeline |
|---|---|
| always | OTLP receiver on `alloy:4317` (gRPC) and `alloy:4318` (HTTP), batched |
| [tempo](https://github.com/yiendos/my-sites-ide-monitoring-tempo) | OTLP traces -> `tempo:4317` |
| [loki](https://github.com/yiendos/my-sites-ide-monitoring-loki) | OTLP logs -> Loki's OTLP endpoint (keeping `trace_id`), and every container on the IDE's network -> Loki, labelled `service_name`, `container` and `compose_project` |
| [prometheus](https://github.com/yiendos/my-sites-ide-monitoring-prometheus) | OTLP metrics -> Prometheus's remote-write receiver |

A signal with nowhere to go is dropped, so apps can point at `alloy:4318` whatever's installed.
Run `monitoring:alloy-start` again after adding or removing a monitoring plugin.

Container logs come from the Docker socket, limited to the IDE's own network
(`<project>_my-sites-ide`, read from `docker compose config`), so a second IDE checkout's containers
aren't collected.

## Sending OpenTelemetry from your apps

Any OpenTelemetry SDK reads these:

```
OTEL_EXPORTER_OTLP_ENDPOINT=http://alloy:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_SERVICE_NAME=<your site>
```

For PHP sites, the
[php plugin](https://github.com/yiendos/my-sites-ide-preprocessors-php) has an opt-in OpenTelemetry
setup (yiendos/my-sites-ide-preprocessors-php#3).

## Command reference

| Command | What it does |
|---|---|
| `monitoring:alloy-start` | Writes the config for the monitoring plugins installed, then `docker compose up -d --build alloy`. Restarts a running Alloy when the config changed, and recreates one whose compose config changed (e.g. a new `ALLOY_PORT`) |
| `monitoring:alloy-stop` | `docker compose stop alloy`, leaving the rest of the IDE running |

## Configuration

| Variable | Default | What it does |
|---|---|---|
| `ALLOY_PORT` | `12345` (this plugin's `.env`) | The host port for Alloy's UI |
| `DOCKER_SOCKET_GID` | found for you | The group that owns the Docker socket - see [Troubleshooting](#troubleshooting) |

Set either in the IDE's root `.env`, which wins over the plugin's default, then run
`monitoring:alloy-start`. `php my-sites-ide ide:plugin-env yiendos/my-sites-ide-monitoring-alloy`
copies them in, commented out.

## What it uses from the IDE

| From the IDE | Used for |
|---|---|
| `NAMESPACE` (root `.env`) | the image name, `${NAMESPACE}_alloy` |
| `IDE_ROOT` (set by the CLI and `_dev/cache/ide.env`) | finding the plugin list and storage |
| `_dev/cache/plugins.php` | which monitoring plugins are installed |
| `docker compose config` | the IDE network's Docker name |
| `storage/plugins/alloy/` (`"storage": true`) | the config, log positions and WAL |
| the `my-sites-ide` network | receiving OTLP, sending to Tempo, Loki and Prometheus |
| the Docker socket, read-only | finding containers and reading their logs |

The container carries `prometheus.io/scrape` labels, so the prometheus plugin scrapes Alloy's own
metrics.

## Troubleshooting

**Something isn't arriving.** http://localhost:12345 shows every component in the pipeline and
whether it's healthy - an unhealthy exporter usually means its destination isn't running.

**`permission denied` on `/var/run/docker.sock` in the logs.** Alloy runs as its `alloy` user and
joins the socket's group: root (`0`) under Docker Desktop, the host's `docker` group on Linux,
which `monitoring:alloy-start` looks up. If yours differs, set `DOCKER_SOCKET_GID` to the socket's
group id (`stat -c %g /var/run/docker.sock`) and start it again.

**A new container's logs are missing.** Alloy looks for containers every 15 seconds, and only on
the IDE's network.

## Known gaps

- Read-only or not, access to the Docker socket is root-equivalent on the Docker host. Fine for a
  local IDE; don't run this anywhere shared.
- No OTLP host ports, so apps on the host can't send - only containers in the IDE.
- On Linux hosts, `storage/plugins/alloy/` is created by your user while Alloy runs as uid 473, so it
  may not be able to write there. Docker Desktop on macOS maps ownership, so it isn't affected.
