# Dead Letter Queue (Failed Jobs)

## What It Does

When an MQTT message fails to be delivered to the broker — after all automatic retry attempts are exhausted — the system saves the failed message with its full context into a "Dead Letter Queue." This ensures no message is silently lost. Operators can view all failures, understand why they happened, retry them individually or in bulk, and clean them up when resolved.

Failed jobs are surfaced in the monitoring dashboard's "Failed Jobs" tab with a count badge, and are also available via the REST API for programmatic access.

## User Journey

1. A message publish job fails (broker unreachable, rate limit exceeded, or misconfiguration).
2. The system automatically captures the failure with the original message, target broker, topic, and error details.
3. The operator opens the monitoring dashboard and sees a failure count badge on the "Failed Jobs" section.
4. The operator reviews the list of failed jobs, each showing:
   - Broker name and topic
   - A preview of the message payload
   - A preview of the error (exception)
   - When the failure occurred (relative time, e.g., "5 minutes ago")
   - How many times it has been retried
5. The operator can:
   - **Retry** a single job — re-dispatches the original message to the queue.
   - **Retry All** — re-dispatches all unretried (or cooled-down) failed jobs at once.
   - **Delete** a single job — removes it from the queue.
   - **Flush All** — permanently deletes all failed jobs (with confirmation dialog).
6. If a retried job fails again, a new failure record is created while the original is preserved with its updated retry count.

## Business Rules

- Failed messages are stored with all original context: broker, topic, payload, QoS level, retain flag, and full exception details.
- Each failed job receives a unique identifier (UUID) used for all operations.
- **Retries are always manual** — the system does not automatically retry failed jobs. This prevents cascading failures when the root cause is systemic (e.g., broker outage).
- **Retry cooldown** — "Retry All" skips jobs that were already retried less than 1 minute ago to prevent accidental double-dispatch.
- Retried jobs go through the full publish pipeline, including rate limiting. A retry is not guaranteed to succeed.
- **Retry records are preserved** — retrying a job does not delete its failure record. The `retry_count` and `retried_at` fields are updated instead.
- "Flush All" is an irreversible destructive operation that deletes all records permanently.
- The dashboard stats overview includes a total count of failed jobs and a count of jobs pending retry (never retried).

## Edge Cases

- **Database unavailable when capturing failure** — if the database is down at the moment of failure, the failure record cannot be saved. The exception is still logged through Laravel's standard logging.
- **Retry of a message to a still-broken broker** — the retry will fail again and generate a new failure record, incrementing the original job's retry count.
- **Rapid "Retry All" clicks** — the 1-minute cooldown prevents the same job from being dispatched multiple times.
- **Very large message payloads** — the `message` column uses `longText`, so arbitrarily large payloads are stored. API responses show a truncated 100-character preview.
- **Long exception stack traces** — the `exception` column uses `text`. API list responses show only the first line; full detail is available via the show endpoint.

## Permissions & Access

- Failed job management is protected by the same dashboard middleware and authorization gate as the rest of the monitoring dashboard.
- In local environments, access is automatically allowed.
- In production, access requires the `viewMqttBroadcast` gate to be defined and pass for the authenticated user.
- All failed job API routes (list, show, retry, delete, flush) require the same authorization level — there is no granular permission distinction between read and write operations.
