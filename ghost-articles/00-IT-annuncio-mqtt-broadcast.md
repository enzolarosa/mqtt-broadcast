---
title: "MQTT Broadcast: Integrazione MQTT Production-Ready per Laravel"
slug: annuncio-mqtt-broadcast-pacchetto-laravel
excerpt: "Sono felice di presentare MQTT Broadcast, un nuovo pacchetto Laravel per l'integrazione MQTT con architettura Horizon, supporto multi-broker e dashboard real-time. Perfetto per IoT, messaggistica real-time e automazione industriale."
feature_image: null
featured: true
tags:
  - laravel
  - mqtt
  - pacchetto-laravel
  - iot
  - real-time
  - italiano
author: Enzo La Rosa
canonical_url: https://enzolarosa.dev/it/blog/annuncio-mqtt-broadcast-pacchetto-laravel
meta_title: "Annuncio MQTT Broadcast: MQTT Production-Ready per Laravel"
meta_description: "Nuovo pacchetto Laravel per integrazione MQTT con architettura Horizon, auto-reconnection, multi-broker e dashboard real-time. Testato con 356 test."
og_title: "MQTT Broadcast: Porta MQTT nelle Tue App Laravel"
og_description: "Pacchetto MQTT production-ready per Laravel. Architettura Horizon, multi-broker, auto-reconnection e dashboard di monitoraggio real-time."
twitter_title: "Nuovo: MQTT Broadcast per Laravel 🚀"
twitter_description: "Integrazione MQTT production-ready per Laravel. Perfetto per IoT, messaggistica real-time e automazione industriale."
---

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "MQTT Broadcast: Integrazione MQTT Production-Ready per Laravel",
  "description": "Annuncio di MQTT Broadcast, un nuovo pacchetto Laravel per l'integrazione MQTT robusta con architettura supervisor stile Horizon",
  "image": "https://enzolarosa.dev/content/images/mqtt-broadcast-announcement-it.jpg",
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
  "inLanguage": "it-IT",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://enzolarosa.dev/it/blog/annuncio-mqtt-broadcast-pacchetto-laravel"
  }
}
</script>

Sono entusiasta di presentare **MQTT Broadcast**, un pacchetto Laravel production-ready che porta un'integrazione MQTT robusta nelle tue applicazioni. Costruito seguendo il collaudato **pattern supervisor di Laravel Horizon**, è progettato per affidabilità, scalabilità e facilità d'uso.

[Read in English →](https://enzolarosa.dev/blog/announcing-mqtt-broadcast-laravel-package)

---

## Il Problema

MQTT è essenziale per le applicazioni moderne: dalle reti di sensori IoT alla messaggistica real-time fino all'automazione industriale. Ma integrare MQTT con Laravel ha sempre significato:

- Scrivere loop supervisor personalizzati soggetti a memory leak
- Gestire manualmente la logica di riconnessione
- Nessuno shutdown graceful (processi killati a metà messaggio)
- Capacità di monitoring limitate
- Limitazione a un solo broker (nessuna ridondanza)
- Difficoltà nel debug in produzione

La maggior parte delle soluzioni esistenti sono semplici wrapper attorno ai client MQTT, lasciandoti il compito di risolvere questi problemi di produzione da solo.

---

## La Soluzione

**MQTT Broadcast** adotta un approccio diverso: porta la collaudata **architettura supervisor di Horizon** all'integrazione MQTT.

### Caratteristiche Principali

**🏗️ Architettura Stile Horizon**
- Pattern supervisor multi-livello (Master → Supervisors → Brokers)
- Gestione automatica dei processi e recupero
- Shutdown graceful con gestione SIGTERM
- Gestione memoria con auto-restart

**🔄 Affidabilità Enterprise**
- Riconnessione con exponential backoff
- Supporto multi-broker per ridondanza
- Policy di retry configurabili
- Auto-recupero su perdita connessione

**📊 Monitoring Real-Time**
- Bellissima dashboard React 19
- Grafici throughput live (minuto/ora/giorno)
- Monitoraggio stato broker
- Tracciamento uso memoria
- Logging messaggi con ricerca

**🚀 Developer Experience**
- Installazione in 2 minuti
- Semplici Laravel Events per gestire messaggi
- Facade per pubblicazione facile
- Integrazione code per operazioni async
- Documentazione completa

**✅ Production-Ready**
- 356 test (327 unit + 29 integration)
- CI/CD con GitHub Actions
- Test con broker Mosquitto reale
- Prevenzione memory leak
- Type-safe (PHP 8.1+)

---

## Quick Start

Installa via Composer:

```bash
composer require enzolarosa/mqtt-broadcast
php artisan migrate
php artisan vendor:publish --tag="mqtt-broadcast-config"
```

Configura il tuo broker in `.env`:

```env
MQTT_HOST=mqtt.example.com
MQTT_PORT=1883
MQTT_USERNAME=tuo_username
MQTT_PASSWORD=tua_password
```

Avvia il subscriber:

```bash
php artisan mqtt-broadcast
```

Ascolta i messaggi in arrivo:

```php
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Support\Facades\Event;

Event::listen(MqttMessageReceived::class, function ($event) {
    logger()->info('Messaggio MQTT:', [
        'topic' => $event->topic,
        'message' => $event->message,
    ]);

    // Processa il tuo messaggio
    $data = json_decode($event->message, true);

    if ($event->topic === 'sensors/temperature') {
        TemperatureReading::create([
            'value' => $data['temperature'],
            'location' => $data['location'],
        ]);
    }
});
```

Pubblica messaggi:

```php
use enzolarosa\MqttBroadcast\Facades\MqttBroadcast;

MqttBroadcast::publish('alerts/critical', 'Sistema surriscaldato!');

// Con opzioni
MqttBroadcast::publish(
    topic: 'sensors/temp',
    message: json_encode(['value' => 25.5]),
    qos: 2
);
```

Accedi alla dashboard su `http://your-app.test/mqtt-broadcast` per monitorare tutto in real-time.

---

## IoT Reso Semplice

Ecco un esempio completo: Sensore ESP32 → MQTT → Laravel → Database → Email alert.

**Codice ESP32 (Arduino):**

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
  String payload = "{\"temperature\": 25.5, \"location\": \"Ufficio\"}";
  client.publish("sensors/temp/1", payload.c_str());
  delay(5000);
}
```

**Listener Laravel:**

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

        // Invia alert se troppo caldo
        if ($reading->temperature > 30) {
            Notification::route('mail', 'admin@example.com')
                ->notify(new HighTemperatureAlert($reading));
        }
    }
}
```

Fatto! Ora hai un sistema IoT production-ready con storage database e alert via email.

---

## Funzionalità Avanzate

### Multi-Broker per Ridondanza

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

Il supervisor si connette a **entrambi i broker simultaneamente**. Se il primario fallisce, l'applicazione continua a ricevere messaggi dal backup.

### Configurazione per Ambiente

```php
'environments' => [
    'local' => ['local-broker'],
    'staging' => ['staging-broker'],
    'production' => ['primary', 'backup'],
],
```

Usa broker diversi per ambiente, o sovrascrivi a runtime:

```bash
php artisan mqtt-broadcast --environment=production
```

### Gestione Memoria

Monitoraggio automatico memoria e restart graceful:

```php
'memory' => [
    'threshold_mb' => 128,
    'auto_restart' => true,
    'restart_delay_seconds' => 10,
],
```

Quando la memoria supera la soglia:
1. Il supervisor smette di accettare nuovi messaggi
2. Completa l'elaborazione dei messaggi correnti
3. Si riavvia gracefully
4. Si riconnette a tutti i broker

Nessun messaggio perso, nessun intervento manuale.

---

## Deploy in Produzione

Deploy con Supervisor per gestione automatica processi:

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

Monitora via dashboard o controlla lo stato processi:

```bash
php artisan tinker
>>> \enzolarosa\MqttBroadcast\Models\BrokerProcess::all();
```

---

## Dashboard Real-Time

La dashboard React inclusa fornisce monitoring completo:

- **Grafici Live**: Throughput messaggi (viste minuto/ora/giorno)
- **Stato Broker**: Stato connessione, uptime, conteggio messaggi
- **Log Messaggi**: Messaggi recenti con filtro per topic
- **Uso Memoria**: Utilizzo corrente con alert soglie
- **Metriche Code**: Monitoring job pending
- **Dark Mode**: Cambio tema automatico

**Autenticazione Produzione:**

```php
// app/Providers/AuthServiceProvider.php

Gate::define('viewMqttBroadcast', function ($user) {
    return $user->isAdmin();
});
```

---

## Testato in Battaglia

MQTT Broadcast è production-ready con test completi:

- **356 test totali**
  - 327 test unitari
  - 29 test di integrazione con broker Mosquitto reale
- **CI/CD GitHub Actions**
- **Test con broker reale** (non mocked)
- **Test memory leak**
- **Test scenari riconnessione**
- **Test graceful shutdown**

I test di integrazione avviano un vero broker Mosquitto via Docker, assicurando che il pacchetto funzioni con infrastruttura MQTT reale.

---

## Casi d'Uso

**IoT & Hardware:**
- Sensori ESP32, ESP8266, Arduino
- Raccolta dati Raspberry Pi
- PLC industriali (Industry 4.0)
- Automazione smart home
- Dispositivi fleet tracking

**Applicazioni Real-Time:**
- Sistemi chat live
- Servizi notifiche
- Pannelli controllo dispositivi
- Dashboard telemetria
- Tool collaborazione multi-user

**Industriale:**
- Automazione fabbrica
- Sistemi SCADA
- Monitoraggio ambientale
- Gestione energia
- Manutenzione predittiva

---

## Perché Non Usare il Pacchetto X?

Buona domanda! Ecco come si confronta MQTT Broadcast:

| Funzionalità | MQTT Broadcast | Altri Pacchetti |
|--------------|---------------|-----------------|
| **Architettura** | Supervisor stile Horizon | Loop semplici |
| **Auto-Riconnessione** | Exponential backoff | Retry base o nessuno |
| **Multi-Broker** | Connessioni simultanee | Solo singolo |
| **Shutdown Graceful** | Gestione SIGTERM | Force kill |
| **Gestione Memoria** | Auto-restart su soglia | Restart manuale |
| **Dashboard Monitoring** | UI React real-time | Nessuna dashboard |
| **Test Produzione** | 356 test con broker reale | Limitati o mocked |
| **Recupero Processi** | Supervisor auto-healing | Restart manuale |

Il pattern Horizon è stato testato in applicazioni Laravel per anni. MQTT Broadcast porta la stessa affidabilità all'integrazione MQTT.

---

## Documentazione

Guide complete disponibili:

- **[Guida Rapida](https://enzolarosa.dev/it/docs/mqtt-broadcast-guida-rapida)** - Installa e configura in 5 minuti
- **[Guida Configurazione](https://enzolarosa.dev/it/docs/mqtt-broadcast-configurazione)** - Tutte le opzioni spiegate
- **[Tutorial IoT](https://enzolarosa.dev/it/tutorials/monitoraggio-temperatura-iot-laravel-esp32)** - Esempio completo ESP32
- **[GitHub Wiki](https://github.com/enzolarosa/mqtt-broadcast/wiki)** - Documentazione community
- **[Analisi Architettura](https://github.com/enzolarosa/mqtt-broadcast/blob/main/docs/ARCHITECTURE.md)** - Come funziona internamente

---

## Provalo Oggi

```bash
composer require enzolarosa/mqtt-broadcast
```

**GitHub:** [enzolarosa/mqtt-broadcast](https://github.com/enzolarosa/mqtt-broadcast)
**Packagist:** [enzolarosa/mqtt-broadcast](https://packagist.org/packages/enzolarosa/mqtt-broadcast)

**Requisiti:**
- Laravel 9.x, 10.x, o 11.x
- PHP 8.1+
- Broker MQTT (Mosquitto, HiveMQ, AWS IoT Core, etc.)

---

## Cosa Succederà Dopo?

Sto lavorando attivamente su:

- **Video Tutorial** - Serie YouTube su IoT con Laravel
- **Altri Esempi** - Chat real-time, controllo dispositivi, multi-tenant
- **Guida Performance** - Gestire milioni di messaggi
- **Integrazione Grafana** - Dashboard pre-costruite

Hai feedback o casi d'uso da condividere? Mi piacerebbe sentirti nelle [GitHub Discussions](https://github.com/enzolarosa/mqtt-broadcast/discussions).

---

## Supporta il Progetto

Se trovi MQTT Broadcast utile:

- ⭐ Stella su [GitHub](https://github.com/enzolarosa/mqtt-broadcast)
- 🐛 Segnala problemi o suggerisci funzionalità
- 📖 Contribuisci alla documentazione
- 💬 Condividi i tuoi casi d'uso nelle Discussions
- 🔗 Diffondi la voce nella tua rete

Costruire applicazioni IoT e real-time con Laravel è appena diventato molto più semplice. Buon coding!

---

**Link Utili:**
- [Repository GitHub](https://github.com/enzolarosa/mqtt-broadcast)
- [Documentazione](https://enzolarosa.dev/it/docs/mqtt-broadcast-guida-rapida)
- [Tutorial IoT](https://enzolarosa.dev/it/tutorials/monitoraggio-temperatura-iot-laravel-esp32)
- [Segnala Problemi](https://github.com/enzolarosa/mqtt-broadcast/issues)
- [Discussioni](https://github.com/enzolarosa/mqtt-broadcast/discussions)
- [Versione Inglese](https://enzolarosa.dev/blog/announcing-mqtt-broadcast-laravel-package)
