<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException;
use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;
use enzolarosa\MqttBroadcast\Support\RateLimitService;

class MqttBroadcast
{
    public static function received(string $topic, string $message, string $broker = 'default'): void
    {
        event(new MqttMessageReceived($topic, $message, $broker));
    }

    public static function publish(
        string $topic,
        string $message,
        string $broker = 'default',
        int $qos = 0,
    ): void {
        self::validateBrokerConfiguration($broker);

        // Check rate limit before dispatching job
        $rateLimiter = app(RateLimitService::class);
        $rateLimiter->attempt($broker);

        MqttMessageJob::dispatch($topic, $message, $broker, $qos);
    }

    public static function publishSync(
        string $topic,
        mixed $message,
        string $broker = 'default',
        int $qos = 0,
    ): void {
        self::validateBrokerConfiguration($broker);

        // Check rate limit before dispatching job synchronously
        $rateLimiter = app(RateLimitService::class);
        $rateLimiter->attempt($broker);

        MqttMessageJob::dispatchSync($topic, $message, $broker, $qos);
    }

    public static function getTopic(string $topic, string $broker = 'default'): string
    {
        self::validateBrokerConfiguration($broker);

        $prefix = config("mqtt-broadcast.connections.{$broker}.prefix", '');

        return $prefix.$topic;
    }

    protected static function validateBrokerConfiguration(string $broker): void
    {
        $brokerConfig = config("mqtt-broadcast.connections.{$broker}");

        throw_if(
            is_null($brokerConfig),
            MqttBroadcastException::brokerNotConfigured($broker)
        );

        throw_if(
            !isset($brokerConfig['host']),
            MqttBroadcastException::brokerMissingConfiguration($broker, 'host')
        );

        throw_if(
            !isset($brokerConfig['port']),
            MqttBroadcastException::brokerMissingConfiguration($broker, 'port')
        );
    }
}
