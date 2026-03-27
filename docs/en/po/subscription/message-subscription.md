# Message Subscription & Events

## What It Does

Message subscription allows the system to listen for incoming MQTT messages from connected brokers and automatically route them to the appropriate handlers. When a message arrives on any topic, the system dispatches it as an event that registered listeners can react to — logging messages, processing sensor data, triggering workflows, or any other business logic.

The system supports multiple brokers simultaneously, each with its own topic prefix and filtering rules. Listeners can target specific brokers and topics, or listen to everything.

## User Journey

1. The MQTT Broadcast supervisor process starts and connects to each configured broker.
2. Each broker connection subscribes to all topics under its configured prefix.
3. When a message arrives on any subscribed topic, the system creates an internal event.
4. All registered listeners receive the event through the queue system (asynchronous processing).
5. Each listener decides whether to process the message based on its broker and topic filters.
6. If the built-in logger is enabled, every message is also stored in the database for audit/debugging.

## Business Rules

- **Automatic subscription**: the system subscribes to all topics under the configured prefix using an MQTT wildcard — no manual topic registration required.
- **Broker isolation**: listeners only process messages from their designated broker. A listener configured for broker "local" ignores messages from broker "remote".
- **Topic filtering**: listeners can target a specific topic or use `*` to receive all messages on their broker.
- **JSON-only processing (default listeners)**: the built-in listener base class only processes messages with valid JSON object payloads. Non-JSON messages or JSON arrays/scalars are silently skipped.
- **Non-blocking processing**: all listener processing happens asynchronously via the queue. The MQTT connection loop is never blocked by slow handlers.
- **Optional logging**: message logging to the database is disabled by default and must be explicitly enabled. When enabled, both JSON and non-JSON messages are stored.
- **Error isolation**: if a listener fails, it does not affect other listeners or the MQTT connection. Failed listener jobs follow standard queue retry rules.

## Edge Cases

- **Invalid JSON payload**: if a message is not valid JSON, the default listener logs a warning and skips it. The built-in logger still stores the raw message.
- **Broker disconnection**: if the broker connection drops, the supervisor handles reconnection automatically (see [process supervision](../supervisor/process-supervision.md)). Messages sent during disconnection are lost (MQTT QoS 0) or redelivered by the broker (QoS 1/2).
- **Listener exception**: if a listener throws an exception during processing, the queue worker marks the job as failed. Other listeners for the same message are unaffected.
- **High message volume**: since listeners run on the queue, message throughput is limited by the number of queue workers, not the MQTT connection speed. Scale queue workers to handle higher volumes.
- **Duplicate messages**: with QoS 1 or 2, the MQTT broker may redeliver messages. The system does not deduplicate — listeners must handle idempotency if required.
- **Empty topic prefix**: if no prefix is configured, the system subscribes to `#` (all topics on the broker). This can produce high message volume on shared brokers.

## Permissions & Access

- Message subscription runs as part of the supervisor process — it requires no user interaction or authentication.
- Adding or modifying listeners requires code changes in the application's `MqttBroadcastServiceProvider`.
- The database logger writes to the `mqtt_loggers` table using the configured database connection.
- Queue workers must be running to process listener jobs. Without active workers, messages queue up but are not processed.
