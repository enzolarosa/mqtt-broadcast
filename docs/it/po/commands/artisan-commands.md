# Comandi Artisan

## Cosa Fa

MQTT Broadcast fornisce un set di strumenti da riga di comando per gestire il sistema di messaggistica MQTT. Questi comandi coprono l'intero ciclo di vita: installazione del pacchetto, avvio del supervisor dei messaggi, arresto graceful e test di connettività al broker. Vengono eseguiti dal terminale e sono progettati per DevOps, amministratori di sistema o sviluppatori che gestiscono l'infrastruttura dell'applicazione.

## Percorso Utente

### Prima Installazione

1. L'amministratore esegue il comando di installazione: `php artisan mqtt-broadcast:install`.
2. Il sistema pubblica il file di configurazione, uno stub del service provider e gli asset della dashboard frontend nell'applicazione.
3. Il sistema registra automaticamente il service provider (supporta sia le strutture progetto Laravel 10 che 11+).
4. L'amministratore riceve una checklist dei passi successivi: configurare il broker, aggiornare i permessi di accesso, eseguire le migrazioni del database e avviare il supervisor.

### Avvio del Supervisor

1. L'amministratore esegue `php artisan mqtt-broadcast`.
2. Il sistema verifica che nessun altro supervisor sia già in esecuzione sulla macchina — se ne esiste uno, avvisa e rifiuta l'avvio (prevenendo istanze duplicate).
3. Il sistema rileva l'ambiente corrente (production, staging, local) e carica le connessioni broker configurate per quell'ambiente.
4. Tutte le configurazioni broker vengono validate prima che venga stabilita qualsiasi connessione. Se una configurazione non è valida, tutti gli errori vengono mostrati insieme e il supervisor non si avvia.
5. Il supervisor si avvia, mostra il numero di connessioni broker attive e inizia a monitorare i messaggi.
6. Il processo gira in foreground e può essere fermato con Ctrl+C per un arresto graceful.

### Arresto del Supervisor

1. L'amministratore esegue `php artisan mqtt-broadcast:terminate`.
2. Opzionalmente, si può specificare un nome broker per fermare solo quella connessione: `php artisan mqtt-broadcast:terminate nome-broker`.
3. Il sistema identifica tutti i processi in esecuzione sulla macchina corrente e invia loro un segnale di arresto graceful.
4. I record nel database e le voci nella cache vengono ripuliti automaticamente.
5. Se un processo si è già fermato, il sistema lo riconosce e lo riporta senza errore.

### Test di Connettività

1. L'amministratore esegue `php artisan mqtt-broadcast:test {broker} {topic} {messaggio}`.
2. Il sistema invia un singolo messaggio in modo sincrono (bypassando la coda) per verificare che il broker sia raggiungibile e configurato correttamente.
3. Il successo o il fallimento vengono mostrati immediatamente.

## Regole di Business

- **Un supervisor per macchina**: il sistema impedisce l'avvio di una seconda istanza di supervisor sulla stessa macchina. La prima istanza deve essere terminata prima di poterne avviare una nuova.
- **Avvio consapevole dell'ambiente**: il supervisor si connette solo ai broker configurati per l'ambiente corrente. Un server di produzione non si connetterà accidentalmente ai broker di sviluppo (e viceversa).
- **Validazione tutto-o-niente**: se una qualsiasi configurazione broker non è valida, l'intero supervisor rifiuta di avviarsi. Questo previene operazioni parziali dove alcuni broker funzionano ma altri falliscono silenziosamente.
- **L'arresto graceful preserva l'integrità dei messaggi**: la terminazione invia un segnale graceful, permettendo ai messaggi in transito di completare l'elaborazione prima che la connessione venga chiusa.
- **Pulizia best-effort**: il comando di terminazione ha sempre successo dal punto di vista dell'utente. Anche se un processo è già morto o irraggiungibile, il sistema ripulisce i suoi record nel database e nella cache.
- **L'installazione è idempotente**: eseguire il comando di installazione più volte è sicuro — forza la ripubblicazione degli asset e salta la registrazione del service provider se già presente.
- **Priorità ambiente**: l'ambiente viene determinato in ordine: flag CLI esplicito > impostazione nel file di configurazione > variabile d'ambiente dell'applicazione. Questo permette sovrascritture mirate senza modificare la configurazione.

## Casi Limite

- **Supervisor già in esecuzione**: tentare di avviare un secondo supervisor mostra un avviso e termina. Nessun danno causato.
- **Nessun broker configurato per l'ambiente**: il supervisor mostra un messaggio di errore che indica il file di config e termina.
- **Processo già morto durante la terminazione**: il sistema lo rileva (tramite codice errore ESRCH) e lo tratta come terminazione riuscita, ripulendo i record obsoleti.
- **Errore di permessi di sistema durante la terminazione**: se l'utente corrente non ha i permessi per inviare segnali a un processo, l'errore viene riportato ma il comando prosegue con la pulizia degli altri processi.
- **Installazione su Laravel 10 vs 11+**: l'installer rileva automaticamente la versione di Laravel e registra il service provider nella posizione corretta (`bootstrap/providers.php` per 11+, `config/app.php` per 10 e precedenti).
- **Comando test con broker irraggiungibile**: la pubblicazione sincrona fallisce immediatamente con un errore descrittivo, confermando il problema di connettività.

## Permessi e Accesso

- Tutti i comandi richiedono **accesso terminale/CLI** al server — non possono essere attivati dall'interfaccia web.
- Il comando `mqtt-broadcast:install` richiede **accesso in scrittura** alle directory config, providers e public dell'applicazione.
- Il comando `mqtt-broadcast:terminate` richiede **permessi di segnalazione processi** (stesso utente del supervisor in esecuzione, o root).
- Non ci sono restrizioni basate su ruoli o gate per i comandi Artisan — il controllo degli accessi è gestito a livello server/SSH.
