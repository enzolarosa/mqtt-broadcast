<?php

namespace enzolarosa\MqttBroadcast\Commands;

use enzolarosa\MqttBroadcast\MqttBroadcast;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'mqtt-broadcast:test', description: 'Test mqtt server')]
class MqttBroadcastTestCommand extends Command
{
    protected $signature = 'mqtt-broadcast:test {broker} {topic} {message}';

    protected $description = 'Test mqtt server';

    public function handle()
    {
        $broker = $this->argument('broker');
        $topic = $this->argument('topic');
        $message = $this->argument('message');

        $this->components->task("Sending a message to $broker broker",
            function () use ($broker, $topic, $message) {
                return MqttBroadcast::publishSync($topic, $message, $broker);
            });
    }
}
