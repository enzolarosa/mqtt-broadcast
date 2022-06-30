<?php

namespace enzolarosa\MqttBroadcast\Commands;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpMqtt\Client\MqttClient;
use Throwable;

class MqttBroadcastCommand extends Command
{
    public $signature = 'mqtt-broadcast {broker}';

    protected $description = 'Listener for mqtt message';

    public function handle(): int
    {
        $clientId = Str::uuid()->toString();

        $broker = $this->argument('broker');
        $server = config("mqtt-broadcast.connections.$broker.host");
        $port = config("mqtt-broadcast.connections.$broker.port");

        Cache::put("mqtt_listener_$broker", $this->pid());

        $mqtt = new MqttClient($server, $port, $clientId);
        $mqtt->connect();

        $this->info("MQTT Broadcast started listener `$broker` successfully at: " . now()->toIso8601String());

        $mqtt->subscribe('#', function ($topic, $message) use ($broker) {
            $this->comment(sprintf('Received message on topic [%s]: %s', $topic, $message));
            try {
                MqttMessageReceived::dispatch(
                    $topic,
                    $message,
                    $broker,
                    $this->pid()
                );
            } catch (Throwable $exception) {
                report($exception);
                $this->error("\t{$exception->getMessage()}");
            }
        }, 0);

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () use ($mqtt) {
            $this->line('Shutting down...');

            if (!posix_kill($this->pid(), SIGTERM)) {
                $this->error("Failed to kill process: {$this->pid()} (" . posix_strerror(posix_get_last_error()) . ')');
            }
        });

        $mqtt->loop();
        $mqtt->disconnect();

        return self::SUCCESS;
    }

    public function pid()
    {
        return getmypid();
    }
}
