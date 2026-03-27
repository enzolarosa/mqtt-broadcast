# Memory Management

## What It Does

The memory management system prevents MQTT supervisor processes from consuming too much server memory. It automatically monitors memory usage, warns operators when usage is high, and can restart processes before they crash due to memory exhaustion.

This is critical for production deployments where MQTT supervisors run continuously for days or weeks. Without memory management, small memory leaks would eventually cause the process to crash, interrupting all MQTT connections.

## User Journey

1. Administrator configures memory limits in `.env` (e.g., `MQTT_MEMORY_THRESHOLD_MB=128`)
2. The MQTT supervisor starts and begins monitoring its own memory usage
3. During normal operation, the system periodically cleans up unused memory (garbage collection)
4. If memory usage reaches 80% of the configured limit, a warning is logged
5. If memory usage exceeds 100% of the limit, an error is logged and a countdown begins
6. After the configured grace period (default: 10 seconds), the process restarts automatically
7. The external process manager (systemd, supervisord) starts a fresh process
8. All MQTT connections are re-established automatically by the new process

## Business Rules

- Memory is checked periodically, not on every loop iteration — the check interval is configurable
- The warning threshold is fixed at 80% of the configured memory limit
- The critical threshold is 100% of the configured memory limit
- A grace period allows in-progress operations to complete before restart
- If memory drops back below the limit during the grace period, the restart is cancelled
- Auto-restart can be disabled entirely — the system will still warn but never restart
- Memory monitoring can be disabled completely by setting the threshold to null
- Only the master supervisor can trigger restarts — individual broker supervisors only log warnings
- Peak memory usage is tracked and included in dashboard statistics

## Edge Cases

- **Memory spike then recovery**: If memory briefly exceeds the threshold but drops back during the grace period, the restart is cancelled and a recovery message is logged. No disruption occurs.
- **Auto-restart disabled**: Warnings and errors are logged indefinitely, but the process never restarts. The administrator must intervene manually.
- **No threshold configured**: All memory monitoring is disabled. Garbage collection still runs to prevent memory leaks, but no warnings or restarts occur.
- **Very short grace period**: Setting the grace period to 0 seconds causes immediate restart on threshold breach. This may interrupt in-progress MQTT publishes.
- **Process manager not configured**: If no external process manager (systemd, supervisord) is running, the auto-restart terminates the process but nothing restarts it. MQTT connections are lost until manual intervention.
- **Container memory limits**: If the container OOM-kills the process before the threshold is reached, the memory manager cannot help. Set the threshold below the container limit.

## Permissions & Access

- Memory configuration is set by the system administrator via environment variables or the config file
- Memory statistics are visible in the monitoring dashboard to any user with the `viewMqttBroadcast` gate permission
- The memory manager operates automatically — no user interaction is required during normal operation
- Restart decisions are made by the system automatically; they cannot be triggered manually through the dashboard
