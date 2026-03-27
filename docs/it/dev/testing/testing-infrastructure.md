# Infrastruttura di Testing

## Panoramica

`mqtt-broadcast` include una suite di test completa basata su **Pest PHP 4** con **Orchestra Testbench** per il bootstrap di Laravel. La suite si divide in **test unitari** (327, nessun broker richiesto) e **test di integrazione** (29, broker MQTT reale via Docker). Un helper `MockMqttClient` consente test MQTT deterministici senza dipendenze di rete.

L'infrastruttura di testing risolve tre problemi:
1. Testare la logica publish/subscribe MQTT senza un broker attivo
2. Bootstrap di un ambiente Laravel completo per un pacchetto standalone
3. Verifica delle interazioni reali con il broker (ciclo di vita connessione, multi-broker, pulizia cache) in CI

## Architettura

La suite di test utilizza un approccio stratificato:

- **Pest PHP** come test runner con il plugin Laravel per `Queue::fake()`, `Event::fake()`, test HTTP
- **Orchestra Testbench** fornisce un'app Laravel minimale (route, config, migration, service provider)
- **MockMqttClient** sostituisce il client reale `php-mqtt/client` nei test unitari
- **BrokerAvailability** controlla i test di integrazione — vengono saltati quando nessun broker e' disponibile
- **Mockery** per aspettative mock dettagliate su factory, repository e servizi

```mermaid
flowchart TD
    subgraph Unit["Test Unitari (327)"]
        MockClient[MockMqttClient]
        Mockery[Mockery Mocks]
        Fakes["Queue::fake / Event::fake"]
    end

    subgraph Integration["Test di Integrazione (29)"]
        BA[BrokerAvailability]
        Docker[Docker Mosquitto + Redis]
        RealClient[php-mqtt/client]
    end

    subgraph Foundation["Fondamenta"]
        Pest[Pest PHP 4]
        Testbench[Orchestra Testbench]
        TC[TestCase Base Class]
    end

    Unit --> Foundation
    Integration --> Foundation
    BA -->|salta se non disponibile| Integration
    Docker -->|porte 1883, 6379| RealClient
```

## Come Funziona

### Bootstrap dei Test (`tests/Pest.php`)

Ogni test eredita dal `TestCase` personalizzato tramite la direttiva `uses()` di Pest. La pulizia di Mockery viene eseguita automaticamente dopo ogni test.

```php
uses(TestCase::class)->in(__DIR__);

afterEach(function () {
    Mockery::close();
});
```

### TestCase Base (`tests/TestCase.php`)

Estende `Orchestra\Testbench\TestCase` e gestisce:

1. **Registrazione del service provider** — carica `MqttBroadcastServiceProvider` rendendo disponibili tutti i binding, le route e le migration
2. **Caricamento migration** — esegue tutte le migration del pacchetto su un database SQLite in memoria
3. **Config di default** — configura l'intero albero `mqtt-broadcast` (connessioni, rate limiting, coda, cache, failed jobs, ambienti)
4. **Risoluzione namespace factory** — mappa le classi model a `Database\Factories\` per il supporto factory di Eloquent

Scelte di configurazione chiave:
- **Database**: SQLite `:memory:` (veloce, usa e getta)
- **Cache**: driver `array` (nessuna dipendenza Redis nei test unitari)
- **Coda**: driver `sync` (i job vengono eseguiti immediatamente, nessuna tabella jobs necessaria)
- **Rate limiting**: abilitato con strategia `reject` e cache driver `array`

#### Metodi Helper

| Metodo | Scopo |
|--------|-------|
| `setMqttConfig(string $broker, array $config)` | Override della config di connessione per un broker specifico nel singolo test |
| `getProtectedProperty(object $object, string $property)` | Accesso a proprieta' private/protected tramite reflection (utile per asserzioni sugli interni dei job) |
| `brokerAvailable()` | Verifica se un broker MQTT reale e' raggiungibile |
| `requiresBroker()` | Salta il test con messaggio se il broker non e' disponibile |

### MockMqttClient (`tests/Helpers/MockMqttClient.php`)

Sostituto drop-in per `PhpMqtt\Client\MqttClient` che registra tutte le chiamate publish/subscribe in memoria. Impone lo stato di connessione — pubblicare o sottoscrivere mentre disconnesso lancia `RuntimeException`.

#### API

| Metodo | Descrizione |
|--------|-------------|
| `connect($settings, bool $cleanSession)` | Imposta lo stato connesso a `true` |
| `disconnect()` | Imposta lo stato connesso a `false` |
| `isConnected()` | Ritorna lo stato di connessione |
| `publish(string $topic, string $message, int $qos, bool $retain)` | Registra il messaggio nell'array `publishedMessages` |
| `subscribe(string $topic, callable $callback, int $qos)` | Registra la sottoscrizione nell'array `subscribedTopics` |
| `loopOnce(int $timeout)` | No-op per testing |
| `assertPublished(string $topic, ?string $message, ?int $qos)` | Asserisce che un messaggio e' stato pubblicato (match opzionale su message/QoS) |
| `assertNotPublished(string $topic)` | Asserisce che nessun messaggio e' stato pubblicato sul topic |
| `getPublishedMessage(int $index)` | Ottieni messaggio pubblicato per indice |
| `getLastPublishedMessage()` | Ottieni l'ultimo messaggio pubblicato |
| `clearPublished()` | Resetta l'array dei messaggi pubblicati |
| `getPublishedCount()` | Conteggio dei messaggi pubblicati |

Ogni messaggio registrato e' un array:
```php
[
    'topic' => 'sensors/temperature',
    'message' => '{"value": 25.5}',
    'qos' => 0,
    'retain' => false,
    'timestamp' => 1711497600,
]
```

### BrokerAvailability (`tests/Support/BrokerAvailability.php`)

Controlla i test di integrazione con una verifica di connessione al broker reale. Il risultato viene cachato staticamente per l'intera esecuzione dei test.

Ordine di rilevamento:
1. **Variabile d'ambiente** `MQTT_BROKER_AVAILABLE` — la CI puo' impostarla a `true`/`false` per saltare il tentativo di connessione
2. **Tentativo di connessione live** — crea un `MqttClient` temporaneo, si connette con timeout di 2 secondi, si disconnette
3. **Fallback diagnostico** — se la connessione fallisce, `getUnavailableReason()` tenta un socket raw per distinguere "porta chiusa" da "handshake MQTT fallito"

```mermaid
flowchart TD
    Start[requiresBroker] --> EnvCheck{Variabile MQTT_BROKER_AVAILABLE<br>impostata?}
    EnvCheck -->|si| EnvValue{Valore truthy?}
    EnvValue -->|si| Run[Esegui test]
    EnvValue -->|no| Skip[markTestSkipped]
    EnvCheck -->|no| TryConnect[Connetti a 127.0.0.1:1883<br>timeout=2s]
    TryConnect -->|successo| CacheTrue[Cache: available=true]
    TryConnect -->|eccezione| CacheFalse[Cache: available=false]
    CacheTrue --> Run
    CacheFalse --> Diagnose[getUnavailableReason]
    Diagnose --> Skip
```

## Componenti Chiave

| File | Classe/Metodo | Responsabilita' |
|------|---------------|-----------------|
| `tests/Pest.php` | — | Bootstrap Pest: associa `TestCase`, chiude Mockery automaticamente |
| `tests/TestCase.php` | `TestCase` | Base Orchestra Testbench: migration, config, registrazione provider |
| `tests/TestCase.php` | `setMqttConfig()` | Override config broker per singolo test |
| `tests/TestCase.php` | `getProtectedProperty()` | Accesso tramite reflection a proprieta' private |
| `tests/TestCase.php` | `requiresBroker()` | Guardia per saltare test di integrazione |
| `tests/Helpers/MockMqttClient.php` | `MockMqttClient` | Client MQTT in memoria con metodi di asserzione |
| `tests/Support/BrokerAvailability.php` | `BrokerAvailability` | Verifica raggiungibilita' broker con cache e diagnostica |
| `composer.json` | `scripts.test` | `vendor/bin/pest` |
| `composer.json` | `scripts.test-coverage` | `vendor/bin/pest --coverage` |

## Configurazione

### Script Composer

| Script | Comando | Scopo |
|--------|---------|-------|
| `composer test` | `vendor/bin/pest` | Esegui la suite completa (unit + integration se broker disponibile) |
| `composer test-coverage` | `vendor/bin/pest --coverage` | Esegui con copertura del codice |
| `composer pint` | `vendor/bin/pint` | Stile codice (eseguire prima del commit) |
| `composer analyse` | `vendor/bin/phpstan analyse` | Analisi statica livello 7 |

### Esecuzione dei Test

```bash
# Solo test unitari (nessun broker necessario)
vendor/bin/pest --exclude-group=integration

# Suite completa con test di integrazione
docker compose -f docker-compose.test.yml up -d
vendor/bin/pest
docker compose -f docker-compose.test.yml down
```

### Dipendenze di Sviluppo

| Pacchetto | Versione | Ruolo |
|-----------|----------|-------|
| `pestphp/pest` | ^4.0 | Test runner |
| `pestphp/pest-plugin-laravel` | ^4.0 | Asserzioni e helper specifici Laravel |
| `orchestra/testbench` | ^9.0\|^10.0 | Bootstrap app Laravel per pacchetti |
| `phpunit/phpunit` | ^10.5\|^11.0\|^12.0 | Motore di test sottostante |
| `nunomaduro/collision` | ^7.0\|^8.0 | Output errori migliorato |
| `nunomaduro/larastan` | ^2.0.1 | Regole PHPStan per Laravel |
| `phpstan/phpstan-deprecation-rules` | ^1.0 | Rilevamento deprecation |
| `phpstan/phpstan-phpunit` | ^1.0 | Analisi PHPUnit-aware |
| `spatie/laravel-ray` | ^1.26 | Strumento di debug |

## Pattern di Test

### Test Unitario: Mock della MqttClientFactory

```php
beforeEach(function () {
    $this->mockFactory = Mockery::mock(MqttClientFactory::class);
    $this->mockClient = Mockery::mock(MqttClient::class);
    $this->app->instance(MqttClientFactory::class, $this->mockFactory);
});

it('publishes message via factory', function () {
    $this->mockFactory->shouldReceive('create')
        ->once()
        ->andReturn($this->mockClient);

    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('publish')
        ->with('test/topic', '{"key":"value"}', 0, false)
        ->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // ... trigger the code under test
});
```

### Test Unitario: Queue Faking con Asserzione su Proprieta' Protette

```php
it('dispatches MqttMessageJob with correct parameters', function () {
    Queue::fake();

    MqttBroadcast::publish('sensors/temperature', '{"value": 25.5}');

    Queue::assertPushed(MqttMessageJob::class, function ($job) {
        return $this->getProtectedProperty($job, 'topic') === 'sensors/temperature'
            && $this->getProtectedProperty($job, 'message') === '{"value": 25.5}';
    });
});
```

### Test Unitario: Controller HTTP con Database

```php
it('returns healthy status when brokers are active', function () {
    BrokerProcess::factory()->create([
        'broker_name' => 'default',
        'connection_status' => 'connected',
        'last_heartbeat_at' => now(),
    ]);

    $response = $this->getJson('/mqtt-broadcast/api/health');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'healthy',
            'data' => [
                'brokers' => ['total' => 1, 'active' => 1, 'stale' => 0],
            ],
        ]);
});
```

### Test Unitario: BrokerSupervisor con Mockery `andReturnUsing`

```php
// Ritorna istanze model reali dal repository mockato
$this->repository->shouldReceive('create')
    ->byDefault()
    ->andReturnUsing(function ($name, $masterName, $pid) {
        $broker = new BrokerProcess();
        $broker->broker_name = $name;
        $broker->master_name = $masterName;
        $broker->pid = $pid;
        $broker->exists = true;
        return $broker;
    });
```

### Test di Integrazione: Ciclo di Vita del Processo Reale

```php
beforeEach(function () {
    $this->requiresBroker();
    // Avvia un processo mqtt-broadcast reale
    $this->processHandle = proc_open(
        'exec php testbench mqtt-broadcast',
        $descriptors,
        $pipes,
        getcwd()
    );
});

afterEach(function () {
    if ($this->processHandle) {
        proc_terminate($this->processHandle, SIGTERM);
        proc_close($this->processHandle);
    }
});
```

## Gestione Errori

| Scenario | Comportamento |
|----------|---------------|
| Nessun broker disponibile per test di integrazione | `markTestSkipped()` con ragione diagnostica |
| `MockMqttClient::publish()` mentre disconnesso | Lancia `RuntimeException('Not connected to MQTT broker')` |
| `MockMqttClient::assertPublished()` fallisce | Lancia `RuntimeException("Message not published to topic: {$topic}")` |
| Fallimento migration SQLite | La suite si arresta — verificare la compatibilita' della sintassi migration con SQLite |
| Aspettativa Mockery non soddisfatta | `Mockery::close()` in `afterEach` provoca fallimento dell'asserzione |

```mermaid
stateDiagram-v2
    [*] --> Setup: Pest esegue il test
    Setup --> UnitPath: Nessun broker necessario
    Setup --> IntegrationPath: requiresBroker()

    UnitPath --> MockSetup: beforeEach
    MockSetup --> Execute: Esegui corpo del test
    Execute --> Assert: Asserzioni
    Assert --> Cleanup: afterEach (Mockery::close)
    Cleanup --> [*]: Pass/Fail

    IntegrationPath --> BrokerCheck: BrokerAvailability::isAvailable()
    BrokerCheck --> Skipped: Non disponibile
    BrokerCheck --> DockerSetup: Disponibile
    DockerSetup --> Execute
    Skipped --> [*]: markTestSkipped
```
