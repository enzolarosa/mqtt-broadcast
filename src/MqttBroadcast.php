<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Exceptions\InvalidBrokerException;
use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;

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

        MqttMessageJob::dispatch($topic, $message, $broker, $qos);
    }

    public static function publishSync(
        string $topic,
        mixed $message,
        string $broker = 'default',
        int $qos = 0,
    ): void {
        self::validateBrokerConfiguration($broker);

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
            InvalidBrokerException::notConfigured($broker)
        );

        throw_if(
            !isset($brokerConfig['host']),
            InvalidBrokerException::missingConfiguration($broker, 'host')
        );

        throw_if(
            !isset($brokerConfig['port']),
            InvalidBrokerException::missingConfiguration($broker, 'port')
        );
    }
}
