
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "MQTT Broadcast: Production-Ready MQTT Integration for Laravel",
  "description": "Announcing MQTT Broadcast, a new Laravel package for robust MQTT integration with Horizon-style supervisor architecture",
  "image": "https://enzolarosa.dev/content/images/mqtt-broadcast-announcement.jpg",
  "author": {
    "@type": "Person",
    "name": "Enzo La Rosa",
    "url": "https://enzolarosa.dev/author/enzo"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Enzo La Rosa",
    "logo": {
      "@type": "ImageObject",
      "url": "https://enzolarosa.dev/content/images/logo.png"
    }
  },
  "datePublished": "2026-01-29",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://enzolarosa.dev/blog/announcing-mqtt-broadcast-laravel-package"
  }
}
</script>

I'm excited to announce **MQTT Broadcast**, a production-ready Laravel package that brings robust MQTT integration to your applications. Built using the proven **Laravel Horizon supervisor pattern**, it's designed for reliability, scalability, and ease of use.


## The Problem

MQTT is essential for modern applications—from IoT sensor networks to real-time messaging and industrial automation. But integrating MQTT with Laravel has historically meant:

- Writing custom supervisor loops prone to memory leaks
- Handling reconnection logic manually
- No graceful shutdown (killing processes mid-message)
- Limited monitoring capabilities
- Single broker limitations (no redundancy)
- Difficulty debugging production issues

Most existing solutions are simple wrappers around MQTT clients, leaving you to solve these production concerns yourself.


## The Solution

**MQTT Broadcast** takes a different approach: it brings the battle-tested **Horizon supervisor architecture** to MQTT integration.

### Key Features

**🏗️ Horizon-Style Architecture**
- Multi-tier supervisor pattern (Master → Supervisors → Brokers)
- Automatic process management and recovery
- Graceful shutdown with SIGTERM handling
- Memory management with auto-restart

**🔄 Enterprise-Grade Reliability**
- Exponential backoff reconnection
- Multiple broker support for redundancy
- Configurable retry policies
- Self-healing on connection failures

**📊 Real-Time Monitoring**
- Beautiful React 19 dashboard
- Live throughput charts (minute/hour/day)
- Broker status monitoring
- Memory usage tracking
- Message logging with search

**🚀 Developer Experience**
- Install in 2 minutes
- Simple Laravel Events for message handling
- Facade for easy publishing
- Queue integration for async operations
- Comprehensive documentation

**✅ Production-Ready**
- 356 tests (327 unit + 29 integration)
- GitHub Actions CI/CD
- Real Mosquitto broker testing
- Memory leak prevention
- Type-safe (PHP 8.1+)


## Quick Start

Install via Composer:

```bash
composer require enzolarosa/mqtt-broadcast
php artisan migrate
php artisan vendor:publish --tag="mqtt-broadcast-config"
```

Configure your broker in `.env`:

```env
MQTT_HOST=mqtt.example.com
MQTT_PORT=1883
MQTT_USERNAME=your_username
MQTT_PASSWORD=your_password
```

Start the subscriber:

```bash
php artisan mqtt-broadcast
```

Listen to incoming messages:

```php
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Support\Facades\Event;

Event::listen(MqttMessageReceived::class, function ($event) {
    logger()->info('MQTT Message:', [
        'topic' => $event->topic,
        'message' => $event->message,
    ]);

    // Process your message
    $data = json_decode($event->message, true);

    if ($event->topic === 'sensors/temperature') {
        TemperatureReading::create([
            'value' => $data['temperature'],
            'location' => $data['location'],
        ]);
    }
});
```

Publish messages:

```php
use enzolarosa\MqttBroadcast\Facades\MqttBroadcast;

MqttBroadcast::publish('alerts/critical', 'System overheating!');

// With options
MqttBroadcast::publish(
    topic: 'sensors/temp',
    message: json_encode(['value' => 25.5]),
    qos: 2
);
```

Access the dashboard at `http://your-app.test/mqtt-broadcast` to monitor everything in real-time.


## IoT Made Simple

Here's a complete example: ESP32 sensor → MQTT → Laravel → Database → Email alerts.

**ESP32 Code (Arduino):**

```cpp
#include <WiFi.h>
#include <PubSubClient.h>

const char* mqtt_server = "192.168.1.100";
WiFiClient espClient;
PubSubClient client(espClient);

void setup() {
  WiFi.begin("SSID", "password");
  client.setServer(mqtt_server, 1883);
}

void loop() {
  String payload = "{\"temperature\": 25.5, \"location\": \"Office\"}";
  client.publish("sensors/temp/1", payload.c_str());
  delay(5000);
}
```

**Laravel Listener:**

```php
namespace App\Listeners;

use App\Models\TemperatureReading;
use App\Notifications\HighTemperatureAlert;
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Support\Facades\Notification;

class ProcessTemperatureReading
{
    public function handle(MqttMessageReceived $event): void
    {
        if (!str_starts_with($event->topic, 'sensors/temp/')) {
            return;
        }

        $data = json_decode($event->message, true);

        $reading = TemperatureReading::create([
            'sensor_id' => str_replace('sensors/temp/', '', $event->topic),
            'temperature' => $data['temperature'],
            'location' => $data['location'],
        ]);

        // Send alert if too hot
        if ($reading->temperature > 30) {
            Notification::route('mail', 'admin@example.com')
                ->notify(new HighTemperatureAlert($reading));
        }
    }
}
```

That's it! You now have a production-ready IoT system with database storage and email alerts.


## Advanced Features

### Multiple Brokers for Redundancy

```php
// config/mqtt-broadcast.php

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
],
```

The supervisor connects to **both brokers simultaneously**. If the primary fails, your application keeps receiving messages from the backup.

### Environment-Specific Configuration

```php
'environments' => [
    'local' => ['local-broker'],
    'staging' => ['staging-broker'],
    'production' => ['primary', 'backup'],
],
```

Use different brokers per environment, or override at runtime:

```bash
php artisan mqtt-broadcast --environment=production
```

### Memory Management

Automatic memory monitoring and graceful restart:

```php
'memory' => [
    'threshold_mb' => 128,
    'auto_restart' => true,
    'restart_delay_seconds' => 10,
],
```

When memory exceeds the threshold:
1. Supervisor stops accepting new messages
2. Completes processing current messages
3. Gracefully restarts
4. Reconnects to all brokers

No lost messages, no manual intervention.


## Production Deployment

Deploy with Supervisor for automatic process management:

```ini
[program:mqtt-broadcast]
command=php /var/www/html/artisan mqtt-broadcast
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/mqtt-broadcast.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mqtt-broadcast
```

Monitor via the dashboard or check process status:

```bash
php artisan tinker
>>> \enzolarosa\MqttBroadcast\Models\BrokerProcess::all();
```


## Real-Time Dashboard

The included React dashboard provides comprehensive monitoring:

- **Live Charts**: Message throughput (minute/hour/day views)
- **Broker Status**: Connection state, uptime, message counts
- **Message Log**: Recent messages with topic filtering
- **Memory Usage**: Current usage with threshold alerts
- **Queue Metrics**: Pending jobs monitoring
- **Dark Mode**: Automatic theme switching

**Production Authentication:**

```php
// app/Providers/AuthServiceProvider.php

Gate::define('viewMqttBroadcast', function ($user) {
    return $user->isAdmin();
});
```


## Battle-Tested

MQTT Broadcast is production-ready with comprehensive testing:

- **356 total tests**
  - 327 unit tests
  - 29 integration tests with real Mosquitto broker
- **GitHub Actions CI/CD**
- **Real broker testing** (not mocked)
- **Memory leak testing**
- **Reconnection scenario testing**
- **Graceful shutdown testing**

Integration tests spin up a real Mosquitto broker via Docker, ensuring the package works with actual MQTT infrastructure.


## Use Cases

**IoT & Hardware:**
- ESP32, ESP8266, Arduino sensors
- Raspberry Pi data collection
- Industrial PLCs (Industry 4.0)
- Smart home automation
- Fleet tracking devices

**Real-Time Applications:**
- Live chat systems
- Notification services
- Device control panels
- Telemetry dashboards
- Multi-user collaboration tools

**Industrial:**
- Factory automation
- SCADA systems
- Environmental monitoring
- Energy management
- Predictive maintenance


## Why Not Just Use Package X?

Good question! Here's how MQTT Broadcast compares:

| Feature | MQTT Broadcast | Other Packages |
|---------|---------------|----------------|
| **Architecture** | Horizon-style supervisor | Simple loops |
| **Auto-Reconnection** | Exponential backoff | Basic retry or none |
| **Multiple Brokers** | Simultaneous connections | Single only |
| **Graceful Shutdown** | SIGTERM handling | Force kill |
| **Memory Management** | Auto-restart on threshold | Manual restart |
| **Monitoring Dashboard** | Real-time React UI | No dashboard |
| **Production Testing** | 356 tests with real broker | Limited or mocked |
| **Process Recovery** | Self-healing supervisors | Manual restart |

The Horizon pattern has been battle-tested in Laravel applications for years. MQTT Broadcast brings that same reliability to MQTT integration.


## Documentation

Comprehensive guides available:

- **[Getting Started](https://enzolarosa.dev/docs/mqtt-broadcast-getting-started)** - Install and configure in 5 minutes
- **[Configuration Guide](https://enzolarosa.dev/docs/mqtt-broadcast-configuration)** - All options explained
- **[IoT Tutorial](https://enzolarosa.dev/tutorials/iot-temperature-monitoring-laravel-esp32)** - Complete ESP32 example
- **[GitHub Wiki](https://github.com/enzolarosa/mqtt-broadcast/wiki)** - Community documentation
- **[Architecture Deep Dive](https://github.com/enzolarosa/mqtt-broadcast/blob/main/docs/ARCHITECTURE.md)** - How it works internally


## Try It Today

```bash
composer require enzolarosa/mqtt-broadcast
```

**GitHub:** [enzolarosa/mqtt-broadcast](https://github.com/enzolarosa/mqtt-broadcast)
**Packagist:** [enzolarosa/mqtt-broadcast](https://packagist.org/packages/enzolarosa/mqtt-broadcast)

**Requirements:**
- Laravel 9.x, 10.x, or 11.x
- PHP 8.1+
- MQTT broker (Mosquitto, HiveMQ, AWS IoT Core, etc.)


## What's Next?

I'm actively working on:

- **Video Tutorials** - YouTube series on IoT with Laravel
- **More Examples** - Real-time chat, device control, multi-tenant
- **Performance Guide** - Handling millions of messages
- **Grafana Integration** - Pre-built dashboards

Have feedback or use cases to share? I'd love to hear from you in the [GitHub Discussions](https://github.com/enzolarosa/mqtt-broadcast/discussions).


## Support the Project

If you find MQTT Broadcast useful:

- ⭐ Star on [GitHub](https://github.com/enzolarosa/mqtt-broadcast)
- 🐛 Report issues or suggest features
- 📖 Contribute to documentation
- 💬 Share your use cases in Discussions
- 🔗 Spread the word in your network

Building IoT and real-time applications with Laravel just got a whole lot easier. Happy coding!


**Links:**
- [GitHub Repository](https://github.com/enzolarosa/mqtt-broadcast)
- [Documentation](https://enzolarosa.dev/docs/mqtt-broadcast-getting-started)
- [IoT Tutorial](https://enzolarosa.dev/tutorials/iot-temperature-monitoring-laravel-esp32)
- [Report Issues](https://github.com/enzolarosa/mqtt-broadcast/issues)
- [Discussions](https://github.com/enzolarosa/mqtt-broadcast/discussions)
