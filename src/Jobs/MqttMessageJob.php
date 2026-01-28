<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Jobs;

use enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException;
use enzolarosa\MqttBroadcast\Exceptions\RateLimitExceededException;
use enzolarosa\MqttBroadcast\Factories\MqttClientFactory;
use enzolarosa\MqttBroadcast\MqttBroadcast;
use enzolarosa\MqttBroadcast\Support\RateLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\RepositoryException;
use PhpMqtt\Client\MqttClient;

class MqttMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $cachedQos;

    protected bool $cachedRetain;

    public function __construct(
        protected string $topic,
        protected mixed $message,
        protected ?string $broker = 'default',
        protected ?int $qos = null,
        protected bool $cleanSession = true,
    ) {
        // Validation is now handled by MqttClientFactory in handle()
        // This allows the job to be dispatched successfully and fail
        // with proper exception handling in the worker

        // Cache config values to avoid repeated calls in handle()
        $this->cachedQos = $this->qos ?? config('mqtt-broadcast.connections.'.$this->broker.'.qos', 0);
        $this->cachedRetain = config('mqtt-broadcast.connections.'.$this->broker.'.retain', false);

        $queue = config('mqtt-broadcast.queue.name');
        $connection = config('mqtt-broadcast.queue.connection');

        if ($queue) {
            $this->onQueue($queue);
        }

        if ($connection) {
            $this->onConnection($connection);
        }
    }

    public function handle(): void
    {
        // Check rate limit before processing (second layer of protection)
        $this->checkRateLimit();

        // Fail-fast: If connection config is invalid, fail immediately
        // without retrying (config errors won't fix themselves)
        try {
            $mqtt = $this->mqtt();
        } catch (MqttBroadcastException $e) {
            // Configuration error - fail the job without retry
            $this->fail($e);

            return;
        }

        try {
            if (!$mqtt->isConnected()) {
                $mqtt->connect();
            }

            if (!is_string($this->message)) {
                $this->message = json_encode($this->message, JSON_THROW_ON_ERROR);
            }

            $mqtt->publish(
                MqttBroadcast::getTopic($this->topic, $this->broker),
                $this->message,
                $this->cachedQos,
                $this->cachedRetain,
            );
        } finally {
            if ($mqtt->isConnected()) {
                $mqtt->disconnect();
            }
        }
    }

    /**
     * Check rate limit before publishing.
     *
     * Handles both 'reject' and 'throttle' strategies.
     * For 'throttle' strategy, releases the job back to queue with delay.
     * For 'reject' strategy, attempt() will throw RateLimitExceededException.
     */
    protected function checkRateLimit(): void
    {
        $rateLimiter = app(RateLimitService::class);
        $strategy = config('mqtt-broadcast.rate_limiting.strategy', 'reject');

        // If rate limit allows, proceed
        if ($rateLimiter->allows($this->broker)) {
            $rateLimiter->hit($this->broker);
            return;
        }

        // Rate limit exceeded
        if ($strategy === 'throttle') {
            // Calculate delay and requeue the job
            $delay = $rateLimiter->availableIn($this->broker);
            $this->release($delay);

            return;
        }

        // Strategy is 'reject' - let attempt() throw the exception
        // (it will build the exception with proper context)
        $rateLimiter->attempt($this->broker);
    }

    /**
     * Create and configure MQTT client using factory.
     *
     * @throws MqttBroadcastException If connection config is invalid
     */
    private function mqtt(): MqttClient
    {
        $factory = app(MqttClientFactory::class);

        // Create client (validates config: connection exists, host/port present)
        $client = $factory->create($this->broker);

        // Get connection settings for authentication
        $connectionInfo = $factory->getConnectionSettings(
            $this->broker,
            $this->cleanSession
        );

        // Connect with authentication if required
        if ($connectionInfo['settings']) {
            $client->connect(
                $connectionInfo['settings'],
                $connectionInfo['cleanSession']
            );
        }

        return $client;
    }
}
