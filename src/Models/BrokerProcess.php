<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrokerProcess extends Model
{
    use HasFactory;

    protected $table = 'mqtt_brokers';

    protected $fillable = [
        'name',
        'connection',
        'pid',
        'started_at',
        'last_heartbeat_at',
        'working',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'connection' => 'string',
            'pid' => 'integer',
            'started_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'working' => 'boolean',
        ];
    }
}
