<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException;
use enzolarosa\MqttBroadcast\Facades\MqttBroadcast;
use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;
use Illuminate\Support\Facades\Queue;

describe('MqttBroadcast Facade', function () {
    beforeEach(function () {
        Queue::fake();
    });

    it('dispatches job when publishing a message', function () {
        MqttBroadcast::publish('sensors/temperature', '{"value": 25.5}');

        Queue::assertPushed(MqttMessageJob::class, function ($job) {
            return $this->getProtectedProperty($job, 'topic') === 'sensors/temperature'
                && $this->getProtectedProperty($job, 'message') === '{"value": 25.5}'
                && $this->getProtectedProperty($job, 'broker') === 'default'
                && $this->getProtectedProperty($job, 'qos') === 0;
        });
    });

    it('dispatches job with custom broker', function () {
        $this->setMqttConfig('custom', [
            'host' => '192.168.1.100',
            'port' => 1883,
        ]);

        MqttBroadcast::publish('test/topic', 'message', 'custom');

        Queue::assertPushed(MqttMessageJob::class, function ($job) {
            return $this->getProtectedProperty($job, 'broker') === 'custom';
        });
    });

    it('dispatches job with custom qos', function () {
        MqttBroadcast::publish('test/topic', 'message', 'default', 2);

        Queue::assertPushed(MqttMessageJob::class, function ($job) {
            return $this->getProtectedProperty($job, 'qos') === 2;
        });
    });

    it('dispatches sync job when using publishSync', function () {
        MqttBroadcast::publishSync('test/topic', 'message');

        Queue::assertPushed(MqttMessageJob::class);
    });

    it('accepts mixed message types in publishSync', function () {
        MqttBroadcast::publishSync('test/topic', ['data' => 'value']);

        Queue::assertPushed(MqttMessageJob::class, function ($job) {
            return $this->getProtectedProperty($job, 'message') === ['data' => 'value'];
        });
    });

    it('returns topic with prefix from config', function () {
        config(['mqtt-broadcast.connections.default.prefix' => 'app/']);

        $topic = MqttBroadcast::getTopic('sensors/temp');

        expect($topic)->toBe('app/sensors/temp');
    });

    it('returns topic without prefix when not configured', function () {
        config(['mqtt-broadcast.connections.default.prefix' => '']);

        $topic = MqttBroadcast::getTopic('sensors/temp');

        expect($topic)->toBe('sensors/temp');
    });

    it('throws exception when broker does not exist', function () {
        MqttBroadcast::publish('test/topic', 'message', 'nonexistent');
    })->throws(MqttBroadcastException::class, 'Broker connection [nonexistent] is not configured');

    it('throws exception when broker is missing host', function () {
        config(['mqtt-broadcast.connections.broken' => [
            'port' => 1883,
        ]]);

        MqttBroadcast::publish('test/topic', 'message', 'broken');
    })->throws(MqttBroadcastException::class, 'missing required key [host]');

    it('throws exception when broker is missing port', function () {
        config(['mqtt-broadcast.connections.broken' => [
            'host' => 'localhost',
        ]]);

        MqttBroadcast::publish('test/topic', 'message', 'broken');
    })->throws(MqttBroadcastException::class, 'missing required key [port]');

    it('validates broker before publishing sync', function () {
        expect(fn () => MqttBroadcast::publishSync('test', 'msg', 'nonexistent'))
            ->toThrow(MqttBroadcastException::class);
    });

    it('validates broker before getting topic', function () {
        expect(fn () => MqttBroadcast::getTopic('test', 'nonexistent'))
            ->toThrow(MqttBroadcastException::class);
    });
});
