# Pattern Repository

## Cosa Fa

Il sistema utilizza due meccanismi di archiviazione separati per tracciare lo stato dei processi MQTT. I processi broker (le connessioni individuali ai server MQTT) sono archiviati nel database per durabilita' e interrogabilita'. Lo stato del master supervisor (l'orchestratore che gestisce tutti i broker) e' archiviato in un livello di cache veloce (tipicamente Redis) perche' e' effimero e aggiornato frequentemente.

Questa separazione garantisce che i record dei broker sopravvivano ai riavvii dell'applicazione e siano disponibili per la visualizzazione nella dashboard, mentre lo stato del supervisor rimane leggero e scade automaticamente quando non e' piu' necessario.

## Percorso Utente

1. Un amministratore avvia il supervisor MQTT tramite `php artisan mqtt-broadcast`
2. Il sistema registra il master supervisor nella cache e crea un record nel database per ogni connessione broker
3. Durante l'esecuzione, ogni broker aggiorna il suo timestamp heartbeat ad ogni ciclo del loop; il master supervisor aggiorna il suo stato in cache (PID, stato, utilizzo memoria, numero broker)
4. La dashboard legge da entrambi i livelli di archiviazione per mostrare lo stato in tempo reale: broker attivi, salute delle connessioni, stato del supervisor e utilizzo della memoria
5. L'endpoint di salute usa i timestamp heartbeat per determinare se i broker sono vivi (heartbeat entro gli ultimi 2 minuti = attivo)
6. Allo shutdown, sia i record broker che le voci cache del supervisor vengono puliti automaticamente

## Regole di Business

- I record broker sono persistenti -- sopravvivono ai riavvii dell'applicazione e restano interrogabili fino all'eliminazione esplicita
- Lo stato del supervisor e' effimero -- scade automaticamente dopo un TTL configurabile (default: 1 ora)
- Tutte le operazioni di pulizia usano un pattern "silent fail": eliminare un record inesistente non causa errori, prevenendo fallimenti a cascata durante lo shutdown
- Ogni broker genera un nome univoco combinando l'hostname e un token casuale (es. `johns-macbook-a3f2`)
- La freschezza dell'heartbeat determina lo stato della connessione: i broker con un heartbeat piu' vecchio di 2 minuti vengono mostrati come inattivi nella dashboard
- La soglia di obsolescenza (configurabile, default 5 minuti) determina quando un broker e' considerato completamente obsoleto e idoneo alla pulizia

## Casi Limite

- **Driver Memcached**: Il sistema non puo' elencare tutti i supervisor attivi quando si usa Memcached come driver di cache perche' il protocollo Memcached non supporta l'enumerazione delle chiavi. La dashboard mostrera' dati supervisor vuoti. Redis e' il driver raccomandato per la produzione.
- **Record orfani**: Se un processo crasha senza shutdown ordinato, i record broker rimangono nel database. Il controllo di salute li mostrera' come inattivi (heartbeat obsoleto) e il comando terminate puo' pulirli tramite PID.
- **Broker multipli con stesso PID**: Sebbene improbabile, `deleteByPid()` rimuovera' tutti i record corrispondenti anziche' uno solo.
- **Cache file corrotta**: Se si usa il driver cache file e un file diventa corrotto, viene loggato come warning e saltato -- non blocca la scoperta degli altri supervisor.
- **Scadenza TTL cache**: Se il master supervisor non aggiorna il suo stato entro la finestra TTL (default 1 ora), la voce scade silenziosamente. Il supervisor riapparira' alla prossima chiamata `persist()`.

## Permessi e Accesso

- Le operazioni dei repository sono interne al pacchetto -- non sono esposte direttamente tramite endpoint API
- I controller della dashboard che leggono i dati dei repository sono protetti dal middleware `Authorize`, che richiede il permesso gate `viewMqttBroadcast` (o ambiente locale)
- Il comando terminate, che attiva la pulizia dei repository, e' un comando Artisan e richiede accesso CLI
- L'accesso al database segue la connessione database configurata dall'applicazione; l'accesso alla cache segue il driver cache configurato
