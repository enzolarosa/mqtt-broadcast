# Pipeline degli Asset della Dashboard

## Cosa Fa

La dashboard di MQTT Broadcast è un'interfaccia di monitoraggio web che necessita di file di stile CSS e codice JavaScript per funzionare nel browser. La pipeline degli asset è il sistema che compila, distribuisce e carica questi file affinché la dashboard venga visualizzata correttamente quando un utente vi accede.

Il pacchetto gestisce automaticamente gli asset — durante l'installazione, i file necessari vengono pubblicati nell'applicazione host. Quando un utente visita l'URL della dashboard, il sistema localizza i file corretti e li inietta nella pagina.

## Percorso Utente

1. Lo sviluppatore installa il pacchetto tramite `composer require enzolarosa/mqtt-broadcast`
2. Lo sviluppatore esegue `php artisan mqtt-broadcast:install`, che pubblica gli asset della dashboard (CSS, JavaScript, manifest) nella directory `public/vendor/mqtt-broadcast/` dell'applicazione
3. L'utente naviga all'URL della dashboard (predefinito: `/mqtt-broadcast`)
4. Il server renderizza la pagina della dashboard, includendo automaticamente i file CSS e JavaScript corretti
5. Il browser carica i file e l'interfaccia di monitoraggio React appare
6. Quando il pacchetto viene aggiornato, lo sviluppatore ripubblica gli asset (`php artisan vendor:publish --tag=mqtt-broadcast-assets --force`) per ottenere l'ultima versione della dashboard

## Regole di Business

- Gli asset devono essere pubblicati nell'applicazione host prima che la dashboard possa essere visualizzata in produzione
- La dashboard usa un file manifest per mappare il codice sorgente ai file compilati — se il manifest è assente, la dashboard appare vuota senza messaggi di errore
- Esistono due strategie di caricamento degli asset: l'integrazione standard Laravel Vite (predefinita) e metodi helper manuali (`css()`/`js()`) come fallback
- Durante lo sviluppo, gli asset possono essere serviti tramite un server di sviluppo live con ricaricamento istantaneo (Hot Module Replacement) — non è necessaria la pubblicazione
- I nomi dei file sono deterministici (senza hash del contenuto), quindi la ripubblicazione dopo un aggiornamento del pacchetto sovrascrive sempre la versione precedente
- Gli asset sono isolati in `vendor/mqtt-broadcast/` per evitare conflitti con il build frontend dell'applicazione host

## Casi Limite

- **Asset non pubblicati**: la pagina della dashboard si carica ma appare vuota — nessun errore visivo viene mostrato all'utente. Lo sviluppatore deve pubblicare gli asset manualmente.
- **Asset obsoleti dopo aggiornamento del pacchetto**: la dashboard potrebbe mostrare una UI vecchia o comportarsi in modo inatteso se gli asset non vengono ripubblicati dopo l'aggiornamento del pacchetto via Composer. Eseguire `vendor:publish --tag=mqtt-broadcast-assets --force` risolve il problema.
- **Server di sviluppo non attivo**: se l'applicazione è in modalità sviluppo e il Vite dev server non è avviato, il browser mostra errori di connessione nella console e la dashboard non viene visualizzata.
- **Percorso public personalizzato**: se l'applicazione host usa una directory public non standard, gli URL degli asset generati dall'helper `asset()` potrebbero non risolversi correttamente.
- **Più versioni del pacchetto**: la pubblicazione degli asset da versioni diverse del pacchetto sovrascrive i file precedenti — non esiste un meccanismo di versionamento o rollback.

## Permessi e Accesso

- La pubblicazione degli asset richiede accesso alla console (`php artisan`) — è un'azione per sviluppatori/deployment, non disponibile per gli utenti finali
- Gli asset pubblicati sono file statici serviti dalla directory `public/` — non è necessaria autenticazione per caricare i file CSS e JavaScript
- L'accesso alla pagina della dashboard (che utilizza questi asset) è controllato dal gate di autorizzazione `viewMqttBroadcast` e dal middleware `Authorize` — consultare la documentazione del Dashboard Monitoring per i dettagli
- Il tag di pubblicazione `laravel-assets` permette a Laravel 11+ di includere gli asset di MQTT Broadcast nella pubblicazione bulk degli asset (`php artisan vendor:publish --tag=laravel-assets`)
