# Installation & Configuration

## What It Does

MQTT Broadcast is a Laravel package that connects your application to MQTT message brokers. It provides real-time message publishing and subscription, automatic process supervision, a web-based monitoring dashboard, and a Dead Letter Queue for failed messages. Installation takes under 5 minutes and requires minimal configuration.

## User Journey

1. **Developer installs the package** via Composer. Laravel auto-discovers the package and registers its services.
2. **Developer runs the install command**, which publishes the configuration file, a local service provider (for access control), and the dashboard frontend assets.
3. **Developer configures the MQTT broker** by adding host, port, and optional credentials to the `.env` file.
4. **Developer runs database migrations** to create the required tables (message logs, broker tracking, failed jobs).
5. **Developer configures dashboard access** by editing the published service provider to define which users can access the monitoring dashboard.
6. **Developer starts the supervisor** with a single Artisan command. The system connects to all configured brokers and begins processing messages.
7. **Developer accesses the dashboard** at the configured URL (default: `/mqtt-broadcast`) to monitor broker status, message throughput, and failed jobs.

## Business Rules

- The package auto-discovers in Laravel 11+ — no manual provider registration needed after the install command runs.
- Migrations are auto-loaded from the vendor directory. No migration publishing step is required.
- The monitoring dashboard is fully accessible in `local` environment without any gate configuration.
- In all non-local environments (staging, production), dashboard access is denied by default. Access must be explicitly granted via a Laravel Gate.
- Multiple broker connections can be configured. Each environment (local, staging, production) can use a different subset of connections.
- The supervisor must run as a long-lived process (similar to Laravel Horizon). A process manager (Supervisor, systemd) is recommended for production.
- A queue worker must be running alongside the supervisor for async message publishing and listener processing.

## Edge Cases

- **Re-running the install command** is safe. The provider registration check is idempotent — it skips if the provider is already registered.
- **Missing MQTT broker at startup** causes the supervisor to attempt reconnection with exponential backoff (up to 20 retries by default), not an immediate crash.
- **No Redis in the environment** requires changing the `cache_driver` and `queue.connection` config values to a different driver (database, file, etc.).
- **Multiple developers on different environments** can each have different broker connections by mapping environment names to connection lists in the config.
- **Upgrading the package** may include new migrations. Running `php artisan migrate` after updates picks them up automatically.
- **Dashboard assets out of date** after upgrade can be refreshed by re-publishing: `php artisan vendor:publish --tag=mqtt-broadcast-assets --force`.

## Permissions & Access

- **Installation**: Requires Composer access and `php artisan` execution rights. Typically performed by a developer or during CI/CD deployment.
- **Configuration**: Requires access to `.env` and `config/mqtt-broadcast.php`. These files contain broker credentials and should be treated as sensitive.
- **Dashboard access**:
  - **Local environment**: Open to all users — no authentication required.
  - **Non-local environments**: Controlled by the `viewMqttBroadcast` Laravel Gate. By default, all access is denied. The gate must be explicitly configured in the published `MqttBroadcastServiceProvider`.
  - Access can be restricted by email, role, or any custom logic supported by Laravel Gates.
- **Supervisor process**: Runs as the web server user (e.g., `www-data`). Must have permission to write to the database and cache.
- **Queue worker**: Must be running on the same queue connection configured in `mqtt-broadcast.queue.connection` (default: `redis`).
