# Sottoscrizione Messaggi ed Eventi

## Cosa Fa

La sottoscrizione messaggi permette al sistema di ascoltare i messaggi MQTT in arrivo dai broker connessi e instradarli automaticamente verso gli handler appropriati. Quando un messaggio arriva su qualsiasi topic, il sistema lo invia come evento a cui i listener registrati possono reagire — salvando messaggi nel log, elaborando dati dei sensori, attivando workflow o qualsiasi altra logica di business.

Il sistema supporta piu' broker contemporaneamente, ciascuno con il proprio prefisso topic e regole di filtraggio. I listener possono mirare a broker e topic specifici, oppure ascoltare tutto.

## Percorso Utente

1. Il processo supervisor di MQTT Broadcast si avvia e si connette a ciascun broker configurato.
2. Ogni connessione broker si sottoscrive a tutti i topic sotto il prefisso configurato.
3. Quando un messaggio arriva su qualsiasi topic sottoscritto, il sistema crea un evento interno.
4. Tutti i listener registrati ricevono l'evento attraverso il sistema di code (elaborazione asincrona).
5. Ogni listener decide se elaborare il messaggio in base ai propri filtri broker e topic.
6. Se il logger integrato e' abilitato, ogni messaggio viene anche salvato nel database per audit/debug.

## Regole di Business

- **Sottoscrizione automatica**: il sistema si sottoscrive a tutti i topic sotto il prefisso configurato usando un wildcard MQTT — non e' necessaria la registrazione manuale dei topic.
- **Isolamento broker**: i listener elaborano solo messaggi dal broker designato. Un listener configurato per il broker "local" ignora i messaggi dal broker "remote".
- **Filtraggio topic**: i listener possono mirare a un topic specifico o usare `*` per ricevere tutti i messaggi sul proprio broker.
- **Elaborazione solo JSON (listener predefiniti)**: la classe base dei listener integrata elabora solo messaggi con payload JSON validi di tipo oggetto. I messaggi non-JSON o array/scalari JSON vengono saltati silenziosamente.
- **Elaborazione non bloccante**: tutta l'elaborazione dei listener avviene in modo asincrono tramite la coda. Il loop della connessione MQTT non viene mai bloccato da handler lenti.
- **Logging opzionale**: il logging dei messaggi nel database e' disabilitato di default e deve essere abilitato esplicitamente. Quando abilitato, vengono salvati sia messaggi JSON che non-JSON.
- **Isolamento errori**: se un listener fallisce, non influenza gli altri listener ne' la connessione MQTT. I job dei listener falliti seguono le regole standard di retry della coda.

## Casi Limite

- **Payload JSON non valido**: se un messaggio non e' JSON valido, il listener predefinito logga un warning e lo salta. Il logger integrato salva comunque il messaggio grezzo.
- **Disconnessione dal broker**: se la connessione al broker cade, il supervisor gestisce la riconnessione automaticamente (vedi [supervisione processi](../supervisor/process-supervision.md)). I messaggi inviati durante la disconnessione vengono persi (MQTT QoS 0) o riconsegnati dal broker (QoS 1/2).
- **Eccezione nel listener**: se un listener lancia un'eccezione durante l'elaborazione, il queue worker segna il job come fallito. Gli altri listener per lo stesso messaggio non vengono influenzati.
- **Alto volume di messaggi**: poiche' i listener girano sulla coda, il throughput dei messaggi e' limitato dal numero di queue worker, non dalla velocita' della connessione MQTT. Scalare i queue worker per gestire volumi maggiori.
- **Messaggi duplicati**: con QoS 1 o 2, il broker MQTT puo' riconsegnare i messaggi. Il sistema non deduplica — i listener devono gestire l'idempotenza se necessario.
- **Prefisso topic vuoto**: se non e' configurato alcun prefisso, il sistema si sottoscrive a `#` (tutti i topic sul broker). Questo puo' produrre un alto volume di messaggi su broker condivisi.

## Permessi e Accesso

- La sottoscrizione messaggi gira come parte del processo supervisor — non richiede interazione utente ne' autenticazione.
- Aggiungere o modificare listener richiede modifiche al codice nel `MqttBroadcastServiceProvider` dell'applicazione.
- Il logger del database scrive nella tabella `mqtt_loggers` usando la connessione database configurata.
- I queue worker devono essere in esecuzione per elaborare i job dei listener. Senza worker attivi, i messaggi si accodano ma non vengono elaborati.
