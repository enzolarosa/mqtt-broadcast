# Installazione e Configurazione

## Cosa Fa

MQTT Broadcast e' un pacchetto Laravel che connette la tua applicazione ai broker di messaggi MQTT. Fornisce pubblicazione e sottoscrizione di messaggi in tempo reale, supervisione automatica dei processi, una dashboard di monitoraggio web-based e una Dead Letter Queue per i messaggi falliti. L'installazione richiede meno di 5 minuti e necessita di una configurazione minima.

## Percorso Utente

1. **Lo sviluppatore installa il pacchetto** tramite Composer. Laravel scopre automaticamente il pacchetto e registra i suoi servizi.
2. **Lo sviluppatore esegue il comando di installazione**, che pubblica il file di configurazione, un service provider locale (per il controllo accessi) e gli asset del frontend della dashboard.
3. **Lo sviluppatore configura il broker MQTT** aggiungendo host, porta e credenziali opzionali al file `.env`.
4. **Lo sviluppatore esegue le migrazioni del database** per creare le tabelle necessarie (log messaggi, tracciamento broker, job falliti).
5. **Lo sviluppatore configura l'accesso alla dashboard** modificando il service provider pubblicato per definire quali utenti possono accedere alla dashboard di monitoraggio.
6. **Lo sviluppatore avvia il supervisor** con un singolo comando Artisan. Il sistema si connette a tutti i broker configurati e inizia a elaborare i messaggi.
7. **Lo sviluppatore accede alla dashboard** all'URL configurato (default: `/mqtt-broadcast`) per monitorare lo stato dei broker, il throughput dei messaggi e i job falliti.

## Regole di Business

- Il pacchetto si auto-scopre in Laravel 11+ — non serve registrazione manuale del provider dopo l'esecuzione del comando di installazione.
- Le migrazioni sono auto-caricate dalla directory del vendor. Non e' necessario alcun passaggio di pubblicazione delle migrazioni.
- La dashboard di monitoraggio e' completamente accessibile in ambiente `local` senza alcuna configurazione del gate.
- In tutti gli ambienti non-local (staging, produzione), l'accesso alla dashboard e' negato per default. L'accesso deve essere concesso esplicitamente tramite un Laravel Gate.
- Possono essere configurate connessioni multiple a broker. Ogni ambiente (local, staging, produzione) puo' utilizzare un sottoinsieme diverso di connessioni.
- Il supervisor deve essere eseguito come processo a lunga durata (simile a Laravel Horizon). Un process manager (Supervisor, systemd) e' consigliato per la produzione.
- Un queue worker deve essere in esecuzione insieme al supervisor per la pubblicazione asincrona dei messaggi e l'elaborazione dei listener.

## Casi Limite

- **Rieseguire il comando di installazione** e' sicuro. Il controllo di registrazione del provider e' idempotente — salta se il provider e' gia' registrato.
- **Broker MQTT mancante all'avvio** causa tentativi di riconnessione con backoff esponenziale (fino a 20 tentativi per default), non un crash immediato.
- **Nessun Redis nell'ambiente** richiede di cambiare i valori di configurazione `cache_driver` e `queue.connection` a un driver diverso (database, file, ecc.).
- **Piu' sviluppatori su ambienti diversi** possono avere connessioni broker diverse mappando i nomi degli ambienti alle liste di connessioni nella configurazione.
- **Aggiornamento del pacchetto** puo' includere nuove migrazioni. Eseguire `php artisan migrate` dopo gli aggiornamenti le raccoglie automaticamente.
- **Asset della dashboard non aggiornati** dopo un upgrade possono essere aggiornati ripubblicando: `php artisan vendor:publish --tag=mqtt-broadcast-assets --force`.

## Permessi e Accesso

- **Installazione**: Richiede accesso a Composer e diritti di esecuzione di `php artisan`. Tipicamente eseguita da uno sviluppatore o durante il deployment CI/CD.
- **Configurazione**: Richiede accesso a `.env` e `config/mqtt-broadcast.php`. Questi file contengono credenziali del broker e devono essere trattati come sensibili.
- **Accesso alla dashboard**:
  - **Ambiente local**: Aperto a tutti gli utenti — nessuna autenticazione richiesta.
  - **Ambienti non-local**: Controllato dal Laravel Gate `viewMqttBroadcast`. Per default, tutti gli accessi sono negati. Il gate deve essere esplicitamente configurato nel `MqttBroadcastServiceProvider` pubblicato.
  - L'accesso puo' essere limitato per email, ruolo o qualsiasi logica personalizzata supportata dai Laravel Gate.
- **Processo supervisor**: Viene eseguito come utente del web server (es. `www-data`). Deve avere permesso di scrittura su database e cache.
- **Queue worker**: Deve essere in esecuzione sulla stessa connessione coda configurata in `mqtt-broadcast.queue.connection` (default: `redis`).
