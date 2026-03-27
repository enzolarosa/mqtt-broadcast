# Dashboard e Monitoraggio

## Cosa Fa

Il sistema MQTT Broadcast include una dashboard di monitoraggio integrata e un'API di health check che fornisce visibilita in tempo reale sullo stato operativo del sistema. Gli operatori possono visualizzare lo stato dei broker, il throughput dei messaggi, l'utilizzo della memoria e la profondita delle code — tutto da un'unica interfaccia web o tramite chiamate REST API per l'integrazione con strumenti di monitoraggio esterni.

## Percorso Utente

1. L'operatore naviga all'URL della dashboard (es. `https://app.example.com/mqtt-broadcast`).
2. Il sistema verifica l'autorizzazione — negli ambienti locali, l'accesso e concesso automaticamente; in produzione, l'operatore deve essere esplicitamente autorizzato attraverso un gate di permessi.
3. La dashboard si carica e mostra un pannello panoramico con: stato del sistema (in esecuzione/fermo), conteggio broker attivi, messaggi al minuto, utilizzo memoria e uptime.
4. L'operatore puo approfondire i singoli broker per vedere lo stato della connessione, l'orario dell'ultimo heartbeat, l'ID del processo e i messaggi recenti.
5. L'operatore puo visualizzare i log dei messaggi con filtri per broker o topic, e ispezionare i payload dei singoli messaggi.
6. I grafici di throughput mostrano le tendenze del volume messaggi nell'ultima ora (per minuto), nelle ultime 24 ore (per ora) o negli ultimi 7 giorni (per giorno).
7. Gli strumenti di monitoraggio esterni possono interrogare l'endpoint di health check per determinare se il sistema e sano (HTTP 200) o non sano (HTTP 503).

## Regole di Business

- **Autorizzazione**: Negli ambienti locali, tutti gli utenti hanno accesso. In produzione, solo gli utenti esplicitamente autorizzati attraverso il gate di permessi `viewMqttBroadcast` possono accedere alla dashboard e all'API.
- **Stale dei broker**: Un broker e considerato "stale" se il suo ultimo heartbeat e piu vecchio di 2 minuti. Il sistema continua a mostrare i broker stale ma li contrassegna di conseguenza.
- **Livelli di stato connessione**: Ogni broker ha uno di quattro stati — connected (attivo e in funzione), idle (attivo ma in pausa), reconnecting (heartbeat in fase di stale), o disconnected (nessun heartbeat per oltre 2 minuti).
- **Criteri di salute**: Il sistema e "sano" quando almeno un broker e attivo E il processo master supervisor e in esecuzione. Se una delle due condizioni fallisce, l'endpoint di salute restituisce uno stato non sano.
- **Soglie di memoria**: L'utilizzo della memoria e confrontato con una soglia configurata. Sotto l'80% e normale, 80–99% genera un avviso, e 100%+ e critico.
- **Dipendenza dal logging messaggi**: I log dei messaggi, le analitiche dei topic e le metriche di throughput sono disponibili solo quando il logging dei messaggi e esplicitamente abilitato nella configurazione. Quando disabilitato, queste sezioni mostrano risultati vuoti con un indicatore chiaro.
- **Limiti messaggi**: Le query sui log messaggi sono limitate a 100 risultati per richiesta (default 30) per prevenire un carico eccessivo sul database.
- **Analitiche topic**: Solo i messaggi delle ultime 24 ore vengono considerati per la classifica dei topic, limitati ai primi 20.

## Casi Limite

- **Nessun broker registrato**: La dashboard mostra stato "fermo" con conteggi a zero. L'endpoint di salute restituisce 503.
- **Master supervisor non in esecuzione**: L'endpoint di salute restituisce 503 anche se i broker sono attivi. Memoria e uptime vengono mostrati a zero.
- **Logging disabilitato**: Le sezioni log messaggi, topic e metriche restituiscono dataset vuoti con un flag di metadati che indica che il logging e disabilitato. Non vengono generati errori.
- **Crash del processo broker**: Il broker rimane nel database ma il suo heartbeat diventa stale. Transiziona attraverso "reconnecting" a "disconnected" in circa 2 minuti.
- **Messaggi non JSON**: I messaggi che non sono JSON valido vengono mostrati come testo raw. La vista di dettaglio indica se il messaggio e JSON o meno.
- **Messaggi molto lunghi**: Le anteprime dei messaggi sono troncate a 100 caratteri nelle viste lista. Il contenuto completo e disponibile nella vista di dettaglio.

## Permessi e Accesso

- **Ambiente locale**: Tutti gli utenti autenticati e non autenticati possono accedere alla dashboard e all'API senza restrizioni.
- **Ambienti non locali**: L'accesso richiede il superamento del gate `viewMqttBroadcast`. Per default, questo gate nega tutti gli accessi — l'amministratore dell'applicazione deve definire esplicitamente quali utenti sono autorizzati.
- **Personalizzazione**: L'autorizzazione e configurata dallo sviluppatore dell'applicazione nel service provider pubblicato, tipicamente verificando l'indirizzo email o il ruolo dell'utente.
- **Stack middleware**: Le route della dashboard utilizzano il gruppo middleware `web` per default, il che significa che l'autenticazione standard via sessione si applica prima del controllo del gate.
