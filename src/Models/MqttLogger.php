<?php

namespace enzolarosa\MqttBroadcast\Models;

use enzolarosa\MqttBroadcast\Traits\Models\ExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MqttLogger extends Model
{
    use HasFactory;
    use SoftDeletes;
    use ExternalId;

    public function __construct()
    {
        $this->connection = config('mqtt-broadcast.logs.connection');
        $this->table = config('mqtt-broadcast.logs.table');
    }

    protected $fillable = [
        'broker',
        'topic',
        'message'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    }
