<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Jobs;

use enzolarosa\MqttBroadcast\Exceptions\InvalidBrokerException;
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
        protected mixed $message,
        protected ?string $broker = 'default',
        protected ?int $qos = null,
        protected bool $cleanSession = true,
    ) {
        $brokerConfig = config("mqtt-broadcast.connections.{$broker}");

        throw_if(
            is_null($brokerConfig),
            InvalidBrokerException::notConfigured($broker)
        );

        throw_if(
            !isset($brokerConfig['host']),
            InvalidBrokerException::missingConfiguration($broker, 'host')
        );

        throw_if(
            !isset($brokerConfig['port']),
            InvalidBrokerException::missingConfiguration($broker, 'port')
        );

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
        $mqtt = $this->mqtt();

        try {
            if (!$mqtt->isConnected()) {
                $mqtt->connect();
            }

            if (!is_string($this->message)) {
                $this->message = json_encode($this->message, JSON_THROW_ON_ERROR);
            }

            $qos = $this->qos ?? config('mqtt-broadcast.connections.'.$this->broker.'.qos', 0);
            $retain = config('mqtt-broadcast.connections.'.$this->broker.'.retain', false);

            $mqtt->publish(
                MqttBroadcast::getTopic($this->topic, $this->broker),
                $this->message,
                $qos,
                $retain,
            );
        } finally {
            if ($mqtt->isConnected()) {
                $mqtt->disconnect();
            }
        }
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
            $cleanSession = $this->cleanSession;
            $keepAliveInterval = config("mqtt-broadcast.connections.$connection.alive_interval", 60);
            $connectionTimeout = config("mqtt-broadcast.connections.$connection.timeout", 3);
            $useTls = config("mqtt-broadcast.connections.$connection.use_tls", true);
            $selfSignedAllowed = config("mqtt-broadcast.connections.$connection.self_signed_allowed", true);

            $connectionSettings = (new ConnectionSettings)
                ->setKeepAliveInterval($keepAliveInterval)
                ->setConnectTimeout($connectionTimeout)
                ->setUseTls($useTls)
                ->setTlsSelfSignedAllowed($selfSignedAllowed)
                ->setUsername($username)
                ->setPassword($password);

            $mqtt->connect($connectionSettings, $cleanSession);
        }

        return $mqtt;
    }
}
