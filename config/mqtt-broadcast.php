<?php

declare(strict_types=1);

return [
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

    'connections' => [

        'default' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => env('MQTT_PORT', '1883'),
            'auth' => env('MQTT_AUTH', false),
            'username' => env('MQTT_USERNAME'),
            'password' => env('MQTT_PASSWORD'),
            'qos' => env('MQTT_QOS', 0),
            'retain' => env('MQTT_RETAIN', false),
            'prefix' => env('MQTT_PREFIX', ''),
            'clean_session' => env('MQTT_CLEAN_SESSION', false),
            'clientId' => env('MQTT_CLIENT_ID'),
            'alive_interval' => env('MQTT_ALIVE_INTERVAL', 60),
            'timeout' => env('MQTT_TIMEOUT', 3),
            'use_tls' => env('MQTT_USE_TLS', false),
            'self_signed_allowed' => env('MQTT_SELF_SIGNED_ALLOWED', true),
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
