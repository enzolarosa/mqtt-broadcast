# Dead Letter Queue (Job Falliti)

## Cosa Fa

Quando un messaggio MQTT non riesce ad essere consegnato al broker — dopo che tutti i tentativi automatici di retry sono stati esauriti — il sistema salva il messaggio fallito con tutto il suo contesto in una "Dead Letter Queue." Questo garantisce che nessun messaggio vada perso silenziosamente. Gli operatori possono visualizzare tutti i fallimenti, capire perche sono avvenuti, ritentarli singolarmente o in massa, e ripulirli quando il problema e risolto.

I job falliti sono visibili nella tab "Failed Jobs" della dashboard di monitoraggio con un badge numerico, e sono anche disponibili via API REST per accesso programmatico.

## Percorso Utente

1. Un job di pubblicazione messaggio fallisce (broker irraggiungibile, rate limit superato, o misconfiguration).
2. Il sistema cattura automaticamente il fallimento con il messaggio originale, broker di destinazione, topic e dettagli dell'errore.
3. L'operatore apre la dashboard di monitoraggio e vede un badge con il conteggio dei fallimenti nella sezione "Failed Jobs."
4. L'operatore consulta la lista dei job falliti, ciascuno mostrando:
   - Nome del broker e topic
   - Un'anteprima del payload del messaggio
   - Un'anteprima dell'errore (eccezione)
   - Quando e avvenuto il fallimento (tempo relativo, ad es. "5 minuti fa")
   - Quante volte e stato ritentato
5. L'operatore puo:
   - **Ritentare** un singolo job — ri-dispatcha il messaggio originale nella coda.
   - **Ritentare Tutti** — ri-dispatcha tutti i job falliti non ancora ritentati (o con cooldown scaduto) in una volta.
   - **Eliminare** un singolo job — lo rimuove dalla coda.
   - **Svuotare Tutti** — elimina permanentemente tutti i job falliti (con dialogo di conferma).
6. Se un job ritentato fallisce nuovamente, viene creato un nuovo record di fallimento mentre quello originale viene conservato con il conteggio retry aggiornato.

## Regole di Business

- I messaggi falliti vengono salvati con tutto il contesto originale: broker, topic, payload, livello QoS, flag retain e dettagli completi dell'eccezione.
- Ogni job fallito riceve un identificatore unico (UUID) usato per tutte le operazioni.
- **I retry sono sempre manuali** — il sistema non ritenta automaticamente i job falliti. Questo previene fallimenti a cascata quando la causa e sistemica (ad es. broker offline).
- **Cooldown sui retry** — "Ritenta Tutti" salta i job gia ritentati meno di 1 minuto fa per prevenire il doppio dispatch accidentale.
- I job ritentati passano attraverso l'intera pipeline di pubblicazione, incluso il rate limiting. Un retry non e garantito che vada a buon fine.
- **I record di retry vengono conservati** — ritentare un job non elimina il suo record di fallimento. I campi `retry_count` e `retried_at` vengono aggiornati.
- "Svuota Tutti" e un'operazione distruttiva irreversibile che elimina tutti i record permanentemente.
- La panoramica delle statistiche della dashboard include un conteggio totale dei job falliti e un conteggio dei job in attesa di retry (mai ritentati).

## Casi Limite

- **Database non disponibile durante la cattura del fallimento** — se il database e giu al momento del fallimento, il record non puo essere salvato. L'eccezione viene comunque registrata dal logging standard di Laravel.
- **Retry di un messaggio verso un broker ancora in errore** — il retry fallira nuovamente e generera un nuovo record di fallimento, incrementando il conteggio retry del job originale.
- **Click rapidi su "Ritenta Tutti"** — il cooldown di 1 minuto impedisce che lo stesso job venga dispatchato piu volte.
- **Payload di messaggi molto grandi** — la colonna `message` usa `longText`, quindi payload di dimensioni arbitrarie vengono salvati. Le risposte API mostrano un'anteprima troncata a 100 caratteri.
- **Stack trace di eccezioni molto lunghi** — la colonna `exception` usa `text`. Le risposte API nella lista mostrano solo la prima riga; il dettaglio completo e disponibile tramite l'endpoint di dettaglio.

## Permessi e Accesso

- La gestione dei job falliti e protetta dallo stesso middleware e gate di autorizzazione del resto della dashboard di monitoraggio.
- In ambienti locali, l'accesso e automaticamente consentito.
- In produzione, l'accesso richiede che il gate `viewMqttBroadcast` sia definito e passi per l'utente autenticato.
- Tutte le route API dei job falliti (lista, dettaglio, retry, elimina, svuota) richiedono lo stesso livello di autorizzazione — non c'e distinzione granulare tra operazioni di lettura e scrittura.
