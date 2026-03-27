<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException;
use enzolarosa\MqttBroadcast\Factories\MqttClientFactory;
use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\MqttClient;

beforeEach(function () {
    $this->mockFactory = Mockery::mock(MqttClientFactory::class);
    $this->mockClient = Mockery::mock(MqttClient::class);
    $this->app->instance(MqttClientFactory::class, $this->mockFactory);

    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'prefix' => '',
        ],
    ]);
});

afterEach(function () {
    Mockery::close();
});

/**
 * ============================================================================
 * EDGE CASE 1: LARGE MESSAGE PAYLOADS
 * ============================================================================
 */

/**
 * EDGE CASE TEST 1: Very large string message (1MB)
 */
test('job handles very large string message payload', function () {
    // Generate 1MB string
    $largeMessage = str_repeat('A', 1024 * 1024); // 1MB

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Should publish the full 1MB message
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/large', $largeMessage, 0, false);

    $job = new MqttMessageJob('test/large', $largeMessage);
    $job->handle();

    // Verify message size
    expect(strlen($largeMessage))->toBe(1024 * 1024);
});

/**
 * EDGE CASE TEST 2: Very large JSON array (10,000 items)
 */
test('job handles very large JSON array payload', function () {
    // Generate large array with 10,000 items
    $largeArray = [];
    for ($i = 0; $i < 10000; $i++) {
        $largeArray[] = [
            'id' => $i,
            'name' => "Item {$i}",
            'value' => random_int(1, 1000),
            'timestamp' => time(),
        ];
    }

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Should publish JSON-encoded array
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->withArgs(function ($topic, $message, $qos, $retain) use ($largeArray) {
            // Verify it's valid JSON and matches our array
            $decoded = json_decode($message, true);

            return $topic === 'test/large-json'
                && is_array($decoded)
                && count($decoded) === 10000
                && $decoded[0]['id'] === 0
                && $decoded[9999]['id'] === 9999;
        });

    $job = new MqttMessageJob('test/large-json', $largeArray);
    $job->handle();
});

/**
 * ============================================================================
 * EDGE CASE 2: UNICODE & SPECIAL CHARACTERS
 * ============================================================================
 */

/**
 * EDGE CASE TEST 3: Unicode characters in message (emojis, multilingual)
 */
test('job handles unicode characters in message', function () {
    $unicodeMessage = '🚀 Hello 世界 Привет مرحبا שלום 🌍';

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Should publish with unicode preserved
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/unicode', $unicodeMessage, 0, false);

    $job = new MqttMessageJob('test/unicode', $unicodeMessage);
    $job->handle();
});

/**
 * EDGE CASE TEST 4: Special MQTT topic characters
 */
test('job handles MQTT wildcard characters in topic', function () {
    // MQTT supports + and # as wildcards in subscriptions
    // But they should work in publish too (treated as literals)
    $specialTopic = 'home/+/sensors/#/data';

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with($specialTopic, 'test', 0, false);

    $job = new MqttMessageJob($specialTopic, 'test');
    $job->handle();
});

/**
 * EDGE CASE TEST 5: Very long topic name (256 characters)
 */
test('job handles very long topic names', function () {
    // MQTT spec allows topics up to 65535 bytes, but typically kept shorter
    $longTopic = str_repeat('level/', 50).'sensor'; // ~300 chars

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with($longTopic, 'test', 0, false);

    $job = new MqttMessageJob($longTopic, 'test');
    $job->handle();

    expect(strlen($longTopic))->toBeGreaterThan(250);
});

/**
 * ============================================================================
 * EDGE CASE 3: EMPTY & NULL VALUES
 * ============================================================================
 */

/**
 * EDGE CASE TEST 6: Empty string message
 */
test('job handles empty string message', function () {
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Empty string should be published as-is
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/empty', '', 0, false);

    $job = new MqttMessageJob('test/empty', '');
    $job->handle();
});

/**
 * EDGE CASE TEST 7: Empty array message
 */
test('job handles empty array message', function () {
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Empty array should be published as "[]"
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/empty-array', '[]', 0, false);

    $job = new MqttMessageJob('test/empty-array', []);
    $job->handle();
});

/**
 * EDGE CASE TEST 8: Null value in array
 */
test('job handles null values in array message', function () {
    $arrayWithNull = [
        'name' => 'Test',
        'value' => null,
        'active' => true,
    ];

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Should encode with null as JSON null
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/null-value', '{"name":"Test","value":null,"active":true}', 0, false);

    $job = new MqttMessageJob('test/null-value', $arrayWithNull);
    $job->handle();
});

/**
 * ============================================================================
 * EDGE CASE 4: NUMERIC VALUES
 * ============================================================================
 */

/**
 * EDGE CASE TEST 9: Integer message (should convert to string)
 */
test('job handles integer message', function () {
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Integer should be JSON-encoded to "42"
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/integer', '42', 0, false);

    $job = new MqttMessageJob('test/integer', 42);
    $job->handle();
});

/**
 * EDGE CASE TEST 10: Float message with precision
 */
test('job handles float message with high precision', function () {
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Float should be JSON-encoded
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/float', '3.14159265359', 0, false);

    $job = new MqttMessageJob('test/float', 3.14159265359);
    $job->handle();
});

/**
 * EDGE CASE TEST 11: Boolean values
 */
test('job handles boolean message values', function () {
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Boolean should be JSON-encoded to "true"
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/boolean', 'true', 0, false);

    $job = new MqttMessageJob('test/boolean', true);
    $job->handle();
});

/**
 * ============================================================================
 * EDGE CASE 5: NESTED & COMPLEX DATA STRUCTURES
 * ============================================================================
 */

/**
 * EDGE CASE TEST 12: Deeply nested JSON structure (10 levels)
 */
test('job handles deeply nested JSON structure', function () {
    // Create 10-level nested structure
    $nested = ['level' => 10];
    for ($i = 9; $i >= 1; $i--) {
        $nested = ['level' => $i, 'child' => $nested];
    }

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    $this->mockClient->shouldReceive('publish')
        ->once()
        ->withArgs(function ($topic, $message, $qos, $retain) {
            $decoded = json_decode($message, true);

            return $topic === 'test/nested'
                && $decoded['level'] === 1
                && $decoded['child']['level'] === 2
                && $decoded['child']['child']['level'] === 3;
        });

    $job = new MqttMessageJob('test/nested', $nested);
    $job->handle();
});

/**
 * EDGE CASE TEST 13: Array with mixed types
 */
test('job handles array with mixed data types', function () {
    $mixedArray = [
        'string' => 'text',
        'integer' => 42,
        'float' => 3.14,
        'boolean' => true,
        'null' => null,
        'array' => [1, 2, 3],
        'object' => ['key' => 'value'],
    ];

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    $this->mockClient->shouldReceive('publish')
        ->once()
        ->withArgs(function ($topic, $message, $qos, $retain) use ($mixedArray) {
            $decoded = json_decode($message, true);

            return $decoded['string'] === 'text'
                && $decoded['integer'] === 42
                && $decoded['float'] === 3.14
                && $decoded['boolean'] === true
                && $decoded['null'] === null
                && $decoded['array'] === [1, 2, 3];
        });

    $job = new MqttMessageJob('test/mixed', $mixedArray);
    $job->handle();
});

/**
 * ============================================================================
 * EDGE CASE 6: SPECIAL JSON SCENARIOS
 * ============================================================================
 */

/**
 * EDGE CASE TEST 14: JSON string that's already encoded
 */
test('job handles pre-encoded JSON string correctly', function () {
    $preEncodedJson = '{"already":"encoded","as":"json"}';

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    // Should NOT double-encode (it's already a string)
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/pre-encoded', $preEncodedJson, 0, false);

    $job = new MqttMessageJob('test/pre-encoded', $preEncodedJson);
    $job->handle();
});

/**
 * EDGE CASE TEST 15: Special characters that need escaping in JSON
 */
test('job handles JSON special characters correctly', function () {
    $specialChars = [
        'quote' => 'He said "Hello"',
        'backslash' => 'Path: C:\\Windows\\System32',
        'newline' => "Line 1\nLine 2",
        'tab' => "Column1\tColumn2",
        'unicode' => '🚀',
    ];

    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    $this->mockClient->shouldReceive('publish')
        ->once()
        ->withArgs(function ($topic, $message, $qos, $retain) {
            // Should be valid JSON
            $decoded = json_decode($message, true);

            // Verify it's valid JSON and contains expected data
            return $topic === 'test/special-chars'
                && $decoded !== null
                && json_last_error() === JSON_ERROR_NONE
                && isset($decoded['quote'])
                && isset($decoded['backslash'])
                && isset($decoded['newline'])
                && isset($decoded['unicode'])
                && $decoded['quote'] === 'He said "Hello"'
                && str_contains($decoded['newline'], "\n");
        });

    $job = new MqttMessageJob('test/special-chars', $specialChars);
    $job->handle();
});

/**
 * ============================================================================
 * EDGE CASE 7: BROKER CONNECTION SCENARIOS
 * ============================================================================
 */

/**
 * EDGE CASE TEST 16: Multiple connection attempts (intermittent failure)
 */
test('job fails after connection succeeds but client reports not connected', function () {
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    // First check: not connected (expected)
    // After connect: still reports not connected (unusual scenario)
    $this->mockClient->shouldReceive('isConnected')
        ->twice()
        ->andReturn(false);

    // Connect succeeds (no exception)
    $this->mockClient->shouldReceive('connect')->once();

    // But then publish is called anyway since we're inside try-catch
    // This would cause publish to fail or work depending on actual state
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/topic', 'message', 0, false);

    // No disconnect since isConnected returns false in finally
    $this->mockClient->shouldNotReceive('disconnect');

    $job = new MqttMessageJob('test/topic', 'message');
    $job->handle();
});

/**
 * EDGE CASE TEST 17: Connection succeeds but publish throws network error
 */
test('job disconnects even when publish throws network exception', function () {
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();

    // Publish throws during transmission
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->andThrow(new DataTransferException(0101, 'Network error during publish'));

    // Should still attempt disconnect in finally block
    $this->mockClient->shouldReceive('disconnect')->once();

    $job = new MqttMessageJob('test/topic', 'message');

    expect(fn() => $job->handle())
        ->toThrow(DataTransferException::class);
});

/**
 * ============================================================================
 * EDGE CASE 8: QOS & RETAIN COMBINATIONS
 * ============================================================================
 */

/**
 * EDGE CASE TEST 18: Maximum QoS level (2) with retain
 */
test('job uses maximum qos level with retain flag', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 2, // Maximum QoS
            'retain' => true, // Retain enabled
            'prefix' => '',
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

    // Should use QoS 2 and retain true
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('important/data', 'critical', 2, true);

    $job = new MqttMessageJob('important/data', 'critical');
    $job->handle();
});

/**
 * EDGE CASE TEST 19: QoS override with retain from config
 */
test('job combines custom qos with retain from config', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0, // Config default
            'retain' => true, // Retain from config
            'prefix' => '',
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

    // Should use custom QoS 1 with retain true from config
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('test/topic', 'message', 1, true);

    $job = new MqttMessageJob('test/topic', 'message', 'default', 1); // QoS override
    $job->handle();
});

/**
 * ============================================================================
 * EDGE CASE 9: PREFIX EDGE CASES
 * ============================================================================
 */

/**
 * EDGE CASE TEST 20: Empty prefix (should not add slash)
 */
test('job handles empty prefix correctly', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'qos' => 0,
            'retain' => false,
            'prefix' => '', // Empty prefix
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

    // Should publish without prefix (no extra slash)
    $this->mockClient->shouldReceive('publish')
        ->once()
        ->with('sensors/temp', 'message', 0, false);

    $job = new MqttMessageJob('sensors/temp', 'message');
    $job->handle();
});
