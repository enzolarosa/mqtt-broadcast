<?php

namespace enzolarosa\MqttBroadcast\Database\Factories;

use enzolarosa\MqttBroadcast\Models\MqttLogger;
use Illuminate\Database\Eloquent\Factories\Factory;

class MqttLoggerFactory extends Factory
{
    protected $model = MqttLogger::class;

    public function definition()
    {
        return [
            'broker' => 'broker',
            'topic' => 'topic',
            'message' => ['msg' => 'Hi'],
        ];
    }
}

