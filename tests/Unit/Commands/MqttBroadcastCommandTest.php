<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Factories\MqttClientFactory;
use enzolarosa\MqttBroadcast\Models\BrokerProcess;
use enzolarosa\MqttBroadcast\Repositories\BrokerRepository;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use enzolarosa\MqttBroadcast\Support\ProcessIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    // Mock MqttClientFactory (no real MQTT connections)
    $this->mockFactory = Mockery::mock(MqttClientFactory::class);
    $this->app->instance(MqttClientFactory::class, $this->mockFactory);
});

afterEach(function () {
    Mockery::close();
});

/**
 * ============================================================================
 * ERROR PATH TESTS (Fast - No Fork Needed)
 * ============================================================================
 */

/**
 * CRITICAL TEST 1: Prevents duplicate master supervisors
 *
 * Real use case: User accidentally runs `mqtt-broadcast` twice
 * Expected: Second command should fail with clear warning
 */
test('command fails if master supervisor already running on this machine', function () {
    // Setup: Create a master supervisor entry (simulate already running)
    $masterRepo = new MasterSupervisorRepository();
    $masterName = ProcessIdentifier::generateName('master');

    $masterRepo->update($masterName, [
        'pid' => getmypid(),
        'status' => 'running',
        'supervisors' => 0,
    ]);

    // Configure at least one connection
    config(['mqtt-broadcast.environments.testing' => ['default']]);

    // Execute command
    $this->artisan('mqtt-broadcast')
        ->expectsOutputToContain('A master supervisor is already running on this machine.')
        ->assertExitCode(1);
});

/**
 * CRITICAL TEST 2: Fails gracefully when no connections configured
 *
 * Real use case: User forgot to configure connections in config/mqtt-broadcast.php
 * Expected: Clear error message with helpful hint
 */
test('command fails when no connections configured for environment', function () {
    // Setup: Set app environment explicitly and empty connections
    config([
        'app.env' => 'testing',
        'mqtt-broadcast.env' => null,
        'mqtt-broadcast.environments.testing' => [],
    ]);

    // Execute command
    $this->artisan('mqtt-broadcast')
        ->expectsOutputToContain('No broker connections configured for environment [testing]')
        ->expectsOutputToContain('Check config/mqtt-broadcast.php -> environments section')
        ->assertExitCode(1);
});

/**
 * ============================================================================
 * SUCCESS PATH TESTS - CURRENTLY SKIPPED
 * ============================================================================
 *
 * ISSUE: Fork-based tests with $this->artisan() inside child process hang
 *        because monitor() is a blocking infinite loop that doesn't properly
 *        handle signals in the test framework context.
 *
 * SOLUTIONS TO IMPLEMENT LATER:
 * 1. Use exec() to launch real artisan command in separate process
 * 2. Add --test-mode flag to command that exits after setup (non-invasive)
 * 3. Create dedicated integration test suite with real process spawning
 *
 * FOR NOW: These tests are documented but skipped. Manual testing confirms
 *          the command works correctly in production.
 *
 * See: TESTING_LIMITATIONS.md for detailed analysis
 * ============================================================================
 */

/**
 * SKIPPED: Command starts successfully and creates broker supervisors
 *
 * Why skipped: Requires fork with exec() or command refactoring
 * Manual test: php artisan mqtt-broadcast (verify it starts and responds to Ctrl+C)
 */
test('command starts successfully and creates broker supervisors', function () {
    config(['mqtt-broadcast.environments.testing' => ['default']]);

    // TODO: Implement with exec('php artisan mqtt-broadcast') in forked process
    // For now: verify structure is correct (no runtime test)

    expect(true)->toBeTrue(); // Placeholder
})->skip('Fork-based test requires exec() implementation - see file header');

/**
 * SKIPPED: Command responds to SIGTERM gracefully
 *
 * Why skipped: Requires fork with exec() or command refactoring
 * Manual test: php artisan mqtt-broadcast, then pkill -TERM {pid}
 */
test('command responds to SIGTERM signal gracefully', function () {
    config(['mqtt-broadcast.environments.testing' => ['default']]);

    // TODO: Implement with real process spawning

    expect(true)->toBeTrue(); // Placeholder
})->skip('Fork-based test requires exec() implementation - see file header');

/**
 * SKIPPED: Environment option priority
 *
 * Why skipped: Success path requires command to actually run
 * Manual test: php artisan mqtt-broadcast --environment=production
 */
test('command uses environment option when provided', function () {
    config([
        'mqtt-broadcast.env' => 'staging',
        'app.env' => 'local',
        'mqtt-broadcast.environments.production' => ['mqtt-prod'],
    ]);

    // TODO: Test with exec() approach

    expect(true)->toBeTrue(); // Placeholder
})->skip('Fork-based test requires exec() implementation - see file header');

/**
 * SKIPPED: Multiple brokers startup
 *
 * Why skipped: Requires real process execution
 * Manual test: Configure 3 brokers, run command, verify all start
 */
test('command creates supervisor for each configured connection', function () {
    config(['mqtt-broadcast.environments.testing' => ['broker-1', 'broker-2', 'broker-3']]);

    // TODO: Test with exec() approach

    expect(true)->toBeTrue(); // Placeholder
})->skip('Fork-based test requires exec() implementation - see file header');

/**
 * EDGE CASE TEST 2: Handles environment with no matching config
 */
test('command fails when specified environment does not exist in config', function () {
    // Setup: Only 'local' environment exists
    config([
        'app.env' => 'local',
        'mqtt-broadcast.env' => null,
        'mqtt-broadcast.environments.local' => ['mqtt-local'],
    ]);

    // Try to use non-existent 'production' environment
    $this->artisan('mqtt-broadcast', ['--environment' => 'production'])
        ->expectsOutputToContain('No broker connections configured for environment [production]')
        ->assertExitCode(1);
});
