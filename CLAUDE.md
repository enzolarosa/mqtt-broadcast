# CLAUDE.md

## Project

Laravel package (`enzolarosa/mqtt-broadcast`) — MQTT integration for Laravel with Horizon-style supervision, multi-broker support, and a React 19 monitoring dashboard.

Namespace: `enzolarosa\MqttBroadcast\`

## Code Style

Always run before committing:

```bash
composer pint        # Laravel Pint (strict preset, strict_types required)
composer analyse     # PHPStan level 7
```

All PHP files must have `declare(strict_types=1)`. Follow Spatie PHP/Laravel guidelines (php-guidelines-from-spatie skill).

## Testing

Unit tests (no broker needed):
```bash
composer test
# or
vendor/bin/pest --exclude-group=integration
```

Integration tests (requires real broker):
```bash
docker compose -f docker-compose.test.yml up -d
vendor/bin/pest
docker compose -f docker-compose.test.yml down
```

Coverage:
```bash
composer test-coverage
```

Tests live in `tests/Unit/` (327) and `tests/Integration/` (29). Unit tests use mock MQTT client via `tests/Helpers/`. Never mock the database in tests.

## Architecture

- **Supervisor pattern** (Horizon-inspired): `MasterSupervisor` → `BrokerSupervisor` → MQTT client per broker
- **Queue jobs**: `MqttMessageJob` handles async publishing with rate limiting and DLQ fallback
- **Failed jobs**: `FailedMqttJob` model → `mqtt_failed_jobs` table (Dead Letter Queue)
- **Dashboard**: React 19 SPA served via Blade, built with Vite (`resources/js/mqtt-dashboard/`)
- **Migrations**: auto-loaded by service provider (no publish required)

Key directories:
```
src/Commands/      Artisan commands
src/Supervisors/   MasterSupervisor, BrokerSupervisor
src/Jobs/          MqttMessageJob
src/Models/        BrokerProcess, MqttLogger, FailedMqttJob
src/Http/          Dashboard controllers + middleware
src/Support/       RateLimitService, MemoryManager, etc.
```

## Local Dev

This is a package — no standalone app. Use `orchestra/testbench` for Laravel bootstrapping in tests.

Docker services for integration tests:
- Mosquitto 2.0 on ports 1883 (MQTT) and 9001 (WebSocket)
- Redis 7 on port 6379

Frontend (dashboard only):
```bash
npm run dev    # dev server
npm run build  # production build
```

## Documentation

Always write documentation directly to Outline via the `outline` MCP server.

**Collection:** `TEST`

Mirror the local `docs/` hierarchy as nested pages in Outline:

```
TEST (collection)
└── mqtt-broadcast
    ├── EN
    │   ├── Developer
    │   │   ├── Getting Started → docs/en/dev/getting-started/
    │   │   ├── Publishing      → docs/en/dev/publishing/
    │   │   ├── Subscription    → docs/en/dev/subscription/
    │   │   ├── Supervisor      → docs/en/dev/supervisor/
    │   │   ├── Failed Jobs     → docs/en/dev/failed-jobs/
    │   │   ├── Dashboard       → docs/en/dev/dashboard/
    │   │   ├── Commands        → docs/en/dev/commands/
    │   │   ├── Infrastructure  → docs/en/dev/infrastructure/
    │   │   ├── Testing         → docs/en/dev/testing/
    │   │   └── Migration       → docs/en/dev/migration/
    │   └── Product Owner
    │       └── (same structure, docs/en/po/)
    └── IT
        ├── Developer           → docs/it/dev/
        └── Product Owner       → docs/it/po/
```

Rules:
- One Outline page per `.md` file, title = filename in title case
- If a page exists, update it — never duplicate
- When adding/modifying a doc file locally, sync the corresponding Outline page

## Current Work in Progress

- `src/Jobs/MqttMessageJob.php` — modified (rate limiting + DLQ integration)
- `src/Models/FailedMqttJob.php` — new model for Dead Letter Queue
- `database/migrations/2025_03_27_000000_create_mqtt_failed_jobs_table.php` — new migration
