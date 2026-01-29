<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Models\BrokerProcess;
use enzolarosa\MqttBroadcast\Models\MqttLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->app['env'] = 'local';
});

test('brokers index returns list of all brokers', function () {
    BrokerProcess::create([
        'name' => 'broker-1',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now()->subHour(),
        'last_heartbeat_at' => now(),
    ]);

    BrokerProcess::create([
        'name' => 'broker-2',
        'connection' => 'secondary',
        'pid' => 12346,
        'working' => false,
        'started_at' => now()->subHours(2),
        'last_heartbeat_at' => now()->subMinutes(5),
    ]);

    $response = $this->get('/mqtt-broadcast/api/brokers');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'connection',
                    'pid',
                    'status',
                    'connection_status',
                    'working',
                    'started_at',
                    'last_heartbeat_at',
                    'last_message_at',
                    'uptime_seconds',
                    'uptime_human',
                    'messages_24h',
                ],
            ],
        ]);
});

test('broker connection status is determined correctly', function () {
    // Connected broker
    $connected = BrokerProcess::create([
        'name' => 'broker-connected',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now()->subHour(),
        'last_heartbeat_at' => now(),
    ]);

    // Idle broker (paused)
    $idle = BrokerProcess::create([
        'name' => 'broker-idle',
        'connection' => 'secondary',
        'pid' => 12346,
        'working' => false,
        'started_at' => now()->subHour(),
        'last_heartbeat_at' => now(),
    ]);

    // Reconnecting broker
    $reconnecting = BrokerProcess::create([
        'name' => 'broker-reconnecting',
        'connection' => 'tertiary',
        'pid' => 12347,
        'working' => true,
        'started_at' => now()->subHour(),
        'last_heartbeat_at' => now()->subMinute(),
    ]);

    // Disconnected broker
    $disconnected = BrokerProcess::create([
        'name' => 'broker-disconnected',
        'connection' => 'quaternary',
        'pid' => 12348,
        'working' => true,
        'started_at' => now()->subHour(),
        'last_heartbeat_at' => now()->subMinutes(10),
    ]);

    $response = $this->get('/mqtt-broadcast/api/brokers');

    $response->assertStatus(200);

    $brokers = $response->json('data');

    expect($brokers[0]['connection_status'])->toBe('connected');
    expect($brokers[1]['connection_status'])->toBe('idle');
    expect($brokers[2]['connection_status'])->toBe('reconnecting');
    expect($brokers[3]['connection_status'])->toBe('disconnected');
});

test('broker index includes message count when logging enabled', function () {
    config(['mqtt-broadcast.logs.enable' => true]);

    BrokerProcess::create([
        'name' => 'broker-1',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now()->subHour(),
        'last_heartbeat_at' => now(),
    ]);

    // Create messages for this broker
    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'test/topic',
        'message' => '{"test": true}',
        'created_at' => now()->subHours(2),
    ]);

    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'test/topic',
        'message' => '{"test": true}',
        'created_at' => now()->subHour(),
    ]);

    $response = $this->get('/mqtt-broadcast/api/brokers');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                [
                    'messages_24h' => 2,
                ],
            ],
        ]);
});

test('broker show returns specific broker details', function () {
    $broker = BrokerProcess::create([
        'name' => 'test-broker',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now()->subHour(),
        'last_heartbeat_at' => now(),
    ]);

    $response = $this->get("/mqtt-broadcast/api/brokers/{$broker->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'connection',
                'pid',
                'status',
                'working',
                'started_at',
                'last_heartbeat_at',
                'uptime_seconds',
                'uptime_human',
                'recent_messages',
            ],
        ])
        ->assertJson([
            'data' => [
                'name' => 'test-broker',
                'connection' => 'default',
            ],
        ]);
});

test('broker show returns 404 for non-existent broker', function () {
    $response = $this->get('/mqtt-broadcast/api/brokers/999');

    $response->assertStatus(404)
        ->assertJson([
            'error' => 'Broker not found',
        ]);
});

test('broker uptime is formatted correctly', function () {
    // Less than 1 minute
    $broker1 = BrokerProcess::create([
        'name' => 'broker-1',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now()->subSeconds(30),
        'last_heartbeat_at' => now(),
    ]);

    // 5 minutes
    $broker2 = BrokerProcess::create([
        'name' => 'broker-2',
        'connection' => 'secondary',
        'pid' => 12346,
        'working' => true,
        'started_at' => now()->subMinutes(5),
        'last_heartbeat_at' => now(),
    ]);

    // 2 hours 30 minutes
    $broker3 = BrokerProcess::create([
        'name' => 'broker-3',
        'connection' => 'tertiary',
        'pid' => 12347,
        'working' => true,
        'started_at' => now()->subHours(2)->subMinutes(30),
        'last_heartbeat_at' => now(),
    ]);

    $response = $this->get('/mqtt-broadcast/api/brokers');

    $response->assertStatus(200);

    $brokers = $response->json('data');

    expect($brokers[0]['uptime_human'])->toBe('30s');
    expect($brokers[1]['uptime_human'])->toBe('5m');
    expect($brokers[2]['uptime_human'])->toBe('2h 30m');
});
