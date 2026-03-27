# Message Publishing

## What It Does

Message publishing allows the application to send messages to MQTT brokers — the message bus that powers real-time communication between systems. Messages can be sent immediately (synchronous) or placed in a background queue (asynchronous) for processing. The system automatically manages connection handling, message formatting, and protects the broker from being overwhelmed through built-in rate limiting.

## User Journey

1. A feature in the application needs to notify an external system or device (e.g., send a command to an IoT device, broadcast a status update).
2. The application calls the publish method with a **topic** (the channel name) and a **message** (the data payload).
3. If publishing asynchronously (default), the message is placed on a background queue and processed by a worker. The application continues immediately without waiting.
4. If publishing synchronously, the application waits until the message is confirmed delivered to the broker before continuing.
5. The system automatically applies a **topic prefix** based on the broker configuration, ensuring messages land in the correct namespace.
6. If the message payload is a structured object (e.g., JSON data), it is automatically serialized before sending.
7. The message is delivered to the MQTT broker, which then distributes it to all connected subscribers on that topic.

## Business Rules

- **Broker configuration is mandatory**: a message cannot be published to a broker that is not configured with at least a host and port. The system rejects the request immediately.
- **Rate limiting is enforced at two levels**: once when the message enters the system, and again when it is about to be published. This prevents both queue flooding and broker overload.
- **Rate limiting has two behaviors**:
  - **Reject**: the message is refused outright and an error is raised. The calling code must handle the error.
  - **Throttle**: the message is delayed and retried automatically after a cooldown period. No manual intervention needed.
- **Rate limits can be set globally or per broker connection**, allowing different throughput for different brokers.
- **Asynchronous publishing is the default**: messages are queued and processed in the background, providing better application responsiveness.
- **Synchronous publishing blocks the caller**: the application waits for the message to be delivered. Use only when immediate confirmation is required.
- **Topic prefixes are applied automatically**: all messages on a given broker connection are prefixed per configuration, preventing topic collisions between environments or tenants.
- **Non-string messages are auto-converted to JSON**: arrays and objects are serialized transparently.
- **Queue configuration is separate from application queues**: publish jobs can be routed to a dedicated queue and connection to isolate MQTT traffic from other background jobs.
- **Failed messages are saved to a Dead Letter Queue**: when a message permanently fails (all retries exhausted or unrecoverable error), the system saves the message details — broker, topic, payload, error — to a dedicated failed jobs table. These can be retried or deleted via the dashboard.
- **Helper functions target a different default broker than the main API**: the quick-send helper functions (`mqttMessage`/`mqttMessageSync`) default to the "local" broker connection, while the primary API defaults to "default". Developers must be aware of this when switching between the two interfaces.
- **QoS and retain settings are captured at send time, not execution time**: when a message is queued asynchronously, the QoS level and retain flag are read from configuration at the moment of dispatch. If configuration changes between dispatch and execution, the original values are used.
- **Publishers always start with a clean MQTT session**: unlike subscribers which can maintain persistent sessions, every publish operation starts fresh. This means no stale state from previous connections can interfere.

## Edge Cases

- **Broker is unreachable**: if the MQTT broker is down or unreachable, asynchronous messages are retried by the queue worker according to the queue's retry policy. Synchronous messages fail immediately with an error.
- **Rate limit exceeded (reject mode)**: the publish request fails with a descriptive error containing the connection name, the limit hit, the time window, and when to retry.
- **Rate limit exceeded (throttle mode)**: the message is silently requeued with a delay. It will be published once the rate limit window resets.
- **Invalid message payload**: if a non-string payload cannot be JSON-encoded, the job fails.
- **Configuration error discovered at publish time**: if the broker config is missing or invalid (e.g., no host), the publish is refused immediately — the message is never queued.
- **Configuration error discovered at job execution time**: if deeper config validation fails (e.g., invalid QoS value, malformed host), the job fails permanently without retry, since configuration errors don't resolve on their own.
- **Multiple broker connections**: each connection has independent rate limits, topic prefixes, and QoS settings. A failure on one broker does not affect publishing to others.
- **Message fails permanently**: when a message cannot be delivered after all retry attempts, it is automatically saved to the Dead Letter Queue. The dashboard's Failed Jobs tab shows these entries with the original message, error details, and retry options.
- **No authentication configured**: if a broker connection has no username/password, the publisher connects without authentication. This works for brokers that allow anonymous connections but will fail silently if the broker requires auth.
- **Helper vs facade targeting different brokers**: if a developer uses `mqttMessage()` (defaults to "local" broker) in production where only a "default" broker is configured, the message will fail. The broker name must match an existing configuration entry.
- **QoS/retain config changes after dispatch**: for queued messages, the QoS and retain values are locked in when the message enters the queue. Changing configuration afterward does not affect messages already waiting in the queue.

## Permissions & Access

- Any code within the Laravel application can publish messages — there is no built-in role or permission check on publishing. Access control is at the application level.
- Rate limiting is enforced regardless of the caller.
- The dashboard (separate feature) provides visibility into published message logs when logging is enabled.
- Broker authentication (username/password) is configured at the connection level and applied automatically to all publish operations on that connection.
