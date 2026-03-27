<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException;
use enzolarosa\MqttBroadcast\Factories\MqttClientFactory;
use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;
use Illuminate\Support\Facades\Queue;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\MqttClient;

beforeEach(function () {
    // Mock MqttClientFactory to avoid real MQTT connections
    $this->mockFactory = Mockery::mock(MqttClientFactory::class);
    $this->mockClient = Mockery::mock(MqttClient::class);

    $this->app->instance(MqttClientFactory::class, $this->mockFactory);
});

afterEach(function () {
    Mockery::close();
});

/**
 * ============================================================================
 * CONSTRUCTION & QUEUE CONFIGURATION TESTS
 * ============================================================================
 */

/**
 * CORE TEST 1: Job is constructed with correct properties
 */
test('job is constructed with all required properties', function () {
    $job = new MqttMessageJob(
        topic: 'sensors/temperature',
        message: '{"value": 25.5}',
        broker: 'production',
        qos: 1,
        cleanSession: false
    );

    expect($job)->toBeInstanceOf(MqttMessageJob::class);
});

/**
 * CORE TEST 2: Job uses queue configuration from config
 */
test('job uses queue name from config', function () {
    config(['mqtt-broadcast.queue.name' => 'mqtt-messages']);
    config(['mqtt-broadcast.queue.connection' => null]);

    Queue::fake();

    $job = new MqttMessageJob(
        topic: 'test/topic',
        message: 'test message'
    );

    // Verify queue was set via onQueue()
    expect($job->queue)->toBe('mqtt-messages');
});

/**
 * CORE TEST 3: Job uses connection configuration from config
 */
test('job uses queue connection from config', function () {
    config(['mqtt-broadcast.queue.name' => null]);
    config(['mqtt-broadcast.queue.connection' => 'redis']);

    Queue::fake();

    $job = new MqttMessageJob(
        topic: 'test/topic',
        message: 'test message'
    );

    // Verify connection was set via onConnection()
    expect($job->connection)->toBe('redis');
});

/**
 * CORE TEST 4: Job uses both queue name and connection
 */
test('job uses both queue name and connection from config', function () {
    config(['mqtt-broadcast.queue.name' => 'mqtt-high-priority']);
    config(['mqtt-broadcast.queue.connection' => 'sqs']);

    Queue::fake();

    $job = new MqttMessageJob(
        topic: 'alerts/critical',
        message: 'Critical alert'
    );

    expect($job->queue)->toBe('mqtt-high-priority')
        ->and($job->connection)->toBe('sqs');
});

/**
 * ============================================================================
 * SUCCESSFUL MESSAGE PUBLISH TESTS
 * ============================================================================
 */

/**
 * CORE TEST 5: Job successfully publishes string message
 */
test('job publishes string message successfully', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    // Setup mock expectations
    $this->mockFactory->shouldReceive('create')
        ->once()
        ->with('default', Mockery::type('string'))
        ->andReturn($this->mockClient);

    $this->mockFactory->shouldReceive('getConnectionSettings')
        ->once()
        ->with('default', true)
        ->andReturn([
            'settings' => null,
            'cleanSession' => true,
        ]);

    $this->mockClient->shouldReceive('isConnected')
        ->once()
        ->andReturn(false);

    $this->mockClient->shouldReceive('connect')
        ->once();

    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('sensors/temperature', '25.5', 0, false);

    $this->mockClient->shouldReceive('isConnected')
        ->once()
        ->andReturn(true);

    $this->mockClient->shouldReceive('disconnect')
        ->once();

    // Execute job
    $job = new MqttMessageJob(
        topic: 'sensors/temperature',
        message: '25.5',
        broker: 'default'
    );

    $job->handle();
});

/**
 * CORE TEST 6: Job skips connect when already connected
 */
test('job skips connect when client already connected', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')
        ->once()
        ->with('default', Mockery::type('string'))
        ->andReturn($this->mockClient);

    $this->mockFactory->shouldReceive('getConnectionSettings')
        ->once()
        ->andReturn([
            'settings' => null,
            'cleanSession' => true,
        ]);

    // Already connected - should NOT call connect()
    $this->mockClient->shouldReceive('isConnected')
        ->once()
        ->andReturn(true);

    $this->mockClient->shouldNotReceive('connect');

    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/topic', 'message', 0, false);

    $this->mockClient->shouldReceive('isConnected')
        ->once()
        ->andReturn(true);

    $this->mockClient->shouldReceive('disconnect')
        ->once();

    $job = new MqttMessageJob('test/topic', 'message');
    $job->handle();
});

/**
 * ============================================================================
 * JSON ENCODING TESTS
 * ============================================================================
 */

/**
 * CORE TEST 7: Job converts array message to JSON
 */
test('job converts array message to JSON', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Expect JSON-encoded array
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('sensors/data', '{"temperature":25.5,"humidity":60}', 0, false);

    $job = new MqttMessageJob(
        topic: 'sensors/data',
        message: ['temperature' => 25.5, 'humidity' => 60]
    );

    $job->handle();
});

/**
 * CORE TEST 8: Job converts object message to JSON
 */
test('job converts object message to JSON', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    $object = (object) ['sensor_id' => 'temp-001', 'value' => 22.5];

    // Expect JSON-encoded object
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('sensors/object', '{"sensor_id":"temp-001","value":22.5}', 0, false);

    $job = new MqttMessageJob('sensors/object', $object);
    $job->handle();
});

/**
 * CORE TEST 9: Job leaves string message unchanged
 */
test('job leaves string message unchanged', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Expect exact string (not re-encoded)
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('alerts/text', 'System temperature critical!', 0, false);

    $job = new MqttMessageJob('alerts/text', 'System temperature critical!');
    $job->handle();
});

/**
 * ============================================================================
 * CONFIGURATION ERROR HANDLING (FAIL WITHOUT RETRY)
 * ============================================================================
 */

/**
 * CRITICAL TEST 10: Job fails immediately on configuration error
 *
 * Configuration errors should NOT retry - config won't fix itself
 */
test('job fails without retry on configuration error', function () {
    // Setup: Invalid broker config (will throw MqttBroadcastException)
    $this->mockFactory->shouldReceive('create')
        ->once()
        ->with('invalid-broker', Mockery::type('string'))
        ->andThrow(new MqttBroadcastException('Connection [invalid-broker] not configured'));

    // Should NOT attempt getConnectionSettings or any other operation
    $this->mockFactory->shouldNotReceive('getConnectionSettings');
    $this->mockClient->shouldNotReceive('connect');
    $this->mockClient->shouldNotReceive('publish');

    $job = new MqttMessageJob('test/topic', 'message', 'invalid-broker');

    // Job should catch exception and call fail()
    // We can't directly verify fail() was called, but we can verify
    // the exception was caught and NOT re-thrown
    try {
        $job->handle();
        // If we reach here, exception was caught (correct behavior)
        expect(true)->toBeTrue();
    } catch (MqttBroadcastException $e) {
        // Should NOT reach here - exception should be caught
        expect(false)->toBeTrue('MqttBroadcastException should be caught and not re-thrown');
    }
});

/**
 * CRITICAL TEST 11: Job fails when connection host missing
 */
test('job fails when connection host is missing', function () {
    config([
        'mqtt-broadcast.connections.broken' => [
            // Missing host
            'port' => 1883,
        ],
    ]);

    $this->mockFactory->shouldReceive('create')
        ->once()
        ->with('broken', Mockery::type('string'))
        ->andThrow(new MqttBroadcastException('Host is required for connection [broken]'));

    $job = new MqttMessageJob('test/topic', 'message', 'broken');

    // Should catch and not re-throw
    $job->handle();

    expect(true)->toBeTrue('Exception was caught correctly');
});

/**
 * ============================================================================
 * NETWORK ERROR HANDLING (WITH RETRY)
 * ============================================================================
 */

/**
 * CRITICAL TEST 12: Job retries on connection failure
 *
 * Network errors SHOULD retry - transient issues may resolve
 */
test('job allows retry on network connection failure', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '192.168.1.100',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
        ],
    ]);

    $this->mockFactory->shouldReceive('create')
        ->once()
        ->andReturn($this->mockClient);

    $this->mockFactory->shouldReceive('getConnectionSettings')
        ->once()
        ->andReturn([
            'settings' => null,
            'cleanSession' => true,
        ]);

    // First call: check if needs connect (false)
    // Second call: in finally block to check if needs disconnect (false)
    $this->mockClient->shouldReceive('isConnected')
        ->twice()
        ->andReturn(false);

    // Network connection failure
    $this->mockClient->shouldReceive('connect')
        ->once()
        ->andThrow(new ConnectingToBrokerFailedException(1000, 'Connection refused'));

    $job = new MqttMessageJob('test/topic', 'message');

    // Exception should propagate (allowing queue to retry)
    expect(fn() => $job->handle())
        ->toThrow(ConnectingToBrokerFailedException::class);
});

/**
 * CRITICAL TEST 13: Job retries on data transfer failure
 */
test('job allows retry on data transfer failure', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();

    // Data transfer fails during publish
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->andThrow(new DataTransferException(0101, 'Connection lost'));

    $this->mockClient->shouldReceive('disconnect')->once();

    $job = new MqttMessageJob('test/topic', 'message');

    // Exception should propagate (allowing queue to retry)
    expect(fn() => $job->handle())
        ->toThrow(DataTransferException::class);
});

/**
 * ============================================================================
 * QOS & RETAIN CONFIGURATION TESTS
 * ============================================================================
 */

/**
 * CORE TEST 14: Job uses custom QoS when provided
 */
test('job uses custom qos when provided', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0, // Config default
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Should use QoS 2 (override)
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('important/data', 'critical message', 2, false);

    $job = new MqttMessageJob(
        topic: 'important/data',
        message: 'critical message',
        broker: 'default',
        qos: 2 // Override config
    );

    $job->handle();
});

/**
 * CORE TEST 15: Job uses QoS from config when not provided
 */
test('job uses qos from config when not provided', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 1, // Config QoS
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Should use QoS 1 from config
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/topic', 'message', 1, false);

    $job = new MqttMessageJob('test/topic', 'message');
    $job->handle();
});

/**
 * CORE TEST 16: Job uses retain flag from config
 */
test('job uses retain flag from config', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => true, // Retain enabled
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Should use retain = true from config
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('status/last-known', 'online', 0, true);

    $job = new MqttMessageJob('status/last-known', 'online');
    $job->handle();
});

/**
 * ============================================================================
 * DISCONNECT IN FINALLY TESTS
 * ============================================================================
 */

/**
 * CRITICAL TEST 17: Job disconnects even when publish fails
 */
test('job disconnects even when publish fails', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();

    // Publish fails
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->andThrow(new DataTransferException(0101, 'Publish failed'));

    // Should STILL disconnect (finally block)
    $this->mockClient->shouldReceive('disconnect')
        ->once();

    $job = new MqttMessageJob('test/topic', 'message');

    try {
        $job->handle();
    } catch (DataTransferException $e) {
        // Expected exception
    }

    // Mockery will verify disconnect was called
});

/**
 * CORE TEST 18: Job only disconnects if connected
 */
test('job only disconnects if client is connected', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->once()->andReturn(false);

    // Connect fails
    $this->mockClient->shouldReceive('connect')
        ->once()
        ->andThrow(new ConnectingToBrokerFailedException(1000, 'Connection failed'));

    // In finally: isConnected returns false, so NO disconnect
    $this->mockClient->shouldReceive('isConnected')->once()->andReturn(false);
    $this->mockClient->shouldNotReceive('disconnect');

    $job = new MqttMessageJob('test/topic', 'message');

    try {
        $job->handle();
    } catch (ConnectingToBrokerFailedException $e) {
        // Expected exception
    }

    // Mockery will verify disconnect was NOT called
});

/**
 * ============================================================================
 * TOPIC PREFIX TESTS
 * ============================================================================
 */

/**
 * CORE TEST 19: Job applies topic prefix from getTopic helper
 */
test('job applies topic prefix from config', function () {
    config([
        'mqtt-broadcast.connections.production' => [
            'host' => 'mqtt.example.com',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'prefix' => 'app/v1/',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Should publish to prefixed topic
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('app/v1/sensors/temp', 'message', 0, false);

    $job = new MqttMessageJob('sensors/temp', 'message', 'production');
    $job->handle();
});

/**
 * ============================================================================
 * CLEAN SESSION TESTS
 * ============================================================================
 */

/**
 * CORE TEST 20: Job uses custom cleanSession value
 */
test('job uses custom clean session value', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'topic_prefix' => '',
        ],
    ]);

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);

    // Verify cleanSession parameter passed to factory
    $this->mockFactory->shouldReceive('getConnectionSettings')
        ->once()
        ->with('default', false) // cleanSession = false
        ->andReturn([
            'settings' => null,
            'cleanSession' => false,
        ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('publish')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    $job = new MqttMessageJob(
        topic: 'test/topic',
        message: 'message',
        broker: 'default',
        qos: null,
        cleanSession: false // Custom value
    );

    $job->handle();
});
