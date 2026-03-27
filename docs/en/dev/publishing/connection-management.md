# Connection Management

## Overview

Connection management is the foundational layer that all MQTT operations depend on — publishing, subscribing, and supervision. It solves three problems:

1. **Configuration validation**: catching invalid broker settings at construction time rather than at runtime deep in a queue worker.
2. **Client creation**: providing a consistent factory interface that handles client ID assignment, TLS, authentication, and clean session negotiation.
3. **Default merging**: allowing per-connection overrides while inheriting sensible global defaults.

The two key classes are `MqttConnectionConfig` (immutable validated value object) and `MqttClientFactory` (client instantiation and connection settings builder).

## Architecture

The design follows two patterns:

- **Value Object** (`MqttConnectionConfig`): immutable, validated at construction, fail-fast on invalid config. Created via named constructors (`fromConnection`, `fromArray`). Cannot be modified after creation.
- **Factory** (`MqttClientFactory`): creates `PhpMqtt\Client\MqttClient` instances from validated config. Returns configured-but-not-connected clients so callers control the connection lifecycle.

The factory delegates all validation to `MqttConnectionConfig`, which means any `MqttBroadcastException` thrown during client creation is always a config error (fail-fast, no retry).

## How It Works

### MqttConnectionConfig Lifecycle

1. **Entry point**: `MqttConnectionConfig::fromConnection('default')` reads `config('mqtt-broadcast.connections.default')`.
2. **Default merging**: merges with `config('mqtt-broadcast.defaults.connection')` — connection values take precedence over defaults, but `null` values are stripped before merging so defaults apply.
3. **Validation chain**: validates each field in order:
   - `host` — required, non-empty string
   - `port` — required, integer 1–65535
   - `qos` — integer 0, 1, or 2
   - `timeout` — positive integer
   - `alive_interval` — positive integer
   - `auth` credentials — if `auth` is `true`, both `username` and `password` must be non-empty strings
4. **Construction**: the private constructor is called with all validated values. The object is now immutable.

Any validation failure throws `MqttBroadcastException` with a message identifying the connection name and the invalid field.

### MqttClientFactory Lifecycle

1. **`create($connection, $clientId)`**: resolves config via `MqttConnectionConfig::fromConnection()`, then delegates to `createFromConfig()`.
2. **Client ID resolution** (three-tier fallback):
   - Explicit `$clientId` parameter (used by `MqttMessageJob` — random UUID per publish to avoid collisions)
   - `clientId` from connection config (used by `BrokerSupervisor` — fixed ID for persistent subscriptions)
   - Auto-generated UUID (fallback)
3. **Client instantiation**: creates `new MqttClient($host, $port, $clientId)` — configured but **not connected**.
4. **`getConnectionSettings($connection, $cleanSession)`**: builds `PhpMqtt\Client\ConnectionSettings` with TLS, keep-alive, timeout, and auth credentials. Returns `null` settings when `auth` is `false` (no auth needed).

### TLS and Authentication in ConnectionSettings

When `getConnectionSettingsFromConfig()` is called and `auth` is `true`, the factory builds a `PhpMqtt\Client\ConnectionSettings` object with these exact calls:

```php
$connectionSettings = (new ConnectionSettings)
    ->setKeepAliveInterval($config->aliveInterval())   // alive_interval config key
    ->setConnectTimeout($config->timeout())             // timeout config key
    ->setUseTls($config->useTls())                      // use_tls config key
    ->setTlsSelfSignedAllowed($config->selfSignedAllowed()) // self_signed_allowed config key
    ->setUsername($config->username())
    ->setPassword($config->password());
```

When `auth` is `false`, the factory returns `null` for settings. This means **TLS settings are only applied when authentication is enabled**. A connection with `use_tls: true` but `auth: false` will **not** have TLS applied at the factory level — the caller would need to handle this separately. This is an intentional design decision: unauthenticated connections to local development brokers don't need TLS configuration overhead.

The `cleanSession` parameter follows a two-tier resolution: if the caller passes an explicit value, it takes precedence; otherwise the config value is used.

### Topic Prefixing via MqttBroadcast::getTopic()

The `MqttBroadcast::getTopic()` static method resolves the final topic string by prepending the connection's configured prefix:

```php
public static function getTopic(string $topic, string $broker = 'default'): string
{
    self::validateBrokerConfiguration($broker);
    $prefix = config("mqtt-broadcast.connections.{$broker}.prefix", '');
    return $prefix . $topic;
}
```

This method is called in two critical places:

- **`MqttMessageJob::handle()`** — resolves the publish topic: `MqttBroadcast::getTopic($this->topic, $this->broker)`
- **`MqttListener::getTopic()`** — resolves the subscription topic filter for listener matching

The prefix is concatenated directly (no separator). If the prefix is `home/` and the topic is `sensors/temp`, the result is `home/sensors/temp`. If the prefix is empty (default), the topic passes through unchanged.

The method also validates broker configuration before accessing the prefix, so calling `getTopic()` with an unconfigured broker throws `MqttBroadcastException`.

### Publisher Connection Lifecycle (MqttMessageJob::mqtt())

The `MqttMessageJob::mqtt()` private method orchestrates the full publisher connection in three steps:

1. **Create client**: `$factory->create($broker, $publisherClientId)` with a random UUID via `Str::uuid()`. This avoids client ID collisions with the long-lived subscriber process.
2. **Get connection settings**: `$factory->getConnectionSettings($broker, $this->cleanSession)`. The `$cleanSession` property defaults to `true` in the job constructor — publishers always request a clean session because they don't need the broker to remember subscriptions.
3. **Connect (conditional)**: if `$connectionInfo['settings']` is not null (auth is enabled), the client is connected with settings. If null, the client is returned without calling `connect()` — the `handle()` method checks `$mqtt->isConnected()` and calls `connect()` with no arguments for unauthenticated brokers.

The job constructor also caches two config values at dispatch time to avoid repeated config lookups in the worker:

- **`$cachedQos`**: `$this->qos ?? config('mqtt-broadcast.connections.'.$this->broker.'.qos', 0)` — explicit QoS parameter takes precedence over connection config, which falls back to `0`.
- **`$cachedRetain`**: `config('mqtt-broadcast.connections.'.$this->broker.'.retain', false)` — read from the per-connection `retain` key, defaulting to `false`. Note: retain is read directly from the connection config, not from `MqttConnectionConfig`. This is intentional — the job caches it at dispatch time before the factory validates the full config.

These cached values are used in the `$mqtt->publish()` call and persisted to the DLQ if the job fails.

### Integration Points

- **`MqttMessageJob::mqtt()`** calls `$factory->create($broker, $uuid)` + `$factory->getConnectionSettings($broker, true)` — ephemeral publisher with random ID and forced clean session.
- **`BrokerSupervisor`** calls `$factory->create($broker)` — long-lived subscriber with config-defined or auto-generated ID. Uses the config-defined `clean_session` value (default: `false`) to support persistent sessions.
- **`MqttBroadcastCommand`** validates config existence via `MqttConnectionConfig::fromConnection()` during startup.
- **`MqttBroadcast::getTopic()`** used by both `MqttMessageJob::handle()` and `MqttListener::getTopic()` to resolve prefixed topics.

## Key Components

| File | Class/Method | Responsibility |
|------|-------------|----------------|
| `src/Support/MqttConnectionConfig.php` | `MqttConnectionConfig` | Immutable validated connection config value object |
| `src/Support/MqttConnectionConfig.php` | `::fromConnection($name)` | Named constructor: reads config, merges defaults, validates |
| `src/Support/MqttConnectionConfig.php` | `::fromArray($config)` | Named constructor: validates raw array (for testing/custom use) |
| `src/Support/MqttConnectionConfig.php` | `->toArray()` | Serialization for backward compatibility |
| `src/Support/MqttConnectionConfig.php` | `->requiresAuth()` | Returns `true` when `auth` config key is `true` |
| `src/Support/MqttConnectionConfig.php` | `->retain()` | Returns the retain flag (per-connection or default) |
| `src/Support/MqttConnectionConfig.php` | `->cleanSession()` | Returns the clean session flag (per-connection or default) |
| `src/Support/MqttConnectionConfig.php` | `->prefix()` | Returns the topic prefix string |
| `src/Support/MqttConnectionConfig.php` | `->useTls()` | Returns `true` when TLS is enabled |
| `src/Support/MqttConnectionConfig.php` | `->selfSignedAllowed()` | Returns `true` when self-signed TLS certs are allowed |
| `src/Support/MqttConnectionConfig.php` | `validateHost()` | Non-empty string check |
| `src/Support/MqttConnectionConfig.php` | `validatePort()` | Integer 1–65535 range check |
| `src/Support/MqttConnectionConfig.php` | `validateQos()` | Integer 0, 1, or 2 |
| `src/Support/MqttConnectionConfig.php` | `validateTimeout()` | Positive integer check |
| `src/Support/MqttConnectionConfig.php` | `validateAliveInterval()` | Positive integer check |
| `src/Support/MqttConnectionConfig.php` | `validateAuthCredentials()` | Username + password required when `auth=true` |
| `src/Factories/MqttClientFactory.php` | `MqttClientFactory` | Creates configured-but-not-connected MQTT clients |
| `src/Factories/MqttClientFactory.php` | `create($connection, $clientId)` | Config-name-based client creation |
| `src/Factories/MqttClientFactory.php` | `createFromConfig($config, $clientId)` | Type-safe creation from validated VO |
| `src/Factories/MqttClientFactory.php` | `getConnectionSettings($connection)` | Builds `ConnectionSettings` with auth/TLS |
| `src/Factories/MqttClientFactory.php` | `getConnectionSettingsFromConfig($config)` | Type-safe settings from validated VO |
| `src/MqttBroadcast.php` | `MqttBroadcast::getTopic($topic, $broker)` | Resolves prefixed topic string for a given broker |

## Configuration

All connection config lives under `mqtt-broadcast.connections.{name}`:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `host` | `string` | `127.0.0.1` | MQTT broker hostname (required) |
| `port` | `int` | `1883` | MQTT broker port (required, 1–65535) |
| `auth` | `bool` | `false` | Enable authentication. When `true`, `username` and `password` are validated as required |
| `username` | `string\|null` | `null` | Auth username (required when `auth=true`) |
| `password` | `string\|null` | `null` | Auth password (required when `auth=true`) |
| `prefix` | `string` | `''` | Topic prefix prepended to all topics via `MqttBroadcast::getTopic()` |
| `use_tls` | `bool` | `false` | Enable TLS/SSL encryption (only applied when `auth=true`) |
| `clientId` | `string\|null` | `null` | Fixed client ID (null = auto-generate UUID) |
| `qos` | `int\|null` | `null` | Per-connection QoS override (falls back to default) |
| `retain` | `bool\|null` | `null` | Per-connection retain override (falls back to default). Also read directly by `MqttMessageJob` constructor |
| `clean_session` | `bool\|null` | `null` | Per-connection clean session override (falls back to default). Publishers always use `true`; subscribers use the config value |

Global defaults under `mqtt-broadcast.defaults.connection`:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `qos` | `int` | `0` | Quality of Service (0=at most once, 1=at least once, 2=exactly once) |
| `retain` | `bool` | `false` | Retain messages on broker |
| `clean_session` | `bool` | `false` | Request clean session on connect |
| `alive_interval` | `int` | `60` | Keep-alive interval in seconds |
| `timeout` | `int` | `3` | Connection timeout in seconds |
| `self_signed_allowed` | `bool` | `true` | Allow self-signed TLS certificates |
| `max_retries` | `int` | `20` | Reconnection max attempts |
| `max_retry_delay` | `int` | `60` | Max seconds between reconnection attempts |
| `max_failure_duration` | `int` | `3600` | Max seconds of continuous failure before giving up |
| `terminate_on_max_retries` | `bool` | `false` | Kill process after max retries exhausted |

### Default Merging Logic

```php
$defaults = config('mqtt-broadcast.defaults.connection', []);
$config = array_merge($defaults, array_filter($config, fn ($value) => $value !== null));
```

Connection-level values override defaults. `null` values in the connection config are stripped before merging, so the default applies. This means setting a connection key to `null` explicitly will fall through to the default — there is no way to "unset" a default.

## Error Handling

All validation errors throw `MqttBroadcastException` with descriptive messages:

| Scenario | Exception Message Pattern | Recovery |
|----------|--------------------------|----------|
| Connection not in config | `Connection "{name}" is not configured` | Add connection to `config/mqtt-broadcast.php` |
| Missing `host` | `Connection "{name}" is missing required configuration: host` | Set `host` in connection config |
| Missing `port` | `Connection "{name}" is missing required configuration: port` | Set `port` in connection config |
| Invalid host type | `Connection "{name}" has invalid host: must be non-empty string, got: {type}` | Fix host value |
| Port out of range | `Connection "{name}" has invalid port: must be between 1 and 65535, got: {value}` | Fix port value |
| Invalid QoS | `Connection "{name}" has invalid qos: must be 0, 1, or 2, got: {value}` | Use 0, 1, or 2 |
| Non-positive timeout | `Connection "{name}" has invalid timeout: must be greater than 0, got: {value}` | Use positive integer |
| Non-positive alive_interval | `Connection "{name}" has invalid alive_interval: must be greater than 0, got: {value}` | Use positive integer |
| Auth enabled, missing username | `Connection "{name}" has auth enabled but missing or invalid username` | Set username or disable auth |
| Auth enabled, missing password | `Connection "{name}" has auth enabled but missing or invalid password` | Set password or disable auth |

In `MqttMessageJob`, config errors are caught and the job is **failed immediately** (`$this->fail($e)`) without retry — config errors won't fix themselves between retries. Network errors are left to Laravel's standard retry mechanism.

## Mermaid Diagrams

### Config Resolution Flow

```mermaid
flowchart TD
    A["MqttConnectionConfig::fromConnection('broker-name')"] --> B["Read config('mqtt-broadcast.connections.broker-name')"]
    B -->|null| ERR1["MqttBroadcastException: not configured"]
    B -->|found| C["Read defaults: config('mqtt-broadcast.defaults.connection')"]
    C --> D["Strip nulls from connection config"]
    D --> E["array_merge(defaults, connection)"]
    E --> F["fromArray(merged, 'broker-name')"]
    F --> V1["validateRequired: host"]
    V1 -->|missing| ERR2["MqttBroadcastException"]
    V1 -->|ok| V2["validateRequired: port"]
    V2 -->|missing| ERR2
    V2 -->|ok| V3["validateHost: non-empty string"]
    V3 -->|invalid| ERR2
    V3 -->|ok| V4["validatePort: 1-65535"]
    V4 -->|invalid| ERR2
    V4 -->|ok| V5["validateQos: 0, 1, or 2"]
    V5 -->|invalid| ERR2
    V5 -->|ok| V6["validateTimeout: > 0"]
    V6 -->|invalid| ERR2
    V6 -->|ok| V7["validateAliveInterval: > 0"]
    V7 -->|invalid| ERR2
    V7 -->|ok| V8{"auth == true?"}
    V8 -->|yes| V9["validateAuthCredentials"]
    V9 -->|invalid| ERR2
    V9 -->|ok| OK["Return immutable MqttConnectionConfig"]
    V8 -->|no| OK
```

### Client Creation & Connection Flow

```mermaid
sequenceDiagram
    participant Caller as MqttMessageJob / BrokerSupervisor
    participant Factory as MqttClientFactory
    participant Config as MqttConnectionConfig
    participant Client as MqttClient

    Caller->>Factory: create('default', $clientId)
    Factory->>Config: fromConnection('default')
    Config->>Config: merge defaults + validate
    Config-->>Factory: validated config VO
    Factory->>Factory: resolve clientId (param > config > UUID)
    Factory->>Client: new MqttClient(host, port, clientId)
    Factory-->>Caller: configured, not-connected client

    Caller->>Factory: getConnectionSettings('default', $cleanSession)
    Factory->>Config: fromConnection('default')
    Config-->>Factory: validated config VO

    alt auth required
        Factory->>Factory: build ConnectionSettings (TLS, keepalive, auth)
        Factory-->>Caller: {settings: ConnectionSettings, cleanSession: bool}
        Caller->>Client: connect(settings, cleanSession)
    else no auth
        Factory-->>Caller: {settings: null, cleanSession: false}
        Caller->>Client: connect()
    end
```

### Publisher Connection Lifecycle

```mermaid
sequenceDiagram
    participant Job as MqttMessageJob::handle()
    participant Mqtt as MqttMessageJob::mqtt()
    participant Factory as MqttClientFactory
    participant Config as MqttConnectionConfig
    participant Client as MqttClient

    Job->>Mqtt: mqtt()
    Mqtt->>Mqtt: $publisherClientId = Str::uuid()
    Mqtt->>Factory: create($broker, $publisherClientId)
    Factory->>Config: fromConnection($broker)
    Config-->>Factory: validated VO
    Factory->>Client: new MqttClient(host, port, uuid)
    Factory-->>Mqtt: client (not connected)

    Mqtt->>Factory: getConnectionSettings($broker, $cleanSession=true)
    Factory->>Config: fromConnection($broker)

    alt auth=true
        Factory-->>Mqtt: {settings: ConnectionSettings, cleanSession: true}
        Mqtt->>Client: connect(settings, true)
    else auth=false
        Factory-->>Mqtt: {settings: null, cleanSession: false}
    end

    Mqtt-->>Job: client (may or may not be connected)

    alt not connected
        Job->>Client: connect()
    end

    Job->>Job: json_encode($message) if not string
    Job->>Client: publish(getTopic(), $message, $cachedQos, $cachedRetain)
    Job->>Client: disconnect()
```

### Topic Prefix Resolution

```mermaid
flowchart LR
    A["MqttBroadcast::getTopic('sensors/temp', 'default')"] --> B["validateBrokerConfiguration('default')"]
    B -->|invalid| ERR["MqttBroadcastException"]
    B -->|ok| C["Read config prefix: 'home/'"]
    C --> D["Concatenate: 'home/' + 'sensors/temp'"]
    D --> E["Result: 'home/sensors/temp'"]

    F["MqttBroadcast::getTopic('sensors/temp', 'default')"] --> G["Prefix is empty ''"]
    G --> H["Result: 'sensors/temp'"]
```

### Client ID Strategy

```mermaid
flowchart LR
    subgraph Publisher ["MqttMessageJob (publisher)"]
        P1["Str::uuid()"] --> P2["Random UUID per publish"]
    end

    subgraph Subscriber ["BrokerSupervisor (subscriber)"]
        S1{"config clientId set?"} -->|yes| S2["Use config clientId"]
        S1 -->|no| S3["Auto-generate UUID"]
    end

    P2 --> WHY1["Avoids client ID collision<br>with long-lived subscriber"]
    S2 --> WHY2["Enables persistent sessions<br>broker remembers subscriptions"]
```
