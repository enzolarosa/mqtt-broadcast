# Upgrade Guide

This document outlines the breaking changes and upgrade steps between major versions.

## From 2.x to 3.0 (Future Release)

### Breaking Changes

#### 1. Model Renamed: `Brokers` → `BrokerProcess`

**What changed:**
- The Eloquent model `enzolarosa\MqttBroadcast\Models\Brokers` has been renamed to `BrokerProcess`
- This clarifies the distinction between the Model and the deprecated service class

**Database impact:**
- ✅ **No migration needed** - table name remains `mqtt_brokers`
- ✅ **No data loss** - existing data is preserved

**Action required:**
```php
// Before (v2.x)
use enzolarosa\MqttBroadcast\Models\Brokers;

$broker = Brokers::factory()->create();
$broker = Brokers::where('name', 'test')->first();

// After (v3.0)
use enzolarosa\MqttBroadcast\Models\BrokerProcess;

$broker = BrokerProcess::factory()->create();
$broker = BrokerProcess::where('name', 'test')->first();
```

**Factory changes:**
```php
// Before (v2.x)
use enzolarosa\MqttBroadcast\Database\Factories\BrokersFactory;

// After (v3.0)
use enzolarosa\MqttBroadcast\Database\Factories\BrokerProcessFactory;
```

**Repository changes:**
The `BrokerRepository` now returns `BrokerProcess` instances:
```php
// Before (v2.x)
public function create(string $name, string $connection): Brokers

// After (v3.0)
public function create(string $name, string $connection): BrokerProcess
```

**Find & Replace:**
Search for these patterns in your codebase and update:
- `use enzolarosa\MqttBroadcast\Models\Brokers` → `use enzolarosa\MqttBroadcast\Models\BrokerProcess`
- `Brokers::factory()` → `BrokerProcess::factory()`
- `Brokers::where(` → `BrokerProcess::where(`
- `Brokers::create(` → `BrokerProcess::create(`
- Type hints: `: Brokers` → `: BrokerProcess`
- Docblocks: `@return Brokers` → `@return BrokerProcess`

---

#### 2. Service Class Removed: `Brokers`

**What changed:**
- The `enzolarosa\MqttBroadcast\Brokers` service class has been removed
- Deprecated in v2.5.0, removed in v3.0

**Why:**
- This class was replaced by the new supervisor architecture in the H4 refactoring
- The new architecture provides better separation of concerns

**Migration path:**

| Old (Brokers) | New (v3.0) |
|---------------|------------|
| `Brokers::make()` | Use `BrokerRepository::create()` |
| `Brokers::find()` | Use `BrokerRepository::find()` |
| `Brokers::all()` | Use `BrokerRepository::all()` |
| `Brokers::client()` | Use `MqttClientFactory::create()` |
| `Brokers->monitor()` | Use `BrokerSupervisor::monitor()` |

**Example migration:**

```php
// Before (v2.x) - Using Brokers service
$brokers = new Brokers();
$brokers->make('default');
$brokers->monitor();

// After (v3.0) - Using new architecture
$repository = app(BrokerRepository::class);
$factory = app(MqttClientFactory::class);

$broker = $repository->create($repository->generateName(), 'default');
$client = $factory->create('default');

$supervisor = new BrokerSupervisor(
    connection: 'default',
    broker: $broker,
    client: $client,
    repository: $repository
);

$supervisor->monitor();
```

---

#### 3. Validator Class Deprecated: `BrokerValidator`

**What changed:**
- `enzolarosa\MqttBroadcast\Support\BrokerValidator` is now deprecated
- Use `MqttConnectionConfig::fromConnection()` instead for configuration validation
- Will be removed in v4.0

**Why:**
- `BrokerValidator` provides only basic validation (host, port, auth)
- `MqttConnectionConfig` provides comprehensive validation:
  - Port range validation (1-65535)
  - QoS validation (0-2)
  - Timeout validation (>0)
  - Type-safe configuration access
  - Better error messages with context
  - Immutable value object pattern

**Action required:**

```php
// Before (v2.x) - Using BrokerValidator
use enzolarosa\MqttBroadcast\Support\BrokerValidator;

BrokerValidator::validate('default'); // Throws if invalid

// After (v3.0) - Using MqttConnectionConfig
use enzolarosa\MqttBroadcast\Support\MqttConnectionConfig;

$config = MqttConnectionConfig::fromConnection('default'); // Throws if invalid
// Now you have a type-safe config object with validated values
$host = $config->host();
$port = $config->port();
$qos = $config->qos(); // Guaranteed to be 0, 1, or 2
```

**Benefits of migration:**
- ✅ More thorough validation (10+ validation rules)
- ✅ Type-safe configuration access with IDE autocomplete
- ✅ Descriptive error messages
- ✅ Immutable value object (thread-safe)
- ✅ Used internally by `MqttClientFactory` (consistency)

**Timeline:**
- v3.0: Deprecated, triggers deprecation notice
- v4.0: Removed

---

## From 1.x to 2.0

No breaking changes documented yet. Version 2.0 was the initial stable release with the supervisor architecture.

---

## Need Help?

If you encounter issues during the upgrade process:
1. Check this document for the specific change
2. Review the [CHANGELOG.md](CHANGELOG.md) for detailed changes
3. Open an issue on GitHub with your specific case
