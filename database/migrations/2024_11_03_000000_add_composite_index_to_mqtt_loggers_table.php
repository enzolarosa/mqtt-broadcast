<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add composite index on (broker, topic, created_at) for improved query performance.
     * This index optimizes common query patterns:
     * - WHERE broker = ? AND topic LIKE ? ORDER BY created_at DESC
     * - WHERE broker = ? ORDER BY created_at DESC
     */
    public function up(): void
    {
        Schema::connection(config('mqtt-broadcast.logs.connection'))
            ->table('mqtt_loggers', function (Blueprint $table) {
                // Drop existing single broker index (line 18 in create migration)
                // to avoid index duplication
                $table->dropIndex(['broker']);

                // Add composite index for common query patterns
                $table->index(['broker', 'topic', 'created_at'], 'mqtt_loggers_broker_topic_created_at_index');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('mqtt-broadcast.logs.connection'))
            ->table('mqtt_loggers', function (Blueprint $table) {
                // Drop composite index
                $table->dropIndex('mqtt_loggers_broker_topic_created_at_index');

                // Restore original single broker index
                $table->index('broker');
            });
    }
};
