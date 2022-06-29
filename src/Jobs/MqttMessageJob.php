<?php

namespace enzolarosa\MqttBroadcast\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
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

    protected static array $clientId = [];

    public function __construct(
        protected string  $topic,
        protected         $message,
        protected ?string $broker = 'local',
        protected int     $qos = 0)
    {
        self::$clientId[$this->broker] = Str::uuid();
        $this->onQueue(config('mqtt-broadcast.queue.name'));
        $this->onConnection(config('mqtt-broadcast.queue.connection'));
    }

    public function middleware()
    {
        $middleware = [];
        if (config('mqtt-broadcast.queue.middleware')) {
            $middleware = [(new RateLimited(config('mqtt-broadcast.queue.middleware')))];
        }

        return $middleware;
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
        $server = config("mqtt-broadcast.connections.$this->broker.host");
        $port = config("mqtt-broadcast.connections.$this->broker.port");

        if (!is_string($this->message)) {
            $this->message = json_encode($this->message);
        }

        $mqtt = new MqttClient($server, $port, self::$clientId[$this->broker]);
        $mqtt->connect();
        $mqtt->publish($this->topic, $this->message, $this->qos);
        $mqtt->disconnect();
    }
}
