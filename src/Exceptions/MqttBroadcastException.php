<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Exceptions;

use Exception;

class MqttBroadcastException extends Exception
{
    /**
     * Thrown when a connection is not configured.
     */
    public static function connectionNotConfigured(string $connection): self
    {
        return new self(
            "MQTT connection [{$connection}] is not configured. Check config/mqtt-broadcast.php"
        );
    }

    /**
     * Thrown when a broker is not configured.
     */
    public static function brokerNotConfigured(string $broker): self
    {
        return new self(
            "Broker connection [{$broker}] is not configured. Check config/mqtt-broadcast.php"
        );
    }

    /**
     * Thrown when a broker is missing required configuration.
     */
    public static function brokerMissingConfiguration(string $broker, string $key): self
    {
        return new self(
            "Broker [{$broker}] configuration is missing required key [{$key}] in config/mqtt-broadcast.php"
        );
    }

    /**
     * Thrown when a connection is missing required configuration.
     */
    public static function connectionMissingConfiguration(string $connection, string $key): self
    {
        return new self(
            "MQTT connection [{$connection}] is missing required key [{$key}] in config/mqtt-broadcast.php"
        );
    }
}
