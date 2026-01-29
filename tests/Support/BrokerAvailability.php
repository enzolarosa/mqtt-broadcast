<?php

declare(strict_types=1);

namespace Tests\Support;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

/**
 * Helper class to check if a real MQTT broker is available for integration tests.
 */
class BrokerAvailability
{
    protected static ?bool $cached = null;

    /**
     * Check if a real MQTT broker is available.
     *
     * @param string $host
     * @param int $port
     * @param int $timeout Connection timeout in seconds
     * @return bool
     */
    public static function isAvailable(
        string $host = '127.0.0.1',
        int $port = 1883,
        int $timeout = 2
    ): bool {
        // Return cached result if already checked
        if (self::$cached !== null) {
            return self::$cached;
        }

        // Check via environment variable (CI can set this)
        if (getenv('MQTT_BROKER_AVAILABLE') !== false) {
            self::$cached = (bool) getenv('MQTT_BROKER_AVAILABLE');
            return self::$cached;
        }

        // Try to connect to broker
        try {
            $clientId = 'test-availability-' . uniqid();
            $client = new MqttClient($host, $port, $clientId);

            $settings = (new ConnectionSettings())
                ->setConnectTimeout($timeout)
                ->setUseTls(false)
                ->setTlsSelfSignedAllowed(true);

            $client->connect($settings, true);
            $client->disconnect();

            self::$cached = true;
            return true;
        } catch (\Throwable $e) {
            self::$cached = false;
            return false;
        }
    }

    /**
     * Reset the cached availability check.
     * Useful for testing the checker itself.
     */
    public static function resetCache(): void
    {
        self::$cached = null;
    }

    /**
     * Get the reason why broker is not available.
     *
     * @param string $host
     * @param int $port
     * @return string|null Error message or null if available
     */
    public static function getUnavailableReason(
        string $host = '127.0.0.1',
        int $port = 1883
    ): ?string {
        if (self::isAvailable($host, $port)) {
            return null;
        }

        // Try to determine the reason
        $socket = @fsockopen($host, $port, $errno, $errstr, 2);

        if ($socket === false) {
            return sprintf(
                'Cannot connect to %s:%d - %s (errno: %d). Start broker with: docker compose -f docker-compose.test.yml up -d',
                $host,
                $port,
                $errstr,
                $errno
            );
        }

        fclose($socket);
        return 'Port is open but MQTT handshake failed';
    }
}
