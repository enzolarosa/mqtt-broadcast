# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

---

## [3.0.0] - 2026-01-28

### 🎉 Major Release - H4 Refactoring Complete + Optimizations

This release completes the Horizon-style architecture refactoring (H4) and includes comprehensive optimizations for type safety, configuration management, and error handling.

### Added

#### Architecture (H4 Refactoring)
- **MqttClientFactory**: Centralized MQTT client creation with config validation
- **MqttConnectionConfig**: Type-safe, immutable value object for connection configuration
  - Validates: port (1-65535), QoS (0-2), timeout (>0), alive_interval (>0)
  - Full validation with descriptive error messages
  - Used internally by MqttClientFactory
- **BrokerRepository**: Database operations for broker processes
- **MasterSupervisorRepository**: Cache-based state persistence for master supervisor
- **ProcessIdentifier**: Utility for process naming and PID management
- **MasterSupervisor**: Orchestrates multiple BrokerSupervisor instances
- **BrokerSupervisor**: Manages individual MQTT broker connections
- Reconnection strategy with exponential backoff (max 20 retries, up to 60s delay)
- Process heartbeat monitoring (1s interval)

#### Configuration
- **Config defaults system**: Centralized defaults for broker connections
  - `defaults.connection` section reduces duplication
  - Auto-merged with connection configs
- **Early config validation**: Command validates all connections before starting
  - Shows all validation errors at once (better DX)
  - Prevents startup with invalid configuration

#### Type Safety
- Return type declarations for all public methods (20+ methods)
- Protected property type hints (`EventMap`, `ListensForSignals`)
- Full PHP 8.1+ strict typing compliance

#### Error Handling
- Cache file deserialization error handling with logging
- JSON error handling with `JSON_THROW_ON_ERROR` and try-catch
  - Logger.php: Stores raw message if JSON invalid
  - MqttListener.php: Logs warning with context for debugging

#### Database
- Composite index on `mqtt_loggers` table: (broker, topic, created_at)
  - Optimizes common query patterns

#### Documentation
- Complete ARCHITECTURE.md (600+ lines)
  - Component diagrams
  - Message flow
  - Lifecycle management
  - Extension points
- Comprehensive README.md rewrite (475 lines)
- UPGRADE.md with migration guides
- TESTING_LIMITATIONS.md explaining test coverage

#### Testing
- 242 tests total (100% passing)
  - Job tests: 56
  - Integration tests: 28
  - Unit tests: 158
- Test coverage: 95%+ critical path
- Command tests with pragmatic approach

### Changed

#### Architecture
- Refactored `MqttMessageJob` to use `MqttClientFactory` (removed 68 lines duplicate code)
- Commands refactored to use supervisor architecture:
  - `MqttBroadcastCommand`: Multi-broker support via environments
  - `MqttBroadcastTerminateCommand`: Uses repositories for cleanup
- Supervisor heartbeat via `BrokerRepository::touch()`
- Config values cached in job constructor (performance optimization)

#### Configuration
- Migration tag fixed: `nova-migrations` → `mqtt-broadcast-migrations`
- Removed redundant config fallbacks from code (trust config defaults)
- Connection configs now use centralized defaults

#### Models
- Trait moved: `Traits\Models\ExternalId` → `Models\Concerns\HasExternalId`
  - Follows Laravel Eloquent conventions
  - Better organization and naming

### Deprecated

⚠️ **Breaking changes for v3.0** - See [UPGRADE.md](UPGRADE.md) for migration guide

- **BrokerValidator** class (use `MqttConnectionConfig::fromConnection()` instead)
  - Will be removed in v4.0
  - Migration: Replace `BrokerValidator::validate('default')` with `MqttConnectionConfig::fromConnection('default')`
  - Benefits: More validation, type safety, better errors

- **Brokers** service class (use supervisor architecture)
  - Deprecated since v2.5.0
  - Will be removed in v4.0
  - Migration path documented in UPGRADE.md

### Fixed

- ServiceBindings: Removed incorrect Eloquent Model singleton binding
- Reconnection logic: Proper exponential backoff implementation
- JSON encoding: Always uses `JSON_THROW_ON_ERROR` flag
- Cache file corruption handling: Graceful error recovery

### Breaking Changes

⚠️ **BREAKING**: Model renamed (requires code changes)

**Before (v2.x):**
```php
use enzolarosa\MqttBroadcast\Models\Brokers;

$broker = Brokers::factory()->create();
```

**After (v3.0):**
```php
use enzolarosa\MqttBroadcast\Models\BrokerProcess;

$broker = BrokerProcess::factory()->create();
```

**Migration:**
- Update all imports: `Models\Brokers` → `Models\BrokerProcess`
- Update factory: `BrokersFactory` → `BrokerProcessFactory`
- No database migration needed (table name unchanged)

See [UPGRADE.md](UPGRADE.md) for complete migration guide.

---

## [2.4.0] - 2024-11-26

### Added
- Initial H4 supervisor architecture (H4.1-H4.8)
- Signal handling for graceful shutdown
- Exponential backoff retry logic

### Changed
- Command architecture improvements
- Process management enhancements

---

## Migration Notes

### To v3.0 from v2.x

**Required Actions:**
1. ✅ Update all `use enzolarosa\MqttBroadcast\Models\Brokers` imports to `BrokerProcess`
2. ✅ Replace `BrokersFactory` with `BrokerProcessFactory` in tests
3. ⚠️ Migrate away from `Brokers` service class (see UPGRADE.md)
4. ⚠️ Replace `BrokerValidator::validate()` with `MqttConnectionConfig::fromConnection()`

**Impact:**
- Model rename: Import updates only (no data migration required)
- Service class: Code refactoring required if used directly
- Validator: Simple one-line replacement

**Time Estimate:** 15-30 minutes for typical application

See [UPGRADE.md](UPGRADE.md) for detailed migration guide.

---

## Statistics - v3.0

- **Issues Resolved:** 26/54 (48.1%)
  - Critical: 4/4 (100%) ✅
  - High Priority: 5/12 (41.7%)
  - Medium Priority: 8/25 (32.0%)
- **Tests:** 242 total (100% passing)
- **Test Coverage:** 95%+ critical path
- **Documentation:** 4 major docs (README, ARCHITECTURE, UPGRADE, TESTING_LIMITATIONS)
- **Architecture:** Horizon-style pattern complete
- **PHP Version:** 8.1+ required
- **Laravel Version:** 10.x, 11.x compatible

---

## Notes

### H4 Refactoring Phases (Complete)
- ✅ **H4.1-H4.4** (FASE 1): Foundation classes
- ✅ **H4.5-H4.6** (FASE 2): Supervisor classes
- ✅ **H4.7-H4.8** (FASE 3): Command refactoring
- ✅ **H4.9-H4.10** (FASE 4): Integration & deprecation
- ✅ **H4.11-H4.12** (FASE 5): Testing & documentation

### Post-H4 Optimizations (Complete)
- ✅ Return type declarations (#2)
- ✅ JSON error handling (#3)
- ✅ Migrations tag fix (#4)
- ✅ Config validation class (#5)
- ✅ Config caching (M1)
- ✅ Trait refactoring (M2)
- ✅ Database index (M5)
- ✅ Cache error handling (M7)
- ✅ Config defaults (M8)
- ✅ Property types (M9)
- ✅ Early validation (M10)
- ✅ Validator deprecation (M11)

[Unreleased]: https://github.com/enzolarosa/mqtt-broadcast/compare/v3.0.0...HEAD
[3.0.0]: https://github.com/enzolarosa/mqtt-broadcast/releases/tag/v3.0.0
[2.4.0]: https://github.com/enzolarosa/mqtt-broadcast/releases/tag/v2.4.0
