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

## 📊 Real-Time Dashboard

MQTT Broadcast includes a **beautiful real-time monitoring dashboard** built with React 19:

<!-- ![Dashboard Screenshot](docs/images/dashboard-preview.png) -->
> 💡 **Dashboard Preview:** Add a screenshot to `docs/images/dashboard-preview.png` to display here

**Access:** `http://your-app.test/mqtt-broadcast`

**Features:**
- 📈 **Live Throughput Charts** - Message rate over time (minute/hour/day views)
- 🖥️ **Broker Status** - Real-time connection monitoring (connected/idle/reconnecting/disconnected)
- 📝 **Message Log** - Recent messages with topic filtering and search
- 💾 **Memory Usage** - Supervisor memory consumption with alerts
- ⚡ **Queue Metrics** - Pending jobs monitoring
- 🌓 **Dark Mode** - Automatic theme switching

### Dashboard Authentication

**Local development:** Dashboard is always accessible (no authentication required).

**Production:** Configure access control with Laravel Gates:

```php
// app/Providers/AuthServiceProvider.php

use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewMqttBroadcast', function ($user) {
        return in_array($user->email, [
            'admin@example.com',
            'devops@example.com',
        ]);
    });

    // Or check by role
    Gate::define('viewMqttBroadcast', function ($user) {
        return $user->hasRole('admin');
    });
}
```

**Customize dashboard path:**

```env
MQTT_BROADCAST_PATH=my-mqtt-monitor
```

Access at: `http://your-app.test/my-mqtt-monitor`

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

- PHP 8.3 or higher
- Laravel 11.x (Laravel 9.x and 10.x also supported)
- MQTT Broker (e.g., Mosquitto, HiveMQ, AWS IoT Core)

## Quick Start

Get up and running in 2 minutes:

**1. Install the package:**

```bash
composer require enzolarosa/mqtt-broadcast
```

**2. Run migrations (auto-discovered):**

```bash
php artisan migrate
```

**3. Publish config:**

```bash
php artisan vendor:publish --tag="mqtt-broadcast-config"
```

**4. Configure your MQTT broker in `.env`:**

```env
MQTT_HOST=127.0.0.1
MQTT_PORT=1883

# Optional: if your broker requires authentication
MQTT_USERNAME=your_username
MQTT_PASSWORD=your_password
```

**5. Start the subscriber:**

```bash
php artisan mqtt-broadcast
```

**6. Listen to messages in your code:**

```php
// app/Providers/EventServiceProvider.php or routes/console.php

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Support\Facades\Event;

Event::listen(MqttMessageReceived::class, function ($event) {
    logger()->info('MQTT Message:', [
        'topic' => $event->topic,
        'message' => $event->message,
        'broker' => $event->broker,
    ]);
});
```

**7. View the dashboard:**

Open `http://your-app.test/mqtt-broadcast` to see real-time monitoring!

---

## Complete Examples

### IoT Temperature Monitoring System

Learn how to build a complete IoT system with MQTT in 15 minutes:

**[View Complete Example →](examples/iot-temperature-monitor/README.md)**

This end-to-end guide shows you how to:
- Connect ESP32/Arduino sensors to your Laravel app
- Store temperature readings in database
- Create real-time dashboards
- Send email alerts on threshold violations
- Deploy to production with Supervisor

**Includes:**
- Laravel event listeners and API endpoints
- ESP32 Arduino sketch with WiFi and MQTT
- Testing and deployment instructions
- Troubleshooting guide

---

## Configuration

The config file has been simplified into clear sections:

### Quick Start Section (Required)

```php
// config/mqtt-broadcast.php

'connections' => [
    'default' => [
        'host' => env('MQTT_HOST', '127.0.0.1'),
        'port' => env('MQTT_PORT', 1883),
        'username' => env('MQTT_USERNAME'),  // Optional
        'password' => env('MQTT_PASSWORD'),  // Optional
        'prefix' => env('MQTT_PREFIX', ''),  // Optional
    ],
],
```

That's it! Everything else has sensible defaults.

### Environment-Specific Brokers

The package automatically selects which brokers to use based on your `APP_ENV`:

```php
'environments' => [
    'local' => ['default'],
    'staging' => ['default'],
    'production' => ['default', 'backup'],
],
```

**How it works:**
- By default, uses the environment from `config('app.env')` (your `APP_ENV`)
- If `local`: Connects to brokers listed in `'local'` array
- If `production`: Connects to brokers listed in `'production'` array
- Override with command flag: `php artisan mqtt-broadcast --environment=staging`

**Example:**
```bash
# Use APP_ENV (automatic)
php artisan mqtt-broadcast

# Force production brokers (even in local)
php artisan mqtt-broadcast --environment=production
```

**Adding new environments:**
```php
'environments' => [
    'local' => ['default'],
    'staging' => ['staging-broker'],
    'production' => ['default', 'backup'],
    'testing' => ['test-broker'],  // Add your custom environment
],
```

If your `APP_ENV` is not listed (e.g., `APP_ENV=development`), the supervisor will throw an error. Add it to the config or use the `--environment` flag.

### Multiple Brokers

Configure multiple connections for redundancy:

```php
'connections' => [
    'primary' => [
        'host' => env('MQTT_PRIMARY_HOST', 'mqtt.example.com'),
        'port' => env('MQTT_PRIMARY_PORT', 8883),
        'username' => env('MQTT_PRIMARY_USERNAME'),
        'password' => env('MQTT_PRIMARY_PASSWORD'),
        'use_tls' => true,
    ],
    'backup' => [
        'host' => env('MQTT_BACKUP_HOST', 'mqtt-backup.example.com'),
        'port' => env('MQTT_BACKUP_PORT', 8883),
        'username' => env('MQTT_BACKUP_USERNAME'),
        'password' => env('MQTT_BACKUP_PASSWORD'),
        'use_tls' => true,
    ],
],

'environments' => [
    'production' => ['primary', 'backup'],  // Both brokers in production
    'staging' => ['primary'],               // Only primary in staging
    'local' => ['default'],                 // Local broker for development
],
```

The supervisor will connect to ALL brokers defined for your environment.

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

### ❌ "Connection refused" or "Broker unreachable"

**Problem:** Cannot connect to MQTT broker.

**Solutions:**

1. **Check broker is running:**
```bash
# Test with mosquitto_sub
mosquitto_sub -h 127.0.0.1 -p 1883 -t '#' -v

# Or check if port is open
nc -zv 127.0.0.1 1883
```

2. **Check firewall:**
```bash
# Allow MQTT port
sudo ufw allow 1883
```

3. **Verify .env configuration:**
```env
MQTT_HOST=127.0.0.1  # Not 'localhost' if broker requires IP
MQTT_PORT=1883
```

### ❌ "Authentication failed"

**Problem:** Broker requires credentials but authentication fails.

**Solutions:**

1. **Check credentials in .env:**
```env
MQTT_USERNAME=your_username
MQTT_PASSWORD=your_password
```

2. **Verify broker ACL** (if using Mosquitto):
```bash
# Check mosquitto.conf
cat /etc/mosquitto/mosquitto.conf | grep -A 5 "allow_anonymous"

# Should be:
allow_anonymous false
password_file /etc/mosquitto/passwd
```

3. **Test credentials manually:**
```bash
mosquitto_sub -h 127.0.0.1 -p 1883 -u your_username -P your_password -t '#'
```

### ❌ "Address already in use" or Process won't start

**Problem:** Another process is using the required port or database.

**Solutions:**

1. **Check if supervisor is already running:**
```bash
php artisan mqtt-broadcast:terminate --dry-run
```

2. **Kill existing processes:**
```bash
php artisan mqtt-broadcast:terminate
```

3. **Find process using port:**
```bash
lsof -i :1883
# or
netstat -tulpn | grep 1883
```

### ❌ Messages not being received

**Problem:** Supervisor running but no messages appear.

**Solutions:**

1. **Check subscription:**
```bash
# View supervisor output for subscription confirmation
# You should see: "✓ Subscribed to topic: # (QoS: 0)"
```

2. **Verify topic prefix:**
```php
// config/mqtt-broadcast.php
'prefix' => 'myapp/',

// If prefix is set, messages to 'sensors/temp'
// must be published to 'myapp/sensors/temp'
```

3. **Check event listener is registered:**
```php
// Make sure your listener is in EventServiceProvider
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;

protected $listen = [
    MqttMessageReceived::class => [
        YourListener::class,
    ],
];
```

4. **Test publishing manually:**
```bash
mosquitto_pub -h 127.0.0.1 -p 1883 -t "test/topic" -m "Hello World"
```

### ❌ "No processes found" when terminating

**Problem:** `mqtt-broadcast:terminate` says no processes found.

**Solutions:**

Check database for stale records:
```bash
php artisan tinker
>>> \enzolarosa\MqttBroadcast\Models\BrokerProcess::all();
>>> \enzolarosa\MqttBroadcast\Models\BrokerProcess::truncate(); // Clear stale
```

### ❌ Memory issues or crashes

**Problem:** Supervisor crashes or uses too much memory.

**Solutions:**

1. **Monitor memory via dashboard:**
   - Go to `http://your-app.test/mqtt-broadcast`
   - Check "Memory Usage" card

2. **Adjust memory limit:**
```php
// config/mqtt-broadcast.php
'memory' => [
    'threshold_mb' => 256,  // Increase from 128MB default
    'auto_restart' => true,
],
```

3. **Enable auto-restart:**
   - Already enabled by default
   - Supervisor will gracefully restart when memory exceeds limit

### 📚 More Help

- **Dashboard:** Check `http://your-app.test/mqtt-broadcast` for real-time diagnostics
- **Logs:** Check `storage/logs/laravel.log` for detailed error messages
- **GitHub Issues:** [Report a bug](https://github.com/enzolarosa/mqtt-broadcast/issues)
- **Testing:** Run tests locally with `./test.sh` (see [tests/README.md](tests/README.md))

## Testing

The package includes 356 tests (327 unit + 29 integration tests).

### Quick Testing

```bash
# Run unit tests (no external dependencies)
composer test

# Or use the helper script
./test.sh unit
```

### Integration Tests with Real Broker

Integration tests require a real MQTT broker (Mosquitto).

**Start test environment:**

```bash
./test.sh start  # Starts Mosquitto + Redis via Docker
```

**Run all tests:**

```bash
./test.sh all
```

**Run only integration tests:**

```bash
./test.sh integration
```

**Stop test environment:**

```bash
./test.sh stop
```

### Helper Script Commands

```bash
./test.sh start       # Start Mosquitto and Redis
./test.sh stop        # Stop services
./test.sh status      # Check if services are running
./test.sh unit        # Run unit tests only
./test.sh integration # Run integration tests only
./test.sh all         # Run all tests with broker
./test.sh clean       # Stop and clean volumes
```

### CI/CD

Integration tests run automatically on GitHub Actions with Mosquitto service container.

For more details, see [tests/README.md](tests/README.md).

## Documentation

- [Architecture Guide](docs/ARCHITECTURE.md) - Detailed architecture explanation
- [Testing Guide](tests/README.md) - Complete testing documentation
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
