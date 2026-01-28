<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    |
    | Default values for various configuration sections. These are applied
    | automatically where not explicitly overridden, reducing duplication
    | across multiple broker connections.
    |
    */
    'defaults' => [
        // Default MQTT connection settings (applied to all connections)
        'connection' => [
            'auth' => false,
            'qos' => 0,
            'retain' => false,
            'prefix' => '',
            'clean_session' => false,
            'alive_interval' => 60,
            'timeout' => 3,
            'use_tls' => false,
            'self_signed_allowed' => true,
        ],
    ],

    'logs' => [
        'enable' => env('MQTT_LOG_ENABLE', false),
        'queue' => env('MQTT_LOG_JOB_QUEUE', 'default'),
        'connection' => env('MQTT_LOG_CONNECTION', 'mysql'),
        'table' => env('MQTT_LOG_TABLE', 'mqtt_loggers'),
    ],

    'password' => env('MQTT_MASTER_PASS', Illuminate\Support\Str::random(32)),

    'queue' => [
        'name' => env('MQTT_JOB_QUEUE', 'default'),
        'listener' => env('MQTT_LISTENER_QUEUE', 'default'),
        'connection' => env('MQTT_JOB_CONNECTION', 'redis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconnection Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic reconnection behavior for BrokerSupervisor when
    | MQTT connection fails. Uses exponential backoff to avoid flooding logs
    | and excessive retry attempts.
    |
    */
    'reconnection' => [
        // Maximum number of consecutive connection failures before action
        'max_retries' => env('MQTT_MAX_RETRIES', 20),

        // Maximum delay between retry attempts (seconds)
        // Uses exponential backoff: 1s, 2s, 4s, 8s... up to this max
        'max_retry_delay' => env('MQTT_MAX_RETRY_DELAY', 60),

        // Whether to terminate supervisor after max_retries reached
        // false: Reset retry count and continue with long pause (backward compatible)
        // true: Terminate supervisor (requires MasterSupervisor to handle restart)
        'terminate_on_max_retries' => env('MQTT_TERMINATE_ON_MAX_RETRIES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supervisor Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for BrokerSupervisor instances that manage individual
    | MQTT broker connections.
    |
    */
    'supervisor' => [
        // Interval between heartbeat updates (seconds)
        'heartbeat_interval' => env('MQTT_HEARTBEAT_INTERVAL', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Master Supervisor Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for MasterSupervisor that orchestrates multiple
    | BrokerSupervisor instances. Used in H4.5+ refactoring.
    |
    */
    'master_supervisor' => [
        // Unique identifier for this master supervisor instance
        'name' => env('MQTT_MASTER_NAME', 'master'),

        // Cache TTL for master supervisor state (seconds)
        'cache_ttl' => env('MQTT_MASTER_CACHE_TTL', 3600),

        // Cache driver to use for state persistence
        // Supports: redis, memcached, file, array
        'cache_driver' => env('MQTT_CACHE_DRIVER', 'redis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Supervisors
    |--------------------------------------------------------------------------
    |
    | Define which MQTT brokers should be monitored in each environment.
    | Following Laravel Horizon's pattern, this allows environment-specific
    | broker configurations. When running `php artisan mqtt-broadcast`, the
    | command will automatically load brokers for the current environment.
    |
    | Example:
    |   'environments' => [
    |       'production' => ['prod-mqtt-1', 'prod-mqtt-2'],
    |       'local' => ['default'],
    |   ],
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
            // Brokers without heartbeat update within this time are marked stale
            'stale_threshold' => env('MQTT_STALE_THRESHOLD', 300),
        ],
    ],

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
        ],

        //        'local' => [
        //            'host' => env('MQTT_HOST', '127.0.0.1'),
        //            'port' => env('MQTT_PORT', '1883'),
        //            'auth' => env('MQTT_AUTH', false),
        //            'username' => env('MQTT_USERNAME'),
        //            'password' => env('MQTT_PASSWORD'),
        //            'qos' => env('MQTT_QOS', 0),
        //            'prefix' => env('MQTT_PREFIX', ''),
        //            'clean_session' => env('MQTT_CLEAN_SESSION', false),
        //            'clientId' => env('MQTT_CLIENT_ID'),
        //        ],

        //        'remote' => [
        //            'host' => env('MQTT_HOST', '127.0.0.1'),
        //            'port' => env('MQTT_PORT', '1883'),
        //            'auth' => env('MQTT_AUTH', false),
        //            'username' => env('MQTT_USERNAME'),
        //            'password' => env('MQTT_PASSWORD'),
        //            'qos' => env('MQTT_QOS', 0),
        //            'prefix' => env('MQTT_PREFIX', ''),
        //            'clean_session' => env('MQTT_CLEAN_SESSION', false),
        //            'clientId' => env('MQTT_CLIENT_ID'),
        //        ],
    ],
];
