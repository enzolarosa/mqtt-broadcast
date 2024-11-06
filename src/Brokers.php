<?php

namespace enzolarosa\MqttBroadcast;

use Closure;
use enzolarosa\MqttBroadcast\Contracts\Terminable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Str;
use PhpMqtt\Client\MqttClient;
use Throwable;

class Brokers implements Terminable
{
    use ListensForSignals;

    public Models\Brokers $broker;

    /** @var MqttClient */
    public $client;

    /**
     * The output handler.
     *
     * @var \Closure|null
     */
    public $output;

    public static function pid()
    {
        return getmypid();
    }

    public static function name()
    {
        static $token;

        if (!$token) {
            $token = Str::random(4);
        }

        return static::basename().'-'.$token;
    }

    public static function basename()
    {
        return Str::slug(gethostname());
    }

    public static function terminateByPid($pid)
    {
        Models\Brokers::query()->where('pid', $pid)->delete();
    }

    public function make($connection)
    {
        $this->broker = Models\Brokers::query()->create([
            'name' => static::name(),
            'connection' => $connection,
            'pid' => Brokers::pid(),
            'started_at' => now(),
            'working' => true,
        ]);

        return $this;
    }

    public function find($name)
    {
        return Models\Brokers::query()->where('name', $name)->first();
    }

    public function all()
    {
        return Models\Brokers::query()->get();
    }

    public function client($name): MqttClient
    {
        $broker = $this->find($name);

        $clientId = Str::uuid()->toString();

        $connection = $broker->connection;

        $server = config("mqtt-broadcast.connections.$connection.host");
        $port = config("mqtt-broadcast.connections.$connection.port");

        return new MqttClient($server, $port, $clientId);
    }

    public function terminate($status = 0)
    {
        $this->broker->delete();

        $this->exit($status);
    }

    public function monitor()
    {
        $this->listenForSignals();

        $this->persist();

        $client = $this->client($this->broker->name);
        $client->connect();
        $client->subscribe('#', function ($topic, $message) {
            $this->output('info', sprintf('Received message on topic [%s]: %s', $topic, $message));

            try {
                MqttBroadcast::received($topic, $message, $this->broker->name);
            } catch (Throwable $exception) {
                report($exception);
                $this->output('error', $exception->getMessage());
            }
        });

        while (true) {
            sleep(1);

            $this->loop($client);
        }
    }

    public function loop(MqttClient $client)
    {
        $loopStartedAt = microtime(true);

        try {
            $this->processPendingSignals();

            if ($this->broker->working) {
                $client->loopOnce($loopStartedAt);
            }
        } catch (Throwable $e) {
            app(ExceptionHandler::class)->report($e);
        }
    }

    public function persist()
    {
        $this->broker->touch('started_at');
    }

    protected function exit($status = 0)
    {
        $this->exitProcess($status);
    }

    protected function exitProcess($status = 0)
    {
        exit((int) $status);
    }

    /**
     * Set the output handler.
     *
     * @param  Closure  $callback
     * @return $this
     */
    public function handleOutputUsing(Closure $callback)
    {
        $this->output = $callback;

        return $this;
    }

    public function output($type, $line)
    {
        call_user_func($this->output, $type, $line);
    }
}
