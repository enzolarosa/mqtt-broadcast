# Gestione della Memoria

## Cosa Fa

Il sistema di gestione della memoria impedisce ai processi supervisor MQTT di consumare troppa memoria del server. Monitora automaticamente l'utilizzo della memoria, avvisa gli operatori quando l'utilizzo è elevato e può riavviare i processi prima che crashino per esaurimento della memoria.

Questo è critico per i deployment in produzione dove i supervisor MQTT vengono eseguiti continuamente per giorni o settimane. Senza la gestione della memoria, piccole perdite di memoria causerebbero eventualmente il crash del processo, interrompendo tutte le connessioni MQTT.

## Percorso Utente

1. L'amministratore configura i limiti di memoria in `.env` (es. `MQTT_MEMORY_THRESHOLD_MB=128`)
2. Il supervisor MQTT si avvia e inizia a monitorare il proprio utilizzo di memoria
3. Durante il funzionamento normale, il sistema pulisce periodicamente la memoria inutilizzata (garbage collection)
4. Se l'utilizzo della memoria raggiunge l'80% del limite configurato, viene registrato un warning
5. Se l'utilizzo della memoria supera il 100% del limite, viene registrato un errore e inizia un conto alla rovescia
6. Dopo il periodo di grazia configurato (default: 10 secondi), il processo si riavvia automaticamente
7. Il process manager esterno (systemd, supervisord) avvia un nuovo processo
8. Tutte le connessioni MQTT vengono ristabilite automaticamente dal nuovo processo

## Regole di Business

- La memoria viene controllata periodicamente, non ad ogni iterazione del loop — l'intervallo di controllo è configurabile
- La soglia di warning è fissata all'80% del limite di memoria configurato
- La soglia critica è il 100% del limite di memoria configurato
- Un periodo di grazia permette alle operazioni in corso di completarsi prima del restart
- Se la memoria scende sotto il limite durante il periodo di grazia, il restart viene annullato
- L'auto-restart può essere disabilitato completamente — il sistema continuerà ad avvisare ma non riavvierà mai
- Il monitoraggio della memoria può essere disabilitato completamente impostando la soglia a null
- Solo il master supervisor può attivare i restart — i singoli broker supervisor registrano solo warning
- L'utilizzo di picco della memoria viene tracciato e incluso nelle statistiche della dashboard

## Casi Limite

- **Picco di memoria e recupero**: Se la memoria supera brevemente la soglia ma scende durante il periodo di grazia, il restart viene annullato e viene registrato un messaggio di recupero. Nessuna interruzione.
- **Auto-restart disabilitato**: Warning ed errori vengono registrati indefinitamente, ma il processo non si riavvia mai. L'amministratore deve intervenire manualmente.
- **Nessuna soglia configurata**: Tutto il monitoraggio della memoria è disabilitato. La garbage collection continua a funzionare per prevenire perdite di memoria, ma nessun warning o restart si verifica.
- **Periodo di grazia molto breve**: Impostare il periodo di grazia a 0 secondi causa un restart immediato al superamento della soglia. Questo potrebbe interrompere le pubblicazioni MQTT in corso.
- **Process manager non configurato**: Se nessun process manager esterno (systemd, supervisord) è in esecuzione, l'auto-restart termina il processo ma nulla lo riavvia. Le connessioni MQTT vengono perse fino all'intervento manuale.
- **Limiti di memoria del container**: Se il container uccide il processo per OOM prima che la soglia venga raggiunta, il memory manager non può intervenire. Impostare la soglia al di sotto del limite del container.

## Permessi e Accesso

- La configurazione della memoria è impostata dall'amministratore di sistema tramite variabili d'ambiente o il file di configurazione
- Le statistiche della memoria sono visibili nella dashboard di monitoraggio per qualsiasi utente con il permesso gate `viewMqttBroadcast`
- Il memory manager opera automaticamente — nessuna interazione utente è richiesta durante il funzionamento normale
- Le decisioni di restart vengono prese automaticamente dal sistema; non possono essere attivate manualmente tramite la dashboard
