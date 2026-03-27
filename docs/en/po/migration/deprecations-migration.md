# Deprecations & Migration Guide

## What It Does

The MQTT Broadcast package has evolved through major architectural changes. Some older components have been replaced by more reliable, better-designed alternatives. This guide explains what changed, why, and what developers need to do when upgrading.

## User Journey

1. Developer runs their application after upgrading to a new version of the package
2. If their code still uses deprecated components, deprecation warnings appear in the application logs
3. Developer consults this guide to understand which components have been replaced
4. Developer updates their code to use the new components
5. Deprecation warnings stop appearing

## Business Rules

- Deprecated components continue to work during the deprecation period — no immediate breakage on upgrade
- Deprecation notices are logged at the `E_USER_DEPRECATED` level, making them visible in error tracking tools
- Each deprecated component has a clear replacement with a documented migration path
- Deprecated classes are removed in the next major version after the deprecation notice

## What Changed and Why

### Broker Management (changed in v2.5.0)

**Before:** A single class handled everything — creating broker connections, monitoring them, handling signals, and managing processes. This made it impossible to test individual parts, had no memory management, and crashed permanently if a connection dropped.

**After:** The system now uses a supervisor hierarchy (inspired by Laravel Horizon). A master supervisor creates individual broker supervisors, each with automatic reconnection, memory limits, and circuit breakers. This means crashed connections recover automatically and memory leaks are detected and resolved.

### Configuration Validation (changed in v3.0)

**Before:** Basic checks that only verified whether host and port were present in the configuration. Invalid values (like a port of `0` or a QoS of `5`) passed validation silently.

**After:** A comprehensive validation system that checks value ranges, types, and logical consistency. Errors include specific context about what went wrong and how to fix it.

## Edge Cases

- **Code using the old `Brokers` class directly:** Will continue to work through v2.x but will log deprecation notices. Must be migrated before upgrading to v3.0
- **Code using `BrokerValidator::validate()`:** Will continue to work through v3.x but depends on a removed exception class (`InvalidBrokerException`), making it fragile. Should be migrated immediately
- **Custom code extending deprecated classes:** Will break on removal. No extension points are available; use the new components directly
- **Error tracking integration:** Deprecation notices will appear in Sentry, Flare, or similar tools. This is expected and intentional — it provides visibility into code that needs updating

## Permissions & Access

- No special permissions are required to use the new components
- The migration is a code-level change — no database migrations, configuration changes, or deployment steps needed beyond updating the code
- All new components are registered in the service container automatically by the service provider
