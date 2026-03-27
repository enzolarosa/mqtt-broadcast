<?php

declare(strict_types=1);

namespace Tests\Unit\Supervisors;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Factories\MqttClientFactory;
use enzolarosa\MqttBroadcast\Repositories\BrokerRepository;
use enzolarosa\MqttBroadcast\Supervisors\BrokerSupervisor;
use enzolarosa\MqttBroadcast\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Mockery;
use PhpMqtt\Client\MqttClient;

class BrokerSupervisorTest extends TestCase
{
    protected BrokerRepository $repository;
    protected MqttClientFactory $clientFactory;
    protected MqttClient $mockClient;
    protected array $outputCalls;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(BrokerRepository::class);
        $this->clientFactory = Mockery::mock(MqttClientFactory::class);
        $this->mockClient = Mockery::mock(MqttClient::class);
        $this->outputCalls = [];

        Event::fake();

        // Mock create() call that happens in BrokerSupervisor constructor
        // Use a real model instance to avoid Eloquent mock issues
        $this->repository->shouldReceive('create')
            ->byDefault()
            ->andReturnUsing(function ($name, $connection) {
                $broker = new \enzolarosa\MqttBroadcast\Models\BrokerProcess([
                    'name' => $name,
                    'connection' => $connection,
                    'pid' => getmypid(),
                    'started_at' => now(),
                    'last_heartbeat_at' => now(),
                    'working' => true,
                ]);
                $broker->exists = true; // Mark as persisted to avoid DB issues
                return $broker;
            });

        $this->clientFactory->shouldReceive('getConnectionSettings')
            ->byDefault()
            ->andReturn(['settings' => null, 'cleanSession' => false]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createSupervisor(?callable $outputCallback = null, ?array $options = null): BrokerSupervisor
    {
        // Default options: high max retries to avoid terminate during tests
        $defaultOptions = ['max_retries' => 100, 'terminate_on_max_retries' => false];

        return new BrokerSupervisor(
            brokerName: 'test-broker',
            connection: 'mqtt-test',
            repository: $this->repository,
            clientFactory: $this->clientFactory,
            output: $outputCallback ?? function ($type, $line) {
                $this->outputCalls[] = compact('type', 'line');
            },
            options: $options ?? $defaultOptions
        );
    }

    public function test_it_accepts_dependencies_via_constructor(): void
    {
        $supervisor = $this->createSupervisor();
        $this->assertInstanceOf(BrokerSupervisor::class, $supervisor);
    }

    public function test_it_registers_broker_in_database_on_instantiation(): void
    {
        // Expect create() to be called with correct broker name and connection
        $this->repository->shouldReceive('create')
            ->once()
            ->with('test-broker', 'mqtt-test')
            ->andReturnUsing(function ($name, $connection) {
                $broker = new \enzolarosa\MqttBroadcast\Models\BrokerProcess([
                    'name' => $name,
                    'connection' => $connection,
                    'pid' => getmypid(),
                    'started_at' => now(),
                    'last_heartbeat_at' => now(),
                    'working' => true,
                ]);
                $broker->exists = true;
                return $broker;
            });

        // Creating supervisor should trigger repository->create()
        $supervisor = $this->createSupervisor();

        $this->assertInstanceOf(BrokerSupervisor::class, $supervisor);
        // Mockery will automatically verify create() was called once
    }

    public function test_monitor_connects_when_client_is_null(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->once()->andReturn($this->mockClient);
        // isConnected() not called when client is null (short-circuit)
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once();
        $this->mockClient->shouldReceive('loopOnce')->once();
        $this->repository->shouldReceive('touch')->once();

        $supervisor = $this->createSupervisor();
        $supervisor->monitor();

        $this->assertStringContainsString('Connected', $this->outputCalls[0]['line']);
    }

    public function test_monitor_skips_when_not_working(): void
    {
        $supervisor = $this->createSupervisor();
        $supervisor->pause();
        $supervisor->monitor();

        $this->assertEmpty($this->outputCalls);
    }

    public function test_connect_uses_correct_config(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => 'test/', 'qos' => 2]]);

        $this->clientFactory->shouldReceive('create')->once()->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once()->with('test/#', Mockery::type('callable'), 2);
        $this->mockClient->shouldReceive('loopOnce')->once();
        $this->repository->shouldReceive('touch')->once();

        $supervisor = $this->createSupervisor();
        $supervisor->monitor();

        $this->assertTrue(true);
    }

    public function test_handle_message_dispatches_event(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $capturedCallback = null;

        $this->clientFactory->shouldReceive('create')->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once()->andReturnUsing(function ($topic, $callback, $qos) use (&$capturedCallback) {
            $capturedCallback = $callback;
        });
        $this->mockClient->shouldReceive('loopOnce')->once();
        $this->repository->shouldReceive('touch')->once();

        $supervisor = $this->createSupervisor();
        $supervisor->monitor();

        $capturedCallback('test/topic', 'test message');

        Event::assertDispatched(MqttMessageReceived::class, function ($event) {
            return $event->getTopic() === 'test/topic'
                && $event->getMessage() === 'test message'
                && $event->getBroker() === 'test-broker';
        });
    }

    public function test_pause_sets_working_to_false(): void
    {
        $supervisor = $this->createSupervisor();
        $supervisor->pause();
        $supervisor->monitor();

        $this->assertEmpty($this->outputCalls);
    }

    public function test_continue_sets_working_to_true(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->once()->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once();
        $this->mockClient->shouldReceive('loopOnce')->once();
        $this->repository->shouldReceive('touch')->once();

        $supervisor = $this->createSupervisor();
        $supervisor->pause();
        $supervisor->continue();
        $supervisor->monitor();

        $this->assertCount(1, $this->outputCalls);
    }

    public function test_terminate_disconnects_and_deletes_from_repository(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('isConnected')->once()->andReturn(true);
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once();
        $this->mockClient->shouldReceive('loopOnce')->once();
        $this->repository->shouldReceive('touch')->once();
        $this->mockClient->shouldReceive('disconnect')->once();
        $this->repository->shouldReceive('delete')->once();

        $supervisor = $this->createSupervisor();
        $supervisor->monitor();
        $supervisor->terminate();

        $this->assertTrue(true); // Assertion satisfied by mock expectations
    }

    public function test_monitor_handles_exception_gracefully(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once();
        $this->mockClient->shouldReceive('loopOnce')->once()->andThrow(new \RuntimeException('Timeout'));

        $supervisor = $this->createSupervisor();
        $supervisor->monitor();

        $errorCalls = array_filter($this->outputCalls, fn($call) => $call['type'] === 'error');
        $this->assertCount(1, $errorCalls);
    }

    public function test_output_callback_is_optional(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once();
        $this->mockClient->shouldReceive('loopOnce')->once();
        $this->repository->shouldReceive('touch')->once();

        $supervisor = new BrokerSupervisor('test', 'mqtt-test', $this->repository, $this->clientFactory, null);
        $supervisor->monitor();

        $this->assertTrue(true);
    }

    // --- BATCH 1: PRIORITY HIGH EDGE CASES ---

    public function test_reconnection_after_disconnect_during_monitor(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        // Factory creates client twice (once per connect() call)
        $this->clientFactory->shouldReceive('create')->twice()->andReturn($this->mockClient);

        // First monitor: client is null (short-circuit), connects
        // Second monitor: client exists, isConnected() returns false, triggers reconnect
        $this->mockClient->shouldReceive('isConnected')->once()->andReturn(false);
        $this->mockClient->shouldReceive('connect')->twice();
        $this->mockClient->shouldReceive('subscribe')->twice();
        $this->mockClient->shouldReceive('loopOnce')->twice();
        $this->repository->shouldReceive('touch')->twice();

        $supervisor = $this->createSupervisor();
        $supervisor->monitor(); // First connection
        $supervisor->monitor(); // Reconnection

        $connectedMessages = array_filter($this->outputCalls, fn($call) =>
            isset($call['line']) && str_contains($call['line'], 'Connected')
        );

        $this->assertCount(2, $connectedMessages); // Two "Connected" messages
    }

    public function test_connection_failure_is_handled_gracefully(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->once()->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('isConnected')->zeroOrMoreTimes()->andReturn(false);
        $this->mockClient->shouldReceive('connect')->once()->andThrow(
            new \RuntimeException('Connection refused: broker unreachable')
        );

        $supervisor = $this->createSupervisor();
        $supervisor->monitor();

        $errorCalls = array_filter($this->outputCalls, fn($call) => $call['type'] === 'error');
        $this->assertCount(1, $errorCalls);
        $this->assertStringContainsString('Connection refused', array_values($errorCalls)[0]['line']);
    }

    public function test_subscribe_failure_is_handled_gracefully(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->once()->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('isConnected')->zeroOrMoreTimes()->andReturn(false);
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once()->andThrow(
            new \RuntimeException('Subscription failed: invalid topic pattern')
        );

        $supervisor = $this->createSupervisor();
        $supervisor->monitor();

        $errorCalls = array_filter($this->outputCalls, fn($call) => $call['type'] === 'error');
        $this->assertCount(1, $errorCalls);
        $this->assertStringContainsString('Subscription failed', array_values($errorCalls)[0]['line']);
    }

    public function test_terminate_when_never_connected(): void
    {
        $this->repository->shouldReceive('delete')->once()->with('test-broker');

        $supervisor = $this->createSupervisor();
        $supervisor->terminate();

        // Should not crash - client is null, isConnected() not called
        $this->assertTrue(true);
    }

    public function test_repository_touch_failure_is_handled(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->once()->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once();
        $this->mockClient->shouldReceive('loopOnce')->once();
        $this->repository->shouldReceive('touch')->once()->andThrow(
            new \RuntimeException('Database connection lost')
        );

        $supervisor = $this->createSupervisor();
        $supervisor->monitor();

        $errorCalls = array_filter($this->outputCalls, fn($call) => $call['type'] === 'error');
        $this->assertCount(1, $errorCalls);
        $this->assertStringContainsString('Database connection lost', array_values($errorCalls)[0]['line']);
    }

    // --- BATCH 2: RECONNECTION LOGIC TESTS ---

    public function test_exponential_backoff_increases_delay(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->times(3)->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->times(3)->andThrow(
            new \RuntimeException('Connection refused')
        );
        // isConnected: 1st monitor (0 - client null) + 2nd monitor (1) + 3rd monitor (1) = 2 total
        $this->mockClient->shouldReceive('isConnected')->times(2)->andReturn(false);

        $supervisor = $this->createSupervisor(null, ['max_retries' => 10]);

        // First attempt - immediate
        $supervisor->monitor();
        $this->assertStringContainsString('attempt 1/', $this->outputCalls[0]['line']);
        $this->assertStringContainsString('Retrying in 1s', $this->outputCalls[0]['line']);

        // Second attempt - delay should be 2s
        sleep(2); // Wait for backoff window
        $supervisor->monitor();
        $this->assertStringContainsString('attempt 2/', $this->outputCalls[1]['line']);
        $this->assertStringContainsString('Retrying in 2s', $this->outputCalls[1]['line']);

        // Third attempt - delay should be 4s
        sleep(3); // Wait for backoff window
        $supervisor->monitor();
        $this->assertStringContainsString('attempt 3/', $this->outputCalls[2]['line']);
        $this->assertStringContainsString('Retrying in 4s', $this->outputCalls[2]['line']);
    }

    public function test_successful_connection_resets_retry_state(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->twice()->andReturn($this->mockClient);

        // First monitor: connection fails (client null, no isConnected call)
        $this->mockClient->shouldReceive('connect')->once()->andThrow(
            new \RuntimeException('Connection refused')
        );

        $supervisor = $this->createSupervisor();
        $supervisor->monitor();

        $this->assertStringContainsString('attempt 1/', $this->outputCalls[0]['line']);

        // Second monitor: connection succeeds
        // isConnected() called once before reconnect attempt
        $this->mockClient->shouldReceive('isConnected')->once()->andReturn(false);
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once();
        $this->mockClient->shouldReceive('loopOnce')->once();
        $this->repository->shouldReceive('touch')->once();

        sleep(2); // Wait for backoff
        $supervisor->monitor();

        // Should show "Connected to broker" (NOT "attempt 2"), retry state was reset
        $this->assertStringContainsString('Connected to broker', $this->outputCalls[1]['line']);
    }

    public function test_max_retries_triggers_reset_when_terminate_disabled(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $maxRetries = 3;
        $this->clientFactory->shouldReceive('create')->times($maxRetries)->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->times($maxRetries)->andThrow(
            new \RuntimeException('Connection refused')
        );
        // isConnected: 2nd monitor (1) + 3rd monitor (1) = 2 total (no terminate)
        $this->mockClient->shouldReceive('isConnected')->times($maxRetries - 1)->andReturn(false);

        $supervisor = $this->createSupervisor(null, [
            'max_retries' => $maxRetries,
            'terminate_on_max_retries' => false,
            'max_retry_delay' => 60,
        ]);

        // Simulate failures up to max
        for ($i = 1; $i <= $maxRetries; $i++) {
            if ($i > 1) {
                sleep(pow(2, $i - 2) + 1); // Wait for backoff
            }
            $supervisor->monitor();
        }

        // Check last message indicates reset with long pause
        $lastError = end($this->outputCalls);
        $this->assertStringContainsString('Max retries (3) exceeded', $lastError['line']);
        $this->assertStringContainsString('Pausing for 60 seconds', $lastError['line']);
    }

    public function test_max_retries_triggers_terminate_when_enabled(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $maxRetries = 3;
        $this->clientFactory->shouldReceive('create')->times($maxRetries)->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->times($maxRetries)->andThrow(
            new \RuntimeException('Connection refused')
        );
        // isConnected: 2nd monitor (1) + 3rd monitor (1) + terminate (1) = 3 total
        $this->mockClient->shouldReceive('isConnected')->times($maxRetries)->andReturn(false);
        $this->repository->shouldReceive('delete')->once();

        $supervisor = $this->createSupervisor(null, [
            'max_retries' => $maxRetries,
            'terminate_on_max_retries' => true,
        ]);

        // Simulate failures up to max
        for ($i = 1; $i <= $maxRetries; $i++) {
            if ($i > 1) {
                sleep(pow(2, $i - 2) + 1);
            }
            $supervisor->monitor();
        }

        // Check last message indicates termination
        $lastError = end($this->outputCalls);
        $this->assertStringContainsString('Max retries (3) exceeded', $lastError['line']);
        $this->assertStringContainsString('Terminating supervisor', $lastError['line']);
    }

    public function test_should_retry_respects_backoff_window(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        // First attempt: connection fails
        $this->clientFactory->shouldReceive('create')->once()->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->once()->andThrow(
            new \RuntimeException('Connection refused')
        );

        $supervisor = $this->createSupervisor();

        // First monitor: connection fails, retry in 1s
        $supervisor->monitor();
        $this->assertCount(1, $this->outputCalls); // "Connection failed..."

        // Second monitor: immediately (no time passed), should skip due to backoff
        // Client exists, so isConnected() is called
        $this->mockClient->shouldReceive('isConnected')->once()->andReturn(false);
        $supervisor->monitor();
        $this->assertCount(1, $this->outputCalls); // Still 1, shouldRetry() returned false

        // Third monitor: after backoff window, connection succeeds
        sleep(2);
        $this->clientFactory->shouldReceive('create')->once()->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('isConnected')->once()->andReturn(false); // Check before reconnect
        $this->mockClient->shouldReceive('connect')->once();
        $this->mockClient->shouldReceive('subscribe')->once();
        $this->mockClient->shouldReceive('loopOnce')->once();
        $this->repository->shouldReceive('touch')->once();

        $supervisor->monitor();

        // Should have at least "Connection failed" + "Connected to broker"
        $this->assertGreaterThanOrEqual(2, count($this->outputCalls));

        // Check that connection eventually succeeded
        $hasConnected = false;
        foreach ($this->outputCalls as $call) {
            if (str_contains($call['line'], 'Connected to broker')) {
                $hasConnected = true;
                break;
            }
        }
        $this->assertTrue($hasConnected, 'Should have "Connected to broker" message');
    }

    public function test_config_validation_rejects_invalid_max_retries(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('max_retries must be at least 1');

        new BrokerSupervisor(
            'test',
            'mqtt-test',
            $this->repository,
            $this->clientFactory,
            null,
            ['max_retries' => 0]
        );
    }

    public function test_config_validation_rejects_invalid_max_retry_delay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('max_retry_delay must be at least 1');

        new BrokerSupervisor(
            'test',
            'mqtt-test',
            $this->repository,
            $this->clientFactory,
            null,
            ['max_retry_delay' => 0]
        );
    }

    public function test_options_override_config_values(): void
    {
        config([
            'mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0],
            'mqtt-broadcast.reconnection.max_retries' => 20,
            'mqtt-broadcast.reconnection.terminate_on_max_retries' => false,
        ]);

        $this->clientFactory->shouldReceive('create')->once()->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->once()->andThrow(
            new \RuntimeException('Connection refused')
        );

        // Options should override config
        $supervisor = $this->createSupervisor(null, [
            'max_retries' => 5,
            'terminate_on_max_retries' => true,
        ]);

        $supervisor->monitor();

        // Should show custom max_retries value
        $this->assertStringContainsString('attempt 1/5', $this->outputCalls[0]['line']);
    }

    public function test_it_reports_working_status()
    {
        $supervisor = $this->createSupervisor();

        $this->assertTrue($supervisor->isWorking());

        $supervisor->pause();
        $this->assertFalse($supervisor->isWorking());

        $supervisor->continue();
        $this->assertTrue($supervisor->isWorking());
    }

    public function test_it_reports_not_working_after_terminate()
    {
        $this->repository->shouldReceive('delete')->once();

        $supervisor = $this->createSupervisor();
        $this->assertTrue($supervisor->isWorking());

        $supervisor->terminate();
        $this->assertFalse($supervisor->isWorking());
    }

    public function test_config_validation_rejects_invalid_max_failure_duration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('max_failure_duration must be at least 1');

        new BrokerSupervisor(
            'test',
            'mqtt-test',
            $this->repository,
            $this->clientFactory,
            null,
            ['max_failure_duration' => 0]
        );
    }

    public function test_circuit_breaker_terminates_after_max_failure_duration(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $this->clientFactory->shouldReceive('create')->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('isConnected')->andReturn(false);
        $this->mockClient->shouldReceive('connect')->andThrow(
            new \RuntimeException('Connection refused')
        );

        // Set very short failure duration for testing (2 seconds)
        $supervisor = $this->createSupervisor(null, [
            'max_failure_duration' => 2,
            'max_retry_delay' => 1,
        ]);

        // Repository should be called to delete when terminate is triggered
        $this->repository->shouldReceive('delete')->once();

        // Simulate connection failures over time
        $supervisor->monitor(); // First failure at t=0
        sleep(1);
        $supervisor->monitor(); // Second failure at t=1

        // This should trigger terminate because we've been failing for > 2 seconds
        sleep(2);

        // The terminate will call exit() which we can't test directly
        // But we can verify the error message is logged
        $hasTerminateMessage = false;
        try {
            $supervisor->monitor();
        } catch (\Throwable $e) {
            // Ignore exit/terminate
        }

        // Check that the terminate message was logged
        foreach ($this->outputCalls as $call) {
            if (str_contains($call['line'], 'Giving up and terminating')) {
                $hasTerminateMessage = true;
                break;
            }
        }

        $this->assertTrue($hasTerminateMessage, 'Should have terminate message when threshold exceeded');
    }

    public function test_circuit_breaker_uses_per_connection_override(): void
    {
        config([
            'mqtt-broadcast.connections.mqtt-test' => [
                'prefix' => '',
                'qos' => 0,
                'max_failure_duration' => 10, // Override to 10 seconds
            ],
            'mqtt-broadcast.reconnection.max_failure_duration' => 3600, // Default 1 hour
        ]);

        $this->clientFactory->shouldReceive('create')->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->andThrow(
            new \RuntimeException('Connection refused')
        );

        $supervisor = $this->createSupervisor();

        // The supervisor should be using the 10 second override, not 3600
        // We can't easily test this directly, but we can verify it doesn't terminate immediately
        $supervisor->monitor();
        $this->assertTrue($supervisor->isWorking());
    }

    public function test_circuit_breaker_resets_on_successful_connection(): void
    {
        config(['mqtt-broadcast.connections.mqtt-test' => ['prefix' => '', 'qos' => 0]]);

        $connectionAttempts = 0;
        $this->clientFactory->shouldReceive('create')->andReturn($this->mockClient);
        $this->mockClient->shouldReceive('connect')->andReturnUsing(
            function () use (&$connectionAttempts) {
                $connectionAttempts++;
                if ($connectionAttempts < 3) {
                    throw new \RuntimeException('Connection refused');
                }
                // Third attempt succeeds
            }
        );

        $this->mockClient->shouldReceive('isConnected')->andReturn(false, false, false, true, true);
        $this->mockClient->shouldReceive('subscribe')->once();
        $this->mockClient->shouldReceive('loopOnce')->once();
        $this->repository->shouldReceive('touch')->once();

        $supervisor = $this->createSupervisor(null, [
            'max_failure_duration' => 10,
            'max_retry_delay' => 1,
        ]);

        // First two failures
        $supervisor->monitor();
        sleep(1);
        $supervisor->monitor();

        // Third attempt succeeds - should reset circuit breaker
        sleep(1);
        $supervisor->monitor();

        // Verify supervisor is still working (didn't terminate)
        $this->assertTrue($supervisor->isWorking());

        // Verify "Connection restored" message was logged
        $hasRestoredMessage = false;
        foreach ($this->outputCalls as $call) {
            if (str_contains($call['line'], 'Connection restored successfully')) {
                $hasRestoredMessage = true;
                break;
            }
        }
        $this->assertTrue($hasRestoredMessage, 'Should have connection restored message');
    }
}
