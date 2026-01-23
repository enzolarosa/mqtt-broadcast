<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;

class MqttBroadcast
{
    public static function received(string $topic, string $message, string $broker = 'default')
    {
        event(new MqttMessageReceived($topic, $message, $broker));
    }

    /**
     * Publishes a message to a specified MQTT topic.
     *
     * @param  string  $topic  The MQTT topic to publish the message to.
     * @param  string  $message  The message content to publish.
     * @param  string  $broker  The broker to use for the connection (default is 'default').
     * @param  int  $qos  The Quality of Service level for message delivery (0, 1, or 2).
     */
    public static function publish(
        string $topic,
        string $message,
        string $broker = 'default',
        int $qos = 0,
    ): void {
        MqttMessageJob::dispatch($topic, $message, $broker, $qos);
    }

    /**
     * Publish a message synchronously to the specified MQTT topic.
     *
     * @param  string  $topic  The name of the topic where the message will be published.
     * @param  mixed  $message  The message content to be published.
     * @param  string  $broker  The name of the broker connection. Defaults to 'default'.
     * @param  int  $qos  The Quality of Service level for message delivery (0, 1, or 2).
     */
    public static function publishSync(
        string $topic,
        mixed $message,
        string $broker = 'default',
        int $qos = 0,
    ): void {
        MqttMessageJob::dispatchSync($topic, $message, $broker, $qos);
    }

    /**
     * Retrieve the full topic name with the specified broker prefix.
     *
     * @param  string  $topic  The name of the topic.
     * @param  string  $broker  The name of the broker connection. Defaults to 'default'.
     * @return string The full topic name with the broker prefix.
     */
    public static function getTopic(string $topic, string $broker = 'default'): string
    {
        $prefix = config("mqtt-broadcast.connections.{$broker}.prefix", '');

        return $prefix.$topic;
    }
}
