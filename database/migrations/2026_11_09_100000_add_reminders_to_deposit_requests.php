<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-09 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->timestampTz('last_reminded_at')->nullable();
            $table->unsignedSmallInteger('last_reminded_by')->nullable();
            $table->unsignedSmallInteger('number_reminders')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->dropColumn(['last_reminded_at', 'last_reminded_by', 'number_reminders']);
        });
    }
};
