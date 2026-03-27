# Dashboard & Monitoring

## What It Does

The MQTT Broadcast system includes a built-in monitoring dashboard and health check API that provides real-time visibility into the system's operational status. Operators can view broker statuses, message throughput, memory usage, and queue depth — all from a single web interface or via REST API calls for integration with external monitoring tools.

## User Journey

1. The operator navigates to the dashboard URL (e.g., `https://app.example.com/mqtt-broadcast`).
2. The system checks authorization — in local environments, access is granted automatically; in production, the operator must be explicitly allowed through a permission gate.
3. The dashboard loads and displays an overview panel showing: system status (running/stopped), active broker count, messages per minute, memory usage, and uptime.
4. The operator can drill down into individual brokers to see connection status, last heartbeat time, process ID, and recent messages.
5. The operator can view message logs with filtering by broker or topic, and inspect individual message payloads.
6. Throughput charts show message volume trends over the last hour (by minute), last 24 hours (by hour), or last 7 days (by day).
7. External monitoring tools can poll the health check endpoint to determine if the system is healthy (HTTP 200) or unhealthy (HTTP 503).

## Business Rules

- **Authorization**: In local environments, all users have access. In production, only users explicitly authorized through the `viewMqttBroadcast` permission gate can access the dashboard and API.
- **Broker staleness**: A broker is considered "stale" if its last heartbeat is older than 2 minutes. The system continues to display stale brokers but marks them accordingly.
- **Connection status levels**: Each broker has one of four statuses — connected (active and working), idle (active but paused), reconnecting (heartbeat becoming stale), or disconnected (no heartbeat for over 2 minutes).
- **Health criteria**: The system is "healthy" when at least one broker is active AND the master supervisor process is running. If either condition fails, the health endpoint returns an unhealthy status.
- **Memory thresholds**: Memory usage is compared against a configured threshold. Below 80% is normal, 80–99% triggers a warning, and 100%+ is critical.
- **Message logging dependency**: Message logs, topic analytics, and throughput metrics are only available when message logging is explicitly enabled in configuration. When disabled, these sections show empty results with a clear indicator.
- **Message limits**: Message log queries are capped at 100 results per request (default 30) to prevent excessive database load.
- **Topic analytics**: Only messages from the last 24 hours are considered for topic ranking, limited to the top 20 topics.

## Edge Cases

- **No brokers registered**: Dashboard shows "stopped" status with zero counts. Health endpoint returns 503.
- **Master supervisor not running**: Health endpoint returns 503 even if brokers are active. Memory and uptime show as zero.
- **Logging disabled**: Message log, topic, and metrics sections return empty datasets with a metadata flag indicating logging is disabled. No errors are raised.
- **Broker process crashed**: The broker remains in the database but its heartbeat becomes stale. It transitions through "reconnecting" to "disconnected" status over ~2 minutes.
- **Non-JSON messages**: Messages that are not valid JSON are displayed as raw text. The detail view indicates whether the message is JSON or not.
- **Very long messages**: Message previews are truncated to 100 characters in list views. Full content is available in the detail view.

## Permissions & Access

- **Local environment**: All authenticated and unauthenticated users can access the dashboard and API without restriction.
- **Non-local environments**: Access requires passing the `viewMqttBroadcast` gate. By default, this gate denies all access — the application administrator must explicitly define which users are authorized.
- **Customization**: Authorization is configured by the application developer in their published service provider, typically by checking the user's email address or role.
- **Middleware stack**: The dashboard routes use the `web` middleware group by default, meaning standard session authentication applies before the gate check.
