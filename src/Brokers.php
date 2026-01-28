<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast;

use Closure;
use enzolarosa\MqttBroadcast\Contracts\Terminable;
use enzolarosa\MqttBroadcast\Supervisors\BrokerSupervisor;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Str;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Throwable;

/**
 * @deprecated since 2.5.0, use BrokerSupervisor + MqttClientFactory instead
 *
 * This class is deprecated and will be removed in v3.0. Use the new architecture:
 * - BrokerSupervisor for broker monitoring and management
 * - MqttClientFactory for MQTT client creation
 * - BrokerRepository for broker persistence operations
 *
 * @see \enzolarosa\MqttBroadcast\Supervisors\BrokerSupervisor
 * @see \enzolarosa\MqttBroadcast\Factories\MqttClientFactory
 * @see \enzolarosa\MqttBroadcast\Repositories\BrokerRepository
 */
class Brokers implements Terminable
{
    use ListensForSignals;

    public Models\BrokerProcess $broker;

    public ?MqttClient $client = null;

    /**
     * The output handler.
     *
     * @var Closure|null
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
        Models\BrokerProcess::query()->where('pid', $pid)->delete();
    }

    public function make($connection)
    {
        trigger_deprecation(
            'enzolarosa/mqtt-broadcast',
            '2.5.0',
            'The "%s" class is deprecated, use "%s" instead.',
            self::class,
            BrokerSupervisor::class
        );

        $this->broker = Models\BrokerProcess::query()->create([
            'name' => static::name(),
            'connection' => $connection,
            'pid' => self::pid(),
            'started_at' => now(),
            'working' => true,
        ]);

        return $this;
    }

    public function find($name)
    {
        return Models\BrokerProcess::query()->where('name', $name)->first();
    }

    public function all()
    {
        return Models\BrokerProcess::query()->get();
    }

    public function client($name, $randomId = false): MqttClient
    {
        $broker = $this->find($name);

        $connection = $broker->connection;

        $server = config("mqtt-broadcast.connections.$connection.host");
        $clientId = $randomId ? Str::uuid()->toString() : config("mqtt-broadcast.connections.$connection.clientId",
            Str::uuid()->toString());
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
            $selfSignedAllowed = config("mqtt-broadcast.connections.$connection.self_signed_allowed", true);

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

    public function terminate($status = 0): never
    {
        if ($this->client?->isConnected()) {
            try {
                $this->client->disconnect();
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->broker->delete();

        $this->exit($status);
    }

    public function monitor(): void
    {
        $this->listenForSignals();

        $this->persist();

        $this->client = $this->client($this->broker->name);

        if (!$this->client->isConnected()) {
            $this->client->connect();
        }

        $connection = $this->broker->connection;
        $prefix = config("mqtt-broadcast.connections.$connection.prefix", '');
        $qos = config("mqtt-broadcast.connections.$connection.qos", 0);

        $this->client->subscribe($prefix.'#', function ($topic, $message) {
            $this->output('info', sprintf('Received message on topic [%s]: %s', $topic, $message));

            try {
                MqttBroadcast::received($topic, $message, $this->broker->name);
            } catch (Throwable $exception) {
                report($exception);
                $this->output('error', $exception->getMessage());
            }
        }, $qos);

        while (true) {
            sleep(1);

            $this->loop();
        }
    }

    public function loop(): void
    {
        $loopStartedAt = microtime(true);

        try {
            $this->processPendingSignals();

            if ($this->broker->working) {
                $this->client->loopOnce($loopStartedAt);
            }
        } catch (Throwable $e) {
            app(ExceptionHandler::class)->report($e);
        }
    }

    public function persist()
    {
        $this->broker->touch('started_at');
    }

    /**
     * Set the output handler.
     *
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

    protected function exit($status = 0): never
    {
        $this->exitProcess($status);
    }

    protected function exitProcess($status = 0): never
    {
        exit((int) $status);
    }
}
