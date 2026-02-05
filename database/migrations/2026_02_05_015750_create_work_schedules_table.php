<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('schedulable_type', 50);
            $table->unsignedBigInteger('schedulable_id');
            $table->string('name', 100);
            $table->unsignedBigInteger('timezone_id')->nullable();
            $table->foreign('timezone_id')
                ->references('id')
                ->on('timezones')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->index(['schedulable_type', 'schedulable_id'], 'idx_schedulable');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
