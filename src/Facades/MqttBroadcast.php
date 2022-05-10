<?php

namespace enzolarosa\MqttBroadcast\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \enzolarosa\MqttBroadcast\MqttBroadcast
 */
class MqttBroadcast extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'mqtt-broadcast';
    }
}
