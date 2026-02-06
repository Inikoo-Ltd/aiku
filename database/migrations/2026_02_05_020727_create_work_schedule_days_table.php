<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('work_schedule_id');
            $table->foreign('work_schedule_id')
                ->references('id')
                ->on('work_schedules')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_working_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->index(['work_schedule_id', 'day_of_week'], 'idx_schedule_day');
            $table->unique(['work_schedule_id', 'day_of_week'], 'uniq_schedule_day');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('work_schedule_days');
    }
};
