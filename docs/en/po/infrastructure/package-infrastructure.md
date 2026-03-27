# Package Infrastructure

## What It Does

The package infrastructure is the foundational layer that makes MQTT Broadcast work as a drop-in Laravel package. It handles automatic setup — when a developer installs the package via Composer, everything is wired up automatically: database tables are created, configuration is merged, the dashboard becomes accessible, and all commands are registered. No manual wiring is needed beyond running `php artisan migrate`.

For production environments, administrators can customise access control, change the dashboard URL, configure which MQTT brokers are used per environment, and tune performance parameters like memory limits and reconnection behaviour — all through a single configuration file and an optional provider override.

## User Journey

1. Developer installs the package via `composer require enzolarosa/mqtt-broadcast`.
2. Laravel auto-discovers the service provider — no manual registration needed.
3. Developer runs `php artisan mqtt-broadcast:install` to publish the configuration file and optional provider.
4. Developer runs `php artisan migrate` — three database tables are created automatically (`mqtt_brokers`, `mqtt_loggers`, `mqtt_failed_jobs`).
5. Developer configures MQTT broker credentials in `.env` (`MQTT_HOST`, `MQTT_PORT`, etc.).
6. Developer optionally maps environments to brokers in the config file (e.g., production uses one broker, staging uses another).
7. In production, developer publishes the provider stub and customises the authorization gate to control who can access the dashboard.
8. The dashboard is accessible at `/mqtt-broadcast` (or a custom path) with automatic middleware protection.

## Business Rules

- **Auto-discovery**: The package registers itself automatically via Composer's auto-discovery — no manual provider registration in `config/app.php` is needed (Laravel 5.5+).
- **Zero-publish migrations**: Database migrations run directly from the vendor directory. Users never need to publish or modify migrations.
- **Environment-based broker selection**: The `environments` config maps `APP_ENV` to a list of broker connections. Only the brokers configured for the current environment are supervised.
- **Deny-all default access**: The dashboard is accessible to everyone in `local` environment, but denies all access in non-local environments by default. Explicit gate configuration is required for production.
- **Configuration precedence**: Published config values override package defaults. Environment variables override config file values.
- **Three publish groups**: Config, provider stub, and frontend assets can be published independently or together.
- **Singleton services**: Core services (client factory, broker repository, supervisor repository) are registered as singletons — one instance per request lifecycle.
- **Route caching compatible**: Routes are not registered when the route cache is active, preventing duplication.

## Edge Cases

- **Missing broker config**: If a connection referenced in the environment mapping does not exist in the `connections` array, the system throws a clear configuration error with the exact missing key.
- **Multiple providers**: If a user publishes the provider stub but also has the base provider auto-discovered, the published provider extends the base — it does not create a conflict. The published provider's `registerGate()` override takes precedence.
- **Route cache**: If routes are cached (`php artisan route:cache`), the package skips dynamic route registration entirely. Users must re-cache routes after config changes.
- **Database connection mismatch**: The `mqtt_loggers` and `mqtt_failed_jobs` tables use configurable database connections. If a connection is misconfigured, migrations fail with a clear database error.
- **Helper function conflicts**: The global `mqttMessage()` and `mqttMessageSync()` helpers are wrapped in `function_exists()` guards, so users can define their own versions without conflicts.

## Permissions & Access

- **Local environment**: Full dashboard access without authentication (handled by `Authorize` middleware which bypasses the gate check).
- **Non-local environments**: Access requires passing the `viewMqttBroadcast` gate. By default, this gate denies all users.
- **Custom gate**: Users override the gate in their published `MqttBroadcastServiceProvider` — typically checking user email, role, or permissions.
- **Middleware stack**: All dashboard routes use the `web` middleware group plus the `Authorize` middleware. This can be customised in the config to add additional middleware (e.g., `auth`, custom IP restrictions).
- **API endpoints**: All API routes (`/api/*`) are protected by the same middleware stack as the dashboard — no separate API authentication.
