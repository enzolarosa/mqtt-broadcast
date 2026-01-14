<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Jobs;

use enzolarosa\MqttBroadcast\Brokers;
use enzolarosa\MqttBroadcast\MqttBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\RepositoryException;

class MqttMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $topic,
        protected $message,
        protected ?string $broker = 'default',
    ) {
        $queue = config('mqtt-broadcast.queue.name');
        $connection = config('mqtt-broadcast.queue.connection');

        if ($queue) {
            $this->onQueue($queue);
        }

        if ($connection) {
            $this->onConnection($connection);
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws DataTransferException
     * @throws RepositoryException
     * @throws ConfigurationInvalidException
     * @throws ConnectingToBrokerFailedException
     */
    public function handle()
    {
        $mqtt = (new Brokers)
            ->make($this->broker)
            ->client(name: $this->broker, randomId: true);

        if (!$mqtt->isConnected()) {
            $mqtt->connect();
        }

        if (!is_string($this->message)) {
            $this->message = json_encode($this->message);
        }

        $qos = config('mqtt-broadcast.connections.'.$this->broker.'.qos', 0);

        $mqtt->publish(
            MqttBroadcast::getTopic($this->topic, $this->broker),
            $this->message,
            $qos,
        );

        $mqtt->disconnect();
    }
}
