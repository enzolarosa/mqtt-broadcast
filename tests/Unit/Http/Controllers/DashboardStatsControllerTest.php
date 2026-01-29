<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Models\BrokerProcess;
use enzolarosa\MqttBroadcast\Models\MqttLogger;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->app['env'] = 'local';
});

test('stats endpoint returns dashboard statistics', function () {
    // Create test brokers
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
        'working' => true,
        'started_at' => now()->subHours(2),
        'last_heartbeat_at' => now()->subMinutes(5), // Stale
    ]);

    // Create master supervisor
    $masterRepo = new MasterSupervisorRepository();
    $masterRepo->update('master', [
        'pid' => 12345,
        'started_at' => now()->subHour(),
        'memory' => 50 * 1024 * 1024,
        'supervisors_count' => 2,
    ]);

    $response = $this->get('/mqtt-broadcast/api/stats');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'status',
                'brokers' => ['total', 'active', 'stale'],
                'messages' => ['per_minute', 'last_hour', 'last_24h', 'logging_enabled'],
                'queue' => ['pending', 'name'],
                'memory' => ['current_mb', 'threshold_mb', 'usage_percent'],
                'uptime_seconds',
            ],
        ])
        ->assertJson([
            'data' => [
                'status' => 'running',
                'brokers' => [
                    'total' => 2,
                    'active' => 1,
                    'stale' => 1,
                ],
            ],
        ]);
});

test('stats endpoint shows correct memory usage percentage', function () {
    BrokerProcess::create([
        'name' => 'broker-1',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    // 64MB used, 128MB threshold = 50%
    config(['mqtt-broadcast.memory.threshold_mb' => 128]);

    $masterRepo = new MasterSupervisorRepository();
    $masterRepo->update('master', [
        'pid' => 12345,
        'started_at' => now(),
        'memory' => 64 * 1024 * 1024,
        'supervisors_count' => 1,
    ]);

    $response = $this->get('/mqtt-broadcast/api/stats');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'memory' => [
                    'current_mb' => 64.0,
                    'threshold_mb' => 128,
                    'usage_percent' => 50.0,
                ],
            ],
        ]);
});

test('stats endpoint includes message counts when logging enabled', function () {
    config(['mqtt-broadcast.logs.enable' => true]);

    BrokerProcess::create([
        'name' => 'broker-1',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    // Create test messages
    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'test/topic',
        'message' => '{"test": true}',
        'created_at' => now()->subMinutes(30),
    ]);

    MqttLogger::create([
        'broker' => 'default',
        'topic' => 'test/topic',
        'message' => '{"test": true}',
        'created_at' => now()->subMinutes(10),
    ]);

    $masterRepo = new MasterSupervisorRepository();
    $masterRepo->update('master', [
        'pid' => 12345,
        'started_at' => now(),
        'memory' => 50 * 1024 * 1024,
        'supervisors_count' => 1,
    ]);

    $response = $this->get('/mqtt-broadcast/api/stats');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'messages' => [
                    'last_hour' => 2,
                    'logging_enabled' => true,
                ],
            ],
        ]);
});

test('stats endpoint shows stopped status when no active brokers', function () {
    // Create only stale broker
    BrokerProcess::create([
        'name' => 'broker-1',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now()->subHour(),
        'last_heartbeat_at' => now()->subMinutes(10), // Stale
    ]);

    $masterRepo = new MasterSupervisorRepository();
    $masterRepo->update('master', [
        'pid' => 12345,
        'started_at' => now(),
        'memory' => 50 * 1024 * 1024,
        'supervisors_count' => 1,
    ]);

    $response = $this->get('/mqtt-broadcast/api/stats');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'status' => 'stopped',
                'brokers' => [
                    'active' => 0,
                ],
            ],
        ]);
});
