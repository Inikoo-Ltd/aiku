<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->string('health_rank')->nullable()->default(null)->after('status');
        });

        Schema::table('trade_unit_families', function (Blueprint $table) {
            $table->string('health_rank')->nullable()->default(null)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropColumn('health_rank');
        });

        Schema::table('trade_unit_families', function (Blueprint $table) {
            $table->dropColumn('health_rank');
        });
    }
};
