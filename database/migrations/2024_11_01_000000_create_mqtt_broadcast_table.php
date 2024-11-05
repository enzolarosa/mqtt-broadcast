<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection(config('mqtt-broadcast.logs.connection'))
            ->create(config('mqtt-broadcast.logs.table'), function (Blueprint $table) {
                $table->id();
                $table->uuid('external_id')->unique();

                $table->string('broker')->default('remote')->index();
                $table->string('topic')->nullable();
                $table->longText('message')->nullable();

                $table->timestamps();
            });
    }
};
