# Testing Guide

## Quick Start

```bash
# Run unit tests (no broker required)
vendor/bin/pest --exclude-group=integration

# Run ALL tests with real broker
docker compose -f docker-compose.test.yml up -d
vendor/bin/pest
docker compose -f docker-compose.test.yml down
```

## Test Types

### Unit Tests (327 tests)
- **No external dependencies** - use mocks
- Run fast (~2 seconds)
- Always enabled

### Integration Tests (29 tests)
- **Require real MQTT broker** (Mosquitto)
- Test actual process lifecycle, signals, cache cleanup
- Currently skipped if broker not available

## Running Integration Tests

### Option 1: Docker Compose (Recommended)

```bash
# Start Mosquitto + Redis
docker compose -f docker-compose.test.yml up -d

# Wait for services to be ready
docker compose -f docker-compose.test.yml ps

# Run tests
vendor/bin/pest

# Or run only integration tests
vendor/bin/pest --group=integration

# Stop services
docker compose -f docker-compose.test.yml down
```

### Option 2: Manual Mosquitto Setup

If you have Mosquitto installed locally:

```bash
# Start Mosquitto with test config
mosquitto -c tests/fixtures/mosquitto.conf

# In another terminal
vendor/bin/pest
```

### Option 3: Skip Integration Tests

```bash
# Run only unit tests
vendor/bin/pest --exclude-group=integration
```

## GitHub Actions

Integration tests run automatically on CI with Mosquitto service container:
- Workflow: `.github/workflows/integration-tests.yml`
- Runs on every push/PR
- Uses real Mosquitto broker

## Writing New Tests

### Unit Test (with Mock)

```php
use Tests\Mocks\MockMqttClient;

test('my feature works', function () {
    $client = new MockMqttClient('127.0.0.1', 1883);
    // ... test logic
});
```

### Integration Test (requires real broker)

```php
test('my integration test', function () {
    // This will auto-skip if broker not available
    $this->requiresBroker();

    // Test with real broker connection
    $client = new MqttClient('127.0.0.1', 1883);
    $client->connect();
    // ...
});
```

## Troubleshooting

### "MQTT broker not available"

```bash
# Check if Mosquitto is running
docker compose -f docker-compose.test.yml ps

# Check if port 1883 is open
nc -zv 127.0.0.1 1883

# View Mosquitto logs
docker compose -f docker-compose.test.yml logs mosquitto
```

### Port already in use

```bash
# Find what's using port 1883
lsof -i :1883

# Stop other Mosquitto instances
docker compose -f docker-compose.test.yml down
```

## Test Coverage

```bash
# Generate coverage report
vendor/bin/pest --coverage

# With HTML report
vendor/bin/pest --coverage --coverage-html coverage/
```

## Environment Variables

For CI or custom setups:

```bash
# Force broker availability check
export MQTT_BROKER_AVAILABLE=1

# Custom broker host/port
export MQTT_HOST=mosquitto.local
export MQTT_PORT=1883

# Run tests
vendor/bin/pest
```

## Mosquitto Versions

### Local Development (Docker Compose)
- Uses **Mosquitto 2.0** with custom config
- Config file: `tests/fixtures/mosquitto.conf`
- Allows anonymous connections for testing

### GitHub Actions (CI)
- Uses **Mosquitto 2.0** with inline configuration
- Config created in workflow step (no volume mount issues)
- Allows anonymous connections for testing

Both use Mosquitto 2.0 for consistency between local and CI environments.
