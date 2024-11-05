<?php

namespace enzolarosa\MqttBroadcast\Commands;

use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;
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

        $this->components->info("I will send `$message` to `$topic` topic to`$broker` connection");

        MqttMessageJob::dispatch($topic, $message, $broker);

        $this->components->success('Done!');
    }
}
