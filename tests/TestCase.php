<?php

namespace enzolarosa\MqttBroadcast\Tests;

use enzolarosa\MqttBroadcast\MqttBroadcastServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'enzolarosa\\MqttBroadcast\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('mqtt-broadcast.logs.connection', 'testing');
        config()->set('mqtt-broadcast.logs.table', 'mqtt_loggers');
        config()->set('mqtt-broadcast.logs.enable', true);

        config()->set('mqtt-broadcast.queue.name', null);
        config()->set('mqtt-broadcast.queue.connection', null);

        config()->set('mqtt-broadcast.connections.default', [
            'host' => '127.0.0.1',
            'port' => 1883,
            'prefix' => '',
            'qos' => 0,
            'retain' => false,
            'auth' => false,
            'username' => null,
            'password' => null,
            'alive_interval' => 60,
            'timeout' => 3,
            'use_tls' => false,
            'self_signed_allowed' => true,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            MqttBroadcastServiceProvider::class,
        ];
    }

    protected function setMqttConfig(string $broker, array $config): void
    {
        config([
            "mqtt-broadcast.connections.{$broker}" => array_merge([
                'host' => '127.0.0.1',
                'port' => 1883,
                'prefix' => '',
                'qos' => 0,
                'retain' => false,
                'auth' => false,
            ], $config),
        ]);
    }

    protected function getProtectedProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
