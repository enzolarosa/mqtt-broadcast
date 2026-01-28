<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Models;

use enzolarosa\MqttBroadcast\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MqttLogger extends Model
{
    use HasExternalId;
    use HasFactory;

    protected $fillable = [
        'broker',
        'topic',
        'message',
    ];

    protected $casts = [
        'message' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('mqtt-broadcast.logs.connection')
            ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return config('mqtt-broadcast.logs.table', 'mqtt_loggers');
    }
}
