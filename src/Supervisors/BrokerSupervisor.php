<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Supervisors;

use Closure;
use enzolarosa\MqttBroadcast\Contracts\Pausable;
use enzolarosa\MqttBroadcast\Contracts\Terminable;
use enzolarosa\MqttBroadcast\Factories\MqttClientFactory;
use enzolarosa\MqttBroadcast\MqttBroadcast;
use enzolarosa\MqttBroadcast\Repositories\BrokerRepository;
use PhpMqtt\Client\MqttClient;
use Throwable;

/**
 * Supervisor for managing a single MQTT broker connection.
 *
 * Inspired by Laravel Horizon's Supervisor pattern, this class handles:
 * - MQTT client lifecycle (connect, disconnect, reconnect)
 * - Message subscription and processing
 * - Heartbeat updates for health monitoring
 * - Graceful pause/resume/termination
 *
 * The supervisor is designed to be called by MasterSupervisor in a monitoring loop.
 * Exceptions are caught and passed to the output callback, allowing the caller
 * to decide on error reporting/logging strategy (no automatic report() calls).
 *
 * @see \enzolarosa\MqttBroadcast\Supervisors\MasterSupervisor
 */
class BrokerSupervisor implements Terminable, Pausable
{
    /**
     * The MQTT client instance.
     */
    protected ?MqttClient $client = null;

    /**
     * Indicates if the supervisor is currently working.
     */
    protected bool $working = true;

    /**
     * Number of consecutive connection failures.
     */
    protected int $retryCount = 0;

    /**
     * Maximum number of consecutive failures before action.
     */
    protected int $maxRetries;

    /**
     * Whether to terminate supervisor after max retries reached.
     */
    protected bool $terminateOnMaxRetries;

    /**
     * Timestamp of last retry attempt.
     */
    protected float $lastRetryAt = 0;

    /**
     * Current retry delay in seconds (exponential backoff).
     */
    protected int $retryDelay = 1;

    /**
     * Maximum retry delay in seconds.
     */
    protected int $maxRetryDelay;

    /**
     * Create a new broker supervisor instance.
     *
     * @param  string  $brokerName  Unique broker identifier
     * @param  string  $connection  MQTT connection name from config
     * @param  BrokerRepository  $repository  Repository for broker persistence
     * @param  MqttClientFactory  $clientFactory  Factory for creating MQTT clients
     * @param  Closure|null  $output  Optional callback for output messages (type, line)
     * @param  array|null  $options  Optional configuration overrides
     *
     * @throws \InvalidArgumentException If configuration values are invalid
     */
    public function __construct(
        protected string $brokerName,
        protected string $connection,
        protected BrokerRepository $repository,
        protected MqttClientFactory $clientFactory,
        protected ?Closure $output = null,
        ?array $options = null
    ) {
        // Load reconnection settings from config or options
        $this->maxRetries = $options['max_retries'] ??
            config('mqtt-broadcast.reconnection.max_retries', 20);

        $this->terminateOnMaxRetries = $options['terminate_on_max_retries'] ??
            config('mqtt-broadcast.reconnection.terminate_on_max_retries', false);

        $this->maxRetryDelay = $options['max_retry_delay'] ??
            config('mqtt-broadcast.reconnection.max_retry_delay', 60);

        // Validate configuration values
        $this->validateReconnectionConfig();
    }

    /**
     * Validate reconnection configuration values.
     *
     * @throws \InvalidArgumentException If any value is invalid
     */
    protected function validateReconnectionConfig(): void
    {
        if ($this->maxRetries < 1) {
            throw new \InvalidArgumentException('max_retries must be at least 1');
        }

        if ($this->maxRetryDelay < 1) {
            throw new \InvalidArgumentException('max_retry_delay must be at least 1');
        }

        if (! is_bool($this->terminateOnMaxRetries)) {
            throw new \InvalidArgumentException('terminate_on_max_retries must be boolean');
        }
    }

    /**
     * Monitor this broker supervisor.
     *
     * Called by MasterSupervisor in the main loop.
     * Handles connection, message processing, and heartbeat updates.
     * Implements exponential backoff retry logic for connection failures.
     */
    public function monitor(): void
    {
        if (! $this->working) {
            return;
        }

        // Handle connection phase with retry logic
        if (! $this->client || ! $this->client->isConnected()) {
            // Check if we should retry based on backoff logic
            if (! $this->shouldRetry()) {
                // Still in backoff window, skip this iteration
                return;
            }

            try {
                $this->connect();
                $this->onConnectSuccess();
            } catch (Throwable $e) {
                // Connection failed - apply backoff
                $this->onConnectFailure($e);

                return;
            }
        }

        // At this point, client should be connected
        // Handle message processing and heartbeat
        try {
            $this->client->loopOnce(microtime(true));
            $this->repository->touch($this->brokerName);
        } catch (Throwable $e) {
            // Operational error (not connection) - just log
            $this->output('error', $e->getMessage());
        }
    }

    /**
     * Check if we should retry connection based on exponential backoff.
     *
     * @return bool True if enough time has passed since last retry
     */
    protected function shouldRetry(): bool
    {
        $now = microtime(true);

        // First attempt or enough time has passed since last retry
        if ($this->lastRetryAt === 0.0 || ($now - $this->lastRetryAt) >= $this->retryDelay) {
            return true;
        }

        return false;
    }

    /**
     * Handle successful connection.
     *
     * Resets retry state when connection is established successfully.
     */
    protected function onConnectSuccess(): void
    {
        // Only reset if we had failures before
        if ($this->retryCount > 0) {
            $this->output('info', 'Connection restored successfully');
        }

        $this->retryCount = 0;
        $this->retryDelay = 1;
        $this->lastRetryAt = 0;
    }

    /**
     * Handle connection failure with exponential backoff.
     *
     * Increments retry count and applies exponential backoff delay.
     * After max retries, either terminates or resets with long pause.
     *
     * @param  Throwable  $e  The exception that caused the failure
     */
    protected function onConnectFailure(Throwable $e): void
    {
        $this->retryCount++;
        $this->lastRetryAt = microtime(true);

        // Exponential backoff: 1s, 2s, 4s, 8s, 16s, 32s, 60s (max)
        $this->retryDelay = min(
            (int) pow(2, $this->retryCount - 1),
            $this->maxRetryDelay
        );

        $this->output('error', sprintf(
            'Connection failed (attempt %d/%d): %s. Retrying in %ds...',
            $this->retryCount,
            $this->maxRetries,
            $e->getMessage(),
            $this->retryDelay
        ));

        // Check if max retries reached
        if ($this->retryCount >= $this->maxRetries) {
            if ($this->terminateOnMaxRetries) {
                // Hard terminate (fail-fast for MasterSupervisor)
                $this->output('error', sprintf(
                    'Max retries (%d) exceeded. Terminating supervisor.',
                    $this->maxRetries
                ));

                $this->terminate(1);
            } else {
                // Soft limit: reset cycle with long pause (backward compatible)
                $this->output('error', sprintf(
                    'Max retries (%d) exceeded. Pausing for %d seconds before reset...',
                    $this->maxRetries,
                    $this->maxRetryDelay
                ));

                $this->retryCount = 0;
                $this->retryDelay = $this->maxRetryDelay;
            }
        }
    }

    /**
     * Connect to MQTT broker and subscribe to topics.
     *
     * Creates a new MQTT client using the factory, connects to the broker,
     * and subscribes to all topics matching the configured prefix pattern.
     *
     * @throws \Exception If connection or subscription fails
     */
    protected function connect(): void
    {
        $this->client = $this->clientFactory->create($this->connection);

        // Get connection settings for authentication
        $connectionInfo = $this->clientFactory->getConnectionSettings($this->connection);

        // Connect with or without authentication
        if ($connectionInfo['settings']) {
            $this->client->connect(
                $connectionInfo['settings'],
                $connectionInfo['cleanSession']
            );
        } else {
            $this->client->connect();
        }

        // Get subscription configuration
        $config = config("mqtt-broadcast.connections.{$this->connection}");
        $prefix = $config['prefix'] ?? '';
        $qos = $config['qos'] ?? 0;

        // Subscribe to all topics under prefix
        $topic = $prefix === '' ? '#' : $prefix.'#';

        $this->client->subscribe($topic, function (string $topic, string $message) {
            $this->handleMessage($topic, $message);
        }, $qos);

        $this->output('info', "Connected to broker: {$this->connection}");
    }

    /**
     * Handle received MQTT message.
     *
     * Processes incoming messages by dispatching MqttMessageReceived event
     * through the MqttBroadcast facade. Exceptions are caught to prevent
     * message processing errors from crashing the supervisor.
     *
     * @param  string  $topic  The MQTT topic
     * @param  string  $message  The message payload
     */
    protected function handleMessage(string $topic, string $message): void
    {
        $this->output('info', sprintf('Received message on [%s]: %s', $topic, $message));

        try {
            MqttBroadcast::received($topic, $message, $this->brokerName);
        } catch (Throwable $e) {
            $this->output('error', $e->getMessage());
        }
    }

    /**
     * Pause the supervisor.
     *
     * Stops processing MQTT messages but keeps the connection alive.
     * The monitor() method will return early when paused.
     */
    public function pause(): void
    {
        $this->working = false;
    }

    /**
     * Continue the supervisor.
     *
     * Resumes processing MQTT messages after being paused.
     */
    public function continue(): void
    {
        $this->working = true;
    }

    /**
     * Terminate the supervisor.
     *
     * Performs graceful shutdown:
     * 1. Stops accepting new messages
     * 2. Disconnects from MQTT broker
     * 3. Removes broker record from repository
     *
     * Disconnect errors are silently swallowed to ensure cleanup completes.
     *
     * @param  int  $status  Exit status code (unused, for interface compatibility)
     */
    public function terminate($status = 0): void
    {
        $this->working = false;

        // Disconnect from MQTT broker if connected
        if ($this->client?->isConnected()) {
            try {
                $this->client->disconnect();
            } catch (Throwable $e) {
                // Swallow disconnect errors to ensure repository cleanup
                // The connection is being terminated anyway
            }
        }

        // Remove broker record from database
        $this->repository->delete($this->brokerName);
    }

    /**
     * Check if the supervisor is currently working.
     *
     * @return bool True if the supervisor is active and processing messages
     */
    public function isWorking(): bool
    {
        return $this->working;
    }

    /**
     * Output a message via the callback.
     *
     * Calls the output callback if provided. Used for logging and
     * console output without coupling the supervisor to specific
     * logging implementations.
     *
     * @param  string  $type  Message type (info, error, etc.)
     * @param  string  $line  Message content
     */
    protected function output(string $type, string $line): void
    {
        if ($this->output) {
            call_user_func($this->output, $type, $line);
        }
    }
}
