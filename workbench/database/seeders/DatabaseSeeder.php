<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use enzolarosa\MqttBroadcast\Models\BrokerProcess;
use enzolarosa\MqttBroadcast\Models\FailedMqttJob;
use enzolarosa\MqttBroadcast\Models\MqttLogger;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed broker processes (simulates a running supervisor)
        BrokerProcess::create([
            'name' => 'default',
            'connection' => 'default',
            'pid' => 12345,
            'working' => true,
            'started_at' => now()->subHours(3),
            'last_heartbeat_at' => now()->subSeconds(5),
        ]);

        BrokerProcess::create([
            'name' => 'sensors',
            'connection' => 'default',
            'pid' => 12346,
            'working' => true,
            'started_at' => now()->subHours(1),
            'last_heartbeat_at' => now()->subSeconds(8),
        ]);

        // Seed message logs
        $topics = ['sensors/temperature', 'sensors/humidity', 'alerts/motion', 'devices/status'];
        $brokers = ['default', 'sensors'];

        for ($i = 0; $i < 30; $i++) {
            MqttLogger::create([
                'broker' => $brokers[array_rand($brokers)],
                'topic' => $topics[array_rand($topics)],
                'message' => json_encode([
                    'value' => round(mt_rand(1800, 3500) / 100, 2),
                    'unit' => 'celsius',
                    'device_id' => 'sensor-'.mt_rand(1, 10),
                    'timestamp' => now()->subMinutes($i * 2)->toIso8601String(),
                ]),
                'created_at' => now()->subMinutes($i * 2),
                'updated_at' => now()->subMinutes($i * 2),
            ]);
        }

        // Seed failed jobs (DLQ demo data)
        $exceptions = [
            "enzolarosa\\MqttBroadcast\\Exceptions\\MqttBroadcastException: Connection to broker 'default' failed: Connection refused\n#0 src/Factories/MqttClientFactory.php(45): create()\n#1 src/Jobs/MqttMessageJob.php(67): handle()",
            "PhpMqtt\\Client\\Exceptions\\ConnectingToBrokerFailedException: Could not connect to broker: 127.0.0.1:1883\n#0 src/Jobs/MqttMessageJob.php(76): connect()",
            "enzolarosa\\MqttBroadcast\\Exceptions\\RateLimitExceededException: Rate limit exceeded for broker 'default': 100/min\n#0 src/Support/RateLimitService.php(88): attempt()",
        ];

        $failedJobsData = [
            ['topic' => 'sensors/temperature', 'broker' => 'default', 'message' => ['value' => 23.5, 'unit' => 'celsius'], 'qos' => 1, 'failed_at' => now()->subMinutes(15)],
            ['topic' => 'alerts/motion', 'broker' => 'default', 'message' => ['triggered' => true, 'zone' => 'entrance'], 'qos' => 2, 'failed_at' => now()->subMinutes(32)],
            ['topic' => 'devices/status', 'broker' => 'sensors', 'message' => ['online' => false, 'device_id' => 'sensor-7'], 'qos' => 0, 'failed_at' => now()->subHours(1)],
            ['topic' => 'sensors/humidity', 'broker' => 'default', 'message' => ['value' => 67.2, 'unit' => '%'], 'qos' => 1, 'failed_at' => now()->subHours(2), 'retry_count' => 2, 'retried_at' => now()->subHours(1)],
        ];

        foreach ($failedJobsData as $i => $jobData) {
            FailedMqttJob::create([
                'broker' => $jobData['broker'],
                'topic' => $jobData['topic'],
                'message' => $jobData['message'],
                'qos' => $jobData['qos'],
                'retain' => false,
                'exception' => $exceptions[$i % count($exceptions)],
                'failed_at' => $jobData['failed_at'],
                'retried_at' => $jobData['retried_at'] ?? null,
                'retry_count' => $jobData['retry_count'] ?? 0,
                'created_at' => $jobData['failed_at'],
                'updated_at' => $jobData['failed_at'],
            ]);
        }
    }
}
