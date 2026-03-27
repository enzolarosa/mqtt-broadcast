<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Models\BrokerProcess;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    // Create a healthy system for testing
    BrokerProcess::create([
        'name' => 'test-broker',
        'connection' => 'default',
        'pid' => 12345,
        'working' => true,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    (new MasterSupervisorRepository())->update('master', [
        'pid' => 12345,
        'started_at' => now(),
        'memory' => 50 * 1024 * 1024,
        'supervisors_count' => 1,
    ]);
});

// Reset app environment after each test to prevent migrate:rollback from
// asking for confirmation (which would fail on the Mockery partial mock).
afterEach(function () {
    if (isset($this->app)) {
        $this->app['env'] = 'testing';
    }
});

/**
 * ============================================================================
 * AUTHORIZATION MIDDLEWARE TESTS
 * ============================================================================
 */

test('authorize middleware allows access in local environment', function () {
    $this->app['env'] = 'local';

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200);
});

test('authorize middleware denies access in production without gate', function () {
    $this->app['env'] = 'production';

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(403);
});

test('authorize middleware allows access in production with custom gate', function () {
    $this->app['env'] = 'production';

    Gate::define('viewMqttBroadcast', function ($user = null) {
        return true;
    });

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200);
});

test('authorize middleware can check user permissions', function () {
    $this->app['env'] = 'production';

    $user = new \Illuminate\Auth\GenericUser(['email' => 'admin@example.com']);

    Gate::define('viewMqttBroadcast', function ($checkedUser = null) {
        if ($checkedUser === null) {
            return false;
        }

        return in_array($checkedUser->email, ['admin@example.com']);
    });

    $this->actingAs($user);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200);
});

test('authorize middleware denies unauthorized users', function () {
    $this->app['env'] = 'production';

    $user = new \Illuminate\Auth\GenericUser(['email' => 'unauthorized@example.com']);

    Gate::define('viewMqttBroadcast', function ($checkedUser = null) {
        if ($checkedUser === null) {
            return false;
        }

        return in_array($checkedUser->email, ['admin@example.com']);
    });

    $this->actingAs($user);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(403);
});

test('authorize middleware works in staging environment', function () {
    $this->app['env'] = 'staging';

    // No custom gate, should deny
    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(403);

    // With gate that accepts nullable user (required for unauthenticated requests)
    Gate::define('viewMqttBroadcast', fn ($user = null) => true);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200);
});
