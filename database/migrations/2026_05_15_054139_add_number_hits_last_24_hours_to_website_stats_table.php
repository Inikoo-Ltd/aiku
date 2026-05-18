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
        Schema::table('website_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('website_stats', 'number_hits_last_24_hours')) {
                $table->unsignedInteger('number_hits_last_24_hours')->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('website_stats', function (Blueprint $table) {
            if (Schema::hasColumn('website_stats', 'number_hits_last_24_hours')) {
                $table->dropColumn(['number_hits_last_24_hours']);
            }
        });
    }
};
