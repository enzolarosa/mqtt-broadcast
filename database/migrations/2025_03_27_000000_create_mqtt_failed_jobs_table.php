<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('mqtt-broadcast.failed_jobs.connection'))
            ->create('mqtt_failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->uuid('external_id')->unique();

                $table->string('broker')->default('default')->index();
                $table->string('topic');
                $table->longText('message')->nullable();
                $table->tinyInteger('qos')->default(0);
                $table->boolean('retain')->default(false);

                $table->text('exception');
                $table->timestamp('failed_at');
                $table->timestamp('retried_at')->nullable();
                $table->unsignedInteger('retry_count')->default(0);

                $table->timestamps();
            });
    }

    public function down(): void
    {
        Schema::connection(config('mqtt-broadcast.failed_jobs.connection'))
            ->dropIfExists('mqtt_failed_jobs');
    }
};
