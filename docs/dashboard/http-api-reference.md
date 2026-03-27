# HTTP API Reference

## Overview

The MQTT Broadcast dashboard exposes 15 JSON API endpoints under a configurable prefix (default `/mqtt-broadcast/api/`). These endpoints power the React SPA dashboard and can be consumed by external monitoring tools, scripts, or load balancer health checks.

All routes are registered by `MqttBroadcastServiceProvider::registerRoutes()` and protected by the `Authorize` middleware, which follows the Laravel Horizon authorization pattern: unrestricted in `local` environment, `viewMqttBroadcast` Gate check in all other environments.

## Architecture

The API layer follows a thin-controller pattern — each controller reads directly from repositories or Eloquent models with no dedicated request/resource classes. Response shapes are hand-crafted arrays, not API Resources.

**Key design decisions:**

- **No pagination** — all list endpoints use `limit` (max 100) instead of cursor/offset pagination, keeping the API simple for dashboard polling
- **Logging dependency** — message, topic, and metrics endpoints return empty/disabled responses when `mqtt-broadcast.logs.enable` is `false`, rather than erroring
- **Connection status is computed** — `BrokerController::determineConnectionStatus()` derives status from heartbeat age, not a stored field
- **Failed jobs use `external_id`** — UUID-based identification via the `HasExternalId` trait, not auto-increment `id`

## How It Works

All API requests follow this lifecycle:

1. Request hits the configurable route prefix (e.g. `GET /mqtt-broadcast/api/health`)
2. `web` middleware stack executes (session, CSRF for non-GET, etc.)
3. `Authorize` middleware checks access:
   - `local` env → pass through
   - Other envs → `Gate::allows('viewMqttBroadcast', [$request->user()])` → 403 if denied
4. Controller method executes, querying repositories/models
5. JSON response returned with appropriate HTTP status code

## Key Components

| File | Class / Method | Responsibility |
|------|---------------|----------------|
| `routes/web.php` | Route definitions | Registers all 15 API routes + SPA catch-all under `api/` prefix; `retry-all` before `{id}/retry` to avoid wildcard capture |
| `src/Http/Middleware/Authorize.php` | `Authorize::handle()` | Gate-based auth with local environment bypass; returns plain text 403, not JSON |
| `src/Http/Controllers/HealthController.php` | `HealthController::check()` | System health check (200/503); reads `$data['memory']` (key mismatch with repository's `memory_mb`) |
| `src/Http/Controllers/HealthController.php` | `getMasterSupervisorData()` | Extracts master data; handles array/object; converts bytes→MB (but input is already MB or 0) |
| `src/Http/Controllers/HealthController.php` | `checkMemoryStatus()` | 3-tier threshold check; always returns `pass` due to memory key mismatch yielding 0 |
| `src/Http/Controllers/DashboardStatsController.php` | `DashboardStatsController::index()` | Aggregated stats; `Queue::size()` in try/catch; `calculateUptime()` sign bug |
| `src/Http/Controllers/DashboardStatsController.php` | `calculateMemoryUsagePercent()` | Divides `$data['memory']` by threshold — always 0 due to key mismatch |
| `src/Http/Controllers/DashboardStatsController.php` | `calculateUptime()` | `now()->diffInSeconds($startedAt, false)` — negative result (operands reversed vs HealthController) |
| `src/Http/Controllers/BrokerController.php` | `BrokerController::index()` | Maps all brokers with 2N+1 queries when logging enabled |
| `src/Http/Controllers/BrokerController.php` | `BrokerController::show()` | Loads `all()` then `firstWhere('id')` — no `findById()` on repository |
| `src/Http/Controllers/BrokerController.php` | `determineConnectionStatus()` | 4-tier state machine; `null` heartbeat → `PHP_INT_MAX` → `disconnected` |
| `src/Http/Controllers/BrokerController.php` | `formatMessage()` | Byte-level `strlen/substr` truncation at 100 — multibyte UTF-8 may split mid-character |
| `src/Http/Controllers/MessageLogController.php` | `MessageLogController::index()` | 4 `json_decode` per message; LIKE wildcards `%`/`_` not escaped in topic filter |
| `src/Http/Controllers/MessageLogController.php` | `MessageLogController::show()` | 3 `json_decode` per message; returns full untruncated message |
| `src/Http/Controllers/MessageLogController.php` | `MessageLogController::topics()` | Top 20 topics in last 24h; `selectRaw` with `COUNT(*)` |
| `src/Http/Controllers/MetricsController.php` | `getThroughputByMinute()` | Inclusive loop produces 61 points not 60; `DATE_FORMAT` MySQL-specific |
| `src/Http/Controllers/MetricsController.php` | `getThroughputByHour()` / `getThroughputByDay()` | 25 / 8 points respectively (off-by-one); same gap-fill pattern |
| `src/Http/Controllers/MetricsController.php` | `MetricsController::summary()` | Rate dilution — divides by constant (60/24/7) not actual elapsed time |
| `src/Http/Controllers/FailedJobController.php` | `FailedJobController::retry()` | Drops `retain` flag; 4 queries per retry (SELECT + dispatch + increment + update + fresh) |
| `src/Http/Controllers/FailedJobController.php` | `FailedJobController::retryAll()` | No chunking; 1 + 3N queries; memory scales with backlog size |
| `src/Http/Controllers/FailedJobController.php` | `FailedJobController::destroy()` | Returns `[]` body despite 204 status |
| `src/Http/Controllers/FailedJobController.php` | `FailedJobController::flush()` | Uses `truncate()` — resets auto-increment, bypasses model events |
| `src/Http/Controllers/FailedJobController.php` | `formatJob()` | Byte-level `strlen/substr` truncation; `json_encode` on array message |
| `src/Http/Controllers/FailedJobController.php` | `index()` | Unfiltered `FailedMqttJob::count()` on every request for `meta.total` |

## Authorization

### `Authorize` Middleware

```
src/Http/Middleware/Authorize.php
```

| Environment | Behavior |
|-------------|----------|
| `local` | Always allowed — no authentication required |
| All others | Checks `Gate::allows('viewMqttBroadcast', [$request->user()])` — returns 403 plain text `Forbidden` on denial |

Gate definition (in your `AppServiceProvider` or published `MqttBroadcastServiceProvider`):

```php
Gate::define('viewMqttBroadcast', function ($user) {
    return in_array($user->email, ['admin@example.com']);
});
```

## Endpoint Reference

### Health Check

#### `GET /api/health`

**Route name:** `mqtt-broadcast.health`
**Controller:** `HealthController::check()`

Returns system health status for monitoring tools and load balancers. Returns HTTP 200 when healthy, 503 when unhealthy.

**Health criteria:** at least one active broker AND master supervisor found in cache.

**Response (200):**

```json
{
  "status": "healthy",
  "timestamp": "2026-03-27T10:00:00+00:00",
  "data": {
    "brokers": {
      "total": 3,
      "active": 2,
      "stale": 1
    },
    "master_supervisor": {
      "pid": 12345,
      "uptime_seconds": 3600,
      "memory_mb": 45.23,
      "supervisors_count": 2
    },
    "queues": {
      "pending": 5
    }
  },
  "checks": {
    "brokers_active": {
      "status": "pass",
      "message": "2 active broker(s)"
    },
    "master_running": {
      "status": "pass",
      "message": "Master supervisor running"
    },
    "memory_ok": {
      "status": "pass",
      "message": "Memory usage at 35.3% of threshold"
    }
  }
}
```

**Memory check status values:**

| Status | Condition |
|--------|-----------|
| `pass` | Usage < 80% of `memory.threshold_mb` |
| `warn` | Usage >= 80% and < 100% |
| `critical` | Usage >= 100% |
| `unknown` | Master supervisor not running |

---

### Dashboard Stats

#### `GET /api/stats`

**Route name:** `mqtt-broadcast.stats`
**Controller:** `DashboardStatsController::index()`

Returns aggregated statistics for the dashboard overview cards. Message counts are only populated when `logs.enable` is `true`.

**Response (200):**

```json
{
  "data": {
    "status": "running",
    "brokers": {
      "total": 3,
      "active": 2,
      "stale": 1
    },
    "messages": {
      "per_minute": 12.5,
      "last_hour": 750,
      "last_24h": 18000,
      "logging_enabled": true
    },
    "queue": {
      "pending": 5,
      "name": "mqtt-broadcast"
    },
    "memory": {
      "current_mb": 45.23,
      "threshold_mb": 128,
      "usage_percent": 35.3
    },
    "failed_jobs": {
      "total": 12,
      "pending_retry": 8
    },
    "uptime_seconds": 3600
  }
}
```

**Notes:**
- `status` is `"running"` if any active broker exists, `"stopped"` otherwise
- `messages.*` are all `0` when `logs.enable` is `false`
- `per_minute` is calculated as `last_hour / 60`
- `failed_jobs.pending_retry` counts jobs where `retried_at IS NULL`

---

### Brokers

#### `GET /api/brokers`

**Route name:** `mqtt-broadcast.brokers.index`
**Controller:** `BrokerController::index()`

Returns all registered brokers with computed status fields.

**Response (200):**

```json
{
  "data": [
    {
      "id": 1,
      "name": "broker-hostname-abc123",
      "connection": "local",
      "pid": 12345,
      "status": "active",
      "connection_status": "connected",
      "working": true,
      "started_at": "2026-03-27T09:00:00+00:00",
      "last_heartbeat_at": "2026-03-27T10:00:00+00:00",
      "last_message_at": "2026-03-27T09:59:30+00:00",
      "uptime_seconds": 3600,
      "uptime_human": "1h 0m",
      "messages_24h": 450
    }
  ]
}
```

**`connection_status` state machine:**

| Status | Heartbeat age | `working` flag | Meaning |
|--------|--------------|----------------|---------|
| `connected` | < 30s | `true` | Active and processing messages |
| `idle` | < 30s | `false` | Active but paused |
| `reconnecting` | 30s – 120s | any | Heartbeat is stale, possibly reconnecting |
| `disconnected` | > 120s | any | No recent heartbeat |

**`status` field:** `active` if heartbeat within last 2 minutes, `stale` otherwise.

**`uptime_human` format:** `"45s"`, `"12m"`, `"3h 15m"`

---

#### `GET /api/brokers/{id}`

**Route name:** `mqtt-broadcast.brokers.show`
**Controller:** `BrokerController::show()`
**Path parameter:** `id` — broker auto-increment ID (integer)

Returns broker detail with last 10 messages (if logging enabled).

**Response (200):**

```json
{
  "data": {
    "id": 1,
    "name": "broker-hostname-abc123",
    "connection": "local",
    "pid": 12345,
    "status": "active",
    "working": true,
    "started_at": "2026-03-27T09:00:00+00:00",
    "last_heartbeat_at": "2026-03-27T10:00:00+00:00",
    "uptime_seconds": 3600,
    "uptime_human": "1h 0m",
    "recent_messages": [
      {
        "id": 100,
        "topic": "sensors/temperature",
        "message": "{\"value\":22.5,\"unit\":\"celsius\"}",
        "created_at": "2026-03-27T09:59:30+00:00"
      }
    ]
  }
}
```

**Error (404):**

```json
{ "error": "Broker not found" }
```

**Notes:**
- Messages are truncated to 100 characters in `recent_messages`
- `recent_messages` is empty array `[]` when logging is disabled

---

### Message Logs

#### `GET /api/messages`

**Route name:** `mqtt-broadcast.messages.index`
**Controller:** `MessageLogController::index()`

Returns recent messages with optional filtering. Requires `logs.enable: true`.

**Query parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `broker` | string | — | Exact match on broker connection name |
| `topic` | string | — | Partial match (`LIKE %topic%`) |
| `limit` | int | 30 | Max results (capped at 100) |

**Response (200) — logging enabled:**

```json
{
  "data": [
    {
      "id": 100,
      "broker": "local",
      "topic": "sensors/temperature",
      "message": "{\n  \"value\": 22.5\n}",
      "message_preview": "{\"value\":22.5,\"unit\":\"celsius\"}",
      "created_at": "2026-03-27T09:59:30+00:00",
      "created_at_human": "1 minute ago"
    }
  ],
  "meta": {
    "logging_enabled": true,
    "count": 30,
    "limit": 30,
    "filters": {
      "broker": null,
      "topic": null
    }
  }
}
```

**Response (200) — logging disabled:**

```json
{
  "data": [],
  "meta": {
    "logging_enabled": false,
    "message": "Message logging is disabled. Enable it in config/mqtt-broadcast.php"
  }
}
```

**Notes:**
- `message` is pretty-printed JSON (if valid JSON), raw string otherwise
- `message_preview` is compact JSON truncated to 100 characters

---

#### `GET /api/messages/{id}`

**Route name:** `mqtt-broadcast.messages.show`
**Controller:** `MessageLogController::show()`
**Path parameter:** `id` — message log auto-increment ID (integer)

Returns full message detail with parsed content.

**Response (200):**

```json
{
  "data": {
    "id": 100,
    "broker": "local",
    "topic": "sensors/temperature",
    "message": "{\"value\":22.5,\"unit\":\"celsius\"}",
    "is_json": true,
    "message_parsed": {
      "value": 22.5,
      "unit": "celsius"
    },
    "created_at": "2026-03-27T09:59:30+00:00",
    "created_at_human": "1 minute ago"
  }
}
```

**Error (404):**
- Logging disabled: `{ "error": "Message logging is disabled" }`
- Not found: `{ "error": "Message not found" }`

**Notes:**
- `message` is the full untruncated original string
- `is_json` indicates whether the message was valid JSON
- `message_parsed` is the decoded JSON object/array, or the raw string if not JSON

---

#### `GET /api/topics`

**Route name:** `mqtt-broadcast.topics`
**Controller:** `MessageLogController::topics()`

Returns top 20 topics from the last 24 hours by message count.

**Response (200):**

```json
{
  "data": [
    { "topic": "sensors/temperature", "count": 450 },
    { "topic": "sensors/humidity", "count": 320 },
    { "topic": "devices/status", "count": 100 }
  ]
}
```

**Notes:**
- Returns empty `data: []` when logging is disabled
- Limited to top 20 topics
- Only counts messages from the last 24 hours

---

### Metrics

#### `GET /api/metrics/throughput`

**Route name:** `mqtt-broadcast.metrics.throughput`
**Controller:** `MetricsController::throughput()`

Returns time-series message counts for charting. Gaps are filled with zero-count entries.

**Query parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `period` | string | `hour` | One of: `hour` (per-minute, 60 points), `day` (per-hour, 24 points), `week` (per-day, 7 points) |

**Response (200):**

```json
{
  "data": [
    { "time": "09:00", "timestamp": "2026-03-27T09:00:00+00:00", "count": 12 },
    { "time": "09:01", "timestamp": "2026-03-27T09:01:00+00:00", "count": 0 },
    { "time": "09:02", "timestamp": "2026-03-27T09:02:00+00:00", "count": 8 }
  ],
  "meta": {
    "logging_enabled": true,
    "period": "hour",
    "data_points": 61
  }
}
```

**`time` format by period:**

| Period | Format | Example |
|--------|--------|---------|
| `hour` | `H:i` | `"14:30"` |
| `day` | `H:00` | `"14:00"` |
| `week` | `M d` | `"Mar 27"` |

**Response (200) — logging disabled:**

```json
{
  "data": [],
  "meta": { "logging_enabled": false, "period": "hour" }
}
```

**Notes:**
- Uses `DATE_FORMAT()` SQL function (MySQL-specific)
- Gap-filling iterates from period start to current time, inserting `count: 0` for missing buckets

---

#### `GET /api/metrics/summary`

**Route name:** `mqtt-broadcast.metrics.summary`
**Controller:** `MetricsController::summary()`

Returns aggregated performance statistics across three time windows.

**Response (200):**

```json
{
  "data": {
    "last_hour": {
      "total": 750,
      "per_minute": 12.5
    },
    "last_24h": {
      "total": 18000,
      "per_hour": 750.0
    },
    "last_7days": {
      "total": 126000,
      "per_day": 18000.0
    },
    "peak_minute": {
      "time": "2026-03-27 09:45:00",
      "count": 42
    }
  }
}
```

**Response (200) — logging disabled:**

```json
{ "data": null }
```

**Notes:**
- `per_minute` = `total / 60`, `per_hour` = `total / 24`, `per_day` = `total / 7`
- `peak_minute` finds the minute with the highest count in the last hour
- `peak_minute.time` is in `Y-m-d H:i:00` format, `null` if no data

---

### Failed Jobs (DLQ)

#### `GET /api/failed-jobs`

**Route name:** `mqtt-broadcast.failed-jobs.index`
**Controller:** `FailedJobController::index()`

Returns failed MQTT jobs ordered by most recent failure.

**Query parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `broker` | string | — | Exact match on broker name |
| `topic` | string | — | Partial match (`LIKE %topic%`) |
| `limit` | int | 30 | Max results (capped at 100) |

**Response (200):**

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "broker": "local",
      "topic": "sensors/temperature",
      "message_preview": "{\"value\":22.5,\"unit\":\"celsius\"}",
      "exception_preview": "enzolarosa\\MqttBroadcast\\Exceptions\\RateLimitExceededException: Rate limit exceeded",
      "qos": 1,
      "retain": false,
      "failed_at": "2026-03-27T09:30:00+00:00",
      "failed_at_human": "30 minutes ago",
      "retried_at": null,
      "retry_count": 0
    }
  ],
  "meta": {
    "count": 1,
    "total": 12,
    "limit": 30,
    "filters": {
      "broker": null,
      "topic": null
    }
  }
}
```

**Notes:**
- `id` is `external_id` (UUID), not the auto-increment primary key
- `message_preview` truncated to 100 characters
- `exception_preview` is the first line of the exception string
- `meta.total` is the global count (unfiltered)

---

#### `GET /api/failed-jobs/{id}`

**Route name:** `mqtt-broadcast.failed-jobs.show`
**Controller:** `FailedJobController::show()`
**Path parameter:** `id` — `external_id` UUID string

Returns full failed job detail including complete exception and message.

**Response (200):**

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "broker": "local",
    "topic": "sensors/temperature",
    "message_preview": "{\"value\":22.5}",
    "exception_preview": "RateLimitExceededException: Rate limit exceeded",
    "qos": 1,
    "retain": false,
    "failed_at": "2026-03-27T09:30:00+00:00",
    "failed_at_human": "30 minutes ago",
    "retried_at": null,
    "retry_count": 0,
    "exception": "enzolarosa\\MqttBroadcast\\Exceptions\\RateLimitExceededException: Rate limit exceeded\n#0 ...",
    "message": {"value": 22.5, "unit": "celsius"}
  }
}
```

**Error:** 404 via `firstOrFail()` (standard Laravel `ModelNotFoundException` → JSON 404).

---

#### `POST /api/failed-jobs/{id}/retry`

**Route name:** `mqtt-broadcast.failed-jobs.retry`
**Controller:** `FailedJobController::retry()`
**Path parameter:** `id` — `external_id` UUID string

Dispatches a new `MqttMessageJob` with the original payload, increments `retry_count`, sets `retried_at` to now.

**Response (200):**

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "broker": "local",
    "topic": "sensors/temperature",
    "message_preview": "{\"value\":22.5}",
    "exception_preview": "...",
    "qos": 1,
    "retain": false,
    "failed_at": "2026-03-27T09:30:00+00:00",
    "failed_at_human": "30 minutes ago",
    "retried_at": "2026-03-27T10:00:00+00:00",
    "retry_count": 1
  }
}
```

---

#### `POST /api/failed-jobs/retry-all`

**Route name:** `mqtt-broadcast.failed-jobs.retry-all`
**Controller:** `FailedJobController::retryAll()`

Retries all eligible failed jobs. A job is eligible if:
- `retried_at IS NULL` (never retried), OR
- `retried_at < now() - 1 minute` (cooldown expired)

This 1-minute cooldown prevents rapid repeated retries.

**Response (200):**

```json
{
  "data": { "retried": 8 }
}
```

---

#### `DELETE /api/failed-jobs/{id}`

**Route name:** `mqtt-broadcast.failed-jobs.destroy`
**Controller:** `FailedJobController::destroy()`
**Path parameter:** `id` — `external_id` UUID string

Deletes a single failed job.

**Response:** HTTP 204 (no body).

**Error:** 404 via `firstOrFail()`.

---

#### `DELETE /api/failed-jobs`

**Route name:** `mqtt-broadcast.failed-jobs.flush`
**Controller:** `FailedJobController::flush()`

Deletes ALL failed jobs by truncating the table.

**Response (200):**

```json
{
  "data": { "flushed": 12 }
}
```

---

### Dashboard SPA

#### `GET /`

**Route name:** `mqtt-broadcast.dashboard`

Serves the Blade view `mqtt-broadcast::dashboard` which bootstraps the React SPA. This is a catch-all for the dashboard UI — it does not return JSON.

## Internal Behaviors and Edge Cases

### Memory Key Mismatch (HealthController / DashboardStatsController)

Both `HealthController::getMasterSupervisorData()` and `DashboardStatsController::index()` read `$data['memory']` from the master supervisor cache record and treat the value as **bytes** (dividing by `1024 * 1024` to produce MB). However, `MasterSupervisorRepository::persist()` stores the field as `memory_mb` (already in megabytes via `MemoryManager`). Since the controllers read `$data['memory']` (not `memory_mb`), the null coalesce `?? 0` fires and memory is always reported as `0`.

The same mismatch affects `supervisors_count` — the repository stores `supervisors` but `getMasterSupervisorData()` reads `$data['supervisors_count']`.

### DashboardStatsController::calculateUptime() Sign Bug

```php
return (int) now()->diffInSeconds(\Carbon\Carbon::parse($startedAt), false);
```

With `absolute = false`, Carbon's `diffInSeconds($other)` returns `$other - $this`. Since `$startedAt` is in the past, `$startedAt - now()` produces a **negative** value. Compare with `HealthController::getMasterSupervisorData()` which correctly reverses the operands:

```php
'uptime_seconds' => Carbon::parse($startedAt)->diffInSeconds(now(), false),
```

The dashboard stats endpoint returns a negative `uptime_seconds`.

### Queue::size() Error Handling Asymmetry

`DashboardStatsController::index()` wraps `Queue::size()` in a `try/catch (Throwable)` that returns 0 on failure — correctly handling queue drivers like `sync` that don't support `size()`. `HealthController::check()` calls `Queue::size()` without any try/catch, so the health endpoint throws an unhandled exception if the queue driver doesn't support size checks.

### destroy() Returns `[]`, Not Empty Body

```php
return response()->json(status: 204);
```

The named parameter `status: 204` sets the HTTP status, but the first positional parameter `$data` defaults to `[]`. The response body is `[]` (a JSON empty array), not empty. A true 204 No Content should have no body — this is a `204` with content.

### JSON Decode Redundancy in MessageLogController

For each message in the `index()` response, two helper methods are called:

1. `formatMessage($message)` — calls `isJson()` (1st `json_decode`), then `json_decode` again for pretty-print (2nd)
2. `getMessagePreview($message)` — calls `isJson()` (3rd `json_decode`), then `json_decode` again for compact encoding (4th)

**4 `json_decode` calls per message in list responses.** For `show()`, `isJson()` is called once, then `parseMessage()` calls `isJson()` again + a third `json_decode` — 3 total.

### Throughput Data Point Count: Off-by-One

The doc states "60 data points" for the `hour` period. The actual code uses an inclusive `while ($current <= $end)` loop from `now()->subHour()->startOfMinute()` to `now()->startOfMinute()`. This produces **61 data points** (minute 0 through minute 60 inclusive). Same pattern for `day` (25 points, not 24) and `week` (8 points, not 7). The `meta.data_points` field correctly reflects the actual count.

### LIKE Wildcard Characters Not Escaped in Filters

Both `MessageLogController::index()` and `FailedJobController::index()` build topic filters with:

```php
$query->where('topic', 'like', "%{$topic}%");
```

The query is parameterized (no SQL injection), but `%` and `_` characters in user input are not escaped. Searching for `%` matches all topics; searching for `_` matches any single character. This affects both the `broker` and `topic` filter parameters for the `topic` LIKE clause. The `broker` filter uses exact `where('broker', $broker)` and is not affected.

### retry() Drops the `retain` Parameter

```php
MqttMessageJob::dispatch(
    topic: $job->topic,
    message: $job->message,
    broker: $job->broker,
    qos: $job->qos,
    // retain: $job->retain  ← NOT PASSED
);
```

The original `retain` flag from the failed job is not forwarded to the retried dispatch. `MqttMessageJob` defaults `retain` to `false`, so retained messages lose their retain flag on retry. Same issue in `retryAll()`.

### Metrics Rate Dilution

`MetricsController::summary()` and `DashboardStatsController::index()` compute per-minute/per-hour rates by dividing by constant denominators (60, 24, 7) regardless of how long the system has been running:

```php
'per_minute' => round($lastHour / 60, 2),
```

If the system started 5 minutes ago and produced 100 messages, `per_minute` = 1.67 instead of the actual 20. The rate is always diluted to the full window size.

### BrokerController::show() Loads All Brokers

```php
$broker = $brokerRepository->all()->firstWhere('id', $id);
```

The `show()` method loads every broker from the database via `all()`, then filters in memory with `firstWhere()`. There is no `findById()` method on `BrokerRepository`. For deployments with many brokers, this loads the entire table for a single-record lookup.

### BrokerController::index() O(n) Message Queries

When logging is enabled, `index()` executes 2 queries per broker:
1. `MqttLogger::where('broker', ...)->where('created_at', ...)->count()` — 24h message count
2. `MqttLogger::where('broker', ...)->orderBy('created_at', 'desc')->first()` — last message timestamp

For N brokers, this produces 1 + 2N queries (1 for `all()`, 2 per broker). No eager loading or batch query.

### Route Ordering: retry-all Before {id}

In `routes/web.php`, `POST /failed-jobs/retry-all` is registered **before** `POST /failed-jobs/{id}/retry`. This is intentional — if reversed, the `{id}` wildcard would capture the literal string `"retry-all"` and attempt to look up a job with that external_id.

### null last_heartbeat_at Handling

When a broker's `last_heartbeat_at` is null (never sent a heartbeat), `determineConnectionStatus()` sets `$heartbeatAge = PHP_INT_MAX`, which falls through to `'disconnected'`. The `status` field comparison `$broker->last_heartbeat_at > now()->subMinutes(2)` evaluates `null > Carbon` as `false`, correctly marking as `'stale'`.

## Configuration

| Config key | Default | Description |
|------------|---------|-------------|
| `mqtt-broadcast.dashboard.prefix` | `mqtt-broadcast` | URL prefix for all routes |
| `mqtt-broadcast.dashboard.domain` | `null` | Optional domain constraint |
| `mqtt-broadcast.dashboard.middleware` | `['web']` | Middleware stack (Authorize is always appended) |
| `mqtt-broadcast.logs.enable` | `false` | Enables message logging (required for messages, topics, metrics endpoints) |
| `mqtt-broadcast.queue.name` | `default` | Queue name checked by health and stats endpoints |
| `mqtt-broadcast.memory.threshold_mb` | `128` | Memory threshold for health check status (but see memory key mismatch above) |
| `mqtt-broadcast.master_supervisor.name` | `master` | Cache key suffix for master supervisor lookup (but see repository-pattern docs for name generation mismatch) |

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Logging disabled | Messages/topics/metrics return empty `data` with `logging_enabled: false` — no error |
| Broker not found | 404 with `{ "error": "Broker not found" }` |
| Message not found | 404 with `{ "error": "Message not found" }` |
| Failed job not found | 404 via Laravel `ModelNotFoundException` (standard JSON response) |
| Unauthorized | 403 plain text `Forbidden` from `Authorize` middleware |
| Master supervisor not found | Health returns 503; stats return zeros for memory/uptime |
| Queue driver doesn't support `size()` | Stats returns 0 (caught); Health throws unhandled exception |
| `%` or `_` in topic filter | Matches all topics / any single char — LIKE wildcards not escaped |
| Retained message retried | `retain` flag dropped, retried as `retain: false` |
| Negative `uptime_seconds` in stats | DashboardStatsController computes `$startedAt - now()` (sign reversed) |
| `destroy()` response body | Returns `[]` (empty JSON array) despite 204 status — not a true empty body |

## Mermaid Diagrams

### Request Authentication Flow

```mermaid
flowchart TD
    A[HTTP Request] --> B{Environment?}
    B -->|local| C[Pass through]
    B -->|production/staging| D{Gate: viewMqttBroadcast}
    D -->|allowed| C
    D -->|denied| E[403 Forbidden plain text]
    C --> F[Controller]
    F --> G[JSON Response]
```

### Connection Status State Machine

```mermaid
stateDiagram-v2
    [*] --> connected : heartbeat < 30s AND working=true
    [*] --> idle : heartbeat < 30s AND working=false
    [*] --> reconnecting : 30s ≤ heartbeat < 120s
    [*] --> disconnected : heartbeat ≥ 120s OR null
    note right of disconnected : null last_heartbeat_at → PHP_INT_MAX → disconnected

    connected --> idle : working=false
    connected --> reconnecting : heartbeat ages past 30s
    idle --> connected : working=true
    idle --> reconnecting : heartbeat ages past 30s
    reconnecting --> connected : heartbeat refreshed, working=true
    reconnecting --> disconnected : heartbeat ages past 120s
    disconnected --> connected : heartbeat refreshed, working=true
```

### Health Check Decision Flow

```mermaid
flowchart TD
    A[GET /api/health] --> B[Load all brokers]
    B --> C[Filter active: heartbeat < 2 min]
    A --> D["Find master supervisor in cache<br/>(reads 'memory' key — mismatch with 'memory_mb')"]
    C --> E{Active brokers > 0?}
    D --> F{Master found?}
    E -->|yes| G{Both healthy?}
    E -->|no| H[checks.brokers_active = fail]
    F -->|yes| G
    F -->|no| I[checks.master_running = fail]
    G -->|yes| J[HTTP 200 - healthy]
    G -->|no| K[HTTP 503 - unhealthy]
    H --> K
    I --> K
    D --> L["Check memory vs threshold<br/>(always 0 due to key mismatch)"]
    L --> M{Usage %}
    M -->|"< 80% (always true)"| N[memory_ok = pass]
    M -->|80-99%| O[memory_ok = warn]
    M -->|≥ 100%| P[memory_ok = critical]
    M -->|no supervisor| Q[memory_ok = unknown]
    A --> R["Queue::size() — NO try/catch<br/>(throws on sync driver)"]
```

### Failed Job Retry Flow

```mermaid
sequenceDiagram
    participant Client
    participant FailedJobController
    participant FailedMqttJob
    participant MqttMessageJob
    participant Queue

    Client->>FailedJobController: POST /api/failed-jobs/{id}/retry
    FailedJobController->>FailedMqttJob: where(external_id, id)->firstOrFail()
    FailedMqttJob-->>FailedJobController: job record
    Note over FailedJobController: retain NOT passed to dispatch
    FailedJobController->>MqttMessageJob: dispatch(topic, message, broker, qos)
    MqttMessageJob-->>Queue: queued (retain defaults to false)
    FailedJobController->>FailedMqttJob: increment(retry_count) — 1 UPDATE
    FailedJobController->>FailedMqttJob: update(retried_at: now()) — 2nd UPDATE
    FailedJobController->>FailedMqttJob: fresh() — 1 SELECT
    Note over FailedJobController: 3 queries per retry (+ 1 initial SELECT = 4 total)
    FailedJobController-->>Client: 200 { data: formatted job }
```

### JSON Decode Redundancy in MessageLogController::index()

```mermaid
flowchart TD
    A["index() maps each message"] --> B["formatMessage(msg)"]
    B --> C["isJson(msg) → json_decode #1"]
    C -->|valid JSON| D["json_decode #2 → JSON_PRETTY_PRINT"]
    A --> E["getMessagePreview(msg)"]
    E --> F["isJson(msg) → json_decode #3"]
    F -->|valid JSON| G["json_decode #4 → compact encoding"]
    G --> H["strlen/substr byte truncation at 100"]
    D --> I["Response: 4 json_decode per message"]
```

### Throughput Gap-Fill Inclusive Loop

```mermaid
flowchart LR
    A["subHour().startOfMinute()"] -->|"minute 0"| B["..."]
    B -->|"minute 59"| C["now().startOfMinute()"]
    C -->|"minute 60"| D["Loop ends: 61 points"]
    style D fill:#ff9,stroke:#333
```
