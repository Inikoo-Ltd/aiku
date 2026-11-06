<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:48
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
            $table->timestampTz('rejected_at')->nullable();
            $table->unsignedSmallInteger('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->dropColumn(['rejected_at', 'rejected_by', 'rejection_reason']);
        });
    }
};
