<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Factories\MqttClientFactory;
use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;
use Illuminate\Support\Facades\Queue;
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
 * PERFORMANCE TEST 1: JOB CREATION SPEED
 * ============================================================================
 */

/**
 * PERFORMANCE TEST 1: Creating many jobs is fast
 */
test('can create 1000 jobs quickly', function () {
    Queue::fake();

    $startTime = microtime(true);

    // Create 1000 jobs
    for ($i = 0; $i < 1000; $i++) {
        $job = new MqttMessageJob("test/topic/{$i}", "message {$i}");
    }

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    // Should take less than 1 second
    expect($duration)->toBeLessThan(1.0);
});

/**
 * PERFORMANCE TEST 2: Job serialization performance
 */
test('job serialization is fast for large payloads', function () {
    Queue::fake();

    // Create job with 100KB payload
    $largePayload = str_repeat('A', 100 * 1024);

    $startTime = microtime(true);

    // Serialize/unserialize 100 times
    for ($i = 0; $i < 100; $i++) {
        $job = new MqttMessageJob('test/large', $largePayload);
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);
    }

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    // Should take less than 1 second for 100 iterations
    expect($duration)->toBeLessThan(1.0);
});

/**
 * ============================================================================
 * PERFORMANCE TEST 2: EXECUTION SPEED
 * ============================================================================
 */

/**
 * PERFORMANCE TEST 3: Job execution is fast (single message)
 */
test('single job executes in under 10ms', function () {
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ]);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true);
    $this->mockClient->shouldReceive('connect')->once();
    $this->mockClient->shouldReceive('publish')->once();
    $this->mockClient->shouldReceive('disconnect')->once();

    $job = new MqttMessageJob('test/perf', 'message');

    $startTime = microtime(true);
    $job->handle();
    $endTime = microtime(true);

    $duration = ($endTime - $startTime) * 1000; // Convert to ms

    // Should execute in under 10ms (with mocks)
    expect($duration)->toBeLessThan(10.0);
});

/**
 * PERFORMANCE TEST 4: Sequential job execution (100 jobs)
 */
test('can execute 100 jobs sequentially in reasonable time', function () {
    // Mock factory returns new client for each job
    $this->mockFactory->shouldReceive('create')
        ->andReturnUsing(function () {
            $client = Mockery::mock(MqttClient::class);
            $client->shouldReceive('isConnected')->andReturn(false, true);
            $client->shouldReceive('connect')->once();
            $client->shouldReceive('publish')->once();
            $client->shouldReceive('disconnect')->once();

            return $client;
        })
        ->times(100);

    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ])->times(100);

    $startTime = microtime(true);

    for ($i = 0; $i < 100; $i++) {
        $job = new MqttMessageJob("test/perf/{$i}", "message {$i}");
        $job->handle();
    }

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    // 100 jobs should take less than 1 second with mocks
    expect($duration)->toBeLessThan(1.0);
});

/**
 * ============================================================================
 * STRESS TEST 1: MEMORY USAGE
 * ============================================================================
 */

/**
 * STRESS TEST 1: Job doesn't leak memory
 */
test('job execution does not leak memory', function () {
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient)->times(1000);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ])->times(1000);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true)->times(2000);
    $this->mockClient->shouldReceive('connect')->times(1000);
    $this->mockClient->shouldReceive('publish')->times(1000);
    $this->mockClient->shouldReceive('disconnect')->times(1000);

    $memoryBefore = memory_get_usage();

    // Execute 1000 jobs
    for ($i = 0; $i < 1000; $i++) {
        $job = new MqttMessageJob("test/memory/{$i}", "message {$i}");
        $job->handle();

        // Force garbage collection every 100 jobs
        if ($i % 100 === 0) {
            gc_collect_cycles();
        }
    }

    gc_collect_cycles();
    $memoryAfter = memory_get_usage();

    $memoryIncrease = $memoryAfter - $memoryBefore;
    $memoryIncreaseMB = $memoryIncrease / 1024 / 1024;

    // Memory increase should be less than 10MB for 1000 jobs
    expect($memoryIncreaseMB)->toBeLessThan(10.0);
})->skip('Memory test can be flaky in CI - enable for local profiling');

/**
 * STRESS TEST 2: Large payload memory efficiency
 */
test('job handles large payloads without excessive memory', function () {
    Queue::fake();

    $memoryBefore = memory_get_usage();

    // Create job with 5MB payload
    $largePayload = str_repeat('A', 5 * 1024 * 1024);
    $job = new MqttMessageJob('test/large', $largePayload);

    $memoryAfter = memory_get_usage();
    $memoryIncrease = $memoryAfter - $memoryBefore;
    $memoryIncreaseMB = $memoryIncrease / 1024 / 1024;

    // Memory increase should be reasonable (payload + overhead)
    // Payload is 5MB, so total should be less than 10MB with overhead
    expect($memoryIncreaseMB)->toBeLessThan(10.0);
});

/**
 * ============================================================================
 * STRESS TEST 2: CONCURRENT SCENARIOS
 * ============================================================================
 */

/**
 * STRESS TEST 3: Multiple jobs can be queued simultaneously
 */
test('can queue 10000 jobs without errors', function () {
    Queue::fake();

    $startTime = microtime(true);

    // Queue 10,000 jobs
    for ($i = 0; $i < 10000; $i++) {
        MqttMessageJob::dispatch("test/stress/{$i}", "message {$i}");
    }

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    // Should queue 10k jobs in under 5 seconds
    expect($duration)->toBeLessThan(5.0);

    Queue::assertPushed(MqttMessageJob::class, 10000);
});

/**
 * STRESS TEST 4: Jobs with varying payload sizes
 */
test('handles mixed payload sizes efficiently', function () {
    Queue::fake();

    $payloads = [
        str_repeat('A', 100),           // 100 bytes
        str_repeat('B', 1024),          // 1KB
        str_repeat('C', 10 * 1024),     // 10KB
        str_repeat('D', 100 * 1024),    // 100KB
        str_repeat('E', 1024 * 1024),   // 1MB
    ];

    $startTime = microtime(true);

    // Create 1000 jobs with varying sizes
    for ($i = 0; $i < 1000; $i++) {
        $payload = $payloads[$i % count($payloads)];
        MqttMessageJob::dispatch("test/mixed/{$i}", $payload);
    }

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    // Should handle mixed sizes in under 2 seconds
    expect($duration)->toBeLessThan(2.0);

    Queue::assertPushed(MqttMessageJob::class, 1000);
});

/**
 * ============================================================================
 * STRESS TEST 3: ERROR SCENARIOS UNDER LOAD
 * ============================================================================
 */

/**
 * STRESS TEST 5: Rapid connection failures don't crash
 */
test('handles rapid connection failures gracefully', function () {
    $this->mockFactory->shouldReceive('create')
        ->times(100)
        ->andThrow(new \enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException('Connection failed'));

    // Try to execute 100 jobs that all fail
    for ($i = 0; $i < 100; $i++) {
        $job = new MqttMessageJob("test/fail/{$i}", "message");

        // Job should fail gracefully (not throw)
        $job->handle();
    }

    // If we get here, all failures were handled gracefully
    expect(true)->toBeTrue();
});

/**
 * ============================================================================
 * BENCHMARK TESTS (Optional - Run Manually)
 * ============================================================================
 */

/**
 * BENCHMARK TEST 1: JSON encoding performance
 */
test('benchmark: JSON encoding for various sizes', function () {
    $sizes = [
        'small' => 100,      // 100 items
        'medium' => 1000,    // 1K items
        'large' => 10000,    // 10K items
    ];

    $results = [];

    foreach ($sizes as $label => $itemCount) {
        $data = array_fill(0, $itemCount, ['id' => 1, 'name' => 'test', 'value' => 123]);

        $startTime = microtime(true);
        $encoded = json_encode($data);
        $endTime = microtime(true);

        $results[$label] = [
            'items' => $itemCount,
            'time_ms' => ($endTime - $startTime) * 1000,
            'size_kb' => strlen($encoded) / 1024,
        ];
    }

    // Output for reference (not assertions)
    expect($results['small']['time_ms'])->toBeLessThan(10.0);
    expect($results['medium']['time_ms'])->toBeLessThan(100.0);
    expect($results['large']['time_ms'])->toBeLessThan(1000.0);
})->skip('Benchmark test - run manually for profiling');

/**
 * BENCHMARK TEST 2: Topic prefix performance
 */
test('benchmark: topic prefix application', function () {
    config([
        'mqtt-broadcast.connections.default' => [
            'host' => '127.0.0.1',
            'port' => 1883,
            'prefix' => 'app/production/v1/',
        ],
    ]);

    $startTime = microtime(true);

    // Apply prefix 10,000 times
    for ($i = 0; $i < 10000; $i++) {
        $topic = \enzolarosa\MqttBroadcast\MqttBroadcast::getTopic("sensor/{$i}", 'default');
    }

    $endTime = microtime(true);
    $duration = ($endTime - $startTime) * 1000;

    // Should be very fast (string concatenation)
    expect($duration)->toBeLessThan(100.0);
})->skip('Benchmark test - run manually for profiling');

/**
 * ============================================================================
 * THROUGHPUT ESTIMATION TESTS
 * ============================================================================
 */

/**
 * THROUGHPUT TEST 1: Estimate max messages per second (mocked)
 */
test('estimate throughput: jobs per second with mocks', function () {
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockClient)->times(1000);
    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ])->times(1000);

    $this->mockClient->shouldReceive('isConnected')->andReturn(false, true)->times(2000);
    $this->mockClient->shouldReceive('connect')->times(1000);
    $this->mockClient->shouldReceive('publish')->times(1000);
    $this->mockClient->shouldReceive('disconnect')->times(1000);

    $startTime = microtime(true);

    // Execute 1000 jobs
    for ($i = 0; $i < 1000; $i++) {
        $job = new MqttMessageJob("test/throughput/{$i}", "message {$i}");
        $job->handle();
    }

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    $messagesPerSecond = 1000 / $duration;

    // With mocks, should achieve > 1000 msg/sec
    expect($messagesPerSecond)->toBeGreaterThan(1000);
})->skip('Throughput estimation - enable for profiling');

/**
 * ============================================================================
 * SCALABILITY TESTS
 * ============================================================================
 */

/**
 * SCALABILITY TEST 1: Multiple brokers handling
 */
test('can handle jobs for 50 different brokers', function () {
    Queue::fake();

    $startTime = microtime(true);

    // Create jobs for 50 different brokers
    for ($broker = 1; $broker <= 50; $broker++) {
        config([
            "mqtt-broadcast.connections.broker-{$broker}" => [
                'host' => "mqtt-{$broker}.example.com",
                'port' => 1883,
                'qos' => 0,
                'retain' => false,
            ],
        ]);

        // 10 jobs per broker = 500 total jobs
        for ($i = 0; $i < 10; $i++) {
            MqttMessageJob::dispatch("test/broker-{$broker}/{$i}", "message", "broker-{$broker}");
        }
    }

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    // Should handle 500 jobs across 50 brokers in under 2 seconds
    expect($duration)->toBeLessThan(2.0);

    Queue::assertPushed(MqttMessageJob::class, 500);
});

/**
 * SCALABILITY TEST 2: Deep topic hierarchy
 */
test('handles deeply nested topic hierarchies efficiently', function () {
    Queue::fake();

    // Create topic with 20 levels
    $deepTopic = implode('/', array_fill(0, 20, 'level'));

    $startTime = microtime(true);

    for ($i = 0; $i < 1000; $i++) {
        MqttMessageJob::dispatch("{$deepTopic}/{$i}", "message");
    }

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    // Should handle deep topics efficiently
    expect($duration)->toBeLessThan(1.0);
    expect(strlen($deepTopic))->toBeGreaterThan(100);
});

/**
 * ============================================================================
 * RESOURCE CLEANUP TESTS
 * ============================================================================
 */

/**
 * RESOURCE TEST 1: Disconnect is always called
 */
test('disconnect called even under stress', function () {
    $disconnectCallCount = 0;

    // Mock factory returns new client for each job
    $this->mockFactory->shouldReceive('create')
        ->andReturnUsing(function () use (&$disconnectCallCount) {
            $client = Mockery::mock(MqttClient::class);
            $client->shouldReceive('isConnected')->andReturn(false, true);
            $client->shouldReceive('connect')->once();
            $client->shouldReceive('publish')->once();
            $client->shouldReceive('disconnect')->once()->andReturnUsing(function () use (&$disconnectCallCount) {
                $disconnectCallCount++;
            });

            return $client;
        })
        ->times(100);

    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ])->times(100);

    // Execute 100 jobs rapidly
    for ($i = 0; $i < 100; $i++) {
        $job = new MqttMessageJob("test/stress/{$i}", "message");
        $job->handle();
    }

    // Verify disconnect was called exactly 100 times
    expect($disconnectCallCount)->toBe(100);
});

/**
 * RESOURCE TEST 2: No dangling connections after errors
 */
test('no dangling connections after multiple failures', function () {
    $disconnectCount = 0;
    $jobNumber = 0;

    // Mock factory returns new client for each job
    $this->mockFactory->shouldReceive('create')
        ->andReturnUsing(function () use (&$disconnectCount, &$jobNumber) {
            $currentJob = $jobNumber++;
            $client = Mockery::mock(MqttClient::class);
            $client->shouldReceive('isConnected')->andReturn(false, true);
            $client->shouldReceive('connect')->once();

            // Alternate success and failure
            $client->shouldReceive('publish')->once()->andReturnUsing(function () use ($currentJob) {
                if ($currentJob % 2 === 1) {
                    throw new \PhpMqtt\Client\Exceptions\DataTransferException(0101, 'Connection lost');
                }
            });

            // Disconnect should be called even on failure
            $client->shouldReceive('disconnect')->once()->andReturnUsing(function () use (&$disconnectCount) {
                $disconnectCount++;
            });

            return $client;
        })
        ->times(50);

    $this->mockFactory->shouldReceive('getConnectionSettings')->andReturn([
        'settings' => null,
        'cleanSession' => true,
    ])->times(50);

    // Execute 50 jobs (25 will succeed, 25 will fail)
    for ($i = 0; $i < 50; $i++) {
        $job = new MqttMessageJob("test/mixed/{$i}", "message");

        try {
            $job->handle();
        } catch (\PhpMqtt\Client\Exceptions\DataTransferException $e) {
            // Expected for some jobs
        }
    }

    // Verify disconnect was called for all 50 jobs
    expect($disconnectCount)->toBe(50);
});
