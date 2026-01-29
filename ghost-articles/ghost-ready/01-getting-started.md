
**MQTT Broadcast** is a production-ready Laravel package that brings reliable MQTT integration to your Laravel applications. Built using the proven **Laravel Horizon supervisor pattern**, it provides robust message handling with automatic reconnection, graceful shutdown, and real-time monitoring.

In this guide, you'll install the package, configure your first MQTT broker connection, and send/receive your first messages.


## Prerequisites

Before you begin, ensure you have:

- **Laravel 11.x** (Laravel 9.x and 10.x also supported)
- **PHP 8.1 or higher**
- **MQTT Broker** running (Mosquitto, HiveMQ, AWS IoT Core, etc.)
- **Redis** (recommended for queue management)


## Step 1: Install the Package

Install via Composer:

```bash
composer require enzolarosa/mqtt-broadcast
```

The package uses Laravel's auto-discovery feature, so the service provider is registered automatically.


## Step 2: Run Migrations

The package includes migrations for tracking broker processes and message logs:

```bash
php artisan migrate
```

This creates two tables:
- `broker_processes` - Tracks active MQTT supervisor processes
- `mqtt_loggers` - Stores received messages (when logging is enabled)


## Step 3: Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="mqtt-broadcast-config"
```

This creates `config/mqtt-broadcast.php` with sensible defaults.


## Step 4: Configure Your Broker

Add your MQTT broker credentials to `.env`:

```env
MQTT_HOST=127.0.0.1
MQTT_PORT=1883

# Optional: if your broker requires authentication
MQTT_USERNAME=your_username
MQTT_PASSWORD=your_password

# Optional: topic prefix for all messages
MQTT_PREFIX=myapp/
```

**That's it!** The minimal configuration is complete.

### Configuration File Structure

The config file is organized into clear sections:

```php
// config/mqtt-broadcast.php

return [
    // QUICK START: Only these settings required
    'connections' => [
        'default' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => env('MQTT_PORT', 1883),
            'username' => env('MQTT_USERNAME'),
            'password' => env('MQTT_PASSWORD'),
            'prefix' => env('MQTT_PREFIX', ''),
        ],
    ],

    // Environment-specific broker selection
    'environments' => [
        'local' => ['default'],
        'production' => ['default'],
    ],

    // Dashboard settings
    'path' => env('MQTT_BROADCAST_PATH', 'mqtt-broadcast'),

    // Message logging
    'logs' => [
        'enable' => env('MQTT_LOG_ENABLE', false),
    ],

    // Advanced options (optimized defaults)
    'defaults' => [...],
];
```


## Step 5: Start the Subscriber

Start the MQTT subscriber daemon:

```bash
php artisan mqtt-broadcast
```

You should see output like:

```
MQTT Broadcast Supervisor started
Connected to broker: 127.0.0.1:1883
Subscribed to: myapp/#
Ready to process messages...
```

The subscriber will:
- Connect to your MQTT broker
- Subscribe to all topics (with your prefix)
- Dispatch Laravel events for received messages
- Automatically reconnect on connection loss
- Handle graceful shutdown on SIGTERM


## Step 6: Listen to Messages

Create an event listener to handle incoming MQTT messages.

### Option A: Using Event Listener Class

Generate a listener:

```bash
php artisan make:listener HandleMqttMessages
```

```php
<?php

namespace App\Listeners;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Support\Facades\Log;

class HandleMqttMessages
{
    public function handle(MqttMessageReceived $event): void
    {
        Log::info('MQTT Message Received', [
            'topic' => $event->topic,
            'message' => $event->message,
            'broker' => $event->broker,
            'qos' => $event->qos,
        ]);

        // Process your message here
        // Example: Parse JSON and store in database
        $data = json_decode($event->message, true);

        if ($event->topic === 'sensors/temperature') {
            // Handle temperature sensor data
        }
    }
}
```

Register in `app/Providers/EventServiceProvider.php`:

```php
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use App\Listeners\HandleMqttMessages;

protected $listen = [
    MqttMessageReceived::class => [
        HandleMqttMessages::class,
    ],
];
```

### Option B: Using Closure (Quick Testing)

For quick testing, use a closure in `routes/console.php` or `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Event;
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;

Event::listen(MqttMessageReceived::class, function ($event) {
    logger()->info('MQTT:', [
        'topic' => $event->topic,
        'message' => $event->message,
    ]);
});
```


## Step 7: Publish Your First Message

### Using the Facade

The easiest way to publish messages:

```php
use enzolarosa\MqttBroadcast\Facades\MqttBroadcast;

// Simple publish
MqttBroadcast::publish('sensors/temp', '25.5');

// Publish JSON data
MqttBroadcast::publish('sensors/temp', json_encode([
    'temperature' => 25.5,
    'humidity' => 65.2,
    'timestamp' => now(),
]));

// Publish with custom QoS
MqttBroadcast::publish('alerts/critical', 'High temperature!', qos: 2);
```

### Using the Job Directly

For more control, dispatch the job directly:

```php
use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;

MqttMessageJob::dispatch(
    topic: 'sensors/temperature',
    message: json_encode(['value' => 25.5]),
    connection: 'default',
    qos: 1,
    retain: false
);
```

### Test with Artisan Tinker

Quick test from the command line:

```bash
php artisan tinker
```

```php
>>> \enzolarosa\MqttBroadcast\Facades\MqttBroadcast::publish('test/topic', 'Hello MQTT!');
=> true

>>> \enzolarosa\MqttBroadcast\Models\BrokerProcess::all();
=> Illuminate\Database\Eloquent\Collection {#...}
```


## Step 8: View the Dashboard

MQTT Broadcast includes a beautiful real-time monitoring dashboard.

**Access it at:** `http://your-app.test/mqtt-broadcast`

The dashboard shows:
- **Live throughput charts** - Messages per minute/hour/day
- **Broker status** - Connection state of all brokers
- **Message log** - Recent messages with filtering
- **Memory usage** - Supervisor memory consumption
- **Queue metrics** - Pending jobs

### Dashboard Authentication

In **local environment**: No authentication required (always accessible).

In **production**: Configure access via Laravel Gates:

```php
// app/Providers/AuthServiceProvider.php

use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewMqttBroadcast', function ($user) {
        return in_array($user->email, [
            'admin@example.com',
        ]);
    });
}
```


## Stopping the Subscriber

To gracefully stop the subscriber:

```bash
php artisan mqtt-broadcast:terminate
```

This sends a SIGTERM signal, allowing the supervisor to:
1. Stop accepting new messages
2. Complete processing current messages
3. Disconnect from brokers
4. Clean up resources


## What's Next?

You now have a working MQTT integration! Here's what to explore next:

### Configuration
Learn about advanced configuration options:
- [**Configuration Guide**](https://enzolarosa.dev/docs/mqtt-broadcast-configuration) - Complete configuration reference

### Real-World Examples
Build something practical:
- [**IoT Temperature Monitoring**](https://enzolarosa.dev/tutorials/iot-temperature-monitoring) - Complete end-to-end example with ESP32

### Production Deployment
Deploy to production:
- [**Production Deployment**](https://enzolarosa.dev/docs/mqtt-broadcast-production-deployment) - Supervisor, monitoring, and scaling

### Troubleshooting
Having issues?
- [**Troubleshooting Guide**](https://enzolarosa.dev/docs/mqtt-broadcast-troubleshooting) - Common problems and solutions


## Need Help?

- 📖 [Full Documentation](https://github.com/enzolarosa/mqtt-broadcast)
- 💬 [GitHub Discussions](https://github.com/enzolarosa/mqtt-broadcast/discussions)
- 🐛 [Report Issues](https://github.com/enzolarosa/mqtt-broadcast/issues)


**Next Article:** [Configuration Guide →](https://enzolarosa.dev/docs/mqtt-broadcast-configuration)
