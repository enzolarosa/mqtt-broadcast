<?php

namespace enzolarosa\MqttBroadcast\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\ProtocolNotSupportedException;
use PhpMqtt\Client\Exceptions\RepositoryException;
use PhpMqtt\Client\MqttClient;

class MqttMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected string $broker;
    protected string $topic;
    protected string $message;
    protected int $qos;
    protected static array $clientId;

    public function __construct(string $topic, string $message, ?string $broker = 'local', int $qos = 0, ?string $clientId = null)
    {
        $this->topic = $topic;
        $this->message = $message;
        $this->qos = $qos;
        $this->broker = $broker;

        self::$clientId[$this->broker] = $clientId ?? Str::uuid();
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws DataTransferException
     * @throws ProtocolNotSupportedException
     * @throws RepositoryException
     * @throws ConfigurationInvalidException
     * @throws ConnectingToBrokerFailedException
     */
    public function handle()
    {
        $server = config("fw.mqtt.$this->broker.host");
        $port = config("fw.mqtt.$this->broker.port");

        $mqtt = new MqttClient($server, $port, self::$clientId[$this->broker]);
        $mqtt->connect();
        $mqtt->publish($this->topic, $this->message, $this->qos);
        $mqtt->disconnect();
    }
}
