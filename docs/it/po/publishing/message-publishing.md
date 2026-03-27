# Pubblicazione Messaggi

## Cosa Fa

La pubblicazione messaggi consente all'applicazione di inviare messaggi ai broker MQTT — il bus di messaggistica che alimenta la comunicazione in tempo reale tra sistemi. I messaggi possono essere inviati immediatamente (sincrono) o inseriti in una coda in background (asincrono) per l'elaborazione. Il sistema gestisce automaticamente le connessioni, la formattazione dei messaggi e protegge il broker dal sovraccarico tramite rate limiting integrato.

## Percorso Utente

1. Una funzionalita' dell'applicazione deve notificare un sistema esterno o un dispositivo (es. inviare un comando a un dispositivo IoT, trasmettere un aggiornamento di stato).
2. L'applicazione chiama il metodo di pubblicazione con un **topic** (il nome del canale) e un **messaggio** (il payload dei dati).
3. Se la pubblicazione e' asincrona (default), il messaggio viene messo in una coda in background ed elaborato da un worker. L'applicazione prosegue immediatamente senza attendere.
4. Se la pubblicazione e' sincrona, l'applicazione attende fino alla conferma di consegna al broker prima di proseguire.
5. Il sistema applica automaticamente un **prefisso topic** basato sulla configurazione del broker, assicurando che i messaggi arrivino nel namespace corretto.
6. Se il payload del messaggio e' un oggetto strutturato (es. dati JSON), viene serializzato automaticamente prima dell'invio.
7. Il messaggio viene consegnato al broker MQTT, che lo distribuisce a tutti i subscriber connessi su quel topic.

## Regole di Business

- **La configurazione del broker e' obbligatoria**: un messaggio non puo' essere pubblicato su un broker non configurato con almeno host e porta. Il sistema rifiuta immediatamente la richiesta.
- **Il rate limiting e' applicato a due livelli**: una volta quando il messaggio entra nel sistema, e di nuovo quando sta per essere pubblicato. Questo previene sia il sovraccarico della coda che del broker.
- **Il rate limiting ha due comportamenti**:
  - **Reject**: il messaggio viene rifiutato nettamente e viene generato un errore. Il codice chiamante deve gestire l'errore.
  - **Throttle**: il messaggio viene ritardato e ritentato automaticamente dopo un periodo di cooldown. Non e' necessario intervento manuale.
- **I limiti possono essere impostati globalmente o per singola connessione broker**, consentendo throughput diversi per broker diversi.
- **La pubblicazione asincrona e' il default**: i messaggi vengono accodati ed elaborati in background, offrendo migliore reattivita' all'applicazione.
- **La pubblicazione sincrona blocca il chiamante**: l'applicazione attende la consegna del messaggio. Da usare solo quando e' necessaria conferma immediata.
- **I prefissi topic vengono applicati automaticamente**: tutti i messaggi su una data connessione broker vengono prefissati secondo configurazione, prevenendo collisioni di topic tra ambienti o tenant.
- **I messaggi non-stringa vengono convertiti automaticamente in JSON**: array e oggetti vengono serializzati in modo trasparente.
- **La configurazione della coda e' separata dalle code dell'applicazione**: i job di pubblicazione possono essere instradati su una coda e connessione dedicate per isolare il traffico MQTT dagli altri job in background.
- **I messaggi falliti vengono salvati in una Dead Letter Queue**: quando un messaggio fallisce permanentemente (tutti i retry esauriti o errore irrecuperabile), il sistema salva i dettagli del messaggio — broker, topic, payload, errore — in una tabella dedicata ai job falliti. Questi possono essere ritentati o eliminati tramite la dashboard.
- **Le funzioni helper puntano a un broker default diverso rispetto all'API principale**: le funzioni helper per invio rapido (`mqttMessage`/`mqttMessageSync`) usano la connessione broker "local" come default, mentre l'API principale usa "default". Gli sviluppatori devono esserne consapevoli quando passano da un'interfaccia all'altra.
- **Le impostazioni QoS e retain vengono catturate al momento dell'invio, non dell'esecuzione**: quando un messaggio viene accodato in modo asincrono, il livello QoS e il flag retain vengono letti dalla configurazione al momento del dispatch. Se la configurazione cambia tra dispatch ed esecuzione, vengono usati i valori originali.
- **I publisher iniziano sempre con una sessione MQTT pulita**: a differenza dei subscriber che possono mantenere sessioni persistenti, ogni operazione di pubblicazione parte da zero. Questo significa che nessuno stato residuo da connessioni precedenti puo' interferire.

## Casi Limite

- **Broker non raggiungibile**: se il broker MQTT e' down o irraggiungibile, i messaggi asincroni vengono ritentati dal queue worker secondo la politica di retry della coda. I messaggi sincroni falliscono immediatamente con un errore.
- **Rate limit superato (modalita' reject)**: la richiesta di pubblicazione fallisce con un errore descrittivo contenente il nome della connessione, il limite raggiunto, la finestra temporale e quando riprovare.
- **Rate limit superato (modalita' throttle)**: il messaggio viene silenziosamente riaccodato con un ritardo. Verra' pubblicato una volta che la finestra del rate limit si resetta.
- **Payload messaggio non valido**: se un payload non-stringa non puo' essere codificato in JSON, il job fallisce.
- **Errore di configurazione scoperto al momento della pubblicazione**: se la configurazione del broker e' mancante o non valida (es. nessun host), la pubblicazione viene rifiutata immediatamente — il messaggio non viene mai accodato.
- **Errore di configurazione scoperto all'esecuzione del job**: se una validazione piu' approfondita della configurazione fallisce (es. valore QoS non valido, host malformato), il job fallisce permanentemente senza retry, poiche' gli errori di configurazione non si risolvono da soli.
- **Connessioni broker multiple**: ogni connessione ha rate limit, prefissi topic e impostazioni QoS indipendenti. Un errore su un broker non influenza la pubblicazione sugli altri.
- **Messaggio fallisce permanentemente**: quando un messaggio non puo' essere consegnato dopo tutti i tentativi di retry, viene automaticamente salvato nella Dead Letter Queue. La tab Failed Jobs della dashboard mostra queste voci con il messaggio originale, i dettagli dell'errore e le opzioni di retry.
- **Nessuna autenticazione configurata**: se una connessione broker non ha username/password, il publisher si connette senza autenticazione. Questo funziona per broker che permettono connessioni anonime ma fallira' silenziosamente se il broker richiede autenticazione.
- **Helper vs facade che puntano a broker diversi**: se uno sviluppatore usa `mqttMessage()` (default broker "local") in produzione dove e' configurato solo un broker "default", il messaggio fallira'. Il nome del broker deve corrispondere a una voce di configurazione esistente.
- **Modifiche config QoS/retain dopo il dispatch**: per i messaggi in coda, i valori QoS e retain vengono bloccati quando il messaggio entra nella coda. Modificare la configurazione successivamente non influenza i messaggi gia' in attesa nella coda.

## Permessi e Accesso

- Qualsiasi codice all'interno dell'applicazione Laravel puo' pubblicare messaggi — non esiste un controllo di ruolo o permesso integrato sulla pubblicazione. Il controllo degli accessi e' a livello applicazione.
- Il rate limiting viene applicato indipendentemente dal chiamante.
- La dashboard (funzionalita' separata) fornisce visibilita' sui log dei messaggi pubblicati quando il logging e' abilitato.
- L'autenticazione del broker (username/password) e' configurata a livello di connessione e applicata automaticamente a tutte le operazioni di pubblicazione su quella connessione.
