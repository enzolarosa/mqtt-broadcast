# HTTP API Reference

## What It Does

The MQTT Broadcast dashboard includes a complete REST API that provides real-time system monitoring data. This API powers the web dashboard and can also be used by external tools for health monitoring, alerting, and integration with other systems.

The API provides six groups of functionality:

1. **Health checking** — Is the system running correctly?
2. **Dashboard statistics** — Summary of brokers, messages, memory, and queue status
3. **Broker management** — Detailed status of each MQTT broker connection
4. **Message logs** — Recent MQTT messages with search and filtering
5. **Throughput metrics** — Time-series data for performance charts
6. **Failed job management** — View, retry, and delete messages that failed to publish

## User Journey

### Monitoring System Health

1. An operator or monitoring tool sends a request to the health endpoint
2. The system checks whether brokers are active and the supervisor is running
3. A status of "healthy" (with HTTP 200) or "unhealthy" (with HTTP 503) is returned
4. Memory usage is checked against the configured threshold and reported as pass/warn/critical

### Viewing Dashboard Stats

1. The dashboard loads and requests aggregated statistics
2. The system returns broker counts, message throughput, queue size, memory usage, and failed job counts
3. The dashboard renders overview cards from this data

### Inspecting Brokers

1. The user views a list of all brokers with their connection status
2. Each broker shows whether it is connected, idle, reconnecting, or disconnected
3. The user can click a broker to see its recent messages (last 10)

### Searching Messages

1. The user opens the message log view
2. They can filter by broker name (exact match) or topic (partial match)
3. Messages are shown with a preview; clicking reveals the full content
4. A separate endpoint lists the top 20 most active topics

### Analyzing Throughput

1. The user views throughput charts showing message volume over time
2. Three time ranges are available: last hour (per-minute), last 24 hours (per-hour), last 7 days (per-day)
3. A summary endpoint provides totals and averages for each window, plus the peak minute

### Managing Failed Jobs

1. The user views a list of failed MQTT publish attempts
2. Each entry shows the broker, topic, a message preview, and the error summary
3. The user can retry a single job or retry all eligible jobs at once
4. The user can delete individual failed jobs or flush the entire queue
5. A 1-minute cooldown prevents the same job from being retried repeatedly

## Business Rules

- **Logging dependency**: message logs, topic analytics, and throughput metrics are only available when message logging is enabled in configuration. When disabled, these endpoints return empty data with a clear indicator.
- **Health criteria**: the system is considered healthy only when at least one broker has an active heartbeat AND the master supervisor process is found in cache.
- **Memory thresholds**: memory usage is reported as a percentage of the configured limit. Below 80% is "pass", 80–99% is "warn", 100%+ is "critical".
- **Connection status**: each broker's connection status is computed from heartbeat freshness — not stored. A heartbeat younger than 30 seconds means connected (or idle if paused); 30 seconds to 2 minutes means reconnecting; over 2 minutes means disconnected.
- **Failed job identification**: failed jobs are identified by a UUID, not a sequential number. This prevents information leakage about the total number of failures.
- **Retry cooldown**: when retrying all failed jobs, only jobs that have never been retried or whose last retry was more than 1 minute ago are included. This prevents flooding the queue with repeated retries.
- **Result limits**: all list endpoints cap results at 100 items per request, with a default of 30.
- **Message previews**: message content is truncated to 100 characters in list views. Full content is only available when viewing a single message or failed job.

## Edge Cases

- **Logging disabled**: message, topic, and metrics endpoints return empty arrays with `logging_enabled: false` rather than an error. The dashboard detects this and shows an appropriate message.
- **No brokers running**: health check returns HTTP 503. Dashboard stats show `status: "stopped"` with all zeros.
- **Master supervisor not found**: health check returns HTTP 503. Memory stats show 0 MB usage, 0% usage, and 0 uptime.
- **Flush all failed jobs**: uses table truncation (not row-by-row deletion) for performance. Returns the count of jobs that were deleted.
- **Retry a recently retried job**: the single-job retry endpoint has no cooldown — only "retry all" enforces the 1-minute cooldown. A single job can be retried immediately and repeatedly.
- **Non-JSON messages**: messages that are not valid JSON are returned as-is. The `is_json` field indicates whether the message could be parsed. Non-JSON messages still appear in logs and previews.
- **Empty topic search**: an empty `topic` filter parameter matches all topics (the LIKE pattern becomes `%%`).

## Permissions & Access

- **Local environment**: all API endpoints are accessible without authentication. This allows unrestricted development and debugging.
- **Production/staging environments**: access requires passing the `viewMqttBroadcast` authorization gate. The gate receives the current authenticated user.
- **Gate not defined**: if no gate is defined and the environment is not local, all access is denied (403 Forbidden).
- **Unauthenticated users**: in non-local environments, unauthenticated users receive a 403 response.
- **No per-endpoint permissions**: all endpoints share the same authorization check. There is no distinction between read-only and write access (e.g., retry/delete operations use the same gate as viewing).
