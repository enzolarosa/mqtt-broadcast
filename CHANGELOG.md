# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- H4.9: Added validation to `MqttClientFactory` for host and port configuration
- H4.9: Added `connectionMissingConfiguration()` exception method
- H4.9: Added fail-fast mitigation in `MqttMessageJob` to prevent retry storms on config errors
- H4.10: Added `UPGRADE.md` documentation for breaking changes

### Changed
- H4.9: Refactored `MqttMessageJob` to use `MqttClientFactory` (removed 68 lines of duplicate code)
- H4.9: Moved validation from `MqttMessageJob` constructor to factory (centralized)
- H4.9: Fixed `ServiceBindings` trait to follow Laravel Horizon pattern
  - Removed: `Models\Brokers` (incorrect Eloquent Model binding)
  - Added: `MqttClientFactory`, `BrokerRepository`, `MasterSupervisorRepository` as singletons

### Deprecated
- H4.10: **BREAKING (v3.0)**: Deprecated `Brokers` service class in favor of new architecture
  - Use `BrokerSupervisor` for monitoring
  - Use `MqttClientFactory` for client creation
  - Use `BrokerRepository` for persistence
- H4.10: **BREAKING (v3.0)**: Renamed `Models\Brokers` to `Models\BrokerProcess`
  - Factory renamed: `BrokersFactory` → `BrokerProcessFactory`
  - Table name unchanged: `mqtt_brokers` (no migration needed)

### Fixed
- H4.9: Fixed ServiceBindings registering Eloquent Model as singleton (incorrect pattern)

### Removed
- None yet (deprecations will be removed in v3.0)

---

## [2.4.0] - Previous Release

### Added
- H4.1-H4.8: Supervisor architecture refactoring
- `BrokerSupervisor` for broker monitoring
- `MasterSupervisor` for multi-broker coordination
- `MqttClientFactory` for centralized client creation
- `BrokerRepository` and `MasterSupervisorRepository`
- Retry logic with exponential backoff
- Process identifiers and heartbeat monitoring

### Changed
- Commands refactored: `MqttBroadcastCommand`, `MqttBroadcastTerminateCommand`
- Improved signal handling and graceful shutdown

---

## Migration Notes

### To v3.0 (Future - Breaking Changes)

**Required Actions:**
1. Update all `use enzolarosa\MqttBroadcast\Models\Brokers` imports to `BrokerProcess`
2. Replace `BrokersFactory` with `BrokerProcessFactory` in tests
3. Migrate away from `Brokers` service class to new architecture (see UPGRADE.md)

**Impact:**
- Model rename: Update imports only (no data migration)
- Service class removal: Requires code refactoring (see UPGRADE.md for migration guide)

---

## Notes

- **H4 Refactoring** (Phase 4 - Integration): Completed in versions 2.5.0
  - H4.9: MqttMessageJob refactoring + ServiceBindings fix
  - H4.10: Model rename + service deprecation

[Unreleased]: https://github.com/enzolarosa/mqtt-broadcast/compare/v2.4.0...HEAD
[2.4.0]: https://github.com/enzolarosa/mqtt-broadcast/releases/tag/v2.4.0
