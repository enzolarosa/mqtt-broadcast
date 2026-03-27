# Dashboard Frontend

## What It Does

The MQTT Broadcast Dashboard is a web-based monitoring interface that gives operators real-time visibility into the state of all MQTT broker connections, message flow, and system health. It runs entirely in the browser and automatically refreshes every 5 seconds without requiring a page reload.

## User Journey

1. The user navigates to the dashboard URL (default: `/mqtt-broadcast`).
2. The page loads and immediately displays the **Dashboard** tab with five summary cards: messages per minute, active brokers, memory usage, queue depth, and failed job count.
3. Below the cards, a **throughput chart** shows message volume over the past hour as a line graph.
4. Further down, a **broker table** lists each connected broker with its status (Connected, Idle, Reconnecting, Disconnected), uptime, and 24-hour message count.
5. If message logging is enabled, a **message feed** shows the 20 most recent messages with broker name, topic, message preview, and relative timestamp ("2 minutes ago").
6. The user clicks the **Failed Jobs** tab to see jobs that could not be delivered. Each entry shows the broker, topic, error preview, and retry count.
7. From the Failed Jobs view, the user can:
   - **Retry** an individual job (retry button per row).
   - **Delete** an individual job.
   - **Retry All** failed jobs at once.
   - **Flush All** failed jobs (with a confirmation dialog).
8. The user clicks the **Docs** tab for quick reference: common Artisan commands, troubleshooting tips for connection issues, a configuration checklist, and links to external documentation.
9. The user can toggle between **dark mode** and **light mode** using the sun/moon icon in the header. The preference is remembered across sessions.

## Business Rules

- The dashboard polls the backend API at a fixed interval (5 seconds by default). There is no manual refresh button — data updates automatically.
- The **Message Log** section is only visible when message logging is enabled in the package configuration. If disabled, the section is completely hidden.
- The **Flush All** action in Failed Jobs requires explicit user confirmation via a browser dialog before executing. This prevents accidental data loss.
- Individual job retries show a per-job loading spinner. The retry button is disabled while the retry is in progress to prevent duplicate dispatches.
- Bulk actions (Retry All, Flush All) disable both buttons while processing to prevent concurrent bulk operations.
- The system status badge in the header shows **Running** (green) or **Stopped** (red) based on whether the supervisor process is active.
- Memory usage card changes color based on thresholds: green (normal), yellow (above 80%), red (above 100% of configured threshold).
- Queue pending card turns yellow when pending jobs exceed 100.
- Failed jobs count in the navigation tab shows a red badge with the count when there are failures.

## Edge Cases

- **API unreachable**: each dashboard section independently shows a "Failed to load" message. Previously loaded data is preserved — the dashboard does not blank out.
- **No brokers running**: the broker table shows "No active brokers" with an empty state.
- **No messages logged**: the message feed shows "No messages yet".
- **No failed jobs**: the Failed Jobs tab shows an empty state with a muted icon. Bulk action buttons are hidden.
- **JavaScript disabled**: a `<noscript>` fallback displays a message asking the user to enable JavaScript.
- **Theme preference lost**: if `localStorage` is cleared, the theme defaults to the system preference (dark or light based on OS setting).

## Permissions & Access

- Access to the dashboard is controlled by the `Authorize` middleware configured in `config/mqtt-broadcast.php`. By default, the dashboard is only accessible in `local` environment.
- The `viewMqttBroadcast` Gate can be defined in the application's `AuthServiceProvider` to control access in non-local environments.
- All API endpoints used by the frontend are protected by the same authorization middleware — there is no unauthenticated API access.
- The dashboard is read-only except for the Failed Jobs tab, which allows retry and delete operations.
