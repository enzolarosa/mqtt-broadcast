<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Models;

use Illuminate\Database\Eloquent\Model;

class Brokers extends Model
{
    protected $table = 'mqtt_brokers';

    protected $fillable = [
        'name',
        'connection',
        'pid',
        'started_at',
        'working',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'connection' => 'string',
            'pid' => 'integer',
            'started_at' => 'datetime',
            'working' => 'boolean',
        ];
    }
}
