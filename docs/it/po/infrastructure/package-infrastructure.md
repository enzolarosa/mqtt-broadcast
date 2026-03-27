# Infrastruttura del Pacchetto

## Cosa Fa

L'infrastruttura del pacchetto e' il livello fondamentale che fa funzionare MQTT Broadcast come pacchetto Laravel plug-and-play. Gestisce il setup automatico: quando uno sviluppatore installa il pacchetto tramite Composer, tutto viene collegato automaticamente — le tabelle del database vengono create, la configurazione viene unita, la dashboard diventa accessibile e tutti i comandi vengono registrati. Non e' necessario alcun collegamento manuale oltre all'esecuzione di `php artisan migrate`.

Per gli ambienti di produzione, gli amministratori possono personalizzare il controllo degli accessi, cambiare l'URL della dashboard, configurare quali broker MQTT vengono utilizzati per ambiente e regolare i parametri di performance come i limiti di memoria e il comportamento di riconnessione — tutto attraverso un singolo file di configurazione e un override opzionale del provider.

## Percorso Utente

1. Lo sviluppatore installa il pacchetto tramite `composer require enzolarosa/mqtt-broadcast`.
2. Laravel scopre automaticamente il service provider — nessuna registrazione manuale necessaria.
3. Lo sviluppatore esegue `php artisan mqtt-broadcast:install` per pubblicare il file di configurazione e il provider opzionale.
4. Lo sviluppatore esegue `php artisan migrate` — tre tabelle del database vengono create automaticamente (`mqtt_brokers`, `mqtt_loggers`, `mqtt_failed_jobs`).
5. Lo sviluppatore configura le credenziali del broker MQTT nel `.env` (`MQTT_HOST`, `MQTT_PORT`, ecc.).
6. Lo sviluppatore opzionalmente mappa gli ambienti ai broker nel file di configurazione (es. la produzione usa un broker, lo staging ne usa un altro).
7. In produzione, lo sviluppatore pubblica lo stub del provider e personalizza il gate di autorizzazione per controllare chi puo' accedere alla dashboard.
8. La dashboard e' accessibile su `/mqtt-broadcast` (o un percorso personalizzato) con protezione middleware automatica.

## Regole di Business

- **Auto-discovery**: Il pacchetto si registra automaticamente tramite l'auto-discovery di Composer — nessuna registrazione manuale del provider in `config/app.php` e' necessaria (Laravel 5.5+).
- **Migrazioni zero-publish**: Le migrazioni del database vengono eseguite direttamente dalla directory vendor. Gli utenti non hanno mai bisogno di pubblicare o modificare le migrazioni.
- **Selezione broker basata sull'ambiente**: La configurazione `environments` mappa `APP_ENV` a una lista di connessioni broker. Solo i broker configurati per l'ambiente corrente vengono supervisionati.
- **Accesso deny-all predefinito**: La dashboard e' accessibile a tutti nell'ambiente `local`, ma nega tutti gli accessi negli ambienti non-local per default. E' richiesta una configurazione esplicita del gate per la produzione.
- **Precedenza configurazione**: I valori della config pubblicata sovrascrivono i default del pacchetto. Le variabili d'ambiente sovrascrivono i valori del file di configurazione.
- **Tre gruppi di pubblicazione**: Config, stub del provider e asset frontend possono essere pubblicati indipendentemente o insieme.
- **Servizi singleton**: I servizi core (factory client, repository broker, repository supervisor) sono registrati come singleton — un'istanza per ciclo di vita della richiesta.
- **Compatibilita' cache rotte**: Le rotte non vengono registrate quando la cache delle rotte e' attiva, prevenendo la duplicazione.

## Casi Limite

- **Config broker mancante**: Se una connessione referenziata nella mappatura ambiente non esiste nell'array `connections`, il sistema lancia un errore di configurazione chiaro con la chiave mancante esatta.
- **Provider multipli**: Se un utente pubblica lo stub del provider ma ha anche il provider base auto-discovered, il provider pubblicato estende quello base — non crea un conflitto. L'override `registerGate()` del provider pubblicato ha la precedenza.
- **Cache rotte**: Se le rotte sono in cache (`php artisan route:cache`), il pacchetto salta completamente la registrazione dinamica delle rotte. Gli utenti devono ri-cachare le rotte dopo le modifiche alla configurazione.
- **Mismatch connessione database**: Le tabelle `mqtt_loggers` e `mqtt_failed_jobs` usano connessioni database configurabili. Se una connessione e' mal configurata, le migrazioni falliscono con un errore database chiaro.
- **Conflitti funzioni helper**: Le helper globali `mqttMessage()` e `mqttMessageSync()` sono avvolte in guard `function_exists()`, cosi' gli utenti possono definire le proprie versioni senza conflitti.

## Permessi e Accesso

- **Ambiente local**: Accesso completo alla dashboard senza autenticazione (gestito dal middleware `Authorize` che bypassa il controllo del gate).
- **Ambienti non-local**: L'accesso richiede il superamento del gate `viewMqttBroadcast`. Per default, questo gate nega tutti gli utenti.
- **Gate personalizzato**: Gli utenti sovrascrivono il gate nel loro `MqttBroadcastServiceProvider` pubblicato — tipicamente controllando email utente, ruolo o permessi.
- **Stack middleware**: Tutte le rotte della dashboard usano il gruppo middleware `web` piu' il middleware `Authorize`. Questo puo' essere personalizzato nella configurazione per aggiungere middleware addizionali (es. `auth`, restrizioni IP personalizzate).
- **Endpoint API**: Tutte le rotte API (`/api/*`) sono protette dallo stesso stack middleware della dashboard — nessuna autenticazione API separata.
