# 🌡️ IoT Temperature Monitoring System

**Complete end-to-end example** showing how to build a real-time temperature monitoring system with MQTT Broadcast.

**What you'll build:**
- ESP32/Arduino sensor publishing temperature data via MQTT
- Laravel backend receiving and storing sensor data
- Real-time dashboard showing live temperature graphs
- Email alerts when temperature exceeds thresholds

**Time to complete:** 15 minutes

---

## Architecture

```
┌─────────────┐         MQTT          ┌──────────────┐
│  ESP32/IoT  │ ──────publish────────▶ │ MQTT Broker  │
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
                  │  Store   │        │  Jobs    │    │  Alerts  │
                  └──────────┘        └──────────┘    └──────────┘
```

---

## Step 1: Setup MQTT Broker

If you don't have a broker yet, use Docker:

```bash
docker run -d \
  --name mosquitto \
  -p 1883:1883 \
  -v $(pwd)/mosquitto.conf:/mosquitto/config/mosquitto.conf \
  eclipse-mosquitto:2.0
```

**mosquitto.conf:**
```conf
allow_anonymous true
listener 1883
persistence true
persistence_location /mosquitto/data/
log_dest stdout
```

Test connection:
```bash
mosquitto_sub -h 127.0.0.1 -p 1883 -t 'sensors/#' -v
```

---

## Step 2: Laravel Application Setup

**Install MQTT Broadcast:**

```bash
composer require enzolarosa/mqtt-broadcast
php artisan migrate
php artisan vendor:publish --tag="mqtt-broadcast-config"
```

**Configure `.env`:**

```env
MQTT_HOST=127.0.0.1
MQTT_PORT=1883
MQTT_PREFIX=myapp/
MQTT_LOG_ENABLE=true
```

**Create Temperature Model:**

```bash
php artisan make:model Temperature -m
```

**Migration** (`database/migrations/xxxx_create_temperatures_table.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperatures', function (Blueprint $table) {
            $table->id();
            $table->string('sensor_id')->index();
            $table->decimal('temperature', 5, 2);
            $table->decimal('humidity', 5, 2)->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperatures');
    }
};
```

```bash
php artisan migrate
```

**Model** (`app/Models/Temperature.php`):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Temperature extends Model
{
    protected $fillable = [
        'sensor_id',
        'temperature',
        'humidity',
        'location',
    ];

    protected $casts = [
        'temperature' => 'float',
        'humidity' => 'float',
    ];
}
```

---

## Step 3: Create MQTT Event Listener

**Event Listener** (`app/Listeners/HandleTemperatureReading.php`):

```php
<?php

namespace App\Listeners;

use App\Models\Temperature;
use App\Notifications\HighTemperatureAlert;
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class HandleTemperatureReading
{
    public function handle(MqttMessageReceived $event): void
    {
        // Only process temperature sensor topics
        if (!str_starts_with($event->topic, 'sensors/temp/')) {
            return;
        }

        // Extract sensor ID from topic: sensors/temp/{sensor_id}
        $sensorId = str_replace('sensors/temp/', '', $event->topic);

        // Parse JSON payload
        $data = json_decode($event->message, true);

        if (!$data) {
            Log::warning('Invalid JSON from sensor', [
                'topic' => $event->topic,
                'message' => $event->message,
            ]);
            return;
        }

        // Store temperature reading
        $temperature = Temperature::create([
            'sensor_id' => $sensorId,
            'temperature' => $data['temperature'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'location' => $data['location'] ?? 'Unknown',
        ]);

        Log::info('Temperature reading stored', [
            'sensor' => $sensorId,
            'temp' => $temperature->temperature,
            'location' => $temperature->location,
        ]);

        // Send alert if temperature is too high
        if ($temperature->temperature > 30) {
            Notification::route('mail', config('app.admin_email'))
                ->notify(new HighTemperatureAlert($temperature));
        }
    }
}
```

**Register Listener** (`app/Providers/EventServiceProvider.php`):

```php
<?php

namespace App\Providers;

use App\Listeners\HandleTemperatureReading;
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MqttMessageReceived::class => [
            HandleTemperatureReading::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
```

**Alert Notification** (`app/Notifications/HighTemperatureAlert.php`):

```php
<?php

namespace App\Notifications;

use App\Models\Temperature;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HighTemperatureAlert extends Notification
{
    use Queueable;

    public function __construct(
        private Temperature $temperature
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔥 High Temperature Alert!')
            ->line("Sensor {$this->temperature->sensor_id} detected high temperature.")
            ->line("Temperature: {$this->temperature->temperature}°C")
            ->line("Location: {$this->temperature->location}")
            ->line("Timestamp: {$this->temperature->created_at->format('Y-m-d H:i:s')}")
            ->action('View Dashboard', url('/mqtt-broadcast'));
    }
}
```

---

## Step 4: Create API Endpoints (Optional)

**Controller** (`app/Http/Controllers/TemperatureController.php`):

```php
<?php

namespace App\Http\Controllers;

use App\Models\Temperature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemperatureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Temperature::query()
            ->orderBy('created_at', 'desc');

        // Filter by sensor
        if ($request->has('sensor_id')) {
            $query->where('sensor_id', $request->sensor_id);
        }

        // Filter by time range
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        return response()->json([
            'data' => $query->paginate(50),
        ]);
    }

    public function latest(): JsonResponse
    {
        $sensors = Temperature::query()
            ->select('sensor_id')
            ->selectRaw('MAX(created_at) as latest')
            ->groupBy('sensor_id')
            ->get();

        $latest = [];

        foreach ($sensors as $sensor) {
            $latest[] = Temperature::where('sensor_id', $sensor->sensor_id)
                ->where('created_at', $sensor->latest)
                ->first();
        }

        return response()->json([
            'data' => $latest,
        ]);
    }

    public function stats(string $sensorId): JsonResponse
    {
        $stats = Temperature::where('sensor_id', $sensorId)
            ->selectRaw('
                AVG(temperature) as avg_temp,
                MIN(temperature) as min_temp,
                MAX(temperature) as max_temp,
                COUNT(*) as total_readings
            ')
            ->first();

        return response()->json([
            'sensor_id' => $sensorId,
            'stats' => $stats,
        ]);
    }
}
```

**Routes** (`routes/api.php`):

```php
use App\Http\Controllers\TemperatureController;
use Illuminate\Support\Facades\Route;

Route::prefix('temperatures')->group(function () {
    Route::get('/', [TemperatureController::class, 'index']);
    Route::get('/latest', [TemperatureController::class, 'latest']);
    Route::get('/{sensorId}/stats', [TemperatureController::class, 'stats']);
});
```

---

## Step 5: Start MQTT Subscriber

```bash
php artisan mqtt-broadcast
```

You should see:
```
MQTT Broadcast Supervisor started
Connected to broker: 127.0.0.1:1883
Subscribed to: myapp/#
```

---

## Step 6: Simulate IoT Sensor Data

**Option A: Using mosquitto_pub (Quick Test)**

```bash
# Publish temperature reading
mosquitto_pub -h 127.0.0.1 -p 1883 -t 'myapp/sensors/temp/sensor1' -m '{
  "temperature": 25.5,
  "humidity": 65.2,
  "location": "Office"
}'

# Simulate high temperature alert
mosquitto_pub -h 127.0.0.1 -p 1883 -t 'myapp/sensors/temp/sensor1' -m '{
  "temperature": 35.8,
  "humidity": 70.1,
  "location": "Server Room"
}'
```

**Option B: Using Laravel Artisan Command**

Create a test command:

```bash
php artisan make:command SimulateSensor
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use enzolarosa\MqttBroadcast\Facades\MqttBroadcast;

class SimulateSensor extends Command
{
    protected $signature = 'sensor:simulate {sensor_id=sensor1}';
    protected $description = 'Simulate IoT sensor publishing temperature data';

    public function handle(): void
    {
        $sensorId = $this->argument('sensor_id');
        $locations = ['Office', 'Server Room', 'Warehouse', 'Kitchen'];

        $this->info("Simulating sensor: {$sensorId}");
        $this->info('Press Ctrl+C to stop');

        while (true) {
            $data = [
                'temperature' => round(random_int(18, 35) + (random_int(0, 99) / 100), 2),
                'humidity' => round(random_int(40, 80) + (random_int(0, 99) / 100), 2),
                'location' => $locations[array_rand($locations)],
            ];

            MqttBroadcast::publish(
                "sensors/temp/{$sensorId}",
                json_encode($data)
            );

            $this->line(sprintf(
                '[%s] 🌡️  %s°C | 💧 %s%% | 📍 %s',
                now()->format('H:i:s'),
                $data['temperature'],
                $data['humidity'],
                $data['location']
            ));

            sleep(5);
        }
    }
}
```

Run it:
```bash
php artisan sensor:simulate sensor1
```

**Option C: ESP32/Arduino Code (Real IoT Device)**

See [ESP32-EXAMPLE.md](ESP32-EXAMPLE.md) for complete Arduino sketch.

---

## Step 7: View Results

**1. Check Database:**

```bash
php artisan tinker
>>> \App\Models\Temperature::latest()->take(5)->get()
```

**2. View Dashboard:**

Open: `http://your-app.test/mqtt-broadcast`

You'll see:
- Real-time message throughput
- Broker connection status
- Recent temperature messages

**3. Check API:**

```bash
curl http://your-app.test/api/temperatures/latest | jq
```

**4. Query Specific Sensor:**

```bash
curl "http://your-app.test/api/temperatures?sensor_id=sensor1" | jq
```

---

## Step 8: Production Deployment

**Supervisor Configuration** (`/etc/supervisor/conf.d/mqtt-broadcast.conf`):

```ini
[program:mqtt-broadcast]
process_name=%(program_name)s
command=php /var/www/html/artisan mqtt-broadcast
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/mqtt-broadcast.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mqtt-broadcast
```

**Queue Worker:**

```ini
[program:mqtt-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=default --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/queue-worker.log
```

---

## Testing

**Unit Tests:**

```php
<?php

namespace Tests\Feature;

use App\Models\Temperature;
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemperatureMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_temperature_reading_is_stored()
    {
        $payload = json_encode([
            'temperature' => 25.5,
            'humidity' => 65.2,
            'location' => 'Office',
        ]);

        event(new MqttMessageReceived('sensors/temp/sensor1', $payload, 0));

        $this->assertDatabaseHas('temperatures', [
            'sensor_id' => 'sensor1',
            'temperature' => 25.5,
            'humidity' => 65.2,
            'location' => 'Office',
        ]);
    }

    public function test_api_returns_latest_readings()
    {
        Temperature::factory()->create([
            'sensor_id' => 'sensor1',
            'temperature' => 25.5,
        ]);

        $response = $this->getJson('/api/temperatures/latest');

        $response->assertOk()
            ->assertJsonFragment([
                'sensor_id' => 'sensor1',
                'temperature' => 25.5,
            ]);
    }
}
```

---

## What's Next?

**Enhancements:**

1. **Add Grafana Dashboard** - Visualize historical data
2. **Implement Batch Processing** - Store readings every N seconds
3. **Add WebSocket Support** - Real-time frontend updates without polling
4. **Multi-Tenant Support** - Different sensors for different customers
5. **Machine Learning** - Predict temperature trends
6. **Mobile App** - Flutter/React Native with push notifications

**Related Examples:**

- [Device Control Example](../iot-device-control/) - Control IoT devices via MQTT
- [Chat Application](../realtime-chat/) - Real-time messaging
- [Notification System](../notification-system/) - Multi-channel alerts

---

## Troubleshooting

**Issue: "No messages received"**

Check topic prefix in config:
```php
'prefix' => 'myapp/',
```

Publish to correct topic:
```bash
mosquitto_pub -t 'myapp/sensors/temp/sensor1' -m '{"temperature": 25.5}'
```

**Issue: "Database not updating"**

Check listener is registered:
```bash
php artisan event:list
```

Check logs:
```bash
tail -f storage/logs/laravel.log
```

**Issue: "High memory usage"**

Limit historical data:
```php
// In a scheduled command
Temperature::where('created_at', '<', now()->subDays(30))->delete();
```

---

## Support

- 📖 [Full Documentation](https://github.com/enzolarosa/mqtt-broadcast)
- 💬 [GitHub Discussions](https://github.com/enzolarosa/mqtt-broadcast/discussions)
- 🐛 [Report Issues](https://github.com/enzolarosa/mqtt-broadcast/issues)
