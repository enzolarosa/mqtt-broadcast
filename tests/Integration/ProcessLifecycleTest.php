<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Models\BrokerProcess;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use enzolarosa\MqttBroadcast\Support\ProcessIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Use array cache driver for tests (avoids database cache table issues)
    config(['cache.default' => 'array']);
    Cache::flush();

    // Setup test environment config
    config([
        'mqtt-broadcast.env' => 'integration-test',
        'mqtt-broadcast.environments.integration-test' => ['test-broker'],
        'mqtt-broadcast.connections.test-broker' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'prefix' => '',
            'qos' => 0,
            'retain' => false,
            'auth' => false,
        ],
    ]);
});

afterEach(function () {
    // Cleanup: kill any stray processes
    if (isset($this->processHandle) && is_resource($this->processHandle)) {
        proc_terminate($this->processHandle, SIGTERM);
        proc_close($this->processHandle);
    }

    Cache::flush();
});

/**
 * ============================================================================
 * HELPER FUNCTIONS FOR PROCESS MANAGEMENT
 * ============================================================================
 */

/**
 * Start mqtt-broadcast command as real background process
 *
 * @return array{handle: resource, pid: int, pipes: array}
 */
function startMqttBroadcastProcess(array $options = []): array
{
    $artisanPath = __DIR__.'/../../vendor/bin/testbench';

    if (! file_exists($artisanPath)) {
        throw new RuntimeException("Testbench binary not found at: {$artisanPath}");
    }

    $command = sprintf(
        'exec php %s mqtt-broadcast %s 2>&1',
        escapeshellarg($artisanPath),
        implode(' ', array_map('escapeshellarg', $options))
    );

    $descriptors = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ];

    $pipes = [];
    $process = proc_open($command, $descriptors, $pipes, getcwd());

    if (! is_resource($process)) {
        throw new RuntimeException('Failed to start mqtt-broadcast process');
    }

    // Set pipes to non-blocking
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    // Get process PID
    $status = proc_get_status($process);
    $pid = $status['pid'];

    return [
        'handle' => $process,
        'pid' => $pid,
        'pipes' => $pipes,
    ];
}

/**
 * Wait for process to start and register in cache/database
 *
 * @param  int  $pid  Process ID to wait for
 * @param  int  $timeout  Max seconds to wait
 * @return bool True if started, false if timeout
 */
function waitForProcessStart(int $pid, int $timeout = 5): bool
{
    $deadline = time() + $timeout;

    while (time() < $deadline) {
        // Check if master supervisor registered in cache
        $hostname = ProcessIdentifier::hostname();
        $masterName = "{$hostname}-master-supervisor";

        if (Cache::has($masterName)) {
            return true;
        }

        // Also check if broker processes registered in database
        if (BrokerProcess::count() > 0) {
            return true;
        }

        usleep(100000); // 100ms
    }

    return false;
}

/**
 * Send signal to process and wait for it to terminate
 *
 * @param  resource  $processHandle  Process handle from proc_open
 * @param  int  $signal  Signal to send (SIGTERM, SIGKILL)
 * @param  int  $timeout  Max seconds to wait for termination
 * @return bool True if terminated, false if timeout
 */
function sendSignalAndWait($processHandle, int $signal = SIGTERM, int $timeout = 5): bool
{
    proc_terminate($processHandle, $signal);

    $deadline = time() + $timeout;

    while (time() < $deadline) {
        $status = proc_get_status($processHandle);

        if (! $status['running']) {
            return true;
        }

        usleep(100000); // 100ms
    }

    return false;
}

/**
 * Read output from process pipes
 *
 * @param  array  $pipes  Pipes from proc_open
 * @return array{stdout: string, stderr: string}
 */
function readProcessOutput(array $pipes): array
{
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';

    return [
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/**
 * ============================================================================
 * PROCESS LIFECYCLE TESTS
 * ============================================================================
 */

/**
 * INTEGRATION TEST 1: Command starts as real process
 *
 * This test verifies that mqtt-broadcast command can be launched
 * as a separate process and registers itself correctly.
 */
test('command starts as real background process', function () {
    // Start process
    $process = startMqttBroadcastProcess();
    $this->processHandle = $process['handle'];

    // Wait for process to initialize
    $started = waitForProcessStart($process['pid'], 5);

    expect($started)->toBeTrue('Process should start and register within 5 seconds');

    // Verify process is running
    $status = proc_get_status($process['handle']);
    expect($status['running'])->toBeTrue();

    // Cleanup
    $terminated = sendSignalAndWait($process['handle'], SIGTERM, 5);
    expect($terminated)->toBeTrue('Process should terminate gracefully on SIGTERM');

    proc_close($process['handle']);
    unset($this->processHandle);
})->skip('Requires real MQTT broker or full mock implementation');

/**
 * INTEGRATION TEST 2: Process responds to SIGTERM gracefully
 *
 * This test verifies that the process handles SIGTERM signal
 * and shuts down gracefully, cleaning up resources.
 */
test('process responds to SIGTERM signal gracefully', function () {
    // Start process
    $process = startMqttBroadcastProcess();
    $this->processHandle = $process['handle'];

    // Wait for startup
    waitForProcessStart($process['pid'], 5);

    // Send SIGTERM
    $terminated = sendSignalAndWait($process['handle'], SIGTERM, 5);

    expect($terminated)->toBeTrue('Process should terminate within 5 seconds');

    // Verify exit code
    $status = proc_get_status($process['handle']);
    expect($status['running'])->toBeFalse();

    // Read output to check for graceful shutdown messages
    $output = readProcessOutput($process['pipes']);

    // Process should not crash (no error traces in output)
    expect($output['stderr'])->not->toContain('Fatal error');
    expect($output['stderr'])->not->toContain('Uncaught exception');

    proc_close($process['handle']);
    unset($this->processHandle);
})->skip('Requires real MQTT broker or full mock implementation');

/**
 * INTEGRATION TEST 3: Process cleans up cache on termination
 *
 * Verifies that master supervisor cache entry is removed when
 * the process terminates gracefully.
 */
test('process cleans up cache on graceful termination', function () {
    $masterRepo = new MasterSupervisorRepository();
    $hostname = ProcessIdentifier::hostname();
    $masterName = "{$hostname}-master-supervisor";

    // Start process
    $process = startMqttBroadcastProcess();
    $this->processHandle = $process['handle'];

    // Wait for startup and verify cache entry exists
    waitForProcessStart($process['pid'], 5);
    expect(Cache::has($masterName))->toBeTrue('Master supervisor should register in cache');

    // Send SIGTERM
    sendSignalAndWait($process['handle'], SIGTERM, 5);

    // Give it a moment to cleanup
    usleep(500000); // 500ms

    // Verify cache was cleaned up
    expect(Cache::has($masterName))->toBeFalse('Master supervisor cache should be cleaned up');

    proc_close($process['handle']);
    unset($this->processHandle);
})->skip('Requires real MQTT broker or full mock implementation');

/**
 * INTEGRATION TEST 4: Process cleans up database on termination
 *
 * Verifies that broker process records are removed when
 * the process terminates gracefully.
 */
test('process cleans up database records on graceful termination', function () {
    // Start process
    $process = startMqttBroadcastProcess();
    $this->processHandle = $process['handle'];

    // Wait for startup and verify database entries exist
    waitForProcessStart($process['pid'], 5);

    $brokerCount = BrokerProcess::count();
    expect($brokerCount)->toBeGreaterThan(0, 'Broker processes should be registered in database');

    // Send SIGTERM
    sendSignalAndWait($process['handle'], SIGTERM, 5);

    // Give it a moment to cleanup
    usleep(500000); // 500ms

    // Verify database was cleaned up
    $brokerCountAfter = BrokerProcess::count();
    expect($brokerCountAfter)->toBe(0, 'Broker process records should be cleaned up');

    proc_close($process['handle']);
    unset($this->processHandle);
})->skip('Requires real MQTT broker or full mock implementation');

/**
 * INTEGRATION TEST 5: Process can be killed with SIGKILL
 *
 * Verifies that SIGKILL works as last resort (no graceful cleanup expected)
 */
test('process can be forcefully killed with SIGKILL', function () {
    // Start process
    $process = startMqttBroadcastProcess();
    $this->processHandle = $process['handle'];

    // Wait for startup
    waitForProcessStart($process['pid'], 5);

    // Send SIGKILL (force kill)
    $terminated = sendSignalAndWait($process['handle'], SIGKILL, 2);

    expect($terminated)->toBeTrue('Process should terminate immediately on SIGKILL');

    // Verify process is dead
    $status = proc_get_status($process['handle']);
    expect($status['running'])->toBeFalse();

    proc_close($process['handle']);
    unset($this->processHandle);
})->skip('Requires real MQTT broker or full mock implementation');

/**
 * INTEGRATION TEST 6: Multiple processes cannot run on same machine
 *
 * Verifies that duplicate master supervisor detection works
 */
test('second process fails when master already running', function () {
    // Start first process
    $process1 = startMqttBroadcastProcess();
    $this->processHandle = $process1['handle'];

    // Wait for first process to start
    waitForProcessStart($process1['pid'], 5);

    // Try to start second process
    $process2 = startMqttBroadcastProcess();

    // Wait a bit and check output
    sleep(1);
    $output = readProcessOutput($process2['pipes']);

    // Second process should fail with warning
    expect($output['stdout'] ?? $output['stderr'])
        ->toContain('already running', 'Second process should detect existing master');

    // Verify second process exits
    $status = proc_get_status($process2['handle']);
    expect($status['running'])->toBeFalse('Second process should exit immediately');

    // Cleanup
    proc_terminate($process2['handle'], SIGTERM);
    proc_close($process2['handle']);

    sendSignalAndWait($process1['handle'], SIGTERM, 5);
    proc_close($process1['handle']);
    unset($this->processHandle);
})->skip('Requires real MQTT broker or full mock implementation');

/**
 * ============================================================================
 * ENVIRONMENT & OPTIONS TESTS
 * ============================================================================
 */

/**
 * INTEGRATION TEST 7: Process uses --environment option correctly
 */
test('process respects environment option', function () {
    // Setup additional environment
    config([
        'mqtt-broadcast.environments.production' => ['prod-broker'],
        'mqtt-broadcast.connections.prod-broker' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'auth' => false,
        ],
    ]);

    // Start with production environment
    $process = startMqttBroadcastProcess(['--environment=production']);
    $this->processHandle = $process['handle'];

    // Wait for startup
    waitForProcessStart($process['pid'], 5);

    // Verify correct broker was created
    $brokers = BrokerProcess::all();
    expect($brokers)->toHaveCount(1);
    expect($brokers->first()->connection)->toBe('prod-broker');

    // Cleanup
    sendSignalAndWait($process['handle'], SIGTERM, 5);
    proc_close($process['handle']);
    unset($this->processHandle);
})->skip('Requires real MQTT broker or full mock implementation');

/**
 * INTEGRATION TEST 8: Process fails gracefully with invalid environment
 */
test('process fails gracefully with non-existent environment', function () {
    // Start with non-existent environment
    $process = startMqttBroadcastProcess(['--environment=non-existent']);

    // Wait a bit and check output
    sleep(1);
    $output = readProcessOutput($process['pipes']);

    // Should show error message
    $allOutput = $output['stdout'].$output['stderr'];
    expect($allOutput)->toContain('No broker connections configured');

    // Verify process exits with error
    $status = proc_get_status($process['handle']);
    expect($status['running'])->toBeFalse('Process should exit on config error');

    // Cleanup
    proc_close($process['handle']);
})->skip('Requires testbench process execution setup');
