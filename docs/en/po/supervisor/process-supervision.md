# Process Supervision

## What It Does

MQTT Broadcast runs as a background service that maintains persistent connections to one or more MQTT brokers. It automatically reconnects when connections drop, monitors its own health, and shuts down cleanly when asked. The system is designed to run 24/7 in production without manual intervention.

## User Journey

1. Administrator configures MQTT broker connections in the application config file.
2. Administrator starts the service by running the `mqtt-broadcast` artisan command.
3. The system connects to all configured brokers and begins listening for messages.
4. If a broker connection drops, the system automatically retries with increasing delays (1s, 2s, 4s, 8s... up to 60s).
5. The real-time dashboard shows the status of each broker connection (connected, reconnecting, disconnected).
6. If the system uses too much memory, it automatically restarts itself to prevent crashes.
7. Administrator can pause, resume, or stop the service using standard process signals or the terminate command.

## Business Rules

- Only one master supervisor instance can run per machine at a time. A second attempt is blocked with a warning.
- Each MQTT connection gets its own isolated supervisor — a failure in one connection does not affect others.
- The system retries failed connections up to 20 times by default before either resetting or giving up (configurable).
- If connections fail continuously for 1 hour (configurable), the system gives up on that broker entirely.
- When stopped, the system disconnects all brokers and cleans up all tracking records before exiting.
- Heartbeat timestamps are updated every second for each active connection, enabling stale process detection.

## Edge Cases

- **Broker unreachable at startup**: all connections are validated before the system starts. If any connection config is invalid, the system refuses to start and displays the errors.
- **Broker goes down during operation**: the supervisor enters reconnection mode with exponential backoff. Messages are not lost if the broker supports persistent sessions (QoS > 0).
- **Process killed with SIGKILL (-9)**: database and cache records become stale. The terminate command includes cleanup logic to remove orphaned records. The stale threshold (default: 5 minutes) also helps the dashboard detect dead processes.
- **Memory leak**: the system runs garbage collection every 100 iterations and monitors memory against a configurable threshold (default: 128 MB). If memory stays above threshold for 10 seconds, the system restarts.
- **Duplicate broker names**: each broker gets a unique name using the machine hostname plus a random token, preventing collisions.

## Permissions & Access

- Starting and stopping the MQTT service requires server-level access (SSH or process manager).
- There is no application-level permission for starting/stopping supervisors — this is an infrastructure concern.
- The monitoring dashboard is protected by a Laravel Gate (`viewMqttBroadcast`). In local environments, access is open to all authenticated users.
