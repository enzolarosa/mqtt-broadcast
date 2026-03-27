<?php

use enzolarosa\MqttBroadcast\Exceptions\MqttBroadcastException;
use enzolarosa\MqttBroadcast\Support\MqttConnectionConfig;

beforeEach(function () {
    // Valid config for testing
    $this->validConfig = [
        'host' => 'mqtt.example.com',
        'port' => 1883,
        'auth' => false,
        'username' => null,
        'password' => null,
        'qos' => 0,
        'retain' => false,
        'prefix' => 'myapp/',
        'clean_session' => false,
        'clientId' => 'test-client',
        'alive_interval' => 60,
        'timeout' => 3,
        'use_tls' => false,
        'self_signed_allowed' => true,
    ];
});

describe('MqttConnectionConfig → Creation', function () {
    test('it creates config from valid array', function () {
        $config = MqttConnectionConfig::fromArray($this->validConfig);

        expect($config)->toBeInstanceOf(MqttConnectionConfig::class)
            ->and($config->host())->toBe('mqtt.example.com')
            ->and($config->port())->toBe(1883)
            ->and($config->qos())->toBe(0);
    });

    test('it creates config from connection name', function () {
        config(['mqtt-broadcast.connections.test' => $this->validConfig]);

        $config = MqttConnectionConfig::fromConnection('test');

        expect($config)->toBeInstanceOf(MqttConnectionConfig::class)
            ->and($config->host())->toBe('mqtt.example.com');
    });

    test('it throws exception for non-existent connection', function () {
        MqttConnectionConfig::fromConnection('non-existent');
    })->throws(MqttBroadcastException::class, 'MQTT connection [non-existent] is not configured');

    test('it applies default values for optional fields', function () {
        $minimal = [
            'host' => 'localhost',
            'port' => 1883,
        ];

        $config = MqttConnectionConfig::fromArray($minimal);

        expect($config->qos())->toBe(0)
            ->and($config->retain())->toBeFalse()
            ->and($config->prefix())->toBe('')
            ->and($config->cleanSession())->toBeFalse()
            ->and($config->timeout())->toBe(3)
            ->and($config->aliveInterval())->toBe(60)
            ->and($config->useTls())->toBeFalse()
            ->and($config->selfSignedAllowed())->toBeTrue();
    });
});

describe('MqttConnectionConfig → Required Fields', function () {
    test('it throws exception when host is missing', function () {
        unset($this->validConfig['host']);

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'is missing required key [host]');

    test('it throws exception when host is empty string', function () {
        $this->validConfig['host'] = '';

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'is missing required key [host]');

    test('it throws exception when host is null', function () {
        $this->validConfig['host'] = null;

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'is missing required key [host]');

    test('it throws exception when port is missing', function () {
        unset($this->validConfig['port']);

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'is missing required key [port]');
});

describe('MqttConnectionConfig → Port Validation', function () {
    test('it accepts valid port numbers', function ($port) {
        $this->validConfig['port'] = $port;

        $config = MqttConnectionConfig::fromArray($this->validConfig);

        expect($config->port())->toBe($port);
    })->with([1, 80, 443, 1883, 8883, 65535]);

    test('it throws exception for port below 1', function () {
        $this->validConfig['port'] = 0;

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'must be between 1 and 65535');

    test('it throws exception for port above 65535', function () {
        $this->validConfig['port'] = 65536;

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'must be between 1 and 65535');

    test('it throws exception for non-numeric port', function () {
        $this->validConfig['port'] = 'invalid';

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'invalid port: must be integer');

    test('it accepts numeric string port and converts to int', function () {
        $this->validConfig['port'] = '1883';

        $config = MqttConnectionConfig::fromArray($this->validConfig);

        expect($config->port())->toBe(1883)
            ->and($config->port())->toBeInt();
    });
});

describe('MqttConnectionConfig → QoS Validation', function () {
    test('it accepts valid QoS levels', function ($qos) {
        $this->validConfig['qos'] = $qos;

        $config = MqttConnectionConfig::fromArray($this->validConfig);

        expect($config->qos())->toBe($qos);
    })->with([0, 1, 2]);

    test('it throws exception for invalid QoS', function ($qos) {
        $this->validConfig['qos'] = $qos;

        MqttConnectionConfig::fromArray($this->validConfig);
    })->with([-1, 3, 10, 99])
        ->throws(MqttBroadcastException::class, 'invalid qos: must be 0, 1, or 2');

    test('it throws exception for non-numeric QoS', function () {
        $this->validConfig['qos'] = 'invalid';

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'invalid qos: must be integer');
});

describe('MqttConnectionConfig → Timeout Validation', function () {
    test('it accepts valid timeout values', function ($timeout) {
        $this->validConfig['timeout'] = $timeout;

        $config = MqttConnectionConfig::fromArray($this->validConfig);

        expect($config->timeout())->toBe($timeout);
    })->with([1, 3, 5, 10, 30, 60]);

    test('it throws exception for zero timeout', function () {
        $this->validConfig['timeout'] = 0;

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'invalid timeout: must be greater than 0');

    test('it throws exception for negative timeout', function () {
        $this->validConfig['timeout'] = -1;

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'invalid timeout: must be greater than 0');

    test('it throws exception for non-numeric timeout', function () {
        $this->validConfig['timeout'] = 'invalid';

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'invalid timeout: must be integer');
});

describe('MqttConnectionConfig → Alive Interval Validation', function () {
    test('it accepts valid alive interval values', function ($interval) {
        $this->validConfig['alive_interval'] = $interval;

        $config = MqttConnectionConfig::fromArray($this->validConfig);

        expect($config->aliveInterval())->toBe($interval);
    })->with([1, 30, 60, 120, 300]);

    test('it throws exception for zero alive interval', function () {
        $this->validConfig['alive_interval'] = 0;

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'invalid alive_interval: must be greater than 0');

    test('it throws exception for negative alive interval', function () {
        $this->validConfig['alive_interval'] = -1;

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'invalid alive_interval: must be greater than 0');
});

describe('MqttConnectionConfig → Authentication Validation', function () {
    test('it requires username when auth is enabled', function () {
        $this->validConfig['auth'] = true;
        unset($this->validConfig['username']);

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'auth enabled but missing or invalid username');

    test('it requires password when auth is enabled', function () {
        $this->validConfig['auth'] = true;
        $this->validConfig['username'] = 'user';
        unset($this->validConfig['password']);

        MqttConnectionConfig::fromArray($this->validConfig);
    })->throws(MqttBroadcastException::class, 'auth enabled but missing or invalid password');

    test('it accepts valid credentials when auth is enabled', function () {
        $this->validConfig['auth'] = true;
        $this->validConfig['username'] = 'test-user';
        $this->validConfig['password'] = 'test-pass';

        $config = MqttConnectionConfig::fromArray($this->validConfig);

        expect($config->requiresAuth())->toBeTrue()
            ->and($config->username())->toBe('test-user')
            ->and($config->password())->toBe('test-pass');
    });

    test('it allows empty username/password when auth is disabled', function () {
        $this->validConfig['auth'] = false;
        $this->validConfig['username'] = null;
        $this->validConfig['password'] = null;

        $config = MqttConnectionConfig::fromArray($this->validConfig);

        expect($config->requiresAuth())->toBeFalse()
            ->and($config->username())->toBeNull()
            ->and($config->password())->toBeNull();
    });
});

describe('MqttConnectionConfig → Getters', function () {
    test('it provides type-safe getters for all fields', function () {
        $config = MqttConnectionConfig::fromArray($this->validConfig);

        expect($config->host())->toBeString()
            ->and($config->port())->toBeInt()
            ->and($config->requiresAuth())->toBeBool()
            ->and($config->qos())->toBeInt()
            ->and($config->retain())->toBeBool()
            ->and($config->prefix())->toBeString()
            ->and($config->cleanSession())->toBeBool()
            ->and($config->aliveInterval())->toBeInt()
            ->and($config->timeout())->toBeInt()
            ->and($config->useTls())->toBeBool()
            ->and($config->selfSignedAllowed())->toBeBool();
    });

    test('it returns correct values from config', function () {
        $config = MqttConnectionConfig::fromArray($this->validConfig);

        expect($config->host())->toBe('mqtt.example.com')
            ->and($config->port())->toBe(1883)
            ->and($config->prefix())->toBe('myapp/')
            ->and($config->clientId())->toBe('test-client');
    });
});

describe('MqttConnectionConfig → Immutability', function () {
    test('it returns same values on multiple calls', function () {
        $config = MqttConnectionConfig::fromArray($this->validConfig);

        $host1 = $config->host();
        $host2 = $config->host();
        $port1 = $config->port();
        $port2 = $config->port();

        expect($host1)->toBe($host2)
            ->and($port1)->toBe($port2);
    });
});

describe('MqttConnectionConfig → Array Conversion', function () {
    test('it converts back to array for backward compatibility', function () {
        $config = MqttConnectionConfig::fromArray($this->validConfig);

        $array = $config->toArray();

        expect($array)->toBeArray()
            ->and($array['host'])->toBe('mqtt.example.com')
            ->and($array['port'])->toBe(1883)
            ->and($array['qos'])->toBe(0)
            ->and($array)->toHaveKeys([
                'host',
                'port',
                'auth',
                'username',
                'password',
                'qos',
                'retain',
                'prefix',
                'clean_session',
                'clientId',
                'alive_interval',
                'timeout',
                'use_tls',
                'self_signed_allowed',
            ]);
    });
});
