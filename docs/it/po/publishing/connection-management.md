# Gestione delle Connessioni

## Cosa Fa

La gestione delle connessioni garantisce che ogni connessione MQTT sia correttamente configurata e validata prima che qualsiasi messaggio venga inviato o ricevuto. Agisce come rete di sicurezza: se l'hostname di un broker, la porta, le credenziali di autenticazione o le impostazioni del protocollo sono errate, il sistema intercetta l'errore immediatamente anziché fallire silenziosamente a runtime.

Il sistema supporta multiple connessioni broker nominate, ciascuna con le proprie impostazioni, fornendo al contempo default ragionevoli che minimizzano lo sforzo di configurazione.

## Percorso Utente

1. Un amministratore configura una o più connessioni broker nel file di configurazione del pacchetto, fornendo come minimo un hostname e una porta.
2. Quando il sistema si avvia (sia il processo supervisor che un job di pubblicazione in coda), valida la configurazione della connessione.
3. Se la validazione ha successo, il sistema crea un client MQTT con le impostazioni corrette e si connette al broker.
4. Se la validazione fallisce, il sistema riporta immediatamente il problema esatto — quale connessione ha quale impostazione non valida — così l'amministratore può correggerlo.

## Regole di Business

- Ogni connessione deve avere un hostname valido (stringa non vuota) e una porta (tra 1 e 65535).
- Il Quality of Service deve essere 0 (al più una volta), 1 (almeno una volta) o 2 (esattamente una volta).
- Il timeout di connessione e l'intervallo keep-alive devono essere numeri positivi.
- L'autenticazione è opt-in: il flag `auth` deve essere impostato esplicitamente a `true`. Quando abilitata, sia username che password sono richiesti — il sistema non tenterà di connettersi con credenziali parziali.
- La crittografia TLS viene applicata solo quando l'autenticazione è abilitata. Le connessioni non autenticate (tipicamente sviluppo locale) saltano completamente la configurazione TLS.
- Le impostazioni specifiche della connessione sovrascrivono i default globali. Se una connessione non specifica un valore, viene usato il default globale.
- Impostare un valore di connessione a null significa "usa il default globale", non "disabilita questa impostazione".
- Il prefisso dei topic è automatico: se una connessione ha un prefisso configurato, questo viene anteposto a ogni topic usato da quella connessione — sia in pubblicazione che nel filtraggio dei messaggi in arrivo nei listener.
- La retention dei messaggi è configurabile per connessione: quando `retain` è abilitato, il broker conserva l'ultimo messaggio su ogni topic e lo consegna ai nuovi subscriber.
- I job publisher usano sempre una sessione pulita (nessuno stato persistente sul broker). I processi subscriber usano il valore di clean session configurato per supportare sottoscrizioni persistenti tra i restart.

## Casi Limite

- **Connessione mancante**: se il codice fa riferimento a un nome di connessione che non esiste nella configurazione, il sistema lancia un errore identificando la connessione mancante per nome.
- **Autenticazione parziale**: abilitare l'autenticazione senza fornire sia username che password viene intercettato al momento della validazione, non al momento della connessione.
- **Certificati auto-firmati**: permessi di default per comodità nello sviluppo. Possono essere disabilitati per connessione negli ambienti di produzione che richiedono la validazione dei certificati.
- **Collisione del client ID**: i job di pubblicazione usano un identificatore casuale univoco per ogni messaggio per evitare conflitti con il processo subscriber long-running. Il subscriber può usare sia un identificatore fisso (per sessioni persistenti) che uno auto-generato.
- **Modifiche alla configurazione senza restart**: poiché la configurazione viene letta al momento del dispatch del job (per i publisher) e all'avvio del supervisor (per i subscriber), le modifiche alla configurazione hanno effetto al prossimo job di pubblicazione o restart del supervisor — non immediatamente per le connessioni subscriber attive.
- **Prefisso topic vuoto**: quando nessun prefisso è configurato (default), i topic passano invariati. Il prefisso viene concatenato direttamente senza separatore — il prefisso stesso deve includere qualsiasi separatore desiderato (es. `home/` non `home`).
- **Cache di retain e QoS**: il job publisher memorizza i valori QoS e retain al momento del dispatch. Se la configurazione cambia tra dispatch ed esecuzione, vengono usati i valori memorizzati dal momento del dispatch. Questi valori vengono anche persistiti nella dead letter queue se il job fallisce, preservando l'intento originale di pubblicazione.

## Permessi e Accesso

La gestione delle connessioni è un'infrastruttura interna — non ha un'interfaccia utente dedicata e nessun controllo di accesso specifico. La configurazione viene gestita tramite il file di configurazione del pacchetto e le variabili d'ambiente. Qualsiasi codice che pubblica o si sottoscrive a MQTT usa implicitamente il livello di gestione delle connessioni.

Gli utenti della dashboard possono vedere lo stato di connessione di ogni broker (connesso, inattivo, in riconnessione, disconnesso) ma non possono modificare le impostazioni di connessione attraverso la dashboard.
