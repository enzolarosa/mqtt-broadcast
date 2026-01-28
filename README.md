# MQTT Broadcast for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/enzolarosa/mqtt-broadcast.svg?style=flat-square)](https://packagist.org/packages/enzolarosa/mqtt-broadcast)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/enzolarosa/mqtt-broadcast/run-tests?label=tests)](https://github.com/enzolarosa/mqtt-broadcast/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/enzolarosa/mqtt-broadcast.svg?style=flat-square)](https://packagist.org/packages/enzolarosa/mqtt-broadcast)

**Production-ready MQTT integration for Laravel** with robust supervisor architecture, multi-broker support, and automatic reconnection handling.

Built using the **Laravel Horizon pattern** for reliable, long-running processes with graceful shutdown and monitoring capabilities.

## Features

- 🚀 **Horizon-Style Architecture** - Battle-tested supervisor pattern from Laravel Horizon
- 🔄 **Multiple Broker Support** - Connect to multiple MQTT brokers simultaneously
- 📡 **Pub/Sub Made Easy** - Publish and subscribe to MQTT topics with Laravel queues
- 🛡️ **Auto-Reconnection** - Exponential backoff with configurable retry limits
- 💪 **Graceful Shutdown** - Clean termination with SIGTERM signal handling
- 📊 **Database Logging** - Optional message logging to database
- 🔐 **TLS/SSL Support** - Secure connections with certificate validation
- ⚡ **Queue Integration** - Async message publishing via Laravel queues
- 🎯 **Type-Safe** - Full PHP 8.1+ type declarations

## Architecture

This package uses a **three-tier supervisor architecture** inspired by Laravel Horizon:

```
MasterSupervisor (Process Orchestrator)
    ├── BrokerSupervisor (MQTT Connection #1)
    ├── BrokerSupervisor (MQTT Connection #2)
    └── BrokerSupervisor (MQTT Connection #3)
```

- **MasterSupervisor**: Manages multiple broker connections, handles signals, persists state
- **BrokerSupervisor**: Manages single MQTT connection with auto-reconnection
- **MqttClientFactory**: Creates configured MQTT clients with auth/TLS support

For detailed architecture documentation, see [ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Requirements

- PHP 8.1 or higher
- Laravel 9.x, 10.x, or 11.x
- MQTT Broker (e.g., Mosquitto, HiveMQ, AWS IoT Core)

## Installation

Install via Composer:

```bash
composer require enzolarosa/mqtt-broadcast
```

Publish and run migrations:

```bash
php artisan vendor:publish --tag="mqtt-broadcast-migrations"
php artisan migrate
```

Publish configuration file:

```bash
php artisan vendor:publish --tag="mqtt-broadcast-config"
```

## Configuration

### Basic Configuration

Edit `config/mqtt-broadcast.php`:

```php
return [
    'connections' => [
        'default' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => env('MQTT_PORT', 1883),
            'auth' => env('MQTT_AUTH', false),
            'username' => env('MQTT_USERNAME'),
            'password' => env('MQTT_PASSWORD'),
            'qos' => env('MQTT_QOS', 0),
            'retain' => env('MQTT_RETAIN', false),
            'prefix' => env('MQTT_PREFIX', ''),
            'clientId' => env('MQTT_CLIENT_ID'),

            // Advanced options
            'alive_interval' => 60,
            'timeout' => 3,
            'use_tls' => false,
            'self_signed_allowed' => true,
        ],
    ],

    'environments' => [
        'local' => ['default'],
        'production' => ['default', 'backup'],
    ],
];
```

### Environment Variables

Add to your `.env` file:

```env
MQTT_HOST=127.0.0.1
MQTT_PORT=1883
MQTT_AUTH=false
MQTT_USERNAME=
MQTT_PASSWORD=
MQTT_QOS=0
MQTT_PREFIX=myapp/
```

### Multiple Brokers

Configure multiple connections for redundancy:

```php
'connections' => [
    'primary' => [
        'host' => 'mqtt.example.com',
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
    'staging' => ['primary'],
    'local' => ['default'],
],
```

## Usage

### Starting the Subscriber

Start the MQTT subscriber daemon:

```bash
# Use default environment (from APP_ENV)
php artisan mqtt-broadcast

# Specify environment explicitly
php artisan mqtt-broadcast --environment=production

# The command will:
# ✓ Connect to all brokers configured for the environment
# ✓ Subscribe to topics with prefix (if configured)
# ✓ Dispatch Laravel events for received messages
# ✓ Handle reconnection automatically
# ✓ Respond to SIGTERM for graceful shutdown
```

### Publishing Messages

#### Method 1: Using Facade (Async via Queue)

```php
use enzolarosa\MqttBroadcast\Facades\MqttBroadcast;

// Simple publish
MqttBroadcast::publish('sensors/temperature', '25.5');

// Publish with custom broker
MqttBroadcast::publish('alerts/critical', 'High temperature!', 'backup');

// Publish with custom QoS
MqttBroadcast::publish('important/data', $data, 'default', qos: 2);
```

#### Method 2: Using Job Directly

```php
use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;

MqttMessageJob::dispatch(
    topic: 'home/livingroom/temperature',
    message: json_encode(['value' => 22.5, 'unit' => 'celsius']),
    broker: 'default',
    qos: 1
);
```

#### Method 3: Synchronous Publish

```php
// Publish immediately (not queued)
MqttBroadcast::publishSync('urgent/alert', 'System down!');
```

### Receiving Messages

Listen to the `MqttMessageReceived` event:

```php
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Support\Facades\Event;

Event::listen(MqttMessageReceived::class, function ($event) {
    echo "Topic: {$event->topic}\n";
    echo "Message: {$event->message}\n";
    echo "Broker: {$event->broker}\n";

    // Your business logic here
    if ($event->topic === 'sensors/temperature') {
        $data = json_decode($event->message, true);
        // Store to database, trigger actions, etc.
    }
});
```

Or create a dedicated listener:

```bash
php artisan make:listener HandleMqttMessage
```

```php
namespace App\Listeners;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;

class HandleMqttMessage
{
    public function handle(MqttMessageReceived $event): void
    {
        match(true) {
            str_starts_with($event->topic, 'sensors/') => $this->handleSensor($event),
            str_starts_with($event->topic, 'alerts/') => $this->handleAlert($event),
            default => logger()->info('Unknown MQTT topic', ['topic' => $event->topic]),
        };
    }

    private function handleSensor(MqttMessageReceived $event): void
    {
        // Handle sensor data
    }

    private function handleAlert(MqttMessageReceived $event): void
    {
        // Handle alert
    }
}
```

Register in `EventServiceProvider`:

```php
protected $listen = [
    MqttMessageReceived::class => [
        HandleMqttMessage::class,
    ],
];
```

### Managing the Process

#### Stop Gracefully

```bash
# Terminate all brokers on this machine
php artisan mqtt-broadcast:terminate

# Terminate specific broker
php artisan mqtt-broadcast:terminate worker-hostname-abc123
```

#### Process Management

Use a process manager like **Supervisor** for production:

```ini
[program:mqtt-broadcast]
process_name=%(program_name)s
command=php /path/to/artisan mqtt-broadcast --environment=production
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/mqtt-broadcast.log
stopwaitsecs=60
```

Or use **systemd**:

```ini
[Unit]
Description=MQTT Broadcast Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/myapp
ExecStart=/usr/bin/php artisan mqtt-broadcast --environment=production
Restart=on-failure
RestartSec=5s

[Install]
WantedBy=multi-user.target
```

## Advanced Usage

### Topic Prefixes

Automatically prefix all topics:

```php
'connections' => [
    'default' => [
        'prefix' => 'myapp/production/',
    ],
],
```

```php
// Publishes to: myapp/production/sensors/temp
MqttBroadcast::publish('sensors/temp', '25.5');
```

### QoS Levels

```php
// QoS 0: At most once (fire and forget)
MqttBroadcast::publish('logs/info', 'message', qos: 0);

// QoS 1: At least once (acknowledged delivery)
MqttBroadcast::publish('sensors/data', 'message', qos: 1);

// QoS 2: Exactly once (guaranteed delivery)
MqttBroadcast::publish('financial/transaction', 'message', qos: 2);
```

### Retained Messages

```php
'connections' => [
    'default' => [
        'retain' => true, // Last message persists on broker
    ],
],
```

### Message Logging

Enable database logging:

```php
'logs' => [
    'enable' => true,
    'connection' => 'mysql',
    'table' => 'mqtt_loggers',
],
```

Query logged messages:

```php
use enzolarosa\MqttBroadcast\Models\MqttLogger;

$recentMessages = MqttLogger::latest()->limit(100)->get();
$sensorData = MqttLogger::where('topic', 'like', 'sensors/%')->get();
```

## Testing

Run the test suite:

```bash
composer test
```

Run specific test suites:

```bash
# Unit tests only
vendor/bin/pest tests/Unit

# Integration tests (some require real MQTT broker)
vendor/bin/pest tests/Integration
```

For testing limitations and manual testing guide, see [docs/TESTING_LIMITATIONS.md](docs/TESTING_LIMITATIONS.md).

## Upgrading

### From 2.x to 3.0

Version 3.0 introduces breaking changes. See [UPGRADE.md](UPGRADE.md) for detailed migration guide.

**Key changes:**
- Model renamed: `Brokers` → `BrokerProcess`
- Service class deprecated (use new architecture)
- Command usage updated (`--environment` flag)

## Troubleshooting

### Connection Issues

```bash
# Test MQTT broker connectivity
mosquitto_sub -h mqtt.example.com -p 1883 -t '#' -v

# Check broker process status
php artisan mqtt-broadcast:terminate --dry-run
```

### Memory Management

The package automatically handles memory management for long-running supervisor processes:

- **Automatic Garbage Collection**: Runs periodically (default: every 100 iterations)
- **Memory Monitoring**: Tracks current and peak memory usage
- **Threshold Warnings**: Alerts at 80% and 100% of configured limit
- **Auto-Restart**: Optional automatic restart when memory limit is exceeded

Configure memory management in `config/mqtt-broadcast.php`:

```php
'memory' => [
    'gc_interval' => 100,              // GC every N iterations
    'threshold_mb' => 128,              // Memory limit (Laravel standard)
    'auto_restart' => true,             // Enable auto-restart
    'restart_delay_seconds' => 10,      // Grace period before restart
],
```

**Note**: Manual `gc_collect_cycles()` in listeners is no longer necessary. The supervisor handles memory cleanup automatically.

### Reconnection Issues

Configure retry behavior in supervisor:

```php
// config/mqtt-broadcast.php
'supervisor' => [
    'max_retries' => 5,
    'max_retry_delay' => 30, // seconds
    'terminate_on_max_retries' => false,
],
```

## Documentation

- [Architecture Guide](docs/ARCHITECTURE.md) - Detailed architecture explanation
- [Upgrade Guide](UPGRADE.md) - Migration between major versions
- [Changelog](CHANGELOG.md) - Version history
- [Testing Limitations](docs/TESTING_LIMITATIONS.md) - Test coverage details
- [Horizon Pattern Analysis](docs/HORIZON_PATTERN_ANALYSIS.md) - Design decisions

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Vincenzo La Rosa](https://github.com/enzolarosa)
- [All Contributors](../../contributors)

Inspired by [Laravel Horizon](https://laravel.com/docs/horizon) architecture.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
