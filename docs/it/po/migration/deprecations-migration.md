# Deprecazioni e Guida alla Migrazione

## Cosa Fa

Il pacchetto MQTT Broadcast ha subito importanti cambiamenti architetturali nel corso delle versioni. Alcuni componenti più vecchi sono stati sostituiti da alternative più affidabili e meglio progettate. Questa guida spiega cosa è cambiato, perché e cosa devono fare gli sviluppatori durante l'aggiornamento.

## Percorso Utente

1. Lo sviluppatore esegue la propria applicazione dopo l'aggiornamento a una nuova versione del pacchetto
2. Se il codice utilizza ancora componenti deprecati, appaiono avvisi di deprecazione nei log dell'applicazione
3. Lo sviluppatore consulta questa guida per capire quali componenti sono stati sostituiti
4. Lo sviluppatore aggiorna il proprio codice per utilizzare i nuovi componenti
5. Gli avvisi di deprecazione smettono di apparire

## Regole di Business

- I componenti deprecati continuano a funzionare durante il periodo di deprecazione — nessuna rottura immediata all'aggiornamento
- Gli avvisi di deprecazione sono registrati a livello `E_USER_DEPRECATED`, rendendoli visibili negli strumenti di error tracking
- Ogni componente deprecato ha un sostituto chiaro con un percorso di migrazione documentato
- Le classi deprecate vengono rimosse nella versione major successiva all'avviso di deprecazione

## Cosa è Cambiato e Perché

### Gestione dei Broker (cambiata nella v2.5.0)

**Prima:** Una singola classe gestiva tutto — creazione delle connessioni broker, monitoraggio, gestione dei segnali e gestione dei processi. Questo rendeva impossibile testare le singole parti, non aveva gestione della memoria e crashava definitivamente se una connessione cadeva.

**Dopo:** Il sistema ora utilizza una gerarchia di supervisor (ispirata a Laravel Horizon). Un master supervisor crea singoli broker supervisor, ognuno con riconnessione automatica, limiti di memoria e circuit breaker. Questo significa che le connessioni crashate si ripristinano automaticamente e le perdite di memoria vengono rilevate e risolte.

### Validazione della Configurazione (cambiata nella v3.0)

**Prima:** Controlli base che verificavano solo se host e porta erano presenti nella configurazione. Valori non validi (come una porta `0` o un QoS di `5`) passavano la validazione silenziosamente.

**Dopo:** Un sistema di validazione completo che controlla range di valori, tipi e consistenza logica. Gli errori includono contesto specifico su cosa è andato storto e come correggerlo.

## Casi Limite

- **Codice che usa direttamente la vecchia classe `Brokers`:** Continuerà a funzionare durante la v2.x ma registrerà avvisi di deprecazione. Deve essere migrato prima di aggiornare alla v3.0
- **Codice che usa `BrokerValidator::validate()`:** Continuerà a funzionare durante la v3.x ma dipende da una classe di eccezione rimossa (`InvalidBrokerException`), rendendolo fragile. Dovrebbe essere migrato immediatamente
- **Codice custom che estende le classi deprecate:** Si romperà alla rimozione. Non sono disponibili punti di estensione; utilizzare direttamente i nuovi componenti
- **Integrazione con error tracking:** Gli avvisi di deprecazione appariranno in Sentry, Flare o strumenti simili. Questo è previsto e intenzionale — fornisce visibilità sul codice che necessita di aggiornamento

## Permessi e Accesso

- Non sono necessari permessi speciali per utilizzare i nuovi componenti
- La migrazione è un cambiamento a livello di codice — non servono migrazioni del database, modifiche alla configurazione o passaggi di deploy oltre all'aggiornamento del codice
- Tutti i nuovi componenti sono registrati nel service container automaticamente dal service provider
