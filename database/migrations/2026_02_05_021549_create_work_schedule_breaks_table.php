<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('work_schedule_breaks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('work_schedule_day_id');
            $table->foreign('work_schedule_day_id')
                ->references('id')
                ->on('work_schedule_days')
                ->cascadeOnDelete();
            $table->string('break_name', 50)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_paid')->default(false);
            $table->index('work_schedule_day_id', 'idx_schedule_day_breaks');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('work_schedule_breaks');
    }
};
