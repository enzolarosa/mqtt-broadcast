# Frontend Dashboard

## Cosa Fa

La Dashboard MQTT Broadcast e un'interfaccia di monitoraggio web che offre agli operatori visibilita in tempo reale sullo stato di tutte le connessioni ai broker MQTT, il flusso dei messaggi e la salute del sistema. Funziona interamente nel browser e si aggiorna automaticamente ogni 5 secondi senza richiedere il ricaricamento della pagina.

## Percorso Utente

1. L'utente naviga all'URL della dashboard (default: `/mqtt-broadcast`).
2. La pagina si carica e mostra immediatamente la scheda **Dashboard** con cinque card riepilogative: messaggi al minuto, broker attivi, uso memoria, profondita coda e conteggio job falliti.
3. Sotto le card, un **grafico di throughput** mostra il volume dei messaggi nell'ultima ora come grafico a linee.
4. Piu in basso, una **tabella broker** elenca ogni broker connesso con il suo stato (Connesso, Inattivo, Riconnessione, Disconnesso), uptime e conteggio messaggi nelle ultime 24 ore.
5. Se il logging dei messaggi e abilitato, un **feed messaggi** mostra i 20 messaggi piu recenti con nome broker, topic, anteprima messaggio e timestamp relativo ("2 minuti fa").
6. L'utente clicca la scheda **Failed Jobs** per vedere i job che non sono stati consegnati. Ogni voce mostra broker, topic, anteprima errore e conteggio retry.
7. Dalla vista Failed Jobs, l'utente puo:
   - **Riprovare** un singolo job (pulsante retry per riga).
   - **Eliminare** un singolo job.
   - **Riprovare Tutti** i job falliti in una volta.
   - **Svuotare Tutti** i job falliti (con dialog di conferma).
8. L'utente clicca la scheda **Docs** per un riferimento rapido: comandi Artisan comuni, suggerimenti per problemi di connessione, checklist di configurazione e link alla documentazione esterna.
9. L'utente puo alternare tra **modalita scura** e **modalita chiara** usando l'icona sole/luna nell'header. La preferenza viene ricordata tra le sessioni.

## Regole di Business

- La dashboard interroga l'API backend a un intervallo fisso (5 secondi per default). Non c'e pulsante di aggiornamento manuale — i dati si aggiornano automaticamente.
- La sezione **Log Messaggi** e visibile solo quando il logging dei messaggi e abilitato nella configurazione del pacchetto. Se disabilitato, la sezione e completamente nascosta.
- L'azione **Svuota Tutti** nei Failed Jobs richiede conferma esplicita dell'utente tramite un dialog del browser prima dell'esecuzione. Questo previene la perdita accidentale di dati.
- I retry individuali dei job mostrano uno spinner di caricamento per-job. Il pulsante retry e disabilitato durante il retry in corso per prevenire invii duplicati.
- Le azioni bulk (Riprova Tutti, Svuota Tutti) disabilitano entrambi i pulsanti durante l'elaborazione per prevenire operazioni bulk concorrenti.
- Il badge di stato del sistema nell'header mostra **Running** (verde) o **Stopped** (rosso) in base a se il processo supervisor e attivo.
- La card uso memoria cambia colore in base alle soglie: verde (normale), giallo (sopra 80%), rosso (sopra 100% della soglia configurata).
- La card coda in attesa diventa gialla quando i job in attesa superano 100.
- Il conteggio job falliti nella scheda di navigazione mostra un badge rosso con il conteggio quando ci sono fallimenti.

## Casi Limite

- **API irraggiungibile**: ogni sezione della dashboard mostra indipendentemente un messaggio "Impossibile caricare". I dati caricati precedentemente vengono preservati — la dashboard non si svuota.
- **Nessun broker in esecuzione**: la tabella broker mostra "Nessun broker attivo" con uno stato vuoto.
- **Nessun messaggio loggato**: il feed messaggi mostra "Nessun messaggio ancora".
- **Nessun job fallito**: la scheda Failed Jobs mostra uno stato vuoto con un'icona attenuata. I pulsanti azione bulk sono nascosti.
- **JavaScript disabilitato**: un fallback `<noscript>` mostra un messaggio che chiede all'utente di abilitare JavaScript.
- **Preferenza tema persa**: se il `localStorage` viene cancellato, il tema torna alla preferenza di sistema (scuro o chiaro in base all'impostazione del sistema operativo).

## Permessi e Accesso

- L'accesso alla dashboard e controllato dal middleware `Authorize` configurato in `config/mqtt-broadcast.php`. Per default, la dashboard e accessibile solo nell'ambiente `local`.
- Il Gate `viewMqttBroadcast` puo essere definito nell'`AuthServiceProvider` dell'applicazione per controllare l'accesso in ambienti non locali.
- Tutti gli endpoint API usati dal frontend sono protetti dallo stesso middleware di autorizzazione — non c'e accesso API non autenticato.
- La dashboard e in sola lettura eccetto la scheda Failed Jobs, che permette operazioni di retry e cancellazione.
