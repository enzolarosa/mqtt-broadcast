<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Models;

use enzolarosa\MqttBroadcast\Traits\Models\ExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MqttLogger extends Model
{
    use ExternalId;
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

    public function __construct()
    {
        $dbConnection = config('mqtt-broadcast.logs.connection');
        if ($dbConnection) {
            $this->connection = $dbConnection;
        }

        $this->table = config('mqtt-broadcast.logs.table');

        parent::__construct();
    }
}
