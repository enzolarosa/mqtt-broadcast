<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Factories;

use enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException;
use enzolarosa\MqttBroadcast\Support\MqttConnectionConfig;
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
        // Use new validated config object internally
        $config = MqttConnectionConfig::fromConnection($connection);

        return $this->createFromConfig($config, $clientId);
    }

    /**
     * Create a new MQTT client from a validated config object.
     *
     * This is the preferred method as it uses type-safe, validated configuration.
     *
     * @param MqttConnectionConfig $config Validated connection configuration
     * @param string|null $clientId Custom client ID (null = use config or generate UUID)
     * @return MqttClient Configured but not connected client
     */
    public function createFromConfig(
        MqttConnectionConfig $config,
        ?string $clientId = null
    ): MqttClient {
        // Determine client ID: custom > config > UUID
        $clientId = $clientId ?? $config->clientId() ?? Str::uuid()->toString();

        $mqtt = new MqttClient($config->host(), $config->port(), $clientId);

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
        // Use new validated config object internally
        $config = MqttConnectionConfig::fromConnection($connection);

        return $this->getConnectionSettingsFromConfig($config, $cleanSession);
    }

    /**
     * Get connection settings from a validated config object.
     *
     * This is the preferred method as it uses type-safe, validated configuration.
     *
     * @param MqttConnectionConfig $config Validated connection configuration
     * @param bool|null $cleanSession Custom clean session (null = use config)
     * @return array{settings: ConnectionSettings|null, cleanSession: bool}
     */
    public function getConnectionSettingsFromConfig(
        MqttConnectionConfig $config,
        ?bool $cleanSession = null
    ): array {
        // If no auth, return null settings
        if (!$config->requiresAuth()) {
            return [
                'settings' => null,
                'cleanSession' => false,
            ];
        }

        $connectionSettings = (new ConnectionSettings)
            ->setKeepAliveInterval($config->aliveInterval())
            ->setConnectTimeout($config->timeout())
            ->setUseTls($config->useTls())
            ->setTlsSelfSignedAllowed($config->selfSignedAllowed())
            ->setUsername($config->username())
            ->setPassword($config->password());

        // Determine clean session: custom > config
        $cleanSession = $cleanSession ?? $config->cleanSession();

        return [
            'settings' => $connectionSettings,
            'cleanSession' => $cleanSession,
        ];
    }
}
