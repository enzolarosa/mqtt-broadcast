# Rate Limiting

## Cosa Fa

Il rate limiting controlla quanti messaggi MQTT possono essere pubblicati entro una finestra temporale, proteggendo i broker dal sovraccarico causato da traffico eccessivo. Il sistema applica limiti configurabili al secondo e al minuto, con due strategie per gestire i messaggi in eccesso: rifiuto immediato (il messaggio fallisce) o throttling (il messaggio viene ritardato e ritentato automaticamente).

Il rate limiting si applica a tutti i messaggi pubblicati tramite la coda (pubblicazione asincrona). Pu&ograve; essere configurato globalmente o con limiti diversi per ogni connessione broker.

## Percorso Utente

1. Un messaggio viene inviato per la pubblicazione MQTT (es. tramite `mqttMessage()` o `MqttBroadcast::publish()`)
2. Il messaggio entra nella coda come job
3. Quando il queue worker prende in carico il job, il sistema controlla il rate limit per la connessione broker di destinazione
4. **Se entro i limiti**: il contatore viene incrementato e il messaggio viene pubblicato normalmente
5. **Se limite superato (strategia reject)**: il messaggio viene rifiutato immediatamente. Appare nella tab "Failed Jobs" nella dashboard con un errore di rate limit. Un amministratore pu&ograve; ritentarlo dalla dashboard.
6. **Se limite superato (strategia throttle)**: il messaggio viene rimesso nella coda con un delay. Verr&agrave; ritentato automaticamente una volta che la finestra di rate limit si resetta (entro pochi secondi). Nessun intervento manuale necessario.

## Regole di Business

- Il rate limiting &egrave; abilitato di default con un limite di 1000 messaggi al minuto per connessione
- Sono disponibili due finestre temporali: al secondo (protezione burst) e al minuto (throughput sostenuto). Entrambe possono essere configurate indipendentemente.
- Quando entrambe le finestre sono configurate, il limite pi&ugrave; restrittivo prevale — se il limite al secondo viene raggiunto, la pubblicazione si ferma anche se il limite al minuto ha ancora spazio
- Ogni connessione broker ha il proprio contatore di rate limit di default, quindi il traffico su un broker non influenza gli altri
- I rate limit possono essere impostati su un pool globale condiviso tra tutte le connessioni se desiderato
- Le singole connessioni possono sovrascrivere i limiti di default (es. una connessione ad alta priorit&agrave; pu&ograve; avere un limite pi&ugrave; alto)
- Impostare un limite a `null` disabilita quella finestra temporale completamente
- Quando il rate limiting &egrave; disabilitato globalmente, tutti i messaggi passano senza alcun controllo
- La strategia "reject" fa fallire i messaggi che finiscono nella Dead Letter Queue — devono essere ritentati manualmente dalla dashboard
- La strategia "throttle" ritarda i messaggi automaticamente — vengono ritentati dopo il reset della finestra di rate, senza perdita di dati

## Casi Limite

- **Entrambi i limiti impostati a null**: anche con il rate limiting abilitato, non avviene alcuna applicazione effettiva — tutti i messaggi passano
- **Cache driver non disponibile**: se il backend cache (Redis, Memcached) &egrave; inattivo, i controlli di rate limit falliranno e i messaggi potrebbero essere rifiutati per errori infrastrutturali, non per rate limit effettivi
- **Burst seguito da traffico sostenuto**: se per-second &egrave; 5 e per-minute &egrave; 100, un burst di 5 messaggi in un secondo &egrave; accettabile, ma un traffico sostenuto di 5/s raggiunger&agrave; il limite al minuto a 100 messaggi (dopo circa 20 secondi)
- **Tempistica di retry del job throttled**: quando un job viene throttled, il delay &egrave; calcolato dalla finestra corrente — potrebbe essere di appena 1 secondo (finestra per-second in scadenza) o fino a 60 secondi (finestra per-minute appena iniziata)
- **Multipli queue worker**: i contatori di rate limit sono condivisi tramite cache, quindi i limiti vengono applicati correttamente tra tutti i worker. Tuttavia, esiste una piccola finestra di race condition tra check e hit dove worker concorrenti potrebbero superare leggermente il limite

## Permessi e Accesso

- La configurazione del rate limiting &egrave; controllata tramite i file di configurazione dell'applicazione — non esistono impostazioni visibili nella dashboard
- La tab "Failed Jobs" della dashboard mostra i messaggi rifiutati dal rate limiting (quando si usa la strategia reject), e gli amministratori con il gate `viewMqttBroadcast` possono ritentarli o eliminarli
- Non c'&egrave; modo per gli utenti finali di sovrascrivere o bypassare i rate limit a runtime — i limiti sono definiti solo nella configurazione
