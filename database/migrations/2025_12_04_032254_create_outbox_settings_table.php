<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('outbox_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outbox_id')->unique();
            $table->foreign('outbox_id')->references('id')->on('outboxes');
            $table->unsignedInteger('days_after')->default(0);
            $table->timeTz('send_time')->default('15:00:00')->timezone('UTC');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('outbox_settings');
    }
};
