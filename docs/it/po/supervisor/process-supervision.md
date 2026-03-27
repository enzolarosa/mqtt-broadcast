# Supervisione dei Processi

## Cosa Fa

MQTT Broadcast funziona come un servizio in background che mantiene connessioni persistenti verso uno o piu' broker MQTT. Si riconnette automaticamente quando le connessioni cadono, monitora la propria salute e si spegne in modo pulito quando richiesto. Il sistema e' progettato per funzionare 24/7 in produzione senza intervento manuale.

## Percorso Utente

1. L'amministratore configura le connessioni ai broker MQTT nel file di configurazione dell'applicazione.
2. L'amministratore avvia il servizio eseguendo il comando artisan `mqtt-broadcast`.
3. Il sistema si connette a tutti i broker configurati e inizia ad ascoltare i messaggi.
4. Se una connessione a un broker cade, il sistema riprova automaticamente con ritardi crescenti (1s, 2s, 4s, 8s... fino a 60s).
5. La dashboard in tempo reale mostra lo stato di ogni connessione broker (connesso, in riconnessione, disconnesso).
6. Se il sistema usa troppa memoria, si riavvia automaticamente per prevenire crash.
7. L'amministratore puo' mettere in pausa, riprendere o fermare il servizio usando segnali di processo standard o il comando di terminazione.

## Regole di Business

- Una sola istanza del master supervisor puo' funzionare per macchina alla volta. Un secondo tentativo viene bloccato con un avviso.
- Ogni connessione MQTT ha il proprio supervisor isolato — un fallimento in una connessione non influisce sulle altre.
- Il sistema riprova le connessioni fallite fino a 20 volte per default prima di resettare o arrendersi (configurabile).
- Se le connessioni falliscono continuamente per 1 ora (configurabile), il sistema rinuncia a quel broker.
- Alla fermata, il sistema disconnette tutti i broker e pulisce tutti i record di tracciamento prima di uscire.
- I timestamp di heartbeat vengono aggiornati ogni secondo per ogni connessione attiva, abilitando il rilevamento di processi inattivi.

## Casi Limite

- **Broker irraggiungibile all'avvio**: tutte le connessioni vengono validate prima che il sistema parta. Se una configurazione di connessione e' invalida, il sistema rifiuta di avviarsi e mostra gli errori.
- **Broker che cade durante il funzionamento**: il supervisor entra in modalita' di riconnessione con backoff esponenziale. I messaggi non vengono persi se il broker supporta sessioni persistenti (QoS > 0).
- **Processo terminato con SIGKILL (-9)**: i record nel database e nella cache diventano obsoleti. Il comando di terminazione include la logica di pulizia per rimuovere i record orfani. La soglia di inattivita' (default: 5 minuti) aiuta inoltre la dashboard a rilevare i processi morti.
- **Memory leak**: il sistema esegue la garbage collection ogni 100 iterazioni e monitora la memoria rispetto a una soglia configurabile (default: 128 MB). Se la memoria resta sopra la soglia per 10 secondi, il sistema si riavvia.
- **Nomi broker duplicati**: ogni broker riceve un nome univoco usando l'hostname della macchina piu' un token casuale, prevenendo collisioni.

## Permessi e Accesso

- Avviare e fermare il servizio MQTT richiede accesso a livello server (SSH o process manager).
- Non esiste un permesso a livello applicazione per avviare/fermare i supervisor — questa e' una competenza infrastrutturale.
- La dashboard di monitoraggio e' protetta da un Gate Laravel (`viewMqttBroadcast`). Negli ambienti locali, l'accesso e' aperto a tutti gli utenti autenticati.
