<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Support;

use enzolarosa\MqttBroadcast\Exceptions\RateLimitExceededException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;

/**
 * Service for managing MQTT publishing rate limits.
 *
 * Provides methods to check and enforce rate limits on MQTT message
 * publishing, with support for per-connection and global limits.
 */
class RateLimitService
{
    /**
     * The rate limiter instance.
     */
    protected RateLimiter $limiter;

    /**
     * Create a new rate limit service instance.
     */
    public function __construct()
    {
        $driver = config('mqtt-broadcast.rate_limiting.cache_driver');
        $cache = $driver ? Cache::driver($driver) : Cache::store();

        $this->limiter = new RateLimiter($cache);
    }

    /**
     * Check if rate limit allows publishing a message.
     *
     * @param  string  $connection  The connection name
     * @return bool True if message can be published
     */
    public function allows(string $connection): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $key = $this->getKey($connection);
        $limits = $this->getLimitsForConnection($connection);

        // Check per-second limit first (more granular)
        if ($limits['max_per_second'] !== null) {
            if ($this->limiter->tooManyAttempts($key.':second', $limits['max_per_second'])) {
                return false;
            }
        }

        // Check per-minute limit
        if ($limits['max_per_minute'] !== null) {
            if ($this->limiter->tooManyAttempts($key.':minute', $limits['max_per_minute'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Attempt to publish a message, enforcing rate limits.
     *
     * @param  string  $connection  The connection name
     * @throws RateLimitExceededException If rate limit is exceeded
     */
    public function attempt(string $connection): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! $this->allows($connection)) {
            $this->handleRateLimitExceeded($connection);
        }

        $this->hit($connection);
    }

    /**
     * Record a hit for the given connection.
     *
     * Increments the rate limit counter. Called after successful
     * rate limit check to track usage.
     *
     * @param  string  $connection  The connection name
     */
    public function hit(string $connection): void
    {
        // Always track hits for potential metrics/monitoring
        // even when rate limiting is disabled
        if (! $this->isEnabled()) {
            return;
        }

        $key = $this->getKey($connection);
        $limits = $this->getLimitsForConnection($connection);

        // Hit per-second counter
        if ($limits['max_per_second'] !== null) {
            $this->limiter->hit($key.':second', 1);
        }

        // Hit per-minute counter
        if ($limits['max_per_minute'] !== null) {
            $this->limiter->hit($key.':minute', 60);
        }
    }

    /**
     * Get the number of remaining attempts for the connection.
     *
     * @param  string  $connection  The connection name
     * @return int Remaining attempts (most restrictive limit)
     */
    public function remaining(string $connection): int
    {
        if (! $this->isEnabled()) {
            return PHP_INT_MAX;
        }

        $key = $this->getKey($connection);
        $limits = $this->getLimitsForConnection($connection);

        $remaining = PHP_INT_MAX;

        // Check per-second remaining
        if ($limits['max_per_second'] !== null) {
            $remaining = min(
                $remaining,
                $this->limiter->remaining($key.':second', $limits['max_per_second'])
            );
        }

        // Check per-minute remaining
        if ($limits['max_per_minute'] !== null) {
            $remaining = min(
                $remaining,
                $this->limiter->remaining($key.':minute', $limits['max_per_minute'])
            );
        }

        return max(0, $remaining);
    }

    /**
     * Get seconds until rate limit resets for the connection.
     *
     * @param  string  $connection  The connection name
     * @return int Seconds until reset
     */
    public function availableIn(string $connection): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $key = $this->getKey($connection);
        $limits = $this->getLimitsForConnection($connection);

        $availableIn = 0;

        // Check per-second availability
        if ($limits['max_per_second'] !== null) {
            if ($this->limiter->tooManyAttempts($key.':second', $limits['max_per_second'])) {
                $availableIn = max(
                    $availableIn,
                    $this->limiter->availableIn($key.':second')
                );
            }
        }

        // Check per-minute availability
        if ($limits['max_per_minute'] !== null) {
            if ($this->limiter->tooManyAttempts($key.':minute', $limits['max_per_minute'])) {
                $availableIn = max(
                    $availableIn,
                    $this->limiter->availableIn($key.':minute')
                );
            }
        }

        return $availableIn;
    }

    /**
     * Clear rate limit counters for the connection.
     *
     * @param  string  $connection  The connection name
     */
    public function clear(string $connection): void
    {
        $key = $this->getKey($connection);

        $this->limiter->clear($key.':second');
        $this->limiter->clear($key.':minute');
    }

    /**
     * Handle rate limit exceeded scenario based on configured strategy.
     *
     * @param  string  $connection  The connection name
     * @throws RateLimitExceededException If strategy is 'reject'
     * @return int Seconds to wait if strategy is 'throttle'
     */
    protected function handleRateLimitExceeded(string $connection): int
    {
        $strategy = config('mqtt-broadcast.rate_limiting.strategy', 'reject');
        $retryAfter = $this->availableIn($connection);
        $limits = $this->getLimitsForConnection($connection);

        // Determine which limit was hit
        $key = $this->getKey($connection);
        $window = 'minute';
        $limit = $limits['max_per_minute'];

        if ($limits['max_per_second'] !== null &&
            $this->limiter->tooManyAttempts($key.':second', $limits['max_per_second'])) {
            $window = 'second';
            $limit = $limits['max_per_second'];
        }

        if ($strategy === 'reject') {
            throw new RateLimitExceededException(
                $connection,
                $limit,
                $window,
                $retryAfter
            );
        }

        // Strategy is 'throttle' - return retry delay
        return $retryAfter;
    }

    /**
     * Get the rate limit cache key for the connection.
     *
     * @param  string  $connection  The connection name
     * @return string The cache key
     */
    protected function getKey(string $connection): string
    {
        $byConnection = config('mqtt-broadcast.rate_limiting.by_connection', true);

        if ($byConnection) {
            return "mqtt_rate_limit:{$connection}";
        }

        return 'mqtt_rate_limit:global';
    }

    /**
     * Get rate limits for a specific connection.
     *
     * Checks for per-connection overrides in the connection config,
     * falls back to global defaults from rate_limiting section.
     *
     * @param  string  $connection  The connection name
     * @return array{max_per_minute: int|null, max_per_second: int|null}
     */
    protected function getLimitsForConnection(string $connection): array
    {
        // Check for per-connection override in connection config
        $connectionRateLimiting = config("mqtt-broadcast.connections.{$connection}.rate_limiting", []);

        return [
            'max_per_minute' => $connectionRateLimiting['max_per_minute'] ??
                config('mqtt-broadcast.defaults.connection.rate_limiting.max_per_minute'),
            'max_per_second' => $connectionRateLimiting['max_per_second'] ??
                config('mqtt-broadcast.defaults.connection.rate_limiting.max_per_second'),
        ];
    }

    /**
     * Check if rate limiting is enabled.
     *
     * @return bool True if rate limiting is enabled
     */
    protected function isEnabled(): bool
    {
        return config('mqtt-broadcast.rate_limiting.enabled', true);
    }
}
