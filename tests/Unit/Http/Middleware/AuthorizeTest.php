<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Http\Middleware\Authorize;
use enzolarosa\MqttBroadcast\Models\BrokerProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

    Cache::put('master-supervisor', [
        'pid' => 12345,
        'started_at' => now(),
        'memory' => 50 * 1024 * 1024,
        'supervisors_count' => 1,
    ], 3600);
});

/**
 * ============================================================================
 * AUTHORIZATION MIDDLEWARE TESTS
 * ============================================================================
 */

test('authorize middleware allows access in local environment', function () {
    // Set environment to local
    $this->app['env'] = 'local';

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200); // Not 403
});

test('authorize middleware denies access in production without gate', function () {
    // Set environment to production
    $this->app['env'] = 'production';

    // No custom gate defined, default gate denies

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(403);
});

test('authorize middleware allows access in production with custom gate', function () {
    // Set environment to production
    $this->app['env'] = 'production';

    // Define custom gate that allows access
    Gate::define('viewMqttBroadcast', function ($user = null) {
        return true; // Allow all users
    });

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200); // Allowed
});

test('authorize middleware can check user permissions', function () {
    $this->app['env'] = 'production';

    // Create a test user
    $user = new class
    {
        public $email = 'admin@example.com';
    };

    // Define gate with user check
    Gate::define('viewMqttBroadcast', function ($checkedUser = null) use ($user) {
        if ($checkedUser === null) {
            return false;
        }

        return in_array($checkedUser->email, ['admin@example.com']);
    });

    // Authenticate the user
    $this->actingAs($user);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200);
});

test('authorize middleware denies unauthorized users', function () {
    $this->app['env'] = 'production';

    // Create a test user
    $user = new class
    {
        public $email = 'unauthorized@example.com';
    };

    // Define gate that only allows admin@example.com
    Gate::define('viewMqttBroadcast', function ($checkedUser = null) {
        if ($checkedUser === null) {
            return false;
        }

        return in_array($checkedUser->email, ['admin@example.com']);
    });

    // Authenticate the unauthorized user
    $this->actingAs($user);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(403);
});

test('authorize middleware works in staging environment', function () {
    $this->app['env'] = 'staging';

    // No custom gate, should deny
    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(403);

    // With gate, should allow
    Gate::define('viewMqttBroadcast', fn () => true);

    $response = $this->get('/mqtt-broadcast/api/health');

    $response->assertStatus(200);
});
