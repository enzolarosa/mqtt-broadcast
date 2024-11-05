<?php

namespace enzolarosa\MqttBroadcast\Commands;

use enzolarosa\MqttBroadcast\Contracts\MqttSupervisorRepository;
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\MqttSupervisor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpMqtt\Client\MqttClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'mqtt-broadcast', description: 'Mqtt Broadcast Command')]
class MqttBroadcastCommand extends Command
{
    public $signature = 'mqtt-broadcast {broker}';

    protected $description = 'Listener for mqtt message';

    public function handle(MqttSupervisorRepository $supervisor)
    {
        if ($supervisor->find(MqttSupervisor::name())) {
            return $this->components->warn('A master supervisor is already running on this machine.');
        }

        $broker = $this->argument('broker');

        $master = (new MqttSupervisor($broker))->handleOutputUsing(function ($type, $line) {
            $this->output->write($line);
        });

        $this->components->info(sprintf('Mqtt Broadcast for %s broker started successfully.', $broker));

        $clientId = Str::uuid()->toString();
        $server = config("mqtt-broadcast.connections.$broker.host");
        $port = config("mqtt-broadcast.connections.$broker.port");

        $mqtt = new MqttClient($server, $port, $clientId);
        $mqtt->connect();

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () use ($master, $mqtt) {
            $this->output->writeln('');

            $this->components->info('Shutting down.');

            $mqtt->disconnect();

            return $master->terminate();
        });

        $master->monitor(function () use ($broker, $mqtt) {
            $mqtt->subscribe('#', function ($topic, $message) use ($broker) {
                $this->output->writeln(sprintf('Received message on topic [%s]: %s', $topic, $message));
                try {
                    MqttMessageReceived::dispatch(
                        $topic,
                        $message,
                        $broker,
                    );
                } catch (Throwable $exception) {
                    report($exception);
                    $this->components->error("\t{$exception->getMessage()}");
                }
            }, 0);

            $mqtt->loop();
        });
    }
}
