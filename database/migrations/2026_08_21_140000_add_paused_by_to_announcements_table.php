<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->unsignedBigInteger('paused_by_announcement_id')->nullable();
            $table->timestampTz('paused_until')->nullable();
            $table->foreign('paused_by_announcement_id')->references('id')->on('announcements')->nullOnDelete();
        });

        DB::statement("alter table announcements alter column schedule_at type timestamp(0) with time zone using nullif(schedule_at, '')::timestamptz");
        DB::statement("alter table announcements alter column schedule_finish_at type timestamp(0) with time zone using nullif(schedule_finish_at, '')::timestamptz");
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['paused_by_announcement_id']);
            $table->dropColumn(['paused_by_announcement_id', 'paused_until']);
        });

        DB::statement('alter table announcements alter column schedule_at type varchar(255) using schedule_at::text');
        DB::statement('alter table announcements alter column schedule_finish_at type varchar(255) using schedule_finish_at::text');
    }
};
