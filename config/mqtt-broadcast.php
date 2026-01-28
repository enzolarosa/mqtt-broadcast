<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for all broker connections. Inspired by Laravel Horizon,
    | these values are automatically applied to all connections unless explicitly
    | overridden in the connection's configuration.
    |
    */
    'defaults' => [
        'connection' => [
            // MQTT Protocol Settings
            'auth' => false,
            'qos' => 0,
            'retain' => false,
            'prefix' => '',
            'clean_session' => false,
            'alive_interval' => 60,
            'timeout' => 3,
            'use_tls' => false,
            'self_signed_allowed' => true,

            // Reconnection & Circuit Breaker
            // Maximum number of consecutive connection failures before action
            'max_retries' => env('MQTT_MAX_RETRIES', 20),

            // Maximum delay between retry attempts (seconds)
            // Uses exponential backoff: 1s, 2s, 4s, 8s... up to this max
            'max_retry_delay' => env('MQTT_MAX_RETRY_DELAY', 60),

            // Whether to terminate supervisor after max_retries reached
            'terminate_on_max_retries' => env('MQTT_TERMINATE_ON_MAX_RETRIES', false),

            // Maximum duration in seconds to keep retrying before giving up
            // After this duration of continuous connection failures, supervisor terminates
            // Default: 3600 seconds (1 hour)
            'max_failure_duration' => env('MQTT_MAX_FAILURE_DURATION', 3600),

            // Rate Limiting (per connection)
            // Override these values per-connection for custom limits
            'rate_limiting' => [
                'max_per_minute' => env('MQTT_RATE_LIMIT_PER_MINUTE', 1000),
                'max_per_second' => env('MQTT_RATE_LIMIT_PER_SECOND', null),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broker Connections
    |--------------------------------------------------------------------------
    |
    | Define your MQTT broker connections here. Each connection inherits
    | settings from 'defaults.connection' unless explicitly overridden.
    |
    | Only host and port are required. All other settings are optional and
    | will fall back to defaults if not specified.
    |
    */
    'connections' => [

        'default' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => env('MQTT_PORT', '1883'),
            'auth' => env('MQTT_AUTH'),
            'username' => env('MQTT_USERNAME'),
            'password' => env('MQTT_PASSWORD'),
            'qos' => env('MQTT_QOS'),
            'retain' => env('MQTT_RETAIN'),
            'prefix' => env('MQTT_PREFIX'),
            'clean_session' => env('MQTT_CLEAN_SESSION'),
            'clientId' => env('MQTT_CLIENT_ID'),
            'alive_interval' => env('MQTT_ALIVE_INTERVAL'),
            'timeout' => env('MQTT_TIMEOUT'),
            'use_tls' => env('MQTT_USE_TLS'),
            'self_signed_allowed' => env('MQTT_SELF_SIGNED_ALLOWED'),

            // All settings from defaults.connection are automatically inherited
            // You can override any default by specifying it here
        ],

        // Example: Critical broker with custom settings
        //        'critical' => [
        //            'host' => env('MQTT_CRITICAL_HOST', '127.0.0.1'),
        //            'port' => env('MQTT_CRITICAL_PORT', '1883'),
        //            'username' => env('MQTT_CRITICAL_USERNAME'),
        //            'password' => env('MQTT_CRITICAL_PASSWORD'),
        //
        //            // Longer failure duration for critical brokers (2 hours)
        //            'max_failure_duration' => 7200,
        //
        //            // Custom rate limiting for critical broker
        //            'rate_limiting' => [
        //                'max_per_minute' => 5000,
        //                'max_per_second' => 100,
        //            ],
        //        ],

        // Example: Low-priority broker with restricted limits
        //        'low-priority' => [
        //            'host' => env('MQTT_LOW_PRIORITY_HOST', '127.0.0.1'),
        //            'port' => env('MQTT_LOW_PRIORITY_PORT', '1883'),
        //
        //            // More restrictive rate limiting
        //            'rate_limiting' => [
        //                'max_per_minute' => 100,
        //            ],
        //        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting - Global Settings
    |--------------------------------------------------------------------------
    |
    | Global rate limiting configuration. Per-connection limits are defined
    | in each connection's configuration (see 'connections' section above).
    |
    */
    'rate_limiting' => [
        // Enable or disable rate limiting globally
        'enabled' => env('MQTT_RATE_LIMIT_ENABLED', true),

        // Strategy when rate limit is exceeded:
        // - 'reject': Throw RateLimitExceededException (default)
        // - 'throttle': Delay/requeue job until rate limit allows
        'strategy' => env('MQTT_RATE_LIMIT_STRATEGY', 'reject'),

        // Rate limit granularity:
        // - true: Limit per broker connection (isolated)
        // - false: Global limit across all connections
        'by_connection' => env('MQTT_RATE_LIMIT_BY_CONNECTION', true),

        // Cache driver for rate limit tracking
        // null = use default cache driver from cache config
        'cache_driver' => env('MQTT_RATE_LIMIT_CACHE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Management
    |--------------------------------------------------------------------------
    |
    | Configure memory management for long-running supervisor processes.
    | Prevents unbounded memory growth from MQTT client queues and PHP
    | circular references.
    |
    */
    'memory' => [
        // Interval between garbage collection cycles (loop iterations)
        // Default: 100 iterations (approximately every 100 seconds with 1s sleep)
        'gc_interval' => env('MQTT_GC_INTERVAL', 100),

        // Memory threshold in MB for warning/restart
        // Following Laravel queue worker standard (128MB default)
        // When exceeded:
        // - At 80%: Warning log (early alert)
        // - At 100%: Error log + auto-restart countdown if enabled
        'threshold_mb' => env('MQTT_MEMORY_THRESHOLD_MB', 128),

        // Automatically restart supervisor when memory threshold is breached
        // Enabled by default for production stability
        'auto_restart' => env('MQTT_MEMORY_AUTO_RESTART', true),

        // Grace period in seconds before auto-restart is triggered
        // Allows in-progress operations to complete safely
        'restart_delay_seconds' => env('MQTT_RESTART_DELAY_SECONDS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supervisor & Environment
    |--------------------------------------------------------------------------
    |
    | Configuration for supervisor instances and environment-specific
    | broker assignments.
    |
    */
    'master_supervisor' => [
        // Unique identifier for this master supervisor instance
        'name' => env('MQTT_MASTER_NAME', 'master'),

        // Cache TTL for master supervisor state (seconds)
        'cache_ttl' => env('MQTT_MASTER_CACHE_TTL', 3600),

        // Cache driver to use for state persistence
        'cache_driver' => env('MQTT_CACHE_DRIVER', 'redis'),
    ],

    'supervisor' => [
        // Interval between heartbeat updates (seconds)
        'heartbeat_interval' => env('MQTT_HEARTBEAT_INTERVAL', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Supervisors
    |--------------------------------------------------------------------------
    |
    | Define which MQTT brokers should be monitored in each environment.
    | Following Laravel Horizon's pattern, this allows environment-specific
    | broker configurations.
    |
    */
    'environments' => [
        'production' => [
            'default',
        ],

        'local' => [
            'default',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for MQTT message publishing and event listeners.
    |
    */
    'queue' => [
        'name' => env('MQTT_JOB_QUEUE', 'default'),
        'listener' => env('MQTT_LISTENER_QUEUE', 'default'),
        'connection' => env('MQTT_JOB_CONNECTION', 'redis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database logging for MQTT messages.
    |
    */
    'logs' => [
        'enable' => env('MQTT_LOG_ENABLE', false),
        'queue' => env('MQTT_LOG_JOB_QUEUE', 'default'),
        'connection' => env('MQTT_LOG_CONNECTION', 'mysql'),
        'table' => env('MQTT_LOG_TABLE', 'mqtt_loggers'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Repository Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for broker and supervisor repository persistence.
    |
    */
    'repository' => [
        'broker' => [
            // Column name for heartbeat timestamp tracking
            'heartbeat_column' => 'last_heartbeat_at',

            // Threshold for considering a broker stale (seconds)
            'stale_threshold' => env('MQTT_STALE_THRESHOLD', 300),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Master Password
    |--------------------------------------------------------------------------
    |
    | Password for master supervisor authentication.
    |
    */
    'password' => env('MQTT_MASTER_PASS', Illuminate\Support\Str::random(32)),
];
