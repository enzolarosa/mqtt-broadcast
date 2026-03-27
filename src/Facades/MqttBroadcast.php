<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void received(string $topic, string $message, string $broker = 'default')
 * @method static void publish(string $topic, string $message, string $broker = 'default', int $qos = 0)
 * @method static void publishSync(string $topic, mixed $message, string $broker = 'default', int $qos = 0)
 * @method static string getTopic(string $topic, string $broker = 'default')
 *
 * @see \enzolarosa\MqttBroadcast\MqttBroadcast
 */
class MqttBroadcast extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \enzolarosa\MqttBroadcast\MqttBroadcast::class;
    }
}
