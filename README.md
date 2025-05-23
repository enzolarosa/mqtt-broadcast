
[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/support-ukraine.svg?t=1" />](https://supportukrainenow.org)

# Use the mqtt power in your projects

[![Latest Version on Packagist](https://img.shields.io/packagist/v/enzolarosa/mqtt-broadcast.svg?style=flat-square)](https://packagist.org/packages/enzolarosa/mqtt-broadcast)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/enzolarosa/mqtt-broadcast/run-tests?label=tests)](https://github.com/enzolarosa/mqtt-broadcast/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/enzolarosa/mqtt-broadcast/Check%20&%20fix%20styling?label=code%20style)](https://github.com/enzolarosa/mqtt-broadcast/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/enzolarosa/mqtt-broadcast.svg?style=flat-square)](https://packagist.org/packages/enzolarosa/mqtt-broadcast)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require enzolarosa/mqtt-broadcast
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="mqtt-broadcast-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="mqtt-broadcast-config"
```

This is the contents of the published config file:

```php
return [
    'logs' => [
        'enable' => env('MQTT_LOG_ENABLE', true),
        'connection' => env('MQTT_LOG_CONNECTION', 'logs'),
        'table' => env('MQTT_LOG_TABLE', 'mqtt_loggers'),
    ],

    'password' => env('MQTT_MASTER_PASS', Illuminate\Support\Str::random(32)),

    'connections' => [
        'local' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => env('MQTT_PORT', '1883'),
            'user' => env('MQTT_USER'),
            'password' => env('MQTT_PASSWORD'),
        ],

        'remote' => [
            'host' => env('MQTT_REMOTE_HOST', '127.0.0.1'),
            'port' => env('MQTT_REMOTE_PORT', '1883'),
            'user' => env('MQTT_REMOTE_USER'),
            'password' => env('MQTT_REMOTE_PASSWORD'),
        ],
    ]
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="mqtt-broadcast-views"
```

## Usage

```php
$mqttBroadcast = new enzolarosa\MqttBroadcast();
echo $mqttBroadcast->echoPhrase('Hello, enzolarosa!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [enzolarosa](https://github.com/enzolarosa)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
