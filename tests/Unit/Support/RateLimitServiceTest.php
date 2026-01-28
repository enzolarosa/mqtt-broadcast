<?php

use enzolarosa\MqttBroadcast\Exceptions\RateLimitExceededException;
use enzolarosa\MqttBroadcast\Support\RateLimitService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Use array cache driver for tests to avoid database issues
    config(['cache.default' => 'array']);

    // Clear cache before each test
    Cache::flush();

    // Default config for testing
    config([
        'mqtt-broadcast.rate_limiting.enabled' => true,
        'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 10,
        'mqtt-broadcast.defaults.connection.rate_limiting.max_per_second' => null,
        'mqtt-broadcast.rate_limiting.strategy' => 'reject',
        'mqtt-broadcast.rate_limiting.by_connection' => true,
        'mqtt-broadcast.rate_limiting.cache_driver' => 'array',
    ]);
});

describe('RateLimitService → Initialization', function () {
    test('it creates service instance', function () {
        $service = new RateLimitService();

        expect($service)->toBeInstanceOf(RateLimitService::class);
    });

    test('it allows publishing when rate limiting is disabled', function () {
        config(['mqtt-broadcast.rate_limiting.enabled' => false]);

        $service = new RateLimitService();

        // Should always allow when disabled
        for ($i = 0; $i < 100; $i++) {
            expect($service->allows('default'))->toBeTrue();
        }
    });
});

describe('RateLimitService → Per-Minute Limit', function () {
    test('it allows publishing within rate limit', function () {
        config(['mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 5]);

        $service = new RateLimitService();

        // First 5 attempts should be allowed
        for ($i = 0; $i < 5; $i++) {
            expect($service->allows('default'))->toBeTrue();
            $service->hit('default');
        }
    });

    test('it blocks publishing when rate limit exceeded', function () {
        config(['mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 3]);

        $service = new RateLimitService();

        // Consume the limit
        for ($i = 0; $i < 3; $i++) {
            $service->hit('default');
        }

        // Next attempt should be blocked
        expect($service->allows('default'))->toBeFalse();
    });

    test('it throws exception when attempt exceeds limit with reject strategy', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 2,
            'mqtt-broadcast.rate_limiting.strategy' => 'reject',
        ]);

        $service = new RateLimitService();

        // Consume the limit
        $service->attempt('default');
        $service->attempt('default');

        // Next attempt should throw
        expect(fn () => $service->attempt('default'))
            ->toThrow(RateLimitExceededException::class);
    });

    test('it tracks remaining attempts correctly', function () {
        config(['mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 5]);

        $service = new RateLimitService();

        expect($service->remaining('default'))->toBe(5);

        $service->hit('default');
        expect($service->remaining('default'))->toBe(4);

        $service->hit('default');
        expect($service->remaining('default'))->toBe(3);
    });
});

describe('RateLimitService → Per-Second Limit', function () {
    test('it enforces per-second limit when configured', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_second' => 2,
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 100,
        ]);

        $service = new RateLimitService();

        // First 2 should be allowed
        expect($service->allows('default'))->toBeTrue();
        $service->hit('default');
        expect($service->allows('default'))->toBeTrue();
        $service->hit('default');

        // Third should be blocked (per-second limit)
        expect($service->allows('default'))->toBeFalse();
    });

    test('it uses most restrictive limit', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_second' => 1,
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 100,
        ]);

        $service = new RateLimitService();

        expect($service->remaining('default'))->toBe(1);

        $service->hit('default');

        // Per-second is most restrictive
        expect($service->remaining('default'))->toBe(0);
    });
});

describe('RateLimitService → Per-Connection Limits', function () {
    test('it isolates limits per connection', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 2,
            'mqtt-broadcast.rate_limiting.by_connection' => true,
        ]);

        $service = new RateLimitService();

        // Consume limit for 'default'
        $service->hit('default');
        $service->hit('default');

        // 'default' should be at limit
        expect($service->allows('default'))->toBeFalse();

        // 'other' should still have full limit
        expect($service->allows('other'))->toBeTrue();
        expect($service->remaining('other'))->toBe(2);
    });

    test('it uses global limit when by_connection is false', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 3,
            'mqtt-broadcast.rate_limiting.by_connection' => false,
        ]);

        $service = new RateLimitService();

        // Hit different connections
        $service->hit('default');
        $service->hit('other');
        $service->hit('third');

        // All connections share the same global limit
        expect($service->allows('default'))->toBeFalse();
        expect($service->allows('other'))->toBeFalse();
        expect($service->allows('another'))->toBeFalse();
    });
});

describe('RateLimitService → Per-Connection Overrides', function () {
    test('it applies per-connection overrides from connection config', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 10,
            'mqtt-broadcast.connections.high-priority.rate_limiting' => [
                'max_per_minute' => 100,
            ],
            'mqtt-broadcast.connections.low-priority.rate_limiting' => [
                'max_per_minute' => 5,
            ],
        ]);

        $service = new RateLimitService();

        // Default connection uses global limit (no override)
        expect($service->remaining('default'))->toBe(10);

        // high-priority uses override from connection config
        expect($service->remaining('high-priority'))->toBe(100);

        // low-priority uses override from connection config
        expect($service->remaining('low-priority'))->toBe(5);
    });

    test('it overrides per-second limit per connection', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => null, // Disable minute limit
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_second' => 10,
            'mqtt-broadcast.connections.fast.rate_limiting' => [
                'max_per_second' => 50,
            ],
        ]);

        $service = new RateLimitService();

        // Default uses global
        expect($service->remaining('default'))->toBe(10);

        // Fast uses override from connection config
        expect($service->remaining('fast'))->toBe(50);
    });
});

describe('RateLimitService → Clear and Reset', function () {
    test('it clears rate limit counters', function () {
        config(['mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 5]);

        $service = new RateLimitService();

        // Consume some limit
        $service->hit('default');
        $service->hit('default');
        expect($service->remaining('default'))->toBe(3);

        // Clear the counters
        $service->clear('default');

        // Limit should be reset
        expect($service->remaining('default'))->toBe(5);
    });

    test('it calculates availableIn correctly', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 1,
        ]);

        $service = new RateLimitService();

        // Consume the limit
        $service->hit('default');

        // Should return seconds until reset
        $availableIn = $service->availableIn('default');
        expect($availableIn)->toBeGreaterThan(0)
            ->and($availableIn)->toBeLessThanOrEqual(60);
    });
});

describe('RateLimitService → Exception Details', function () {
    test('it throws exception with correct details', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => 1,
            'mqtt-broadcast.rate_limiting.strategy' => 'reject',
        ]);

        $service = new RateLimitService();

        // Consume limit
        $service->hit('test-broker');

        try {
            $service->attempt('test-broker');
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            expect($e->getConnection())->toBe('test-broker')
                ->and($e->getLimit())->toBe(1)
                ->and($e->getWindow())->toBe('minute')
                ->and($e->getRetryAfter())->toBeGreaterThan(0);
        }
    });
});

describe('RateLimitService → Edge Cases', function () {
    test('it handles null limits gracefully', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => null,
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_second' => null,
        ]);

        $service = new RateLimitService();

        // Should always allow when no limits configured
        for ($i = 0; $i < 100; $i++) {
            expect($service->allows('default'))->toBeTrue();
            $service->hit('default');
        }
    });

    test('it returns max int for remaining when no limits', function () {
        config([
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute' => null,
            'mqtt-broadcast.defaults.connection.rate_limiting.max_per_second' => null,
        ]);

        $service = new RateLimitService();

        expect($service->remaining('default'))->toBe(PHP_INT_MAX);
    });
});
