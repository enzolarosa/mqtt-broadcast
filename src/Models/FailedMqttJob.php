<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Models;

use enzolarosa\MqttBroadcast\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedMqttJob extends Model
{
    use HasExternalId;
    use HasFactory;

    protected $fillable = [
        'broker',
        'topic',
        'message',
        'qos',
        'retain',
        'exception',
        'failed_at',
        'retried_at',
        'retry_count',
    ];

    protected $casts = [
        'message' => 'json',
        'retain' => 'boolean',
        'qos' => 'integer',
        'retry_count' => 'integer',
        'failed_at' => 'datetime',
        'retried_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('mqtt-broadcast.failed_jobs.connection')
            ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return config('mqtt-broadcast.failed_jobs.table', 'mqtt_failed_jobs');
    }
}
