<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Support;

use enzolarosa\MqttBroadcast\Exceptions\InvalidBrokerException;

class BrokerValidator
{
    public static function validate(string $broker): void
    {
        $config = config("mqtt-broadcast.connections.{$broker}");

        throw_unless($config, InvalidBrokerException::notConfigured($broker));

        throw_unless($config['host'] ?? null, InvalidBrokerException::missingHost($broker));

        throw_unless($config['port'] ?? null, InvalidBrokerException::missingPort($broker));

        if ($config['auth'] ?? false) {
            throw_unless(
                ($config['username'] ?? null) && ($config['password'] ?? null),
                InvalidBrokerException::missingCredentials($broker)
            );
        }
    }
}
