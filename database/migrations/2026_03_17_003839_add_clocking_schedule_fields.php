<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('clockings', function (Blueprint $table) {
            $table->unsignedBigInteger('work_schedule_id')->nullable()->after('subject_id');
            $table->foreign('work_schedule_id')->references('id')->on('work_schedules')->onDelete('set null');
            $table->boolean('is_late')->default(false)->after('clocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('clockings', function (Blueprint $table) {
            $table->dropForeign(['work_schedule_id']);
            $table->dropColumn(['work_schedule_id', 'is_late']);
        });
    }
};
