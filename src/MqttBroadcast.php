<?php

namespace enzolarosa\MqttBroadcast;

use Exception;

class MqttBroadcast
{
    /**
     * Configure the Redis databases that will store Horizon data.
     *
     * @param  string  $connection
     * @return void
     *
     * @throws Exception
     */
    public static function use($connection)
    {
        if (! is_null($config = config("database.redis.clusters.{$connection}.0"))) {
            config(["database.redis.{$connection}" => $config]);
        } elseif (is_null($config) && is_null($config = config("database.redis.{$connection}"))) {
            throw new Exception("Redis connection [{$connection}] has not been configured.");
        }

        $config['options']['prefix'] = config('mqtt-broadcast.prefix') ?: 'mqtt-broadcast:';

        config(['database.redis.mqtt-broadcast' => $config]);
    }
}
