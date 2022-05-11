<?php

namespace enzolarosa\MqttBroadcast\Commands;

use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;
use Illuminate\Console\Command;

class MqttBroadcastTestCommand extends Command
{
    protected $signature = 'mqtt-broadcast:test {topic} {message}';

    protected $description = 'Test mqtt server';

    public function handle()
    {
        $topic = $this->argument('topic');
        $message = $this->argument('message');

        $this->comment("I will send `$message` to `$topic` topic");

        MqttMessageJob::dispatch($topic, $message);

        $this->comment('Done!');
    }
}
