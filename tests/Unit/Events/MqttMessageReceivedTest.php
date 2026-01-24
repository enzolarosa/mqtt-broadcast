<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Support\Facades\Event;

describe('MqttMessageReceived Event', function () {
    it('creates event with all properties', function () {
        $event = new MqttMessageReceived(
            topic: 'sensors/temperature',
            message: '{"value": 25.5}',
            broker: 'production',
            pid: 12345
        );

        expect($event)->toBeInstanceOf(MqttMessageReceived::class);
    });

    it('returns correct topic via getter', function () {
        $event = new MqttMessageReceived(
            topic: 'sensors/temperature',
            message: 'test',
        );

        expect($event->getTopic())->toBe('sensors/temperature');
    });

    it('returns correct message via getter', function () {
        $event = new MqttMessageReceived(
            topic: 'test/topic',
            message: '{"data": "value"}',
        );

        expect($event->getMessage())->toBe('{"data": "value"}');
    });

    it('returns correct broker via getter', function () {
        $event = new MqttMessageReceived(
            topic: 'test/topic',
            message: 'test',
            broker: 'custom-broker'
        );

        expect($event->getBroker())->toBe('custom-broker');
    });

    it('returns correct pid via getter', function () {
        $event = new MqttMessageReceived(
            topic: 'test/topic',
            message: 'test',
            pid: 99999
        );

        expect($event->getPid())->toBe(99999);
    });

    it('uses default broker value when not provided', function () {
        $event = new MqttMessageReceived(
            topic: 'test/topic',
            message: 'test'
        );

        expect($event->getBroker())->toBe('local');
    });

    it('uses null as default pid when not provided', function () {
        $event = new MqttMessageReceived(
            topic: 'test/topic',
            message: 'test'
        );

        expect($event->getPid())->toBeNull();
    });

    it('properties are readonly and cannot be modified via reflection', function () {
        $event = new MqttMessageReceived(
            topic: 'original/topic',
            message: 'original message'
        );

        $reflection = new ReflectionClass($event);
        $topicProperty = $reflection->getProperty('topic');

        // Verify property is readonly
        expect($topicProperty->isReadOnly())->toBeTrue();
    });

    it('all four properties are readonly', function () {
        $event = new MqttMessageReceived(
            topic: 'test/topic',
            message: 'test',
            broker: 'test-broker',
            pid: 123
        );

        $reflection = new ReflectionClass($event);

        $properties = ['topic', 'message', 'broker', 'pid'];

        foreach ($properties as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            expect($property->isReadOnly())->toBeTrue(
                "Property '{$propertyName}' should be readonly"
            );
        }
    });

    it('can be serialized and unserialized correctly', function () {
        $original = new MqttMessageReceived(
            topic: 'sensors/humidity',
            message: '{"humidity": 65}',
            broker: 'iot-broker',
            pid: 54321
        );

        $serialized = serialize($original);
        $unserialized = unserialize($serialized);

        expect($unserialized->getTopic())->toBe('sensors/humidity')
            ->and($unserialized->getMessage())->toBe('{"humidity": 65}')
            ->and($unserialized->getBroker())->toBe('iot-broker')
            ->and($unserialized->getPid())->toBe(54321);
    });

    it('handles complex JSON messages', function () {
        $complexJson = json_encode([
            'sensor_id' => 'temp-001',
            'readings' => [
                'temperature' => 22.5,
                'humidity' => 45,
                'pressure' => 1013.25,
            ],
            'metadata' => [
                'location' => 'Living Room',
                'timestamp' => '2026-01-24T10:30:00Z',
            ],
        ]);

        $event = new MqttMessageReceived(
            topic: 'home/sensors/temp-001',
            message: $complexJson
        );

        expect($event->getMessage())->toBe($complexJson);

        $decoded = json_decode($event->getMessage(), true);
        expect($decoded['sensor_id'])->toBe('temp-001')
            ->and($decoded['readings']['temperature'])->toBe(22.5);
    });

    it('handles empty string message', function () {
        $event = new MqttMessageReceived(
            topic: 'test/topic',
            message: ''
        );

        expect($event->getMessage())->toBe('');
    });

    it('handles plain text messages', function () {
        $event = new MqttMessageReceived(
            topic: 'alerts/warning',
            message: 'System temperature critical!'
        );

        expect($event->getMessage())->toBe('System temperature critical!');
    });

    it('handles unicode characters in message', function () {
        $unicodeMessage = '你好世界 🌍 Привет мир';

        $event = new MqttMessageReceived(
            topic: 'chat/messages',
            message: $unicodeMessage
        );

        expect($event->getMessage())->toBe($unicodeMessage);
    });

    it('handles special characters in topic', function () {
        $event = new MqttMessageReceived(
            topic: 'home/+/sensors/#',
            message: 'test'
        );

        expect($event->getTopic())->toBe('home/+/sensors/#');
    });

    it('handles very long topic names', function () {
        $longTopic = str_repeat('level/', 100).'sensor';

        $event = new MqttMessageReceived(
            topic: $longTopic,
            message: 'test'
        );

        expect($event->getTopic())->toBe($longTopic);
    });

    it('handles negative pid values', function () {
        // Edge case: in some systems PID could theoretically be negative
        $event = new MqttMessageReceived(
            topic: 'test/topic',
            message: 'test',
            pid: -1
        );

        expect($event->getPid())->toBe(-1);
    });

    it('does not have setter methods', function () {
        $reflection = new ReflectionClass(MqttMessageReceived::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        $setterMethods = array_filter($methods, function ($method) {
            return str_starts_with($method->getName(), 'set');
        });

        expect($setterMethods)->toBeEmpty();
    });

    it('has only getter methods and constructor', function () {
        $reflection = new ReflectionClass(MqttMessageReceived::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        $publicMethodNames = array_map(fn ($method) => $method->getName(), $methods);

        // Should only have constructor and 4 getters
        $expectedMethods = ['__construct', 'getTopic', 'getMessage', 'getBroker', 'getPid'];

        // Filter out trait methods (Dispatchable, SerializesModels)
        $classMethods = array_filter($publicMethodNames, function ($methodName) use ($reflection) {
            $method = $reflection->getMethod($methodName);

            return $method->getDeclaringClass()->getName() === MqttMessageReceived::class;
        });

        expect($classMethods)->toBe($expectedMethods);
    });

    it('can be dispatched as event', function () {
        Event::fake();

        $event = new MqttMessageReceived(
            topic: 'test/topic',
            message: 'test message'
        );

        $event->dispatch();

        Event::assertDispatched(MqttMessageReceived::class);
    });

    it('event properties remain unchanged after dispatch', function () {
        Event::fake();

        $event = new MqttMessageReceived(
            topic: 'sensors/temp',
            message: '{"value": 22.5}',
            broker: 'local',
            pid: 12345
        );

        $event->dispatch();

        // Verify properties are still the same after dispatch
        expect($event->getTopic())->toBe('sensors/temp')
            ->and($event->getMessage())->toBe('{"value": 22.5}')
            ->and($event->getBroker())->toBe('local')
            ->and($event->getPid())->toBe(12345);
    });
});
