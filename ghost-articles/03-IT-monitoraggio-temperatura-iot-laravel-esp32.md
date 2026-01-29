---
title: "Come Creare un Sistema IoT di Monitoraggio Temperatura con Laravel e ESP32"
slug: monitoraggio-temperatura-iot-laravel-esp32
excerpt: "Impara a costruire un sistema completo di monitoraggio temperatura IoT usando Laravel MQTT Broadcast e ESP32. Include codice Arduino, dashboard real-time, alert via email e deploy in produzione."
feature_image: null
featured: true
tags:
  - mqtt-broadcast
  - laravel
  - iot
  - esp32
  - arduino
  - tutorial-italiano
  - sensori-temperatura
  - italiano
author: Enzo La Rosa
canonical_url: https://enzolarosa.dev/it/tutorials/monitoraggio-temperatura-iot-laravel-esp32
meta_title: "Sistema IoT Monitoraggio Temperatura con Laravel & ESP32 - Tutorial Completo"
meta_description: "Costruisci un sistema di monitoraggio temperatura IoT con Laravel MQTT Broadcast ed ESP32. Include database, dashboard real-time, alert e codice Arduino completo."
og_title: "Come Costruire un Sistema IoT con Laravel & ESP32"
og_description: "Tutorial completo: sensori ESP32 → MQTT → Laravel → Dashboard real-time. Include sketch Arduino, progettazione database e deploy in produzione."
twitter_title: "Sistema IoT Temperatura: Laravel + ESP32 Tutorial Italiano 🇮🇹"
twitter_description: "Costruisci un sistema IoT completo con Laravel MQTT ed ESP32. Monitoraggio real-time, alert e codice pronto per produzione."
---

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "Come Creare un Sistema IoT di Monitoraggio Temperatura con Laravel e ESP32",
  "description": "Tutorial completo che mostra come costruire un sistema di monitoraggio temperatura IoT pronto per produzione usando Laravel MQTT Broadcast e microcontrollori ESP32",
  "image": "https://enzolarosa.dev/content/images/iot-temperature-monitoring-it.jpg",
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
  "inLanguage": "it-IT",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://enzolarosa.dev/it/tutorials/monitoraggio-temperatura-iot-laravel-esp32"
  },
  "dependencies": "Laravel MQTT Broadcast",
  "proficiencyLevel": "Intermedio",
  "timeRequired": "PT45M",
  "articleBody": "Tutorial IoT completo con ESP32, Laravel, MQTT e monitoraggio real-time in italiano"
}
</script>

Costruisci un **sistema completo di monitoraggio temperatura IoT** da zero usando **Laravel MQTT Broadcast** ed **ESP32**. Questo tutorial copre tutto, dal collegamento hardware al deploy in produzione.

**Cosa costruirai:**
- 🌡️ Sensori ESP32 che pubblicano dati temperatura via MQTT
- 💾 Backend Laravel che memorizza i dati nel database
- 📊 Dashboard real-time con grafici live
- 📧 Alert via email quando la temperatura supera le soglie
- 🚀 Deploy pronto per produzione con Supervisor

**Tempo necessario:** ~45 minuti
**Difficoltà:** Intermedio

[Read in English →](https://enzolarosa.dev/tutorials/iot-temperature-monitoring-laravel-esp32)

---

## Panoramica Architettura

```
┌─────────────┐         MQTT          ┌──────────────┐
│  ESP32/DHT22│ ──────publish────────▶ │ MQTT Broker  │
│   Sensori   │    sensors/temp/1      │ (Mosquitto)  │
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
                  │ Database │        │  Code    │    │  Eventi  │
                  │  MySQL   │        │  Redis   │    │  Alert   │
                  └──────────┘        └──────────┘    └──────────┘
```

---

## Prerequisiti

**Hardware:**
- ESP32 DevKit (o ESP8266)
- Sensore temperatura/umidità DHT22
- Breadboard e cavi jumper
- Cavo USB per programmazione

**Software:**
- Applicazione Laravel 11.x
- Arduino IDE con supporto ESP32
- MQTT broker Mosquitto (o cloud broker)
- Redis (per le code Laravel)

**Competenze:**
- Conoscenza base di Laravel
- Programmazione Arduino base
- Comprensione del protocollo MQTT

---

## Parte 1: Setup Backend Laravel

### Step 1: Installare MQTT Broadcast

```bash
composer require enzolarosa/mqtt-broadcast
php artisan migrate
php artisan vendor:publish --tag="mqtt-broadcast-config"
```

### Step 2: Configurare il Broker MQTT

Aggiungi a `.env`:

```env
MQTT_HOST=127.0.0.1
MQTT_PORT=1883
MQTT_PREFIX=iot/
MQTT_LOG_ENABLE=true

# Configurazione email per gli alert
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=alert@example.com
```

### Step 3: Creare lo Schema Database

Genera migration:

```bash
php artisan make:migration create_temperature_readings_table
```

**File migration:**

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
            $table->integer('rssi')->nullable(); // Potenza segnale WiFi
            $table->timestamps();

            // Indice per query time-series
            $table->index(['sensor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperature_readings');
    }
};
```

Esegui migration:

```bash
php artisan migrate
```

### Step 4: Creare il Model

```bash
php artisan make:model TemperatureReading
```

**app/Models/TemperatureReading.php:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
     * Ottieni letture delle ultime N ore
     */
    public static function recentReadings(string $sensorId, int $hours = 24)
    {
        return static::where('sensor_id', $sensorId)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Ottieni statistiche per un sensore
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

### Step 5: Creare Event Listener

Genera listener:

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
    // Soglie temperatura (Celsius)
    private const TEMP_WARNING = 28;
    private const TEMP_CRITICAL = 35;

    public function handle(MqttMessageReceived $event): void
    {
        // Processa solo topic sensori temperatura
        if (!str_starts_with($event->topic, 'iot/sensors/temp/')) {
            return;
        }

        // Estrai sensor ID dal topic: iot/sensors/temp/{sensor_id}
        $sensorId = str_replace('iot/sensors/temp/', '', $event->topic);

        // Parse payload JSON
        $data = json_decode($event->message, true);

        if (!$data || !isset($data['temperature'])) {
            Log::warning('Dati temperatura non validi ricevuti', [
                'topic' => $event->topic,
                'message' => $event->message,
            ]);
            return;
        }

        // Salva lettura nel database
        $reading = TemperatureReading::create([
            'sensor_id' => $sensorId,
            'temperature' => $data['temperature'],
            'humidity' => $data['humidity'] ?? null,
            'location' => $data['location'] ?? 'Sconosciuta',
            'rssi' => $data['rssi'] ?? null,
        ]);

        Log::info('Lettura temperatura salvata', [
            'sensor' => $sensorId,
            'temp' => $reading->temperature,
            'location' => $reading->location,
        ]);

        // Invia alert se necessario
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

**Registra listener in `app/Providers/EventServiceProvider.php`:**

```php
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use App\Listeners\ProcessTemperatureReading;

protected $listen = [
    MqttMessageReceived::class => [
        ProcessTemperatureReading::class,
    ],
];
```

### Step 6: Creare Notifica Alert

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
            ? 'CRITICO: Temperatura Elevata Rilevata!'
            : 'AVVISO: Alert Temperatura';

        return (new MailMessage)
            ->subject($emoji . ' ' . $subject)
            ->level($this->severity)
            ->line("Temperatura elevata rilevata sul sensore **{$this->reading->sensor_id}**")
            ->line("**Temperatura:** {$this->reading->temperature}°C")
            ->line("**Posizione:** {$this->reading->location}")
            ->line("**Orario:** {$this->reading->created_at->format('d/m/Y H:i:s')}")
            ->when($this->reading->humidity, function ($mail) {
                return $mail->line("**Umidità:** {$this->reading->humidity}%");
            })
            ->action('Visualizza Dashboard', url('/mqtt-broadcast'))
            ->line('Controlla immediatamente il sistema.');
    }
}
```

---

## Parte 2: Setup Sensore ESP32

### Collegamento Hardware

**DHT22 a ESP32:**
```
DHT22 VCC  → ESP32 3.3V
DHT22 GND  → ESP32 GND
DHT22 DATA → ESP32 GPIO 4 (D4)
```

Opzionale: Aggiungi resistenza pull-up 4.7kΩ tra DATA e VCC.

### Librerie Arduino

Installa tramite Library Manager:
1. **WiFi** (built-in)
2. **PubSubClient** di Nick O'Leary
3. **DHT sensor library** di Adafruit
4. **Adafruit Unified Sensor**
5. **ArduinoJson** di Benoit Blanchon

### Codice ESP32

Sketch Arduino completo con riconnessione WiFi e gestione errori:

```cpp
#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <ArduinoJson.h>

// ========================================
// CONFIGURAZIONE
// ========================================

// Credenziali WiFi
const char* ssid = "TUO_WIFI_SSID";
const char* password = "TUO_WIFI_PASSWORD";

// MQTT Broker
const char* mqtt_server = "192.168.1.100";  // IP del tuo server Laravel
const int mqtt_port = 1883;
const char* mqtt_user = "";                 // Se autenticazione abilitata
const char* mqtt_password = "";

// Topic MQTT (deve corrispondere al prefix Laravel)
const char* sensor_id = "sensor1";
String topic = String("iot/sensors/temp/") + sensor_id;

// Configurazione Sensore
#define DHTPIN 4           // Pin GPIO
#define DHTTYPE DHT22      // DHT22 o DHT11
const char* location = "Ufficio";

// Intervallo pubblicazione (millisecondi)
const unsigned long publish_interval = 5000;  // 5 secondi

// ========================================
// OGGETTI GLOBALI
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
  Serial.println("Monitor Temperatura ESP32");
  Serial.println("=================================\n");

  // Inizializza sensore DHT
  dht.begin();
  Serial.println("✓ Sensore DHT inizializzato");

  // Connetti a WiFi
  setup_wifi();

  // Configura MQTT
  client.setServer(mqtt_server, mqtt_port);
  client.setKeepAlive(60);
  client.setSocketTimeout(30);

  Serial.println("\n✓ Setup completato");
  Serial.println("=================================\n");
}

// ========================================
// LOOP PRINCIPALE
// ========================================

void loop() {
  // Mantieni connessione MQTT
  if (!client.connected()) {
    reconnect_mqtt();
  }
  client.loop();

  // Pubblica dati sensore periodicamente
  unsigned long now = millis();
  if (now - last_publish >= publish_interval) {
    last_publish = now;
    publish_temperature();
  }
}

// ========================================
// CONNESSIONE WIFI
// ========================================

void setup_wifi() {
  delay(10);
  Serial.print("Connessione a WiFi: ");
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
    Serial.println("\n✓ WiFi connesso");
    Serial.print("  Indirizzo IP: ");
    Serial.println(WiFi.localIP());
    Serial.print("  Potenza segnale: ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
  } else {
    Serial.println("\n✗ Connessione WiFi fallita!");
    Serial.println("  Riavvio tra 5 secondi...");
    delay(5000);
    ESP.restart();
  }
}

// ========================================
// CONNESSIONE MQTT
// ========================================

void reconnect_mqtt() {
  mqtt_reconnect_attempts++;

  Serial.print("Connessione a broker MQTT: ");
  Serial.print(mqtt_server);
  Serial.print(":");
  Serial.println(mqtt_port);

  // Genera ID client univoco
  String clientId = "ESP32-" + String(sensor_id) + "-" + String(random(0xffff), HEX);

  // Tentativo connessione
  bool connected;
  if (strlen(mqtt_user) > 0) {
    connected = client.connect(clientId.c_str(), mqtt_user, mqtt_password);
  } else {
    connected = client.connect(clientId.c_str());
  }

  if (connected) {
    Serial.println("✓ MQTT connesso");
    Serial.print("  ID Client: ");
    Serial.println(clientId);
    Serial.print("  Pubblicazione su: ");
    Serial.println(topic);
    mqtt_reconnect_attempts = 0;
  } else {
    Serial.print("✗ Connessione MQTT fallita, rc=");
    Serial.println(client.state());

    // Exponential backoff
    int delay_seconds = min(mqtt_reconnect_attempts * 2, 60);
    Serial.print("  Riprovo tra ");
    Serial.print(delay_seconds);
    Serial.println(" secondi...");
    delay(delay_seconds * 1000);

    // Riavvia dopo troppi fallimenti
    if (mqtt_reconnect_attempts > 10) {
      Serial.println("✗ Troppi fallimenti MQTT, riavvio...");
      delay(2000);
      ESP.restart();
    }
  }
}

// ========================================
// PUBBLICA DATI TEMPERATURA
// ========================================

void publish_temperature() {
  // Leggi sensore
  float humidity = dht.readHumidity();
  float temperature = dht.readTemperature();

  // Valida letture
  if (isnan(humidity) || isnan(temperature)) {
    failed_readings++;
    Serial.println("✗ Impossibile leggere dal sensore DHT");

    if (failed_readings > 10) {
      Serial.println("✗ Troppi errori sensore, riavvio...");
      delay(2000);
      ESP.restart();
    }
    return;
  }

  failed_readings = 0;

  // Crea payload JSON
  StaticJsonDocument<256> doc;
  doc["temperature"] = round(temperature * 100) / 100.0;
  doc["humidity"] = round(humidity * 100) / 100.0;
  doc["location"] = location;
  doc["sensor_id"] = sensor_id;
  doc["rssi"] = WiFi.RSSI();
  doc["uptime"] = millis() / 1000;

  String payload;
  serializeJson(doc, payload);

  // Pubblica su MQTT
  if (client.publish(topic.c_str(), payload.c_str(), false)) {
    Serial.println("✓ Pubblicato:");
    Serial.print("  Temperatura: ");
    Serial.print(temperature);
    Serial.println("°C");
    Serial.print("  Umidità: ");
    Serial.print(humidity);
    Serial.println("%");
    Serial.print("  Payload: ");
    Serial.println(payload);
  } else {
    Serial.println("✗ Pubblicazione fallita");
  }
}
```

### Carica su ESP32

1. Seleziona **Strumenti → Scheda → ESP32 Dev Module**
2. Seleziona la **Porta** corretta
3. Clicca **Carica**
4. Apri **Monitor Seriale** (115200 baud)

Dovresti vedere:

```
=================================
Monitor Temperatura ESP32
=================================

✓ Sensore DHT inizializzato
Connessione a WiFi: MiaRete
.....
✓ WiFi connesso
  Indirizzo IP: 192.168.1.50
  Potenza segnale: -45 dBm

Connessione a broker MQTT: 192.168.1.100:1883
✓ MQTT connesso
  ID Client: ESP32-sensor1-A3F2
  Pubblicazione su: iot/sensors/temp/sensor1

✓ Setup completato
=================================

✓ Pubblicato:
  Temperatura: 24.50°C
  Umidità: 62.30%
  Payload: {"temperature":24.5,"humidity":62.3,"location":"Ufficio","sensor_id":"sensor1","rssi":-45,"uptime":5}
```

---

## Parte 3: Test e Monitoraggio

### Avvia Subscriber Laravel

```bash
php artisan mqtt-broadcast
```

Dovresti vedere i messaggi ricevuti:

```
Supervisor MQTT Broadcast avviato
Connesso al broker: 127.0.0.1:1883
Iscritto a: iot/#

[2026-01-28 10:15:23] Messaggio ricevuto su iot/sensors/temp/sensor1
[2026-01-28 10:15:23] Lettura temperatura salvata: sensor1, 24.5°C
```

### Visualizza Dashboard

Apri `http://your-app.test/mqtt-broadcast` per vedere:
- Throughput messaggi real-time
- Stato connessione broker
- Messaggi temperatura recenti

### Controlla Database

```bash
php artisan tinker
```

```php
>>> \App\Models\TemperatureReading::latest()->take(5)->get();
>>> \App\Models\TemperatureReading::stats('sensor1', 24);
```

### Testa Alert

Attiva manualmente una temperatura alta:

```cpp
// Nel codice ESP32, sovrascrivi temporaneamente:
float temperature = 36.0;  // Sopra TEMP_CRITICAL
```

Ricarica il codice e dovresti ricevere un'email di alert.

---

## Parte 4: Deploy in Produzione

Vedi la [Guida Completa Deploy in Produzione](https://enzolarosa.dev/it/docs/mqtt-broadcast-deploy-produzione) per:

- Configurazione Supervisor
- Setup Nginx
- Certificati SSL
- Ottimizzazione database
- Scalare queue worker
- Monitoraggio con Grafana

---

## Prossimi Passi

**Miglioramenti:**
- Aggiungi più tipi di sensori (pressione, CO2, ecc.)
- Crea dashboard personalizzate Grafana
- Implementa WebSocket per aggiornamenti frontend real-time
- Aggiungi machine learning per rilevamento anomalie
- Crea app mobile con notifiche push

**Tutorial Correlati:**
- [Controllo Dispositivi via MQTT](https://enzolarosa.dev/it/tutorials/controllo-dispositivi-iot-mqtt)
- [Piattaforma IoT Multi-Tenant](https://enzolarosa.dev/it/tutorials/piattaforma-iot-multi-tenant)
- [Automazione Industriale con PLC](https://enzolarosa.dev/it/tutorials/industry-40-laravel)

---

## Serve Aiuto?

- 📖 [Documentazione](https://github.com/enzolarosa/mqtt-broadcast)
- 💬 [Discussioni](https://github.com/enzolarosa/mqtt-broadcast/discussions)
- 🐛 [Segnala Problemi](https://github.com/enzolarosa/mqtt-broadcast/issues)
- 🇬🇧 [English Version](https://enzolarosa.dev/tutorials/iot-temperature-monitoring-laravel-esp32)

---

**Articoli Correlati:**
- [Iniziare con MQTT Broadcast](https://enzolarosa.dev/it/docs/mqtt-broadcast-guida-rapida)
- [Guida Configurazione](https://enzolarosa.dev/it/docs/mqtt-broadcast-configurazione)
- [Deploy in Produzione](https://enzolarosa.dev/it/docs/mqtt-broadcast-deploy-produzione)
