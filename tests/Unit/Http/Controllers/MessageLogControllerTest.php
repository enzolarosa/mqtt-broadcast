<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Models\MqttLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->app['env'] = 'local';
});

test('messages endpoint returns empty when logging disabled', function () {
    config(['mqtt-broadcast.logs.enable' => false]);

    $response = $this->get('/mqtt-broadcast/api/messages');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [],
            'meta' => [
                'logging_enabled' => false,
            ],
        ]);
});

test('messages endpoint returns recent messages when logging enabled', function () {
    config(['mqtt-broadcast.logs.enable' => true]);

    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'sensors/temperature',
        'message' => '{"temp": 22.5}',
        'created_at' => now()->subMinutes(5),
    ]);

    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'sensors/humidity',
        'message' => '{"humidity": 65}',
        'created_at' => now()->subMinutes(2),
    ]);

    $response = $this->get('/mqtt-broadcast/api/messages');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'broker',
                    'topic',
                    'message',
                    'message_preview',
                    'created_at',
                    'created_at_human',
                ],
            ],
            'meta' => [
                'logging_enabled',
                'count',
                'limit',
            ],
        ]);
});

test('messages endpoint filters by broker', function () {
    config(['mqtt-broadcast.logs.enable' => true]);

    MqttLogger::create([
        'broker' => 'broker1',
        'topic' => 'test/topic',
        'message' => 'message 1',
        'created_at' => now(),
    ]);

    MqttLogger::create([
        'broker' => 'broker2',
        'topic' => 'test/topic',
        'message' => 'message 2',
        'created_at' => now(),
    ]);

    $response = $this->get('/mqtt-broadcast/api/messages?broker=broker1');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJson([
            'data' => [
                ['broker' => 'broker1'],
            ],
        ]);
});

test('messages endpoint filters by topic', function () {
    config(['mqtt-broadcast.logs.enable' => true]);

    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'sensors/temperature',
        'message' => 'temp data',
        'created_at' => now(),
    ]);

    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'sensors/humidity',
        'message' => 'humidity data',
        'created_at' => now(),
    ]);

    $response = $this->get('/mqtt-broadcast/api/messages?topic=temperature');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJson([
            'data' => [
                ['topic' => 'sensors/temperature'],
            ],
        ]);
});

test('messages endpoint respects limit parameter', function () {
    config(['mqtt-broadcast.logs.enable' => true]);

    // Create 50 messages
    for ($i = 0; $i < 50; $i++) {
        MqttLogger::create([
            'broker' => 'default',
            'topic' => 'test/topic',
            'message' => "message {$i}",
            'created_at' => now()->subMinutes($i),
        ]);
    }

    // Default limit is 30
    $response = $this->get('/mqtt-broadcast/api/messages');
    $response->assertStatus(200)->assertJsonCount(30, 'data');

    // Custom limit
    $response = $this->get('/mqtt-broadcast/api/messages?limit=10');
    $response->assertStatus(200)->assertJsonCount(10, 'data');

    // Max limit is 100
    $response = $this->get('/mqtt-broadcast/api/messages?limit=200');
    $response->assertStatus(200)->assertJsonCount(50, 'data'); // Only 50 exist
});

test('message show returns individual message details', function () {
    config(['mqtt-broadcast.logs.enable' => true]);

    $message = MqttLogger::create([
        'broker' => 'default',
        'topic' => 'test/topic',
        'message' => '{"test": "data", "value": 123}',
        'created_at' => now(),
    ]);

    $response = $this->get("/mqtt-broadcast/api/messages/{$message->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'broker',
                'topic',
                'message',
                'is_json',
                'message_parsed',
                'created_at',
                'created_at_human',
            ],
        ])
        ->assertJson([
            'data' => [
                'is_json' => true,
                'message_parsed' => [
                    'test' => 'data',
                    'value' => 123,
                ],
            ],
        ]);
});

test('topics endpoint returns active topics with counts', function () {
    config(['mqtt-broadcast.logs.enable' => true]);

    // Create messages with different topics
    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'sensors/temperature',
        'message' => 'data',
        'created_at' => now(),
    ]);

    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'sensors/temperature',
        'message' => 'data',
        'created_at' => now(),
    ]);

    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'sensors/humidity',
        'message' => 'data',
        'created_at' => now(),
    ]);

    $response = $this->get('/mqtt-broadcast/api/topics');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'topic',
                    'count',
                ],
            ],
        ]);

    $topics = $response->json('data');

    // Should be ordered by count desc
    expect($topics[0]['topic'])->toBe('sensors/temperature');
    expect($topics[0]['count'])->toBe(2);
    expect($topics[1]['topic'])->toBe('sensors/humidity');
    expect($topics[1]['count'])->toBe(1);
});
