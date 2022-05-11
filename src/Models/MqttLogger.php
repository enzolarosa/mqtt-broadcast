<?php

namespace enzolarosa\MqttBroadcast\Models;

use enzolarosa\MqttBroadcast\Traits\Models\ExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MqttLogger extends Model
{
    use HasFactory, SoftDeletes, ExternalId;

    public function __construct()
    {
        $this->connection = config('mqtt-broadcast.logs.connection');
        $this->table = config('mqtt-broadcast.logs.table');

        parent::__construct();
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
