# Infrastruttura di Testing

## Cosa Fa

Il pacchetto include una suite di test automatizzata completa che verifica il corretto funzionamento di tutte le funzionalita' — dalla pubblicazione dei messaggi alle risposte API della dashboard fino alla supervisione dei broker. I test funzionano in due modalita': **test unitari** che vengono eseguiti istantaneamente su qualsiasi macchina senza dipendenze esterne, e **test di integrazione** che verificano la comunicazione reale con il broker MQTT tramite Docker.

La suite di test funge da rete di sicurezza: qualsiasi modifica al codice viene validata contro 356 controlli automatici prima di poter essere integrata.

## Percorso Utente

1. Lo sviluppatore clona il repository
2. Esegue `composer install` per installare le dipendenze di test
3. Esegue `composer test` — tutti i 327 test unitari vengono eseguiti immediatamente (nessuna configurazione richiesta)
4. Per i test di integrazione, avvia i servizi Docker: `docker compose -f docker-compose.test.yml up -d`
5. Esegue nuovamente `composer test` — ora tutti i 356 test vengono eseguiti (unitari + integrazione)
6. I test di integrazione che richiedono un broker vengono saltati automaticamente se Docker non e' in esecuzione
7. Lo sviluppatore apporta modifiche al codice
8. Esegue `composer test` per verificare che nulla sia rotto
9. Esegue `composer pint` (stile codice) e `composer analyse` (analisi statica) prima del commit

## Regole di Business

- **I test unitari non richiedono mai un broker MQTT in esecuzione** — utilizzano un client mock che simula il comportamento del broker in memoria
- **I test di integrazione vengono saltati in modo trasparente** quando nessun broker e' disponibile, invece di fallire — questo permette alla CI di eseguire una suite parziale senza Docker
- **Tutti i test vengono eseguiti su un database SQLite in memoria** — nessuna configurazione di database persistente richiesta, ogni test parte da zero
- **La suite di test valida l'intero stack**: model, job, controller, middleware, supervisor, rate limiting e Dead Letter Queue
- **Stile codice e analisi statica sono controlli separati** — `composer pint` impone la formattazione, `composer analyse` rileva errori di tipo a livello PHPStan 7
- **I report di copertura sono disponibili** tramite `composer test-coverage`
- **Le factory per i dati di test sono incluse nel pacchetto** — factory predefinite per i processi broker e i log dei messaggi consentono scenari di test realistici, inclusi broker fermati, heartbeat obsoleti e vari formati di messaggio
- **Tutti i model esposti via API usano identificatori UUID** — gli scenari di test e le interazioni API referenziano i record tramite UUID, non per ID auto-incrementale del database, garantendo coerenza con il modo in cui la dashboard e i consumatori esterni accedono ai dati

## Casi Limite

- **Il broker va in down durante i test di integrazione**: i test gia' iniziati prima dell'interruzione possono fallire con errori di connessione; i test non ancora iniziati verranno saltati con un messaggio diagnostico
- **Limitazioni SQLite**: alcune funzionalita' del database (es. query su colonne JSON) possono comportarsi diversamente in SQLite rispetto a MySQL/PostgreSQL — i test di integrazione usano il driver di database reale quando disponibile
- **Rate limiting nei test**: utilizza il driver cache `array` quindi i contatori si resettano automaticamente tra un test e l'altro
- **Esecuzioni concorrenti dei test**: sicure perche' ogni esecuzione usa il proprio database in memoria e cache array — nessuno stato condiviso tra processi paralleli
- **CI senza Docker**: la variabile d'ambiente `MQTT_BROKER_AVAILABLE=false` puo' essere impostata per saltare i test di integrazione senza tentare una connessione

## Permessi e Accesso

- **Qualsiasi sviluppatore** puo' eseguire i test unitari — nessuna credenziale, Docker o servizio esterno richiesto
- **I test di integrazione** richiedono Docker (Mosquitto sulla porta 1883, Redis sulla porta 6379)
- **I sistemi CI** possono controllare l'ambito dei test tramite la variabile d'ambiente `MQTT_BROKER_AVAILABLE`
- **I report di copertura** richiedono l'estensione PHP Xdebug o PCOV installata
