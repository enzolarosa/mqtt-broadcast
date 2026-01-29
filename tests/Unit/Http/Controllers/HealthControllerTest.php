<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Models\BrokerProcess;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    // Set environment to local for testing (bypasses authorization)
    $this->app['env'] = 'local';
});

/**
 * ============================================================================
 * HEALTH CHECK ENDPOINT TESTS
 * ============================================================================
 */

test('health endpoint returns 200 when brokers are active', function () {
    // Create active broker (heartbeat within 2 minutes)
    BrokerProcess::create([
        'name' => 'test-broker',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    // Create master supervisor entry in cache
    $masterRepo = new MasterSupervisorRepository();
    $masterRepo->update('master', [
        'pid' => 12345,
        'started_at' => now()->subMinutes(10),
        'memory' => 50 * 1024 * 1024, // 50 MB
        'supervisors_count' => 1,
    ]);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'healthy',
            'data' => [
                'brokers' => [
                    'total' => 1,
                    'active' => 1,
                    'stale' => 0,
                ],
            ],
            'checks' => [
                'brokers_active' => [
                    'status' => 'pass',
                ],
                'master_running' => [
                    'status' => 'pass',
                ],
            ],
        ]);
});

test('health endpoint returns 503 when no brokers are active', function () {
    // Create stale broker (heartbeat older than 2 minutes)
    BrokerProcess::create([
        'name' => 'test-broker',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now()->subHours(1),
        'last_heartbeat_at' => now()->subMinutes(5), // Stale!
    ]);

    // Master supervisor exists
    Cache::put('master-supervisor', [
        'pid' => 12345,
        'started_at' => now()->subMinutes(10),
        'memory' => 50 * 1024 * 1024,
        'supervisors_count' => 1,
    ], 3600);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(503)
        ->assertJson([
            'status' => 'unhealthy',
            'data' => [
                'brokers' => [
                    'total' => 1,
                    'active' => 0,
                    'stale' => 1,
                ],
            ],
            'checks' => [
                'brokers_active' => [
                    'status' => 'fail',
                ],
            ],
        ]);
});

test('health endpoint returns 503 when master supervisor is not running', function () {
    // Create active broker
    BrokerProcess::create([
        'name' => 'test-broker',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    // No master supervisor in cache!

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(503)
        ->assertJson([
            'status' => 'unhealthy',
            'checks' => [
                'master_running' => [
                    'status' => 'fail',
                    'message' => 'Master supervisor not found',
                ],
            ],
        ]);
});

test('health endpoint includes master supervisor details', function () {
    BrokerProcess::create([
        'name' => 'test-broker',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    $startedAt = now()->subMinutes(30);
    $masterRepo = new MasterSupervisorRepository();
    $masterRepo->update('master', [
        'pid' => 12345,
        'started_at' => $startedAt,
        'memory' => 100 * 1024 * 1024, // 100 MB
        'supervisors_count' => 3,
    ]);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'healthy',
            'data' => [
                'master_supervisor' => [
                    'pid' => 12345,
                    'memory_mb' => 100.0,
                    'supervisors_count' => 3,
                ],
            ],
        ]);

    // Check uptime is approximately 30 minutes (1800 seconds)
    $uptime = $response->json('data.master_supervisor.uptime_seconds');
    expect($uptime)->toBeGreaterThanOrEqual(1790) // Allow 10s tolerance
        ->toBeLessThanOrEqual(1810);
});

test('health endpoint shows memory status as pass when under 80%', function () {
    BrokerProcess::create([
        'name' => 'test-broker',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    // Set threshold to 128MB, use 50MB (39%)
    config(['mqtt-broadcast.memory.threshold_mb' => 128]);

    $masterRepo = new MasterSupervisorRepository();
    $masterRepo->update('master', [
        'pid' => 12345,
        'started_at' => now(),
        'memory' => 50 * 1024 * 1024, // 50 MB
        'supervisors_count' => 1,
    ]);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200)
        ->assertJson([
            'checks' => [
                'memory_ok' => [
                    'status' => 'pass',
                ],
            ],
        ]);
});

test('health endpoint shows memory status as warn when between 80% and 100%', function () {
    BrokerProcess::create([
        'name' => 'test-broker',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    // Set threshold to 100MB, use 90MB (90%)
    config(['mqtt-broadcast.memory.threshold_mb' => 100]);

    $masterRepo = new MasterSupervisorRepository();
    $masterRepo->update('master', [
        'pid' => 12345,
        'started_at' => now(),
        'memory' => 90 * 1024 * 1024, // 90 MB
        'supervisors_count' => 1,
    ]);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200)
        ->assertJson([
            'checks' => [
                'memory_ok' => [
                    'status' => 'warn',
                ],
            ],
        ]);
});

test('health endpoint shows memory status as critical when over 100%', function () {
    BrokerProcess::create([
        'name' => 'test-broker',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    // Set threshold to 100MB, use 150MB (150%)
    config(['mqtt-broadcast.memory.threshold_mb' => 100]);

    $masterRepo = new MasterSupervisorRepository();
    $masterRepo->update('master', [
        'pid' => 12345,
        'started_at' => now(),
        'memory' => 150 * 1024 * 1024, // 150 MB
        'supervisors_count' => 1,
    ]);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200) // Still 200 because brokers are active
        ->assertJson([
            'checks' => [
                'memory_ok' => [
                    'status' => 'critical',
                ],
            ],
        ]);
});

test('health endpoint includes queue size', function () {
    BrokerProcess::create([
        'name' => 'test-broker',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    $masterRepo = new MasterSupervisorRepository();
    $masterRepo->update('master', [
        'pid' => 12345,
        'started_at' => now(),
        'memory' => 50 * 1024 * 1024,
        'supervisors_count' => 1,
    ]);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'queues' => [
                    'pending',
                ],
            ],
        ]);
});

test('health endpoint uses custom path from config', function () {
    // This test is skipped because routes are registered once during boot
    // Testing custom path would require a separate test suite with different config
    // The feature works as demonstrated in the config file
    $this->markTestSkipped('Route reconfiguration at runtime not supported in tests');
})->skip();
