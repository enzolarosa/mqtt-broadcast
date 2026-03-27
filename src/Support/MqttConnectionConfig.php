<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Support;

use enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException;

/**
 * Immutable value object representing a validated MQTT connection configuration.
 *
 * Provides type-safe access to connection settings with comprehensive validation.
 */
class MqttConnectionConfig
{
    /**
     * Create a new MqttConnectionConfig instance.
     *
     * @param array<string, mixed> $config Raw configuration array
     * @throws MqttBroadcastException If validation fails
     */
    private function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly bool $auth,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly int $qos,
        private readonly bool $retain,
        private readonly string $prefix,
        private readonly bool $cleanSession,
        private readonly ?string $clientId,
        private readonly int $aliveInterval,
        private readonly int $timeout,
        private readonly bool $useTls,
        private readonly bool $selfSignedAllowed,
    ) {}

    /**
     * Create configuration from connection name.
     *
     * @param string $connection Connection name from config
     * @return self
     * @throws MqttBroadcastException If connection not found or invalid
     */
    public static function fromConnection(string $connection): self
    {
        $config = config("mqtt-broadcast.connections.{$connection}");

        throw_if(
            is_null($config),
            MqttBroadcastException::connectionNotConfigured($connection)
        );

        // Merge with defaults to avoid duplication in config
        $defaults = config('mqtt-broadcast.defaults.connection', []);
        $config = array_merge($defaults, array_filter($config, fn ($value) => $value !== null));

        return self::fromArray($config, $connection);
    }

    /**
     * Create configuration from array.
     *
     * @param array<string, mixed> $config Configuration array
     * @param string|null $connectionName Optional connection name for error messages
     * @return self
     * @throws MqttBroadcastException If validation fails
     */
    public static function fromArray(array $config, ?string $connectionName = null): self
    {
        // Validate required fields
        self::validateRequired($config, 'host', $connectionName);
        self::validateRequired($config, 'port', $connectionName);

        // Validate types and ranges
        $host = self::validateHost($config['host'], $connectionName);
        $port = self::validatePort($config['port'], $connectionName);
        $qos = self::validateQos($config['qos'] ?? 0, $connectionName);
        $timeout = self::validateTimeout($config['timeout'] ?? 3, $connectionName);
        $aliveInterval = self::validateAliveInterval($config['alive_interval'] ?? 60, $connectionName);

        // Extract boolean flags with defaults
        $auth = (bool) ($config['auth'] ?? false);
        $retain = (bool) ($config['retain'] ?? false);
        $cleanSession = (bool) ($config['clean_session'] ?? false);
        $useTls = (bool) ($config['use_tls'] ?? false);
        $selfSignedAllowed = (bool) ($config['self_signed_allowed'] ?? true);

        // Validate auth credentials if auth is enabled
        if ($auth) {
            self::validateAuthCredentials($config, $connectionName);
        }

        return new self(
            host: $host,
            port: $port,
            auth: $auth,
            username: $config['username'] ?? null,
            password: $config['password'] ?? null,
            qos: $qos,
            retain: $retain,
            prefix: (string) ($config['prefix'] ?? ''),
            cleanSession: $cleanSession,
            clientId: isset($config['clientId']) ? (string) $config['clientId'] : null,
            aliveInterval: $aliveInterval,
            timeout: $timeout,
            useTls: $useTls,
            selfSignedAllowed: $selfSignedAllowed,
        );
    }

    /**
     * Get the MQTT broker host.
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * Get the MQTT broker port.
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * Check if authentication is required.
     */
    public function requiresAuth(): bool
    {
        return $this->auth;
    }

    /**
     * Get the username for authentication.
     */
    public function username(): ?string
    {
        return $this->username;
    }

    /**
     * Get the password for authentication.
     */
    public function password(): ?string
    {
        return $this->password;
    }

    /**
     * Get the Quality of Service level.
     */
    public function qos(): int
    {
        return $this->qos;
    }

    /**
     * Check if retain flag is enabled.
     */
    public function retain(): bool
    {
        return $this->retain;
    }

    /**
     * Get the topic prefix.
     */
    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Check if clean session is enabled.
     */
    public function cleanSession(): bool
    {
        return $this->cleanSession;
    }

    /**
     * Get the custom client ID.
     */
    public function clientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * Get the keep-alive interval in seconds.
     */
    public function aliveInterval(): int
    {
        return $this->aliveInterval;
    }

    /**
     * Get the connection timeout in seconds.
     */
    public function timeout(): int
    {
        return $this->timeout;
    }

    /**
     * Check if TLS/SSL is enabled.
     */
    public function useTls(): bool
    {
        return $this->useTls;
    }

    /**
     * Check if self-signed certificates are allowed.
     */
    public function selfSignedAllowed(): bool
    {
        return $this->selfSignedAllowed;
    }

    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'auth' => $this->auth,
            'username' => $this->username,
            'password' => $this->password,
            'qos' => $this->qos,
            'retain' => $this->retain,
            'prefix' => $this->prefix,
            'clean_session' => $this->cleanSession,
            'clientId' => $this->clientId,
            'alive_interval' => $this->aliveInterval,
            'timeout' => $this->timeout,
            'use_tls' => $this->useTls,
            'self_signed_allowed' => $this->selfSignedAllowed,
        ];
    }

    /**
     * Validate required field exists.
     *
     * @throws MqttBroadcastException
     */
    private static function validateRequired(array $config, string $key, ?string $connection): void
    {
        if (!isset($config[$key]) || $config[$key] === '' || $config[$key] === null) {
            throw MqttBroadcastException::connectionMissingConfiguration(
                $connection ?? 'unknown',
                $key
            );
        }
    }

    /**
     * Validate host is non-empty string.
     *
     * @throws MqttBroadcastException
     */
    private static function validateHost(mixed $host, ?string $connection): string
    {
        if (!is_string($host) || trim($host) === '') {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has invalid host: must be non-empty string, got: %s',
                    $connection ?? 'unknown',
                    get_debug_type($host)
                )
            );
        }

        return $host;
    }

    /**
     * Validate port is integer between 1 and 65535.
     *
     * @throws MqttBroadcastException
     */
    private static function validatePort(mixed $port, ?string $connection): int
    {
        if (!is_int($port) && !is_numeric($port)) {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has invalid port: must be integer, got: %s',
                    $connection ?? 'unknown',
                    get_debug_type($port)
                )
            );
        }

        $port = (int) $port;

        if ($port < 1 || $port > 65535) {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has invalid port: must be between 1 and 65535, got: %d',
                    $connection ?? 'unknown',
                    $port
                )
            );
        }

        return $port;
    }

    /**
     * Validate QoS level is 0, 1, or 2.
     *
     * @throws MqttBroadcastException
     */
    private static function validateQos(mixed $qos, ?string $connection): int
    {
        if (!is_int($qos) && !is_numeric($qos)) {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has invalid qos: must be integer, got: %s',
                    $connection ?? 'unknown',
                    get_debug_type($qos)
                )
            );
        }

        $qos = (int) $qos;

        if ($qos < 0 || $qos > 2) {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has invalid qos: must be 0, 1, or 2, got: %d',
                    $connection ?? 'unknown',
                    $qos
                )
            );
        }

        return $qos;
    }

    /**
     * Validate timeout is positive integer.
     *
     * @throws MqttBroadcastException
     */
    private static function validateTimeout(mixed $timeout, ?string $connection): int
    {
        if (!is_int($timeout) && !is_numeric($timeout)) {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has invalid timeout: must be integer, got: %s',
                    $connection ?? 'unknown',
                    get_debug_type($timeout)
                )
            );
        }

        $timeout = (int) $timeout;

        if ($timeout <= 0) {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has invalid timeout: must be greater than 0, got: %d',
                    $connection ?? 'unknown',
                    $timeout
                )
            );
        }

        return $timeout;
    }

    /**
     * Validate alive interval is positive integer.
     *
     * @throws MqttBroadcastException
     */
    private static function validateAliveInterval(mixed $interval, ?string $connection): int
    {
        if (!is_int($interval) && !is_numeric($interval)) {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has invalid alive_interval: must be integer, got: %s',
                    $connection ?? 'unknown',
                    get_debug_type($interval)
                )
            );
        }

        $interval = (int) $interval;

        if ($interval <= 0) {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has invalid alive_interval: must be greater than 0, got: %d',
                    $connection ?? 'unknown',
                    $interval
                )
            );
        }

        return $interval;
    }

    /**
     * Validate auth credentials when auth is enabled.
     *
     * @throws MqttBroadcastException
     */
    private static function validateAuthCredentials(array $config, ?string $connection): void
    {
        if (empty($config['username']) || !is_string($config['username'])) {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has auth enabled but missing or invalid username',
                    $connection ?? 'unknown'
                )
            );
        }

        if (empty($config['password']) || !is_string($config['password'])) {
            throw new MqttBroadcastException(
                sprintf(
                    'Connection "%s" has auth enabled but missing or invalid password',
                    $connection ?? 'unknown'
                )
            );
        }
    }
}
