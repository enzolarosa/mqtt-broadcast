<?php

namespace enzolarosa\MqttBroadcast\Models;

use enzolarosa\MqttBroadcast\Traits\Models\ExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MqttLogger extends Model
{
    use HasFactory;
    use ExternalId;

    public function __construct()
    {
        $dbConnection = config('mqtt-broadcast.logs.connection');
        if ($dbConnection) {
            $this->connection = $dbConnection;
        }

        $this->table = config('mqtt-broadcast.logs.table');

        parent::__construct();
    }

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
}
