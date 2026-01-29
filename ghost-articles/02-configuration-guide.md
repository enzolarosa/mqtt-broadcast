---
title: "MQTT Broadcast Configuration Guide"
slug: mqtt-broadcast-configuration
excerpt: "Complete reference for configuring MQTT Broadcast: multiple brokers, environment-specific connections, TLS/SSL, memory management, and advanced options."
feature_image: null
featured: false
tags:
  - mqtt-broadcast
  - laravel
  - configuration
author: Enzo La Rosa
---

This guide covers all configuration options available in MQTT Broadcast, from basic connection settings to advanced performance tuning.

---

## Configuration File Structure

The configuration file (`config/mqtt-broadcast.php`) is organized into sections:

1. **Quick Start** - Minimal required settings
2. **Environment Configuration** - Per-environment broker selection
3. **Dashboard & Monitoring** - Dashboard access and customization
4. **Message Logging** - Database logging options
5. **Advanced Options** - Performance tuning and protocol settings

---

## Quick Start Configuration

The minimal configuration requires only broker connection details:

```php
'connections' => [
    'default' => [
        // Required
        'host' => env('MQTT_HOST', '127.0.0.1'),
        'port' => env('MQTT_PORT', 1883),

        // Optional
        'username' => env('MQTT_USERNAME'),
        'password' => env('MQTT_PASSWORD'),
        'prefix' => env('MQTT_PREFIX', ''),
        'use_tls' => env('MQTT_USE_TLS', false),
        'clientId' => env('MQTT_CLIENT_ID'),
    ],
],
```

### Connection Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `host` | string | `127.0.0.1` | MQTT broker hostname or IP |
| `port` | int | `1883` | Broker port (1883 standard, 8883 for TLS) |
| `username` | string | `null` | Authentication username (if required) |
| `password` | string | `null` | Authentication password (if required) |
| `prefix` | string | `''` | Topic prefix for all messages |
| `use_tls` | bool | `false` | Enable TLS/SSL encryption |
| `clientId` | string | auto | MQTT client identifier (auto-generated if not set) |

### Example `.env` Configuration

```env
# Basic connection
MQTT_HOST=mqtt.example.com
MQTT_PORT=1883

# With authentication
MQTT_USERNAME=my_app
MQTT_PASSWORD=secure_password_123

# With topic prefix
MQTT_PREFIX=production/myapp/

# With TLS/SSL
MQTT_USE_TLS=true
MQTT_PORT=8883
```

---

## Environment-Specific Brokers

Configure different brokers for each environment:

```php
'environments' => [
    'local' => ['default'],
    'staging' => ['staging-broker'],
    'production' => ['default', 'backup'],
],
```

### How It Works

1. By default, uses `config('app.env')` (your `APP_ENV`)
2. Looks up which brokers to use in the `environments` array
3. Connects to all listed brokers simultaneously

### Examples

**Single broker per environment:**

```php
'connections' => [
    'local-broker' => [
        'host' => '127.0.0.1',
        'port' => 1883,
    ],
    'production-broker' => [
        'host' => 'mqtt.example.com',
        'port' => 8883,
        'use_tls' => true,
    ],
],

'environments' => [
    'local' => ['local-broker'],
    'production' => ['production-broker'],
],
```

**Multiple brokers for redundancy:**

```php
'connections' => [
    'primary' => [
        'host' => 'mqtt-primary.example.com',
        'port' => 8883,
        'use_tls' => true,
    ],
    'backup' => [
        'host' => 'mqtt-backup.example.com',
        'port' => 8883,
        'use_tls' => true,
    ],
],

'environments' => [
    'production' => ['primary', 'backup'],
    'local' => ['primary'],
],
```

### Override Environment via CLI

Force a specific environment when starting:

```bash
# Use production brokers even in local environment
php artisan mqtt-broadcast --environment=production

# Use staging brokers
php artisan mqtt-broadcast --environment=staging
```

---

## Topic Prefix

The prefix is prepended to ALL topics when subscribing and publishing:

### Without Prefix

```php
'prefix' => '',
```

```php
// Subscribe to: sensors/temp
// Publish to: sensors/temp
MqttBroadcast::publish('sensors/temp', '25.5');
```

### With Prefix

```php
'prefix' => 'myapp/',
```

```php
// Subscribe to: myapp/#
// Publish to: myapp/sensors/temp
MqttBroadcast::publish('sensors/temp', '25.5');
```

**Important:** External devices must publish to the full topic including prefix:

```bash
# Without prefix
mosquitto_pub -t 'sensors/temp' -m '25.5'

# With prefix 'myapp/'
mosquitto_pub -t 'myapp/sensors/temp' -m '25.5'
```

### Multi-Tenant Prefix Example

```php
// config/mqtt-broadcast.php
'connections' => [
    'tenant-a' => [
        'host' => 'mqtt.example.com',
        'prefix' => 'tenant-a/',
    ],
    'tenant-b' => [
        'host' => 'mqtt.example.com',
        'prefix' => 'tenant-b/',
    ],
],

'environments' => [
    'production' => ['tenant-a', 'tenant-b'],
],
```

---

## TLS/SSL Configuration

Enable encrypted connections to your MQTT broker:

```php
'connections' => [
    'secure' => [
        'host' => 'mqtt.example.com',
        'port' => 8883,
        'use_tls' => true,
        'username' => 'user',
        'password' => 'pass',
    ],
],
```

### Self-Signed Certificates

Allow self-signed certificates (not recommended for production):

```php
'defaults' => [
    'connection' => [
        'self_signed_allowed' => true,
    ],
],
```

### Certificate Paths

For custom certificate validation, configure via environment:

```env
MQTT_CA_CERT=/path/to/ca.crt
MQTT_CLIENT_CERT=/path/to/client.crt
MQTT_CLIENT_KEY=/path/to/client.key
```

---

## Dashboard Configuration

Customize the real-time monitoring dashboard:

```php
'path' => env('MQTT_BROADCAST_PATH', 'mqtt-broadcast'),
'domain' => env('MQTT_BROADCAST_DOMAIN', null),
'middleware' => ['web', \enzolarosa\MqttBroadcast\Http\Middleware\Authorize::class],
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `path` | URL path for dashboard | `mqtt-broadcast` |
| `domain` | Custom domain for dashboard | `null` (uses app domain) |
| `middleware` | Middleware stack | `['web', Authorize::class]` |

### Custom Path

```env
MQTT_BROADCAST_PATH=admin/mqtt-monitor
```

Access at: `http://your-app.test/admin/mqtt-monitor`

### Custom Domain

```env
MQTT_BROADCAST_DOMAIN=mqtt.example.com
```

Access at: `http://mqtt.example.com`

### Authentication

Configure access control via Laravel Gates:

```php
// app/Providers/AuthServiceProvider.php

Gate::define('viewMqttBroadcast', function ($user) {
    return $user->isAdmin();
});
```

**In local environment:** Authentication is always bypassed for convenience.

---

## Message Logging

Enable database logging for all received messages:

```php
'logs' => [
    'enable' => env('MQTT_LOG_ENABLE', false),
    'queue' => env('MQTT_LOG_JOB_QUEUE', 'default'),
    'connection' => env('MQTT_LOG_CONNECTION', 'mysql'),
    'table' => env('MQTT_LOG_TABLE', 'mqtt_loggers'),
],
```

### Enable Logging

```env
MQTT_LOG_ENABLE=true
```

### View Logs

```php
use enzolarosa\MqttBroadcast\Models\MqttLogger;

// Recent messages
$messages = MqttLogger::latest()->take(100)->get();

// Filter by topic
$temps = MqttLogger::where('topic', 'LIKE', 'sensors/temp%')->get();

// Today's messages
$today = MqttLogger::whereDate('created_at', today())->get();
```

### Clean Up Old Logs

Schedule a cleanup task:

```php
// app/Console/Kernel.php

$schedule->command('mqtt-broadcast:cleanup-logs --days=30')->daily();
```

---

## Advanced Options

### MQTT Protocol Settings

```php
'defaults' => [
    'connection' => [
        'qos' => 0,                    // Quality of Service (0, 1, or 2)
        'retain' => false,             // Retain messages on broker
        'clean_session' => false,      // Clean session flag
        'alive_interval' => 60,        // Keep-alive interval (seconds)
        'timeout' => 3,                // Connection timeout (seconds)
        'self_signed_allowed' => true, // Allow self-signed certificates
    ],
],
```

### Reconnection Behavior

```php
'defaults' => [
    'connection' => [
        'max_retries' => env('MQTT_MAX_RETRIES', 20),
        'max_retry_delay' => env('MQTT_MAX_RETRY_DELAY', 60),
        'max_failure_duration' => env('MQTT_MAX_FAILURE_DURATION', 3600),
        'terminate_on_max_retries' => env('MQTT_TERMINATE_ON_MAX_RETRIES', false),
    ],
],
```

**Retry Logic:**
1. First reconnection attempt: immediate
2. Subsequent attempts: exponential backoff (2s, 4s, 8s, ..., up to `max_retry_delay`)
3. After `max_retries` or `max_failure_duration`: terminate or keep trying

### Memory Management

```php
'memory' => [
    'gc_interval' => env('MQTT_GC_INTERVAL', 100),
    'threshold_mb' => env('MQTT_MEMORY_THRESHOLD_MB', 128),
    'auto_restart' => env('MQTT_MEMORY_AUTO_RESTART', true),
    'restart_delay_seconds' => env('MQTT_RESTART_DELAY_SECONDS', 10),
],
```

**How it works:**
- Every `gc_interval` messages, checks memory usage
- If above `threshold_mb`, logs warning
- If `auto_restart` enabled, gracefully restarts supervisor
- Waits `restart_delay_seconds` before restarting

**Increase for high-volume systems:**

```env
MQTT_MEMORY_THRESHOLD_MB=512
MQTT_GC_INTERVAL=500
```

### Queue Configuration

```php
'queue' => [
    'name' => env('MQTT_JOB_QUEUE', 'default'),
    'listener' => env('MQTT_LISTENER_QUEUE', 'default'),
    'connection' => env('MQTT_JOB_CONNECTION', 'redis'),
],
```

**Use dedicated queue for MQTT:**

```env
MQTT_JOB_QUEUE=mqtt
MQTT_LISTENER_QUEUE=mqtt-listeners
MQTT_JOB_CONNECTION=redis
```

Start workers:

```bash
php artisan queue:work redis --queue=mqtt
php artisan queue:work redis --queue=mqtt-listeners
```

---

## Example Configurations

### Basic Local Development

```env
MQTT_HOST=127.0.0.1
MQTT_PORT=1883
```

### Production with TLS

```env
MQTT_HOST=mqtt.production.com
MQTT_PORT=8883
MQTT_USE_TLS=true
MQTT_USERNAME=prod_user
MQTT_PASSWORD=secure_pass_123
MQTT_PREFIX=production/
MQTT_LOG_ENABLE=true
MQTT_MEMORY_THRESHOLD_MB=256
```

### High-Volume IoT System

```env
MQTT_HOST=iot-broker.example.com
MQTT_PORT=8883
MQTT_USE_TLS=true
MQTT_PREFIX=iot/

# Memory & Performance
MQTT_MEMORY_THRESHOLD_MB=512
MQTT_GC_INTERVAL=500

# Dedicated Queues
MQTT_JOB_QUEUE=iot-mqtt
MQTT_LISTENER_QUEUE=iot-processing
MQTT_JOB_CONNECTION=redis

# Logging
MQTT_LOG_ENABLE=false  # Too many messages, use events instead
```

---

## Validation

Test your configuration:

```bash
# Check connection
php artisan tinker
>>> \enzolarosa\MqttBroadcast\Models\BrokerProcess::all();

# View active processes
>>> \enzolarosa\MqttBroadcast\Models\BrokerProcess::where('status', 'running')->count();

# Test publishing
>>> \enzolarosa\MqttBroadcast\Facades\MqttBroadcast::publish('test', 'hello');
```

---

## Next Steps

- [**IoT Temperature Monitoring Tutorial**](https://enzolarosa.dev/tutorials/iot-temperature-monitoring) - Build a complete IoT system
- [**Production Deployment Guide**](https://enzolarosa.dev/docs/mqtt-broadcast-production-deployment) - Deploy with Supervisor
- [**Troubleshooting**](https://enzolarosa.dev/docs/mqtt-broadcast-troubleshooting) - Common issues and solutions

---

**Previous:** [← Getting Started](https://enzolarosa.dev/docs/mqtt-broadcast-getting-started)
**Next:** [Production Deployment →](https://enzolarosa.dev/docs/mqtt-broadcast-production-deployment)
