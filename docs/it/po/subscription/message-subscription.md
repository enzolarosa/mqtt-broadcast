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
- **Filtraggio topic**: i listener possono mirare a un topic specifico o usare `*` per ricevere tutti i messaggi sul proprio broker. Il matching dei topic e' esatto — i wildcard MQTT (`+`, `#`) non sono supportati a livello di listener.
- **Elaborazione solo JSON (listener personalizzati)**: i listener personalizzati costruiti sulla classe base elaborano solo messaggi con payload JSON validi di tipo oggetto. I messaggi non-JSON, array JSON e scalari JSON vengono saltati silenziosamente. Questo e' imposto dalla classe base, non configurabile.
- **Il Logger cattura tutto**: il logger integrato e' un componente separato che non segue le stesse regole dei listener personalizzati. Salva tutti i messaggi indipendentemente dal formato — oggetti JSON, array, testo semplice o qualsiasi altro payload. Questo assicura una copertura di audit completa.
- **Due architetture di listener**: il sistema supporta due tipi di listener distinti. I listener personalizzati impongono filtraggio JSON rigoroso e matching broker/topic. Il logger integrato bypassa tutto il filtraggio per garantire la cattura completa dei messaggi. Gli sviluppatori devono scegliere quale pattern si adatta al loro caso d'uso.
- **Gate di pre-elaborazione**: i listener personalizzati possono includere un controllo di pre-elaborazione che viene eseguito prima del parsing del messaggio. Questo permette l'elaborazione condizionale (es. saltare durante la manutenzione) senza modificare la logica principale del listener.
- **Elaborazione non bloccante**: tutta l'elaborazione dei listener avviene in modo asincrono tramite la coda. Il loop della connessione MQTT non viene mai bloccato da handler lenti.
- **Logging opzionale**: il logging dei messaggi nel database e' disabilitato di default e deve essere abilitato esplicitamente. Quando abilitato, vengono salvati sia messaggi JSON che non-JSON.
- **Isolamento errori**: se un listener fallisce, non influenza gli altri listener ne' la connessione MQTT. I job dei listener falliti seguono le regole standard di retry della coda.
- **Identificazione messaggi tramite UUID**: ogni messaggio salvato nel log riceve un identificatore unico (UUID) per l'accesso API. Questo impedisce di esporre gli ID interni del database esternamente.

## Casi Limite

- **Payload JSON non valido**: se un messaggio non e' JSON valido, i listener personalizzati loggano un warning (includendo i primi 200 caratteri del messaggio per il debug) e lo saltano. Il logger integrato salva comunque il messaggio grezzo.
- **Payload JSON di tipo array**: se un messaggio e' JSON valido ma decodifica in un array invece che in un oggetto, i listener personalizzati lo saltano silenziosamente. Il logger lo salva normalmente. Questo puo' essere inaspettato se i dispositivi MQTT inviano payload di tipo array.
- **Disconnessione dal broker**: se la connessione al broker cade, il supervisor gestisce la riconnessione automaticamente (vedi [supervisione processi](../supervisor/process-supervision.md)). I messaggi inviati durante la disconnessione vengono persi (MQTT QoS 0) o riconsegnati dal broker (QoS 1/2).
- **Eccezione nel listener**: se un listener lancia un'eccezione durante l'elaborazione, il queue worker segna il job come fallito. Gli altri listener per lo stesso messaggio non vengono influenzati.
- **Alto volume di messaggi**: poiche' i listener girano sulla coda, il throughput dei messaggi e' limitato dal numero di queue worker, non dalla velocita' della connessione MQTT. Scalare i queue worker per gestire volumi maggiori.
- **Messaggi duplicati**: con QoS 1 o 2, il broker MQTT puo' riconsegnare i messaggi. Il sistema non deduplica — i listener devono gestire l'idempotenza se necessario.
- **Prefisso topic vuoto**: se non e' configurato alcun prefisso, il sistema si sottoscrive a `#` (tutti i topic sul broker). Questo puo' produrre un alto volume di messaggi su broker condivisi.
- **Rifiuto pre-elaborazione**: se il gate di pre-elaborazione ritorna false, il messaggio viene scartato silenziosamente per quel listener. Nessun warning viene loggato e nessun errore viene sollevato.

## Permessi e Accesso

- La sottoscrizione messaggi gira come parte del processo supervisor — non richiede interazione utente ne' autenticazione.
- Aggiungere o modificare listener richiede modifiche al codice nel `MqttBroadcastServiceProvider` dell'applicazione.
- Il logger del database scrive nella tabella `mqtt_loggers` usando la connessione database configurata.
- I queue worker devono essere in esecuzione per elaborare i job dei listener. Senza worker attivi, i messaggi si accodano ma non vengono elaborati.
