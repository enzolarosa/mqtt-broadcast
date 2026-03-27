# Riferimento API HTTP

## Cosa Fa

La dashboard MQTT Broadcast include un'API REST completa che fornisce dati di monitoraggio del sistema in tempo reale. Questa API alimenta la dashboard web e può essere utilizzata anche da strumenti esterni per il monitoraggio della salute, alerting e integrazione con altri sistemi.

L'API fornisce sei gruppi di funzionalità:

1. **Health checking** — Il sistema funziona correttamente?
2. **Statistiche dashboard** — Riepilogo di broker, messaggi, memoria e stato delle code
3. **Gestione broker** — Stato dettagliato di ogni connessione MQTT broker
4. **Log messaggi** — Messaggi MQTT recenti con ricerca e filtri
5. **Metriche throughput** — Dati time-series per grafici delle prestazioni
6. **Gestione job falliti** — Visualizza, ritenta e elimina messaggi che non sono stati pubblicati

## Percorso Utente

### Monitoraggio Salute del Sistema

1. Un operatore o strumento di monitoraggio invia una richiesta all'endpoint di health check
2. Il sistema verifica se i broker sono attivi e se il supervisor è in esecuzione
3. Viene restituito uno stato "healthy" (con HTTP 200) o "unhealthy" (con HTTP 503)
4. L'utilizzo della memoria viene verificato rispetto alla soglia configurata e riportato come pass/warn/critical

### Visualizzazione Statistiche Dashboard

1. La dashboard si carica e richiede le statistiche aggregate
2. Il sistema restituisce conteggi broker, throughput messaggi, dimensione coda, utilizzo memoria e conteggio job falliti
3. La dashboard renderizza le card panoramiche con questi dati

### Ispezione Broker

1. L'utente visualizza la lista di tutti i broker con il loro stato di connessione
2. Ogni broker mostra se è connesso, in pausa, in riconnessione o disconnesso
3. L'utente può cliccare su un broker per vederne i messaggi recenti (ultimi 10)

### Ricerca Messaggi

1. L'utente apre la vista dei log messaggi
2. Può filtrare per nome broker (match esatto) o topic (match parziale)
3. I messaggi sono mostrati con un'anteprima; cliccando si rivela il contenuto completo
4. Un endpoint separato elenca i 20 topic più attivi

### Analisi Throughput

1. L'utente visualizza i grafici throughput che mostrano il volume di messaggi nel tempo
2. Sono disponibili tre intervalli temporali: ultima ora (per minuto), ultime 24 ore (per ora), ultimi 7 giorni (per giorno)
3. Un endpoint di riepilogo fornisce totali e medie per ogni finestra temporale, più il minuto di picco

### Gestione Job Falliti

1. L'utente visualizza la lista dei tentativi di pubblicazione MQTT falliti
2. Ogni voce mostra il broker, il topic, un'anteprima del messaggio e il riepilogo dell'errore
3. L'utente può ritentare un singolo job o ritentare tutti i job eleggibili contemporaneamente
4. L'utente può eliminare singoli job falliti o svuotare l'intera coda
5. Un cooldown di 1 minuto impedisce che lo stesso job venga ritentato ripetutamente

## Regole di Business

- **Dipendenza dal logging**: i log messaggi, le analisi dei topic e le metriche di throughput sono disponibili solo quando il logging dei messaggi è abilitato nella configurazione. Quando disabilitato, questi endpoint restituiscono dati vuoti con un indicatore chiaro.
- **Criteri di salute**: il sistema è considerato sano solo quando almeno un broker ha un heartbeat attivo E il processo master supervisor è trovato nella cache.
- **Soglie memoria**: l'utilizzo della memoria è riportato come percentuale del limite configurato. Sotto l'80% è "pass", 80–99% è "warn", 100%+ è "critical".
- **Stato connessione**: lo stato di connessione di ogni broker è calcolato dalla freschezza dell'heartbeat — non memorizzato. Un heartbeat più recente di 30 secondi significa connesso (o in pausa se sospeso); da 30 secondi a 2 minuti significa in riconnessione; oltre 2 minuti significa disconnesso.
- **Identificazione job falliti**: i job falliti sono identificati da un UUID, non da un numero sequenziale. Questo previene la divulgazione di informazioni sul numero totale di fallimenti.
- **Cooldown retry**: quando si ritentano tutti i job falliti, vengono inclusi solo i job che non sono mai stati ritentati o il cui ultimo retry risale a più di 1 minuto fa. Questo previene il sovraccarico della coda con retry ripetuti.
- **Limiti risultati**: tutti gli endpoint lista limitano i risultati a 100 elementi per richiesta, con un default di 30.
- **Anteprime messaggi**: il contenuto dei messaggi è troncato a 100 caratteri nelle viste lista. Il contenuto completo è disponibile solo visualizzando un singolo messaggio o job fallito.

## Casi Limite

- **Logging disabilitato**: gli endpoint messaggi, topic e metriche restituiscono array vuoti con `logging_enabled: false` anziché un errore. La dashboard rileva questa condizione e mostra un messaggio appropriato.
- **Nessun broker in esecuzione**: l'health check restituisce HTTP 503. Le statistiche dashboard mostrano `status: "stopped"` con tutti zeri.
- **Master supervisor non trovato**: l'health check restituisce HTTP 503. Le statistiche memoria mostrano 0 MB di utilizzo, 0% di utilizzo e 0 uptime.
- **Flush di tutti i job falliti**: utilizza il truncate della tabella (non l'eliminazione riga per riga) per prestazioni. Restituisce il conteggio dei job eliminati.
- **Retry di un job appena ritentato**: l'endpoint retry singolo non ha cooldown — solo "retry all" applica il cooldown di 1 minuto. Un singolo job può essere ritentato immediatamente e ripetutamente.
- **Messaggi non JSON**: i messaggi che non sono JSON valido vengono restituiti così come sono. Il campo `is_json` indica se il messaggio è stato parsabile. I messaggi non JSON appaiono comunque nei log e nelle anteprime.
- **Ricerca topic vuota**: un parametro filtro `topic` vuoto corrisponde a tutti i topic (il pattern LIKE diventa `%%`).

## Permessi e Accesso

- **Ambiente locale**: tutti gli endpoint API sono accessibili senza autenticazione. Questo permette sviluppo e debug senza restrizioni.
- **Ambienti production/staging**: l'accesso richiede il superamento del gate di autorizzazione `viewMqttBroadcast`. Il gate riceve l'utente autenticato corrente.
- **Gate non definito**: se nessun gate è definito e l'ambiente non è locale, tutti gli accessi vengono negati (403 Forbidden).
- **Utenti non autenticati**: negli ambienti non locali, gli utenti non autenticati ricevono una risposta 403.
- **Nessun permesso per-endpoint**: tutti gli endpoint condividono lo stesso controllo di autorizzazione. Non c'è distinzione tra accesso in sola lettura e accesso in scrittura (es. le operazioni retry/delete usano lo stesso gate della visualizzazione).
