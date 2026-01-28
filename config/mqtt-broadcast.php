<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | QUICK START - Minimal Configuration
    |--------------------------------------------------------------------------
    |
    | These are the ONLY settings you need to get started.
    | Everything else has sensible defaults.
    |
    | 1. Set your MQTT broker connection details
    | 2. Run: php artisan mqtt-broadcast
    | 3. Done!
    |
    */

    'connections' => [
        'default' => [
            // Required: Broker connection
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => env('MQTT_PORT', 1883),

            // Optional: Authentication (remove if not needed)
            'username' => env('MQTT_USERNAME'),
            'password' => env('MQTT_PASSWORD'),

            // Optional: Topic prefix for all messages
            'prefix' => env('MQTT_PREFIX', ''),

            // Optional: TLS/SSL encryption
            'use_tls' => env('MQTT_USE_TLS', false),

            // Optional: MQTT Client ID (auto-generated if not set)
            'clientId' => env('MQTT_CLIENT_ID'),
        ],

        // Add more brokers for redundancy or multi-environment
        // 'backup' => [
        //     'host' => env('MQTT_BACKUP_HOST', 'mqtt-backup.example.com'),
        //     'port' => env('MQTT_BACKUP_PORT', 1883),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Configuration
    |--------------------------------------------------------------------------
    |
    | Define which brokers to use per environment.
    | Uses APP_ENV by default (local, staging, production).
    |
    */

    'environments' => [
        'production' => ['default'],
        'local' => ['default'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard & Monitoring
    |--------------------------------------------------------------------------
    |
    | Access the real-time dashboard at: /mqtt-broadcast
    |
    | In production, configure access via Gate:
    | Gate::define('viewMqttBroadcast', fn($user) => $user->isAdmin());
    |
    */

    'path' => env('MQTT_BROADCAST_PATH', 'mqtt-broadcast'),
    'domain' => env('MQTT_BROADCAST_DOMAIN', null),
    'middleware' => ['web', \enzolarosa\MqttBroadcast\Http\Middleware\Authorize::class],

    /*
    |--------------------------------------------------------------------------
    | Message Logging
    |--------------------------------------------------------------------------
    |
    | Enable database logging to store all received MQTT messages.
    | Useful for debugging and message history.
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
    | ADVANCED OPTIONS
    |--------------------------------------------------------------------------
    |
    | These options have optimized defaults.
    | Only modify if you know what you're doing.
    |
    */

    // MQTT Protocol Defaults
    'defaults' => [
        'connection' => [
            'qos' => 0,                    // Quality of Service (0, 1, or 2)
            'retain' => false,             // Retain messages on broker
            'clean_session' => false,      // Clean session flag
            'alive_interval' => 60,        // Keep-alive interval (seconds)
            'timeout' => 3,                // Connection timeout (seconds)
            'self_signed_allowed' => true, // Allow self-signed TLS certificates

            // Reconnection behavior
            'max_retries' => env('MQTT_MAX_RETRIES', 20),
            'max_retry_delay' => env('MQTT_MAX_RETRY_DELAY', 60),
            'max_failure_duration' => env('MQTT_MAX_FAILURE_DURATION', 3600), // 1 hour
            'terminate_on_max_retries' => env('MQTT_TERMINATE_ON_MAX_RETRIES', false),
        ],
    ],

    // Memory Management
    'memory' => [
        'gc_interval' => env('MQTT_GC_INTERVAL', 100),
        'threshold_mb' => env('MQTT_MEMORY_THRESHOLD_MB', 128),
        'auto_restart' => env('MQTT_MEMORY_AUTO_RESTART', true),
        'restart_delay_seconds' => env('MQTT_RESTART_DELAY_SECONDS', 10),
    ],

    // Queue Configuration
    'queue' => [
        'name' => env('MQTT_JOB_QUEUE', 'default'),
        'listener' => env('MQTT_LISTENER_QUEUE', 'default'),
        'connection' => env('MQTT_JOB_CONNECTION', 'redis'),
    ],

    // Supervisor Configuration
    'master_supervisor' => [
        'name' => env('MQTT_MASTER_NAME', 'master'),
        'cache_ttl' => env('MQTT_MASTER_CACHE_TTL', 3600),
        'cache_driver' => env('MQTT_CACHE_DRIVER', 'redis'),
    ],

    'supervisor' => [
        'heartbeat_interval' => env('MQTT_HEARTBEAT_INTERVAL', 1),
    ],

    // Repository Settings
    'repository' => [
        'broker' => [
            'heartbeat_column' => 'last_heartbeat_at',
            'stale_threshold' => env('MQTT_STALE_THRESHOLD', 300),
        ],
    ],

    // Master Password (legacy, can be removed in future versions)
    'password' => env('MQTT_MASTER_PASS', Illuminate\Support\Str::random(32)),
];
