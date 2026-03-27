# Testing Infrastructure

## What It Does

The package includes a full automated test suite that verifies all functionality works correctly — from message publishing to dashboard API responses to broker supervision. Tests run in two modes: **unit tests** that work instantly on any machine without external dependencies, and **integration tests** that verify real MQTT broker communication using Docker.

The test suite acts as a safety net: any code change is validated against 356 automated checks before it can be merged.

## User Journey

1. Developer clones the repository
2. Runs `composer install` to install test dependencies
3. Runs `composer test` — all 327 unit tests execute immediately (no setup required)
4. For integration testing, starts Docker services: `docker compose -f docker-compose.test.yml up -d`
5. Runs `composer test` again — now all 356 tests execute (unit + integration)
6. Integration tests that require a broker skip automatically if Docker is not running
7. Developer makes code changes
8. Runs `composer test` to verify nothing is broken
9. Runs `composer pint` (code style) and `composer analyse` (static analysis) before committing

## Business Rules

- **Unit tests must never require a running MQTT broker** — they use a mock client that simulates broker behavior in memory
- **Integration tests skip gracefully** when no broker is available, rather than failing — this allows CI to run a partial suite without Docker
- **All tests run against an in-memory SQLite database** — no persistent database setup required, each test starts fresh
- **The test suite validates the full stack**: models, jobs, controllers, middleware, supervisors, rate limiting, and the Dead Letter Queue
- **Code style and static analysis are separate checks** — `composer pint` enforces formatting, `composer analyse` catches type errors at PHPStan level 7
- **Coverage reports are available** via `composer test-coverage`
- **Test data factories ship with the package** — pre-built factories for broker processes and message logs allow realistic test scenarios including stopped brokers, stale heartbeats, and various message formats
- **All API-facing models use UUID identifiers** — test scenarios and API interactions reference records by UUID, not by database auto-increment IDs, ensuring consistency with how the dashboard and external consumers access data

## Edge Cases

- **Broker goes down during integration tests**: tests that started before the outage may fail with connection errors; tests that haven't started yet will skip with a diagnostic message explaining why
- **SQLite limitations**: some database features (e.g., JSON column queries) may behave differently in SQLite vs MySQL/PostgreSQL — integration tests use the real database driver when available
- **Rate limiting in tests**: uses the `array` cache driver so rate limit counters reset between tests automatically
- **Concurrent test runs**: safe because each run uses its own in-memory database and array cache — no shared state between parallel processes
- **CI without Docker**: the environment variable `MQTT_BROKER_AVAILABLE=false` can be set to skip integration tests without attempting a connection

## Permissions & Access

- **Any developer** can run unit tests — no credentials, Docker, or external services required
- **Integration tests** require Docker (Mosquitto on port 1883, Redis on port 6379)
- **CI systems** can control test scope via the `MQTT_BROKER_AVAILABLE` environment variable
- **Coverage reports** require Xdebug or PCOV PHP extension installed
