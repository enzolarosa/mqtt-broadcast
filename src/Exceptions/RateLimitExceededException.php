<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Exceptions;

use RuntimeException;

/**
 * Exception thrown when MQTT publishing rate limit is exceeded.
 *
 * This exception is thrown when the configured rate limit for MQTT
 * message publishing is exceeded, protecting the broker from being
 * overwhelmed by too many messages.
 */
class RateLimitExceededException extends RuntimeException
{
    /**
     * Create a new rate limit exceeded exception.
     *
     * @param  string  $connection  The connection name that hit the limit
     * @param  int  $limit  The maximum number of messages allowed
     * @param  string  $window  The time window (e.g., 'minute', 'second')
     * @param  int  $retryAfter  Seconds until rate limit resets
     */
    public function __construct(
        protected string $connection,
        protected int $limit,
        protected string $window,
        protected int $retryAfter
    ) {
        $message = sprintf(
            'Rate limit exceeded for connection "%s": %d messages per %s. Retry after %d seconds.',
            $connection,
            $limit,
            $window,
            $retryAfter
        );

        parent::__construct($message);
    }

    /**
     * Get the connection name that hit the rate limit.
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * Get the rate limit threshold.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Get the time window for the rate limit.
     */
    public function getWindow(): string
    {
        return $this->window;
    }

    /**
     * Get seconds until rate limit resets.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
