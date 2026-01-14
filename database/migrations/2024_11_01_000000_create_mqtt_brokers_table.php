<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mqtt_brokers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('connection');
            $table->unsignedInteger('pid')->nullable();
            $table->boolean('working')->default(false);
            $table->dateTimeTz('started_at')->nullable();

            $table->timestamps();
        });
    }
};
