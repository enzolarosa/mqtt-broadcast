---
title: "Build an IoT Temperature Monitoring System with Laravel and ESP32"
slug: iot-temperature-monitoring-laravel-esp32
excerpt: "Learn how to build a complete IoT temperature monitoring system using Laravel MQTT Broadcast and ESP32. Includes Arduino code, real-time dashboard, email alerts, and production deployment."
feature_image: null
featured: true
tags:
  - mqtt-broadcast
  - laravel
  - iot
  - esp32
  - arduino
  - tutorial
  - temperature-sensor
author: Enzo La Rosa
canonical_url: https://enzolarosa.dev/tutorials/iot-temperature-monitoring-laravel-esp32
meta_title: "IoT Temperature Monitoring with Laravel & ESP32 - Complete Tutorial"
meta_description: "Build a production-ready IoT temperature monitoring system with Laravel MQTT Broadcast and ESP32. Includes database storage, real-time dashboard, alerts, and Arduino code."
og_title: "Build IoT Temperature Monitor with Laravel & ESP32"
og_description: "Complete tutorial: ESP32 sensors → MQTT → Laravel → Real-time dashboard. Includes Arduino sketch, database design, and production deployment."
twitter_title: "IoT Temperature Monitoring: Laravel + ESP32 Tutorial"
twitter_description: "Build a complete IoT system with Laravel MQTT and ESP32 sensors. Real-time monitoring, alerts, and production-ready code."
---

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "Build an IoT Temperature Monitoring System with Laravel and ESP32",
  "description": "Complete tutorial showing how to build a production-ready IoT temperature monitoring system using Laravel MQTT Broadcast and ESP32 microcontrollers",
  "image": "https://enzolarosa.dev/content/images/iot-temperature-monitoring.jpg",
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
  "datePublished": "2026-01-28",
  "dateModified": "2026-01-28",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://enzolarosa.dev/tutorials/iot-temperature-monitoring-laravel-esp32"
  },
  "dependencies": "Laravel MQTT Broadcast",
  "proficiencyLevel": "Intermediate",
  "timeRequired": "PT45M",
  "articleBody": "Complete IoT tutorial with ESP32, Laravel, MQTT, and real-time monitoring"
}
</script>

Build a **complete IoT temperature monitoring system** from scratch using **Laravel MQTT Broadcast** and **ESP32** microcontrollers. This tutorial covers everything from hardware wiring to production deployment.

**What you'll build:**
- 🌡️ ESP32 sensors publishing temperature data via MQTT
- 💾 Laravel backend storing sensor data in database
- 📊 Real-time dashboard with live charts
- 📧 Email alerts when temperature exceeds thresholds
- 🚀 Production-ready deployment with Supervisor

**Time to complete:** ~45 minutes
**Difficulty:** Intermediate

---

## Architecture Overview

```
┌─────────────┐         MQTT          ┌──────────────┐
│  ESP32/DHT22│ ──────publish────────▶ │ MQTT Broker  │
│   Sensors   │    sensors/temp/1      │ (Mosquitto)  │
└─────────────┘                        └──────────────┘
                                              │
                                              │ subscribe
                                              ▼
                                    ┌──────────────────┐
                                    │ Laravel App      │
                                    │ mqtt-broadcast   │
                                    └──────────────────┘
                                              │
                        ┌────────────────────┼────────────────┐
                        ▼                    ▼                ▼
                  ┌──────────┐        ┌──────────┐    ┌──────────┐
                  │ Database │        │  Queue   │    │  Events  │
                  │  MySQL   │        │  Redis   │    │  Alerts  │
                  └──────────┘        └──────────┘    └──────────┘
```

---

## Prerequisites

**Hardware:**
- ESP32 DevKit (or ESP8266)
- DHT22 temperature/humidity sensor
- Breadboard and jumper wires
- USB cable for programming

**Software:**
- Laravel 11.x application
- Arduino IDE with ESP32 board support
- Mosquitto MQTT broker (or cloud broker)
- Redis (for Laravel queues)

**Skills:**
- Basic Laravel knowledge
- Basic Arduino programming
- Understanding of MQTT protocol

---

## Part 1: Laravel Backend Setup

### Step 1: Install MQTT Broadcast

```bash
composer require enzolarosa/mqtt-broadcast
php artisan migrate
php artisan vendor:publish --tag="mqtt-broadcast-config"
```

### Step 2: Configure MQTT Broker

Add to `.env`:

```env
MQTT_HOST=127.0.0.1
MQTT_PORT=1883
MQTT_PREFIX=iot/
MQTT_LOG_ENABLE=true

# Email configuration for alerts
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=alerts@example.com
```

### Step 3: Create Database Schema

Generate migration:

```bash
php artisan make:migration create_temperature_readings_table
```

**Migration file:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperature_readings', function (Blueprint $table) {
            $table->id();
            $table->string('sensor_id')->index();
            $table->decimal('temperature', 5, 2);
            $table->decimal('humidity', 5, 2)->nullable();
            $table->string('location')->nullable();
            $table->integer('rssi')->nullable(); // WiFi signal strength
            $table->timestamps();

            // Index for time-series queries
            $table->index(['sensor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperature_readings');
    }
};
```

Run migration:

```bash
php artisan migrate
```

### Step 4: Create Model

```bash
php artisan make:model TemperatureReading
```

**app/Models/TemperatureReading.php:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemperatureReading extends Model
{
    protected $fillable = [
        'sensor_id',
        'temperature',
        'humidity',
        'location',
        'rssi',
    ];

    protected $casts = [
        'temperature' => 'float',
        'humidity' => 'float',
        'rssi' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get readings from last N hours
     */
    public static function recentReadings(string $sensorId, int $hours = 24)
    {
        return static::where('sensor_id', $sensorId)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get statistics for a sensor
     */
    public static function stats(string $sensorId, int $hours = 24)
    {
        return static::where('sensor_id', $sensorId)
            ->where('created_at', '>=', now()->subHours($hours))
            ->selectRaw('
                AVG(temperature) as avg_temp,
                MIN(temperature) as min_temp,
                MAX(temperature) as max_temp,
                AVG(humidity) as avg_humidity,
                COUNT(*) as total_readings
            ')
            ->first();
    }
}
```

### Step 5: Create Event Listener

Generate listener:

```bash
php artisan make:listener ProcessTemperatureReading
```

**app/Listeners/ProcessTemperatureReading.php:**

```php
<?php

namespace App\Listeners;

use App\Models\TemperatureReading;
use App\Notifications\HighTemperatureAlert;
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessTemperatureReading
{
    // Temperature thresholds (Celsius)
    private const TEMP_WARNING = 28;
    private const TEMP_CRITICAL = 35;

    public function handle(MqttMessageReceived $event): void
    {
        // Only process temperature sensor topics
        if (!str_starts_with($event->topic, 'iot/sensors/temp/')) {
            return;
        }

        // Extract sensor ID from topic: iot/sensors/temp/{sensor_id}
        $sensorId = str_replace('iot/sensors/temp/', '', $event->topic);

        // Parse JSON payload
        $data = json_decode($event->message, true);

        if (!$data || !isset($data['temperature'])) {
            Log::warning('Invalid temperature data received', [
                'topic' => $event->topic,
                'message' => $event->message,
            ]);
            return;
        }

        // Store reading in database
        $reading = TemperatureReading::create([
            'sensor_id' => $sensorId,
            'temperature' => $data['temperature'],
            'humidity' => $data['humidity'] ?? null,
            'location' => $data['location'] ?? 'Unknown',
            'rssi' => $data['rssi'] ?? null,
        ]);

        Log::info('Temperature reading stored', [
            'sensor' => $sensorId,
            'temp' => $reading->temperature,
            'location' => $reading->location,
        ]);

        // Send alerts if needed
        $this->checkThresholds($reading);
    }

    private function checkThresholds(TemperatureReading $reading): void
    {
        if ($reading->temperature >= self::TEMP_CRITICAL) {
            Notification::route('mail', config('app.admin_email'))
                ->notify(new HighTemperatureAlert($reading, 'critical'));
        } elseif ($reading->temperature >= self::TEMP_WARNING) {
            Notification::route('mail', config('app.admin_email'))
                ->notify(new HighTemperatureAlert($reading, 'warning'));
        }
    }
}
```

**Register listener in `app/Providers/EventServiceProvider.php`:**

```php
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use App\Listeners\ProcessTemperatureReading;

protected $listen = [
    MqttMessageReceived::class => [
        ProcessTemperatureReading::class,
    ],
];
```

### Step 6: Create Alert Notification

```bash
php artisan make:notification HighTemperatureAlert
```

**app/Notifications/HighTemperatureAlert.php:**

```php
<?php

namespace App\Notifications;

use App\Models\TemperatureReading;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HighTemperatureAlert extends Notification
{
    use Queueable;

    public function __construct(
        private TemperatureReading $reading,
        private string $severity = 'warning'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $emoji = $this->severity === 'critical' ? '🚨' : '⚠️';
        $subject = $this->severity === 'critical'
            ? 'CRITICAL: High Temperature Detected!'
            : 'WARNING: Temperature Alert';

        return (new MailMessage)
            ->subject($emoji . ' ' . $subject)
            ->level($this->severity)
            ->line("High temperature detected on sensor **{$this->reading->sensor_id}**")
            ->line("**Temperature:** {$this->reading->temperature}°C")
            ->line("**Location:** {$this->reading->location}")
            ->line("**Time:** {$this->reading->created_at->format('Y-m-d H:i:s')}")
            ->when($this->reading->humidity, function ($mail) {
                return $mail->line("**Humidity:** {$this->reading->humidity}%");
            })
            ->action('View Dashboard', url('/mqtt-broadcast'))
            ->line('Please check the system immediately.');
    }
}
```

### Step 7: Create API Endpoints (Optional)

For frontend consumption:

```bash
php artisan make:controller Api/TemperatureController
```

**app/Http/Controllers/Api/TemperatureController.php:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TemperatureReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemperatureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TemperatureReading::query()
            ->orderBy('created_at', 'desc');

        if ($request->has('sensor_id')) {
            $query->where('sensor_id', $request->sensor_id);
        }

        if ($request->has('hours')) {
            $query->where('created_at', '>=', now()->subHours($request->hours));
        }

        return response()->json([
            'data' => $query->paginate(50),
        ]);
    }

    public function latest(): JsonResponse
    {
        $sensors = TemperatureReading::query()
            ->select('sensor_id')
            ->selectRaw('MAX(created_at) as latest')
            ->groupBy('sensor_id')
            ->get();

        $latest = [];
        foreach ($sensors as $sensor) {
            $reading = TemperatureReading::where('sensor_id', $sensor->sensor_id)
                ->where('created_at', $sensor->latest)
                ->first();

            if ($reading) {
                $latest[] = $reading;
            }
        }

        return response()->json(['data' => $latest]);
    }

    public function stats(string $sensorId): JsonResponse
    {
        $stats = TemperatureReading::stats($sensorId, 24);

        return response()->json([
            'sensor_id' => $sensorId,
            'period_hours' => 24,
            'stats' => $stats,
        ]);
    }
}
```

**routes/api.php:**

```php
use App\Http\Controllers\Api\TemperatureController;

Route::prefix('temperatures')->group(function () {
    Route::get('/', [TemperatureController::class, 'index']);
    Route::get('/latest', [TemperatureController::class, 'latest']);
    Route::get('/{sensorId}/stats', [TemperatureController::class, 'stats']);
});
```

---

## Part 2: ESP32 Sensor Setup

### Hardware Wiring

**DHT22 to ESP32:**
```
DHT22 VCC  → ESP32 3.3V
DHT22 GND  → ESP32 GND
DHT22 DATA → ESP32 GPIO 4 (D4)
```

Optional: Add 4.7kΩ pull-up resistor between DATA and VCC.

### Arduino Libraries

Install via Library Manager:
1. **WiFi** (built-in)
2. **PubSubClient** by Nick O'Leary
3. **DHT sensor library** by Adafruit
4. **Adafruit Unified Sensor**
5. **ArduinoJson** by Benoit Blanchon

### ESP32 Code

Complete Arduino sketch with WiFi reconnection and error handling:

```cpp
#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <ArduinoJson.h>

// ========================================
// CONFIGURATION
// ========================================

// WiFi credentials
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";

// MQTT Broker
const char* mqtt_server = "192.168.1.100";  // Your Laravel server IP
const int mqtt_port = 1883;
const char* mqtt_user = "";                 // If authentication enabled
const char* mqtt_password = "";

// MQTT Topic (must match Laravel prefix)
const char* sensor_id = "sensor1";
String topic = String("iot/sensors/temp/") + sensor_id;

// Sensor Configuration
#define DHTPIN 4           // GPIO pin
#define DHTTYPE DHT22      // DHT22 or DHT11
const char* location = "Office";

// Publish interval (milliseconds)
const unsigned long publish_interval = 5000;  // 5 seconds

// ========================================
// GLOBAL OBJECTS
// ========================================

WiFiClient espClient;
PubSubClient client(espClient);
DHT dht(DHTPIN, DHTTYPE);

unsigned long last_publish = 0;
int failed_readings = 0;
int mqtt_reconnect_attempts = 0;

// ========================================
// SETUP
// ========================================

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n\n=================================");
  Serial.println("ESP32 Temperature Monitor");
  Serial.println("=================================\n");

  // Initialize DHT sensor
  dht.begin();
  Serial.println("✓ DHT sensor initialized");

  // Connect to WiFi
  setup_wifi();

  // Configure MQTT
  client.setServer(mqtt_server, mqtt_port);
  client.setKeepAlive(60);
  client.setSocketTimeout(30);

  Serial.println("\n✓ Setup complete");
  Serial.println("=================================\n");
}

// ========================================
// MAIN LOOP
// ========================================

void loop() {
  // Maintain MQTT connection
  if (!client.connected()) {
    reconnect_mqtt();
  }
  client.loop();

  // Publish sensor data periodically
  unsigned long now = millis();
  if (now - last_publish >= publish_interval) {
    last_publish = now;
    publish_temperature();
  }
}

// ========================================
// WIFI CONNECTION
// ========================================

void setup_wifi() {
  delay(10);
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✓ WiFi connected");
    Serial.print("  IP address: ");
    Serial.println(WiFi.localIP());
    Serial.print("  Signal strength: ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
  } else {
    Serial.println("\n✗ WiFi connection failed!");
    Serial.println("  Restarting in 5 seconds...");
    delay(5000);
    ESP.restart();
  }
}

// ========================================
// MQTT CONNECTION
// ========================================

void reconnect_mqtt() {
  mqtt_reconnect_attempts++;

  Serial.print("Connecting to MQTT broker: ");
  Serial.print(mqtt_server);
  Serial.print(":");
  Serial.println(mqtt_port);

  // Generate unique client ID
  String clientId = "ESP32-" + String(sensor_id) + "-" + String(random(0xffff), HEX);

  // Attempt connection
  bool connected;
  if (strlen(mqtt_user) > 0) {
    connected = client.connect(clientId.c_str(), mqtt_user, mqtt_password);
  } else {
    connected = client.connect(clientId.c_str());
  }

  if (connected) {
    Serial.println("✓ MQTT connected");
    Serial.print("  Client ID: ");
    Serial.println(clientId);
    Serial.print("  Publishing to: ");
    Serial.println(topic);
    mqtt_reconnect_attempts = 0;
  } else {
    Serial.print("✗ MQTT connection failed, rc=");
    Serial.println(client.state());

    // Exponential backoff
    int delay_seconds = min(mqtt_reconnect_attempts * 2, 60);
    Serial.print("  Retrying in ");
    Serial.print(delay_seconds);
    Serial.println(" seconds...");
    delay(delay_seconds * 1000);

    // Restart after too many failures
    if (mqtt_reconnect_attempts > 10) {
      Serial.println("✗ Too many MQTT failures, restarting...");
      delay(2000);
      ESP.restart();
    }
  }
}

// ========================================
// PUBLISH TEMPERATURE DATA
// ========================================

void publish_temperature() {
  // Read sensor
  float humidity = dht.readHumidity();
  float temperature = dht.readTemperature();

  // Validate readings
  if (isnan(humidity) || isnan(temperature)) {
    failed_readings++;
    Serial.println("✗ Failed to read from DHT sensor");

    if (failed_readings > 10) {
      Serial.println("✗ Too many sensor failures, restarting...");
      delay(2000);
      ESP.restart();
    }
    return;
  }

  failed_readings = 0;

  // Create JSON payload
  StaticJsonDocument<256> doc;
  doc["temperature"] = round(temperature * 100) / 100.0;
  doc["humidity"] = round(humidity * 100) / 100.0;
  doc["location"] = location;
  doc["sensor_id"] = sensor_id;
  doc["rssi"] = WiFi.RSSI();
  doc["uptime"] = millis() / 1000;

  String payload;
  serializeJson(doc, payload);

  // Publish to MQTT
  if (client.publish(topic.c_str(), payload.c_str(), false)) {
    Serial.println("✓ Published:");
    Serial.print("  Temperature: ");
    Serial.print(temperature);
    Serial.println("°C");
    Serial.print("  Humidity: ");
    Serial.print(humidity);
    Serial.println("%");
    Serial.print("  Payload: ");
    Serial.println(payload);
  } else {
    Serial.println("✗ Publish failed");
  }
}
```

### Upload to ESP32

1. Select **Tools → Board → ESP32 Dev Module**
2. Select correct **Port**
3. Click **Upload**
4. Open **Serial Monitor** (115200 baud)

You should see:

```
=================================
ESP32 Temperature Monitor
=================================

✓ DHT sensor initialized
Connecting to WiFi: MyNetwork
.....
✓ WiFi connected
  IP address: 192.168.1.50
  Signal strength: -45 dBm

Connecting to MQTT broker: 192.168.1.100:1883
✓ MQTT connected
  Client ID: ESP32-sensor1-A3F2
  Publishing to: iot/sensors/temp/sensor1

✓ Setup complete
=================================

✓ Published:
  Temperature: 24.50°C
  Humidity: 62.30%
  Payload: {"temperature":24.5,"humidity":62.3,"location":"Office","sensor_id":"sensor1","rssi":-45,"uptime":5}
```

---

## Part 3: Testing & Monitoring

### Start Laravel Subscriber

```bash
php artisan mqtt-broadcast
```

You should see messages being received:

```
MQTT Broadcast Supervisor started
Connected to broker: 127.0.0.1:1883
Subscribed to: iot/#

[2026-01-28 10:15:23] Message received on iot/sensors/temp/sensor1
[2026-01-28 10:15:23] Temperature reading stored: sensor1, 24.5°C
```

### View Dashboard

Open `http://your-app.test/mqtt-broadcast` to see:
- Real-time message throughput
- Broker connection status
- Recent temperature messages

### Check Database

```bash
php artisan tinker
```

```php
>>> \App\Models\TemperatureReading::latest()->take(5)->get();
>>> \App\Models\TemperatureReading::stats('sensor1', 24);
```

### Test Alerts

Manually trigger a high temperature:

```cpp
// In ESP32 code, temporarily override:
float temperature = 36.0;  // Above TEMP_CRITICAL
```

Re-upload, and you should receive an email alert.

---

## Part 4: Production Deployment

See the complete [Production Deployment Guide](https://enzolarosa.dev/docs/mqtt-broadcast-production-deployment) for:

- Supervisor configuration
- Nginx setup
- SSL certificates
- Database optimization
- Queue worker scaling
- Monitoring with Grafana

---

## What's Next?

**Enhancements:**
- Add multiple sensor types (pressure, CO2, etc.)
- Create custom Grafana dashboards
- Implement WebSocket for real-time frontend updates
- Add machine learning for anomaly detection
- Build mobile app with push notifications

**Related Tutorials:**
- [Device Control via MQTT](https://enzolarosa.dev/tutorials/iot-device-control-mqtt)
- [Multi-Tenant IoT Platform](https://enzolarosa.dev/tutorials/multi-tenant-iot-platform)
- [Industrial Automation with PLCs](https://enzolarosa.dev/tutorials/industry-40-laravel)

---

## Need Help?

- 📖 [Documentation](https://github.com/enzolarosa/mqtt-broadcast)
- 💬 [Discussions](https://github.com/enzolarosa/mqtt-broadcast/discussions)
- 🐛 [Report Issues](https://github.com/enzolarosa/mqtt-broadcast/issues)
- 🇮🇹 [Versione Italiana](https://enzolarosa.dev/it/tutorials/monitoraggio-temperatura-iot-laravel-esp32)

---

**Related Articles:**
- [Getting Started with MQTT Broadcast](https://enzolarosa.dev/docs/mqtt-broadcast-getting-started)
- [Configuration Guide](https://enzolarosa.dev/docs/mqtt-broadcast-configuration)
- [Production Deployment](https://enzolarosa.dev/docs/mqtt-broadcast-production-deployment)
