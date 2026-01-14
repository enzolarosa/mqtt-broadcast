<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Jobs;

use enzolarosa\MqttBroadcast\MqttBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\RepositoryException;
use PhpMqtt\Client\MqttClient;

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
        $mqtt = $this->mqtt();

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

    private function mqtt(): MqttClient
    {
        $connection = $this->broker;
        $clientId = Str::uuid()->toString();

        $server = config("mqtt-broadcast.connections.$connection.host");
        $port = config("mqtt-broadcast.connections.$connection.port");
        $authentication = config("mqtt-broadcast.connections.$connection.auth", false);

        $mqtt = new MqttClient($server, $port, $clientId);

        if ($authentication) {
            $username = config("mqtt-broadcast.connections.$connection.username");
            $password = config("mqtt-broadcast.connections.$connection.password");
            $clean_session = config("mqtt-broadcast.connections.$connection.clean_session", false);
            $keepAliveInterval = config("mqtt-broadcast.connections.$connection.alive_interval", 60);
            $connectionTimeout = config("mqtt-broadcast.connections.$connection.timeout", 3);
            $useTls = config("mqtt-broadcast.connections.$connection.use_tls", true);
            $selfSignedAllowed = config("mqtt-broadcast.connections.$connection.self_aligned_allowed", true);

            $connectionSettings = (new ConnectionSettings)
                ->setKeepAliveInterval($keepAliveInterval)
                ->setConnectTimeout($connectionTimeout)
                ->setUseTls($useTls)
                ->setTlsSelfSignedAllowed($selfSignedAllowed)
                ->setUsername($username)
                ->setPassword($password);

            $mqtt->connect($connectionSettings, $clean_session);
        }

        return $mqtt;
    }
}
