<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Support;

use enzolarosa\MqttBroadcast\Exceptions\InvalidBrokerException;

/**
 * @deprecated since 3.0, use MqttConnectionConfig::fromConnection() instead
 *
 * This class provides basic broker configuration validation but lacks
 * comprehensive validation and type safety. Use MqttConnectionConfig for:
 * - Complete validation (port range, QoS values, timeouts, etc.)
 * - Type-safe configuration access
 * - Better error messages with context
 * - Value object pattern for immutable config
 *
 * Migration example:
 * Before: BrokerValidator::validate('default');
 * After:  MqttConnectionConfig::fromConnection('default'); // Throws if invalid
 *
 * This class will be removed in v4.0.
 */
class BrokerValidator
{
    /**
     * Validate broker configuration.
     *
     * @deprecated since 3.0, use MqttConnectionConfig::fromConnection() instead
     *
     * @param string $broker The broker connection name
     * @throws InvalidBrokerException If validation fails
     */
    public static function validate(string $broker): void
    {
        trigger_deprecation(
            'enzolarosa/mqtt-broadcast',
            '3.0',
            'BrokerValidator::validate() is deprecated, use MqttConnectionConfig::fromConnection() instead.'
        );

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
