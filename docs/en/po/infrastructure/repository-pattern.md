# Repository Pattern

## What It Does

The system uses two separate storage mechanisms to track the state of MQTT processes. Broker processes (the individual connections to MQTT servers) are stored in the database for durability and queryability. Master supervisor state (the orchestrator that manages all brokers) is stored in a fast cache layer (typically Redis) because it is ephemeral and updated frequently.

This separation ensures that broker records survive application restarts and are available for the dashboard to display, while supervisor state remains lightweight and automatically expires when no longer needed.

## User Journey

1. An administrator starts the MQTT supervisor via `php artisan mqtt-broadcast`
2. The system registers the master supervisor in the cache and creates a database record for each broker connection
3. While running, each broker updates its heartbeat timestamp on every loop cycle; the master supervisor updates its cached state (PID, status, memory usage, broker count)
4. The dashboard reads from both storage layers to display real-time status: active brokers, connection health, supervisor state, and memory usage
5. The health endpoint uses heartbeat timestamps to determine if brokers are alive (heartbeat within the last 2 minutes = active)
6. On shutdown, both the broker records and supervisor cache entries are cleaned up automatically

## Business Rules

- Broker records are persistent -- they survive application restarts and remain queryable until explicitly deleted
- Supervisor state is ephemeral -- it expires automatically after a configurable TTL (default: 1 hour)
- All cleanup operations use a "silent fail" pattern: deleting a non-existent record does not cause an error, which prevents cascading failures during shutdown
- Each broker generates a unique name combining the hostname and a random token (e.g. `johns-macbook-a3f2`)
- Heartbeat freshness determines connection status: brokers with a heartbeat older than 2 minutes are shown as inactive in the dashboard
- The stale threshold (configurable, default 5 minutes) determines when a broker is considered completely stale and eligible for cleanup

## Edge Cases

- **Memcached driver**: The system cannot list all active supervisors when using Memcached as the cache driver because the Memcached protocol does not support key enumeration. The dashboard will show empty supervisor data. Redis is the recommended driver for production.
- **Orphaned records**: If a process crashes without graceful shutdown, broker records remain in the database. The health check will show them as inactive (stale heartbeat) and the terminate command can clean them up by PID.
- **Multiple brokers with same PID**: While unlikely, `deleteByPid()` will remove all matching records rather than just one.
- **Corrupted file cache**: If using the file cache driver and a cache file becomes corrupted, it is logged as a warning and skipped -- it does not block the discovery of other supervisors.
- **Cache TTL expiration**: If the master supervisor does not update its state within the TTL window (default 1 hour), the entry expires silently. The supervisor will re-appear on the next `persist()` call.

## Permissions & Access

- Repository operations are internal to the package -- they are not directly exposed via API endpoints
- Dashboard controllers that read repository data are protected by the `Authorize` middleware, which requires the `viewMqttBroadcast` gate permission (or local environment)
- The terminate command, which triggers repository cleanup, is an Artisan command and requires CLI access
- Database access follows the application's configured database connection; cache access follows the configured cache driver
