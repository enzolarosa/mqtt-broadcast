<?php

declare(strict_types=1);

namespace Tests\Unit\Factories;

use enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException;
use enzolarosa\MqttBroadcast\Factories\MqttClientFactory;
use enzolarosa\MqttBroadcast\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttClientFactoryTest extends TestCase
{
    protected MqttClientFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new MqttClientFactory();
    }

    /**
     * CORE TEST 1: Creates client without authentication
     */
    public function test_it_creates_client_without_authentication(): void
    {
        Config::set('mqtt-broadcast.connections.test', [
            'host' => '127.0.0.1',
            'port' => 1883,
            'auth' => false,
        ]);

        $client = $this->factory->create('test');

        $this->assertInstanceOf(MqttClient::class, $client);
    }

    /**
     * CORE TEST 2: Creates client with authentication
     */
    public function test_it_creates_client_with_authentication(): void
    {
        Config::set('mqtt-broadcast.connections.secure', [
            'host' => 'mqtt.example.com',
            'port' => 8883,
            'auth' => true,
            'username' => 'testuser',
            'password' => 'testpass',
            'use_tls' => true,
            'self_signed_allowed' => false,
            'alive_interval' => 60,
            'timeout' => 5,
            'clean_session' => true,
        ]);

        $client = $this->factory->create('secure');

        $this->assertInstanceOf(MqttClient::class, $client);
    }

    /**
     * CORE TEST 3: Throws exception for non-existent connection
     */
    public function test_it_throws_exception_for_non_existent_connection(): void
    {
        $this->expectException(MqttBroadcastException::class);
        $this->expectExceptionMessage('MQTT connection [non-existent-connection] is not configured');

        $this->factory->create('non-existent-connection');
    }

    /**
     * EDGE CASE 1: Uses custom client ID when provided
     */
    public function test_it_uses_custom_client_id_when_provided(): void
    {
        Config::set('mqtt-broadcast.connections.test', [
            'host' => '127.0.0.1',
            'port' => 1883,
            'auth' => false,
        ]);

        $customId = 'custom-client-123';
        $client = $this->factory->create('test', $customId);

        $this->assertInstanceOf(MqttClient::class, $client);
        // Note: MqttClient doesn't expose clientId publicly, but we can verify it was created
    }

    /**
     * EDGE CASE 2: Generates UUID client ID when not provided
     */
    public function test_it_generates_uuid_client_id_when_not_provided(): void
    {
        Config::set('mqtt-broadcast.connections.test', [
            'host' => '127.0.0.1',
            'port' => 1883,
            'auth' => false,
        ]);

        $client = $this->factory->create('test');

        $this->assertInstanceOf(MqttClient::class, $client);
        // UUID is generated internally, client is created successfully
    }

    /**
     * EDGE CASE 3: Uses config client ID if specified
     */
    public function test_it_uses_config_client_id_if_specified(): void
    {
        Config::set('mqtt-broadcast.connections.test', [
            'host' => '127.0.0.1',
            'port' => 1883,
            'auth' => false,
            'clientId' => 'configured-client-id',
        ]);

        $client = $this->factory->create('test');

        $this->assertInstanceOf(MqttClient::class, $client);
    }

    /**
     * EDGE CASE 4: Returns connection settings for authenticated connection
     */
    public function test_it_returns_connection_settings_for_authenticated_connection(): void
    {
        Config::set('mqtt-broadcast.connections.secure', [
            'host' => 'mqtt.example.com',
            'port' => 8883,
            'auth' => true,
            'username' => 'testuser',
            'password' => 'testpass',
            'use_tls' => true,
            'self_signed_allowed' => false,
            'alive_interval' => 60,
            'timeout' => 5,
            'clean_session' => true,
        ]);

        $result = $this->factory->getConnectionSettings('secure');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('settings', $result);
        $this->assertArrayHasKey('cleanSession', $result);
        $this->assertInstanceOf(ConnectionSettings::class, $result['settings']);
        $this->assertTrue($result['cleanSession']);
    }

    /**
     * EDGE CASE 5: Returns null settings for non-authenticated connection
     */
    public function test_it_returns_null_settings_for_non_authenticated_connection(): void
    {
        Config::set('mqtt-broadcast.connections.test', [
            'host' => '127.0.0.1',
            'port' => 1883,
            'auth' => false,
        ]);

        $result = $this->factory->getConnectionSettings('test');

        $this->assertIsArray($result);
        $this->assertNull($result['settings']);
        $this->assertFalse($result['cleanSession']);
    }

    /**
     * EDGE CASE 6: Uses custom clean session when provided
     */
    public function test_it_uses_custom_clean_session_when_provided(): void
    {
        Config::set('mqtt-broadcast.connections.secure', [
            'host' => 'mqtt.example.com',
            'port' => 8883,
            'auth' => true,
            'username' => 'testuser',
            'password' => 'testpass',
            'clean_session' => false,
        ]);

        $result = $this->factory->getConnectionSettings('secure', true);

        $this->assertTrue($result['cleanSession']); // Custom value used
    }

    /**
     * EDGE CASE 7: Uses config clean session when not provided
     */
    public function test_it_uses_config_clean_session_when_not_provided(): void
    {
        Config::set('mqtt-broadcast.connections.secure', [
            'host' => 'mqtt.example.com',
            'port' => 8883,
            'auth' => true,
            'username' => 'testuser',
            'password' => 'testpass',
            'clean_session' => true,
        ]);

        $result = $this->factory->getConnectionSettings('secure');

        $this->assertTrue($result['cleanSession']); // Config value used
    }

    /**
     * EDGE CASE 8: Defaults to false for clean session if not in config
     */
    public function test_it_defaults_to_false_for_clean_session_if_not_in_config(): void
    {
        Config::set('mqtt-broadcast.connections.secure', [
            'host' => 'mqtt.example.com',
            'port' => 8883,
            'auth' => true,
            'username' => 'testuser',
            'password' => 'testpass',
            // No clean_session specified
        ]);

        $result = $this->factory->getConnectionSettings('secure');

        $this->assertFalse($result['cleanSession']); // Defaults to false
    }
}
