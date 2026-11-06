<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:09
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
            $table->unsignedSmallInteger('created_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->unsignedSmallInteger('approved_by')->nullable();
        });

        Schema::table('org_agents', function (Blueprint $table) {
            $table->jsonb('settings')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'approved_at', 'approved_by']);
        });

        Schema::table('org_agents', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
