# Rate Limiting

## What It Does

Rate limiting controls how many MQTT messages can be published within a given time window, protecting brokers from being overwhelmed by excessive traffic. The system enforces configurable limits per second and per minute, with two strategies for handling excess messages: immediate rejection (the message fails) or throttling (the message is delayed and retried automatically).

Rate limiting applies to all messages published through the queue (async publishing). It can be configured globally or with different limits for each broker connection.

## User Journey

1. A message is dispatched for MQTT publishing (e.g., via `mqttMessage()` or `MqttBroadcast::publish()`)
2. The message enters the queue as a job
3. When the queue worker picks up the job, the system checks the rate limit for the target broker connection
4. **If within limits**: the counter is incremented and the message is published normally
5. **If limit exceeded (reject strategy)**: the message is rejected immediately. It appears in the "Failed Jobs" tab on the dashboard with a rate limit error. An administrator can retry it later from the dashboard.
6. **If limit exceeded (throttle strategy)**: the message is placed back in the queue with a delay. It will be retried automatically once the rate limit window resets (within seconds). No manual intervention needed.

## Business Rules

- Rate limiting is enabled by default with a limit of 1000 messages per minute per connection
- Two time windows are available: per-second (burst protection) and per-minute (sustained throughput). Both can be configured independently.
- When both windows are configured, the stricter limit applies — if the per-second limit is reached, publishing stops even if the per-minute limit still has room
- Each broker connection has its own rate limit counter by default, so traffic on one broker does not affect others
- Rate limits can be switched to a global shared pool across all connections if desired
- Individual connections can override the default limits (e.g., a high-priority connection can have a higher limit)
- Setting a limit to `null` disables that time window entirely
- When rate limiting is disabled globally, all messages pass through without any checks
- The "reject" strategy causes messages to fail and land in the Dead Letter Queue — they must be retried manually from the dashboard
- The "throttle" strategy delays messages automatically — they are retried after the rate window resets, with no data loss

## Edge Cases

- **Both limits set to null**: even with rate limiting enabled, no actual enforcement happens — all messages pass through
- **Cache driver unavailable**: if the cache backend (Redis, Memcached) is down, rate limit checks will fail and messages may be rejected due to infrastructure errors, not actual rate limits
- **Burst followed by sustained traffic**: if per-second is 5 and per-minute is 100, a burst of 5 messages in one second is fine, but sustained 5/s traffic will hit the minute limit at 100 messages (after ~20 seconds)
- **Throttled job retry timing**: when throttled, the delay is calculated from the current window — it could be as short as 1 second (per-second window about to reset) or up to 60 seconds (per-minute window just started)
- **Multiple queue workers**: rate limit counters are shared via cache, so limits are enforced correctly across all workers. However, there is a small race window between check and hit where concurrent workers might slightly exceed the limit

## Permissions & Access

- Rate limiting configuration is controlled via the application's config files — no user-facing settings exist in the dashboard
- The dashboard's "Failed Jobs" tab shows messages that were rejected by rate limiting (when using the reject strategy), and administrators with the `viewMqttBroadcast` gate can retry or delete them
- There is no way for end users to override or bypass rate limits at runtime — limits are defined in configuration only
