<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Factories;

use enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException;
use Illuminate\Support\Str;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttClientFactory
{
    /**
     * Create a new MQTT client for the given connection.
     *
     * Returns a configured but NOT connected client.
     * The caller is responsible for calling connect() when ready.
     *
     * @param string $connection The connection name from config
     * @param string|null $clientId Custom client ID (null = use config or generate UUID)
     * @param bool|null $cleanSession Custom clean session (null = use config)
     * @return MqttClient Configured but not connected client
     * @throws MqttBroadcastException If connection config is not found or missing required keys
     */
    public function create(
        string $connection,
        ?string $clientId = null,
        ?bool $cleanSession = null
    ): MqttClient {
        $config = config("mqtt-broadcast.connections.{$connection}");

        throw_if(
            is_null($config),
            MqttBroadcastException::connectionNotConfigured($connection)
        );

        // Validate required configuration keys
        throw_if(
            !isset($config['host']),
            MqttBroadcastException::connectionMissingConfiguration($connection, 'host')
        );

        throw_if(
            !isset($config['port']),
            MqttBroadcastException::connectionMissingConfiguration($connection, 'port')
        );

        // Determine client ID: custom > config > UUID
        $clientId = $clientId ?? $config['clientId'] ?? Str::uuid()->toString();

        $server = $config['host'];
        $port = $config['port'];

        $mqtt = new MqttClient($server, $port, $clientId);

        // Store connection settings for later use when connect() is called
        // Note: We don't call connect() here - the caller decides when to connect
        // This makes the factory testable without requiring a real MQTT broker

        return $mqtt;
    }

    /**
     * Get connection settings for the given connection.
     *
     * This is useful when you need to call connect() manually with authentication.
     *
     * @param string $connection The connection name from config
     * @param bool|null $cleanSession Custom clean session (null = use config)
     * @return array{settings: ConnectionSettings|null, cleanSession: bool}
     * @throws MqttBroadcastException If connection config is not found
     */
    public function getConnectionSettings(string $connection, ?bool $cleanSession = null): array
    {
        $config = config("mqtt-broadcast.connections.{$connection}");

        throw_if(
            is_null($config),
            MqttBroadcastException::connectionNotConfigured($connection)
        );

        // If no auth, return null settings
        if (!($config['auth'] ?? false)) {
            return [
                'settings' => null,
                'cleanSession' => false,
            ];
        }

        $connectionSettings = (new ConnectionSettings)
            ->setKeepAliveInterval($config['alive_interval'] ?? 60)
            ->setConnectTimeout($config['timeout'] ?? 3)
            ->setUseTls($config['use_tls'] ?? true)
            ->setTlsSelfSignedAllowed($config['self_signed_allowed'] ?? true)
            ->setUsername($config['username'])
            ->setPassword($config['password']);

        // Determine clean session: custom > config > false
        $cleanSession = $cleanSession ?? $config['clean_session'] ?? false;

        return [
            'settings' => $connectionSettings,
            'cleanSession' => $cleanSession,
        ];
    }
}
