<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Exceptions;

use InvalidArgumentException;

class InvalidBrokerException extends InvalidArgumentException
{
    public static function notConfigured(string $broker): self
    {
        return new self(
            "Broker connection [{$broker}] is not configured. Check config/mqtt-broadcast.php"
        );
    }

    public static function missingConfiguration(string $broker, string $key): self
    {
        return new self(
            "Broker [{$broker}] configuration is missing required key [{$key}] in config/mqtt-broadcast.php"
        );
    }
}
