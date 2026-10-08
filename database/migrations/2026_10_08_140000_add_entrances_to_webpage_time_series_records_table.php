<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpage_time_series_records', function (Blueprint $table) {
            $table->integer('entrances')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('webpage_time_series_records', function (Blueprint $table) {
            $table->dropColumn('entrances');
        });
    }
};
